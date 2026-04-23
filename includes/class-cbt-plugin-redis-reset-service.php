<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Exam_Preflight_Service')) {
    require_once __DIR__ . '/class-cbt-exam-preflight-service.php';
}

if (!class_exists('CBT_Exam_Availability_Auto_Warm_Service')) {
    require_once __DIR__ . '/class-cbt-exam-availability-auto-warm-service.php';
}

final class CBT_Plugin_Redis_Reset_Service
{
    private const KEY_PATTERN = 'cbt_*';
    private const KEY_PREFIX = 'cbt_';
    private const DEFAULT_HOST = '127.0.0.1';
    private const DEFAULT_PORT = 6379;
    private const DEFAULT_WP_DATABASE = 1;
    private const DEFAULT_RUNTIME_DATABASE = 2;
    private const DEFAULT_TIMEOUT = 1.5;
    private const KEY_GROUP_PREFIXES = [
        'attempt_session' => 'cbt_attempt_session:',
        'attempt_contract' => 'cbt_attempt_contract:',
        'active_attempt' => 'cbt_active_attempt:',
        'exam_delivery' => 'cbt_exam_delivery:',
        'start_attempt' => 'cbt_exam_start_attempt:',
        'submit_context' => 'cbt_submit_context:',
        'exam_availability' => 'cbt_exam_availability:',
        'profile' => 'cbt_profile:',
        'login' => 'cbt_login_auth:',
        'start_gate' => 'cbt_start_attempt_gate:',
    ];

