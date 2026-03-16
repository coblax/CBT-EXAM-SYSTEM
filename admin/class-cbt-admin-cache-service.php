<?php

if (!defined('ABSPATH')) {
    exit;
}

final class CBT_Admin_Cache_Service
{
    private const REDIS_BOOTSTRAP_PLUGIN = 'redis-cache/redis-cache.php';
    private const REDIS_BOOTSTRAP_SLUG = 'redis-cache';
    private const REDIS_CONFIG_BLOCK_START = "/** BEGIN CBT Redis Object Cache */";
    private const REDIS_CONFIG_BLOCK_END = "/** END CBT Redis Object Cache */";

    public static function can_manage_cache(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function build_page_context(array $query): array
    {
        $notice = isset($query['cbt_msg']) ? sanitize_text_field(wp_unslash((string) $query['cbt_msg'])) : '';
        $error = isset($query['cbt_err']) ? sanitize_text_field(wp_unslash((string) $query['cbt_err'])) : '';
        $overview = CBT_Cache::get_admin_overview();
        $health = isset($overview['health']) && is_array($overview['health']) ? $overview['health'] : [];
        $namespaces = isset($overview['namespaces']) && is_array($overview['namespaces']) ? $overview['namespaces'] : [];
        $locks = isset($overview['locks']) && is_array($overview['locks']) ? $overview['locks'] : [];
        $ui_states = isset($overview['ui_states']) && is_array($overview['ui_states']) ? $overview['ui_states'] : [];
        $ttl_reference = isset($overview['ttl_reference']) && is_array($overview['ttl_reference']) ? $overview['ttl_reference'] : [];
        $redis_config = isset($health['redis_config']) && is_array($health['redis_config']) ? $health['redis_config'] : [];
        $server_probe = isset($health['server_probe']) && is_array($health['server_probe']) ? $health['server_probe'] : [];
        $probe = isset($health['probe']) && is_array($health['probe']) ? $health['probe'] : [];
        $runtime_buffer = isset($health['runtime_buffer']) && is_array($health['runtime_buffer']) ? $health['runtime_buffer'] : [];
        $runtime_buffer_config = isset($runtime_buffer['config']) && is_array($runtime_buffer['config']) ? $runtime_buffer['config'] : [];
        $runtime_buffer_probe = isset($runtime_buffer['probe']) && is_array($runtime_buffer['probe']) ? $runtime_buffer['probe'] : [];
        $readiness = (string) ($health['readiness'] ?? 'fallback');
        $readiness_meta = self::cache_readiness_meta($readiness);
        $server_probe_meta = self::cache_server_probe_meta((string) ($server_probe['status'] ?? 'skipped'));
        $probe_meta = self::cache_probe_meta((string) ($probe['status'] ?? 'skipped'));
        $runtime_probe_meta = self::cache_probe_meta((string) ($runtime_buffer_probe['status'] ?? 'skipped'));
        $namespace_prune_after = (int) CBT_Cache::namespace_prune_retention_seconds();
        $namespace_prune_label = human_time_diff(max(0, time() - $namespace_prune_after), time());
        $next_steps = self::cache_next_steps($health);
        $show_redis_rollback = self::should_render_redis_rollback_action();
        $namespace_group_meta = self::cache_namespace_group_meta();
        $namespace_group_resolver = static function (string $namespace_name): string {
            $namespace_name = trim($namespace_name);
            if ($namespace_name === '') {
                return '';
            }

            if ($namespace_name === '__global__') {
                return '__global__';
            }

            $parts = explode(':', $namespace_name, 2);
            return sanitize_key((string) ($parts[0] ?? ''));
        };
        $namespace_filter = isset($query['cbt_namespace_filter'])
            ? sanitize_text_field(wp_unslash((string) $query['cbt_namespace_filter']))
            : '';
        $namespace_filter_options = [];
        foreach ($namespaces as $namespace_entry) {
            $namespace_group = $namespace_group_resolver((string) ($namespace_entry['namespace'] ?? ''));
            if ($namespace_group === '') {
                continue;
            }
            $namespace_filter_options[$namespace_group] = $namespace_group;
        }
        $namespace_filter_options = array_values($namespace_filter_options);
        sort($namespace_filter_options, SORT_NATURAL | SORT_FLAG_CASE);
        if ($namespace_filter !== '' && !in_array($namespace_filter, $namespace_filter_options, true)) {
            $namespace_filter = '';
        }
        if ($namespace_filter !== '') {
            $namespaces = array_values(array_filter($namespaces, static function ($namespace_entry) use ($namespace_filter, $namespace_group_resolver): bool {
                return $namespace_group_resolver((string) ($namespace_entry['namespace'] ?? '')) === $namespace_filter;
            }));
        }
        $namespace_per_page = isset($query['cbt_namespace_per_page'])
            ? self::normalize_standard_list_per_page(absint(wp_unslash((string) $query['cbt_namespace_per_page'])))
            : 20;
        $namespace_current_page = isset($query['cbt_namespace_paged'])
            ? max(1, absint(wp_unslash((string) $query['cbt_namespace_paged'])))
            : 1;
        $namespace_total = count($namespaces);
        $namespace_total_all = count($namespace_filter_options);
        $namespace_total_pages = max(1, (int) ceil($namespace_total / max(1, $namespace_per_page)));
        if ($namespace_total > 0 && $namespace_current_page > $namespace_total_pages) {
            $namespace_current_page = $namespace_total_pages;
        }
        $namespace_offset = ($namespace_current_page - 1) * $namespace_per_page;
        $visible_namespaces = array_slice($namespaces, $namespace_offset, $namespace_per_page);
        $namespace_pagination_links = [];
        $show_stale_locks = isset($query['cbt_lock_show_stale'])
            ? (absint(wp_unslash((string) $query['cbt_lock_show_stale'])) === 1)
            : false;
        $lock_per_page = isset($query['cbt_lock_per_page'])
            ? self::normalize_standard_list_per_page(absint(wp_unslash((string) $query['cbt_lock_per_page'])))
            : 20;
        $lock_current_page = isset($query['cbt_lock_paged'])
            ? max(1, absint(wp_unslash((string) $query['cbt_lock_paged'])))
            : 1;
        $active_locks = [];
        $stale_locks = [];
        foreach ($locks as $lock_entry) {
            if (!empty($lock_entry['is_stale'])) {
                $stale_locks[] = $lock_entry;
                continue;
            }
            $active_locks[] = $lock_entry;
        }
        $visible_lock_source = $show_stale_locks ? $locks : $active_locks;
        $lock_total = count($visible_lock_source);
        $stale_lock_total = count($stale_locks);
        $active_lock_total = count($active_locks);
        $lock_total_pages = max(1, (int) ceil($lock_total / max(1, $lock_per_page)));
        if ($lock_total > 0 && $lock_current_page > $lock_total_pages) {
            $lock_current_page = $lock_total_pages;
        }
        $lock_offset = ($lock_current_page - 1) * $lock_per_page;
        $visible_locks = array_slice($visible_lock_source, $lock_offset, $lock_per_page);
        $lock_pagination_links = [];
        if ($namespace_total_pages > 1) {
            $namespace_pagination_args = [
                'page' => 'cbt-cache',
                'cbt_namespace_per_page' => $namespace_per_page,
                'cbt_namespace_paged' => '%#%',
                'cbt_lock_per_page' => $lock_per_page,
                'cbt_lock_show_stale' => $show_stale_locks ? 1 : 0,
            ];
            if ($namespace_filter !== '') {
                $namespace_pagination_args['cbt_namespace_filter'] = $namespace_filter;
            }
            $namespace_pagination_links = paginate_links([
                'base' => add_query_arg($namespace_pagination_args, admin_url('admin.php')),
                'format' => '',
                'current' => $namespace_current_page,
                'total' => $namespace_total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'type' => 'array',
                'end_size' => 1,
                'mid_size' => 1,
            ]);
        }
        if ($lock_total_pages > 1) {
            $lock_pagination_links = paginate_links([
                'base' => add_query_arg(
                    [
                        'page' => 'cbt-cache',
                        'cbt_namespace_per_page' => $namespace_per_page,
                        'cbt_namespace_paged' => $namespace_current_page,
                        'cbt_lock_per_page' => $lock_per_page,
                        'cbt_lock_show_stale' => $show_stale_locks ? 1 : 0,
                        'cbt_lock_paged' => '%#%',
                    ],
                    admin_url('admin.php')
                ),
                'format' => '',
                'current' => $lock_current_page,
                'total' => $lock_total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'type' => 'array',
                'end_size' => 1,
                'mid_size' => 1,
            ]);
        }

        $user_ids = [];
        foreach ($ui_states as $ui_state) {
            $user_id = (int) ($ui_state['user_id'] ?? 0);
            if ($user_id > 0) {
                $user_ids[$user_id] = $user_id;
            }
        }
        $user_labels = [];
        if (!empty($user_ids)) {
            $users = get_users([
                'include' => array_values($user_ids),
                'fields' => ['ID', 'display_name', 'user_login'],
            ]);
            foreach ((array) $users as $user) {
                if (!($user instanceof WP_User)) {
                    continue;
                }
                $user_labels[(int) $user->ID] = trim((string) $user->display_name) !== ''
                    ? (string) $user->display_name . ' (' . $user->user_login . ')'
                    : (string) $user->user_login;
            }
        }

        return compact(
            'active_lock_total',
            'error',
            'health',
            'lock_current_page',
            'lock_pagination_links',
            'lock_per_page',
            'lock_total',
            'lock_total_pages',
            'namespaces',
            'namespace_current_page',
            'namespace_filter',
            'namespace_filter_options',
            'namespace_group_meta',
            'namespace_pagination_links',
            'namespace_per_page',
            'namespace_prune_after',
            'namespace_prune_label',
            'namespace_total',
            'namespace_total_all',
            'namespace_total_pages',
            'next_steps',
            'notice',
            'overview',
            'probe',
            'probe_meta',
            'readiness',
            'readiness_meta',
            'redis_config',
            'runtime_buffer',
            'runtime_buffer_config',
            'runtime_buffer_probe',
            'runtime_probe_meta',
            'server_probe',
            'server_probe_meta',
            'show_redis_rollback',
            'show_stale_locks',
            'stale_lock_total',
            'stale_locks',
            'ttl_reference',
            'ui_states',
            'user_labels',
            'visible_locks',
            'visible_namespaces'
        );
    }

    /**
     * @return array{cache_url:string}|null
     */
    public static function get_runtime_notice_context(): ?array
    {
        if (!is_admin() || !self::can_manage_cache()) {
            return null;
        }

        $current_page = isset($_GET['page']) ? sanitize_key((string) wp_unslash((string) $_GET['page'])) : '';
        if ($current_page === 'cbt-cache') {
            return null;
        }

        $overview = CBT_Cache::get_admin_overview();
        $health = isset($overview['health']) && is_array($overview['health']) ? $overview['health'] : [];
        if ((string) ($health['readiness'] ?? 'fallback') !== 'fallback') {
            return null;
        }

        return [
            'cache_url' => admin_url('admin.php?page=cbt-cache'),
        ];
    }

    private static function normalize_standard_list_per_page(int $requested): int
    {
        $allowed = [20, 40, 60, 80, 100];
        if (in_array($requested, $allowed, true)) {
            return $requested;
        }

        return 20;
    }
    public static function cache_readiness_meta(string $readiness): array
    {
        switch ($readiness) {
            case 'ready':
                return [
                    'label' => 'Ready',
                    'accent' => '#135e36',
                    'background' => '#edfaef',
                ];
            case 'partial':
                return [
                    'label' => 'Partial',
                    'accent' => '#8a4b00',
                    'background' => '#fff5e6',
                ];
            default:
                return [
                    'label' => 'Fallback',
                    'accent' => '#8a2424',
                    'background' => '#fff1f1',
                ];
        }
    }

    public static function cache_probe_meta(string $status): array
    {
        switch ($status) {
            case 'passed':
                return [
                    'label' => 'Passed',
                    'accent' => '#135e36',
                    'background' => '#edfaef',
                ];
            case 'failed':
                return [
                    'label' => 'Failed',
                    'accent' => '#8a2424',
                    'background' => '#fff1f1',
                ];
            default:
                return [
                    'label' => 'Skipped',
                    'accent' => '#6b7280',
                    'background' => '#f3f4f6',
                ];
        }
    }

    public static function cache_server_probe_meta(string $status): array
    {
        switch ($status) {
            case 'reachable':
                return [
                    'label' => 'Reachable',
                    'accent' => '#135e36',
                    'background' => '#edfaef',
                ];
            case 'unreachable':
                return [
                    'label' => 'Unreachable',
                    'accent' => '#8a2424',
                    'background' => '#fff1f1',
                ];
            default:
                return [
                    'label' => 'Skipped',
                    'accent' => '#6b7280',
                    'background' => '#f3f4f6',
                ];
        }
    }

    public static function cache_next_steps(array $health): array
    {
        $steps = [];
        $redis_config = isset($health['redis_config']) && is_array($health['redis_config']) ? $health['redis_config'] : [];
        $server_probe = isset($health['server_probe']) && is_array($health['server_probe']) ? $health['server_probe'] : [];
        $probe = isset($health['probe']) && is_array($health['probe']) ? $health['probe'] : [];
        $runtime_buffer = isset($health['runtime_buffer']) && is_array($health['runtime_buffer']) ? $health['runtime_buffer'] : [];

        if (empty($health['wp_cache_enabled'])) {
            $steps[] = "Tambahkan define('WP_CACHE', true); di wp-config.php.";
        }

        if ((string) ($server_probe['status'] ?? '') !== 'reachable') {
            $steps[] = 'Install/jalankan service Redis, lalu pastikan host dan port Redis dapat dijangkau dari server WordPress.';
        }

        if (empty($health['object_cache_dropin_present'])) {
            $steps[] = 'Install dan aktifkan plugin/drop-in Redis Object Cache WordPress sampai wp-content/object-cache.php tersedia.';
        }

        if (empty($redis_config['host']) || empty($redis_config['port'])) {
            $steps[] = "Tambahkan WP_REDIS_HOST dan WP_REDIS_PORT di wp-config.php sesuai host Redis yang dipakai.";
        }

        if (trim((string) ($redis_config['database'] ?? '')) === '') {
            $steps[] = 'Tetapkan WP_REDIS_DATABASE khusus agar key CBT tidak bercampur dengan aplikasi WordPress lain.';
        }

        if (empty($redis_config['prefix'])) {
            $steps[] = 'Tetapkan WP_REDIS_PREFIX yang unik per site untuk mencegah collision key.';
        }

        if (!empty($redis_config['disabled'])) {
            $steps[] = 'Pastikan WP_REDIS_DISABLED tidak bernilai true pada environment produksi.';
        }

        if (!empty($health['object_cache_active']) && (string) ($health['backend_hint'] ?? '') !== 'redis') {
            $steps[] = 'Pastikan object cache drop-in yang aktif benar-benar memakai backend Redis, bukan object cache persistent lain.';
        }

        if ((string) ($server_probe['status'] ?? '') === 'unreachable') {
            $steps[] = 'Periksa service Redis, firewall, dan endpoint pada WP_REDIS_HOST/WP_REDIS_PORT sampai status Redis Server menjadi Reachable.';
        }

        if ((string) ($probe['status'] ?? '') === 'failed') {
            $steps[] = 'Periksa koneksi Redis, kredensial, dan status drop-in sampai probe CBT Cache lulus.';
        }

        if (!empty($runtime_buffer['enabled']) && empty($runtime_buffer['ready'])) {
            $steps[] = 'Pastikan runtime Redis CBT memakai phpredis, database terpisah, dan endpoint CBT runtime dapat terhubung agar buffering jawaban aktif.';
        }

        $steps[] = 'Verifikasi ulang pada halaman CBT Cache sampai Readiness = ready, Backend Hint = redis, dan Probe Status = passed.';

        return array_values(array_unique($steps));
    }

    public static function cache_readiness_summary(array $health): string
    {
        $readiness = (string) ($health['readiness'] ?? 'fallback');
        $server_probe = isset($health['server_probe']) && is_array($health['server_probe']) ? $health['server_probe'] : [];
        $runtime_buffer = isset($health['runtime_buffer']) && is_array($health['runtime_buffer']) ? $health['runtime_buffer'] : [];

        if ($readiness === 'ready') {
            if (!empty($runtime_buffer['enabled']) && empty($runtime_buffer['ready'])) {
                return 'Redis object cache WordPress aktif, tetapi runtime buffer CBT untuk jawaban belum siap. Cache baca sudah siap, namun jalur batch write masih fallback ke database.';
            }
            return 'Redis object cache WordPress aktif dan probe round-trip berhasil. Plugin CBT sekarang memakai persistent object cache lintas request.';
        }

        if ($readiness === 'partial') {
            if ((string) ($server_probe['status'] ?? '') === 'unreachable') {
                return 'Sebagian konfigurasi Redis sudah terdeteksi, tetapi server Redis belum dapat dijangkau dari WordPress. Perbaiki endpoint atau jalankan service Redis terlebih dahulu.';
            }
            return 'Sebagian konfigurasi Redis/object cache sudah terdeteksi, tetapi runtime Redis belum siap penuh untuk CBT. Selesaikan checklist di bawah sampai status menjadi Ready.';
        }

        return 'WordPress masih menjalankan CBT pada mode transient fallback. Mode ini tetap didukung, tetapi bukan pilihan yang direkomendasikan untuk beban ujian serentak dengan trafik tinggi.';
    }

    public static function cache_boolean_label(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    public static function cache_scalar_label($value): string
    {
        $label = trim((string) $value);
        return $label !== '' ? $label : '-';
    }

    public static function bootstrap_redis_wordpress(): array
    {
        $messages = [];
        $errors = [];
        $config = self::redis_bootstrap_defaults();

        $config_result = self::ensure_redis_wp_config_block($config);
        if (is_wp_error($config_result)) {
            return [
                'message' => null,
                'error' => $config_result->get_error_message(),
            ];
        }

        if (!empty($config_result['changed'])) {
            $messages[] = 'Konfigurasi Redis berhasil ditambahkan ke wp-config.php.';
        } else {
            $messages[] = 'Blok konfigurasi Redis di wp-config.php sudah tersedia.';
        }

        self::prime_runtime_redis_constants($config);

        $server_probe = CBT_Cache::probe_redis_server($config);
        if ((string) ($server_probe['status'] ?? '') !== 'reachable') {
            $errors[] = 'Server Redis belum bisa dijangkau pada endpoint ' . self::cache_scalar_label($server_probe['endpoint'] ?? '-') . '. Jalankan service Redis lalu klik bootstrap lagi.';

            return [
                'message' => implode(' ', $messages),
                'error' => implode(' ', $errors),
            ];
        }

        $plugin_result = self::ensure_redis_object_cache_plugin();
        if (is_wp_error($plugin_result)) {
            $errors[] = $plugin_result->get_error_message();

            return [
                'message' => implode(' ', $messages),
                'error' => implode(' ', $errors),
            ];
        }

        if (!empty($plugin_result['message'])) {
            $messages[] = (string) $plugin_result['message'];
        }

        $dropin_result = self::enable_redis_object_cache_dropin();
        if (is_wp_error($dropin_result)) {
            $errors[] = $dropin_result->get_error_message();
        } elseif (!empty($dropin_result['message'])) {
            $messages[] = (string) $dropin_result['message'];
        }

        if (empty($errors)) {
            $messages[] = 'Bootstrap Redis WordPress selesai. Halaman CBT Cache akan memverifikasi status ready pada request berikutnya.';
        }

        return [
            'message' => !empty($messages) ? implode(' ', array_values(array_unique($messages))) : null,
            'error' => !empty($errors) ? implode(' ', array_values(array_unique($errors))) : null,
        ];
    }

    public static function should_render_redis_rollback_action(): bool
    {
        if (self::redis_bootstrap_marker_present()) {
            return true;
        }

        if (self::is_redis_object_cache_plugin_active()) {
            return true;
        }

        $dropin_state = self::redis_object_cache_dropin_state();
        return !empty($dropin_state['exists']) && !empty($dropin_state['valid']);
    }

    public static function rollback_redis_wordpress(): array
    {
        $messages = [];
        $errors = [];
        $dropin_state = self::redis_object_cache_dropin_state();

        if (!empty($dropin_state['exists']) && empty($dropin_state['valid'])) {
            return [
                'message' => null,
                'error' => 'Ditemukan object-cache.php yang bukan drop-in Redis Object Cache yang dikenali CBT. Rollback dibatalkan agar tidak menghapus drop-in milik plugin lain.',
            ];
        }

        $dropin_result = self::disable_redis_object_cache_dropin();
        if (is_wp_error($dropin_result)) {
            return [
                'message' => null,
                'error' => $dropin_result->get_error_message(),
            ];
        }
        if (!empty($dropin_result['message'])) {
            $messages[] = (string) $dropin_result['message'];
        }

        $plugin_result = self::deactivate_redis_object_cache_plugin();
        if (is_wp_error($plugin_result)) {
            $errors[] = $plugin_result->get_error_message();
        } elseif (!empty($plugin_result['message'])) {
            $messages[] = (string) $plugin_result['message'];
        }

        $config_result = self::remove_redis_wp_config_block();
        if (is_wp_error($config_result)) {
            $errors[] = $config_result->get_error_message();
        } elseif (!empty($config_result['message'])) {
            $messages[] = (string) $config_result['message'];
        }

        if (empty($errors)) {
            $messages[] = 'Rollback Redis dari sisi WordPress selesai. CBT akan kembali memakai jalur fallback/transient jika object cache lain tidak aktif.';
        }

        return [
            'message' => !empty($messages) ? implode(' ', array_values(array_unique($messages))) : null,
            'error' => !empty($errors) ? implode(' ', array_values(array_unique($errors))) : null,
        ];
    }

    private static function redis_bootstrap_marker_present(): bool
    {
        $wp_config_path = self::wp_config_path();
        if ($wp_config_path === '' || !is_readable($wp_config_path)) {
            return false;
        }

        $contents = file_get_contents($wp_config_path);
        if (!is_string($contents) || $contents === '') {
            return false;
        }

        return strpos($contents, self::REDIS_CONFIG_BLOCK_START) !== false
            || strpos($contents, self::REDIS_CONFIG_BLOCK_END) !== false;
    }

    private static function is_redis_object_cache_plugin_active(): bool
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        return is_plugin_active(self::REDIS_BOOTSTRAP_PLUGIN)
            || (is_multisite() && is_plugin_active_for_network(self::REDIS_BOOTSTRAP_PLUGIN));
    }

    private static function redis_object_cache_dropin_state(): array
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $path = WP_CONTENT_DIR . '/object-cache.php';
        $state = [
            'path' => $path,
            'exists' => file_exists($path),
            'valid' => false,
            'name' => '',
            'plugin_uri' => '',
        ];

        if (!$state['exists']) {
            return $state;
        }

        $plugin_data = get_plugin_data($path, false, false);
        $state['name'] = trim((string) ($plugin_data['Name'] ?? ''));
        $state['plugin_uri'] = trim((string) ($plugin_data['PluginURI'] ?? ''));

        if ($state['plugin_uri'] === 'https://wordpress.org/plugins/redis-cache/') {
            $state['valid'] = true;
            return $state;
        }

        $source = WP_PLUGIN_DIR . '/redis-cache/includes/object-cache.php';
        if (is_readable($source) && is_readable($path)) {
            $source_hash = md5_file($source);
            $target_hash = md5_file($path);
            if (is_string($source_hash) && is_string($target_hash) && $source_hash !== '' && hash_equals($source_hash, $target_hash)) {
                $state['valid'] = true;
                return $state;
            }
        }

        $contents = file_get_contents($path, false, null, 0, 4096);
        if (is_string($contents) && strpos($contents, 'Redis Object Cache Drop-In') !== false) {
            $state['valid'] = true;
        }

        return $state;
    }

