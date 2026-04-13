<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Shared_Helpers
{
    private static function is_student_role(string $role): bool
    {
        return in_array(strtolower(trim($role)), ['siswa', 'student'], true);
    }
    private static function get_request_payload_value(WP_REST_Request $request, string $key)
    {
        $value = $request->get_param($key);
        if ($value !== null) {
            return $value;
        }

        if (method_exists($request, 'get_json_params')) {
            $json_params = $request->get_json_params();
            if (is_array($json_params) && array_key_exists($key, $json_params)) {
                return $json_params[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function enrich_security_event_context(WP_REST_Request $request, array $context): array
    {
        $user_agent = '';

        if (method_exists($request, 'get_header')) {
            $user_agent = (string) $request->get_header('user_agent');
            if ($user_agent === '') {
                $user_agent = (string) $request->get_header('user-agent');
            }
        }

        if (empty($context['device_type'])) {
            $context['device_type'] = self::detect_security_request_device_type($user_agent);
        }

        if (empty($context['device_platform'])) {
            $context['device_platform'] = self::detect_security_request_device_platform($user_agent);
        }

        return $context;
    }

    /**
     * @param array<string,mixed> $context
     * @param array{native_app:mixed,native_version:mixed,warning_code:mixed,warning_message:mixed,occurred_at_client:mixed} $payload
     * @return array<string,mixed>
     */
    private static function enrich_native_security_event_context(WP_REST_Request $request, array $context, array $payload): array
    {
        $context = self::enrich_security_event_context($request, $context);

        $native_app = sanitize_key((string) ($payload['native_app'] ?? ''));
        $context['source'] = CBT_Security_Log::native_app_source($native_app);
        $context['native_app'] = $native_app;

        $native_version = sanitize_text_field((string) ($payload['native_version'] ?? ''));
        if ($native_version !== '') {
            $context['native_version'] = $native_version;
        }

        $warning_code = sanitize_key((string) ($payload['warning_code'] ?? ''));
        if ($warning_code !== '') {
            $context['warning_code'] = $warning_code;
        }

        $warning_message = sanitize_text_field((string) ($payload['warning_message'] ?? ''));
        if ($warning_message !== '') {
            $context['warning_message'] = $warning_message;
        }

        $occurred_at_client = sanitize_text_field((string) ($payload['occurred_at_client'] ?? ''));
        if ($occurred_at_client !== '') {
            $context['occurred_at_client'] = $occurred_at_client;
        }

        return $context;
    }

    private static function detect_security_request_device_type(string $user_agent): string
    {
        $user_agent = strtolower($user_agent);
        if ($user_agent === '') {
            return 'unknown';
        }

        $is_tablet = (bool) preg_match('/\b(ipad|tablet|playbook|silk)\b/', $user_agent)
            || (strpos($user_agent, 'android') !== false && strpos($user_agent, 'mobile') === false);
        if ($is_tablet) {
            return 'tablet';
        }

        if (preg_match('/\b(mobi|iphone|ipod|android.*mobile|windows phone)\b/', $user_agent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * @param array<string,mixed> $attempt
     */
    private static function maybe_update_attempt_presence_from_session(array $attempt, WP_REST_Request $request): void
    {
        if (!class_exists('CBT_Live_Proctoring_Presence') || !CBT_Live_Proctoring_Presence::is_available()) {
            return;
        }

        if (strtolower((string) ($attempt['status'] ?? '')) !== 'in_progress') {
            return;
        }

        $presence = [];
        if ($request->get_param('presence_connection_status') !== null) {
            $presence['connection_status'] = sanitize_text_field((string) $request->get_param('presence_connection_status'));
        }
        if ($request->get_param('presence_visibility_state') !== null) {
            $presence['visibility_state'] = sanitize_text_field((string) $request->get_param('presence_visibility_state'));
        }
        if ($request->get_param('presence_has_focus') !== null) {
            $presence['has_focus'] = self::normalize_request_flag($request->get_param('presence_has_focus'));
        }
        if ($request->get_param('presence_pending_sync_count') !== null) {
            $presence['pending_sync_count'] = max(0, (int) $request->get_param('presence_pending_sync_count'));
        }
        if ($request->get_param('presence_heartbeat_lost_active') !== null) {
            $presence['heartbeat_lost_active'] = self::normalize_request_flag($request->get_param('presence_heartbeat_lost_active'));
        }

        if (empty($presence)) {
            return;
        }

        CBT_Live_Proctoring_Presence::update_attempt_presence($attempt, $presence);
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $context
     */
    private static function maybe_update_attempt_presence_from_context(array $attempt, array $context): void
    {
        if (!class_exists('CBT_Live_Proctoring_Presence') || !CBT_Live_Proctoring_Presence::is_available()) {
            return;
        }

        if (strtolower((string) ($attempt['status'] ?? '')) !== 'in_progress') {
            return;
        }

        $presence = [];
        foreach (['connection_status', 'visibility_state', 'has_focus', 'pending_sync_count', 'heartbeat_lost_active'] as $key) {
            if (array_key_exists($key, $context)) {
                $presence[$key] = $context[$key];
            }
        }

        if (empty($presence)) {
            return;
        }

        CBT_Live_Proctoring_Presence::update_attempt_presence($attempt, $presence);
    }

    /**
     * @param mixed $value
     */
    private static function normalize_request_flag($value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    private static function detect_security_request_device_platform(string $user_agent): string
    {
        $user_agent = strtolower($user_agent);
        if ($user_agent === '') {
            return 'unknown';
        }

        if (strpos($user_agent, 'android') !== false) {
            return 'android';
        }
        if (preg_match('/\b(iphone|ipad|ipod)\b/', $user_agent)) {
            return 'ios';
        }
        if (strpos($user_agent, 'cros') !== false) {
            return 'chromeos';
        }
        if (strpos($user_agent, 'windows') !== false) {
            return 'windows';
        }
        if (strpos($user_agent, 'mac os') !== false || strpos($user_agent, 'macintosh') !== false) {
            return 'macos';
        }
        if (strpos($user_agent, 'linux') !== false || strpos($user_agent, 'x11') !== false) {
            return 'linux';
        }

        return 'unknown';
    }
    private static function mark_priority_window(string $source = ''): void
    {
        $seconds = (int) apply_filters('cbt_exam_priority_window_seconds', 10, $source);
        if ($seconds <= 0) {
            return;
        }
        if ($seconds > 120) {
            $seconds = 120;
        }

        $now = time();
        $target_until = $now + $seconds;
        $current_until = (int) get_transient(self::PRIORITY_WINDOW_TRANSIENT_KEY);
        if ($current_until >= $target_until) {
            return;
        }

        set_transient(
            self::PRIORITY_WINDOW_TRANSIENT_KEY,
            $target_until,
            max(30, $seconds * 3)
        );
    }

    private static function is_priority_window_active(): bool
    {
        $until = (int) get_transient(self::PRIORITY_WINDOW_TRANSIENT_KEY);
        return $until > time();
    }

    private static function should_defer_submit_scoring(): bool
    {
        $enabled = (bool) apply_filters('cbt_submit_priority_mode_enabled', true);
        if (!$enabled) {
            return false;
        }

        if (self::is_priority_window_active()) {
            return true;
        }

        $load_threshold = (float) apply_filters('cbt_submit_defer_load_threshold', 0.0);
        if ($load_threshold > 0 && function_exists('sys_getloadavg')) {
            $load_values = sys_getloadavg();
            $load_1m = is_array($load_values) ? (float) ($load_values[0] ?? 0.0) : 0.0;
            if ($load_1m >= $load_threshold) {
                return true;
            }
        }

        return false;
    }

    private static function normalize_kelas_code(string $value): string
    {
        return strtoupper(sanitize_text_field(trim($value)));
    }

    /**
     * @return string[]
     */
    private static function parse_exam_target_kelas(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r", "\n", ';', '|'], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        $items = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $normalized = self::normalize_kelas_code($part);
            if ($normalized === '') {
                continue;
            }
            $items[$normalized] = $normalized;
        }

        return array_values($items);
    }

    private static function exam_allows_student_class(array $exam, string $student_kelas): bool
    {
        $target_kelas = self::parse_exam_target_kelas((string) ($exam['target_kelas'] ?? ''));
        if (empty($target_kelas)) {
            return true;
        }

        $student_kelas = self::normalize_kelas_code($student_kelas);
        if ($student_kelas === '') {
            return false;
        }

        return in_array($student_kelas, $target_kelas, true);
    }
}