    /**
     * @return array<string,mixed>
     */
    public static function get_plugin_diagnostics(): array
    {
        $default_prefix_counts = self::empty_prefix_counts();
        $default = [
            'redis_available' => false,
            'redis_error' => '',
            'memory_used_human' => '-',
            'memory_peak_human' => '-',
            'connected_clients' => 0,
            'memory_fragmentation_ratio' => '',
            'total_keys' => 0,
            'database_summaries' => [],
            'prefix_counts' => $default_prefix_counts,
        ];

        if (self::using_test_redis_storage()) {
            $counts = self::count_prefixed_keys_from_test_storage();

            return array_merge($default, [
                'redis_available' => true,
                'memory_used_human' => 'test-double',
                'memory_peak_human' => 'test-double',
                'total_keys' => (int) ($counts['total_keys'] ?? 0),
                'database_summaries' => [
                    [
                        'host' => 'test-double',
                        'port' => 0,
                        'database' => 0,
                        'key_count' => (int) ($counts['total_keys'] ?? 0),
                        'ok' => true,
                    ],
                ],
                'prefix_counts' => is_array($counts['prefix_counts'] ?? null) ? $counts['prefix_counts'] : $default_prefix_counts,
            ]);
        }

        if (!class_exists('Redis')) {
            return $default;
        }

        $connections = self::target_connections();
        if (empty($connections)) {
            return array_merge($default, [
                'redis_error' => 'Konfigurasi Redis CBT tidak ditemukan.',
            ]);
        }

        $total_keys = 0;
        $prefix_counts = $default_prefix_counts;
        $database_summaries = [];
        $errors = [];
        $instance_memory = [];

        foreach ($connections as $connection) {
            $database_result = self::inspect_connection_keys($connection);
            $database_summaries[] = [
                'host' => (string) ($connection['host'] ?? self::DEFAULT_HOST),
                'port' => (int) ($connection['port'] ?? self::DEFAULT_PORT),
                'database' => (int) ($connection['database'] ?? 0),
                'key_count' => (int) ($database_result['key_count'] ?? 0),
                'ok' => !empty($database_result['success']),
            ];

            if (!empty($database_result['success'])) {
                $total_keys += (int) ($database_result['key_count'] ?? 0);
                foreach ((array) ($database_result['prefix_counts'] ?? []) as $group => $count) {
                    if (!array_key_exists($group, $prefix_counts)) {
                        $prefix_counts[$group] = 0;
                    }
                    $prefix_counts[$group] += (int) $count;
                }
            } else {
                $errors[] = (string) ($database_result['message'] ?? 'Gagal memeriksa key Redis CBT.');
            }

            $instance_signature = implode('|', [
                (string) ($connection['host'] ?? self::DEFAULT_HOST),
                (string) ($connection['port'] ?? self::DEFAULT_PORT),
                (string) ($connection['password'] ?? ''),
            ]);
            if (!isset($instance_memory[$instance_signature])) {
                $instance_memory[$instance_signature] = self::inspect_instance_memory($connection);
            }
        }

        $memory_snapshot = [];
        foreach ($instance_memory as $candidate) {
            if (!empty($candidate['success'])) {
                $memory_snapshot = $candidate;
                break;
            }
        }

        $success = ($memory_snapshot !== []) || !empty(array_filter(array_map(static function ($row): bool {
            return !empty($row['ok']);
        }, $database_summaries)));

        return array_merge($default, [
            'redis_available' => $success,
            'redis_error' => !$success ? implode(' ', array_filter($errors)) : '',
            'memory_used_human' => (string) ($memory_snapshot['memory_used_human'] ?? '-'),
            'memory_peak_human' => (string) ($memory_snapshot['memory_peak_human'] ?? '-'),
            'connected_clients' => (int) ($memory_snapshot['connected_clients'] ?? 0),
            'memory_fragmentation_ratio' => (string) ($memory_snapshot['memory_fragmentation_ratio'] ?? ''),
            'total_keys' => $total_keys,
            'database_summaries' => $database_summaries,
            'prefix_counts' => $prefix_counts,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public static function reset_all_plugin_keys(): array
    {
        if (self::using_test_redis_storage()) {
            $deleted_keys = self::delete_prefixed_keys_from_test_storage();
            $deleted_options = self::reset_plugin_state_options();

            return [
                'success' => true,
                'message' => sprintf(
                    'Redis CBT berhasil dibersihkan. Key terhapus %d. State option direset %d.',
                    $deleted_keys,
                    $deleted_options
                ),
                'deleted_keys' => $deleted_keys,
                'databases' => [
                    [
                        'host' => 'test-double',
                        'port' => 0,
                        'database' => 0,
                        'deleted_keys' => $deleted_keys,
                        'ok' => true,
                    ],
                ],
                'deleted_options' => $deleted_options,
            ];
        }

        if (!class_exists('Redis')) {
            return [
                'success' => false,
                'message' => 'Ekstensi Redis tidak tersedia di environment ini.',
                'deleted_keys' => 0,
                'databases' => [],
                'deleted_options' => 0,
            ];
        }

        $connections = self::target_connections();
        if (empty($connections)) {
            return [
                'success' => false,
                'message' => 'Konfigurasi Redis CBT tidak ditemukan.',
                'deleted_keys' => 0,
                'databases' => [],
                'deleted_options' => 0,
            ];
        }

        $deleted_keys = 0;
        $database_summaries = [];
        $errors = [];

        foreach ($connections as $connection) {
            $result = self::delete_prefixed_keys_for_connection($connection);
            $deleted_keys += (int) ($result['deleted_keys'] ?? 0);
            $database_summaries[] = [
                'host' => (string) ($connection['host'] ?? self::DEFAULT_HOST),
                'port' => (int) ($connection['port'] ?? self::DEFAULT_PORT),
                'database' => (int) ($connection['database'] ?? 0),
                'deleted_keys' => (int) ($result['deleted_keys'] ?? 0),
                'ok' => !empty($result['success']),
            ];

            if (empty($result['success'])) {
                $errors[] = (string) ($result['message'] ?? 'Gagal menghapus key Redis CBT.');
            }
        }

        $deleted_options = self::reset_plugin_state_options();

        if (!empty($errors) && $deleted_keys <= 0 && $deleted_options <= 0) {
            return [
                'success' => false,
                'message' => implode(' ', array_filter($errors)),
                'deleted_keys' => 0,
                'databases' => $database_summaries,
                'deleted_options' => 0,
            ];
        }

        $message = sprintf(
            'Redis CBT berhasil dibersihkan. Key terhapus %d. State option direset %d.',
            $deleted_keys,
            $deleted_options
        );

        return [
            'success' => true,
            'message' => $message,
            'deleted_keys' => $deleted_keys,
            'databases' => $database_summaries,
            'deleted_options' => $deleted_options,
        ];
    }

    /**
     * @return array<int,array{host:string,port:int,database:int,password:string,timeout:float}>
     */
    private static function target_connections(): array
    {
        $wp_host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::DEFAULT_HOST));
        $wp_port = (int) self::constant_scalar('WP_REDIS_PORT', self::DEFAULT_PORT);
        if ($wp_port <= 0) {
            $wp_port = self::DEFAULT_PORT;
        }

        $runtime_host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($runtime_host === '') {
            $runtime_host = $wp_host !== '' ? $wp_host : self::DEFAULT_HOST;
        }

        $runtime_port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($runtime_port <= 0) {
            $runtime_port = $wp_port;
        }
        if ($runtime_port <= 0) {
            $runtime_port = self::DEFAULT_PORT;
        }

        $runtime_password = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_PASSWORD', ''));
        if ($runtime_password === '') {
            $runtime_password = trim((string) self::constant_scalar('WP_REDIS_PASSWORD', ''));
        }

        $wp_database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::DEFAULT_WP_DATABASE);
        if ($wp_database < 0) {
            $wp_database = self::DEFAULT_WP_DATABASE;
        }

        $exam_database = (int) self::constant_scalar('CBT_RUNTIME_REDIS_DB', -1);
        if ($exam_database < 0) {
            $exam_database = $wp_database;
        }

        $runtime_database = self::constant_scalar('CBT_RUNTIME_REDIS_DATABASE', null);
        if ($runtime_database === null || $runtime_database === '') {
            $runtime_database = max(0, $wp_database + 1);
        }
        $runtime_database = (int) $runtime_database;

        $login_host = (string) getenv('CBT_REDIS_HOST');
        if ($login_host === '') {
            $login_host = self::DEFAULT_HOST;
        }

        $login_port = (int) getenv('CBT_REDIS_PORT');
        if ($login_port <= 0) {
            $login_port = self::DEFAULT_PORT;
        }

        $login_database = (int) getenv('CBT_REDIS_DB');
        if ($login_database < 0) {
            $login_database = self::DEFAULT_RUNTIME_DATABASE;
        }

        $login_password = (string) getenv('CBT_REDIS_PASSWORD');

        $configs = [
            [
                'host' => $runtime_host !== '' ? $runtime_host : self::DEFAULT_HOST,
                'port' => $runtime_port,
                'database' => max(0, $exam_database),
                'password' => $runtime_password,
                'timeout' => self::DEFAULT_TIMEOUT,
            ],
            [
                'host' => $runtime_host !== '' ? $runtime_host : self::DEFAULT_HOST,
                'port' => $runtime_port,
                'database' => max(0, $runtime_database),
                'password' => $runtime_password,
                'timeout' => self::DEFAULT_TIMEOUT,
            ],
            [
                'host' => $login_host !== '' ? $login_host : self::DEFAULT_HOST,
                'port' => $login_port,
                'database' => max(0, $login_database),
                'password' => $login_password,
                'timeout' => self::DEFAULT_TIMEOUT,
            ],
        ];

        $unique_configs = [];
        foreach ($configs as $config) {
            $signature = implode('|', [
                (string) ($config['host'] ?? ''),
                (string) ($config['port'] ?? 0),
                (string) ($config['database'] ?? 0),
                (string) ($config['password'] ?? ''),
            ]);
            $unique_configs[$signature] = $config;
        }

        return array_values($unique_configs);
    }

