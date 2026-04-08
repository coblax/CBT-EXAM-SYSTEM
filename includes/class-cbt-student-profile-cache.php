<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Redis_Pipeline_Helper')) {
    require_once __DIR__ . '/class-cbt-redis-pipeline-helper.php';
}

class CBT_Student_Profile_Cache
{
    private const PROFILE_REDIS_TTL_SECONDS = 44100;
    private const PROFILE_EVENT_REDIS_TTL_SECONDS = 604800;
    private const PROFILE_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const PROFILE_REDIS_DEFAULT_PORT = 6379;
    private const PROFILE_REDIS_DEFAULT_DATABASE = 2;
    private const PROFILE_REDIS_PREFIX = 'cbt_profile:';
    private const PROFILE_EVENT_REDIS_PREFIX = 'cbt_profile_meta:';
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
        $result = self::warm_snapshot_result($user_id);
        return is_array($result['snapshot'] ?? null) ? $result['snapshot'] : self::empty_snapshot();
    }

    /**
     * @return array{
     *   ready:bool,
     *   write_success:bool,
     *   reason:string,
     *   snapshot:array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}
     * }
     */
    public static function warm_snapshot_result(int $user_id): array
    {
        $results = self::warm_snapshot_results([$user_id]);
        $user_id = absint($user_id);

        return $results[$user_id] ?? [
            'ready' => false,
            'write_success' => false,
            'reason' => 'invalid_user',
            'snapshot' => self::empty_snapshot(),
        ];
    }

    /**
     * @param int[] $user_ids
     * @return array<int,array{
     *   ready:bool,
     *   write_success:bool,
     *   reason:string,
     *   snapshot:array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}
     * }>
     */
    public static function warm_snapshot_results(array $user_ids): array
    {
        $user_ids = array_values(array_unique(array_filter(array_map('absint', $user_ids))));
        if (empty($user_ids)) {
            return [];
        }

        self::prime_snapshot_batch_caches($user_ids);
        $results = [];
        $operations = [];
        $operation_user_ids = [];

        foreach ($user_ids as $user_id) {
            $snapshot = self::build_snapshot_from_usermeta($user_id);
            $results[$user_id] = [
                'ready' => false,
                'write_success' => false,
                'reason' => 'redis_unavailable',
                'snapshot' => $snapshot,
            ];

            $operation = self::prepare_profile_snapshot_write($user_id, $snapshot);
            if (!is_array($operation)) {
                $results[$user_id]['reason'] = 'encode_failed';
                continue;
            }

            $operations[] = $operation;
            $operation_user_ids[] = $user_id;
        }

        $redis = self::profile_redis();
        if (!$redis instanceof Redis) {
            foreach ($user_ids as $user_id) {
                if (($results[$user_id]['reason'] ?? '') !== 'encode_failed') {
                    $results[$user_id]['reason'] = 'redis_unavailable';
                }
            }

            return $results;
        }

        $write_results = CBT_Redis_Pipeline_Helper::write_setex_results($redis, $operations);
        foreach ($operation_user_ids as $index => $user_id) {
            $write_success = !empty($write_results[$index]);
            $results[$user_id]['ready'] = $write_success;
            $results[$user_id]['write_success'] = $write_success;
            $results[$user_id]['reason'] = $write_success ? 'ready' : 'write_failed';
            if ($write_success) {
                self::write_profile_event_marker($user_id, 'written');
            }
        }

        return $results;
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

        $deleted = (int) $redis->del(self::profile_storage_key($user_id));
        self::write_profile_event_marker($user_id, 'manual_clear');

        return $deleted;
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
     *   snapshot_miss_reason:string,
     *   snapshot_miss_reason_label:string,
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
                'snapshot_miss_reason' => 'idle',
                'snapshot_miss_reason_label' => 'Belum dipilih',
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
                'snapshot_miss_reason' => 'redis_unavailable',
                'snapshot_miss_reason_label' => 'Redis tidak tersedia',
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
            $miss_reason = ['code' => '', 'label' => ''];
            $snapshot_message = 'Snapshot profil siswa siap dipakai untuk live payload.';
        } elseif ($snapshot_exists) {
            $snapshot_status = 'invalid';
            $miss_reason = ['code' => '', 'label' => ''];
            $snapshot_message = 'Snapshot profil ditemukan tetapi payload-nya tidak valid dan akan diabaikan.';
        } else {
            $snapshot_status = 'miss';
            $miss_reason = self::detect_snapshot_miss_reason($user_id, $redis);
            $snapshot_message = self::build_snapshot_miss_message($miss_reason);
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
            'snapshot_miss_reason' => (string) ($miss_reason['code'] ?? ''),
            'snapshot_miss_reason_label' => (string) ($miss_reason['label'] ?? ''),
            'snapshot_message' => $snapshot_message,
            'payload_bytes' => $payload_bytes,
            'ttl_seconds' => $ttl_seconds,
            'preview' => $preview,
        ];
    }

    public static function invalidate(int $user_id, string $reason = 'invalidated'): void
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
        self::write_profile_event_marker($user_id, $reason);
    }

    public static function handle_delete_user(int $user_id): void
    {
        self::invalidate($user_id, 'user_deleted');
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

        self::invalidate($user_id, 'meta_changed');
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
            self::invalidate($user_id, 'invalid_payload');
            return null;
        }

        $snapshot = self::sanitize_snapshot($decoded);
        $redis->expire(self::profile_storage_key($user_id), self::PROFILE_REDIS_TTL_SECONDS);
        return $snapshot;
    }

    /**
     * @param array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string} $snapshot
     */
    private static function write_profile_redis_snapshot(int $user_id, array $snapshot): bool
    {
        $operation = self::prepare_profile_snapshot_write($user_id, $snapshot);
        if (!is_array($operation)) {
            return false;
        }

        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return false;
        }

        $redis = self::profile_redis();
        if (!$redis instanceof Redis) {
            return false;
        }

        $write_success = $redis->setEx(
            (string) $operation['key'],
            (int) $operation['ttl'],
            (string) $operation['value']
        ) !== false;

        if ($write_success) {
            self::write_profile_event_marker($user_id, 'written');
        }

        return $write_success;
    }

    /**
     * @param array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string} $snapshot
     * @return array{key:string,ttl:int,value:string}|null
     */
    private static function prepare_profile_snapshot_write(int $user_id, array $snapshot): ?array
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return null;
        }

        $encoded = wp_json_encode(self::sanitize_snapshot($snapshot));
        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        return [
            'key' => self::profile_storage_key($user_id),
            'ttl' => self::PROFILE_REDIS_TTL_SECONDS,
            'value' => $encoded,
        ];
    }

    /**
     * @param int[] $user_ids
     */
    private static function prime_snapshot_batch_caches(array $user_ids): void
    {
        $user_ids = array_values(array_filter(array_map('absint', $user_ids)));
        if (empty($user_ids)) {
            return;
        }

        if (function_exists('cache_users')) {
            cache_users($user_ids);
        }

        if (function_exists('update_meta_cache')) {
            update_meta_cache('user', $user_ids);
        }
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

    private static function profile_event_storage_key(int $user_id): string
    {
        return self::PROFILE_EVENT_REDIS_PREFIX . 'user:' . max(0, $user_id);
    }

    private static function is_relevant_meta_key(string $meta_key): bool
    {
        return in_array(trim($meta_key), self::SNAPSHOT_FIELDS, true);
    }

    /**
     * @return array{code:string,label:string}
     */
    private static function detect_snapshot_miss_reason(int $user_id, Redis $redis): array
    {
        $event = self::read_profile_event_marker($user_id, $redis);
        $event_code = sanitize_key((string) ($event['event'] ?? ''));

        if ($event_code === 'meta_changed') {
            return ['code' => 'meta_changed', 'label' => 'Meta profil berubah'];
        }

        if ($event_code === 'user_invalidated' || $event_code === 'invalidated') {
            return ['code' => 'user_invalidated', 'label' => 'Invalidasi user'];
        }

        if ($event_code === 'manual_clear') {
            return ['code' => 'manual_clear', 'label' => 'Dibersihkan manual'];
        }

        if ($event_code === 'user_deleted') {
            return ['code' => 'user_deleted', 'label' => 'User dihapus'];
        }

        if ($event_code === 'invalid_payload') {
            return ['code' => 'invalid_payload', 'label' => 'Payload invalid'];
        }

        if ($event_code === 'written') {
            return ['code' => 'expired_or_evicted', 'label' => 'TTL habis / ter-evict'];
        }

        return ['code' => 'not_prepared', 'label' => 'Belum disiapkan'];
    }

    /**
     * @param array{code:string,label:string} $miss_reason
     */
    private static function build_snapshot_miss_message(array $miss_reason): string
    {
        $code = sanitize_key((string) ($miss_reason['code'] ?? ''));

        if ($code === 'meta_changed') {
            return 'Snapshot profil MISS karena meta profil siswa berubah, jadi key sebelumnya sengaja dihapus dan akan dihydrate ulang pada pembacaan berikutnya.';
        }

        if ($code === 'user_invalidated') {
            return 'Snapshot profil MISS karena namespace user diinvalidasi oleh runtime, jadi key profile lama ikut dibersihkan dan akan dihydrate ulang.';
        }

        if ($code === 'manual_clear') {
            return 'Snapshot profil MISS karena dibersihkan manual dari panel admin. Pembacaan profile berikutnya akan hydrate ke Redis.';
        }

        if ($code === 'user_deleted') {
            return 'Snapshot profil MISS karena user siswa sudah dihapus.';
        }

        if ($code === 'invalid_payload') {
            return 'Snapshot profil MISS karena payload Redis sebelumnya tidak valid, sehingga key lama dibuang dan akan dihydrate ulang.';
        }

        if ($code === 'expired_or_evicted') {
            return 'Snapshot profil MISS karena key sebelumnya kemungkinan sudah expired atau ter-evict. Pembacaan profile berikutnya akan hydrate ke Redis.';
        }

        return 'Snapshot profil belum ada. Pembacaan profile berikutnya akan hydrate ke Redis.';
    }

    private static function write_profile_event_marker(int $user_id, string $event): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $redis = self::profile_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $payload = wp_json_encode([
            'event' => sanitize_key($event),
            'recorded_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if (!is_string($payload) || $payload === '') {
            return;
        }

        $redis->setEx(
            self::profile_event_storage_key($user_id),
            self::PROFILE_EVENT_REDIS_TTL_SECONDS,
            $payload
        );
    }

    private static function read_profile_event_marker(int $user_id, Redis $redis): ?array
    {
        $raw_event = $redis->get(self::profile_event_storage_key($user_id));
        if (!is_string($raw_event) || trim($raw_event) === '') {
            return null;
        }

        $decoded = json_decode($raw_event, true);
        if (!is_array($decoded)) {
            $redis->del(self::profile_event_storage_key($user_id));
            return null;
        }

        return $decoded;
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
