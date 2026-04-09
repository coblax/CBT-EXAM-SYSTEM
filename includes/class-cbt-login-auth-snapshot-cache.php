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

if (!class_exists('CBT_Redis_Pipeline_Helper')) {
    require_once __DIR__ . '/class-cbt-redis-pipeline-helper.php';
}

if (!class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
    require_once __DIR__ . '/class-cbt-snapshot-auto-heal-queue-service.php';
}

class CBT_Login_Auth_Snapshot_Cache
{
    private const SNAPSHOT_EVENT_REDIS_TTL_SECONDS = 604800;
    private const SNAPSHOT_REDIS_TTL_SECONDS = 43200;
    private const SNAPSHOT_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const SNAPSHOT_REDIS_DEFAULT_PORT = 6379;
    private const SNAPSHOT_REDIS_DEFAULT_DATABASE = 2;
    private const SNAPSHOT_EVENT_REDIS_PREFIX = 'cbt_login_auth_meta:';
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
        $lookup = self::get_snapshot_lookup_result($identifier);
        return is_array($lookup['snapshot'] ?? null) ? $lookup['snapshot'] : null;
    }

    /**
     * @return array{
     *   snapshot:array<string,mixed>|null,
     *   lookup_status:string,
     *   snapshot_miss_reason:string,
     *   snapshot_miss_reason_label:string,
     *   source_path:string,
     *   resolved_user_id:int,
     *   lookup_identifier:string
     * }
     */
    public static function get_snapshot_lookup_result(string $identifier): array
    {
        $lookup_identifiers = self::build_lookup_identifiers($identifier);
        if (empty($lookup_identifiers)) {
            return [
                'snapshot' => null,
                'lookup_status' => 'miss',
                'snapshot_miss_reason' => 'not_prepared',
                'snapshot_miss_reason_label' => self::get_snapshot_miss_reason_label('not_prepared'),
                'source_path' => 'canonical',
                'resolved_user_id' => 0,
                'lookup_identifier' => '',
            ];
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return [
                'snapshot' => null,
                'lookup_status' => 'unavailable',
                'snapshot_miss_reason' => 'redis_unavailable',
                'snapshot_miss_reason_label' => self::get_snapshot_miss_reason_label('redis_unavailable'),
                'source_path' => 'canonical',
                'resolved_user_id' => self::resolve_user_id_from_identifier($identifier),
                'lookup_identifier' => '',
            ];
        }

        $resolved_user_id = self::resolve_user_id_from_identifier($identifier);
        $last_reason = [
            'code' => '',
            'label' => '',
        ];

        foreach ($lookup_identifiers as $lookup_identifier) {
            $user_id = self::read_index_user_id($lookup_identifier, $redis);
            if ($user_id <= 0) {
                continue;
            }

            $snapshot = self::read_user_snapshot($user_id, $redis_available);
            if (is_array($snapshot)) {
                if (!in_array($lookup_identifier, (array) ($snapshot['identifiers'] ?? []), true)) {
                    self::clear_user_snapshot($user_id, 'identifier_changed');
                    $resolved_user_id = $user_id;
                    $last_reason = [
                        'code' => 'identifier_changed',
                        'label' => self::get_snapshot_miss_reason_label('identifier_changed'),
                    ];
                    continue;
                }

                $redis->expire(self::index_storage_key($lookup_identifier), self::SNAPSHOT_REDIS_TTL_SECONDS);
                return [
                    'snapshot' => $snapshot,
                    'lookup_status' => 'ready',
                    'snapshot_miss_reason' => '',
                    'snapshot_miss_reason_label' => '',
                    'source_path' => 'snapshot',
                    'resolved_user_id' => $user_id,
                    'lookup_identifier' => $lookup_identifier,
                ];
            }

            if ($redis_available) {
                $redis->del(self::index_storage_key($lookup_identifier));
            }

            $resolved_user_id = $user_id;
            $last_reason = self::detect_snapshot_miss_reason($user_id, $redis);
        }

        if ($last_reason['code'] === '' && $resolved_user_id > 0) {
            $last_reason = self::detect_snapshot_miss_reason($resolved_user_id, $redis);
        }

        if (($last_reason['code'] ?? '') === '' && $resolved_user_id <= 0) {
            $last_reason = [
                'code' => 'not_prepared',
                'label' => self::get_snapshot_miss_reason_label('not_prepared'),
            ];
        }

        $reason_code = sanitize_key((string) ($last_reason['code'] ?? ''));
        if ($resolved_user_id > 0 && $reason_code !== '') {
            self::maybe_enqueue_auto_heal($resolved_user_id, $reason_code, 'lookup');
        }

        return [
            'snapshot' => null,
            'lookup_status' => $reason_code === 'invalid_payload' ? 'invalid' : 'miss',
            'snapshot_miss_reason' => $reason_code,
            'snapshot_miss_reason_label' => (string) ($last_reason['label'] ?? self::get_snapshot_miss_reason_label($reason_code)),
            'source_path' => 'canonical',
            'resolved_user_id' => $resolved_user_id,
            'lookup_identifier' => '',
        ];
    }

    public static function get_snapshot_miss_reason_label(string $reason_code): string
    {
        $reason_code = sanitize_key($reason_code);

        switch ($reason_code) {
            case 'manual_clear':
                return 'Dibersihkan manual';
            case 'identifier_changed':
                return 'Identifier login berubah';
            case 'password_changed':
                return 'Password berubah';
            case 'password_mismatch':
                return 'Hash password snapshot tidak cocok';
            case 'role_changed':
                return 'Role user berubah';
            case 'user_deleted':
                return 'User dihapus';
            case 'invalid_payload':
                return 'Payload invalid';
            case 'invalid_snapshot':
                return 'Snapshot login tidak valid';
            case 'ineligible_user':
                return 'User bukan siswa';
            case 'write_failed':
                return 'Gagal menulis ke Redis';
            case 'expired_or_evicted':
                return 'TTL habis / ter-evict';
            case 'redis_unavailable':
                return 'Redis tidak tersedia';
            case 'not_prepared':
            default:
                return 'Belum disiapkan';
        }
    }

    /**
     * @param int[] $user_ids
     * @return array<int,array<string,mixed>>
     */
    public static function get_user_snapshot_freshness_map(array $user_ids, int $required_ttl_seconds = 0): array
    {
        $user_ids = array_values(array_unique(array_filter(array_map('absint', $user_ids))));
        $required_ttl_seconds = max(0, $required_ttl_seconds);
        if (empty($user_ids)) {
            return [];
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            $rows = [];
            foreach ($user_ids as $user_id) {
                $rows[$user_id] = [
                    'user_id' => $user_id,
                    'snapshot_status' => 'unavailable',
                    'snapshot_miss_reason' => 'redis_unavailable',
                    'snapshot_miss_reason_label' => self::get_snapshot_miss_reason_label('redis_unavailable'),
                    'ttl_seconds' => -2,
                    'repair_status' => '',
                    'repair_message' => '',
                    'eligible_for_refresh' => false,
                ];
            }

            return $rows;
        }

        $storage_keys = [];
        foreach ($user_ids as $user_id) {
            $storage_keys[$user_id] = self::user_storage_key($user_id);
        }

        $raw_snapshots = [];
        $ttl_map = [];
        if (method_exists($redis, 'pipeline') && method_exists($redis, 'exec')) {
            try {
                $pipeline = $redis->pipeline();
                if (is_object($pipeline) && method_exists($pipeline, 'get') && method_exists($pipeline, 'ttl') && method_exists($pipeline, 'exec')) {
                    foreach ($storage_keys as $storage_key) {
                        $pipeline->get($storage_key);
                        $pipeline->ttl($storage_key);
                    }

                    $results = $pipeline->exec();
                    if (is_array($results) && count($results) === (count($storage_keys) * 2)) {
                        $result_index = 0;
                        foreach ($storage_keys as $user_id => $storage_key) {
                            $raw_snapshots[$user_id] = $results[$result_index] ?? false;
                            $ttl_map[$user_id] = isset($results[$result_index + 1]) ? (int) $results[$result_index + 1] : -2;
                            $result_index += 2;
                        }
                    }
                }
            } catch (Throwable $throwable) {
            }
        }

        $rows = [];
        foreach ($user_ids as $user_id) {
            $storage_key = $storage_keys[$user_id];
            $raw_snapshot = array_key_exists($user_id, $raw_snapshots) ? $raw_snapshots[$user_id] : $redis->get($storage_key);
            $ttl_seconds = array_key_exists($user_id, $ttl_map) ? (int) $ttl_map[$user_id] : ((method_exists($redis, 'ttl')) ? (int) $redis->ttl($storage_key) : -2);
            $snapshot_exists = is_string($raw_snapshot) && trim($raw_snapshot) !== '';
            $snapshot = $snapshot_exists ? self::decode_snapshot((string) $raw_snapshot) : null;
            $snapshot_valid = is_array($snapshot);
            $snapshot_status = 'miss';
            $snapshot_miss_reason = '';
            $snapshot_miss_reason_label = '';

            if ($snapshot_valid) {
                $snapshot_status = 'ready';
            } elseif ($snapshot_exists) {
                $snapshot_status = 'invalid';
                $snapshot_miss_reason = 'invalid_payload';
                $snapshot_miss_reason_label = self::get_snapshot_miss_reason_label('invalid_payload');
            } else {
                $miss_reason = self::detect_snapshot_miss_reason($user_id, $redis);
                $snapshot_status = 'miss';
                $snapshot_miss_reason = (string) ($miss_reason['code'] ?? '');
                $snapshot_miss_reason_label = (string) ($miss_reason['label'] ?? self::get_snapshot_miss_reason_label($snapshot_miss_reason));
            }

            if (in_array($snapshot_status, ['miss', 'invalid'], true) && $snapshot_miss_reason !== '') {
                self::maybe_enqueue_auto_heal($user_id, $snapshot_miss_reason, 'freshness_probe');
            }

            $queue_meta = self::build_queue_repair_meta($user_id);
            $repair_status = (string) ($queue_meta['status'] ?? '');
            $repair_message = (string) ($queue_meta['message'] ?? '');
            $eligible_for_refresh = self::is_freshness_eligible(
                $snapshot_status,
                $snapshot_miss_reason,
                $ttl_seconds,
                $repair_status,
                $required_ttl_seconds
            );

            $rows[$user_id] = [
                'user_id' => $user_id,
                'snapshot_status' => $snapshot_status,
                'snapshot_miss_reason' => $snapshot_miss_reason,
                'snapshot_miss_reason_label' => $snapshot_miss_reason_label,
                'ttl_seconds' => $ttl_seconds,
                'repair_status' => $repair_status,
                'repair_message' => $repair_message,
                'eligible_for_refresh' => $eligible_for_refresh,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>
     */
    public static function warm_user_snapshot(int $user_id, string $source = 'manual'): array
    {
        $result = self::warm_user_snapshot_result($user_id, $source);
        return is_array($result['snapshot'] ?? null) ? $result['snapshot'] : self::empty_snapshot();
    }

    /**
     * @param array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}|null $profile_snapshot
     * @return array{
     *   ready:bool,
     *   write_success:bool,
     *   reason:string,
     *   snapshot:array<string,mixed>
     * }
     */
    public static function warm_user_snapshot_result(int $user_id, string $source = 'manual', ?array $profile_snapshot = null): array
    {
        $user_id = absint($user_id);
        $results = self::warm_user_snapshot_results(
            [$user_id],
            $source,
            $profile_snapshot !== null ? [$user_id => $profile_snapshot] : []
        );

        return $results[$user_id] ?? [
            'ready' => false,
            'write_success' => false,
            'reason' => 'invalid_user',
            'snapshot' => self::empty_snapshot(),
        ];
    }

    /**
     * @param int[] $user_ids
     * @param array<int,array{kode_kelas:string,kode_ruang:string,agama:string,foto:string,jenis_kelamin:string,nisn:string}> $profile_snapshots_by_user
     * @return array<int,array{
     *   ready:bool,
     *   write_success:bool,
     *   reason:string,
     *   snapshot:array<string,mixed>
     * }>
     */
    public static function warm_user_snapshot_results(array $user_ids, string $source = 'manual', array $profile_snapshots_by_user = []): array
    {
        $user_ids = array_values(array_unique(array_filter(array_map('absint', $user_ids))));
        if (empty($user_ids)) {
            return [];
        }

        self::prime_user_snapshot_caches($user_ids);
        $results = [];
        $prepared_snapshots = [];
        $clear_targets_by_reason = [];

        foreach ($user_ids as $user_id) {
            $results[$user_id] = [
                'ready' => false,
                'write_success' => false,
                'reason' => 'invalid_user',
                'snapshot' => self::empty_snapshot(),
            ];

            $user = get_user_by('id', $user_id);
            if (!($user instanceof WP_User) || !self::is_snapshot_eligible_user($user)) {
                $results[$user_id]['reason'] = 'ineligible_user';
                $clear_targets_by_reason['ineligible_user'][] = $user_id;
                continue;
            }

            $profile_snapshot = isset($profile_snapshots_by_user[$user_id]) && is_array($profile_snapshots_by_user[$user_id])
                ? $profile_snapshots_by_user[$user_id]
                : null;
            $snapshot = self::build_snapshot_from_user($user, $source, $profile_snapshot);
            if (empty($snapshot['identifiers']) || (string) ($snapshot['password_hash'] ?? '') === '') {
                $results[$user_id]['reason'] = 'invalid_snapshot';
                $clear_targets_by_reason['invalid_snapshot'][] = $user_id;
                continue;
            }

            $prepared_snapshots[$user_id] = $snapshot;
            $results[$user_id]['snapshot'] = $snapshot;
            $results[$user_id]['reason'] = 'redis_unavailable';
        }

        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return $results;
        }

        foreach ($clear_targets_by_reason as $reason => $reason_user_ids) {
            self::clear_user_snapshots_for_rewrite($reason_user_ids, (string) $reason);
        }
        self::clear_user_snapshots_for_rewrite(array_keys($prepared_snapshots));
        $operations = [];
        $operation_user_ids = [];

        foreach ($prepared_snapshots as $user_id => $snapshot) {
            $encoded = wp_json_encode($snapshot);
            if (!is_string($encoded) || $encoded === '') {
                $results[$user_id]['snapshot'] = self::empty_snapshot();
                $results[$user_id]['reason'] = 'encode_failed';
                unset($prepared_snapshots[$user_id]);
                continue;
            }

            $operations[] = [
                'key' => self::user_storage_key($user_id),
                'ttl' => self::SNAPSHOT_REDIS_TTL_SECONDS,
                'value' => $encoded,
            ];
            $operation_user_ids[] = $user_id;

            foreach ((array) ($snapshot['identifiers'] ?? []) as $identifier_key) {
                $identifier_key = is_scalar($identifier_key) ? trim((string) $identifier_key) : '';
                if ($identifier_key === '') {
                    continue;
                }

                $operations[] = [
                    'key' => self::index_storage_key($identifier_key),
                    'ttl' => self::SNAPSHOT_REDIS_TTL_SECONDS,
                    'value' => (string) $user_id,
                ];
                $operation_user_ids[] = $user_id;
            }
        }

        $write_results = CBT_Redis_Pipeline_Helper::write_setex_results($redis, $operations);
        $user_write_results = [];
        foreach ($operation_user_ids as $index => $operation_user_id) {
            if (!isset($user_write_results[$operation_user_id])) {
                $user_write_results[$operation_user_id] = [];
            }

            $user_write_results[$operation_user_id][] = !empty($write_results[$index]);
        }

        $failed_user_ids = [];
        foreach ($prepared_snapshots as $user_id => $snapshot) {
            $write_success = !empty($user_write_results[$user_id])
                && !in_array(false, $user_write_results[$user_id], true);
            $results[$user_id]['ready'] = $write_success;
            $results[$user_id]['write_success'] = $write_success;
            $results[$user_id]['reason'] = $write_success ? 'ready' : 'write_failed';
            if ($write_success) {
                self::write_snapshot_event_marker($user_id, 'written');
            }

            if (!$write_success) {
                $failed_user_ids[] = $user_id;
            }
        }

        if (!empty($failed_user_ids)) {
            self::clear_user_snapshots_for_rewrite($failed_user_ids, 'write_failed');
        }

        return $results;
    }

    public static function clear_user_snapshot(int $user_id, string $reason = 'manual_clear'): int
    {
        return self::clear_user_snapshots_for_rewrite([$user_id], $reason);
    }

    /**
     * @param int[] $user_ids
     */
    public static function clear_user_snapshots_for_rewrite(array $user_ids, string $reason = ''): int
    {
        $user_ids = array_values(array_unique(array_filter(array_map('absint', $user_ids))));
        if (empty($user_ids)) {
            return 0;
        }

        self::prime_user_snapshot_caches($user_ids);
        $redis = self::snapshot_redis();
        if (!$redis instanceof Redis) {
            return 0;
        }

        $keys = [];
        foreach ($user_ids as $user_id) {
            $keys[] = self::user_storage_key($user_id);
            $raw_snapshot = $redis->get(self::user_storage_key($user_id));
            foreach (self::extract_identifiers_from_raw_snapshot($raw_snapshot) as $identifier_key) {
                $keys[] = self::index_storage_key($identifier_key);
            }

            $user = get_user_by('id', $user_id);
            if ($user instanceof WP_User) {
                foreach (self::build_user_identifiers($user) as $identifier_key) {
                    $keys[] = self::index_storage_key($identifier_key);
                }
            }
        }

        $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));
        $reason = sanitize_key($reason);
        if (empty($keys)) {
            if ($reason !== '') {
                foreach ($user_ids as $user_id) {
                    self::write_snapshot_event_marker($user_id, $reason);
                    self::maybe_enqueue_auto_heal($user_id, $reason, 'invalidation');
                }
            }

            return 0;
        }

        $deleted = (int) $redis->del(...$keys);
        if ($reason !== '') {
            foreach ($user_ids as $user_id) {
                self::write_snapshot_event_marker($user_id, $reason);
                self::maybe_enqueue_auto_heal($user_id, $reason, 'invalidation');
            }
        }

        return $deleted;
    }

    /**
     * @param array<string,mixed> $exam_row
     * @return array<string,mixed>
     */
    public static function warm_exam_target_snapshots(array $exam_row, string $source = 'manual_exam'): array
    {
        $target_student_ids = self::get_exam_target_student_ids($exam_row);
        self::prime_user_snapshot_caches($target_student_ids);
        $ready_count = 0;
        $failure_count = 0;

        $results = self::warm_user_snapshot_results($target_student_ids, $source);
        foreach ($target_student_ids as $user_id) {
            $result = $results[$user_id] ?? [
                'ready' => false,
            ];
            if (!empty($result['ready'])) {
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
        $deleted_keys = self::clear_user_snapshots_for_rewrite($target_student_ids);

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
     *   snapshot_miss_reason:string,
     *   snapshot_miss_reason_label:string,
     *   snapshot_message:string,
     *   repair_status:string,
     *   repair_message:string,
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
                'snapshot_miss_reason' => 'idle',
                'snapshot_miss_reason_label' => 'Belum dipilih',
                'snapshot_message' => 'User login snapshot belum dipilih.',
                'repair_status' => '',
                'repair_message' => '',
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
                'snapshot_miss_reason' => 'redis_unavailable',
                'snapshot_miss_reason_label' => 'Redis tidak tersedia',
                'snapshot_message' => 'Redis login snapshot tidak tersedia.',
                'repair_status' => '',
                'repair_message' => '',
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
        $miss_reason = ['code' => '', 'label' => ''];

        if ($snapshot_valid) {
            $snapshot_status = 'ready';
            $snapshot_message = 'Login snapshot siap dipakai untuk login siswa.';
        } elseif ($snapshot_exists) {
            $snapshot_status = 'invalid';
            $miss_reason = ['code' => 'invalid_payload', 'label' => 'Payload invalid'];
            $snapshot_message = self::build_snapshot_miss_message($miss_reason);
        } else {
            $snapshot_status = 'miss';
            $miss_reason = self::detect_snapshot_miss_reason($user_id, $redis);
            $snapshot_message = self::build_snapshot_miss_message($miss_reason);
        }

        $snapshot_miss_reason = (string) ($miss_reason['code'] ?? '');
        if (in_array($snapshot_status, ['miss', 'invalid'], true)) {
            self::maybe_enqueue_auto_heal($user_id, $snapshot_miss_reason, 'diagnostics');
        }
        $queue_meta = self::build_queue_repair_meta($user_id);

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
            'snapshot_miss_reason' => $snapshot_miss_reason,
            'snapshot_miss_reason_label' => (string) ($miss_reason['label'] ?? ''),
            'snapshot_message' => $snapshot_message,
            'repair_status' => (string) ($queue_meta['status'] ?? ''),
            'repair_message' => (string) ($queue_meta['message'] ?? ''),
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

    /**
     * @return array{
     *   success:bool,
     *   status:string,
     *   message:string,
     *   diagnostics:array<string,mixed>
     * }
     */
    public static function maybe_auto_heal_snapshot(int $user_id, string $source = 'admin'): array
    {
        $user_id = absint($user_id);
        $diagnostics = self::get_snapshot_diagnostics($user_id);
        $default = [
            'success' => false,
            'status' => '',
            'message' => '',
            'diagnostics' => $diagnostics,
        ];

        if ($user_id <= 0) {
            return $default;
        }

        $snapshot_status = sanitize_key((string) ($diagnostics['snapshot_status'] ?? 'miss'));
        $miss_reason = sanitize_key((string) ($diagnostics['snapshot_miss_reason'] ?? ''));
        if (!in_array($snapshot_status, ['miss', 'invalid'], true)) {
            return $default;
        }

        if (!self::is_auto_heal_miss_reason($miss_reason)) {
            return $default;
        }

        $user = get_user_by('id', $user_id);
        if (!($user instanceof WP_User)) {
            return $default;
        }

        if ($miss_reason === 'role_changed' && !self::is_snapshot_eligible_user($user)) {
            return $default;
        }

        $result = self::warm_user_snapshot_result($user_id, $source);
        if (empty($result['ready'])) {
            return [
                'success' => false,
                'status' => '',
                'message' => '',
                'diagnostics' => self::get_snapshot_diagnostics($user_id),
            ];
        }

        $message = 'Dipulihkan otomatis dari data login canonical';
        $healed_diagnostics = self::get_snapshot_diagnostics($user_id);
        $healed_diagnostics['repair_status'] = 'auto_healed';
        $healed_diagnostics['repair_message'] = $message;

        return [
            'success' => true,
            'status' => 'auto_healed',
            'message' => $message,
            'diagnostics' => $healed_diagnostics,
        ];
    }

    public static function handle_delete_user(int $user_id): void
    {
        self::clear_user_snapshot($user_id, 'user_deleted');
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

        self::clear_user_snapshot($user_id, 'identifier_changed');
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
            self::clear_user_snapshot($user_id, 'user_deleted');
            return;
        }

        $old_login = strtolower(trim((string) ($old_user_data->user_login ?? '')));
        $old_email = strtolower(trim((string) ($old_user_data->user_email ?? '')));
        $new_login = strtolower(trim((string) $current_user->user_login));
        $new_email = strtolower(trim((string) $current_user->user_email));

        if ($old_login !== $new_login || $old_email !== $new_email) {
            self::clear_user_snapshot($user_id, 'identifier_changed');
        }
    }

    /**
     * @param mixed $old_roles
     */
    public static function handle_user_role_change(int $user_id, string $role, $old_roles = []): void
    {
        self::clear_user_snapshot($user_id, 'role_changed');
    }

    public static function handle_password_reset(WP_User $user, string $new_pass): void
    {
        self::clear_user_snapshot((int) ($user->ID ?? 0), 'password_changed');
    }

    public static function handle_wp_set_password(string $password, int $user_id): void
    {
        self::clear_user_snapshot($user_id, 'password_changed');
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_snapshot_from_user(WP_User $user, string $source, ?array $profile_snapshot = null): array
    {
        $user_id = (int) $user->ID;
        $profile = is_array($profile_snapshot) ? $profile_snapshot : CBT_Student_Profile_Cache::get_snapshot($user_id);
        $nisn = sanitize_text_field((string) ($profile['nisn'] ?? ''));
        $identifiers = self::build_user_identifiers($user, $nisn);

        return [
            'user_id' => $user_id,
            'role' => self::resolve_snapshot_role($user),
            'display_name' => sanitize_text_field((string) ($user->display_name !== '' ? $user->display_name : $user->user_login)),
            'user_login' => sanitize_text_field((string) $user->user_login),
            'user_email' => sanitize_email((string) $user->user_email),
            'nisn' => $nisn,
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
    private static function build_user_identifiers(WP_User $user, string $nisn = ''): array
    {
        $identifiers = [];
        $user_login = sanitize_user((string) $user->user_login, true);
        $user_email = sanitize_email((string) $user->user_email);
        $nisn = sanitize_text_field($nisn !== '' ? $nisn : (string) get_user_meta((int) $user->ID, 'nisn', true));

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

    /**
     * @param int[] $user_ids
     */
    private static function prime_user_snapshot_caches(array $user_ids): void
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
            self::clear_user_snapshot($user_id, 'invalid_payload');
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

    private static function is_auto_heal_miss_reason(string $miss_reason): bool
    {
        return in_array(
            sanitize_key($miss_reason),
            [
                'identifier_changed',
                'password_changed',
                'invalid_payload',
                'expired_or_evicted',
                'write_failed',
                'role_changed',
            ],
            true
        );
    }

    private static function maybe_enqueue_auto_heal(int $user_id, string $reason, string $source = 'system'): void
    {
        $user_id = absint($user_id);
        $reason = sanitize_key($reason);
        if ($user_id <= 0 || $reason === '' || !self::is_auto_heal_miss_reason($reason)) {
            return;
        }

        if ($reason === 'role_changed') {
            $user = get_user_by('id', $user_id);
            if (!($user instanceof WP_User) || !self::is_snapshot_eligible_user($user)) {
                return;
            }
        }

        if (class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
            CBT_Snapshot_Auto_Heal_Queue_Service::maybe_enqueue('login_user', $user_id, $reason, $source);
        }
    }

    /**
     * @return array{status:string,message:string}
     */
    private static function build_queue_repair_meta(int $user_id): array
    {
        if ($user_id <= 0 || !class_exists('CBT_Snapshot_Auto_Heal_Queue_Service')) {
            return [
                'status' => '',
                'message' => '',
            ];
        }

        $queue_meta = CBT_Snapshot_Auto_Heal_Queue_Service::get_target_repair_state('login_user', $user_id);
        if (empty($queue_meta['queued'])) {
            return [
                'status' => '',
                'message' => '',
            ];
        }

        return [
            'status' => (string) ($queue_meta['status'] ?? 'queued_auto_heal'),
            'message' => (string) ($queue_meta['message'] ?? ''),
        ];
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

    private static function event_storage_key(int $user_id): string
    {
        return self::SNAPSHOT_EVENT_REDIS_PREFIX . 'user:' . absint($user_id);
    }

    private static function index_storage_key(string $identifier_key): string
    {
        return self::SNAPSHOT_REDIS_PREFIX . 'index:' . trim($identifier_key);
    }

    /**
     * @return array{code:string,label:string}
     */
    private static function detect_snapshot_miss_reason(int $user_id, Redis $redis): array
    {
        $event = self::read_snapshot_event_marker($user_id, $redis);
        $event_code = sanitize_key((string) ($event['event'] ?? ''));

        if ($event_code === 'manual_clear') {
            return ['code' => 'manual_clear', 'label' => self::get_snapshot_miss_reason_label('manual_clear')];
        }

        if ($event_code === 'identifier_changed') {
            return ['code' => 'identifier_changed', 'label' => self::get_snapshot_miss_reason_label('identifier_changed')];
        }

        if ($event_code === 'password_changed') {
            return ['code' => 'password_changed', 'label' => self::get_snapshot_miss_reason_label('password_changed')];
        }

        if ($event_code === 'role_changed') {
            return ['code' => 'role_changed', 'label' => self::get_snapshot_miss_reason_label('role_changed')];
        }

        if ($event_code === 'user_deleted') {
            return ['code' => 'user_deleted', 'label' => self::get_snapshot_miss_reason_label('user_deleted')];
        }

        if ($event_code === 'invalid_payload') {
            return ['code' => 'invalid_payload', 'label' => self::get_snapshot_miss_reason_label('invalid_payload')];
        }

        if ($event_code === 'invalid_snapshot') {
            return ['code' => 'invalid_snapshot', 'label' => self::get_snapshot_miss_reason_label('invalid_snapshot')];
        }

        if ($event_code === 'ineligible_user') {
            return ['code' => 'ineligible_user', 'label' => self::get_snapshot_miss_reason_label('ineligible_user')];
        }

        if ($event_code === 'write_failed') {
            return ['code' => 'write_failed', 'label' => self::get_snapshot_miss_reason_label('write_failed')];
        }

        if ($event_code === 'written') {
            return ['code' => 'expired_or_evicted', 'label' => self::get_snapshot_miss_reason_label('expired_or_evicted')];
        }

        $user = get_user_by('id', $user_id);
        if (!($user instanceof WP_User)) {
            return ['code' => 'user_deleted', 'label' => self::get_snapshot_miss_reason_label('user_deleted')];
        }

        if (!self::is_snapshot_eligible_user($user)) {
            return ['code' => 'ineligible_user', 'label' => self::get_snapshot_miss_reason_label('ineligible_user')];
        }

        return ['code' => 'not_prepared', 'label' => self::get_snapshot_miss_reason_label('not_prepared')];
    }

    /**
     * @param array{code:string,label:string} $miss_reason
     */
    private static function build_snapshot_miss_message(array $miss_reason): string
    {
        $code = sanitize_key((string) ($miss_reason['code'] ?? ''));

        if ($code === 'manual_clear') {
            return 'Login snapshot MISS karena dibersihkan manual dari panel admin. Login berikutnya akan fallback ke auth WordPress lalu mencoba hydrate ulang.';
        }

        if ($code === 'identifier_changed') {
            return 'Login snapshot MISS karena identifier login siswa berubah, seperti login, email, atau NISN. Snapshot lama sengaja dihapus agar tidak melayani identifier yang usang.';
        }

        if ($code === 'password_changed') {
            return 'Login snapshot MISS karena password siswa berubah. Snapshot lama sengaja dihapus agar hash password terbaru yang dipakai.';
        }

        if ($code === 'password_mismatch') {
            return 'Login snapshot dilewati karena hash password di snapshot tidak cocok. Jalur login akan fallback ke auth WordPress untuk memverifikasi password terbaru.';
        }

        if ($code === 'role_changed') {
            return 'Login snapshot MISS karena role user berubah, sehingga snapshot login siswa lama dibersihkan.';
        }

        if ($code === 'user_deleted') {
            return 'Login snapshot MISS karena user siswa sudah dihapus.';
        }

        if ($code === 'invalid_payload') {
            return 'Login snapshot MISS karena payload Redis sebelumnya tidak valid, sehingga key lama dibuang dan akan dihydrate ulang bila login berhasil.';
        }

        if ($code === 'invalid_snapshot') {
            return 'Login snapshot MISS karena snapshot yang dibangun tidak valid dan dibersihkan kembali.';
        }

        if ($code === 'ineligible_user') {
            return 'Login snapshot MISS karena user ini bukan siswa yang eligible untuk login snapshot.';
        }

        if ($code === 'write_failed') {
            return 'Login snapshot MISS karena penulisan ke Redis gagal atau hanya parsial, sehingga key dibersihkan kembali agar tidak setengah jadi.';
        }

        if ($code === 'expired_or_evicted') {
            return 'Login snapshot MISS karena key sebelumnya kemungkinan sudah expired atau ter-evict. Login berikutnya akan fallback ke auth WordPress lalu mencoba hydrate ulang.';
        }

        return 'Login snapshot belum ada. Jalur login akan fallback ke auth WordPress lalu hydrate ulang bila memungkinkan.';
    }

    private static function write_snapshot_event_marker(int $user_id, string $event): void
    {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return;
        }

        $redis = self::snapshot_redis();
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
            self::event_storage_key($user_id),
            self::SNAPSHOT_EVENT_REDIS_TTL_SECONDS,
            $payload
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function read_snapshot_event_marker(int $user_id, Redis $redis): ?array
    {
        $raw_event = $redis->get(self::event_storage_key($user_id));
        if (!is_string($raw_event) || trim($raw_event) === '') {
            return null;
        }

        $decoded = json_decode($raw_event, true);
        if (!is_array($decoded)) {
            $redis->del(self::event_storage_key($user_id));
            return null;
        }

        return $decoded;
    }

    private static function is_freshness_eligible(
        string $snapshot_status,
        string $snapshot_miss_reason,
        int $ttl_seconds,
        string $repair_status,
        int $required_ttl_seconds
    ): bool {
        $snapshot_status = sanitize_key($snapshot_status);
        $snapshot_miss_reason = sanitize_key($snapshot_miss_reason);
        $repair_status = sanitize_key($repair_status);

        if (in_array($snapshot_status, ['miss', 'invalid'], true)) {
            return true;
        }

        if ($repair_status === 'queued_auto_heal') {
            return true;
        }

        if ($required_ttl_seconds > 0 && $snapshot_status === 'ready' && $ttl_seconds >= 0 && $ttl_seconds < $required_ttl_seconds) {
            return true;
        }

        return false;
    }

    private static function resolve_user_id_from_identifier(string $identifier): int
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '') {
            return 0;
        }

        if (is_email($identifier)) {
            $by_email = get_user_by('email', sanitize_email($identifier));
            if ($by_email instanceof WP_User) {
                return (int) $by_email->ID;
            }
        }

        $by_login = get_user_by('login', sanitize_user($identifier, true));
        if ($by_login instanceof WP_User) {
            return (int) $by_login->ID;
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
            return absint($by_nisn_ids[0]);
        }

        if (strpos($identifier, '@') === false) {
            $fallback_email = sanitize_email($identifier . '@' . self::FALLBACK_EMAIL_DOMAIN);
            if ($fallback_email !== '') {
                $fallback_user = get_user_by('email', $fallback_email);
                if ($fallback_user instanceof WP_User) {
                    return (int) $fallback_user->ID;
                }
            }
        }

        return 0;
    }
}
