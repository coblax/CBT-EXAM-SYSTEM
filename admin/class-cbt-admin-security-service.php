<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Live_Attempt_Roster_Index')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-live-attempt-roster-index.php';
}

if (!class_exists('CBT_Security_User_Agent_Guard')) {
    require_once dirname(__DIR__) . '/includes/class-cbt-security-user-agent-guard.php';
}

final class CBT_Admin_Security_Service
{
    private const SECURITY_OPTION_KEY = 'cbt_setup_security';
    private const EXAM_WATERMARK_OPACITY_DEFAULT = 0.07;
    private const EXAM_WATERMARK_OPACITY_MIN = 0.03;
    private const EXAM_WATERMARK_OPACITY_MAX = 0.12;

    public static function can_manage_exams(): bool
    {
        return CBT_Admin_Setup_Service::can_manage_exams();
    }

    public static function is_admin_scope(): bool
    {
        return CBT_Admin_Setup_Service::is_admin_scope();
    }

    public static function security_option_key(): string
    {
        return self::SECURITY_OPTION_KEY;
    }

    /**
     * @return array{
     *     force_fullscreen:int,
     *     block_copy_paste:int,
     *     block_browser_inspection_shortcuts:int,
     *     log_security_events:int,
     *     security_redis_first_ingest:int,
     *     detect_idle_during_exam:int,
     *     detect_heartbeat_lost:int,
     *     idle_threshold_minutes:int,
     *     detect_screenshot_keys:int,
     *     show_exam_watermark:int,
     *     exam_watermark_opacity:float,
     *     restrict_student_user_agent:int,
     *     allowed_user_agents:array<int,string>
     * }
     */
    public static function get_security_settings(): array
    {
        $raw = get_option(self::SECURITY_OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $force_fullscreen = !empty($raw['force_fullscreen']);
        $block_copy_paste = !empty($raw['block_copy_paste']);
        $block_browser_inspection_shortcuts = !empty($raw['block_browser_inspection_shortcuts']);
        $log_security_events = !empty($raw['log_security_events']);
        $security_redis_first_ingest = !empty($raw['security_redis_first_ingest']);
        $detect_idle_during_exam = !array_key_exists('detect_idle_during_exam', $raw) || !empty($raw['detect_idle_during_exam']);
        $detect_heartbeat_lost = !empty($raw['detect_heartbeat_lost']);
        $idle_threshold_minutes = max(1, absint($raw['idle_threshold_minutes'] ?? 5));
        $detect_screenshot_keys = !empty($raw['detect_screenshot_keys']);
        $show_exam_watermark = !empty($raw['show_exam_watermark']);
        $exam_watermark_opacity = self::normalize_exam_watermark_opacity(
            $raw['exam_watermark_opacity'] ?? self::EXAM_WATERMARK_OPACITY_DEFAULT
        );
        $restrict_student_user_agent = !empty($raw['restrict_student_user_agent']);
        $allowed_user_agents = CBT_Security_User_Agent_Guard::normalize_allowed_user_agents($raw['allowed_user_agents'] ?? []);

        return [
            'force_fullscreen' => $force_fullscreen ? 1 : 0,
            'block_copy_paste' => $block_copy_paste ? 1 : 0,
            'block_browser_inspection_shortcuts' => $block_browser_inspection_shortcuts ? 1 : 0,
            'log_security_events' => $log_security_events ? 1 : 0,
            'security_redis_first_ingest' => $security_redis_first_ingest ? 1 : 0,
            'detect_idle_during_exam' => $detect_idle_during_exam ? 1 : 0,
            'detect_heartbeat_lost' => $detect_heartbeat_lost ? 1 : 0,
            'idle_threshold_minutes' => $idle_threshold_minutes,
            'detect_screenshot_keys' => $detect_screenshot_keys ? 1 : 0,
            'show_exam_watermark' => $show_exam_watermark ? 1 : 0,
            'exam_watermark_opacity' => $exam_watermark_opacity,
            'restrict_student_user_agent' => $restrict_student_user_agent ? 1 : 0,
            'allowed_user_agents' => $allowed_user_agents,
        ];
    }

    public static function normalize_exam_watermark_opacity($value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        if (!is_numeric($value)) {
            $opacity = self::EXAM_WATERMARK_OPACITY_DEFAULT;
        } else {
            $opacity = (float) $value;
        }

        $opacity = max(self::EXAM_WATERMARK_OPACITY_MIN, min(self::EXAM_WATERMARK_OPACITY_MAX, $opacity));
        return round($opacity, 3);
    }

    public static function flush_security_settings_cache(): void
    {
        if (!function_exists('wp_cache_delete')) {
            return;
        }

        wp_cache_delete(self::SECURITY_OPTION_KEY, 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';

        $security = self::get_security_settings();
        $security_force_fullscreen = !empty($security['force_fullscreen']);
        $security_block_copy_paste = !empty($security['block_copy_paste']);
        $security_block_browser_inspection_shortcuts = !empty($security['block_browser_inspection_shortcuts']);
        $security_log_events_enabled = !empty($security['log_security_events']);
        $security_redis_first_ingest = !empty($security['security_redis_first_ingest']);
        $security_detect_idle_during_exam = !empty($security['detect_idle_during_exam']);
        $security_detect_heartbeat_lost = !empty($security['detect_heartbeat_lost']);
        $security_idle_threshold_minutes = max(1, (int) ($security['idle_threshold_minutes'] ?? 5));
        $security_detect_screenshot_keys = !empty($security['detect_screenshot_keys']);
        $security_show_exam_watermark = !empty($security['show_exam_watermark']);
        $security_exam_watermark_opacity = self::normalize_exam_watermark_opacity($security['exam_watermark_opacity'] ?? self::EXAM_WATERMARK_OPACITY_DEFAULT);
        $security_restrict_student_user_agent = !empty($security['restrict_student_user_agent']);
        $security_allowed_user_agents = CBT_Security_User_Agent_Guard::normalize_allowed_user_agents($security['allowed_user_agents'] ?? []);
        $security_allowed_user_agents_text = implode("\n", $security_allowed_user_agents);
        $security_log_event_definitions = CBT_Security_Log::event_definitions();

        $security_live_snapshot = self::build_security_observability_snapshot(false);
        $security_live_roster_groups = (array) ($security_live_snapshot['live_roster_groups'] ?? []);
        $security_log_must_watch_attempts = (array) ($security_live_snapshot['must_watch_attempts'] ?? []);
        $security_logs_page = self::build_security_logs_page_payload([
            'page' => 1,
            'per_page' => 20,
        ]);
        $security_logs = (array) ($security_logs_page['logs'] ?? []);
        $security_must_watch_score_threshold = CBT_Security_Log::must_watch_score_threshold();
        $security_must_watch_high_risk_threshold = CBT_Security_Log::must_watch_high_risk_threshold();
        $security_log_status_snapshot = (array) ($security_live_snapshot['status_snapshot'] ?? []);
        $security_observability_endpoint_url = rest_url('cbt/v1/security_observability_snapshot');
        $security_logs_page_endpoint_url = rest_url('cbt/v1/security_logs_page');
        $security_ingest_action_endpoint_url = rest_url('cbt/v1/security_ingest_admin_action');
        $security_rest_nonce = wp_create_nonce('wp_rest');
        $native_browser_event_catalog = self::build_event_catalog(CBT_Security_Log::browser_supported_event_definitions(), ['android_webview', 'windows_cefsharp']);
        $native_android_event_catalog = self::build_event_catalog(CBT_Security_Log::android_native_supported_event_definitions(), ['android_webview']);
        $native_windows_event_catalog = self::build_event_catalog(CBT_Security_Log::windows_native_supported_event_definitions(), ['windows_cefsharp']);
        $native_simulation_event_catalog = self::build_simulation_event_catalog();
        $native_supported_apps = CBT_Security_Log::native_app_labels();
        $native_security_endpoint_url = rest_url('cbt/v1/native_security_event');
        $native_security_sample_attempt_id = 0;

        if (!empty($security_log_must_watch_attempts)) {
            $native_security_sample_attempt_id = (int) ($security_log_must_watch_attempts[0]['attempt_id'] ?? 0);
        }

        if ($native_security_sample_attempt_id <= 0 && !empty($security_logs)) {
            $native_security_sample_attempt_id = (int) ($security_logs[0]['attempt_id'] ?? 0);
        }

        return compact(
            'error',
            'notice',
            'native_android_event_catalog',
            'native_browser_event_catalog',
            'native_security_endpoint_url',
            'native_security_sample_attempt_id',
            'native_simulation_event_catalog',
            'native_supported_apps',
            'native_windows_event_catalog',
            'security_block_browser_inspection_shortcuts',
            'security_block_copy_paste',
            'security_detect_idle_during_exam',
            'security_detect_heartbeat_lost',
            'security_force_fullscreen',
            'security_idle_threshold_minutes',
            'security_allowed_user_agents',
            'security_allowed_user_agents_text',
            'security_detect_screenshot_keys',
            'security_show_exam_watermark',
            'security_exam_watermark_opacity',
            'security_redis_first_ingest',
            'security_restrict_student_user_agent',
            'security_log_event_definitions',
            'security_log_events_enabled',
            'security_log_status_snapshot',
            'security_must_watch_high_risk_threshold',
            'security_must_watch_score_threshold',
            'security_live_roster_groups',
            'security_live_snapshot',
            'security_log_must_watch_attempts',
            'security_logs',
            'security_observability_endpoint_url',
            'security_ingest_action_endpoint_url',
            'security_logs_page_endpoint_url',
            'security_rest_nonce'
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function build_security_observability_snapshot(bool $allow_micro_drain = true): array
    {
        $teacher_scope = self::is_admin_scope() ? 0 : get_current_user_id();
        if ($allow_micro_drain && class_exists('CBT_Security_Event_Ingest')) {
            CBT_Security_Event_Ingest::maybe_micro_drain();
        }

        $live_roster_groups = class_exists('CBT_Live_Attempt_Roster_Index')
            ? CBT_Live_Attempt_Roster_Index::get_grouped_payloads([
                'teacher_id' => $teacher_scope,
            ])
            : [];
        $must_watch_attempts = CBT_Security_Log::get_must_watch_attempts(5, [
            'teacher_id' => $teacher_scope,
        ]);
        $status_snapshot = class_exists('CBT_Security_Event_Ingest')
            ? CBT_Security_Event_Ingest::get_status_snapshot()
            : [
                'mode' => class_exists('CBT_Security_Live_Counters') && CBT_Security_Live_Counters::is_available()
                    ? 'redis_live'
                    : 'mysql_fallback',
                'status_label' => class_exists('CBT_Security_Live_Counters') && CBT_Security_Live_Counters::is_available()
                    ? 'Live Redis • Ingest direct MySQL • Persist direct MySQL'
                    : 'Live MySQL fallback • Ingest direct MySQL • Persist direct MySQL',
                'live_label' => class_exists('CBT_Security_Live_Counters') && CBT_Security_Live_Counters::is_available()
                    ? 'Live Redis'
                    : 'Live MySQL fallback',
                'ingest_label' => 'Ingest direct MySQL',
                'persist_label' => 'Persist direct MySQL',
                'backlog_count' => 0,
                'dead_letter_count' => 0,
                'oldest_pending_age_seconds' => 0,
                'worker_scheduled' => 0,
                'next_flush_at' => '',
                'last_enqueue_at' => '',
                'last_enqueue_status' => '',
                'last_enqueue_error' => '',
                'last_flush_at' => '',
                'last_flush_status' => '',
                'last_flush_result' => '',
                'last_stream_id' => '',
                'ingest_mode' => 'disabled',
            ];

        $live_roster_total = 0;
        foreach ((array) $live_roster_groups as $group) {
            $live_roster_total += count((array) ($group['attempts'] ?? []));
        }

        return [
            'mode' => sanitize_key((string) ($status_snapshot['mode'] ?? 'mysql_fallback')),
            'status_snapshot' => $status_snapshot,
            'live_roster_groups' => $live_roster_groups,
            'must_watch_attempts' => $must_watch_attempts,
            'live_roster_total' => $live_roster_total,
            'must_watch_total' => count((array) $must_watch_attempts),
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_security_logs_page_payload(array $query): array
    {
        $teacher_scope = self::is_admin_scope() ? 0 : get_current_user_id();
        $page = max(1, absint($query['page'] ?? 1));
        $per_page = max(1, min(50, absint($query['per_page'] ?? 20)));
        $filters = [
            'teacher_id' => $teacher_scope,
        ];

        $rows = CBT_Security_Log::get_recent_logs(50, $filters);
        $filtered = array_values(array_filter($rows, static function (array $row) use ($query): bool {
            $severity = sanitize_key((string) ($query['severity'] ?? 'all'));
            $event_type = sanitize_key((string) ($query['event_type'] ?? 'all'));
            $device_type = sanitize_key((string) ($query['device_type'] ?? 'all'));
            $kelas = sanitize_text_field((string) ($query['kelas'] ?? 'all'));
            $ruang = sanitize_text_field((string) ($query['ruang'] ?? 'all'));
            $student_name = function_exists('mb_strtolower')
                ? mb_strtolower(trim((string) ($query['student_name'] ?? '')), 'UTF-8')
                : strtolower(trim((string) ($query['student_name'] ?? '')));

            if ($severity !== '' && $severity !== 'all' && sanitize_key((string) ($row['severity'] ?? '')) !== $severity) {
                return false;
            }

            if ($event_type !== '' && $event_type !== 'all' && sanitize_key((string) ($row['event_type'] ?? '')) !== $event_type) {
                return false;
            }

            if ($device_type !== '' && $device_type !== 'all' && sanitize_key((string) ($row['device_type'] ?? '')) !== $device_type) {
                return false;
            }

            if ($kelas !== '' && $kelas !== 'all' && sanitize_text_field((string) ($row['student_kode_kelas'] ?? '')) !== $kelas) {
                return false;
            }

            if ($ruang !== '' && $ruang !== 'all' && sanitize_text_field((string) ($row['student_kode_ruang'] ?? '')) !== $ruang) {
                return false;
            }

            if ($student_name !== '') {
                $candidate = function_exists('mb_strtolower')
                    ? mb_strtolower((string) ($row['student_name'] ?? ''), 'UTF-8')
                    : strtolower((string) ($row['student_name'] ?? ''));
                if (strpos($candidate, $student_name) === false) {
                    return false;
                }
            }

            return true;
        }));

        $total = count($filtered);
        $page_count = max(1, (int) ceil($total / $per_page));
        $page = min($page, $page_count);
        $offset = ($page - 1) * $per_page;

        return [
            'logs' => array_slice($filtered, $offset, $per_page),
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'page_count' => $page_count,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function build_event_catalog(array $definitions, array $supported_apps = []): array
    {
        $catalog = [];

        foreach ($definitions as $event_type => $definition) {
            $catalog[] = [
                'event_type' => $event_type,
                'label' => (string) ($definition['label'] ?? $event_type),
                'severity' => (string) ($definition['severity'] ?? 'info'),
                'message' => (string) ($definition['message'] ?? ''),
                'risk_weight' => CBT_Security_Log::get_event_risk_weight($event_type),
                'supported_apps' => $supported_apps,
            ];
        }

        return $catalog;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function build_simulation_event_catalog(): array
    {
        $grouped = [];

        foreach (self::build_event_catalog(CBT_Security_Log::android_native_supported_event_definitions(), ['android_webview']) as $item) {
            $event_type = (string) ($item['event_type'] ?? '');
            if ($event_type === '') {
                continue;
            }
            $grouped[$event_type] = $item;
        }

        foreach (self::build_event_catalog(CBT_Security_Log::windows_native_supported_event_definitions(), ['windows_cefsharp']) as $item) {
            $event_type = (string) ($item['event_type'] ?? '');
            if ($event_type === '') {
                continue;
            }

            if (isset($grouped[$event_type])) {
                $existing_apps = isset($grouped[$event_type]['supported_apps']) && is_array($grouped[$event_type]['supported_apps'])
                    ? $grouped[$event_type]['supported_apps']
                    : [];
                $next_apps = isset($item['supported_apps']) && is_array($item['supported_apps'])
                    ? $item['supported_apps']
                    : [];
                $grouped[$event_type]['supported_apps'] = array_values(array_unique(array_merge($existing_apps, $next_apps)));
                continue;
            }

            $grouped[$event_type] = $item;
        }

        return array_values($grouped);
    }
}
