<?php

if (!defined('ABSPATH')) {
    exit;
}

class CBT_Cache
{
    private const CACHE_GROUP = 'cbt_exam_system';
    private const REGISTRY_OPTION = 'cbt_exam_cache_registry_v1';
    private const TRANSIENT_PREFIX = 'cbt_ce_';
    private const LOCK_OPTION_PREFIX = 'cbt_ce_lock_';
    private const CACHE_SENTINEL = '__cbt_cache_envelope_v1';
    private const MAX_LOCK_REGISTRY = 200;
    private const MAX_UI_STATE_REGISTRY = 200;
    private const DEFAULT_NAMESPACE_PRUNE_RETENTION = 259200;

    /** @var array<string,mixed>|null */
    private static $registry = null;

    public static function object_cache_active(): bool
    {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    public static function object_cache_dropin_present(): bool
    {
        return defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR . '/object-cache.php');
    }

    public static function wp_cache_enabled(): bool
    {
        return defined('WP_CACHE') && (bool) constant('WP_CACHE');
    }

    public static function runtime_mode(): string
    {
        return self::object_cache_active() ? 'persistent_object_cache' : 'transient_fallback';
    }

    /**
     * @param array<int,string> $namespaces
     * @return mixed
     */
    public static function remember(string $logical_key, int $ttl, array $namespaces, callable $producer)
    {
        $cached = self::get($logical_key, $namespaces, $found);
        if ($found) {
            return $cached;
        }

        $value = $producer();
        self::set($logical_key, $value, $ttl, $namespaces);
        return $value;
    }

    /**
     * @param array<int,string> $namespaces
     * @return mixed|null
     */
    public static function get(string $logical_key, array $namespaces = [], ?bool &$found = null)
    {
        $storage_key = self::build_storage_key($logical_key, $namespaces);
        $found = false;

        if (self::object_cache_active()) {
            $envelope = wp_cache_get($storage_key, self::CACHE_GROUP, false, $cache_found);
            if (!$cache_found || !self::is_cache_envelope($envelope)) {
                return null;
            }

            $found = true;
            return $envelope['value'];
        }

        $envelope = get_transient(self::transient_key($storage_key));
        if (!self::is_cache_envelope($envelope)) {
            return null;
        }

        $found = true;
        return $envelope['value'];
    }

    /**
     * @param array<int,string> $namespaces
     * @param mixed $value
     */
    public static function set(string $logical_key, $value, int $ttl, array $namespaces = []): bool
    {
        $ttl = max(1, $ttl);
        $storage_key = self::build_storage_key($logical_key, $namespaces);
        $envelope = [
            self::CACHE_SENTINEL => 1,
            'value' => $value,
            'stored_at' => time(),
            'expires_at' => time() + $ttl,
        ];

        if (self::object_cache_active()) {
            return wp_cache_set($storage_key, $envelope, self::CACHE_GROUP, $ttl);
        }

        return set_transient(self::transient_key($storage_key), $envelope, $ttl);
    }

    /**
     * @param array<int,string> $namespaces
     */
    public static function delete(string $logical_key, array $namespaces = []): bool
    {
        $storage_key = self::build_storage_key($logical_key, $namespaces);

        if (self::object_cache_active()) {
            return wp_cache_delete($storage_key, self::CACHE_GROUP);
        }

        return delete_transient(self::transient_key($storage_key));
    }

    public static function namespace_catalog(): string
    {
        return 'catalog';
    }

    public static function namespace_exam(int $exam_id): string
    {
        return 'exam:' . max(0, $exam_id);
    }

    public static function namespace_user(int $user_id): string
    {
        return 'user:' . max(0, $user_id);
    }

    public static function namespace_attempt(int $attempt_id): string
    {
        return 'attempt:' . max(0, $attempt_id);
    }

    public static function namespace_ui_state(): string
    {
        return 'ui_state';
    }

    public static function namespace_analytics(): string
    {
        return 'analytics';
    }