    private static function disable_redis_object_cache_dropin()
    {
        $dropin_state = self::redis_object_cache_dropin_state();
        $path = (string) ($dropin_state['path'] ?? (WP_CONTENT_DIR . '/object-cache.php'));

        if (empty($dropin_state['exists'])) {
            return [
                'changed' => 0,
                'message' => 'Drop-in Redis object cache sudah tidak ada di wp-content/object-cache.php.',
            ];
        }

        if (empty($dropin_state['valid'])) {
            return new WP_Error('redis_dropin_foreign_rollback', 'object-cache.php yang aktif bukan drop-in Redis Object Cache yang dikenali CBT. Hapus manual jika memang ingin membatalkan object cache tersebut.');
        }

        $deleted = false;
        if (is_writable($path)) {
            $deleted = @unlink($path);
        }

        if (!$deleted && file_exists($path)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
            global $wp_filesystem;
            if (!is_object($wp_filesystem)) {
                return new WP_Error('redis_dropin_delete_fs', 'Filesystem WordPress tidak bisa diinisialisasi untuk menghapus wp-content/object-cache.php.');
            }

            $deleted = (bool) $wp_filesystem->delete($path, false, 'f');
        }

        if (!$deleted && file_exists($path)) {
            return new WP_Error('redis_dropin_delete', 'Gagal menghapus wp-content/object-cache.php. Periksa permission filesystem WordPress.');
        }

        return [
            'changed' => 1,
            'message' => 'Drop-in Redis object cache berhasil dihapus dari wp-content/object-cache.php.',
        ];
    }

