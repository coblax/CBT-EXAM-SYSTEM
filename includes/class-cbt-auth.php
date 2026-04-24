<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Student_Profile_Cache')) {
    require_once __DIR__ . '/class-cbt-student-profile-cache.php';
}

if (!class_exists('CBT_Student_Cohort_Index_Service')) {
    require_once __DIR__ . '/class-cbt-student-cohort-index-service.php';
}

if (!class_exists('CBT_Login_Auth_Snapshot_Cache')) {
    require_once __DIR__ . '/class-cbt-login-auth-snapshot-cache.php';
}

if (!class_exists('CBT_Login_Snapshot_Metrics_Service')) {
    require_once __DIR__ . '/class-cbt-login-snapshot-metrics-service.php';
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
    private const LOGIN_TOKEN_TTL_SECONDS = 43200;
    private const AUTH_REDIS_TTL_SECONDS = 44100;
    private const AUTH_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const AUTH_REDIS_DEFAULT_PORT = 6379;
    private const AUTH_REDIS_DEFAULT_DATABASE = 2;
    private const AUTH_REDIS_PREFIX = 'cbt_auth:';
    private const AUTH_REDIS_TIMEOUT = 1.5;
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
    /** @var Redis|false|null */
    private static $auth_redis = null;
    /** @var bool */
    private static $auth_redis_connection_attempted = false;
    /** @var string */
    private static $auth_redis_last_connection_error = '';
    /**
     * Cache identifier lookup selama satu request login supaya canonical fallback tidak
     * mengulang rangkaian query email/login/NISN/fallback email.
     *
     * @var array<string,array{user:WP_User|null,matched_by:string,user_id:int}>
     */
    private static $identifier_lookup_cache = [];

    public static function login(string $identifier, string $password)
    {
        self::$identifier_lookup_cache = [];

        $lib_check = self::ensure_jwt_library();
        if (is_wp_error($lib_check)) {
            return $lib_check;
        }

        $identifier = trim((string) $identifier);
        $snapshot_attempt = self::attempt_snapshot_login($identifier, $password);
        if (($snapshot_attempt['status'] ?? '') === 'success') {
            self::record_login_metrics('snapshot_success', $snapshot_attempt);
            return $snapshot_attempt['result'];
        }

        if (($snapshot_attempt['status'] ?? '') === 'session_already_active') {
            self::record_login_metrics('session_already_active', $snapshot_attempt);
            return $snapshot_attempt['result'];
        }

        $canonical_attempt = self::attempt_canonical_login($identifier, $password, true, $snapshot_attempt);
        self::record_login_metrics((string) ($canonical_attempt['status'] ?? ''), $snapshot_attempt, $canonical_attempt);

        return $canonical_attempt['result'];
    }

    public static function generate_token(int $user_id, string $role, string $session_key): string
    {
        $issued_at = time();

        $payload = [
            'iss' => site_url(),
            'iat' => $issued_at,
            'exp' => $issued_at + self::LOGIN_TOKEN_TTL_SECONDS,
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
        $issued_at = time();
        $payload = [
            'session_key' => $session_key,
            'touched_at' => $issued_at,
            'issued_at' => $issued_at,
        ];
        self::write_active_login_state($user_id, $payload);
        return $session_key;
    }

    public static function clear_login_session(int $user_id, ?string $session_key = null): bool
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        $active_state = self::read_active_login_state($user_id);
        $active_session_key = (string) ($active_state['session_key'] ?? '');
        if ($session_key !== null && $session_key !== '' && $active_session_key !== '' && !hash_equals($active_session_key, $session_key)) {
            return false;
        }

        self::delete_active_login_state($user_id);
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
            $active_state = self::read_active_login_state($user_id);
            $active_session_key = (string) ($active_state['session_key'] ?? '');

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

    public static function permission_supervisor_dashboard(WP_REST_Request $request)
    {
        $decoded = self::verify_request_token($request);
        if (is_wp_error($decoded)) {
            return $decoded;
        }

        $role = self::decoded_token_value($decoded, 'role');
        $role = is_scalar($role) ? (string) $role : '';
        if (!self::is_supervisor_role($role)) {
            return new WP_Error('forbidden', 'You do not have permission to access this endpoint', ['status' => 403]);
        }

        return true;
    }

    public static function is_supervisor_role(string $role): bool
    {
        return in_array(sanitize_key($role), ['admin', 'administrator', 'guru', 'teacher'], true);
    }

    public static function is_admin_role(string $role): bool
    {
        return in_array(sanitize_key($role), ['admin', 'administrator'], true);
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

    /**
     * @return array<string,mixed>|WP_Error|null
     */
    private static function attempt_snapshot_login(string $identifier, string $password): array
    {
        if (!class_exists('CBT_Login_Auth_Snapshot_Cache')
            || !method_exists('CBT_Login_Auth_Snapshot_Cache', 'get_snapshot_lookup_result')) {
            return [
                'status' => 'miss',
                'result' => null,
                'lookup' => self::empty_snapshot_lookup_meta(),
            ];
        }

        $lookup = CBT_Login_Auth_Snapshot_Cache::get_snapshot_lookup_result($identifier, false);
        $snapshot = is_array($lookup['snapshot'] ?? null) ? $lookup['snapshot'] : null;
        if (!is_array($snapshot)) {
            return [
                'status' => sanitize_key((string) ($lookup['lookup_status'] ?? 'miss')),
                'result' => null,
                'lookup' => self::normalize_snapshot_lookup_meta($lookup),
            ];
        }

        $user_id = (int) ($snapshot['user_id'] ?? 0);
        $password_hash = (string) ($snapshot['password_hash'] ?? '');
        $role = sanitize_key((string) ($snapshot['role'] ?? ''));
        if ($user_id <= 0 || $password_hash === '' || $role === '') {
            $lookup['lookup_status'] = 'invalid';
            $lookup['snapshot_miss_reason'] = 'invalid_snapshot';
            $lookup['snapshot_miss_reason_label'] = CBT_Login_Auth_Snapshot_Cache::get_snapshot_miss_reason_label('invalid_snapshot');
            $lookup['source_path'] = 'canonical';
            return [
                'status' => 'invalid',
                'result' => null,
                'lookup' => self::normalize_snapshot_lookup_meta($lookup),
            ];
        }

        if (!wp_check_password($password, $password_hash, $user_id)) {
            $lookup['lookup_status'] = 'password_mismatch';
            $lookup['snapshot_miss_reason'] = 'password_mismatch';
            $lookup['snapshot_miss_reason_label'] = CBT_Login_Auth_Snapshot_Cache::get_snapshot_miss_reason_label('password_mismatch');
            $lookup['source_path'] = 'canonical';
            return [
                'status' => 'password_mismatch',
                'result' => null,
                'lookup' => self::normalize_snapshot_lookup_meta($lookup),
            ];
        }

        $result = self::complete_login(
            $user_id,
            $role,
            [
                'display_name' => (string) ($snapshot['display_name'] ?? ''),
                'username' => (string) ($snapshot['user_login'] ?? ''),
                'email' => (string) ($snapshot['user_email'] ?? ''),
            ],
            [
                'kode_kelas' => (string) ($snapshot['kode_kelas'] ?? ''),
                'kode_ruang' => (string) ($snapshot['kode_ruang'] ?? ''),
                'agama' => (string) ($snapshot['agama'] ?? ''),
                'foto' => (string) ($snapshot['foto'] ?? ''),
            ]
        );

        return [
            'status' => is_wp_error($result) ? $result->get_error_code() : 'success',
            'result' => $result,
            'lookup' => self::normalize_snapshot_lookup_meta($lookup),
        ];
    }

    /**
     * @return array{status:string,result:array<string,mixed>|WP_Error,lookup:array<string,mixed>}
     */
    private static function attempt_canonical_login(
        string $identifier,
        string $password,
        bool $rewrite_snapshot_on_success = false,
        array $snapshot_attempt = []
    ): array
    {
        $lookup = self::normalize_snapshot_lookup_meta($snapshot_attempt['lookup'] ?? []);
        $user = self::find_user_by_identifier($identifier);
        if ($user instanceof WP_User) {
            $lookup = self::enrich_snapshot_lookup_after_canonical_resolution($lookup, (int) $user->ID);
        }

        if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
            return [
                'status' => 'invalid_credentials',
                'result' => new WP_Error('invalid_credentials', 'Invalid identifier or password', ['status' => 401]),
                'lookup' => $lookup,
            ];
        }

        $role = self::resolve_primary_role($user);
        $user_id = (int) $user->ID;
        $profile_snapshot = CBT_Student_Profile_Cache::get_snapshot($user_id);
        $result = self::complete_login(
            $user_id,
            $role,
            [
                'display_name' => (string) $user->display_name,
                'username' => (string) $user->user_login,
                'email' => (string) $user->user_email,
            ],
            $profile_snapshot
        );

        if (!is_wp_error($result) && $rewrite_snapshot_on_success && $role === 'siswa') {
            try {
                CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot($user_id, 'canonical_login');
            } catch (Throwable $throwable) {
                // Snapshot login bersifat akselerator, jadi kegagalannya tidak boleh memblokir auth utama.
            }
        }

        return [
            'status' => is_wp_error($result) ? $result->get_error_code() : 'success',
            'result' => $result,
            'lookup' => $lookup,
        ];
    }

    /**
     * @param array<string,mixed> $lookup
     * @return array<string,mixed>
     */
    private static function enrich_snapshot_lookup_after_canonical_resolution(array $lookup, int $user_id): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0 || !class_exists('CBT_Login_Auth_Snapshot_Cache')) {
            return $lookup;
        }

        $reason = sanitize_key((string) ($lookup['snapshot_miss_reason'] ?? ''));
        $status = sanitize_key((string) ($lookup['lookup_status'] ?? ''));
        if (!in_array($status, ['miss', 'invalid', 'unavailable'], true) || ($reason !== '' && $reason !== 'not_prepared')) {
            $lookup['resolved_user_id'] = $user_id;
            return $lookup;
        }

        try {
            $diagnostics = CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics($user_id);
        } catch (Throwable $throwable) {
            $lookup['resolved_user_id'] = $user_id;
            return $lookup;
        }

        $diagnostic_status = sanitize_key((string) ($diagnostics['snapshot_status'] ?? ''));
        $diagnostic_reason = sanitize_key((string) ($diagnostics['snapshot_miss_reason'] ?? ''));
        if (in_array($diagnostic_status, ['miss', 'invalid', 'unavailable'], true) && $diagnostic_reason !== '' && $diagnostic_reason !== 'idle') {
            $lookup['lookup_status'] = $diagnostic_status === 'invalid'
                ? 'invalid'
                : ($diagnostic_status === 'unavailable' ? 'unavailable' : 'miss');
            $lookup['snapshot_miss_reason'] = $diagnostic_reason;
            $lookup['snapshot_miss_reason_label'] = (string) ($diagnostics['snapshot_miss_reason_label'] ?? '');
        }

        $lookup['source_path'] = 'canonical';
        $lookup['resolved_user_id'] = $user_id;
        return self::normalize_snapshot_lookup_meta($lookup);
    }

    /**
     * @param array<string,mixed> $snapshot_attempt
     * @param array<string,mixed> $canonical_attempt
     */
    private static function record_login_metrics(string $outcome, array $snapshot_attempt = [], array $canonical_attempt = []): void
    {
        if (!class_exists('CBT_Login_Snapshot_Metrics_Service')) {
            return;
        }

        $lookup = self::normalize_snapshot_lookup_meta(
            !empty($canonical_attempt['lookup']) ? (array) $canonical_attempt['lookup'] : (array) ($snapshot_attempt['lookup'] ?? [])
        );
        $miss_reason = sanitize_key((string) ($lookup['snapshot_miss_reason'] ?? ''));
        $source_path = sanitize_key((string) ($lookup['source_path'] ?? ''));
        $lookup_status = sanitize_key((string) ($lookup['lookup_status'] ?? ''));
        $meta = [
            'lookup_status' => $lookup_status,
            'source_path' => $source_path,
            'snapshot_miss_reason' => $miss_reason,
        ];

        switch (sanitize_key($outcome)) {
            case 'snapshot_success':
                CBT_Login_Snapshot_Metrics_Service::record_snapshot_success($meta);
                return;
            case 'success':
                CBT_Login_Snapshot_Metrics_Service::record_canonical_success($miss_reason, $meta);
                return;
            case 'invalid_credentials':
                CBT_Login_Snapshot_Metrics_Service::record_invalid_credentials($source_path !== '' ? $source_path : 'canonical', $miss_reason, $meta);
                return;
            case 'session_already_active':
                CBT_Login_Snapshot_Metrics_Service::record_session_already_active($source_path !== '' ? $source_path : 'canonical', $miss_reason, $meta);
                return;
        }
    }

    /**
     * @param array<string,mixed> $lookup
     * @return array<string,mixed>
     */
    private static function normalize_snapshot_lookup_meta(array $lookup): array
    {
        return [
            'lookup_status' => sanitize_key((string) ($lookup['lookup_status'] ?? 'miss')),
            'snapshot_miss_reason' => sanitize_key((string) ($lookup['snapshot_miss_reason'] ?? '')),
            'snapshot_miss_reason_label' => sanitize_text_field((string) ($lookup['snapshot_miss_reason_label'] ?? '')),
            'source_path' => sanitize_key((string) ($lookup['source_path'] ?? 'canonical')),
            'resolved_user_id' => absint($lookup['resolved_user_id'] ?? 0),
            'lookup_identifier' => sanitize_text_field((string) ($lookup['lookup_identifier'] ?? '')),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_snapshot_lookup_meta(): array
    {
        return [
            'lookup_status' => 'miss',
            'snapshot_miss_reason' => '',
            'snapshot_miss_reason_label' => '',
            'source_path' => 'canonical',
            'resolved_user_id' => 0,
            'lookup_identifier' => '',
        ];
    }

    /**
     * @param array{display_name:string,username:string,email:string} $identity
     * @param array<string,mixed> $profile_snapshot
     * @return array<string,mixed>|WP_Error
     */
    private static function complete_login(int $user_id, string $role, array $identity, array $profile_snapshot)
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return new WP_Error('invalid_credentials', 'Invalid identifier or password', ['status' => 401]);
        }

        if (self::has_recent_active_login_session($user_id)) {
            return new WP_Error(
                'session_already_active',
                'Akun ini masih aktif di browser lain. Logout dulu dari browser sebelumnya atau minta admin reset login.',
                ['status' => 409]
            );
        }

        $session_key = self::reset_login_session($user_id);
        $token = self::generate_token($user_id, $role, $session_key);

        $payload = [
            'token' => $token,
            'user_id' => $user_id,
            'role' => $role,
            'display_name' => (string) ($identity['display_name'] ?? ''),
            'username' => (string) ($identity['username'] ?? ''),
            'email' => (string) ($identity['email'] ?? ''),
            'kode_kelas' => (string) ($profile_snapshot['kode_kelas'] ?? ''),
            'kode_ruang' => (string) ($profile_snapshot['kode_ruang'] ?? ''),
            'agama' => (string) ($profile_snapshot['agama'] ?? ''),
            'foto' => (string) ($profile_snapshot['foto'] ?? ''),
        ];

        if (class_exists('CBT_Adaptive_Load_Service')) {
            $payload['adaptive_load'] = CBT_Adaptive_Load_Service::get_frontend_payload();
        }

        return $payload;
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
        $lookup = self::resolve_login_user_by_identifier($identifier);
        return ($lookup['user'] ?? null) instanceof WP_User ? $lookup['user'] : null;
    }

    /**
     * @return array{user:WP_User|null,matched_by:string,user_id:int}
     */
    private static function resolve_login_user_by_identifier(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return [
                'user' => null,
                'matched_by' => '',
                'user_id' => 0,
            ];
        }

        $cache_key = self::login_identifier_cache_key($identifier);
        if (array_key_exists($cache_key, self::$identifier_lookup_cache)) {
            return self::$identifier_lookup_cache[$cache_key];
        }

        if (is_email($identifier)) {
            $by_email = get_user_by('email', sanitize_email($identifier));
            if ($by_email instanceof WP_User) {
                return self::store_login_identifier_lookup($cache_key, $by_email, 'email');
            }
        }

        $by_login = get_user_by('login', sanitize_user($identifier, true));
        if ($by_login instanceof WP_User) {
            return self::store_login_identifier_lookup($cache_key, $by_login, 'login');
        }

        $nisn_lookup = self::resolve_user_id_by_nisn($identifier);
        $nisn_user_id = (int) ($nisn_lookup['user_id'] ?? 0);
        if ($nisn_user_id > 0) {
            $by_nisn = get_user_by('id', $nisn_user_id);
            if ($by_nisn instanceof WP_User) {
                return self::store_login_identifier_lookup($cache_key, $by_nisn, (string) ($nisn_lookup['matched_by'] ?? 'nisn'));
            }
        }

        if (strpos($identifier, '@') === false) {
            $fallback_email = sanitize_email($identifier . '@student.sch.id');
            if ($fallback_email !== '') {
                $fallback_user = get_user_by('email', $fallback_email);
                if ($fallback_user instanceof WP_User) {
                    return self::store_login_identifier_lookup($cache_key, $fallback_user, 'fallback_email');
                }
            }
        }

        return self::store_login_identifier_lookup($cache_key, null, '');
    }

    private static function login_identifier_cache_key(string $identifier): string
    {
        return strtolower(trim($identifier));
    }

    /**
     * @return array{user:WP_User|null,matched_by:string,user_id:int}
     */
    private static function store_login_identifier_lookup(string $cache_key, ?WP_User $user, string $matched_by): array
    {
        $row = [
            'user' => $user,
            'matched_by' => sanitize_key($matched_by),
            'user_id' => $user instanceof WP_User ? (int) $user->ID : 0,
        ];
        self::$identifier_lookup_cache[$cache_key] = $row;
        return $row;
    }

    /**
     * @return array{user_id:int,matched_by:string}
     */
    private static function resolve_user_id_by_nisn(string $identifier): array
    {
        $nisn = sanitize_text_field($identifier);
        if ($nisn === '') {
            return [
                'user_id' => 0,
                'matched_by' => '',
            ];
        }

        if (class_exists('CBT_Student_Cohort_Index_Service') && method_exists('CBT_Student_Cohort_Index_Service', 'find_user_id_by_nisn')) {
            try {
                $cohort_user_id = CBT_Student_Cohort_Index_Service::find_user_id_by_nisn($nisn);
                if ($cohort_user_id > 0) {
                    return [
                        'user_id' => $cohort_user_id,
                        'matched_by' => 'nisn_cohort',
                    ];
                }
            } catch (Throwable $throwable) {
                // Cohort index adalah akselerator; canonical usermeta tetap fallback aman.
            }
        }

        $by_nisn_ids = get_users([
            'number' => 1,
            'count_total' => false,
            'fields' => 'ids',
            'meta_key' => 'nisn',
            'meta_value' => $nisn,
            'meta_compare' => '=',
        ]);
        if (!empty($by_nisn_ids)) {
            return [
                'user_id' => absint($by_nisn_ids[0]),
                'matched_by' => 'nisn_usermeta',
            ];
        }

        return [
            'user_id' => 0,
            'matched_by' => '',
        ];
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
        $state = self::read_active_login_state($user_id);
        return trim((string) ($state['session_key'] ?? ''));
    }

    private static function get_active_login_session_touched_at(int $user_id): int
    {
        $state = self::read_active_login_state($user_id);
        return max(0, (int) ($state['touched_at'] ?? 0));
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

        $redis = self::auth_redis();
        if ($redis instanceof Redis) {
            $payload = self::read_auth_redis_session($user_id, $redis_available);
            if (!$redis_available || !is_array($payload)) {
                return;
            }

            $active_session_key = trim((string) ($payload['session_key'] ?? ''));
            if ($active_session_key === '' || !hash_equals($active_session_key, $session_key)) {
                return;
            }

            $now = time();
            $last_touched_at = max(0, (int) ($payload['touched_at'] ?? 0));
            if ($last_touched_at > 0 && ($last_touched_at + self::ACTIVE_LOGIN_TOUCH_DEBOUNCE_SECONDS) > $now) {
                return;
            }

            $payload['touched_at'] = $now;
            self::write_auth_redis_session($user_id, $payload);
            return;
        }

        $legacy_state = self::read_legacy_active_login_state($user_id);
        $active_session_key = trim((string) ($legacy_state['session_key'] ?? ''));
        if ($active_session_key === '' || !hash_equals($active_session_key, $session_key)) {
            return;
        }

        $now = time();
        $last_touched_at = max(0, (int) ($legacy_state['touched_at'] ?? 0));
        if ($last_touched_at > 0 && ($last_touched_at + self::ACTIVE_LOGIN_TOUCH_DEBOUNCE_SECONDS) > $now) {
            return;
        }

        self::write_legacy_active_login_state($user_id, [
            'session_key' => $active_session_key,
            'touched_at' => $now,
            'issued_at' => max(0, (int) ($legacy_state['issued_at'] ?? 0)),
        ]);
    }

    /**
     * @return array{session_key:string,touched_at:int,issued_at:int,source:string,redis_available:int}
     */
    private static function read_active_login_state(int $user_id): array
    {
        $default_state = [
            'session_key' => '',
            'touched_at' => 0,
            'issued_at' => 0,
            'source' => 'none',
            'redis_available' => 0,
        ];

        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return $default_state;
        }

        $redis_state = self::read_auth_redis_session($user_id, $redis_available);
        if ($redis_available) {
            if (is_array($redis_state)) {
                $redis_state['source'] = 'redis';
                $redis_state['redis_available'] = 1;
                return array_merge($default_state, $redis_state);
            }

            $legacy_state = self::read_legacy_active_login_state($user_id);
            if (is_array($legacy_state)) {
                self::hydrate_auth_redis_from_legacy($user_id, $legacy_state);
                $legacy_state['source'] = 'legacy';
                $legacy_state['redis_available'] = 1;
                return array_merge($default_state, $legacy_state);
            }

            $default_state['redis_available'] = 1;
            return $default_state;
        }

        $legacy_state = self::read_legacy_active_login_state($user_id);
        if (is_array($legacy_state)) {
            $legacy_state['source'] = 'legacy';
            return array_merge($default_state, $legacy_state);
        }

        return $default_state;
    }

    /**
     * @param array{session_key:string,touched_at:int,issued_at:int} $payload
     */
    private static function write_active_login_state(int $user_id, array $payload): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        self::write_auth_redis_session($user_id, $payload);
        self::write_legacy_active_login_state($user_id, $payload);
    }

    private static function delete_active_login_state(int $user_id): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        self::delete_auth_redis_session($user_id);
        self::delete_legacy_active_login_state($user_id);
    }

    /**
     * @return array{session_key:string,touched_at:int,issued_at:int}|null
     */
    private static function read_legacy_active_login_state(int $user_id): ?array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return null;
        }

        $meta = get_user_meta($user_id);
        $session_key = isset($meta[self::USER_META_ACTIVE_LOGIN_SESSION][0])
            ? trim((string) $meta[self::USER_META_ACTIVE_LOGIN_SESSION][0])
            : '';
        if ($session_key === '') {
            return null;
        }

        $touched_at = isset($meta[self::USER_META_ACTIVE_LOGIN_SESSION_TOUCHED_AT][0])
            ? max(0, (int) $meta[self::USER_META_ACTIVE_LOGIN_SESSION_TOUCHED_AT][0])
            : 0;

        return [
            'session_key' => $session_key,
            'touched_at' => $touched_at,
            'issued_at' => $touched_at,
        ];
    }

    /**
     * @param array{session_key:string,touched_at:int,issued_at:int} $payload
     */
    private static function write_legacy_active_login_state(int $user_id, array $payload): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        update_user_meta($user_id, self::USER_META_ACTIVE_LOGIN_SESSION, (string) ($payload['session_key'] ?? ''));
        update_user_meta($user_id, self::USER_META_ACTIVE_LOGIN_SESSION_TOUCHED_AT, max(0, (int) ($payload['touched_at'] ?? 0)));
    }

    private static function delete_legacy_active_login_state(int $user_id): void
    {
        delete_user_meta($user_id, self::USER_META_ACTIVE_LOGIN_SESSION);
        delete_user_meta($user_id, self::USER_META_ACTIVE_LOGIN_SESSION_TOUCHED_AT);
    }

    /**
     * @return array{session_key:string,touched_at:int,issued_at:int}|null
     */
    private static function read_auth_redis_session(int $user_id, ?bool &$redis_available = null): ?array
    {
        $redis_available = false;
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return null;
        }

        $redis = self::auth_redis();
        if (!$redis instanceof Redis) {
            return null;
        }

        $redis_available = true;
        $raw_payload = $redis->get(self::auth_session_storage_key($user_id));
        if (!is_string($raw_payload) || trim($raw_payload) === '') {
            return null;
        }

        $decoded = json_decode($raw_payload, true);
        if (!is_array($decoded)) {
            self::delete_auth_redis_session($user_id);
            return null;
        }

        $session_key = trim((string) ($decoded['session_key'] ?? ''));
        if ($session_key === '') {
            self::delete_auth_redis_session($user_id);
            return null;
        }

        return [
            'session_key' => $session_key,
            'touched_at' => max(0, (int) ($decoded['touched_at'] ?? 0)),
            'issued_at' => max(0, (int) ($decoded['issued_at'] ?? 0)),
        ];
    }

    /**
     * @param array{session_key:string,touched_at:int,issued_at:int} $payload
     */
    private static function write_auth_redis_session(int $user_id, array $payload): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $redis = self::auth_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $session_key = trim((string) ($payload['session_key'] ?? ''));
        if ($session_key === '') {
            return;
        }

        $stored_payload = [
            'session_key' => $session_key,
            'touched_at' => max(0, (int) ($payload['touched_at'] ?? 0)),
            'issued_at' => max(0, (int) ($payload['issued_at'] ?? 0)),
        ];
        $encoded = wp_json_encode($stored_payload);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $redis->setEx(self::auth_session_storage_key($user_id), self::AUTH_REDIS_TTL_SECONDS, $encoded);
    }

    private static function delete_auth_redis_session(int $user_id): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $redis = self::auth_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $redis->del(self::auth_session_storage_key($user_id));
    }

    /**
     * @param array{session_key:string,touched_at:int,issued_at:int} $legacy_state
     */
    private static function hydrate_auth_redis_from_legacy(int $user_id, array $legacy_state): void
    {
        $session_key = trim((string) ($legacy_state['session_key'] ?? ''));
        if ($session_key === '') {
            return;
        }

        $payload = [
            'session_key' => $session_key,
            'touched_at' => max(0, (int) ($legacy_state['touched_at'] ?? 0)),
            'issued_at' => max(0, (int) ($legacy_state['issued_at'] ?? 0)),
        ];
        if ((int) $payload['issued_at'] <= 0) {
            $payload['issued_at'] = (int) ($payload['touched_at'] > 0 ? $payload['touched_at'] : time());
        }

        self::write_auth_redis_session($user_id, $payload);
    }

    /**
     * @return Redis|null
     */
    private static function auth_redis(): ?Redis
    {
        if (self::$auth_redis_connection_attempted) {
            return (self::$auth_redis instanceof Redis) ? self::$auth_redis : null;
        }

        self::$auth_redis_connection_attempted = true;
        self::$auth_redis = false;
        self::$auth_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$auth_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::auth_redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::AUTH_REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::AUTH_REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::AUTH_REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::AUTH_REDIS_TIMEOUT)
                );
            }

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::AUTH_REDIS_DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis auth gagal.');
            }

            self::$auth_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$auth_redis_last_connection_error = 'Koneksi auth Redis gagal: ' . $throwable->getMessage();
            self::$auth_redis = false;
            return null;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float,scheme:string}
     */
    private static function auth_redis_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::AUTH_REDIS_DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::AUTH_REDIS_DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::AUTH_REDIS_DEFAULT_PORT;
        }

        $database = self::constant_scalar('CBT_RUNTIME_REDIS_DATABASE', null);
        if ($database === null || $database === '') {
            $wp_database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::AUTH_REDIS_DEFAULT_DATABASE - 1);
            $database = max(0, $wp_database + 1);
        }

        $password = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_PASSWORD', ''));
        if ($password === '') {
            $password = trim((string) self::constant_scalar('WP_REDIS_PASSWORD', ''));
        }

        $scheme = 'tcp';
        if ($host !== '' && strpos($host, '/') === 0) {
            $scheme = 'unix';
        }

        return [
            'host' => $host !== '' ? $host : self::AUTH_REDIS_DEFAULT_HOST,
            'port' => $port,
            'database' => (int) $database,
            'password' => $password,
            'timeout' => self::AUTH_REDIS_TIMEOUT,
            'scheme' => $scheme,
        ];
    }

    private static function auth_session_storage_key(int $user_id): string
    {
        return self::AUTH_REDIS_PREFIX . 'user:' . max(0, $user_id) . ':session';
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private static function constant_scalar(string $constant_name, $default)
    {
        return defined($constant_name) ? constant($constant_name) : $default;
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