    public static function namespace_analytics_exam(int $exam_id): string
    {
        return 'analytics_exam:' . max(0, $exam_id);
    }

    public static function invalidate_catalog(): void
    {
        self::invalidate_namespace(self::namespace_catalog());
        self::invalidate_analytics();
    }

    public static function invalidate_exam(int $exam_id): void
    {
        if ($exam_id > 0) {
            self::invalidate_namespace(self::namespace_exam($exam_id));
            self::invalidate_analytics_exam($exam_id);
        }
    }

    /**
     * @param array<int,int> $exam_ids
     */
    public static function invalidate_exams(array $exam_ids): void
    {
        foreach ($exam_ids as $exam_id) {
            self::invalidate_exam((int) $exam_id);
        }
    }

    public static function invalidate_user(int $user_id): void
    {
        if ($user_id > 0) {
            self::invalidate_namespace(self::namespace_user($user_id));
            if (class_exists('CBT_Student_Profile_Cache')) {
                CBT_Student_Profile_Cache::invalidate($user_id, 'user_invalidated');
            }
        }
    }

    /**
     * @param array<int,int> $user_ids
     */
    public static function invalidate_users(array $user_ids): void
    {
        foreach ($user_ids as $user_id) {
            self::invalidate_user((int) $user_id);
        }
    }

    public static function invalidate_attempt(int $attempt_id): void
    {
        if ($attempt_id > 0) {
            self::invalidate_namespace(self::namespace_attempt($attempt_id));
        }
    }

    /**
     * @param array<int,int> $attempt_ids
     */
    public static function invalidate_attempts(array $attempt_ids): void
    {
        foreach ($attempt_ids as $attempt_id) {
            self::invalidate_attempt((int) $attempt_id);
        }
    }

    public static function invalidate_ui_state(): void
    {
        self::invalidate_namespace(self::namespace_ui_state());
    }

    public static function invalidate_analytics(): void
    {
        self::invalidate_namespace(self::namespace_analytics());
    }

    public static function invalidate_analytics_exam(int $exam_id): void
    {
        if ($exam_id > 0) {
            self::invalidate_namespace(self::namespace_analytics_exam($exam_id));
        }
    }

    /**
     * @param array<int,int> $exam_ids
     */
    public static function invalidate_analytics_exams(array $exam_ids): void
    {
        foreach ($exam_ids as $exam_id) {
            self::invalidate_analytics_exam((int) $exam_id);
        }
    }

    public static function invalidate_namespace(string $namespace): int
    {
        $namespace = self::normalize_namespace($namespace);
        if ($namespace === '') {
            return 0;
        }

        $registry = self::load_registry();
        $current = isset($registry['namespaces'][$namespace]['version'])
            ? (int) $registry['namespaces'][$namespace]['version']
            : 1;
        $next = $current + 1;
        $registry['namespaces'][$namespace] = [
            'version' => $next,
            'invalidated_at' => time(),
        ];
        self::save_registry($registry);
        return $next;
    }

    public static function invalidate_all(): int
    {
        $registry = self::load_registry();
        $registry['global_version'] = max(1, (int) ($registry['global_version'] ?? 1)) + 1;
        $registry['global_invalidated_at'] = time();
        self::save_registry($registry);
        return (int) $registry['global_version'];
    }

    public static function namespace_prune_retention_seconds(): int
    {
        $configured = defined('CBT_CACHE_NAMESPACE_PRUNE_RETENTION')
            ? (int) constant('CBT_CACHE_NAMESPACE_PRUNE_RETENTION')
            : self::DEFAULT_NAMESPACE_PRUNE_RETENTION;

        return $configured > 0 ? $configured : self::DEFAULT_NAMESPACE_PRUNE_RETENTION;
    }

    public static function prune_old_namespaces(?int $max_age = null): int
    {
        $registry = self::load_registry();
        $retention = $max_age !== null && $max_age > 0
            ? $max_age
            : self::namespace_prune_retention_seconds();
        $pruned = self::prune_old_namespaces_in_registry($registry, time(), $retention);
        if ($pruned > 0) {
            self::save_registry($registry);
        }

        return $pruned;
    }

