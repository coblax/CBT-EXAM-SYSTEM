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
        $live_roster = self::build_live_roster_context($scope, $filters);
        $must_watch = self::build_must_watch_context($scope, $filters);
        $monitoring_attempts = self::build_monitoring_attempts_context($scope, $filters);
        $submit_recovery = self::build_submit_recovery_context($monitoring_attempts);
        $active_tab = (string) ($filters['tab'] ?? 'overview');

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
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private static function normalize_filters(array $query): array
    {
        $tab = sanitize_key((string) ($query['tab'] ?? 'overview'));
        if (!in_array($tab, ['overview', 'live_roster', 'must_watch', 'monitoring_attempts', 'security_log', 'token_gate', 'submit_recovery', 'attendance'], true)) {
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
            'security_page' => max(1, (int) ($query['security_page'] ?? 1)),
            'security_severity' => self::normalize_all_filter((string) ($query['security_severity'] ?? 'all')),
            'security_event_type' => self::normalize_all_filter((string) ($query['security_event_type'] ?? 'all')),
            'security_device_type' => self::normalize_all_filter((string) ($query['security_device_type'] ?? 'all')),
            'attendance_page' => max(1, (int) ($query['attendance_page'] ?? 1)),
            'attendance_status' => self::normalize_attendance_status((string) ($query['attendance_status'] ?? '')),
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