    /**
     * @param array{host:string,port:int,database:int,password:string,timeout:float} $connection
     * @return array<string,mixed>
     */
    private static function delete_prefixed_keys_for_connection(array $connection): array
    {
        try {
            $redis = new Redis();
            $host = (string) ($connection['host'] ?? self::DEFAULT_HOST);
            $port = (int) ($connection['port'] ?? self::DEFAULT_PORT);
            $timeout = (float) ($connection['timeout'] ?? self::DEFAULT_TIMEOUT);
            if (strpos($host, '/') === 0) {
                $redis->connect($host, 0, $timeout);
            } else {
                $redis->connect($host, $port, $timeout);
            }

            $password = (string) ($connection['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($connection['database'] ?? 0);
            if ($database > 0) {
                $redis->select($database);
            }

            $deleted_keys = self::delete_prefixed_keys($redis);

            return [
                'success' => true,
                'deleted_keys' => $deleted_keys,
            ];
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Koneksi Redis gagal: ' . $throwable->getMessage(),
                'deleted_keys' => 0,
            ];
        }
    }

    /**
     * @param array{host:string,port:int,database:int,password:string,timeout:float} $connection
     * @return array<string,mixed>
     */
    private static function inspect_connection_keys(array $connection): array
    {
        try {
            $redis = self::connect_to_redis($connection);
            $counts = self::count_prefixed_keys($redis);

            return [
                'success' => true,
                'key_count' => (int) ($counts['total_keys'] ?? 0),
                'prefix_counts' => (array) ($counts['prefix_counts'] ?? []),
            ];
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Koneksi Redis gagal: ' . $throwable->getMessage(),
                'key_count' => 0,
                'prefix_counts' => self::empty_prefix_counts(),
            ];
        }
    }

