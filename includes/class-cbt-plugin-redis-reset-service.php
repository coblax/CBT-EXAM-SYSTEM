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
    private const RESET_JOB_TRANSIENT_PREFIX = 'cbt_redis_reset_job_';
    private const RESET_JOB_ACTIVE_TOKEN_TRANSIENT = 'cbt_redis_reset_job_active_token';
    private const RESET_JOB_TTL = 1800;
    private const RESET_JOB_CHUNK_SIZE = 500;
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
     * @return array<string,mixed>
     */
    public static function start_reset_job(string $source = 'admin'): array
    {
        $active_token = sanitize_key((string) get_transient(self::RESET_JOB_ACTIVE_TOKEN_TRANSIENT));
        if ($active_token !== '') {
            $active_state = self::get_reset_job_state($active_token);
            if (is_array($active_state) && !self::is_reset_job_terminal($active_state)) {
                return [
                    'success' => true,
                    'message' => 'Reset Redis CBT sedang berjalan. Progress dilanjutkan dari sesi aktif.',
                    'state' => $active_state,
                ];
            }

            delete_transient(self::RESET_JOB_ACTIVE_TOKEN_TRANSIENT);
        }

        $token = strtolower((string) wp_generate_password(24, false, false));
        $connections = self::using_test_redis_storage() ? [] : self::target_connections();
        if (!self::using_test_redis_storage() && (!class_exists('Redis') || empty($connections))) {
            return [
                'success' => false,
                'message' => !class_exists('Redis')
                    ? 'Ekstensi Redis tidak tersedia di environment ini.'
                    : 'Konfigurasi Redis CBT tidak ditemukan.',
                'state' => self::build_reset_job_state([
                    'token' => $token,
                    'status' => 'failed',
                    'last_message' => 'Reset Redis CBT gagal dimulai.',
                ]),
            ];
        }

        $test_keys = self::using_test_redis_storage() ? self::collect_prefixed_test_storage_keys() : [];
        $state = self::build_reset_job_state([
            'token' => $token,
            'status' => 'active',
            'active' => true,
            'source' => sanitize_key($source) !== '' ? sanitize_key($source) : 'admin',
            'started_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'connection_total' => self::using_test_redis_storage() ? 1 : count($connections),
            'test_keys' => $test_keys,
            'total_keys' => count($test_keys),
            'last_message' => 'Reset Redis CBT dimulai.',
        ]);
        self::save_reset_job_state($state);
        set_transient(self::RESET_JOB_ACTIVE_TOKEN_TRANSIENT, (string) ($state['token'] ?? $token), self::RESET_JOB_TTL);

        return [
            'success' => true,
            'message' => 'Reset Redis CBT dimulai.',
            'state' => $state,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function tick_reset_job(string $token): array
    {
        $state = self::get_reset_job_state($token);
        if (!is_array($state)) {
            return [
                'success' => false,
                'message' => 'Sesi reset Redis CBT tidak ditemukan atau sudah berakhir.',
                'state' => self::build_reset_job_state([
                    'token' => $token,
                    'status' => 'failed',
                    'last_message' => 'Sesi reset Redis CBT tidak ditemukan atau sudah berakhir.',
                ]),
            ];
        }

        if (self::is_reset_job_terminal($state)) {
            return [
                'success' => true,
                'message' => (string) ($state['last_message'] ?? 'Reset Redis CBT selesai.'),
                'state' => $state,
            ];
        }

        if (self::using_test_redis_storage()) {
            $state = self::tick_test_reset_job($state);
        } else {
            $state = self::tick_redis_reset_job($state);
        }

        if (self::is_reset_job_terminal($state)) {
            self::clear_reset_job_state((string) ($state['token'] ?? $token));
        } else {
            self::save_reset_job_state($state);
        }

        return [
            'success' => sanitize_key((string) ($state['status'] ?? 'active')) !== 'failed',
            'message' => (string) ($state['last_message'] ?? 'Reset Redis CBT berjalan.'),
            'state' => $state,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_reset_job_state(string $token): ?array
    {
        $token = sanitize_key($token);
        if ($token === '') {
            return null;
        }

        $state = get_transient(self::RESET_JOB_TRANSIENT_PREFIX . $token);
        return is_array($state) ? self::build_reset_job_state($state) : null;
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    public static function build_reset_job_response(array $state): array
    {
        $state = self::build_reset_job_state($state);
        $status = sanitize_key((string) ($state['status'] ?? 'active'));
        $complete = self::is_reset_job_terminal($state);
        $connection_total = max(1, (int) ($state['connection_total'] ?? 1));
        $connection_index = min($connection_total, max(0, (int) ($state['connection_index'] ?? 0)));
        $test_keys = array_values(array_filter(array_map('strval', (array) ($state['test_keys'] ?? []))));
        if (self::using_test_redis_storage()) {
            $total_keys = max(0, (int) ($state['total_keys'] ?? (count($test_keys) + (int) ($state['deleted_keys'] ?? 0))));
            $processed = $total_keys > 0 ? ($total_keys - count($test_keys)) : (int) ($state['deleted_keys'] ?? 0);
            $percent = $total_keys > 0 ? ($processed / $total_keys) * 100 : ($complete ? 100 : 0);
        } else {
            $percent = $complete ? 100 : (($connection_index / $connection_total) * 100);
        }

        return [
            'token' => (string) ($state['token'] ?? ''),
            'status' => $status,
            'status_label' => self::format_reset_job_status_label($status),
            'complete' => $complete,
            'progress_percent' => round(min(100, max(0, $percent)), 2),
            'message' => (string) ($state['last_message'] ?? ''),
            'detail' => (string) ($state['last_detail'] ?? ''),
            'totals' => [
                'deleted_keys' => max(0, (int) ($state['deleted_keys'] ?? 0)),
                'deleted_options' => max(0, (int) ($state['deleted_options'] ?? 0)),
                'connection_index' => $connection_index,
                'connection_total' => $connection_total,
                'total_keys' => max(0, (int) ($state['total_keys'] ?? 0)),
            ],
            'databases' => array_values(array_filter((array) ($state['databases'] ?? []), 'is_array')),
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function build_reset_job_state(array $state): array
    {
        $status = sanitize_key((string) ($state['status'] ?? 'inactive'));
        if (!in_array($status, ['active', 'completed', 'failed'], true)) {
            $status = 'inactive';
        }

        return [
            'token' => sanitize_key((string) ($state['token'] ?? '')),
            'active' => !empty($state['active']) || $status === 'active',
            'status' => $status,
            'source' => sanitize_key((string) ($state['source'] ?? 'admin')),
            'connection_index' => max(0, (int) ($state['connection_index'] ?? 0)),
            'connection_total' => max(0, (int) ($state['connection_total'] ?? 0)),
            'scan_cursor' => max(0, (int) ($state['scan_cursor'] ?? 0)),
            'deleted_keys' => max(0, (int) ($state['deleted_keys'] ?? 0)),
            'deleted_options' => max(0, (int) ($state['deleted_options'] ?? 0)),
            'total_keys' => max(0, (int) ($state['total_keys'] ?? 0)),
            'test_keys' => array_values(array_filter(array_map('strval', (array) ($state['test_keys'] ?? [])))),
            'databases' => array_values(array_filter((array) ($state['databases'] ?? []), 'is_array')),
            'started_at' => sanitize_text_field((string) ($state['started_at'] ?? '')),
            'updated_at' => sanitize_text_field((string) ($state['updated_at'] ?? '')),
            'finished_at' => sanitize_text_field((string) ($state['finished_at'] ?? '')),
            'last_message' => sanitize_text_field((string) ($state['last_message'] ?? '')),
            'last_detail' => sanitize_text_field((string) ($state['last_detail'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function save_reset_job_state(array $state): bool
    {
        $state = self::build_reset_job_state($state);
        $token = sanitize_key((string) ($state['token'] ?? ''));
        if ($token === '') {
            return false;
        }

        return set_transient(self::RESET_JOB_TRANSIENT_PREFIX . $token, $state, self::RESET_JOB_TTL);
    }

    private static function clear_reset_job_state(string $token): void
    {
        $token = sanitize_key($token);
        if ($token !== '') {
            delete_transient(self::RESET_JOB_TRANSIENT_PREFIX . $token);
        }

        $active_token = sanitize_key((string) get_transient(self::RESET_JOB_ACTIVE_TOKEN_TRANSIENT));
        if ($active_token === $token || $active_token === '') {
            delete_transient(self::RESET_JOB_ACTIVE_TOKEN_TRANSIENT);
        }
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function is_reset_job_terminal(array $state): bool
    {
        return in_array(sanitize_key((string) ($state['status'] ?? '')), ['completed', 'failed'], true);
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function tick_test_reset_job(array $state): array
    {
        $state = self::build_reset_job_state($state);
        $keys = array_values(array_filter(array_map('strval', (array) ($state['test_keys'] ?? []))));
        if (empty($keys)) {
            return self::finish_reset_job($state);
        }

        $chunk = array_slice($keys, 0, self::RESET_JOB_CHUNK_SIZE);
        $remaining = array_slice($keys, count($chunk));
        $deleted = 0;
        foreach ($chunk as $key) {
            if (self::delete_test_storage_key($key)) {
                $deleted++;
            }
        }

        $state['test_keys'] = $remaining;
        $state['deleted_keys'] = max(0, (int) ($state['deleted_keys'] ?? 0)) + $deleted;
        $state['updated_at'] = current_time('mysql');
        $state['last_message'] = sprintf('Reset Redis CBT berjalan. Terhapus %d key.', max(0, (int) ($state['deleted_keys'] ?? 0)));
        $state['last_detail'] = sprintf('Batch terakhir %d key. Sisa estimasi %d key.', $deleted, count($remaining));

        if (empty($remaining)) {
            return self::finish_reset_job($state);
        }

        return self::build_reset_job_state($state);
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function tick_redis_reset_job(array $state): array
    {
        $state = self::build_reset_job_state($state);
        if (!class_exists('Redis')) {
            $state['status'] = 'failed';
            $state['active'] = false;
            $state['finished_at'] = current_time('mysql');
            $state['last_message'] = 'Ekstensi Redis tidak tersedia di environment ini.';
            return self::build_reset_job_state($state);
        }

        $connections = self::target_connections();
        if (empty($connections)) {
            $state['status'] = 'failed';
            $state['active'] = false;
            $state['finished_at'] = current_time('mysql');
            $state['last_message'] = 'Konfigurasi Redis CBT tidak ditemukan.';
            return self::build_reset_job_state($state);
        }

        $state['connection_total'] = count($connections);
        $connection_index = max(0, (int) ($state['connection_index'] ?? 0));
        if ($connection_index >= count($connections)) {
            return self::finish_reset_job($state);
        }

        $connection = $connections[$connection_index];
        $result = self::delete_prefixed_keys_chunk_for_connection($connection, max(0, (int) ($state['scan_cursor'] ?? 0)), self::RESET_JOB_CHUNK_SIZE);
        $deleted = max(0, (int) ($result['deleted_keys'] ?? 0));
        $state['deleted_keys'] = max(0, (int) ($state['deleted_keys'] ?? 0)) + $deleted;
        $state['scan_cursor'] = max(0, (int) ($result['next_cursor'] ?? 0));
        $state['updated_at'] = current_time('mysql');
        $state['last_message'] = sprintf('Reset Redis CBT berjalan. Terhapus %d key.', max(0, (int) ($state['deleted_keys'] ?? 0)));
        $state['last_detail'] = sprintf(
            'DB %d batch terakhir %d key.',
            max(0, (int) ($connection['database'] ?? 0)),
            $deleted
        );

        if (empty($result['success'])) {
            $state['databases'][] = [
                'host' => (string) ($connection['host'] ?? self::DEFAULT_HOST),
                'port' => (int) ($connection['port'] ?? self::DEFAULT_PORT),
                'database' => (int) ($connection['database'] ?? 0),
                'deleted_keys' => $deleted,
                'ok' => false,
                'message' => (string) ($result['message'] ?? 'Gagal menghapus key Redis CBT.'),
            ];
            $state['connection_index'] = $connection_index + 1;
            $state['scan_cursor'] = 0;
        } elseif (!empty($result['done'])) {
            $state['databases'][] = [
                'host' => (string) ($connection['host'] ?? self::DEFAULT_HOST),
                'port' => (int) ($connection['port'] ?? self::DEFAULT_PORT),
                'database' => (int) ($connection['database'] ?? 0),
                'deleted_keys' => max(0, (int) ($result['connection_deleted_keys'] ?? $deleted)),
                'ok' => true,
            ];
            $state['connection_index'] = $connection_index + 1;
            $state['scan_cursor'] = 0;
        }

        if ((int) ($state['connection_index'] ?? 0) >= count($connections)) {
            return self::finish_reset_job($state);
        }

        return self::build_reset_job_state($state);
    }

    /**
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private static function finish_reset_job(array $state): array
    {
        $state = self::build_reset_job_state($state);
        if (sanitize_key((string) ($state['status'] ?? '')) !== 'completed') {
            $state['deleted_options'] = self::reset_plugin_state_options();
        }
        $state['active'] = false;
        $state['status'] = 'completed';
        $state['finished_at'] = current_time('mysql');
        $state['updated_at'] = current_time('mysql');
        $state['last_message'] = sprintf(
            'Redis CBT berhasil dibersihkan. Key terhapus %d. State option direset %d.',
            max(0, (int) ($state['deleted_keys'] ?? 0)),
            max(0, (int) ($state['deleted_options'] ?? 0))
        );
        $state['last_detail'] = 'Reset Redis CBT selesai.';

        return self::build_reset_job_state($state);
    }

    private static function format_reset_job_status_label(string $status): string
    {
        switch (sanitize_key($status)) {
            case 'completed':
                return 'Selesai';
            case 'failed':
                return 'Gagal';
            case 'active':
                return 'Berjalan';
            default:
                return 'Siaga';
        }
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

    /**
     * @param array{host:string,port:int,database:int,password:string,timeout:float} $connection
     * @return array<string,mixed>
     */
    private static function delete_prefixed_keys_chunk_for_connection(array $connection, int $cursor, int $limit): array
    {
        try {
            $redis = self::connect_to_redis($connection);
            if (!method_exists($redis, 'scan')) {
                $deleted = self::delete_prefixed_keys_from_test_storage();
                return [
                    'success' => true,
                    'deleted_keys' => $deleted,
                    'connection_deleted_keys' => $deleted,
                    'next_cursor' => 0,
                    'done' => true,
                ];
            }

            $iterator = $cursor > 0 ? $cursor : null;
            $deleted = 0;
            $scanned = 0;

            do {
                $keys = $redis->scan($iterator, self::KEY_PATTERN, $limit);
                if (is_array($keys) && !empty($keys)) {
                    $prefixed_keys = array_values(array_filter($keys, static function ($key): bool {
                        return is_string($key) && str_starts_with($key, self::KEY_PREFIX);
                    }));
                    if (!empty($prefixed_keys)) {
                        $deleted += (int) $redis->del(...$prefixed_keys);
                    }
                }

                $scanned++;
            } while ($iterator !== 0 && $deleted <= 0 && $scanned < 5);

            return [
                'success' => true,
                'deleted_keys' => $deleted,
                'connection_deleted_keys' => $deleted,
                'next_cursor' => max(0, (int) $iterator),
                'done' => $iterator === 0,
            ];
        } catch (Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Koneksi Redis gagal: ' . $throwable->getMessage(),
                'deleted_keys' => 0,
                'connection_deleted_keys' => 0,
                'next_cursor' => 0,
                'done' => true,
            ];
        }
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

    /**
     * @return string[]
     */
    private static function collect_prefixed_test_storage_keys(): array
    {
        $keys = [];
        foreach (['cbt_test_redis_storage', 'cbt_test_redis_zsets'] as $global_key) {
            if (!isset($GLOBALS[$global_key]) || !is_array($GLOBALS[$global_key])) {
                continue;
            }

            foreach (array_keys($GLOBALS[$global_key]) as $key) {
                if (is_string($key) && str_starts_with($key, self::KEY_PREFIX)) {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    private static function delete_test_storage_key(string $key): bool
    {
        $deleted = false;
        foreach (['cbt_test_redis_storage', 'cbt_test_redis_zsets'] as $global_key) {
            if (isset($GLOBALS[$global_key]) && is_array($GLOBALS[$global_key]) && array_key_exists($key, $GLOBALS[$global_key])) {
                unset($GLOBALS[$global_key][$key]);
                $deleted = true;
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
