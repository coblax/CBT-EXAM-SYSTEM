<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Exam_Availability_Auto_Warm_Service')) {
    require_once __DIR__ . '/class-cbt-exam-availability-auto-warm-service.php';
}

if (!class_exists('CBT_Student_Profile_Cache')) {
    require_once __DIR__ . '/class-cbt-student-profile-cache.php';
}

class CBT_Login_Auth_Snapshot_Cache
{
    private const SNAPSHOT_REDIS_TTL_SECONDS = 14400;
    private const SNAPSHOT_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const SNAPSHOT_REDIS_DEFAULT_PORT = 6379;
    private const SNAPSHOT_REDIS_DEFAULT_DATABASE = 2;
    private const SNAPSHOT_REDIS_PREFIX = 'cbt_login_auth:';
    private const SNAPSHOT_REDIS_TIMEOUT = 1.5;
    private const FALLBACK_EMAIL_DOMAIN = 'student.sch.id';

    /** @var Redis|false|null */
    private static $snapshot_redis = null;
    /** @var bool */
    private static $snapshot_redis_connection_attempted = false;
    /** @var string */
    private static $snapshot_redis_last_connection_error = '';

    public static function init(): void
    {
        if (function_exists('add_action')) {
            add_action('added_user_meta', [self::class, 'handle_user_meta_change'], 10, 4);
            add_action('updated_user_meta', [self::class, 'handle_user_meta_change'], 10, 4);
            add_action('deleted_user_meta', [self::class, 'handle_user_meta_change'], 10, 4);
            add_action('delete_user', [self::class, 'handle_delete_user'], 10, 1);
            add_action('profile_update', [self::class, 'handle_profile_update'], 10, 3);
            add_action('set_user_role', [self::class, 'handle_user_role_change'], 10, 3);
            add_action('password_reset', [self::class, 'handle_password_reset'], 10, 2);
            add_action('after_password_reset', [self::class, 'handle_password_reset'], 10, 2);
            add_action('wp_set_password', [self::class, 'handle_wp_set_password'], 10, 2);
        }
    }

