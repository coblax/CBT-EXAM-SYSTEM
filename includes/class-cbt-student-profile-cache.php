<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Student_Profile_Cache
{
    private const PROFILE_REDIS_TTL_SECONDS = 44100;
    private const PROFILE_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const PROFILE_REDIS_DEFAULT_PORT = 6379;
    private const PROFILE_REDIS_DEFAULT_DATABASE = 2;
    private const PROFILE_REDIS_PREFIX = 'cbt_profile:';
    private const PROFILE_REDIS_TIMEOUT = 1.5;
    /** @var array<int,string> */
    private const SNAPSHOT_FIELDS = [
        'kode_kelas',
        'kode_ruang',
        'agama',
        'foto',
        'jenis_kelamin',
        'nisn',
    ];

    /** @var Redis|false|null */
    private static $profile_redis = null;
    /** @var bool */
    private static $profile_redis_connection_attempted = false;
    /** @var string */
    private static $profile_redis_last_connection_error = '';

    public static function is_available(): bool
    {
        return self::profile_redis() instanceof Redis;
    }

    public static function init(): void
    {
        if (function_exists('add_action')) {
            add_action('added_user_meta', [self::class, 'handle_user_meta_change'], 10, 4);
            add_action('updated_user_meta', [self::class, 'handle_user_meta_change'], 10, 4);
            add_action('deleted_user_meta', [self::class, 'handle_user_meta_change'], 10, 4);
            add_action('delete_user', [self::class, 'handle_delete_user'], 10, 1);
        }
    }

    /**
     * @return array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}
     */
    public static function get_snapshot(int $user_id): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return self::empty_snapshot();
        }

        $redis_available = false;
        $snapshot = self::read_profile_redis_snapshot($user_id, $redis_available);
        if (is_array($snapshot)) {
            return $snapshot;
        }

        $snapshot = self::build_snapshot_from_usermeta($user_id);
        if ($redis_available) {
            self::write_profile_redis_snapshot($user_id, $snapshot);
        }

        return $snapshot;
    }

    /**
     * @return array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}
     */
    public static function warm_snapshot(int $user_id): array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return self::empty_snapshot();
        }

        $snapshot = self::build_snapshot_from_usermeta($user_id);
        $redis = self::profile_redis();
        if ($redis instanceof Redis) {
            self::write_profile_redis_snapshot($user_id, $snapshot);
        }

        return $snapshot;
    }

    public static function clear_snapshot(int $user_id): int
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return 0;
        }

        $redis = self::profile_redis();
        if (!$redis instanceof Redis) {
            return 0;
        }

        return (int) $redis->del(self::profile_storage_key($user_id));
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
     *   preview:array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}
     * }
     */
    public static function get_snapshot_diagnostics(int $user_id): array
    {
        $user_id = absint($user_id);
        $settings = self::profile_redis_settings();
        $storage_key = $user_id > 0 ? self::profile_storage_key($user_id) : '';
        $redis = self::profile_redis();

        if ($user_id <= 0) {
            return [
                'user_id' => 0,
                'redis_available' => $redis instanceof Redis,
                'redis_error' => self::$profile_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::PROFILE_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::PROFILE_REDIS_DEFAULT_DATABASE),
                'storage_key' => '',
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'idle',
                'snapshot_message' => 'User siswa belum dipilih.',
                'payload_bytes' => 0,
                'ttl_seconds' => -2,
                'preview' => self::empty_snapshot(),
            ];
        }

        if (!$redis instanceof Redis) {
            return [
                'user_id' => $user_id,
                'redis_available' => false,
                'redis_error' => self::$profile_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::PROFILE_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::PROFILE_REDIS_DEFAULT_DATABASE),
                'storage_key' => $storage_key,
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'unavailable',
                'snapshot_message' => 'Redis profile tidak tersedia.',
                'payload_bytes' => 0,
                'ttl_seconds' => -2,
                'preview' => self::empty_snapshot(),
            ];
        }

        $raw_snapshot = $storage_key !== '' ? $redis->get($storage_key) : false;
        $snapshot_exists = is_string($raw_snapshot) && trim($raw_snapshot) !== '';
        $snapshot_valid = false;
        $payload_bytes = $snapshot_exists ? strlen((string) $raw_snapshot) : 0;
        $ttl_seconds = ($snapshot_exists && method_exists($redis, 'ttl')) ? (int) $redis->ttl($storage_key) : -2;
        $preview = self::empty_snapshot();

        if ($snapshot_exists) {
            $decoded = json_decode((string) $raw_snapshot, true);
            if (is_array($decoded)) {
                $preview = self::sanitize_snapshot($decoded);
                $snapshot_valid = true;
            }
        }

        if ($snapshot_valid) {
            $snapshot_status = 'ready';
            $snapshot_message = 'Snapshot profil siswa siap dipakai untuk live payload.';
        } elseif ($snapshot_exists) {
            $snapshot_status = 'invalid';
            $snapshot_message = 'Snapshot profil ditemukan tetapi payload-nya tidak valid dan akan diabaikan.';
        } else {
            $snapshot_status = 'miss';
            $snapshot_message = 'Snapshot profil belum ada. Pembacaan profile berikutnya akan hydrate ke Redis.';
        }

        return [
            'user_id' => $user_id,
            'redis_available' => true,
            'redis_error' => self::$profile_redis_last_connection_error,
            'redis_host' => (string) ($settings['host'] ?? self::PROFILE_REDIS_DEFAULT_HOST),
            'redis_database' => (int) ($settings['database'] ?? self::PROFILE_REDIS_DEFAULT_DATABASE),
            'storage_key' => $storage_key,
            'snapshot_exists' => $snapshot_exists,
            'snapshot_valid' => $snapshot_valid,
            'snapshot_status' => $snapshot_status,
            'snapshot_message' => $snapshot_message,
            'payload_bytes' => $payload_bytes,
            'ttl_seconds' => $ttl_seconds,
            'preview' => $preview,
        ];
    }

    public static function invalidate(int $user_id): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $redis = self::profile_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $redis->del(self::profile_storage_key($user_id));
    }

    public static function handle_delete_user(int $user_id): void
    {
        self::invalidate($user_id);
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
        if ($user_id <= 0 || !self::is_relevant_meta_key($meta_key)) {
            return;
        }

        self::invalidate($user_id);
    }

    /**
     * @return array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}
     */
    private static function build_snapshot_from_usermeta(int $user_id): array
    {
        $meta = get_user_meta($user_id);
        return self::sanitize_snapshot($meta);
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}
     */
    private static function sanitize_snapshot(array $raw): array
    {
        $snapshot = self::empty_snapshot();
        foreach (self::SNAPSHOT_FIELDS as $field) {
            $value = $raw[$field] ?? '';
            if (is_array($value)) {
                $value = $value[0] ?? '';
            }

            if ($field === 'foto') {
                $snapshot[$field] = self::normalize_snapshot_photo_url((string) $value);
                continue;
            }

            $snapshot[$field] = sanitize_text_field((string) $value);
        }

        return $snapshot;
    }

    private static function normalize_snapshot_photo_url(string $value): string
    {
        $sanitized = esc_url_raw(trim($value));
        if ($sanitized === '') {
            return '';
        }

        if ($sanitized[0] === '/') {
            return $sanitized;
        }

        $parsed = wp_parse_url($sanitized);
        if (!is_array($parsed)) {
            return $sanitized;
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower((string) ($parsed['host'] ?? ''));
        $path = (string) ($parsed['path'] ?? '');
        if (($scheme !== 'http' && $scheme !== 'https') || $host === '' || $path === '') {
            return $sanitized;
        }

        $current = wp_parse_url(home_url('/'));
        if (!is_array($current)) {
            return $sanitized;
        }

        $current_scheme = strtolower((string) ($current['scheme'] ?? ''));
        $current_host = strtolower((string) ($current['host'] ?? ''));
        $query = (string) ($parsed['query'] ?? '');
        $fragment = (string) ($parsed['fragment'] ?? '');

        if ($host === $current_host && $scheme === 'http' && $current_scheme === 'https') {
            return self::build_current_site_asset_url($path, $query, $fragment);
        }

        if ($host !== $current_host && self::is_private_network_host($host) && self::is_wordpress_local_content_path($path)) {
            return self::build_current_site_asset_url($path, $query, $fragment);
        }

        return $sanitized;
    }

    private static function build_current_site_asset_url(string $path, string $query = '', string $fragment = ''): string
    {
        $normalized_path = '/' . ltrim($path, '/');
        $url = untrailingslashit(home_url('/')) . $normalized_path;
        if ($query !== '') {
            $url .= '?' . ltrim($query, '?');
        }
        if ($fragment !== '') {
            $url .= '#' . ltrim($fragment, '#');
        }

        return esc_url_raw($url);
    }

    private static function is_wordpress_local_content_path(string $path): bool
    {
        return preg_match('#^/(?:wp-content|wp-includes|wp-admin)/#i', $path) === 1;
    }

    private static function is_private_network_host(string $host): bool
    {
        $normalized = strtolower(trim($host));
        if ($normalized === '') {
            return false;
        }

        if ($normalized === 'localhost' || $normalized === '127.0.0.1' || $normalized === '::1') {
            return true;
        }

        if (preg_match('/^10\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $normalized) === 1) {
            return true;
        }

        if (preg_match('/^192\.168\.\d{1,3}\.\d{1,3}$/', $normalized) === 1) {
            return true;
        }

        return preg_match('/^172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}$/', $normalized) === 1;
    }

    /**
     * @return array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}|null
     */
    private static function read_profile_redis_snapshot(int $user_id, ?bool &$redis_available = null): ?array
    {
        $redis_available = false;
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return null;
        }

        $redis = self::profile_redis();
        if (!$redis instanceof Redis) {
            return null;
        }

        $redis_available = true;
        $raw_snapshot = $redis->get(self::profile_storage_key($user_id));
        if (!is_string($raw_snapshot) || trim($raw_snapshot) === '') {
            return null;
        }

        $decoded = json_decode($raw_snapshot, true);
        if (!is_array($decoded)) {
            self::invalidate($user_id);
            return null;
        }

        $snapshot = self::sanitize_snapshot($decoded);
        $redis->expire(self::profile_storage_key($user_id), self::PROFILE_REDIS_TTL_SECONDS);
        return $snapshot;
    }

    /**
     * @param array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string} $snapshot
     */
    private static function write_profile_redis_snapshot(int $user_id, array $snapshot): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $redis = self::profile_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $encoded = wp_json_encode(self::sanitize_snapshot($snapshot));
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        $redis->setEx(self::profile_storage_key($user_id), self::PROFILE_REDIS_TTL_SECONDS, $encoded);
    }

    /**
     * @return Redis|null
     */
    private static function profile_redis(): ?Redis
    {
        if (self::$profile_redis_connection_attempted) {
            return (self::$profile_redis instanceof Redis) ? self::$profile_redis : null;
        }

        self::$profile_redis_connection_attempted = true;
        self::$profile_redis = false;
        self::$profile_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$profile_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::profile_redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::PROFILE_REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::PROFILE_REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::PROFILE_REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::PROFILE_REDIS_TIMEOUT)
                );
            }

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::PROFILE_REDIS_DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis profile gagal.');
            }

            self::$profile_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$profile_redis_last_connection_error = 'Koneksi profile Redis gagal: ' . $throwable->getMessage();
            self::$profile_redis = false;
            return null;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float,scheme:string}
     */
    private static function profile_redis_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::PROFILE_REDIS_DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::PROFILE_REDIS_DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::PROFILE_REDIS_DEFAULT_PORT;
        }

        $database = self::constant_scalar('CBT_RUNTIME_REDIS_DATABASE', null);
        if ($database === null || $database === '') {
            $wp_database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::PROFILE_REDIS_DEFAULT_DATABASE - 1);
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
            'host' => $host !== '' ? $host : self::PROFILE_REDIS_DEFAULT_HOST,
            'port' => $port,
            'database' => (int) $database,
            'password' => $password,
            'timeout' => self::PROFILE_REDIS_TIMEOUT,
            'scheme' => $scheme,
        ];
    }

    private static function profile_storage_key(int $user_id): string
    {
        return self::PROFILE_REDIS_PREFIX . 'user:' . max(0, $user_id);
    }

    private static function is_relevant_meta_key(string $meta_key): bool
    {
        return in_array(trim($meta_key), self::SNAPSHOT_FIELDS, true);
    }

    /**
     * @return array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}
     */
    private static function empty_snapshot(): array
    {
        return [
            'kode_kelas' => '',
            'kode_ruang' => '',
            'agama' => '',
            'foto' => '',
            'jenis_kelamin' => '',
            'nisn' => '',
        ];
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private static function constant_scalar(string $constant_name, $default)
    {
        return defined($constant_name) ? constant($constant_name) : $default;
    }
}
