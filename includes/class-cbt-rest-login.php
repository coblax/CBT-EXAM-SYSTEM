<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Login_Routes
{
    public static function login(WP_REST_Request $request)
    {
        $identifier = (string) $request->get_param('identifier');
        if ($identifier === '') {
            $identifier = (string) $request->get_param('email');
        }
        if ($identifier === '') {
            $identifier = (string) $request->get_param('username');
        }
        if ($identifier === '') {
            $identifier = (string) $request->get_param('nisn');
        }
        $password = (string) $request->get_param('password');

        if (!$identifier || !$password) {
            return new WP_Error('invalid_payload', 'Identifier and password are required', ['status' => 400]);
        }

        $result = CBT_Auth::login($identifier, $password);
        if (is_wp_error($result)) {
            return $result;
        }

        self::mark_priority_window('login');

        return rest_ensure_response($result);
    }

    public static function logout(WP_REST_Request $request)
    {
        $user_id = CBT_Auth::current_user_id($request);
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        CBT_Auth::logout_current_session($request);
        CBT_Cache::invalidate_user($user_id);

        return rest_ensure_response([
            'ok' => true,
        ]);
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    public static function entry_flow_metric(WP_REST_Request $request)
    {
        $user_id = method_exists('CBT_Auth', 'current_user_id')
            ? (int) CBT_Auth::current_user_id($request)
            : (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $flow = sanitize_key((string) self::get_request_payload_value($request, 'flow'));
        $metric_key = sanitize_text_field((string) self::get_request_payload_value($request, 'metric_key'));
        $duration_raw = self::get_request_payload_value($request, 'duration_ms');
        $duration_ms = is_numeric($duration_raw) ? (int) $duration_raw : -1;
        $phase_durations = self::get_request_payload_value($request, 'phase_durations');
        $phase_durations = is_array($phase_durations) ? $phase_durations : [];
        $exam_id = absint(self::get_request_payload_value($request, 'exam_id'));
        $attempt_id = absint(self::get_request_payload_value($request, 'attempt_id'));

        if ($flow === '') {
            return new WP_Error('invalid_entry_flow_metric_flow', 'Flow metric wajib diisi.', ['status' => 400]);
        }

        if (
            !class_exists('CBT_Entry_Flow_Metrics_Service')
            || !CBT_Entry_Flow_Metrics_Service::is_allowed_flow($flow)
        ) {
            return new WP_Error('invalid_entry_flow_metric_flow', 'Flow metric tidak didukung.', ['status' => 400]);
        }

        if (trim($metric_key) === '') {
            return new WP_Error('invalid_entry_flow_metric_key', 'Metric key wajib diisi.', ['status' => 400]);
        }

        if ($duration_ms < 0) {
            return new WP_Error('invalid_entry_flow_metric_duration', 'Duration metric tidak valid.', ['status' => 400]);
        }

        $result = class_exists('CBT_Entry_Flow_Metrics_Service')
            ? CBT_Entry_Flow_Metrics_Service::record_flow_event(
                $flow,
                $metric_key,
                $duration_ms,
                $phase_durations,
                [
                    'user_id' => $user_id,
                    'exam_id' => $exam_id,
                    'attempt_id' => $attempt_id,
                    'route' => method_exists($request, 'get_route') ? (string) $request->get_route() : '',
                ]
            )
            : ['recorded' => false, 'duplicate' => false, 'skipped' => true];

        return [
            'ok' => true,
            'duplicate' => !empty($result['duplicate']),
            'skipped' => !empty($result['skipped']),
        ];
    }
}