    public static function is_available(): bool
    {
        return self::snapshot_redis() instanceof Redis;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_snapshot_by_identifier(string $identifier): ?array
    {
        $lookup_identifiers = self::build_lookup_identifiers($identifier);
        if (empty($lookup_identifiers)) {
            return null;
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return null;
        }

        foreach ($lookup_identifiers as $lookup_identifier) {
            $user_id = self::read_index_user_id($lookup_identifier, $redis);
            if ($user_id <= 0) {
                continue;
            }

            $snapshot = self::read_user_snapshot($user_id, $redis_available);
            if (is_array($snapshot)) {
                if (!in_array($lookup_identifier, (array) ($snapshot['identifiers'] ?? []), true)) {
                    self::clear_user_snapshot($user_id);
                    continue;
                }

                $redis->expire(self::index_storage_key($lookup_identifier), self::SNAPSHOT_REDIS_TTL_SECONDS);
                return $snapshot;
            }

            if ($redis_available) {
                $redis->del(self::index_storage_key($lookup_identifier));
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    public static function warm_user_snapshot(int $user_id, string $source = 'manual'): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return self::empty_snapshot();
        }

        $user = get_user_by('id', $user_id);
        if (!($user instanceof WP_User) || !self::is_snapshot_eligible_user($user)) {
            self::clear_user_snapshot($user_id);
            return self::empty_snapshot();
        }

        $snapshot = self::build_snapshot_from_user($user, $source);
        if (empty($snapshot['identifiers']) || (string) ($snapshot['password_hash'] ?? '') === '') {
            self::clear_user_snapshot($user_id);
            return self::empty_snapshot();
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return $snapshot;
        }

        self::clear_user_snapshot($user_id);
        $encoded = wp_json_encode($snapshot);
        if (!is_string($encoded) || $encoded === '') {
            return self::empty_snapshot();
        }

        $redis->setEx(self::user_storage_key($user_id), self::SNAPSHOT_REDIS_TTL_SECONDS, $encoded);
        foreach ((array) $snapshot['identifiers'] as $identifier_key) {
            $identifier_key = is_scalar($identifier_key) ? trim((string) $identifier_key) : '';
            if ($identifier_key === '') {
                continue;
            }

            $redis->setEx(self::index_storage_key($identifier_key), self::SNAPSHOT_REDIS_TTL_SECONDS, (string) $user_id);
        }

        return $snapshot;
    }

    public static function clear_user_snapshot(int $user_id): int
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return 0;
        }

        $keys = [self::user_storage_key($user_id)];
        $raw_snapshot = $redis->get(self::user_storage_key($user_id));
        $snapshot_identifiers = self::extract_identifiers_from_raw_snapshot($raw_snapshot);
        foreach ($snapshot_identifiers as $identifier_key) {
            $keys[] = self::index_storage_key($identifier_key);
        }

        $user = get_user_by('id', $user_id);
        if ($user instanceof WP_User) {
            foreach (self::build_user_identifiers($user) as $identifier_key) {
                $keys[] = self::index_storage_key($identifier_key);
            }
        }

        $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));
        if (empty($keys)) {
            return 0;
        }

        return (int) $redis->del(...$keys);
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    public static function warm_exam_target_snapshots(array $exam_row, string $source = 'manual_exam'): array
    {
        $target_student_ids = self::get_exam_target_student_ids($exam_row);
        $ready_count = 0;
        $failure_count = 0;

        foreach ($target_student_ids as $user_id) {
            self::warm_user_snapshot($user_id, $source);
            $diagnostics = self::get_snapshot_diagnostics($user_id);
            if ((string) ($diagnostics['snapshot_status'] ?? '') === 'ready') {
                $ready_count++;
            } else {
                $failure_count++;
            }
        }

        return [
            'exam_id' => (int) ($exam_row['id'] ?? 0),
            'target_student_ids' => $target_student_ids,
            'target_student_count' => count($target_student_ids),
            'processed_count' => count($target_student_ids),
            'ready_count' => $ready_count,
            'failure_count' => $failure_count,
        ];
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    public static function clear_exam_target_snapshots(array $exam_row): array
    {
        $target_student_ids = self::get_exam_target_student_ids($exam_row);
        $deleted_keys = 0;

        foreach ($target_student_ids as $user_id) {
            $deleted_keys += self::clear_user_snapshot($user_id);
        }

        return [
            'exam_id' => (int) ($exam_row['id'] ?? 0),
            'target_student_ids' => $target_student_ids,
            'target_student_count' => count($target_student_ids),
            'processed_count' => count($target_student_ids),
            'deleted_keys' => $deleted_keys,
        ];
    }

    /**
     * @return array{
     *   user_id:int,
     *   redis_available:bool,
     *   redis_error:string,
     *   redis_host:string,
     *   redis_database:int,
     *   storage_key:string,
     *   snapshot_exists:bool,
     *   snapshot_valid:bool,
     *   snapshot_status:string,
     *   snapshot_message:string,
     *   payload_bytes:int,
     *   ttl_seconds:int,
     *   generated_at:string,
     *   snapshot_source:string,
     *   identifiers:array<int,string>,
     *   preview:array<string,mixed>
     * }
     */
    public static function get_snapshot_diagnostics(int $user_id): array
    {
        $user_id = absint($user_id);
        $settings = self::snapshot_redis_settings();
        $storage_key = $user_id > 0 ? self::user_storage_key($user_id) : '';
        $redis = self::snapshot_redis();

        if ($user_id <= 0) {
            return [
                'user_id' => 0,
                'redis_available' => $redis instanceof Redis,
                'redis_error' => self::$snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
                'storage_key' => '',
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'idle',
                'snapshot_message' => 'User login snapshot belum dipilih.',
                'payload_bytes' => 0,
                'ttl_seconds' => -2,
                'generated_at' => '',
                'snapshot_source' => '',
                'identifiers' => [],
                'preview' => self::empty_preview(),
            ];
        }

        if (!$redis instanceof Redis) {
            return [
                'user_id' => $user_id,
                'redis_available' => false,
                'redis_error' => self::$snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
                'storage_key' => $storage_key,
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'unavailable',
                'snapshot_message' => 'Redis login snapshot tidak tersedia.',
                'payload_bytes' => 0,
                'ttl_seconds' => -2,
                'generated_at' => '',
                'snapshot_source' => '',
                'identifiers' => [],
                'preview' => self::empty_preview(),
            ];
        }

        $raw_snapshot = $redis->get($storage_key);
        $snapshot_exists = is_string($raw_snapshot) && trim($raw_snapshot) !== '';
        $payload_bytes = $snapshot_exists ? strlen((string) $raw_snapshot) : 0;
        $ttl_seconds = ($snapshot_exists && method_exists($redis, 'ttl')) ? (int) $redis->ttl($storage_key) : -2;
        $snapshot = $snapshot_exists ? self::decode_snapshot($raw_snapshot) : null;
        $snapshot_valid = is_array($snapshot);

        if ($snapshot_valid) {
            $snapshot_status = 'ready';
            $snapshot_message = 'Login snapshot siap dipakai untuk login siswa.';
        } elseif ($snapshot_exists) {
            $snapshot_status = 'invalid';
            $snapshot_message = 'Login snapshot ditemukan tetapi payload-nya tidak valid dan akan diabaikan.';
        } else {
            $snapshot_status = 'miss';
            $snapshot_message = 'Login snapshot belum ada. Jalur login akan fallback ke auth WordPress lalu hydrate ulang bila memungkinkan.';
        }

        return [
            'user_id' => $user_id,
            'redis_available' => true,
            'redis_error' => self::$snapshot_redis_last_connection_error,
            'redis_host' => (string) ($settings['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
            'redis_database' => (int) ($settings['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE),
            'storage_key' => $storage_key,
            'snapshot_exists' => $snapshot_exists,
            'snapshot_valid' => $snapshot_valid,
            'snapshot_status' => $snapshot_status,
            'snapshot_message' => $snapshot_message,
            'payload_bytes' => $payload_bytes,
            'ttl_seconds' => $ttl_seconds,
            'generated_at' => is_array($snapshot) ? (string) ($snapshot['generated_at'] ?? '') : '',
            'snapshot_source' => is_array($snapshot) ? (string) ($snapshot['source'] ?? '') : '',
            'identifiers' => is_array($snapshot) ? array_values(array_map('strval', (array) ($snapshot['identifiers'] ?? []))) : [],
            'preview' => is_array($snapshot) ? self::build_preview($snapshot) : self::empty_preview(),
        ];
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    public static function get_exam_target_snapshot_diagnostics(array $exam_row): array
    {
        $target_student_ids = self::get_exam_target_student_ids($exam_row);
        $ready_count = 0;
        $missing_count = 0;
        $invalid_count = 0;
        $unavailable_count = 0;

        foreach ($target_student_ids as $user_id) {
            $diagnostics = self::get_snapshot_diagnostics($user_id);
            $status = sanitize_key((string) ($diagnostics['snapshot_status'] ?? 'miss'));
            if ($status === 'ready') {
                $ready_count++;
                continue;
            }

            if ($status === 'invalid') {
                $invalid_count++;
            } elseif ($status === 'unavailable') {
                $unavailable_count++;
            } else {
                $missing_count++;
            }
        }

        return [
            'exam_id' => (int) ($exam_row['id'] ?? 0),
            'target_student_count' => count($target_student_ids),
            'ready_count' => $ready_count,
            'missing_count' => $missing_count,
            'invalid_count' => $invalid_count,
            'unavailable_count' => $unavailable_count,
        ];
    }

    public static function handle_delete_user(int $user_id): void
    {
        self::clear_user_snapshot($user_id);
    }

    /**
     * @param mixed $meta_id
     * @param mixed $user_id
     * @param mixed $meta_key
     * @param mixed $meta_value
     */
    public static function handle_user_meta_change($meta_id, $user_id, $meta_key, $meta_value): void
    {
        $user_id = absint($user_id);
        $meta_key = is_scalar($meta_key) ? (string) $meta_key : '';
        if ($user_id <= 0 || $meta_key !== 'nisn') {
            return;
        }

        self::clear_user_snapshot($user_id);
    }

    /**
     * @param array<string,mixed> $userdata
     */
    public static function handle_profile_update(int $user_id, WP_User $old_user_data, array $userdata = []): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $current_user = get_user_by('id', $user_id);
        if (!($current_user instanceof WP_User)) {
            self::clear_user_snapshot($user_id);
            return;
        }

        $old_login = strtolower(trim((string) ($old_user_data->user_login ?? '')));
        $old_email = strtolower(trim((string) ($old_user_data->user_email ?? '')));
        $new_login = strtolower(trim((string) $current_user->user_login));
        $new_email = strtolower(trim((string) $current_user->user_email));

        if ($old_login !== $new_login || $old_email !== $new_email) {
            self::clear_user_snapshot($user_id);
        }
    }

    /**
     * @param mixed $old_roles
     */
    public static function handle_user_role_change(int $user_id, string $role, $old_roles = []): void
    {
        self::clear_user_snapshot($user_id);
    }

    public static function handle_password_reset(WP_User $user, string $new_pass): void
    {
        self::clear_user_snapshot((int) ($user->ID ?? 0));
    }

    public static function handle_wp_set_password(string $password, int $user_id): void
    {
        self::clear_user_snapshot($user_id);
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_snapshot_from_user(WP_User $user, string $source): array
    {
        $user_id = (int) $user->ID;
        $profile = CBT_Student_Profile_Cache::get_snapshot($user_id);
        $identifiers = self::build_user_identifiers($user);

        return [
            'user_id' => $user_id,
            'role' => self::resolve_snapshot_role($user),
            'display_name' => sanitize_text_field((string) ($user->display_name !== '' ? $user->display_name : $user->user_login)),
            'user_login' => sanitize_text_field((string) $user->user_login),
            'user_email' => sanitize_email((string) $user->user_email),
            'nisn' => sanitize_text_field((string) get_user_meta($user_id, 'nisn', true)),
            'identifiers' => $identifiers,
            'password_hash' => (string) $user->user_pass,
            'kode_kelas' => (string) ($profile['kode_kelas'] ?? ''),
            'kode_ruang' => (string) ($profile['kode_ruang'] ?? ''),
            'agama' => (string) ($profile['agama'] ?? ''),
            'foto' => (string) ($profile['foto'] ?? ''),
            'jenis_kelamin' => (string) ($profile['jenis_kelamin'] ?? ''),
            'generated_at' => (string) current_time('mysql'),
            'ttl_seconds' => self::SNAPSHOT_REDIS_TTL_SECONDS,
            'source' => sanitize_key($source),
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function build_lookup_identifiers(string $identifier): array
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '') {
            return [];
        }

        $keys = [];
        if (is_email($identifier)) {
            $email = sanitize_email($identifier);
            if ($email !== '') {
                $keys[] = 'email:' . strtolower($email);
            }

            return array_values(array_unique($keys));
        }

        $login = sanitize_user($identifier, true);
        if ($login !== '') {
            $keys[] = 'login:' . strtolower($login);
        }

        $nisn = sanitize_text_field($identifier);
        if ($nisn !== '') {
            $keys[] = 'nisn:' . $nisn;
        }

        $keys[] = 'fallback:' . strtolower(sanitize_text_field($identifier));

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * @return array<int,string>
     */
    private static function build_user_identifiers(WP_User $user): array
    {
        $identifiers = [];
        $user_login = sanitize_user((string) $user->user_login, true);
        $user_email = sanitize_email((string) $user->user_email);
        $nisn = sanitize_text_field((string) get_user_meta((int) $user->ID, 'nisn', true));

        if ($user_login !== '') {
            $identifiers[] = 'login:' . strtolower($user_login);
        }

        if ($user_email !== '') {
            $identifiers[] = 'email:' . strtolower($user_email);
            $email_local_part = self::extract_fallback_local_part($user_email);
            if ($email_local_part !== '') {
                $identifiers[] = 'fallback:' . $email_local_part;
            }
        }

        if ($nisn !== '') {
            $identifiers[] = 'nisn:' . $nisn;
        }

        return array_values(array_unique(array_filter($identifiers)));
    }

    private static function extract_fallback_local_part(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '' || !str_ends_with($email, '@' . self::FALLBACK_EMAIL_DOMAIN)) {
            return '';
        }

        $parts = explode('@', $email, 2);
        $local = sanitize_text_field((string) ($parts[0] ?? ''));
        return strtolower($local);
    }

    private static function is_snapshot_eligible_user(WP_User $user): bool
    {
        return self::resolve_snapshot_role($user) === 'siswa';
    }

    private static function resolve_snapshot_role(WP_User $user): string
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
            in_array('subscriber', $user->roles, true) ||
            in_array('siswa', $user->roles, true)
        ) {
            return 'siswa';
        }

        return sanitize_key((string) ($user->roles[0] ?? ''));
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function read_user_snapshot(int $user_id, ?bool &$redis_available = null): ?array
    {
        $redis_available = false;
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return null;
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return null;
        }

        $redis_available = true;
        $raw_snapshot = $redis->get(self::user_storage_key($user_id));
        if (!is_string($raw_snapshot) || trim($raw_snapshot) === '') {
            return null;
        }

        $snapshot = self::decode_snapshot($raw_snapshot);
        if (!is_array($snapshot)) {
            self::clear_user_snapshot($user_id);
            return null;
        }

        $redis->expire(self::user_storage_key($user_id), self::SNAPSHOT_REDIS_TTL_SECONDS);
        return $snapshot;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function decode_snapshot(string $raw_snapshot): ?array
    {
        $decoded = json_decode($raw_snapshot, true);
        if (!is_array($decoded)) {
            return null;
        }

        $user_id = absint($decoded['user_id'] ?? 0);
        $role = sanitize_key((string) ($decoded['role'] ?? ''));
        $password_hash = (string) ($decoded['password_hash'] ?? '');
        $identifiers = array_values(array_filter(array_map(static function ($value): string {
            return is_scalar($value) ? trim((string) $value) : '';
        }, (array) ($decoded['identifiers'] ?? []))));

        if ($user_id <= 0 || $role === '' || $password_hash === '' || empty($identifiers)) {
            return null;
        }

        return [
            'user_id' => $user_id,
            'role' => $role,
            'display_name' => sanitize_text_field((string) ($decoded['display_name'] ?? '')),
            'user_login' => sanitize_text_field((string) ($decoded['user_login'] ?? '')),
            'user_email' => sanitize_email((string) ($decoded['user_email'] ?? '')),
            'nisn' => sanitize_text_field((string) ($decoded['nisn'] ?? '')),
            'identifiers' => $identifiers,
            'password_hash' => $password_hash,
            'kode_kelas' => sanitize_text_field((string) ($decoded['kode_kelas'] ?? '')),
            'kode_ruang' => sanitize_text_field((string) ($decoded['kode_ruang'] ?? '')),
            'agama' => sanitize_text_field((string) ($decoded['agama'] ?? '')),
            'foto' => esc_url_raw((string) ($decoded['foto'] ?? '')),
            'jenis_kelamin' => sanitize_text_field((string) ($decoded['jenis_kelamin'] ?? '')),
            'generated_at' => sanitize_text_field((string) ($decoded['generated_at'] ?? '')),
            'ttl_seconds' => max(0, (int) ($decoded['ttl_seconds'] ?? self::SNAPSHOT_REDIS_TTL_SECONDS)),
            'source' => sanitize_key((string) ($decoded['source'] ?? '')),
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function extract_identifiers_from_raw_snapshot($raw_snapshot): array
    {
        if (!is_string($raw_snapshot) || trim($raw_snapshot) === '') {
            return [];
        }

        $decoded = json_decode($raw_snapshot, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($value): string {
            return is_scalar($value) ? trim((string) $value) : '';
        }, (array) ($decoded['identifiers'] ?? []))));
    }

    private static function read_index_user_id(string $identifier_key, Redis $redis): int
    {
        $identifier_key = trim($identifier_key);
        if ($identifier_key === '') {
            return 0;
        }

        $raw_user_id = $redis->get(self::index_storage_key($identifier_key));
        if (!is_string($raw_user_id) || trim($raw_user_id) === '') {
            return 0;
        }

        return absint($raw_user_id);
    }

    /**
     * @return int[]
     */
    private static function get_exam_target_student_ids(array $exam_row): array
    {
        if (!class_exists('CBT_Exam_Availability_Auto_Warm_Service')
            || !method_exists('CBT_Exam_Availability_Auto_Warm_Service', 'get_target_student_ids_for_exam')) {
            return [];
        }

        return array_values(array_filter(array_map('absint', (array) CBT_Exam_Availability_Auto_Warm_Service::get_target_student_ids_for_exam($exam_row))));
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_preview(array $snapshot): array
    {
        return [
            'display_name' => (string) ($snapshot['display_name'] ?? ''),
            'user_login' => (string) ($snapshot['user_login'] ?? ''),
            'user_email' => (string) ($snapshot['user_email'] ?? ''),
            'role' => (string) ($snapshot['role'] ?? ''),
            'nisn' => (string) ($snapshot['nisn'] ?? ''),
            'kode_kelas' => (string) ($snapshot['kode_kelas'] ?? ''),
            'kode_ruang' => (string) ($snapshot['kode_ruang'] ?? ''),
            'agama' => (string) ($snapshot['agama'] ?? ''),
            'foto' => (string) ($snapshot['foto'] ?? ''),
            'jenis_kelamin' => (string) ($snapshot['jenis_kelamin'] ?? ''),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_snapshot(): array
    {
        return [
            'user_id' => 0,
            'role' => '',
            'display_name' => '',
            'user_login' => '',
            'user_email' => '',
            'nisn' => '',
            'identifiers' => [],
            'password_hash' => '',
            'kode_kelas' => '',
            'kode_ruang' => '',
            'agama' => '',
            'foto' => '',
            'jenis_kelamin' => '',
            'generated_at' => '',
            'ttl_seconds' => self::SNAPSHOT_REDIS_TTL_SECONDS,
            'source' => '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function empty_preview(): array
    {
        return [
            'display_name' => '',
            'user_login' => '',
            'user_email' => '',
            'role' => '',
            'nisn' => '',
            'kode_kelas' => '',
            'kode_ruang' => '',
            'agama' => '',
            'foto' => '',
            'jenis_kelamin' => '',
        ];
    }

    /**
     * @return Redis|null
     */
    private static function snapshot_redis(): ?Redis
    {
        if (self::$snapshot_redis_connection_attempted) {
            return (self::$snapshot_redis instanceof Redis) ? self::$snapshot_redis : null;
        }

        self::$snapshot_redis_connection_attempted = true;
        self::$snapshot_redis = false;
        self::$snapshot_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$snapshot_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::snapshot_redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::SNAPSHOT_REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::SNAPSHOT_REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::SNAPSHOT_REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::SNAPSHOT_REDIS_TIMEOUT)
                );
            }

            if ((string) ($config['password'] ?? '') !== '') {
                $redis->auth((string) $config['password']);
            }

            $database = (int) ($config['database'] ?? self::SNAPSHOT_REDIS_DEFAULT_DATABASE);
            if ($database > 0) {
                $redis->select($database);
            }

            self::$snapshot_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$snapshot_redis_last_connection_error = 'Koneksi login snapshot Redis gagal: ' . $throwable->getMessage();
            self::$snapshot_redis = false;
            return null;
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function snapshot_redis_settings(): array
    {
        $host = (string) getenv('CBT_REDIS_HOST');
        $port = (int) getenv('CBT_REDIS_PORT');
        $database = (int) getenv('CBT_REDIS_DB');
        $password = (string) getenv('CBT_REDIS_PASSWORD');
        $timeout = (float) getenv('CBT_REDIS_TIMEOUT');

        return [
            'scheme' => str_starts_with($host, '/') ? 'unix' : 'tcp',
            'host' => $host !== '' ? $host : self::SNAPSHOT_REDIS_DEFAULT_HOST,
            'port' => $port > 0 ? $port : self::SNAPSHOT_REDIS_DEFAULT_PORT,
            'database' => $database >= 0 ? $database : self::SNAPSHOT_REDIS_DEFAULT_DATABASE,
            'password' => $password,
            'timeout' => $timeout > 0 ? $timeout : self::SNAPSHOT_REDIS_TIMEOUT,
        ];
    }

    private static function user_storage_key(int $user_id): string
    {
        return self::SNAPSHOT_REDIS_PREFIX . 'user:' . absint($user_id);
    }

    private static function index_storage_key(string $identifier_key): string
    {
        return self::SNAPSHOT_REDIS_PREFIX . 'index:' . trim($identifier_key);
    }
}
