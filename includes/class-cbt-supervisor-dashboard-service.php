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

if (!class_exists('CBT_Start_Attempt_Gate_Service')) {
    require_once __DIR__ . '/class-cbt-start-attempt-gate-service.php';
}

if (!class_exists('CBT_Exam_Availability_Auto_Warm_Service')) {
    require_once __DIR__ . '/class-cbt-exam-availability-auto-warm-service.php';
}

final class CBT_Supervisor_Dashboard_Service
{
    private const DEFAULT_ROSTER_PER_PAGE = 8;
    private const DEFAULT_ATTEMPTS_PER_PAGE = 8;
    private const DEFAULT_SECURITY_PER_PAGE = 12;
    private const DEFAULT_ATTENDANCE_PER_PAGE = 12;
    private const DEFAULT_ACTION_REQUIRED_PER_PAGE = 10;
    private const SECURITY_LOG_SCAN_LIMIT = 150;
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
        $active_tab = (string) ($filters['tab'] ?? 'overview');
        $action_required = $active_tab === 'action_required' || $active_tab === 'overview'
            ? self::build_action_required_context($scope, $filters, $active_tab === 'overview')
            : self::empty_action_required_context();
        $live_roster = $active_tab === 'live_roster'
            ? self::build_live_roster_context($scope, $filters)
            : self::empty_live_roster_context();
        $must_watch = $active_tab === 'must_watch'
            ? self::build_must_watch_context($scope, $filters)
            : self::empty_must_watch_context();
        $monitoring_attempts = $active_tab === 'monitoring_attempts'
            ? self::build_monitoring_attempts_context($scope, $filters)
            : self::empty_monitoring_attempts_context();
        $submit_recovery = $active_tab === 'submit_recovery'
            ? self::build_submit_recovery_context(self::build_monitoring_attempts_context($scope, $filters))
            : self::empty_submit_recovery_context();

