<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Security_Service
{
    private const SECURITY_OPTION_KEY = 'cbt_setup_security';

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
     * @return array{force_fullscreen:int,block_copy_paste:int,log_security_events:int,detect_idle_during_exam:int,idle_threshold_minutes:int}
     */
    public static function get_security_settings(): array
    {
        $raw = get_option(self::SECURITY_OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $force_fullscreen = !empty($raw['force_fullscreen']);
        $block_copy_paste = !empty($raw['block_copy_paste']);
        $log_security_events = !empty($raw['log_security_events']);
        $detect_idle_during_exam = !array_key_exists('detect_idle_during_exam', $raw) || !empty($raw['detect_idle_during_exam']);
        $idle_threshold_minutes = max(1, absint($raw['idle_threshold_minutes'] ?? 5));

        return [
            'force_fullscreen' => $force_fullscreen ? 1 : 0,
            'block_copy_paste' => $block_copy_paste ? 1 : 0,
            'log_security_events' => $log_security_events ? 1 : 0,
            'detect_idle_during_exam' => $detect_idle_during_exam ? 1 : 0,
            'idle_threshold_minutes' => $idle_threshold_minutes,
        ];
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
        $security_log_events_enabled = !empty($security['log_security_events']);
        $security_detect_idle_during_exam = !empty($security['detect_idle_during_exam']);
        $security_idle_threshold_minutes = max(1, (int) ($security['idle_threshold_minutes'] ?? 5));
        $security_log_event_definitions = CBT_Security_Log::event_definitions();

        $teacher_scope = self::is_admin_scope() ? 0 : get_current_user_id();
        $security_log_must_watch_attempts = CBT_Security_Log::get_must_watch_attempts(5, [
            'teacher_id' => $teacher_scope,
        ]);
        $security_logs = CBT_Security_Log::get_recent_logs(20, [
            'teacher_id' => $teacher_scope,
        ]);
        $security_must_watch_score_threshold = CBT_Security_Log::must_watch_score_threshold();
        $security_must_watch_high_risk_threshold = CBT_Security_Log::must_watch_high_risk_threshold();
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
            'security_block_copy_paste',
            'security_detect_idle_during_exam',
            'security_force_fullscreen',
            'security_idle_threshold_minutes',
            'security_log_event_definitions',
            'security_log_events_enabled',
            'security_must_watch_high_risk_threshold',
            'security_must_watch_score_threshold',
            'security_log_must_watch_attempts',
            'security_logs'
        );
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
