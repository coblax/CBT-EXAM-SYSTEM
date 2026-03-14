<?php

if (!defined('ABSPATH')) {
    exit;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class CBT_Auth
{
    private const OPTION_GLOBAL_EXAM_TOKEN = 'cbt_global_exam_token_value';
    private const OPTION_GLOBAL_EXAM_TOKEN_GENERATED_AT = 'cbt_global_exam_token_generated_at';
    private const OPTION_GLOBAL_EXAM_TOKEN_REFRESH_MINUTES = 'cbt_global_exam_token_refresh_minutes';
    private const OPTION_GLOBAL_EXAM_TOKEN_FRONTEND_AUTO_APPLY = 'cbt_global_exam_token_frontend_auto_apply';
    private const USER_META_ACTIVE_LOGIN_SESSION = 'cbt_active_login_session';
    private const USER_META_ACTIVE_LOGIN_SESSION_TOUCHED_AT = 'cbt_active_login_session_touched_at';
    private const DEFAULT_TOKEN_REFRESH_MINUTES = 15;
    private const EXAM_TOKEN_LENGTH = 6;
    private const EXAM_TOKEN_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ123456789';
    private const ACTIVE_LOGIN_TTL_SECONDS = 45;
    private const ACTIVE_LOGIN_TOUCH_DEBOUNCE_SECONDS = 15;
    /**
     * Cache decoded token selama satu request.
     *
     * @var array<string,array|WP_Error>
     */
    private static $decoded_token_cache = [];

    public static function login(string $identifier, string $password)
    {
        $lib_check = self::ensure_jwt_library();
        if (is_wp_error($lib_check)) {
            return $lib_check;
        }

        $identifier = trim((string) $identifier);
        $user = self::find_user_by_identifier($identifier);

        if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
            return new WP_Error('invalid_credentials', 'Invalid identifier or password', ['status' => 401]);
        }

        $role = self::resolve_primary_role($user);
        $user_id = (int) $user->ID;
        if (self::has_recent_active_login_session($user_id)) {
            return new WP_Error(
                'session_already_active',
                'Akun ini masih aktif di browser lain. Logout dulu dari browser sebelumnya atau minta admin reset login.',
                ['status' => 409]
            );
        }

        $session_key = self::reset_login_session($user_id);
        $token = self::generate_token($user_id, $role, $session_key);
        $user_meta_all = get_user_meta($user_id);
        $kode_kelas = isset($user_meta_all['kode_kelas'][0]) ? (string) $user_meta_all['kode_kelas'][0] : '';
        $kode_ruang = isset($user_meta_all['kode_ruang'][0]) ? (string) $user_meta_all['kode_ruang'][0] : '';
        $agama = isset($user_meta_all['agama'][0]) ? (string) $user_meta_all['agama'][0] : '';
        $foto = isset($user_meta_all['foto'][0]) ? esc_url_raw((string) $user_meta_all['foto'][0]) : '';

        return [
            'token' => $token,
            'user_id' => $user_id,
            'role' => $role,
            'display_name' => (string) $user->display_name,
            'username' => (string) $user->user_login,
            'email' => (string) $user->user_email,
            'kode_kelas' => $kode_kelas,
            'kode_ruang' => $kode_ruang,
            'agama' => $agama,
            'foto' => $foto,
        ];
    }

    public static function generate_token(int $user_id, string $role, string $session_key): string
    {
        $issued_at = time();

        $payload = [
            'iss' => site_url(),
            'iat' => $issued_at,
            'exp' => $issued_at + (12 * HOUR_IN_SECONDS),
            'data' => [
                'user_id' => $user_id,
                'role' => $role,
                'session_key' => $session_key,
            ],
        ];

        return JWT::encode($payload, self::jwt_secret(), 'HS256');
    }

    public static function reset_login_session(int $user_id): string
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return '';
        }

        $session_key = self::generate_login_session_key();
        update_user_meta($user_id, self::USER_META_ACTIVE_LOGIN_SESSION, $session_key);
        update_user_meta($user_id, self::USER_META_ACTIVE_LOGIN_SESSION_TOUCHED_AT, time());
        return $session_key;
    }

    public static function clear_login_session(int $user_id, ?string $session_key = null): bool
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        $active_session_key = self::get_active_login_session($user_id);
        if ($session_key !== null && $session_key !== '' && $active_session_key !== '' && !hash_equals($active_session_key, $session_key)) {
            return false;
        }

        delete_user_meta($user_id, self::USER_META_ACTIVE_LOGIN_SESSION);
        delete_user_meta($user_id, self::USER_META_ACTIVE_LOGIN_SESSION_TOUCHED_AT);
        return true;
    }

    public static function logout_current_session(WP_REST_Request $request): bool
    {
        $decoded = self::verify_request_token($request);
        if (is_wp_error($decoded)) {
            return false;
        }

        $user_id = (int) (self::decoded_token_value($decoded, 'user_id') ?? 0);
        $session_key = (string) (self::decoded_token_value($decoded, 'session_key') ?? '');
        if ($user_id <= 0 || $session_key === '') {
            return false;
        }

        return self::clear_login_session($user_id, $session_key);
    }

    public static function verify_request_token(WP_REST_Request $request)
    {
        $lib_check = self::ensure_jwt_library();
        if (is_wp_error($lib_check)) {
            return $lib_check;
        }

        $cache_key = self::request_cache_key($request);
        if (isset(self::$decoded_token_cache[$cache_key])) {
            return self::$decoded_token_cache[$cache_key];
        }

        $token = self::extract_bearer_token($request);

        if (!$token) {
            $error = new WP_Error('missing_token', 'Authorization token not found', ['status' => 401]);
            self::$decoded_token_cache[$cache_key] = $error;
            return $error;
        }

        try {
            $decoded = JWT::decode($token, new Key(self::jwt_secret(), 'HS256'));
            $payload = (array) $decoded;
            $user_id = (int) (self::decoded_token_value($payload, 'user_id') ?? 0);
            $session_key = (string) (self::decoded_token_value($payload, 'session_key') ?? '');
            $active_session_key = self::get_active_login_session($user_id);

            if ($user_id <= 0 || $session_key === '' || $active_session_key === '' || !hash_equals($active_session_key, $session_key)) {
                CBT_Security_Log::record_latest_student_attempt_event($user_id, 'session_revoked', [
                    'request_route' => method_exists($request, 'get_route') ? (string) $request->get_route() : '',
                    'request_method' => method_exists($request, 'get_method') ? (string) $request->get_method() : '',
                ]);
                $error = new WP_Error(
                    'session_revoked',
                    'Sesi login ini sudah digantikan oleh login lain. Silakan login kembali.',
                    ['status' => 401]
                );
                self::$decoded_token_cache[$cache_key] = $error;
                return $error;
            }

            self::touch_login_session($user_id, $session_key);
            self::$decoded_token_cache[$cache_key] = $payload;
            return $payload;
        } catch (Throwable $exception) {
            $error = new WP_Error('invalid_token', 'Invalid or expired token', ['status' => 401]);
            self::$decoded_token_cache[$cache_key] = $error;
            return $error;
        }
    }

    public static function current_user_id(WP_REST_Request $request): int
    {
        $decoded = self::verify_request_token($request);
        if (is_wp_error($decoded)) {
            return 0;
        }

        $user_id = self::decoded_token_value($decoded, 'user_id');
        return (int) ($user_id ?? 0);
    }

    public static function current_user_role(WP_REST_Request $request): string
    {
        $decoded = self::verify_request_token($request);
        if (is_wp_error($decoded)) {
            return '';
        }

        $role = self::decoded_token_value($decoded, 'role');
        return is_scalar($role) ? (string) $role : '';
    }

    public static function permission_authenticated(WP_REST_Request $request)
    {
        return self::verify_request_token($request);
    }

    public static function permission_teacher_or_student(WP_REST_Request $request)
    {
        $decoded = self::verify_request_token($request);
        if (is_wp_error($decoded)) {
            return $decoded;
        }

        $role = self::decoded_token_value($decoded, 'role');
        $role = is_scalar($role) ? (string) $role : '';
        if (!in_array($role, ['admin', 'administrator', 'guru', 'teacher', 'siswa', 'student'], true)) {
            return new WP_Error('forbidden', 'You do not have permission to access this endpoint', ['status' => 403]);
        }

        return true;
    }

    /**
     * @return array<string,int|string>
     */
    public static function get_global_exam_token(bool $auto_rotate = true): array
    {
        $refresh_minutes = self::normalize_token_refresh_minutes(
            (int) get_option(self::OPTION_GLOBAL_EXAM_TOKEN_REFRESH_MINUTES, self::DEFAULT_TOKEN_REFRESH_MINUTES)
        );
        update_option(self::OPTION_GLOBAL_EXAM_TOKEN_REFRESH_MINUTES, $refresh_minutes);
        $frontend_auto_apply = self::normalize_frontend_auto_apply_setting(
            (int) get_option(self::OPTION_GLOBAL_EXAM_TOKEN_FRONTEND_AUTO_APPLY, 0)
        );
        update_option(self::OPTION_GLOBAL_EXAM_TOKEN_FRONTEND_AUTO_APPLY, $frontend_auto_apply);

        $token = self::normalize_exam_token((string) get_option(self::OPTION_GLOBAL_EXAM_TOKEN, ''));
        $generated_at = (int) get_option(self::OPTION_GLOBAL_EXAM_TOKEN_GENERATED_AT, 0);
        $now = time();

        if ($generated_at <= 0) {
            $generated_at = $now;
        }

        $needs_rotation = ($token === '');
        if ($auto_rotate && !$needs_rotation) {
            $needs_rotation = ($now >= ($generated_at + ($refresh_minutes * MINUTE_IN_SECONDS)));
        }

        if ($needs_rotation) {
            $token = self::generate_exam_token();
            $generated_at = $now;
            update_option(self::OPTION_GLOBAL_EXAM_TOKEN, $token);
            update_option(self::OPTION_GLOBAL_EXAM_TOKEN_GENERATED_AT, $generated_at);
        }

        $next_refresh_at = $generated_at + ($refresh_minutes * MINUTE_IN_SECONDS);

        return [
            'token' => $token,
            'refresh_minutes' => $refresh_minutes,
            'generated_at' => $generated_at,
            'next_refresh_at' => $next_refresh_at,
            'remaining_seconds' => max(0, $next_refresh_at - $now),
            'frontend_auto_apply' => $frontend_auto_apply,
        ];
    }

    /**
     * @return array<string,int|string>
     */
    public static function save_global_exam_token_settings(
        string $token_input,
        int $refresh_minutes,
        bool $regenerate = false,
        ?bool $frontend_auto_apply = null
    ): array
    {
        $refresh_minutes = self::normalize_token_refresh_minutes($refresh_minutes);
        $token = self::normalize_exam_token($token_input);
        if ($frontend_auto_apply === null) {
            $frontend_auto_apply = ((int) get_option(self::OPTION_GLOBAL_EXAM_TOKEN_FRONTEND_AUTO_APPLY, 0) === 1);
        }
        $frontend_auto_apply_value = self::normalize_frontend_auto_apply_setting($frontend_auto_apply ? 1 : 0);

        if ($token === '' && !$regenerate) {
            $token = self::normalize_exam_token((string) get_option(self::OPTION_GLOBAL_EXAM_TOKEN, ''));
        }

        if ($token === '' || $regenerate) {
            $token = self::generate_exam_token();
        }

        $generated_at = time();
        update_option(self::OPTION_GLOBAL_EXAM_TOKEN_REFRESH_MINUTES, $refresh_minutes);
        update_option(self::OPTION_GLOBAL_EXAM_TOKEN, $token);
        update_option(self::OPTION_GLOBAL_EXAM_TOKEN_GENERATED_AT, $generated_at);
        update_option(self::OPTION_GLOBAL_EXAM_TOKEN_FRONTEND_AUTO_APPLY, $frontend_auto_apply_value);

        return self::get_global_exam_token(false);
    }

    public static function is_frontend_auto_exam_token_enabled(): bool
    {
        return self::normalize_frontend_auto_apply_setting(
            (int) get_option(self::OPTION_GLOBAL_EXAM_TOKEN_FRONTEND_AUTO_APPLY, 0)
        ) === 1;
    }

    public static function normalize_exam_token_input(string $token): string
    {
        return self::normalize_exam_token($token);
    }

    private static function extract_bearer_token(WP_REST_Request $request): ?string
    {
        $header = $request->get_header('authorization');
        if (!$header) {
            return null;
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private static function jwt_secret(): string
    {
        if (defined('CBT_JWT_SECRET') && CBT_JWT_SECRET) {
            return (string) CBT_JWT_SECRET;
        }

        return wp_salt('auth');
    }

    private static function ensure_jwt_library()
    {
        if (!class_exists('Firebase\\JWT\\JWT') || !class_exists('Firebase\\JWT\\Key')) {
            return new WP_Error(
                'jwt_library_missing',
                'JWT library is missing. Run composer install in plugin directory.',
                ['status' => 500]
            );
        }

        return true;
    }

    private static function resolve_primary_role(WP_User $user): string
    {
        if (in_array('administrator', $user->roles, true) || in_array('admin_cbt', $user->roles, true)) {
            return 'admin';
        }

        if (
            in_array('guru_cbt', $user->roles, true) ||
            in_array('teacher', $user->roles, true) ||
            in_array('editor', $user->roles, true)
        ) {
            return 'guru';
        }

        if (
            in_array('siswa_cbt', $user->roles, true) ||
            in_array('student', $user->roles, true) ||
            in_array('subscriber', $user->roles, true)
        ) {
            return 'siswa';
        }

        return $user->roles[0] ?? 'siswa';
    }

    private static function normalize_token_refresh_minutes(int $minutes): int
    {
        if ($minutes < 5 || $minutes > 60 || ($minutes % 5) !== 0) {
            return self::DEFAULT_TOKEN_REFRESH_MINUTES;
        }

        return $minutes;
    }

    private static function normalize_frontend_auto_apply_setting(int $value): int
    {
        return ($value === 1) ? 1 : 0;
    }

    private static function normalize_exam_token(string $token): string
    {
        $token = strtoupper(trim(sanitize_text_field($token)));
        if ($token === '') {
            return '';
        }

        $token = preg_replace('/[^A-HJKMNPQRSTUVWXYZ1-9]/', '', $token);
        if (!is_string($token) || $token === '') {
            return '';
        }

        $token = substr($token, 0, self::EXAM_TOKEN_LENGTH);
        if (strlen($token) < self::EXAM_TOKEN_LENGTH) {
            return '';
        }

        return $token;
    }

    private static function generate_exam_token(int $length = self::EXAM_TOKEN_LENGTH): string
    {
        $length = self::EXAM_TOKEN_LENGTH;
        $alphabet = self::EXAM_TOKEN_ALPHABET;
        $max_index = strlen($alphabet) - 1;
        $token = '';

        for ($i = 0; $i < $length; $i++) {
            try {
                $index = random_int(0, $max_index);
            } catch (Throwable $e) {
                $index = wp_rand(0, $max_index);
            }
            $token .= $alphabet[$index];
        }

        return $token;
    }

    private static function find_user_by_identifier(string $identifier): ?WP_User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        if (is_email($identifier)) {
            $by_email = get_user_by('email', sanitize_email($identifier));
            if ($by_email instanceof WP_User) {
                return $by_email;
            }
        }

        $by_login = get_user_by('login', sanitize_user($identifier, true));
        if ($by_login instanceof WP_User) {
            return $by_login;
        }

        $by_nisn_ids = get_users([
            'number' => 1,
            'count_total' => false,
            'fields' => 'ids',
            'meta_key' => 'nisn',
            'meta_value' => sanitize_text_field($identifier),
            'meta_compare' => '=',
        ]);
        if (!empty($by_nisn_ids)) {
            $nisn_user_id = (int) $by_nisn_ids[0];
            if ($nisn_user_id > 0) {
                $by_nisn = get_user_by('id', $nisn_user_id);
                if ($by_nisn instanceof WP_User) {
                    return $by_nisn;
                }
            }
        }

        if (strpos($identifier, '@') === false) {
            $fallback_email = sanitize_email($identifier . '@student.sch.id');
            if ($fallback_email !== '') {
                $fallback_user = get_user_by('email', $fallback_email);
                if ($fallback_user instanceof WP_User) {
                    return $fallback_user;
                }
            }
        }

        return null;
    }

    private static function request_cache_key(WP_REST_Request $request): string
    {
        if (function_exists('spl_object_id')) {
            return (string) spl_object_id($request);
        }

        return spl_object_hash($request);
    }

    /**
     * @param array<string,mixed> $decoded
     * @return mixed|null
     */
    private static function decoded_token_value(array $decoded, string $key)
    {
        $data = $decoded['data'] ?? null;

        if (is_object($data) && isset($data->{$key})) {
            return $data->{$key};
        }

        if (is_array($data) && array_key_exists($key, $data)) {
            return $data[$key];
        }

        return null;
    }

    private static function get_active_login_session(int $user_id): string
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return '';
        }

        return trim((string) get_user_meta($user_id, self::USER_META_ACTIVE_LOGIN_SESSION, true));
    }

    private static function get_active_login_session_touched_at(int $user_id): int
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        return max(0, (int) get_user_meta($user_id, self::USER_META_ACTIVE_LOGIN_SESSION_TOUCHED_AT, true));
    }

    private static function has_recent_active_login_session(int $user_id): bool
    {
        $active_session_key = self::get_active_login_session($user_id);
        if ($active_session_key === '') {
            return false;
        }

        $touched_at = self::get_active_login_session_touched_at($user_id);
        if ($touched_at <= 0) {
            return false;
        }

        return ($touched_at + self::ACTIVE_LOGIN_TTL_SECONDS) >= time();
    }

    private static function touch_login_session(int $user_id, string $session_key): void
    {
        $user_id = absint($user_id);
        $session_key = trim((string) $session_key);
        if ($user_id <= 0 || $session_key === '') {
            return;
        }

        $active_session_key = self::get_active_login_session($user_id);
        if ($active_session_key === '' || !hash_equals($active_session_key, $session_key)) {
            return;
        }

        $now = time();
        $last_touched_at = self::get_active_login_session_touched_at($user_id);
        if ($last_touched_at > 0 && ($last_touched_at + self::ACTIVE_LOGIN_TOUCH_DEBOUNCE_SECONDS) > $now) {
            return;
        }

        update_user_meta($user_id, self::USER_META_ACTIVE_LOGIN_SESSION_TOUCHED_AT, $now);
    }

    private static function generate_login_session_key(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $throwable) {
            return md5(wp_generate_password(64, true, true) . '|' . microtime(true) . '|' . wp_rand());
        }
    }
}
