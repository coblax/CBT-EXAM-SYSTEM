<?php

if (!defined('ABSPATH')) {
    exit;
}

trait CBT_REST_Login_Routes
{
    /**
     * @return array{value:string,invalid:bool}
     */
    private static function read_login_text_param(WP_REST_Request $request, string $key, bool $trim = true): array
    {
        $value = $request->get_param($key);
        if ($value === null) {
            return [
                'value' => '',
                'invalid' => false,
            ];
        }

        if (is_array($value) || is_object($value) || is_resource($value) || is_bool($value)) {
            return [
                'value' => '',
                'invalid' => true,
            ];
        }

        if (function_exists('wp_unslash')) {
            $value = wp_unslash($value);
        }

        $value = (string) $value;
        if ($trim) {
            $value = trim($value);
        }

        return [
            'value' => $value,
            'invalid' => false,
        ];
    }

    /**
     * @param array<int,string> $keys
     * @return array{value:string,invalid:bool}
     */
    private static function read_first_login_text_param(WP_REST_Request $request, array $keys, bool $trim = true): array
    {
        foreach ($keys as $key) {
            $param = self::read_login_text_param($request, $key, $trim);
            if (!empty($param['invalid'])) {
                return $param;
            }

            if ($param['value'] !== '') {
                return $param;
            }
        }

        return [
            'value' => '',
            'invalid' => false,
        ];
    }

    private static function get_login_rate_limit_delay(int $attempts): int
    {
        $penalized_attempts = max(1, $attempts - 4);
        return min(240, $penalized_attempts * 5);
    }

    private static function get_login_rate_limit_attempt_ttl(): int
    {
        return 3600;
    }

    private static function build_login_rate_limited_error(int $retry_after): WP_Error
    {
        $retry_after = max(1, $retry_after);
        return new WP_Error(
            'too_many_requests',
            'Terlalu banyak percobaan yang gagal. Coba lagi setelah jeda singkat.',
            [
                'status' => 429,
                'retry_after' => $retry_after,
                'retry_after_ms' => $retry_after * 1000,
            ]
        );
    }

    private static function attach_login_retry_after(WP_Error $error, int $retry_after): WP_Error
    {
        $data = $error->get_error_data();
        if (!is_array($data)) {
            $data = [];
        }

        $retry_after = max(1, $retry_after);
        $data['retry_after'] = $retry_after;
        $data['retry_after_ms'] = $retry_after * 1000;

        return new WP_Error($error->get_error_code(), $error->get_error_message(), $data);
    }

    public static function login(WP_REST_Request $request)
    {
        $identifier_param = self::read_first_login_text_param($request, ['identifier', 'email', 'username', 'nisn']);
        $password_param = self::read_login_text_param($request, 'password', false);

        if (!empty($identifier_param['invalid']) || !empty($password_param['invalid'])) {
            return new WP_Error('invalid_payload', 'Identifier and password must be text values', ['status' => 400]);
        }

        $identifier = $identifier_param['value'];
        $password = $password_param['value'];

        if ($identifier === '' || $password === '') {
            return new WP_Error('invalid_payload', 'Identifier and password are required', ['status' => 400]);
        }

        if (strlen($identifier) > 191 || strlen($password) > 1024) {
            return new WP_Error('invalid_payload', 'Identifier or password is too long', ['status' => 400]);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $limit_key = 'cbt_rl_' . md5($ip . '_' . strtolower($identifier));
        $block_key = $limit_key . '_block';

        $blocked_until = get_transient($block_key);
        if ($blocked_until !== false && time() < (int) $blocked_until) {
            $retry_after = max(1, (int) $blocked_until - time());
            return self::build_login_rate_limited_error($retry_after);
        }
        if ($blocked_until !== false) {
            delete_transient($block_key);
        }

        $result = CBT_Auth::login($identifier, $password);
        if (is_wp_error($result)) {
            if ($result->get_error_code() !== 'invalid_credentials') {
                return $result;
            }

            $attempts = max(0, (int) get_transient($limit_key)) + 1;
            set_transient($limit_key, $attempts, self::get_login_rate_limit_attempt_ttl());

            if ($attempts < 5) {
                return $result;
            }

            $retry_after = self::get_login_rate_limit_delay($attempts);
            set_transient($block_key, time() + $retry_after, $retry_after);

            return self::attach_login_retry_after($result, $retry_after);
        }

        delete_transient($limit_key);
        delete_transient($block_key);

        self::mark_priority_window('login');

        return rest_ensure_response($result);
    }

    public static function logout(WP_REST_Request $request)
    {
        $user_id = CBT_Auth::request_token_user_id($request);
        if ($user_id <= 0) {
            return new WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $cleared = CBT_Auth::logout_current_session($request);
        if (!$cleared) {
            return new WP_Error(
                'logout_session_mismatch',
                'Sesi server tidak cocok dengan token logout. Muat ulang halaman atau minta reset login.',
                ['status' => 409]
            );
        }

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