    /**
     * @param array{host:string,port:int,database:int,password:string,timeout:float} $connection
     * @return array<string,mixed>
     */
    private static function inspect_instance_memory(array $connection): array
    {
        try {
            $redis = self::connect_to_redis(array_merge($connection, ['database' => 0]));
            $memory_info = $redis->info('memory');
            $clients_info = $redis->info('clients');
            if (!is_array($memory_info)) {
                $memory_info = [];
            }
            if (!is_array($clients_info)) {
                $clients_info = [];
            }

            $fragmentation = '';
            if (isset($memory_info['mem_fragmentation_ratio'])) {
                $fragmentation = is_numeric($memory_info['mem_fragmentation_ratio'])
                    ? number_format((float) $memory_info['mem_fragmentation_ratio'], 2)
                    : (string) $memory_info['mem_fragmentation_ratio'];
            }

            return [
                'success' => true,
                'memory_used_human' => (string) ($memory_info['used_memory_human'] ?? '-'),
                'memory_peak_human' => (string) ($memory_info['used_memory_peak_human'] ?? '-'),
                'connected_clients' => (int) ($clients_info['connected_clients'] ?? 0),
                'memory_fragmentation_ratio' => $fragmentation,
            ];
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Koneksi Redis gagal: ' . $throwable->getMessage(),
            ];
        }
    }

    /**
     * @param Redis $redis
     * @return array{total_keys:int,prefix_counts:array<string,int>}
     */
    private static function count_prefixed_keys(Redis $redis): array
    {
        $iterator = null;
        $total_keys = 0;
        $prefix_counts = self::empty_prefix_counts();

        do {
            $keys = $redis->scan($iterator, self::KEY_PATTERN, 500);
            if ($keys === false || empty($keys)) {
                continue;
            }

            foreach ($keys as $key) {
                if (!is_string($key) || !str_starts_with($key, self::KEY_PREFIX)) {
                    continue;
                }

                $total_keys++;
                $group = self::detect_key_group($key);
                if ($group !== '' && array_key_exists($group, $prefix_counts)) {
                    $prefix_counts[$group]++;
                }
            }
        } while ($iterator !== 0);

        return [
            'total_keys' => $total_keys,
            'prefix_counts' => $prefix_counts,
        ];
    }

    /**
     * @return array{total_keys:int,prefix_counts:array<string,int>}
     */
    private static function count_prefixed_keys_from_test_storage(): array
    {
        $total_keys = 0;
        $prefix_counts = self::empty_prefix_counts();
        foreach (array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? [])) as $key) {
            if (!is_string($key) || !str_starts_with($key, self::KEY_PREFIX)) {
                continue;
            }

            $total_keys++;
            $group = self::detect_key_group($key);
            if ($group !== '' && array_key_exists($group, $prefix_counts)) {
                $prefix_counts[$group]++;
            }
        }

        return [
            'total_keys' => $total_keys,
            'prefix_counts' => $prefix_counts,
        ];
    }

    /**
     * @return array<string,int>
     */
    private static function empty_prefix_counts(): array
    {
        $counts = [];
        foreach (array_keys(self::KEY_GROUP_PREFIXES) as $group) {
            $counts[$group] = 0;
        }

        return $counts;
    }

    private static function detect_key_group(string $key): string
    {
        foreach (self::KEY_GROUP_PREFIXES as $group => $prefix) {
            if (str_starts_with($key, $prefix)) {
                return $group;
            }
        }

        return '';
    }

    /**
     * @param array{host:string,port:int,database:int,password:string,timeout:float} $connection
     */
    private static function connect_to_redis(array $connection): Redis
    {
        $redis = new Redis();
        $host = (string) ($connection['host'] ?? self::DEFAULT_HOST);
        $port = (int) ($connection['port'] ?? self::DEFAULT_PORT);
        $timeout = (float) ($connection['timeout'] ?? self::DEFAULT_TIMEOUT);
        if (strpos($host, '/') === 0) {
            $redis->connect($host, 0, $timeout);
        } else {
            $redis->connect($host, $port, $timeout);
        }

        $password = (string) ($connection['password'] ?? '');
        if ($password !== '') {
            $redis->auth($password);
        }

        $database = (int) ($connection['database'] ?? 0);
        if ($database >= 0) {
            $redis->select($database);
        }

        return $redis;
    }

    private static function delete_prefixed_keys(Redis $redis): int
    {
        if (method_exists($redis, 'scan')) {
            return self::delete_prefixed_keys_via_scan($redis);
        }

        return self::delete_prefixed_keys_from_test_storage();
    }

    private static function delete_prefixed_keys_via_scan(Redis $redis): int
    {
        $deleted = 0;
        $iterator = null;

        do {
            $keys = $redis->scan($iterator, self::KEY_PATTERN, 500);
            if (is_array($keys) && !empty($keys)) {
                $deleted += (int) $redis->del(...$keys);
            }
        } while ($iterator !== 0);

        return $deleted;
    }

    private static function delete_prefixed_keys_from_test_storage(): int
    {
        $deleted = 0;

        if (isset($GLOBALS['cbt_test_redis_storage']) && is_array($GLOBALS['cbt_test_redis_storage'])) {
            foreach (array_keys($GLOBALS['cbt_test_redis_storage']) as $key) {
                if (is_string($key) && str_starts_with($key, self::KEY_PREFIX)) {
                    unset($GLOBALS['cbt_test_redis_storage'][$key]);
                    $deleted++;
                }
            }
        }

        if (isset($GLOBALS['cbt_test_redis_zsets']) && is_array($GLOBALS['cbt_test_redis_zsets'])) {
            foreach (array_keys($GLOBALS['cbt_test_redis_zsets']) as $key) {
                if (is_string($key) && str_starts_with($key, self::KEY_PREFIX)) {
                    unset($GLOBALS['cbt_test_redis_zsets'][$key]);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private static function reset_plugin_state_options(): int
    {
        $deleted_options = 0;

        if (class_exists('CBT_Exam_Preflight_Service')) {
            CBT_Exam_Preflight_Service::deactivate();
        }
        if (class_exists('CBT_Exam_Availability_Auto_Warm_Service')) {
            CBT_Exam_Availability_Auto_Warm_Service::deactivate();
        }
        if (class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
            CBT_Snapshot_Auto_Heal_Queue_Service::deactivate();
        }
        if (class_exists('CBT_Login_Snapshot_Freshness_Service')) {
            CBT_Login_Snapshot_Freshness_Service::deactivate();
        }
        if (class_exists('CBT_Adaptive_Load_Service')) {
            CBT_Adaptive_Load_Service::deactivate();
        }
        if (class_exists('CBT_Expired_Attempt_Finalize_Service')) {
            CBT_Expired_Attempt_Finalize_Service::deactivate();
        }
        if (class_exists('CBT_Login_Readiness_Warm_Queue_Service')) {
            CBT_Login_Readiness_Warm_Queue_Service::deactivate();
        }
        if (class_exists('CBT_Student_Cohort_Index_Service')) {
            CBT_Student_Cohort_Index_Service::deactivate();
        }

        foreach ([
            'cbt_exam_preflight_state',
            'cbt_exam_preflight_jobs',
            'cbt_exam_preflight_global_runner',
            'cbt_exam_availability_auto_warm_state',
            'cbt_exam_availability_rewarm_queue_state',
            'cbt_snapshot_auto_heal_queue_state',
            'cbt_login_snapshot_freshness_state',
            'cbt_adaptive_load_state',
            'cbt_login_readiness_warm_queue_state',
            'cbt_student_cohort_index_rebuild_state',
        ] as $option_name) {
            if (function_exists('delete_option') && delete_option($option_name)) {
                $deleted_options++;
            }
        }

        return $deleted_options;
    }

    private static function using_test_redis_storage(): bool
    {
        return isset($GLOBALS['cbt_test_redis_storage']) || isset($GLOBALS['cbt_test_redis_zsets']);
    }

    /**
     * @return scalar|null
     */
    private static function constant_scalar(string $name, $default = null)
    {
        if (!defined($name)) {
            return $default;
        }

        $value = constant($name);
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return $default;
    }
}
