<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Security_Events_Routes
{
    public static function security_event(WP_REST_Request $request)
    {
        return self::handle_security_event_request($request, false);
    }

    public static function native_security_event(WP_REST_Request $request)
    {
        return self::handle_security_event_request($request, true);
    }

    private static function handle_security_event_request(WP_REST_Request $request, bool $native_mode)
    {
        $user_id = CBT_Auth::current_user_id($request);
        $role = CBT_Auth::current_user_role($request);
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        if (!in_array($role, ['siswa', 'student'], true)) {
            return new WP_Error('forbidden', 'Only student role can log security events', ['status' => 403]);
        }

        if (!CBT_Security_Log::is_logging_enabled()) {
            return rest_ensure_response([
                'ok' => true,
                'logged' => 0,
                'skipped' => 1,
                'reason' => 'logging_disabled',
            ]);
        }

        $attempt_id = (int) self::get_request_payload_value($request, 'attempt_id');
        if ($attempt_id <= 0) {
            return new WP_Error('invalid_payload', 'attempt_id is required', ['status' => 400]);
        }

        $event_type = sanitize_key((string) self::get_request_payload_value($request, 'event_type'));
        if ($event_type === '') {
            return new WP_Error('invalid_event_type', 'Event type is not allowed', ['status' => 400]);
        }

        if ($native_mode) {
            if (!isset(CBT_Security_Log::event_definitions()[$event_type])) {
                return new WP_Error('invalid_event_type', 'Event type is not allowed', ['status' => 400]);
            }
        } else {
            $browser_supported_event_definitions = CBT_Security_Log::browser_supported_event_definitions();
            if (!isset($browser_supported_event_definitions[$event_type])) {
                if (isset(CBT_Security_Log::native_supported_event_definitions()[$event_type])) {
                    return new WP_Error('native_event_requires_native_endpoint', 'Native event type must use native_security_event endpoint', ['status' => 400]);
                }

                return new WP_Error('invalid_event_type', 'Event type is not allowed', ['status' => 400]);
            }
        }

        $native_app = '';
        if ($native_mode) {
            $native_app = sanitize_key((string) self::get_request_payload_value($request, 'native_app'));
            $allowed_native_apps = CBT_Security_Log::native_app_labels();
            if ($native_app === '' || !isset($allowed_native_apps[$native_app])) {
                return new WP_Error('invalid_native_app', 'Native app is not allowed', ['status' => 400]);
            }

            if (!isset(CBT_Security_Log::native_supported_event_definitions_for_app($native_app)[$event_type])) {
                return new WP_Error('invalid_native_event_type', 'Native endpoint only accepts supported CBT security events', ['status' => 400]);
            }
        }

        $attempt = self::get_attempt_for_submission($attempt_id, $user_id);
        if (is_wp_error($attempt)) {
            return $attempt;
        }

        $context = self::get_request_payload_value($request, 'context');
        if (!is_array($context)) {
            $context = [];
        }

        if ($native_mode) {
            $context = self::enrich_native_security_event_context($request, $context, [
                'native_app' => $native_app,
                'native_version' => self::get_request_payload_value($request, 'native_version'),
                'warning_code' => self::get_request_payload_value($request, 'warning_code'),
                'warning_message' => self::get_request_payload_value($request, 'warning_message'),
                'occurred_at_client' => self::get_request_payload_value($request, 'occurred_at_client'),
            ]);
        } else {
            $context = self::enrich_security_event_context($request, $context);
        }

        $logged = CBT_Security_Log::record_attempt_event_for_context($attempt, $event_type, $context);
        if ($logged) {
            self::maybe_update_attempt_presence_from_context($attempt, $context);
        }

        return rest_ensure_response([
            'ok' => true,
            'event_type' => $event_type,
            'logged' => $logged ? 1 : 0,
            'skipped' => $logged ? 0 : 1,
        ]);
    }

    public static function security_observability_snapshot(WP_REST_Request $request)
    {
        $allow_micro_drain = absint((int) $request->get_param('micro_drain')) === 1;
        $snapshot = class_exists('CBT_Admin_Security_Service')
            ? CBT_Admin_Security_Service::build_security_observability_snapshot($allow_micro_drain)
            : [];

        $must_watch_attempts = is_array($snapshot['must_watch_attempts'] ?? null)
            ? $snapshot['must_watch_attempts']
            : [];
        $live_roster_groups = is_array($snapshot['live_roster_groups'] ?? null)
            ? $snapshot['live_roster_groups']
            : [];
        $status_snapshot = is_array($snapshot['status_snapshot'] ?? null)
            ? $snapshot['status_snapshot']
            : [];

        ob_start();
        if (class_exists('CBT_Admin_Security_Page')) {
            CBT_Admin_Security_Page::render_security_log_must_watch_panel($must_watch_attempts);
        }
        $must_watch_html = (string) ob_get_clean();

        ob_start();
        if (class_exists('CBT_Admin_Security_Page')) {
            CBT_Admin_Security_Page::render_security_log_live_roster_panel($live_roster_groups);
        }
        $live_roster_html = (string) ob_get_clean();

        return rest_ensure_response([
            'ok' => true,
            'mode' => sanitize_key((string) ($snapshot['mode'] ?? 'mysql_fallback')),
            'status_snapshot' => $status_snapshot,
            'must_watch_total' => max(0, (int) ($snapshot['must_watch_total'] ?? count($must_watch_attempts))),
            'live_roster_total' => max(0, (int) ($snapshot['live_roster_total'] ?? 0)),
            'must_watch_html' => $must_watch_html,
            'live_roster_html' => $live_roster_html,
        ]);
    }

    public static function security_logs_page(WP_REST_Request $request)
    {
        $query = [
            'page' => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
            'severity' => $request->get_param('severity'),
            'event_type' => $request->get_param('event_type'),
            'device_type' => $request->get_param('device_type'),
            'kelas' => $request->get_param('kelas'),
            'ruang' => $request->get_param('ruang'),
            'student_name' => $request->get_param('student_name'),
        ];

        $payload = class_exists('CBT_Admin_Security_Service')
            ? CBT_Admin_Security_Service::build_security_logs_page_payload($query)
            : [
                'logs' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => 20,
                'page_count' => 1,
            ];

        ob_start();
        if (class_exists('CBT_Admin_Security_Page')) {
            CBT_Admin_Security_Page::render_security_log_history_table_region(
                is_array($payload['logs'] ?? null) ? $payload['logs'] : [],
                class_exists('CBT_Security_Log') ? CBT_Security_Log::event_definitions() : []
            );
        }
        $history_html = (string) ob_get_clean();

        return rest_ensure_response([
            'ok' => true,
            'history_html' => $history_html,
            'total' => max(0, (int) ($payload['total'] ?? 0)),
            'page' => max(1, (int) ($payload['page'] ?? 1)),
            'per_page' => max(1, (int) ($payload['per_page'] ?? 20)),
            'page_count' => max(1, (int) ($payload['page_count'] ?? 1)),
        ]);
    }

    public static function security_ingest_admin_action(WP_REST_Request $request)
    {
        $action = sanitize_key((string) self::get_request_payload_value($request, 'action'));
        if ($action !== 'micro_drain' && $action !== 'flush_now') {
            return new WP_Error('invalid_action', 'Security ingest action is not allowed.', ['status' => 400]);
        }

        if (!class_exists('CBT_Security_Event_Ingest')) {
            $status_snapshot = class_exists('CBT_Admin_Security_Service')
                ? (array) (CBT_Admin_Security_Service::build_security_observability_snapshot(false)['status_snapshot'] ?? [])
                : [];

            return rest_ensure_response([
                'ok' => true,
                'action' => $action,
                'action_result' => [
                    'skipped' => 1,
                    'reason' => 'ingest_service_missing',
                ],
                'status_snapshot' => $status_snapshot,
            ]);
        }

        if ($action === 'micro_drain') {
            $action_result = CBT_Security_Event_Ingest::maybe_micro_drain();
        } else {
            $action_result = CBT_Security_Event_Ingest::flush_batch(500, 5.0, 'admin_force_flush');
        }

        return rest_ensure_response([
            'ok' => true,
            'action' => $action,
            'action_result' => is_array($action_result) ? $action_result : [],
            'status_snapshot' => CBT_Security_Event_Ingest::get_status_snapshot(),
        ]);
    }

    public static function permission_manage_security_admin(): bool
    {
        return class_exists('CBT_Admin_Security_Service') && CBT_Admin_Security_Service::can_manage_exams();
    }
}