    private static function deactivate_redis_object_cache_plugin()
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_file = self::REDIS_BOOTSTRAP_PLUGIN;
        $is_network_active = is_multisite() && is_plugin_active_for_network($plugin_file);
        $is_active = is_plugin_active($plugin_file) || $is_network_active;

        if (!$is_active) {
            return [
                'changed' => 0,
                'message' => 'Plugin Redis Object Cache sudah nonaktif.',
            ];
        }

        if ($is_network_active) {
            if (!current_user_can('manage_network_plugins')) {
                return new WP_Error('redis_plugin_deactivate_cap', 'Plugin Redis Object Cache aktif network-wide. Nonaktifkan dari Network Admin atau gunakan akun dengan izin manage_network_plugins.');
            }
        } elseif (!current_user_can('activate_plugins')) {
            return new WP_Error('redis_plugin_deactivate_cap', 'User saat ini tidak punya izin untuk menonaktifkan plugin Redis Object Cache.');
        }

        deactivate_plugins($plugin_file, false, $is_network_active);

        if (is_plugin_active($plugin_file) || (is_multisite() && is_plugin_active_for_network($plugin_file))) {
            return new WP_Error('redis_plugin_deactivate', 'Plugin Redis Object Cache gagal dinonaktifkan.');
        }

        return [
            'changed' => 1,
            'message' => 'Plugin Redis Object Cache berhasil dinonaktifkan.',
        ];
    }

    private static function remove_redis_wp_config_block()
    {
        $wp_config_path = self::wp_config_path();
        if ($wp_config_path === '') {
            return new WP_Error('redis_wp_config_path', 'wp-config.php tidak ditemukan.');
        }

        if (!is_readable($wp_config_path) || !is_writable($wp_config_path)) {
            return new WP_Error('redis_wp_config_perm', 'wp-config.php tidak bisa dibaca/ditulis oleh proses WordPress.');
        }

        $contents = file_get_contents($wp_config_path);
        if (!is_string($contents)) {
            return new WP_Error('redis_wp_config_read', 'Isi wp-config.php tidak dapat dibaca.');
        }

        $has_start = strpos($contents, self::REDIS_CONFIG_BLOCK_START) !== false;
        $has_end = strpos($contents, self::REDIS_CONFIG_BLOCK_END) !== false;

        if (!$has_start && !$has_end) {
            return [
                'changed' => 0,
                'message' => 'Blok konfigurasi Redis di wp-config.php sudah tidak ada.',
            ];
        }

        if (!$has_start || !$has_end) {
            return new WP_Error('redis_wp_config_inconsistent', 'Marker konfigurasi Redis di wp-config.php tidak lengkap. Periksa file tersebut secara manual sebelum menjalankan rollback lagi.');
        }

        $pattern = '/\R?' . preg_quote(self::REDIS_CONFIG_BLOCK_START, '/') . '.*?' . preg_quote(self::REDIS_CONFIG_BLOCK_END, '/') . '\R*/s';
        $updated = preg_replace($pattern, PHP_EOL, $contents, 1);
        if (!is_string($updated)) {
            return new WP_Error('redis_wp_config_remove', 'Blok konfigurasi Redis di wp-config.php tidak bisa dihapus.');
        }

        if ($updated !== $contents) {
            $normalized = preg_replace("/(\r\n|\r|\n){3,}/", PHP_EOL . PHP_EOL, $updated);
            if (is_string($normalized)) {
                $updated = $normalized;
            }

            if (file_put_contents($wp_config_path, $updated) === false) {
                return new WP_Error('redis_wp_config_write', 'Gagal menyimpan perubahan ke wp-config.php.');
            }

            return [
                'changed' => 1,
                'message' => 'Blok konfigurasi Redis berhasil dihapus dari wp-config.php.',
            ];
        }

        return [
            'changed' => 0,
            'message' => 'Blok konfigurasi Redis di wp-config.php sudah tidak ada.',
        ];
    }

    private static function ensure_redis_object_cache_plugin()
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_file = self::REDIS_BOOTSTRAP_PLUGIN;
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

        if (!file_exists($plugin_path)) {
            if (!current_user_can('install_plugins')) {
                return new WP_Error('redis_plugin_missing', 'Plugin Redis Object Cache belum terpasang dan user saat ini tidak punya izin install plugin.');
            }

            if (get_filesystem_method() !== 'direct') {
                return new WP_Error('redis_plugin_fs', 'Install plugin Redis Object Cache otomatis membutuhkan filesystem WordPress mode direct atau plugin redis-cache yang sudah terpasang di server.');
            }

            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

            $api = plugins_api('plugin_information', [
                'slug' => self::REDIS_BOOTSTRAP_SLUG,
                'fields' => [
                    'sections' => false,
                    'icons' => false,
                    'banners' => false,
                ],
            ]);
            if (is_wp_error($api) || empty($api->download_link)) {
                return new WP_Error('redis_plugin_api', 'Gagal mengambil paket plugin Redis Object Cache dari WordPress.org.');
            }

            ob_start();
            $skin = new Automatic_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            $installed = $upgrader->install((string) $api->download_link);
            ob_end_clean();

            if (is_wp_error($installed) || !$installed) {
                return new WP_Error('redis_plugin_install', 'Plugin Redis Object Cache tidak bisa diinstall otomatis. Periksa permission filesystem WordPress.');
            }

            $plugin_file = $upgrader->plugin_info() ?: $plugin_file;
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        }

        if (!file_exists($plugin_path)) {
            return new WP_Error('redis_plugin_not_found', 'File plugin Redis Object Cache tidak ditemukan setelah proses bootstrap.');
        }

        if (!is_plugin_active($plugin_file)) {
            if (!current_user_can('activate_plugin', $plugin_file)) {
                return new WP_Error('redis_plugin_activate_cap', 'User saat ini tidak punya izin untuk mengaktifkan plugin Redis Object Cache.');
            }

            ob_start();
            $activation = activate_plugin($plugin_file, '', is_network_admin(), false);
            ob_end_clean();

            if (is_wp_error($activation)) {
                return new WP_Error('redis_plugin_activate', 'Plugin Redis Object Cache gagal diaktifkan: ' . $activation->get_error_message());
            }

            return [
                'message' => 'Plugin Redis Object Cache berhasil diinstall dan diaktifkan.',
            ];
        }

        return [
            'message' => 'Plugin Redis Object Cache sudah aktif.',
        ];
    }

    private static function enable_redis_object_cache_dropin()
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $plugin_file = self::REDIS_BOOTSTRAP_PLUGIN;
        if (!is_plugin_active($plugin_file)) {
            return new WP_Error('redis_dropin_inactive', 'Plugin Redis Object Cache belum aktif, sehingga drop-in belum bisa di-enable.');
        }

        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        if (file_exists($plugin_path) && !function_exists('redis_object_cache')) {
            require_once $plugin_path;
        }

        if (!function_exists('redis_object_cache')) {
            return new WP_Error('redis_dropin_runtime', 'Runtime plugin Redis Object Cache tidak tersedia untuk meng-enable drop-in.');
        }

        $redis_plugin = redis_object_cache();
        if (is_object($redis_plugin) && method_exists($redis_plugin, 'object_cache_dropin_exists') && method_exists($redis_plugin, 'validate_object_cache_dropin')) {
            $dropin_exists = (bool) $redis_plugin->object_cache_dropin_exists();
            $dropin_valid = (bool) $redis_plugin->validate_object_cache_dropin();

            if ($dropin_exists && $dropin_valid) {
                return [
                    'message' => 'Drop-in Redis object cache sudah aktif.',
                ];
            }

            if ($dropin_exists && !$dropin_valid) {
                return new WP_Error('redis_dropin_foreign', 'Ditemukan object-cache.php milik plugin lain. Hapus atau ganti drop-in tersebut terlebih dahulu sebelum bootstrap Redis.');
            }

            if (method_exists($redis_plugin, 'test_filesystem_writing')) {
                $fs_test = $redis_plugin->test_filesystem_writing();
                if (is_wp_error($fs_test)) {
                    return new WP_Error('redis_dropin_fs', 'WordPress tidak bisa menulis object-cache.php: ' . $fs_test->get_error_message());
                }
            }
        }

        WP_Filesystem();
        global $wp_filesystem;
        if (!is_object($wp_filesystem)) {
            return new WP_Error('redis_dropin_fs_init', 'Filesystem WordPress tidak bisa diinisialisasi untuk menyalin object-cache.php.');
        }

        if (!defined('WP_REDIS_PLUGIN_PATH')) {
            return new WP_Error('redis_dropin_path', 'Path plugin Redis Object Cache tidak tersedia.');
        }

        $source = WP_REDIS_PLUGIN_PATH . '/includes/object-cache.php';
        $target = WP_CONTENT_DIR . '/object-cache.php';
        if (!file_exists($source)) {
            return new WP_Error('redis_dropin_source', 'File sumber object-cache.php milik plugin Redis tidak ditemukan.');
        }

        $copied = $wp_filesystem->copy($source, $target, true, FS_CHMOD_FILE);
        if (!$copied) {
            return new WP_Error('redis_dropin_copy', 'Gagal menyalin object-cache.php ke wp-content. Periksa permission filesystem WordPress.');
        }

        return [
            'message' => 'Drop-in Redis object cache berhasil di-enable.',
        ];
    }

    private static function ensure_redis_wp_config_block(array $config)
    {
        $wp_config_path = self::wp_config_path();
        if ($wp_config_path === '') {
            return new WP_Error('redis_wp_config_path', 'wp-config.php tidak ditemukan.');
        }

        if (!is_readable($wp_config_path) || !is_writable($wp_config_path)) {
            return new WP_Error('redis_wp_config_perm', 'wp-config.php tidak bisa dibaca/ditulis oleh proses WordPress.');
        }

        $contents = file_get_contents($wp_config_path);
        if (!is_string($contents) || $contents === '') {
            return new WP_Error('redis_wp_config_read', 'Isi wp-config.php tidak dapat dibaca.');
        }

        $block = self::redis_wp_config_block($config);
        $changed = false;

        if (strpos($contents, self::REDIS_CONFIG_BLOCK_START) !== false && strpos($contents, self::REDIS_CONFIG_BLOCK_END) !== false) {
            $pattern = '/' . preg_quote(self::REDIS_CONFIG_BLOCK_START, '/') . '.*?' . preg_quote(self::REDIS_CONFIG_BLOCK_END, '/') . '\R*/s';
            $updated = preg_replace($pattern, $block . PHP_EOL . PHP_EOL, $contents, 1);
            if (!is_string($updated)) {
                return new WP_Error('redis_wp_config_replace', 'Blok konfigurasi Redis di wp-config.php tidak bisa diperbarui.');
            }
            $changed = ($updated !== $contents);
            $contents = $updated;
        } else {
            $needle = "/* That's all, stop editing! Happy publishing. */";
            if (strpos($contents, $needle) === false) {
                return new WP_Error('redis_wp_config_marker', 'Marker stop editing pada wp-config.php tidak ditemukan.');
            }

            $contents = str_replace($needle, $block . PHP_EOL . PHP_EOL . $needle, $contents, $replace_count);
            if ($replace_count < 1) {
                return new WP_Error('redis_wp_config_insert', 'Blok Redis tidak bisa disisipkan ke wp-config.php.');
            }
            $changed = true;
        }

        if ($changed && file_put_contents($wp_config_path, $contents) === false) {
            return new WP_Error('redis_wp_config_write', 'Gagal menyimpan perubahan ke wp-config.php.');
        }

        return [
            'changed' => $changed ? 1 : 0,
        ];
    }

    private static function redis_wp_config_block(array $config): string
    {
        $host = var_export((string) ($config['host'] ?? '127.0.0.1'), true);
        $port = (int) ($config['port'] ?? 6379);
        $database = (int) ($config['database'] ?? 1);
        $prefix = var_export((string) ($config['prefix'] ?? self::default_redis_prefix()), true);

        return implode(PHP_EOL, [
            self::REDIS_CONFIG_BLOCK_START,
            "if ( ! defined( 'WP_CACHE' ) ) {",
            "    define( 'WP_CACHE', true );",
            "}",
            "if ( ! defined( 'WP_REDIS_HOST' ) ) {",
            "    define( 'WP_REDIS_HOST', {$host} );",
            "}",
            "if ( ! defined( 'WP_REDIS_PORT' ) ) {",
            "    define( 'WP_REDIS_PORT', {$port} );",
            "}",
            "if ( ! defined( 'WP_REDIS_DATABASE' ) ) {",
            "    define( 'WP_REDIS_DATABASE', {$database} );",
            "}",
            "if ( ! defined( 'WP_REDIS_PREFIX' ) ) {",
            "    define( 'WP_REDIS_PREFIX', {$prefix} );",
            "}",
            self::REDIS_CONFIG_BLOCK_END,
        ]);
    }

    private static function redis_bootstrap_defaults(): array
    {
        return [
            'host' => defined('WP_REDIS_HOST') ? (string) constant('WP_REDIS_HOST') : '127.0.0.1',
            'port' => defined('WP_REDIS_PORT') ? (int) constant('WP_REDIS_PORT') : 6379,
            'database' => defined('WP_REDIS_DATABASE') ? (int) constant('WP_REDIS_DATABASE') : 1,
            'prefix' => defined('WP_REDIS_PREFIX') && trim((string) constant('WP_REDIS_PREFIX')) !== ''
                ? (string) constant('WP_REDIS_PREFIX')
                : self::default_redis_prefix(),
        ];
    }

    private static function default_redis_prefix(): string
    {
        global $table_prefix;

        $host = sanitize_key((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        if ($host === '') {
            $host = 'wordpress';
        }

        $table = preg_replace('/[^a-z0-9_]/i', '', (string) $table_prefix);
        if (!is_string($table) || $table === '') {
            $table = 'wp_';
        }

        return strtolower($host . ':' . $table . 'cbt:');
    }

    private static function prime_runtime_redis_constants(array $config): void
    {
        foreach ([
            'WP_CACHE' => true,
            'WP_REDIS_HOST' => (string) ($config['host'] ?? '127.0.0.1'),
            'WP_REDIS_PORT' => (int) ($config['port'] ?? 6379),
            'WP_REDIS_DATABASE' => (int) ($config['database'] ?? 1),
            'WP_REDIS_PREFIX' => (string) ($config['prefix'] ?? self::default_redis_prefix()),
        ] as $constant => $value) {
            if (!defined($constant)) {
                define($constant, $value);
            }
        }
    }

    private static function wp_config_path(): string
    {
        if (file_exists(ABSPATH . 'wp-config.php')) {
            return ABSPATH . 'wp-config.php';
        }

        $parent = dirname(ABSPATH) . '/wp-config.php';
        return file_exists($parent) ? $parent : '';
    }

    public static function cache_namespace_group_meta(): array
    {
        return [
            '__global__' => [
                'label' => '__global__ | versi global semua cache CBT',
                'description' => 'Namespace global untuk versi induk semua cache CBT. Jika ini berubah, hampir semua cache CBT akan ikut dibangun ulang pada request berikutnya.',
            ],
            'attempt' => [
                'label' => 'attempt | cache per attempt ujian',
                'description' => 'Cache yang terkait satu attempt ujian tertentu, misalnya payload hasil, state attempt, dan data yang perlu sinkron per peserta per attempt.',
            ],
            'catalog' => [
                'label' => 'catalog | daftar exam, mapel, token global',
                'description' => 'Cache katalog global yang dipakai lintas user, seperti daftar exam, subject, dan metadata global. Ini untuk listing/metadata umum, bukan payload isi soal.',
            ],
            'exam' => [
                'label' => 'exam | cache spesifik satu exam',
                'description' => 'Cache yang hanya berlaku untuk satu exam, termasuk payload soal statis saat frontend/backend ambil soal, setting exam, dan data turunan yang scope-nya satu exam.',
            ],
            'ui_state' => [
                'label' => 'ui_state | preferensi UI dan resume ujian',
                'description' => 'Cache untuk state UI frontend, seperti preferensi tampilan, posisi soal terakhir, dan tanda ragu-ragu yang disimpan lintas browser.',
            ],
            'user' => [
                'label' => 'user | cache spesifik satu user',
                'description' => 'Cache yang terkait satu user tertentu, misalnya daftar exam sesuai hak akses user, profil ringkas, atau state yang scope-nya per akun.',
            ],
        ];
    }
}