    public static function reset_plugin_cache_state(): void
    {
        $registry = self::load_registry();
        $registry['global_version'] = max(1, (int) ($registry['global_version'] ?? 1)) + 1;
        $registry['global_invalidated_at'] = time();
        $registry['namespaces'] = [];
        $registry['locks'] = [];
        $registry['ui_states'] = [];
        self::save_registry($registry);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function acquire_lock(string $lock_key, int $ttl, array $context = []): bool
    {
        $lock_key = trim($lock_key);
        if ($lock_key === '') {
            return false;
        }

        $ttl = max(1, $ttl);
        $now = time();
        $payload = [
            'lock_key' => $lock_key,
            'context' => $context,
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => $now + $ttl,
        ];

        $acquired = self::object_cache_active()
            ? self::acquire_object_cache_lock($lock_key, $payload, $ttl)
            : self::acquire_option_lock($lock_key, $payload);

        if ($acquired) {
            self::register_lock($lock_key, $payload);
        }

        return $acquired;
    }

    public static function release_lock(string $lock_key): bool
    {
        $lock_key = trim($lock_key);
        if ($lock_key === '') {
            return false;
        }

        if (self::object_cache_active()) {
            wp_cache_delete(self::lock_storage_key($lock_key), self::CACHE_GROUP);
        } else {
            delete_option(self::option_lock_key($lock_key));
        }

        self::unregister_lock($lock_key);
        return true;
    }

    public static function release_stale_locks(): int
    {
        $locks = self::get_lock_registry_entries();
        $now = time();
        $released = 0;

        foreach ($locks as $entry) {
            $expires_at = (int) ($entry['expires_at'] ?? 0);
            $lock_key = (string) ($entry['lock_key'] ?? '');
            if ($lock_key === '' || $expires_at > $now) {
                continue;
            }

            self::release_lock($lock_key);
            $released++;
        }

        return $released;
    }

    /**
     * @param array<string,mixed> $meta
     */
    public static function register_ui_state(string $registry_key, array $meta): void
    {
        $registry_key = trim($registry_key);
        if ($registry_key === '') {
            return;
        }

        $registry = self::load_registry();
        if (!isset($registry['ui_states']) || !is_array($registry['ui_states'])) {
            $registry['ui_states'] = [];
        }

        $registry['ui_states'][$registry_key] = [
            'registry_key' => $registry_key,
            'type' => (string) ($meta['type'] ?? ''),
            'user_id' => (int) ($meta['user_id'] ?? 0),
            'attempt_id' => (int) ($meta['attempt_id'] ?? 0),
            'updated_at' => (int) ($meta['updated_at'] ?? time()),
            'expires_at' => (int) ($meta['expires_at'] ?? 0),
            'context' => is_array($meta['context'] ?? null) ? $meta['context'] : [],
        ];

        uasort($registry['ui_states'], static function (array $left, array $right): int {
            return (int) ($right['updated_at'] ?? 0) <=> (int) ($left['updated_at'] ?? 0);
        });

        if (count($registry['ui_states']) > self::MAX_UI_STATE_REGISTRY) {
            $registry['ui_states'] = array_slice($registry['ui_states'], 0, self::MAX_UI_STATE_REGISTRY, true);
        }

        self::save_registry($registry);
    }

    public static function unregister_ui_state(string $registry_key): void
    {
        $registry = self::load_registry();
        if (!isset($registry['ui_states'][$registry_key])) {
            return;
        }

        unset($registry['ui_states'][$registry_key]);
        self::save_registry($registry);
    }

    public static function clear_ui_state_registry(): void
    {
        $registry = self::load_registry();
        $registry['ui_states'] = [];
        self::save_registry($registry);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_ui_state_registry_entries(): array
    {
        $registry = self::load_registry();
        $entries = isset($registry['ui_states']) && is_array($registry['ui_states'])
            ? array_values($registry['ui_states'])
            : [];

        usort($entries, static function (array $left, array $right): int {
            return (int) ($right['updated_at'] ?? 0) <=> (int) ($left['updated_at'] ?? 0);
        });

        return $entries;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_lock_registry_entries(): array
    {
        $registry = self::load_registry();
        $entries = isset($registry['locks']) && is_array($registry['locks'])
            ? array_values($registry['locks'])
            : [];
        $now = time();

        usort($entries, static function (array $left, array $right): int {
            return (int) ($right['updated_at'] ?? 0) <=> (int) ($left['updated_at'] ?? 0);
        });

        foreach ($entries as &$entry) {
            $entry['is_stale'] = ((int) ($entry['expires_at'] ?? 0) <= $now) ? 1 : 0;
        }
        unset($entry);

        return $entries;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_namespace_registry_entries(): array
    {
        $registry = self::load_registry();
        $entries = [];

        $entries[] = [
            'namespace' => '__global__',
            'version' => (int) ($registry['global_version'] ?? 1),
            'invalidated_at' => (int) ($registry['global_invalidated_at'] ?? 0),
        ];

        $namespaces = isset($registry['namespaces']) && is_array($registry['namespaces'])
            ? $registry['namespaces']
            : [];
        ksort($namespaces);
        foreach ($namespaces as $namespace => $payload) {
            $entries[] = [
                'namespace' => (string) $namespace,
                'version' => (int) ($payload['version'] ?? 1),
                'invalidated_at' => (int) ($payload['invalidated_at'] ?? 0),
            ];
        }

        return $entries;
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_namespace_registry_entry(string $namespace): array
    {
        $normalized_namespace = self::normalize_namespace($namespace);
        $registry = self::load_registry();
        $payload = (
            $normalized_namespace !== ''
            && isset($registry['namespaces'][$normalized_namespace])
            && is_array($registry['namespaces'][$normalized_namespace])
        )
            ? $registry['namespaces'][$normalized_namespace]
            : [];

        return [
            'namespace' => $normalized_namespace,
            'version' => max(1, (int) ($payload['version'] ?? 1)),
            'invalidated_at' => max(0, (int) ($payload['invalidated_at'] ?? 0)),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_exam_revision_meta(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return [
                'exam_id' => 0,
                'namespace' => '',
                'version' => 0,
                'invalidated_at' => 0,
                'signature' => '',
            ];
        }

        $namespace = self::namespace_exam($exam_id);
        $entry = self::get_namespace_registry_entry($namespace);
        $version = max(1, (int) ($entry['version'] ?? 1));
        $invalidated_at = max(0, (int) ($entry['invalidated_at'] ?? 0));

        return [
            'exam_id' => $exam_id,
            'namespace' => $namespace,
            'version' => $version,
            'invalidated_at' => $invalidated_at,
            'signature' => $namespace . '|v:' . $version . '|t:' . $invalidated_at,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_admin_overview(): array
    {
        $redis_config = self::redis_config_summary();
        $server_probe = self::probe_redis_server($redis_config);
        $probe = self::object_cache_probe();
        $backend_hint = self::object_cache_backend_hint($redis_config);
        $runtime_buffer = class_exists('CBT_Runtime') ? CBT_Runtime::get_admin_overview() : [];
        $wp_cache_enabled = self::wp_cache_enabled();
        $object_cache_active = self::object_cache_active();
        $dropin_present = self::object_cache_dropin_present();

        return [
            'health' => [
                'wp_cache_enabled' => $wp_cache_enabled ? 1 : 0,
                'object_cache_active' => $object_cache_active ? 1 : 0,
                'object_cache_dropin_present' => $dropin_present ? 1 : 0,
                'runtime_mode' => self::runtime_mode(),
                'backend_hint' => $backend_hint,
                'readiness' => self::object_cache_readiness(
                    $wp_cache_enabled,
                    $object_cache_active,
                    $dropin_present,
                    $backend_hint,
                    $probe,
                    $redis_config
                ),
                'cache_group' => self::CACHE_GROUP,
                'redis_config' => $redis_config,
                'server_probe' => $server_probe,
                'probe' => $probe,
                'runtime_buffer' => is_array($runtime_buffer) ? $runtime_buffer : [],
            ],
            'ttl_reference' => self::ttl_reference(),
            'namespaces' => self::get_namespace_registry_entries(),
            'locks' => self::get_lock_registry_entries(),
            'ui_states' => self::get_ui_state_registry_entries(),
        ];
    }

    /**
     * @return array<string,int>
     */
    public static function ttl_reference(): array
    {
        return [
            'get_exams' => 15,
            'get_questions' => 43200,
            'submit_question_context' => 43200,
            'get_result' => 300,
            'ui_preferences' => 2592000,
            'ui_attempt_state' => 172800,
            'locks' => 15,
            'runtime_flush_delay' => 5,
            'runtime_attempt_ttl_extension' => 7200,
            'namespace_prune_after' => self::namespace_prune_retention_seconds(),
        ];
    }

    /**
     * @param array<int,string> $namespaces
     */
    private static function build_storage_key(string $logical_key, array $namespaces = []): string
    {
        $signature = self::build_namespace_signature($namespaces);
        return 'cbt:' . md5($signature . '|' . $logical_key);
    }

    /**
     * @param array<int,string> $namespaces
     */
    private static function build_namespace_signature(array $namespaces): string
    {
        $registry = self::load_registry();
        $parts = ['g' . max(1, (int) ($registry['global_version'] ?? 1))];
        $namespaces = array_values(array_unique(array_filter(array_map([self::class, 'normalize_namespace'], $namespaces))));
        sort($namespaces, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($namespaces as $namespace) {
            $version = isset($registry['namespaces'][$namespace]['version'])
                ? (int) $registry['namespaces'][$namespace]['version']
                : 1;
            $parts[] = $namespace . ':' . max(1, $version);
        }

        return implode('|', $parts);
    }

    private static function transient_key(string $storage_key): string
    {
        return self::TRANSIENT_PREFIX . md5($storage_key);
    }

    private static function lock_storage_key(string $lock_key): string
    {
        return 'lock:' . md5($lock_key);
    }

    private static function option_lock_key(string $lock_key): string
    {
        return self::LOCK_OPTION_PREFIX . md5($lock_key);
    }

    /**
     * @param mixed $payload
     */
    private static function is_cache_envelope($payload): bool
    {
        return is_array($payload) && isset($payload[self::CACHE_SENTINEL]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function load_registry(): array
    {
        if (is_array(self::$registry)) {
            return self::$registry;
        }

        $raw = get_option(self::REGISTRY_OPTION, []);
        $registry = is_array($raw) ? $raw : [];
        if (!isset($registry['global_version'])) {
            $registry['global_version'] = 1;
        }
        if (!isset($registry['global_invalidated_at'])) {
            $registry['global_invalidated_at'] = 0;
        }
        if (!isset($registry['namespaces']) || !is_array($registry['namespaces'])) {
            $registry['namespaces'] = [];
        }
        if (!isset($registry['locks']) || !is_array($registry['locks'])) {
            $registry['locks'] = [];
        }
        if (!isset($registry['ui_states']) || !is_array($registry['ui_states'])) {
            $registry['ui_states'] = [];
        }

        $pruned = self::prune_old_namespaces_in_registry($registry, time(), self::namespace_prune_retention_seconds());

        self::$registry = $registry;
        if ($pruned > 0) {
            if (get_option(self::REGISTRY_OPTION, null) === null) {
                add_option(self::REGISTRY_OPTION, $registry, '', false);
            } else {
                update_option(self::REGISTRY_OPTION, $registry, false);
            }
        }
        return self::$registry;
    }

    /**
     * @param array<string,mixed> $registry
     */
    private static function save_registry(array $registry): void
    {
        self::$registry = $registry;

        if (get_option(self::REGISTRY_OPTION, null) === null) {
            add_option(self::REGISTRY_OPTION, $registry, '', false);
            return;
        }

        update_option(self::REGISTRY_OPTION, $registry, false);
    }

    private static function normalize_namespace(string $namespace): string
    {
        $namespace = strtolower(trim($namespace));
        if ($namespace === '') {
            return '';
        }

        return preg_replace('/[^a-z0-9:_-]/', '', $namespace) ?: '';
    }

    /**
     * @param array<string,mixed> $registry
     */
    private static function prune_old_namespaces_in_registry(array &$registry, int $now, int $retention): int
    {
        if ($retention <= 0 || !isset($registry['namespaces']) || !is_array($registry['namespaces'])) {
            return 0;
        }

        $cutoff = $now - $retention;
        $pruned = 0;

        foreach ($registry['namespaces'] as $namespace => $payload) {
            if (!is_array($payload)) {
                unset($registry['namespaces'][$namespace]);
                $pruned++;
                continue;
            }

            $invalidated_at = (int) ($payload['invalidated_at'] ?? 0);
            if ($invalidated_at > 0 && $invalidated_at <= $cutoff) {
                unset($registry['namespaces'][$namespace]);
                $pruned++;
            }
        }

        return $pruned;
    }

    /**
     * @return array<string,mixed>
     */
    private static function redis_config_summary(): array
    {
        return [
            'host' => self::config_constant_summary('WP_REDIS_HOST'),
            'port' => self::config_constant_summary('WP_REDIS_PORT'),
            'database' => self::config_constant_summary('WP_REDIS_DATABASE'),
            'prefix' => self::config_constant_summary('WP_REDIS_PREFIX'),
            'scheme' => self::config_constant_summary('WP_REDIS_SCHEME'),
            'client' => self::config_constant_summary('WP_REDIS_CLIENT'),
            'password_configured' => self::config_constant_has_non_empty_value('WP_REDIS_PASSWORD') ? 1 : 0,
            'disabled' => defined('WP_REDIS_DISABLED') ? ((bool) constant('WP_REDIS_DISABLED') ? 1 : 0) : null,
        ];
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public static function probe_redis_server(array $config = []): array
    {
        $config = !empty($config) ? $config : self::redis_config_summary();
        $target = self::redis_socket_target($config);

        if ($target['status'] !== 'ready') {
            return [
                'status' => 'skipped',
                'endpoint' => (string) ($target['endpoint_label'] ?? '-'),
                'message' => (string) ($target['message'] ?? 'Konfigurasi Redis belum tersedia.'),
                'tested_at' => time(),
            ];
        }

        $address = (string) ($target['address'] ?? '');
        $timeout = (float) ($target['timeout'] ?? 1.5);
        $errno = 0;
        $errstr = '';
        $handle = @stream_socket_client($address, $errno, $errstr, $timeout);
        if (!is_resource($handle)) {
            return [
                'status' => 'unreachable',
                'endpoint' => (string) ($target['endpoint_label'] ?? '-'),
                'message' => trim(sprintf('Koneksi ke Redis gagal (%s).', $errstr !== '' ? $errstr : ('errno ' . $errno))),
                'tested_at' => time(),
            ];
        }

        fclose($handle);

        return [
            'status' => 'reachable',
            'endpoint' => (string) ($target['endpoint_label'] ?? '-'),
            'message' => 'TCP/socket Redis dapat dijangkau dari WordPress.',
            'tested_at' => time(),
        ];
    }

    /**
     * @param array<string,mixed> $redis_config
     */
    private static function object_cache_backend_hint(array $redis_config): string
    {
        if (!self::object_cache_active()) {
            return 'transient_fallback';
        }

        return self::object_cache_looks_like_redis($redis_config) ? 'redis' : 'persistent_object_cache';
    }

    /**
     * @param array<string,mixed> $probe
     * @param array<string,mixed> $redis_config
     */
    private static function object_cache_readiness(
        bool $wp_cache_enabled,
        bool $object_cache_active,
        bool $dropin_present,
        string $backend_hint,
        array $probe,
        array $redis_config
    ): string {
        $probe_status = (string) ($probe['status'] ?? '');
        if ($object_cache_active && $backend_hint === 'redis' && $probe_status === 'passed') {
            return 'ready';
        }

        if (
            $object_cache_active ||
            $wp_cache_enabled ||
            $dropin_present ||
            self::redis_config_has_signal($redis_config)
        ) {
            return 'partial';
        }

        return 'fallback';
    }

    /**
     * @param array<string,mixed> $redis_config
     */
    private static function object_cache_looks_like_redis(array $redis_config): bool
    {
        if (self::redis_config_has_signal($redis_config)) {
            return true;
        }

        global $wp_object_cache;
        if (is_object($wp_object_cache) && stripos(get_class($wp_object_cache), 'redis') !== false) {
            return true;
        }

        if (!defined('WP_CONTENT_DIR')) {
            return false;
        }

        $dropin = WP_CONTENT_DIR . '/object-cache.php';
        if (!is_readable($dropin)) {
            return false;
        }

        $contents = file_get_contents($dropin, false, null, 0, 8192);
        return is_string($contents) && stripos($contents, 'redis') !== false;
    }

    /**
     * @param array<string,mixed> $redis_config
     */
    private static function redis_config_has_signal(array $redis_config): bool
    {
        foreach (['host', 'port', 'database', 'prefix', 'scheme', 'client'] as $key) {
            $value = isset($redis_config[$key]) ? trim((string) $redis_config[$key]) : '';
            if ($value !== '') {
                return true;
            }
        }

        return !empty($redis_config['password_configured']) || (array_key_exists('disabled', $redis_config) && $redis_config['disabled'] !== null);
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private static function redis_socket_target(array $config): array
    {
        $host = trim((string) ($config['host'] ?? ''));
        $scheme = strtolower(trim((string) ($config['scheme'] ?? '')));
        $timeout = 1.5;

        if ($scheme === 'unix' || strpos($host, '/') === 0) {
            if ($host === '') {
                return [
                    'status' => 'missing',
                    'message' => 'Unix socket Redis belum ditentukan.',
                    'endpoint_label' => '-',
                ];
            }

            return [
                'status' => 'ready',
                'address' => 'unix://' . $host,
                'endpoint_label' => 'unix://' . $host,
                'timeout' => $timeout,
            ];
        }

        if ($host === '') {
            return [
                'status' => 'missing',
                'message' => 'WP_REDIS_HOST belum ditentukan.',
                'endpoint_label' => '-',
            ];
        }

        $port = (int) ($config['port'] ?? 6379);
        if ($port <= 0) {
            $port = 6379;
        }

        return [
            'status' => 'ready',
            'address' => 'tcp://' . $host . ':' . $port,
            'endpoint_label' => $host . ':' . $port,
            'timeout' => $timeout,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function object_cache_probe(): array
    {
        if (!self::object_cache_active()) {
            return [
                'status' => 'skipped',
                'message' => 'Probe dilewati karena external object cache belum aktif.',
                'tested_at' => time(),
            ];
        }

        $cache_key = 'probe:' . md5(microtime(true) . '|' . wp_rand());
        $payload = [
            'token' => wp_generate_password(10, false, false),
            'tested_at' => time(),
        ];

        try {
            $stored = wp_cache_set($cache_key, $payload, self::CACHE_GROUP, 30);
            if (!$stored) {
                return [
                    'status' => 'failed',
                    'message' => 'wp_cache_set gagal menyimpan key probe.',
                    'tested_at' => time(),
                ];
            }

            $cached = wp_cache_get($cache_key, self::CACHE_GROUP, false, $found);
            if (!$found || !is_array($cached) || $cached !== $payload) {
                wp_cache_delete($cache_key, self::CACHE_GROUP);
                return [
                    'status' => 'failed',
                    'message' => 'wp_cache_get tidak mengembalikan payload probe yang sama.',
                    'tested_at' => time(),
                ];
            }

            $deleted = wp_cache_delete($cache_key, self::CACHE_GROUP);
            if (!$deleted) {
                return [
                    'status' => 'failed',
                    'message' => 'wp_cache_delete gagal menghapus key probe.',
                    'tested_at' => time(),
                ];
            }

            wp_cache_get($cache_key, self::CACHE_GROUP, false, $found_after_delete);
            if ($found_after_delete) {
                return [
                    'status' => 'failed',
                    'message' => 'Key probe masih ditemukan setelah dihapus.',
                    'tested_at' => time(),
                ];
            }

            return [
                'status' => 'passed',
                'message' => 'Round-trip wp_cache_set/get/delete berhasil.',
                'tested_at' => time(),
            ];
        } catch (Throwable $exception) {
            wp_cache_delete($cache_key, self::CACHE_GROUP);

            return [
                'status' => 'failed',
                'message' => 'Probe object cache gagal: ' . $exception->getMessage(),
                'tested_at' => time(),
            ];
        }
    }

    /**
     * @return scalar|string
     */
    private static function config_constant_summary(string $constant_name)
    {
        if (!defined($constant_name)) {
            return '';
        }

        $value = constant($constant_name);
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        $json = wp_json_encode($value);
        return is_string($json) ? $json : '';
    }

    private static function config_constant_has_non_empty_value(string $constant_name): bool
    {
        if (!defined($constant_name)) {
            return false;
        }

        $value = constant($constant_name);
        if (is_array($value)) {
            return !empty($value);
        }

        return trim((string) $value) !== '';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function acquire_object_cache_lock(string $lock_key, array $payload, int $ttl): bool
    {
        $storage_key = self::lock_storage_key($lock_key);
        $added = wp_cache_add($storage_key, $payload, self::CACHE_GROUP, $ttl);
        if ($added) {
            return true;
        }

        $existing = wp_cache_get($storage_key, self::CACHE_GROUP, false, $found);
        if (
            !$found ||
            !is_array($existing) ||
            (int) ($existing['expires_at'] ?? 0) > time()
        ) {
            return false;
        }

        wp_cache_delete($storage_key, self::CACHE_GROUP);
        return wp_cache_add($storage_key, $payload, self::CACHE_GROUP, $ttl);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function acquire_option_lock(string $lock_key, array $payload): bool
    {
        $option_key = self::option_lock_key($lock_key);
        $added = add_option($option_key, $payload, '', false);
        if ($added) {
            return true;
        }

        $existing = get_option($option_key, []);
        if (
            !is_array($existing) ||
            (int) ($existing['expires_at'] ?? 0) > time()
        ) {
            return false;
        }

        delete_option($option_key);
        return add_option($option_key, $payload, '', false);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function register_lock(string $lock_key, array $payload): void
    {
        $registry = self::load_registry();
        if (!isset($registry['locks']) || !is_array($registry['locks'])) {
            $registry['locks'] = [];
        }

        $registry['locks'][$lock_key] = [
            'lock_key' => $lock_key,
            'storage_key' => self::object_cache_active() ? self::lock_storage_key($lock_key) : self::option_lock_key($lock_key),
            'context' => is_array($payload['context'] ?? null) ? $payload['context'] : [],
            'created_at' => (int) ($payload['created_at'] ?? time()),
            'updated_at' => (int) ($payload['updated_at'] ?? time()),
            'expires_at' => (int) ($payload['expires_at'] ?? 0),
        ];

        uasort($registry['locks'], static function (array $left, array $right): int {
            return (int) ($right['updated_at'] ?? 0) <=> (int) ($left['updated_at'] ?? 0);
        });

        if (count($registry['locks']) > self::MAX_LOCK_REGISTRY) {
            $registry['locks'] = array_slice($registry['locks'], 0, self::MAX_LOCK_REGISTRY, true);
        }

        self::save_registry($registry);
    }

    private static function unregister_lock(string $lock_key): void
    {
        $registry = self::load_registry();
        if (!isset($registry['locks'][$lock_key])) {
            return;
        }

        unset($registry['locks'][$lock_key]);
        self::save_registry($registry);
    }
}
