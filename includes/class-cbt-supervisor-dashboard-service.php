<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Live_Attempt_Roster_Index')) {
    require_once __DIR__ . '/class-cbt-live-attempt-roster-index.php';
}

if (!class_exists('CBT_Security_Log')) {
    require_once __DIR__ . '/class-cbt-security-log.php';
}

if (!class_exists('CBT_Admin_Results_Service')) {
    require_once dirname(__DIR__) . '/admin/class-cbt-admin-results-service.php';
}

final class CBT_Supervisor_Dashboard_Service
{
    private const DEFAULT_ROSTER_PER_PAGE = 8;
    private const DEFAULT_ATTEMPTS_PER_PAGE = 8;
    private const MUST_WATCH_LIMIT = 12;

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_dashboard_payload(int $user_id, string $role, array $query = []): array
    {
        $scope = self::resolve_scope($user_id, $role);
        $filters = self::normalize_filters($query);
        $status_snapshot = self::get_security_status_snapshot();
        $live_roster = self::build_live_roster_context($scope, $filters);
        $must_watch = self::build_must_watch_context($scope, $filters);
        $monitoring_attempts = self::build_monitoring_attempts_context($scope, $filters);

        return [
            'ok' => true,
            'scope' => $scope,
            'filters' => $filters,
            'filter_options' => [
                'exams' => self::load_accessible_exam_options($scope),
                'kelas' => self::load_accessible_kelas_options($scope),
            ],
            'summary_cards' => [
                [
                    'key' => 'live_roster',
                    'label' => 'Live Roster',
                    'value' => max(0, (int) ($live_roster['total'] ?? 0)),
                    'meta' => !empty($live_roster['available']) ? 'Attempt aktif realtime' : 'Belum tersedia',
                ],
                [
                    'key' => 'must_watch',
                    'label' => 'Must Watch',
                    'value' => max(0, (int) ($must_watch['total'] ?? 0)),
                    'meta' => 'Attempt berisiko yang perlu dipantau',
                ],
                [
                    'key' => 'monitoring_attempts',
                    'label' => 'Monitoring Attempts',
                    'value' => max(0, (int) ($monitoring_attempts['total'] ?? 0)),
                    'meta' => 'Attempt berjalan dan selesai pada scope aktif',
                ],
                [
                    'key' => 'submit_watchlist',
                    'label' => 'Submit Watchlist',
                    'value' => max(0, (int) (($monitoring_attempts['submit_watchlist']['total'] ?? 0))),
                    'meta' => 'Submit recovery yang belum selesai',
                ],
            ],
            'status_snapshot' => $status_snapshot,
            'live_roster' => $live_roster,
            'must_watch' => $must_watch,
            'monitoring_attempts' => [
                'items' => (array) ($monitoring_attempts['items'] ?? []),
                'total' => max(0, (int) ($monitoring_attempts['total'] ?? 0)),
                'pagination' => (array) ($monitoring_attempts['pagination'] ?? []),
                'note' => (string) ($monitoring_attempts['note'] ?? ''),
            ],
            'submit_health' => (array) ($monitoring_attempts['submit_health'] ?? []),
            'submit_watchlist' => (array) ($monitoring_attempts['submit_watchlist'] ?? []),
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public static function reset_login_for_attempt(int $attempt_id, int $user_id, string $role)
    {
        if (!class_exists('CBT_Admin_Results_Service')) {
            return new WP_Error('service_unavailable', 'Results service tidak tersedia.', ['status' => 500]);
        }

        return CBT_Admin_Results_Service::reset_login_for_attempt_with_scope(
            $attempt_id,
            CBT_Auth::is_admin_role($role),
            $user_id,
            $user_id,
            'supervisor_dashboard'
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function resolve_scope(int $user_id, string $role): array
    {
        $normalized_role = sanitize_key($role);
        $is_admin_scope = CBT_Auth::is_admin_role($normalized_role);

        return [
            'user_id' => max(0, $user_id),
            'role' => $normalized_role,
            'role_label' => $is_admin_scope ? 'Admin' : 'Guru',
            'is_admin_scope' => $is_admin_scope,
            'teacher_scope_user_id' => $is_admin_scope ? 0 : max(0, $user_id),
            'scope_label' => $is_admin_scope ? 'Semua exam' : 'Exam milik guru aktif',
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private static function normalize_filters(array $query): array
    {
        $tab = sanitize_key((string) ($query['tab'] ?? 'live_roster'));
        if (!in_array($tab, ['live_roster', 'must_watch', 'monitoring_attempts'], true)) {
            $tab = 'live_roster';
        }

        $status = sanitize_key((string) ($query['status'] ?? ''));
        if (!in_array($status, ['', 'in_progress', 'completed'], true)) {
            $status = '';
        }

        return [
            'tab' => $tab,
            'exam_id' => max(0, (int) ($query['exam_id'] ?? 0)),
            'kelas' => trim(sanitize_text_field((string) ($query['kelas'] ?? ''))),
            'student_keyword' => trim(sanitize_text_field((string) ($query['student_keyword'] ?? ''))),
            'status' => $status,
            'roster_page' => max(1, (int) ($query['roster_page'] ?? 1)),
            'attempts_page' => max(1, (int) ($query['attempts_page'] ?? 1)),
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private static function build_live_roster_context(array $scope, array $filters): array
    {
        $available = class_exists('CBT_Live_Attempt_Roster_Index') && CBT_Live_Attempt_Roster_Index::is_available();
        $groups = class_exists('CBT_Live_Attempt_Roster_Index')
            ? CBT_Live_Attempt_Roster_Index::get_grouped_payloads([
                'teacher_id' => max(0, (int) ($scope['teacher_scope_user_id'] ?? 0)),
            ])
            : [];

        $items = [];
        foreach ((array) $groups as $group) {
            foreach ((array) ($group['attempts'] ?? []) as $row) {
                if (!is_array($row) || !self::matches_student_filters($row, $filters)) {
                    continue;
                }

                $risk_score = (float) ($row['risk_score'] ?? 0.0);
                $items[] = [
                    'attempt_id' => (int) ($row['attempt_id'] ?? 0),
                    'exam_id' => (int) ($row['exam_id'] ?? 0),
                    'exam_title' => (string) ($row['exam_title'] ?? '-'),
                    'student_id' => (int) ($row['student_id'] ?? 0),
                    'student_name' => (string) ($row['student_name'] ?? '-'),
                    'student_login' => (string) ($row['student_login'] ?? ''),
                    'student_kelas' => (string) ($row['student_kode_kelas'] ?? ''),
                    'student_ruang' => (string) ($row['student_kode_ruang'] ?? ''),
                    'presence_status' => (string) ($row['presence_status'] ?? ''),
                    'presence_label' => self::format_presence_status((string) ($row['presence_status'] ?? '')),
                    'connection_status' => (string) ($row['connection_status'] ?? ''),
                    'visibility_state' => (string) ($row['visibility_state'] ?? ''),
                    'has_focus' => array_key_exists('has_focus', $row) ? $row['has_focus'] : null,
                    'pending_sync_count' => max(0, (int) ($row['pending_sync_count'] ?? 0)),
                    'heartbeat_lost_active' => !empty($row['heartbeat_lost_active']),
                    'risk_tone' => (string) ($row['risk_tone'] ?? ''),
                    'risk_label' => self::format_risk_tone((string) ($row['risk_tone'] ?? '')),
                    'risk_score' => $risk_score,
                    'risk_score_label' => self::format_risk_score($risk_score),
                    'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
                ];
            }
        }

        usort($items, static function (array $left, array $right): int {
            $riskCompare = (float) ($right['risk_score'] ?? 0.0) <=> (float) ($left['risk_score'] ?? 0.0);
            if ($riskCompare !== 0) {
                return $riskCompare;
            }

            return strcmp((string) ($right['last_seen_at'] ?? ''), (string) ($left['last_seen_at'] ?? ''));
        });

        return array_merge(
            [
                'available' => $available,
                'note' => $available
                    ? 'Roster live menampilkan attempt aktif terbaru pada scope pengawas.'
                    : 'Roster live belum tersedia. Pastikan Redis roster aktif untuk observability realtime.',
            ],
            self::paginate_items($items, (int) ($filters['roster_page'] ?? 1), self::DEFAULT_ROSTER_PER_PAGE)
        );
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private static function build_must_watch_context(array $scope, array $filters): array
    {
        $rows = class_exists('CBT_Security_Log')
            ? CBT_Security_Log::get_must_watch_attempts(self::MUST_WATCH_LIMIT, [
                'teacher_id' => max(0, (int) ($scope['teacher_scope_user_id'] ?? 0)),
            ])
            : [];

        $items = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row) || !self::matches_student_filters($row, $filters)) {
                continue;
            }

            $risk_score = (float) ($row['risk_score'] ?? 0.0);
            $items[] = [
                'attempt_id' => (int) ($row['attempt_id'] ?? 0),
                'exam_id' => (int) ($row['exam_id'] ?? 0),
                'exam_title' => (string) ($row['exam_title'] ?? '-'),
                'student_id' => (int) ($row['student_id'] ?? 0),
                'student_name' => (string) ($row['student_name'] ?? '-'),
                'student_login' => (string) ($row['student_login'] ?? ''),
                'student_kelas' => (string) ($row['student_kode_kelas'] ?? ''),
                'student_ruang' => (string) ($row['student_kode_ruang'] ?? ''),
                'risk_score' => $risk_score,
                'risk_score_label' => self::format_risk_score($risk_score),
                'risk_label' => (string) ($row['risk_label'] ?? self::format_risk_tone((string) ($row['risk_tone'] ?? 'watch'))),
                'primary_event_label' => (string) ($row['primary_event_label'] ?? 'Aktivitas diamati'),
                'last_event_at' => (string) ($row['last_event_at'] ?? ''),
                'presence_status' => (string) ($row['presence_status'] ?? ''),
                'presence_label' => self::format_presence_status((string) ($row['presence_status'] ?? '')),
                'top_indicators' => array_values(array_filter(array_map('strval', (array) ($row['top_indicators'] ?? [])))),
            ];
        }

        return [
            'items' => $items,
            'total' => count($items),
            'note' => empty($items)
                ? 'Belum ada attempt yang melewati ambang must watch pada scope aktif.'
                : 'Pantau attempt dengan skor risiko tertinggi lebih dulu.',
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private static function build_monitoring_attempts_context(array $scope, array $filters): array
    {
        if (!class_exists('CBT_Admin_Results_Service')) {
            return [
                'items' => [],
                'total' => 0,
                'pagination' => self::empty_pagination(),
                'note' => 'Results service belum tersedia.',
                'submit_health' => ['available' => false, 'note' => 'Submit telemetry belum tersedia.'],
                'submit_watchlist' => ['available' => false, 'items' => [], 'total' => 0, 'display_count' => 0],
            ];
        }

        $context = CBT_Admin_Results_Service::build_frontend_monitoring_context([
            'is_admin_scope' => !empty($scope['is_admin_scope']),
            'current_user_id' => max(0, (int) ($scope['user_id'] ?? 0)),
            'selected_exam_id' => max(0, (int) ($filters['exam_id'] ?? 0)),
            'selected_status' => (string) ($filters['status'] ?? ''),
            'selected_kelas' => (string) ($filters['kelas'] ?? ''),
            'student_keyword' => (string) ($filters['student_keyword'] ?? ''),
            'current_page' => max(1, (int) ($filters['attempts_page'] ?? 1)),
            'per_page' => self::DEFAULT_ATTEMPTS_PER_PAGE,
        ]);

        return [
            'items' => (array) ($context['items'] ?? []),
            'total' => max(0, (int) ($context['total'] ?? 0)),
            'pagination' => (array) ($context['pagination'] ?? self::empty_pagination()),
            'note' => !empty($context['items'])
                ? 'Monitoring attempts menampilkan score, progress jawaban, dan status finalisasi terbaru.'
                : 'Belum ada attempt yang cocok dengan filter aktif.',
            'submit_health' => (array) ($context['submit_health'] ?? []),
            'submit_watchlist' => (array) ($context['submit_watchlist'] ?? []),
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>
     */
    private static function load_accessible_exam_options(array $scope): array
    {
        global $wpdb;

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $where = 'WHERE title NOT LIKE %s';
        $params = ['Bank Soal - %'];
        if (empty($scope['is_admin_scope'])) {
            $where .= ' AND created_by = %d';
            $params[] = max(0, (int) ($scope['user_id'] ?? 0));
        }

        $sql = $wpdb->prepare(
            "SELECT id, title
             FROM {$exam_table}
             {$where}
             ORDER BY id DESC",
            $params
        );

        return array_values(array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'label' => (string) ($row['title'] ?? '-'),
            ];
        }, (array) $wpdb->get_results($sql, ARRAY_A)));
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,string>
     */
    private static function load_accessible_kelas_options(array $scope): array
    {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $where = 'WHERE kelas_meta.meta_value IS NOT NULL AND kelas_meta.meta_value <> ""';
        $params = [];
        if (empty($scope['is_admin_scope'])) {
            $where .= ' AND e.created_by = %d';
            $params[] = max(0, (int) ($scope['user_id'] ?? 0));
        }

        $sql = "SELECT DISTINCT kelas_meta.meta_value AS kelas
                FROM {$attempt_table} a
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                INNER JOIN {$wpdb->users} u ON u.ID = a.student_id
                LEFT JOIN (
                    SELECT user_id, MAX(meta_value) AS meta_value
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = 'kode_kelas'
                    GROUP BY user_id
                ) kelas_meta ON kelas_meta.user_id = u.ID
                {$where}
                ORDER BY kelas_meta.meta_value ASC";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return array_values(array_filter(array_map(static function (array $row): string {
            return trim((string) ($row['kelas'] ?? ''));
        }, (array) $wpdb->get_results($sql, ARRAY_A))));
    }

    /**
     * @return array<string,mixed>
     */
    private static function get_security_status_snapshot(): array
    {
        if (class_exists('CBT_Security_Event_Ingest')) {
            return (array) CBT_Security_Event_Ingest::get_status_snapshot();
        }

        $live_available = class_exists('CBT_Security_Live_Counters') && CBT_Security_Live_Counters::is_available();
        return [
            'mode' => $live_available ? 'redis_live' : 'mysql_fallback',
            'status_label' => $live_available
                ? 'Live Redis • Ingest direct MySQL • Persist direct MySQL'
                : 'Live MySQL fallback • Ingest direct MySQL • Persist direct MySQL',
            'live_label' => $live_available ? 'Live Redis' : 'Live MySQL fallback',
            'ingest_label' => 'Ingest direct MySQL',
            'persist_label' => 'Persist direct MySQL',
            'backlog_count' => 0,
            'dead_letter_count' => 0,
            'worker_scheduled' => 0,
            'next_flush_at' => '',
            'last_flush_at' => '',
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $filters
     */
    private static function matches_student_filters(array $row, array $filters): bool
    {
        $selected_exam_id = max(0, (int) ($filters['exam_id'] ?? 0));
        if ($selected_exam_id > 0 && (int) ($row['exam_id'] ?? 0) !== $selected_exam_id) {
            return false;
        }

        $selected_kelas = trim((string) ($filters['kelas'] ?? ''));
        $row_kelas = trim((string) ($row['student_kelas'] ?? $row['student_kode_kelas'] ?? ''));
        if ($selected_kelas !== '' && $row_kelas !== $selected_kelas) {
            return false;
        }

        $student_keyword = strtolower(trim((string) ($filters['student_keyword'] ?? '')));
        if ($student_keyword !== '') {
            $haystack = strtolower(implode(' ', array_filter([
                (string) ($row['student_name'] ?? ''),
                (string) ($row['student_login'] ?? $row['student_username'] ?? ''),
                (string) ($row['student_nisn'] ?? ''),
                (string) ($row['exam_title'] ?? ''),
            ])));
            if ($haystack === '' || strpos($haystack, $student_keyword) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>
     */
    private static function paginate_items(array $items, int $page, int $per_page): array
    {
        $page = max(1, $page);
        $per_page = max(1, $per_page);
        $total = count($items);
        $total_pages = max(1, (int) ceil($total / $per_page));
        if ($page > $total_pages) {
            $page = $total_pages;
        }

        return [
            'items' => array_values(array_slice($items, ($page - 1) * $per_page, $per_page)),
            'total' => $total,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_pages' => $total_pages,
                'total_items' => $total,
            ],
        ];
    }

    /**
     * @return array<string,int>
     */
    private static function empty_pagination(): array
    {
        return [
            'current_page' => 1,
            'per_page' => self::DEFAULT_ATTEMPTS_PER_PAGE,
            'total_pages' => 1,
            'total_items' => 0,
        ];
    }

    private static function format_presence_status(string $status): string
    {
        $normalized = sanitize_key($status);
        if ($normalized === 'online') {
            return 'Online';
        }
        if ($normalized === 'stale') {
            return 'Stale';
        }
        if ($normalized === 'offline') {
            return 'Offline';
        }

        return '-';
    }

    private static function format_risk_tone(string $tone): string
    {
        $normalized = sanitize_key($tone);
        if ($normalized === 'high_risk') {
            return 'High Risk';
        }
        if ($normalized === 'watch') {
            return 'Must Watch';
        }
        if ($normalized === 'warning') {
            return 'Warning';
        }

        return 'Normal';
    }

    private static function format_risk_score(float $score): string
    {
        if (class_exists('CBT_Security_Log') && method_exists('CBT_Security_Log', 'format_risk_score')) {
            return (string) CBT_Security_Log::format_risk_score($score);
        }

        return number_format_i18n($score, 1);
    }
}