        return [
            'ok' => true,
            'scope' => $scope,
            'filters' => $filters,
            'permissions' => self::build_permissions($scope),
            'filter_options' => [
                'exams' => self::load_accessible_exam_options($scope),
                'kelas' => self::load_accessible_kelas_options($scope),
                'ruang' => self::load_accessible_ruang_options($scope),
            ],
            'summary_cards' => self::build_summary_cards($status_snapshot, $action_required),
            'status_snapshot' => $status_snapshot,
            'action_required' => $action_required,
            'live_roster' => $live_roster,
            'must_watch' => $must_watch,
            'monitoring_attempts' => [
                'items' => (array) ($monitoring_attempts['items'] ?? []),
                'total' => max(0, (int) ($monitoring_attempts['total'] ?? 0)),
                'pagination' => (array) ($monitoring_attempts['pagination'] ?? []),
                'note' => (string) ($monitoring_attempts['note'] ?? ''),
            ],
            'submit_health' => (array) ($submit_recovery['submit_health'] ?? []),
            'submit_watchlist' => (array) ($submit_recovery['submit_watchlist'] ?? []),
            'submit_recovery' => $submit_recovery,
            'security_log' => $active_tab === 'security_log'
                ? self::build_security_log_context($scope, $filters)
                : self::empty_security_log_context(),
            'token_gate' => $active_tab === 'token_gate'
                ? self::build_token_gate_context($scope, $filters)
                : self::empty_token_gate_context(),
            'attendance' => $active_tab === 'attendance'
                ? self::build_attendance_context($scope, $filters)
                : self::empty_attendance_context(),
        ];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public static function get_attempt_detail(int $attempt_id, int $user_id, string $role)
    {
        $scope = self::resolve_scope($user_id, $role);
        $row = self::load_accessible_attempt_row($scope, $attempt_id);
        if (empty($row)) {
            return new WP_Error('attempt_not_found', 'Attempt tidak ditemukan atau di luar scope pengawas.', ['status' => 404]);
        }

        $answer_count = max(0, (int) ($row['answer_count'] ?? 0));
        $question_count = max(0, (int) ($row['question_count'] ?? 0));
        $answered_percentage = $question_count > 0 ? round(($answer_count / $question_count) * 100, 2) : 0.0;
        $total_points = max(0.0, (float) ($row['total_points'] ?? 0.0));
        $earned_points = max(0.0, (float) ($row['earned_points'] ?? 0.0));
        $score_percentage = $total_points > 0 ? round(($earned_points / $total_points) * 100, 2) : 0.0;
        $attempt_status = sanitize_key((string) ($row['status'] ?? ''));
        $duration_minutes = max(1, (int) ($row['exam_duration_minutes'] ?? 0)) + max(0, (int) ($row['extra_time_minutes'] ?? 0));
        $presence = self::find_live_roster_item_for_attempt($scope, (int) ($row['attempt_id'] ?? 0));
        $submit_status = self::find_submit_status_for_attempt($scope, $row);
        $security_timeline = self::load_attempt_security_timeline($scope, (int) ($row['attempt_id'] ?? 0));

        return [
            'ok' => true,
            'attempt' => [
                'attempt_id' => (int) ($row['attempt_id'] ?? 0),
                'status' => $attempt_status,
                'status_label' => self::format_attempt_status_label($attempt_status),
                'score_percentage' => $score_percentage,
                'score_percentage_label' => number_format_i18n($score_percentage, 2) . '%',
                'duration_minutes' => $duration_minutes,
                'extra_time_minutes' => max(0, (int) ($row['extra_time_minutes'] ?? 0)),
            ],
            'student' => [
                'student_id' => (int) ($row['student_id'] ?? 0),
                'name' => (string) ($row['student_name'] ?? '-'),
                'username' => (string) ($row['student_username'] ?? ''),
                'nisn' => (string) ($row['student_nisn'] ?? ''),
                'kelas' => (string) ($row['student_kelas'] ?? ''),
                'ruang' => (string) ($row['student_ruang'] ?? ''),
            ],
            'exam' => [
                'exam_id' => (int) ($row['exam_id'] ?? 0),
                'title' => (string) ($row['exam_title'] ?? '-'),
                'status' => (string) ($row['exam_status'] ?? ''),
                'created_by' => (int) ($row['exam_created_by'] ?? 0),
            ],
            'presence' => $presence,
            'answer_progress' => [
                'answer_count' => $answer_count,
                'question_count' => $question_count,
                'answered_percentage' => $answered_percentage,
                'answered_percentage_label' => number_format_i18n($answered_percentage, 2) . '%',
                'earned_points' => $earned_points,
                'total_points' => $total_points,
            ],
            'security_timeline' => $security_timeline,
            'security_events' => self::latest_security_events_from_timeline($security_timeline),
            'submit_status' => $submit_status,
            'timeline' => [
                'started_at' => (string) ($row['started_at'] ?? ''),
                'finished_at' => (string) ($row['finished_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'remaining_label' => self::format_attempt_remaining_label($row, $duration_minutes),
            ],
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
     * @param array<string,mixed> $scope
     * @return array<string,bool>
     */
    private static function build_permissions(array $scope): array
    {
        return [
            'can_reset_login' => true,
            'can_view_token' => true,
            'can_print_attendance' => true,
            'can_manage_token' => false,
            'can_delete_security_logs' => false,
            'is_admin_scope' => !empty($scope['is_admin_scope']),
        ];
    }

    /**
     * @param array<string,mixed> $status_snapshot
     * @param array<string,mixed> $action_required
     * @return array<int,array<string,mixed>>
     */
    private static function build_summary_cards(array $status_snapshot, array $action_required): array
    {
        return [
            [
                'key' => 'action_required',
                'label' => 'Butuh Tindakan',
                'value' => max(0, (int) ($action_required['total'] ?? 0)),
                'meta' => 'Prioritas masalah yang perlu respons cepat',
            ],
            [
                'key' => 'security_backlog',
                'label' => 'Backlog',
                'value' => max(0, (int) ($status_snapshot['backlog_count'] ?? 0)),
                'meta' => 'Antrian ingest security',
            ],
            [
                'key' => 'security_dead_letter',
                'label' => 'Dead Letter',
                'value' => max(0, (int) ($status_snapshot['dead_letter_count'] ?? 0)),
                'meta' => 'Event gagal persist',
            ],
            [
                'key' => 'system_mode',
                'label' => 'Mode',
                'value' => strtoupper((string) ($status_snapshot['mode'] ?? 'online')),
                'meta' => (string) ($status_snapshot['status_label'] ?? 'Telemetry siap dipantau.'),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private static function normalize_filters(array $query): array
    {
        $tab = sanitize_key((string) ($query['tab'] ?? 'overview'));
        if (!in_array($tab, ['overview', 'token_gate', 'attendance', 'action_required', 'live_roster', 'must_watch', 'security_log', 'monitoring_attempts', 'submit_recovery'], true)) {
            $tab = 'overview';
        }

        $status = sanitize_key((string) ($query['status'] ?? ''));
        if (!in_array($status, ['', 'in_progress', 'completed'], true)) {
            $status = '';
        }

        return [
            'tab' => $tab,
            'exam_id' => max(0, (int) ($query['exam_id'] ?? 0)),
            'kelas' => trim(sanitize_text_field((string) ($query['kelas'] ?? ''))),
            'ruang' => trim(sanitize_text_field((string) ($query['ruang'] ?? ''))),
            'student_keyword' => trim(sanitize_text_field((string) ($query['student_keyword'] ?? ''))),
            'status' => $status,
            'roster_page' => max(1, (int) ($query['roster_page'] ?? 1)),
            'attempts_page' => max(1, (int) ($query['attempts_page'] ?? 1)),
            'action_page' => max(1, (int) ($query['action_page'] ?? 1)),
            'security_page' => max(1, (int) ($query['security_page'] ?? 1)),
            'security_severity' => self::normalize_all_filter((string) ($query['security_severity'] ?? 'all')),
            'security_event_type' => self::normalize_all_filter((string) ($query['security_event_type'] ?? 'all')),
            'security_device_type' => self::normalize_all_filter((string) ($query['security_device_type'] ?? 'all')),
            'attendance_page' => max(1, (int) ($query['attendance_page'] ?? 1)),
            'attendance_status' => self::normalize_attendance_status((string) ($query['attendance_status'] ?? '')),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_action_required_context(): array
    {
        return [
            'items' => [],
            'total' => 0,
            'pagination' => self::empty_pagination(self::DEFAULT_ACTION_REQUIRED_PER_PAGE),
            'severity_counts' => [
                'critical' => 0,
                'warning' => 0,
                'info' => 0,
            ],
            'note' => 'Butuh Tindakan dimuat saat tab dibuka.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_live_roster_context(): array
    {
        return [
            'available' => class_exists('CBT_Live_Attempt_Roster_Index') && CBT_Live_Attempt_Roster_Index::is_available(),
            'items' => [],
            'total' => 0,
            'pagination' => self::empty_pagination(self::DEFAULT_ROSTER_PER_PAGE),
            'note' => 'Live roster dimuat saat tab Live Roster dibuka.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_must_watch_context(): array
    {
        return [
            'items' => [],
            'total' => 0,
            'note' => 'Must Watch dimuat saat tab Must Watch dibuka.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_monitoring_attempts_context(): array
    {
        return [
            'items' => [],
            'total' => 0,
            'pagination' => self::empty_pagination(self::DEFAULT_ATTEMPTS_PER_PAGE),
            'note' => 'Attempts dimuat saat tab Attempts dibuka.',
            'submit_health' => ['available' => false, 'note' => 'Submit telemetry dimuat dari tab Submit Recovery.'],
            'submit_watchlist' => ['available' => false, 'items' => [], 'total' => 0, 'display_count' => 0],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_submit_recovery_context(): array
    {
        return [
            'submit_health' => ['available' => false, 'note' => 'Submit telemetry dimuat saat tab Submit Recovery dibuka.'],
            'submit_watchlist' => ['available' => false, 'items' => [], 'total' => 0, 'display_count' => 0],
            'note' => 'Submit recovery dimuat saat tab Submit Recovery dibuka.',
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
        $items = self::collect_live_roster_items($scope, $filters);

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
     * @return array<int,array<string,mixed>>
     */
    private static function collect_live_roster_items(array $scope, array $filters): array
    {
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

        return $items;
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private static function build_must_watch_context(array $scope, array $filters): array
    {
        $items = self::collect_must_watch_items($scope, $filters);

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
     * @return array<int,array<string,mixed>>
     */
    private static function collect_must_watch_items(array $scope, array $filters): array
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

        return $items;
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
     * @param array<string,mixed> $monitoring_attempts
     * @return array<string,mixed>
     */
    private static function build_submit_recovery_context(array $monitoring_attempts): array
    {
        $submit_health = (array) ($monitoring_attempts['submit_health'] ?? []);
        $submit_watchlist = (array) ($monitoring_attempts['submit_watchlist'] ?? []);

        return [
            'submit_health' => $submit_health,
            'submit_watchlist' => $submit_watchlist,
            'note' => (string) ($submit_watchlist['note'] ?? $submit_health['note'] ?? 'Pantau submit recovery dan unresolved submit dari tab ini.'),
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private static function build_action_required_context(array $scope, array $filters, bool $summary_only = false): array
    {
        $items_by_attempt = [];
        foreach (self::collect_live_roster_items($scope, $filters) as $item) {
            $attempt_id = max(0, (int) ($item['attempt_id'] ?? 0));
            if ($attempt_id <= 0) {
                continue;
            }

            $presence = sanitize_key((string) ($item['presence_status'] ?? ''));
            if (!empty($item['heartbeat_lost_active'])) {
                self::upsert_action_required_item($items_by_attempt, $item, 'critical', 'Heartbeat hilang', 'Perangkat tidak mengirim heartbeat saat attempt aktif.', 'heartbeat_lost');
            } elseif ($presence === 'offline') {
                self::upsert_action_required_item($items_by_attempt, $item, 'critical', 'Siswa offline', 'Koneksi siswa terputus dari roster live.', 'offline');
            } elseif ($presence === 'stale') {
                self::upsert_action_required_item($items_by_attempt, $item, 'warning', 'Koneksi stale', 'Roster belum menerima update baru dari siswa.', 'stale');
            }

            $risk_tone = sanitize_key((string) ($item['risk_tone'] ?? ''));
            $risk_score = (float) ($item['risk_score'] ?? 0.0);
            if ($risk_tone === 'high_risk' || $risk_score >= 8.0) {
                self::upsert_action_required_item($items_by_attempt, $item, 'critical', 'Risiko tinggi', 'Risk score ' . self::format_risk_score($risk_score) . ' perlu dicek.', 'risk_high');
            } elseif ($risk_tone === 'watch' || $risk_score >= 5.0) {
                self::upsert_action_required_item($items_by_attempt, $item, 'warning', 'Must watch', 'Risk score ' . self::format_risk_score($risk_score) . ' masuk prioritas pantau.', 'risk_watch');
            }
        }

        foreach (self::collect_must_watch_items($scope, $filters) as $item) {
            $risk_score = (float) ($item['risk_score'] ?? 0.0);
            self::upsert_action_required_item(
                $items_by_attempt,
                $item,
                $risk_score >= 8.0 ? 'critical' : 'warning',
                (string) ($item['primary_event_label'] ?? 'Must watch'),
                'Security event masuk prioritas must watch.',
                'must_watch'
            );
        }

        if (!$summary_only) {
            $monitoring_attempts = self::build_monitoring_attempts_context($scope, $filters);
            foreach ((array) ($monitoring_attempts['items'] ?? []) as $item_row) {
                $item = (array) $item_row;
                if (!empty($item['finalize_pending'])) {
                    self::upsert_action_required_item($items_by_attempt, $item, 'critical', 'Finalisasi tertunda', 'Waktu attempt habis dan finalisasi masih diproses.', 'finalize_pending');
                }

                if (!empty($item['presence_heartbeat_lost_active'])) {
                    self::upsert_action_required_item($items_by_attempt, $item, 'critical', 'Heartbeat hilang', 'Presence attempt menandai heartbeat lost.', 'heartbeat_lost');
                }
            }

            $submit_watchlist = (array) ($monitoring_attempts['submit_watchlist'] ?? []);
            foreach ((array) ($submit_watchlist['items'] ?? []) as $item_row) {
                $item = (array) $item_row;
                $label = (string) ($item['state_label'] ?? 'Submit recovery');
                $severity = stripos($label, 'fail') !== false || stripos($label, 'gagal') !== false ? 'critical' : 'warning';
                self::upsert_action_required_item(
                    $items_by_attempt,
                    $item,
                    $severity,
                    $label,
                    (string) ($item['detail'] ?? 'Submit recovery masih unresolved.'),
                    'submit_recovery'
                );
            }
        }

        $items = array_values($items_by_attempt);
        usort($items, static function (array $left, array $right): int {
            $severity_compare = self::severity_rank((string) ($right['severity'] ?? 'info')) <=> self::severity_rank((string) ($left['severity'] ?? 'info'));
            if ($severity_compare !== 0) {
                return $severity_compare;
            }

            $risk_compare = (float) ($right['risk_score'] ?? 0.0) <=> (float) ($left['risk_score'] ?? 0.0);
            if ($risk_compare !== 0) {
                return $risk_compare;
            }

            return strcmp((string) ($right['last_seen_at'] ?? ''), (string) ($left['last_seen_at'] ?? ''));
        });

        $severity_counts = [
            'critical' => 0,
            'warning' => 0,
            'info' => 0,
        ];
        foreach ($items as $item) {
            $severity = sanitize_key((string) ($item['severity'] ?? 'info'));
            if (!array_key_exists($severity, $severity_counts)) {
                $severity = 'info';
            }
            $severity_counts[$severity]++;
        }

        if ($summary_only) {
            return [
                'items' => [],
                'total' => count($items),
                'pagination' => self::empty_pagination(self::DEFAULT_ACTION_REQUIRED_PER_PAGE),
                'severity_counts' => $severity_counts,
                'note' => empty($items)
                    ? 'Belum ada tindakan prioritas pada scope aktif.'
                    : 'Buka tab Butuh Tindakan untuk melihat daftar prioritas.',
            ];
        }

        return array_merge(
            [
                'severity_counts' => $severity_counts,
                'note' => empty($items)
                    ? 'Belum ada tindakan prioritas pada scope aktif.'
                    : 'Urutkan respons dari critical paling atas.',
            ],
            self::paginate_items($items, (int) ($filters['action_page'] ?? 1), self::DEFAULT_ACTION_REQUIRED_PER_PAGE)
        );
    }

    /**
     * @param array<int,array<string,mixed>> $items_by_attempt
     * @param array<string,mixed> $source
     */
    private static function upsert_action_required_item(array &$items_by_attempt, array $source, string $severity, string $reason, string $detail, string $source_key): void
    {
        $attempt_id = max(0, (int) ($source['attempt_id'] ?? 0));
        if ($attempt_id <= 0) {
            return;
        }

        $current = $items_by_attempt[$attempt_id] ?? null;
        $next = [
            'attempt_id' => $attempt_id,
            'exam_id' => (int) ($source['exam_id'] ?? 0),
            'exam_title' => (string) ($source['exam_title'] ?? '-'),
            'student_id' => (int) ($source['student_id'] ?? 0),
            'student_name' => (string) ($source['student_name'] ?? '-'),
            'student_login' => (string) ($source['student_login'] ?? $source['student_username'] ?? ''),
            'student_nisn' => (string) ($source['student_nisn'] ?? ''),
            'student_kelas' => (string) ($source['student_kelas'] ?? $source['student_kode_kelas'] ?? ''),
            'student_ruang' => (string) ($source['student_ruang'] ?? $source['student_kode_ruang'] ?? ''),
            'severity' => in_array($severity, ['critical', 'warning', 'info'], true) ? $severity : 'info',
            'severity_label' => self::format_action_severity($severity),
            'reason' => $reason,
            'detail' => $detail,
            'source' => $source_key,
            'risk_score' => (float) ($source['risk_score'] ?? 0.0),
            'risk_score_label' => isset($source['risk_score_label']) ? (string) $source['risk_score_label'] : self::format_risk_score((float) ($source['risk_score'] ?? 0.0)),
            'presence_status' => (string) ($source['presence_status'] ?? ''),
            'presence_label' => isset($source['presence_label'])
                ? (string) $source['presence_label']
                : self::format_presence_status((string) ($source['presence_status'] ?? '')),
            'last_seen_at' => (string) ($source['last_seen_at'] ?? $source['last_event_at'] ?? $source['presence_last_seen_at'] ?? $source['started_at'] ?? ''),
        ];

        if (!is_array($current) || self::severity_rank($next['severity']) > self::severity_rank((string) ($current['severity'] ?? 'info'))) {
            $items_by_attempt[$attempt_id] = $next;
            return;
        }

        $items_by_attempt[$attempt_id]['detail'] = trim((string) ($items_by_attempt[$attempt_id]['detail'] ?? '') . ' ' . $detail);
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_security_log_context(): array
    {
        return [
            'items' => [],
            'total' => 0,
            'pagination' => self::empty_pagination(self::DEFAULT_SECURITY_PER_PAGE),
            'event_catalog' => [],
            'note' => 'Security log dimuat saat tab Security Log dibuka.',
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private static function build_security_log_context(array $scope, array $filters): array
    {
        if (!class_exists('CBT_Security_Log') || !method_exists('CBT_Security_Log', 'get_recent_logs')) {
            return array_merge(self::empty_security_log_context(), [
                'note' => 'Security log service belum tersedia.',
            ]);
        }

        $rows = CBT_Security_Log::get_recent_logs(self::SECURITY_LOG_SCAN_LIMIT, [
            'teacher_id' => max(0, (int) ($scope['teacher_scope_user_id'] ?? 0)),
        ]);

        $items = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row) || !self::matches_security_filters($row, $filters)) {
                continue;
            }

            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'attempt_id' => (int) ($row['attempt_id'] ?? 0),
                'exam_id' => (int) ($row['exam_id'] ?? 0),
                'exam_title' => (string) ($row['exam_title'] ?? '-'),
                'student_id' => (int) ($row['student_id'] ?? 0),
                'student_name' => (string) ($row['student_name'] ?? '-'),
                'student_login' => (string) ($row['student_login'] ?? ''),
                'student_kelas' => (string) ($row['student_kode_kelas'] ?? $row['student_kelas'] ?? ''),
                'student_ruang' => (string) ($row['student_kode_ruang'] ?? $row['student_ruang'] ?? ''),
                'event_type' => (string) ($row['event_type'] ?? ''),
                'event_label' => (string) ($row['event_label'] ?? ucwords(str_replace('_', ' ', (string) ($row['event_type'] ?? 'event')))),
                'severity' => (string) ($row['severity'] ?? 'info'),
                'message_display' => (string) ($row['message_display'] ?? $row['message'] ?? ''),
                'device_type' => (string) ($row['device_type'] ?? 'unknown'),
                'device_summary' => (string) ($row['device_summary'] ?? $row['device_label'] ?? 'Unknown'),
                'occurred_at' => (string) ($row['occurred_at'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return array_merge(
            [
                'event_catalog' => self::build_security_event_catalog(),
                'note' => empty($items)
                    ? 'Belum ada security event yang cocok dengan filter aktif.'
                    : 'Security log bersifat read-only di frontend pengawas.',
            ],
            self::paginate_items($items, (int) ($filters['security_page'] ?? 1), self::DEFAULT_SECURITY_PER_PAGE)
        );
    }

    /**
     * @return array<int,array<string,string>>
     */
    private static function build_security_event_catalog(): array
    {
        if (!class_exists('CBT_Security_Log') || !method_exists('CBT_Security_Log', 'event_definitions')) {
            return [];
        }

        $items = [];
        foreach ((array) CBT_Security_Log::event_definitions() as $event_type => $definition) {
            $items[] = [
                'event_type' => (string) $event_type,
                'label' => (string) ($definition['label'] ?? ucwords(str_replace('_', ' ', (string) $event_type))),
                'severity' => (string) ($definition['severity'] ?? 'info'),
            ];
        }

        usort($items, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_token_gate_context(): array
    {
        return [
            'token' => self::build_token_context(false),
            'selected_exam' => null,
            'gate' => self::empty_gate_context(),
            'auto_warm' => self::empty_auto_warm_context(),
            'note' => 'Token & gate dimuat saat tab Token & Gate dibuka.',
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private static function build_token_gate_context(array $scope, array $filters): array
    {
        $token = self::build_token_context(true);
        $exam_id = max(0, (int) ($filters['exam_id'] ?? 0));
        if ($exam_id <= 0) {
            return [
                'token' => $token,
                'selected_exam' => null,
                'gate' => self::empty_gate_context(),
                'auto_warm' => self::empty_auto_warm_context(),
                'note' => 'Pilih exam untuk melihat start gate dan auto-warm readiness.',
            ];
        }

        $exam_row = self::load_accessible_exam_row($scope, $exam_id);
        if (empty($exam_row)) {
            return [
                'token' => $token,
                'selected_exam' => null,
                'gate' => self::empty_gate_context(),
                'auto_warm' => self::empty_auto_warm_context(),
                'note' => 'Exam tidak ditemukan atau di luar scope pengawas.',
            ];
        }

        $gate = class_exists('CBT_Start_Attempt_Gate_Service')
            ? (array) CBT_Start_Attempt_Gate_Service::get_exam_diagnostics($exam_id)
            : self::empty_gate_context();
        $auto_warm = class_exists('CBT_Exam_Availability_Auto_Warm_Service')
            ? (array) CBT_Exam_Availability_Auto_Warm_Service::get_exam_panel_context($exam_row)
            : self::empty_auto_warm_context();

        return [
            'token' => $token,
            'selected_exam' => self::format_exam_summary($exam_row),
            'gate' => array_merge(self::empty_gate_context(), $gate),
            'auto_warm' => array_merge(self::empty_auto_warm_context(), $auto_warm),
            'note' => 'Token dapat dilihat di pengawas, tetapi pengaturan dan regenerate tetap di admin.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_token_context(bool $include_value): array
    {
        $meta = class_exists('CBT_Auth') && method_exists('CBT_Auth', 'get_global_exam_token')
            ? (array) CBT_Auth::get_global_exam_token(false)
            : [];
        $token = strtoupper(trim((string) ($meta['token'] ?? '')));
        $remaining_seconds = max(0, (int) ($meta['remaining_seconds'] ?? 0));

        return [
            'available' => $token !== '',
            'display' => $include_value && $token !== '' ? $token : '------',
            'refresh_minutes' => max(0, (int) ($meta['refresh_minutes'] ?? 0)),
            'generated_at' => max(0, (int) ($meta['generated_at'] ?? 0)),
            'generated_at_label' => self::format_timestamp_label(max(0, (int) ($meta['generated_at'] ?? 0))),
            'next_refresh_at' => max(0, (int) ($meta['next_refresh_at'] ?? 0)),
            'next_refresh_label' => self::format_timestamp_label(max(0, (int) ($meta['next_refresh_at'] ?? 0))),
            'remaining_seconds' => $remaining_seconds,
            'remaining_label' => $remaining_seconds > 0 ? (int) ceil($remaining_seconds / 60) . ' menit lagi' : 'Menunggu siklus berikutnya',
            'frontend_auto_apply' => (int) ($meta['frontend_auto_apply'] ?? 0) === 1,
            'frontend_auto_apply_label' => (int) ($meta['frontend_auto_apply'] ?? 0) === 1 ? 'Auto aktif' : 'Manual',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_gate_context(): array
    {
        return [
            'redis_available' => false,
            'redis_error' => '',
            'status_label' => 'DISABLED',
            'status_tone' => 'warning',
            'status_slug' => 'disabled',
            'queue_depth' => 0,
            'bucket_tokens' => 0,
            'gate_capacity' => 0,
            'gate_window_seconds' => 0,
            'release_rate_label' => '-',
            'oldest_wait_seconds' => 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_auto_warm_context(): array
    {
        return [
            'enabled' => false,
            'status' => 'inactive',
            'status_label' => 'INACTIVE',
            'status_tone' => 'muted',
            'target_kelas' => [],
            'target_student_count' => 0,
            'prepared_count' => 0,
            'last_message' => 'Pilih exam untuk membaca auto-warm readiness.',
            'can_start' => false,
            'can_stop' => false,
            'redis_available' => false,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_attendance_context(): array
    {
        return [
            'available' => false,
            'selected_exam' => null,
            'items' => [],
            'total' => 0,
            'pagination' => self::empty_pagination(self::DEFAULT_ATTENDANCE_PER_PAGE),
            'summary' => [
                'not_started' => 0,
                'in_progress' => 0,
                'completed' => 0,
            ],
            'note' => 'Daftar hadir dimuat saat tab Daftar Hadir dibuka.',
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private static function build_attendance_context(array $scope, array $filters): array
    {
        $exam_id = max(0, (int) ($filters['exam_id'] ?? 0));
        if ($exam_id <= 0) {
            return array_merge(self::empty_attendance_context(), [
                'note' => 'Pilih exam dulu untuk membuka daftar hadir.',
            ]);
        }

        $exam_row = self::load_accessible_exam_row($scope, $exam_id);
        if (empty($exam_row)) {
            return array_merge(self::empty_attendance_context(), [
                'note' => 'Exam tidak ditemukan atau di luar scope pengawas.',
            ]);
        }

        $target_kelas = self::split_target_kelas_csv((string) ($exam_row['target_kelas'] ?? ''));
        if (empty($target_kelas)) {
            return array_merge(self::empty_attendance_context(), [
                'available' => true,
                'selected_exam' => self::format_exam_summary($exam_row),
                'note' => 'Exam ini belum memiliki target_kelas, jadi daftar hadir tidak dibuka agar scope siswa tetap aman.',
            ]);
        }

        $rows = self::load_attendance_rows($exam_row, $target_kelas);
        $items = [];
        $summary = [
            'not_started' => 0,
            'in_progress' => 0,
            'completed' => 0,
        ];

        foreach ($rows as $row) {
            $status = self::normalize_attendance_row_status((string) ($row['attempt_status'] ?? ''));
            $summary[$status]++;

            $item = [
                'student_id' => (int) ($row['student_id'] ?? 0),
                'student_name' => (string) ($row['student_name'] ?? '-'),
                'student_username' => (string) ($row['student_username'] ?? ''),
                'student_nisn' => (string) ($row['student_nisn'] ?? ''),
                'student_kelas' => (string) ($row['student_kelas'] ?? ''),
                'student_ruang' => (string) ($row['student_ruang'] ?? ''),
                'attempt_id' => (int) ($row['attempt_id'] ?? 0),
                'status' => $status,
                'status_label' => self::format_attendance_status($status),
                'started_at' => (string) ($row['started_at'] ?? ''),
                'finished_at' => (string) ($row['finished_at'] ?? ''),
            ];

            if (!self::matches_attendance_filters($item, $filters)) {
                continue;
            }

            $items[] = $item;
        }

        return array_merge(
            [
                'available' => true,
                'selected_exam' => self::format_exam_summary($exam_row),
                'summary' => $summary,
                'note' => empty($items)
                    ? 'Belum ada peserta yang cocok dengan filter daftar hadir.'
                    : 'Daftar hadir hanya menampilkan peserta dari target_kelas exam terpilih.',
            ],
            self::paginate_items($items, (int) ($filters['attendance_page'] ?? 1), self::DEFAULT_ATTENDANCE_PER_PAGE)
        );
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
     * @param array<string,mixed> $scope
     * @return array<int,string>
     */
    private static function load_accessible_ruang_options(array $scope): array
    {
        global $wpdb;

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $where = 'WHERE ruang_meta.meta_value IS NOT NULL AND ruang_meta.meta_value <> ""';
        $params = [];
        if (empty($scope['is_admin_scope'])) {
            $where .= ' AND e.created_by = %d';
            $params[] = max(0, (int) ($scope['user_id'] ?? 0));
        }

        $sql = "SELECT DISTINCT ruang_meta.meta_value AS ruang
                FROM {$attempt_table} a
                INNER JOIN {$exam_table} e ON e.id = a.exam_id
                INNER JOIN {$wpdb->users} u ON u.ID = a.student_id
                LEFT JOIN (
                    SELECT user_id, MAX(meta_value) AS meta_value
                    FROM {$wpdb->usermeta}
                    WHERE meta_key = 'kode_ruang'
                    GROUP BY user_id
                ) ruang_meta ON ruang_meta.user_id = u.ID
                {$where}
                ORDER BY ruang_meta.meta_value ASC";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return array_values(array_filter(array_map(static function (array $row): string {
            return trim((string) ($row['ruang'] ?? ''));
        }, (array) $wpdb->get_results($sql, ARRAY_A))));
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<string,mixed>
     */
    private static function load_accessible_exam_row(array $scope, int $exam_id): array
    {
        global $wpdb;

        $exam_id = max(0, $exam_id);
        if ($exam_id <= 0) {
            return [];
        }

        $exam_table = $wpdb->prefix . 'cbt_exams';
        $where = 'WHERE id = %d AND title NOT LIKE %s';
        $params = [$exam_id, 'Bank Soal - %'];
        if (empty($scope['is_admin_scope'])) {
            $where .= ' AND created_by = %d';
            $params[] = max(0, (int) ($scope['user_id'] ?? 0));
        }

        $sql = $wpdb->prepare(
            "SELECT id, title, status, target_kelas, duration_minutes, starts_at, ends_at, created_by
             FROM {$exam_table}
             {$where}
             LIMIT 1",
            $params
        );
        $rows = (array) $wpdb->get_results($sql, ARRAY_A);
        $row = isset($rows[0]) && is_array($rows[0]) ? $rows[0] : [];

        return $row;
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<string,mixed>
     */
    private static function load_accessible_attempt_row(array $scope, int $attempt_id): array
    {
        global $wpdb;

        $attempt_id = max(0, $attempt_id);
        if ($attempt_id <= 0) {
            return [];
        }

        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $exam_table = $wpdb->prefix . 'cbt_exams';
        $answer_table = $wpdb->prefix . 'cbt_answers';
        $question_table = $wpdb->prefix . 'cbt_questions';
        $where = 'WHERE a.id = %d';
        $params = [$attempt_id];
        if (empty($scope['is_admin_scope'])) {
            $where .= ' AND e.created_by = %d';
            $params[] = max(0, (int) ($scope['user_id'] ?? 0));
        }

        $sql = $wpdb->prepare(
            "SELECT a.id AS attempt_id,
                    a.exam_id,
                    a.student_id,
                    a.status,
                    a.score,
                    a.max_score,
                    a.started_at,
                    a.finished_at,
                    a.extra_time_minutes,
                    a.updated_at,
                    e.title AS exam_title,
                    e.status AS exam_status,
                    e.duration_minutes AS exam_duration_minutes,
                    e.created_by AS exam_created_by,
                    u.user_login AS student_username,
                    u.display_name AS student_name,
                    kelas_meta.meta_value AS student_kelas,
                    ruang_meta.meta_value AS student_ruang,
                    nisn_meta.meta_value AS student_nisn,
                    (SELECT COUNT(*) FROM {$answer_table} ans WHERE ans.attempt_id = a.id) AS answer_count,
                    (SELECT COUNT(*) FROM {$question_table} qcount WHERE qcount.exam_id = a.exam_id AND COALESCE(qcount.is_active, 1) = 1) AS question_count,
                    CASE
                        WHEN COALESCE(a.max_score, 0) > 0 THEN a.max_score
                        ELSE (SELECT COALESCE(SUM(qpoints.points), 0) FROM {$question_table} qpoints WHERE qpoints.exam_id = a.exam_id AND COALESCE(qpoints.is_active, 1) = 1)
                    END AS total_points,
                    CASE
                        WHEN a.status = 'completed' THEN COALESCE(a.score, 0)
                        ELSE (SELECT COALESCE(SUM(anscore.score_awarded), 0) FROM {$answer_table} anscore WHERE anscore.attempt_id = a.id)
                    END AS earned_points
             FROM {$attempt_table} a
             INNER JOIN {$exam_table} e ON e.id = a.exam_id
             INNER JOIN {$wpdb->users} u ON u.ID = a.student_id
             LEFT JOIN {$wpdb->usermeta} kelas_meta
                ON kelas_meta.user_id = u.ID
               AND kelas_meta.meta_key = 'kode_kelas'
             LEFT JOIN {$wpdb->usermeta} ruang_meta
                ON ruang_meta.user_id = u.ID
               AND ruang_meta.meta_key = 'kode_ruang'
             LEFT JOIN {$wpdb->usermeta} nisn_meta
                ON nisn_meta.user_id = u.ID
               AND nisn_meta.meta_key = 'nisn'
             {$where}
             LIMIT 1",
            $params
        );
        $rows = (array) $wpdb->get_results($sql, ARRAY_A);

        return isset($rows[0]) && is_array($rows[0]) ? $rows[0] : [];
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<string,mixed>
     */
    private static function find_live_roster_item_for_attempt(array $scope, int $attempt_id): array
    {
        foreach (self::collect_live_roster_items($scope, []) as $item) {
            if ((int) ($item['attempt_id'] ?? 0) !== $attempt_id) {
                continue;
            }

            return [
                'available' => true,
                'presence_status' => (string) ($item['presence_status'] ?? ''),
                'presence_label' => (string) ($item['presence_label'] ?? ''),
                'connection_status' => (string) ($item['connection_status'] ?? ''),
                'visibility_state' => (string) ($item['visibility_state'] ?? ''),
                'has_focus' => $item['has_focus'] ?? null,
                'pending_sync_count' => max(0, (int) ($item['pending_sync_count'] ?? 0)),
                'heartbeat_lost_active' => !empty($item['heartbeat_lost_active']),
                'last_seen_at' => (string) ($item['last_seen_at'] ?? ''),
                'risk_tone' => (string) ($item['risk_tone'] ?? ''),
                'risk_label' => (string) ($item['risk_label'] ?? ''),
                'risk_score' => (float) ($item['risk_score'] ?? 0.0),
                'risk_score_label' => (string) ($item['risk_score_label'] ?? '0'),
            ];
        }

        return [
            'available' => false,
            'presence_status' => '',
            'presence_label' => '-',
            'connection_status' => '',
            'visibility_state' => '',
            'has_focus' => null,
            'pending_sync_count' => 0,
            'heartbeat_lost_active' => false,
            'last_seen_at' => '',
            'risk_tone' => '',
            'risk_label' => 'Normal',
            'risk_score' => 0.0,
            'risk_score_label' => self::format_risk_score(0.0),
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<string,mixed>
     */
    private static function load_attempt_security_timeline(array $scope, int $attempt_id): array
    {
        if ($attempt_id <= 0 || !class_exists('CBT_Security_Log') || !method_exists('CBT_Security_Log', 'get_attempt_timeline')) {
            return self::empty_security_timeline();
        }

        $timeline = CBT_Security_Log::get_attempt_timeline($attempt_id, [
            'teacher_id' => max(0, (int) ($scope['teacher_scope_user_id'] ?? 0)),
        ]);

        return is_array($timeline) ? $timeline : self::empty_security_timeline();
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_security_timeline(): array
    {
        return [
            'summary' => [
                'total_events' => 0,
                'grouped_items' => 0,
                'warning_count' => 0,
                'critical_count' => 0,
                'info_count' => 0,
                'risk_score' => 0.0,
                'risk_score_label' => self::format_risk_score(0.0),
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
     * @param array<string,mixed> $timeline
     * @return array<int,array<string,mixed>>
     */
    private static function latest_security_events_from_timeline(array $timeline): array
    {
        $items = isset($timeline['items']) && is_array($timeline['items'])
            ? array_reverse(array_values($timeline['items']))
            : [];
        $events = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $events[] = [
                'id' => (int) ($item['id'] ?? 0),
                'event_type' => (string) ($item['event_type'] ?? ''),
                'event_label' => (string) ($item['event_label'] ?? ucwords(str_replace('_', ' ', (string) ($item['event_type'] ?? 'event')))),
                'severity' => (string) ($item['severity'] ?? 'info'),
                'message_display' => (string) ($item['message_display'] ?? ''),
                'device_type' => (string) ($item['device_type'] ?? 'unknown'),
                'device_summary' => (string) ($item['device_summary'] ?? 'Unknown'),
                'occurred_at' => (string) ($item['occurred_at'] ?? $item['last_occurred_at'] ?? ''),
                'created_at' => (string) ($item['created_at'] ?? ''),
                'count' => max(1, (int) ($item['count'] ?? 1)),
                'first_occurred_at' => (string) ($item['first_occurred_at'] ?? ''),
                'last_occurred_at' => (string) ($item['last_occurred_at'] ?? $item['occurred_at'] ?? ''),
            ];

            if (count($events) >= 5) {
                break;
            }
        }

        return $events;
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>
     */
    private static function load_attempt_security_events(array $scope, int $attempt_id): array
    {
        return self::latest_security_events_from_timeline(self::load_attempt_security_timeline($scope, $attempt_id));
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,mixed> $attempt_row
     * @return array<string,mixed>
     */
    private static function find_submit_status_for_attempt(array $scope, array $attempt_row): array
    {
        $attempt_id = max(0, (int) ($attempt_row['attempt_id'] ?? 0));
        if ($attempt_id <= 0 || !class_exists('CBT_Admin_Results_Service') || !method_exists('CBT_Admin_Results_Service', 'build_frontend_monitoring_context')) {
            return [
                'available' => false,
                'state_label' => 'Tidak tersedia',
                'detail' => 'Submit telemetry belum tersedia.',
            ];
        }

        $context = CBT_Admin_Results_Service::build_frontend_monitoring_context([
            'is_admin_scope' => !empty($scope['is_admin_scope']),
            'current_user_id' => max(0, (int) ($scope['user_id'] ?? 0)),
            'selected_exam_id' => max(0, (int) ($attempt_row['exam_id'] ?? 0)),
            'selected_status' => '',
            'selected_kelas' => '',
            'student_keyword' => '',
            'current_page' => 1,
            'per_page' => self::DEFAULT_ATTEMPTS_PER_PAGE,
        ]);
        foreach ((array) (($context['submit_watchlist'] ?? [])['items'] ?? []) as $item_row) {
            $item = (array) $item_row;
            if ((int) ($item['attempt_id'] ?? 0) !== $attempt_id) {
                continue;
            }

            return [
                'available' => true,
                'state_label' => (string) ($item['state_label'] ?? 'Submit recovery'),
                'detail' => (string) ($item['detail'] ?? 'Submit recovery masih dipantau.'),
            ];
        }

        return [
            'available' => true,
            'state_label' => 'Normal',
            'detail' => 'Tidak ada unresolved submit recovery untuk attempt ini.',
        ];
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    private static function format_exam_summary(array $exam_row): array
    {
        return [
            'id' => (int) ($exam_row['id'] ?? 0),
            'title' => (string) ($exam_row['title'] ?? '-'),
            'status' => (string) ($exam_row['status'] ?? ''),
            'target_kelas' => self::split_target_kelas_csv((string) ($exam_row['target_kelas'] ?? '')),
            'duration_minutes' => max(0, (int) ($exam_row['duration_minutes'] ?? 0)),
            'starts_at' => (string) ($exam_row['starts_at'] ?? ''),
            'ends_at' => (string) ($exam_row['ends_at'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $exam_row
     * @param array<int,string> $target_kelas
     * @return array<int,array<string,mixed>>
     */
    private static function load_attendance_rows(array $exam_row, array $target_kelas): array
    {
        global $wpdb;

        $exam_id = max(0, (int) ($exam_row['id'] ?? 0));
        if ($exam_id <= 0 || empty($target_kelas)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($target_kelas), '%s'));
        $attempt_table = $wpdb->prefix . 'cbt_attempts';
        $params = array_merge($target_kelas, [$exam_id]);
        $sql = $wpdb->prepare(
            "SELECT u.ID AS student_id,
                    u.user_login AS student_username,
                    u.display_name AS student_name,
                    nisn_meta.meta_value AS student_nisn,
                    kelas_meta.meta_value AS student_kelas,
                    ruang_meta.meta_value AS student_ruang,
                    a.id AS attempt_id,
                    a.status AS attempt_status,
                    a.started_at,
                    a.finished_at
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} kelas_meta
                ON kelas_meta.user_id = u.ID
               AND kelas_meta.meta_key = 'kode_kelas'
               AND UPPER(kelas_meta.meta_value) IN ({$placeholders})
             LEFT JOIN {$wpdb->usermeta} ruang_meta
                ON ruang_meta.user_id = u.ID
               AND ruang_meta.meta_key = 'kode_ruang'
             LEFT JOIN {$wpdb->usermeta} nisn_meta
                ON nisn_meta.user_id = u.ID
               AND nisn_meta.meta_key = 'nisn'
             LEFT JOIN (
                 SELECT aa.id, aa.exam_id, aa.student_id, aa.status, aa.started_at, aa.finished_at
                 FROM {$attempt_table} aa
                 INNER JOIN (
                     SELECT student_id, MAX(id) AS latest_attempt_id
                     FROM {$attempt_table}
                     WHERE exam_id = %d
                     GROUP BY student_id
                 ) latest_attempt
                    ON latest_attempt.latest_attempt_id = aa.id
             ) a ON a.student_id = u.ID
             ORDER BY kelas_meta.meta_value ASC, ruang_meta.meta_value ASC, u.display_name ASC, u.user_login ASC",
            $params
        );

        return array_values(array_filter((array) $wpdb->get_results($sql, ARRAY_A), 'is_array'));
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

        $selected_ruang = trim((string) ($filters['ruang'] ?? ''));
        $row_ruang = trim((string) ($row['student_ruang'] ?? $row['student_kode_ruang'] ?? ''));
        if ($selected_ruang !== '' && $row_ruang !== $selected_ruang) {
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
     * @param array<string,mixed> $row
     * @param array<string,mixed> $filters
     */
    private static function matches_security_filters(array $row, array $filters): bool
    {
        if (!self::matches_student_filters($row, $filters)) {
            return false;
        }

        $severity = self::normalize_all_filter((string) ($filters['security_severity'] ?? 'all'));
        if ($severity !== 'all' && sanitize_key((string) ($row['severity'] ?? '')) !== $severity) {
            return false;
        }

        $event_type = self::normalize_all_filter((string) ($filters['security_event_type'] ?? 'all'));
        if ($event_type !== 'all' && sanitize_key((string) ($row['event_type'] ?? '')) !== $event_type) {
            return false;
        }

        $device_type = self::normalize_all_filter((string) ($filters['security_device_type'] ?? 'all'));
        if ($device_type !== 'all' && sanitize_key((string) ($row['device_type'] ?? '')) !== $device_type) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $filters
     */
    private static function matches_attendance_filters(array $item, array $filters): bool
    {
        $selected_kelas = trim((string) ($filters['kelas'] ?? ''));
        if ($selected_kelas !== '' && trim((string) ($item['student_kelas'] ?? '')) !== $selected_kelas) {
            return false;
        }

        $selected_ruang = trim((string) ($filters['ruang'] ?? ''));
        if ($selected_ruang !== '' && trim((string) ($item['student_ruang'] ?? '')) !== $selected_ruang) {
            return false;
        }

        $attendance_status = self::normalize_attendance_status((string) ($filters['attendance_status'] ?? ''));
        if ($attendance_status !== '' && (string) ($item['status'] ?? '') !== $attendance_status) {
            return false;
        }

        $student_keyword = strtolower(trim((string) ($filters['student_keyword'] ?? '')));
        if ($student_keyword !== '') {
            $haystack = strtolower(implode(' ', array_filter([
                (string) ($item['student_name'] ?? ''),
                (string) ($item['student_username'] ?? ''),
                (string) ($item['student_nisn'] ?? ''),
                (string) ($item['student_kelas'] ?? ''),
                (string) ($item['student_ruang'] ?? ''),
            ])));
            if ($haystack === '' || strpos($haystack, $student_keyword) === false) {
                return false;
            }
        }

        return true;
    }

    private static function normalize_all_filter(string $value): string
    {
        $normalized = sanitize_key($value);
        return $normalized !== '' ? $normalized : 'all';
    }

    private static function normalize_attendance_status(string $status): string
    {
        $normalized = sanitize_key($status);
        return in_array($normalized, ['not_started', 'in_progress', 'completed'], true) ? $normalized : '';
    }

    private static function normalize_attendance_row_status(string $status): string
    {
        $normalized = sanitize_key($status);
        if ($normalized === 'in_progress') {
            return 'in_progress';
        }
        if ($normalized === 'completed') {
            return 'completed';
        }

        return 'not_started';
    }

    private static function format_attendance_status(string $status): string
    {
        $normalized = self::normalize_attendance_row_status($status);
        if ($normalized === 'in_progress') {
            return 'Berjalan';
        }
        if ($normalized === 'completed') {
            return 'Selesai';
        }

        return 'Belum Mulai';
    }

    private static function format_attempt_status_label(string $status): string
    {
        $normalized = sanitize_key($status);
        if ($normalized === 'in_progress') {
            return 'Berjalan';
        }
        if ($normalized === 'completed') {
            return 'Selesai';
        }

        return $normalized !== '' ? ucwords(str_replace('_', ' ', $normalized)) : '-';
    }

    /**
     * @param array<string,mixed> $attempt_row
     */
    private static function format_attempt_remaining_label(array $attempt_row, int $duration_minutes): string
    {
        $status = sanitize_key((string) ($attempt_row['status'] ?? ''));
        if ($status !== 'in_progress') {
            return 'Selesai';
        }

        if (class_exists('CBT_Admin_Results_Helper') && method_exists('CBT_Admin_Results_Helper', 'calculate_attempt_remaining_seconds')) {
            $remaining_seconds = CBT_Admin_Results_Helper::calculate_attempt_remaining_seconds(
                (string) ($attempt_row['started_at'] ?? ''),
                max(1, $duration_minutes),
                $status
            );
            if ($remaining_seconds > 0 && method_exists('CBT_Admin_Results_Helper', 'format_attempt_remaining_label')) {
                return (string) CBT_Admin_Results_Helper::format_attempt_remaining_label($remaining_seconds);
            }

            return 'Diproses';
        }

        return 'Berjalan';
    }

    /**
     * @return array<int,string>
     */
    private static function split_target_kelas_csv(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r", "\n", ';', '|'], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        $items = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $normalized = strtoupper(sanitize_text_field($part));
            if ($normalized !== '') {
                $items[$normalized] = $normalized;
            }
        }

        return array_values($items);
    }

    private static function format_timestamp_label(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '-';
        }

        return function_exists('wp_date')
            ? (string) wp_date('Y-m-d H:i:s', $timestamp)
            : date('Y-m-d H:i:s', $timestamp);
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
    private static function empty_pagination(int $per_page = self::DEFAULT_ATTEMPTS_PER_PAGE): array
    {
        return [
            'current_page' => 1,
            'per_page' => max(1, $per_page),
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

    private static function severity_rank(string $severity): int
    {
        $normalized = sanitize_key($severity);
        if ($normalized === 'critical') {
            return 3;
        }
        if ($normalized === 'warning') {
            return 2;
        }

        return 1;
    }

    private static function format_action_severity(string $severity): string
    {
        $normalized = sanitize_key($severity);
        if ($normalized === 'critical') {
            return 'Critical';
        }
        if ($normalized === 'warning') {
            return 'Warning';
        }

        return 'Info';
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
