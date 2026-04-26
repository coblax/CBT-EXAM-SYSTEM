<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Security_User_Agent_Guard
{
    public const DEFAULT_ALLOWED_USER_AGENT = 'CBXExamLockAndroid';

    private const SECURITY_OPTION_KEY = 'cbt_setup_security';

    /**
     * @return array<int,string>
     */
    public static function default_allowed_user_agents(): array
    {
        return [self::DEFAULT_ALLOWED_USER_AGENT];
    }

    /**
     * @param mixed $raw
     * @return array<int,string>
     */
    public static function normalize_allowed_user_agents($raw): array
    {
        $items = [];
        if (is_array($raw)) {
            foreach ($raw as $value) {
                if (is_array($value)) {
                    continue;
                }
                $items[] = is_scalar($value) ? (string) $value : '';
            }
        } elseif (is_scalar($raw)) {
            $parts = preg_split('/\R+/', (string) $raw);
            $items = is_array($parts) ? $parts : [];
        }

        $normalized = [];
        $seen = [];

        foreach (array_merge(self::default_allowed_user_agents(), $items) as $item) {
            $value = self::sanitize_user_agent_pattern($item);
            if ($value === '') {
                continue;
            }

            $key = self::lowercase($value);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * @return array{restrict_student_user_agent:int,allowed_user_agents:array<int,string>}
     */
    public static function get_settings(): array
    {
        $raw = get_option(self::SECURITY_OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        return [
            'restrict_student_user_agent' => !empty($raw['restrict_student_user_agent']) ? 1 : 0,
            'allowed_user_agents' => self::normalize_allowed_user_agents($raw['allowed_user_agents'] ?? []),
        ];
    }

    /**
     * @param array<string,mixed>|null $settings
     */
    public static function is_restriction_enabled(?array $settings = null): bool
    {
        $settings = is_array($settings) ? $settings : self::get_settings();
        return !empty($settings['restrict_student_user_agent']);
    }

    /**
     * @param array<string,mixed>|null $settings
     * @return array<int,string>
     */
    public static function allowed_user_agents(?array $settings = null): array
    {
        $settings = is_array($settings) ? $settings : self::get_settings();
        return self::normalize_allowed_user_agents($settings['allowed_user_agents'] ?? []);
    }

    /**
     * @param array<string,mixed>|null $settings
     */
    public static function is_request_allowed(?WP_REST_Request $request = null, ?array $settings = null): bool
    {
        $settings = is_array($settings) ? $settings : self::get_settings();
        if (!self::is_restriction_enabled($settings)) {
            return true;
        }

        return self::is_user_agent_allowed(
            self::request_user_agent($request),
            self::allowed_user_agents($settings)
        );
    }

    /**
     * @param array<int,string>|null $allowed_user_agents
     */
    public static function is_user_agent_allowed(string $user_agent, ?array $allowed_user_agents = null): bool
    {
        $user_agent = trim($user_agent);
        if ($user_agent === '') {
            return false;
        }

        $haystack = self::lowercase($user_agent);
        foreach (self::normalize_allowed_user_agents($allowed_user_agents ?? []) as $pattern) {
            $needle = self::lowercase($pattern);
            if ($needle !== '' && strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function request_user_agent(?WP_REST_Request $request = null): string
    {
        if ($request instanceof WP_REST_Request && method_exists($request, 'get_header')) {
            $user_agent = (string) $request->get_header('user_agent');
            if ($user_agent === '') {
                $user_agent = (string) $request->get_header('user-agent');
            }
            if ($user_agent !== '') {
                return $user_agent;
            }
        }

        return isset($_SERVER['HTTP_USER_AGENT']) && is_scalar($_SERVER['HTTP_USER_AGENT'])
            ? (string) $_SERVER['HTTP_USER_AGENT']
            : '';
    }

    public static function guard_student_request(?WP_REST_Request $request, string $role)
    {
        if (!self::is_student_role($role) || self::is_request_allowed($request)) {
            return true;
        }

        return self::forbidden_error();
    }

    public static function forbidden_error(): WP_Error
    {
        return new WP_Error(
            'student_user_agent_forbidden',
            'Akses ujian hanya diizinkan dari aplikasi atau User-Agent yang terdaftar.',
            ['status' => 403]
        );
    }

    private static function is_student_role(string $role): bool
    {
        return in_array(sanitize_key($role), ['siswa', 'student'], true);
    }

    private static function sanitize_user_agent_pattern(string $value): string
    {
        if (function_exists('wp_unslash')) {
            $value = (string) wp_unslash($value);
        }

        $value = function_exists('sanitize_text_field')
            ? sanitize_text_field($value)
            : trim(strip_tags($value));

        return trim($value);
    }

    private static function lowercase(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}
