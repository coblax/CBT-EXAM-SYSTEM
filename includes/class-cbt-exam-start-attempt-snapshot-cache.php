<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CBT_Cache')) {
    require_once __DIR__ . '/class-cbt-cache.php';
}

class CBT_Exam_Start_Attempt_Snapshot_Cache
{
    private const START_REDIS_TTL_SECONDS = 44100;
    private const START_REDIS_DEFAULT_HOST = '127.0.0.1';
    private const START_REDIS_DEFAULT_PORT = 6379;
    private const START_REDIS_DEFAULT_DATABASE = 2;
    private const START_REDIS_PREFIX = 'cbt_exam_start_attempt:';
    private const START_REDIS_TIMEOUT = 1.5;

    /** @var Redis|false|null */
    private static $start_snapshot_redis = null;
    /** @var bool */
    private static $start_snapshot_redis_connection_attempted = false;
    /** @var string */
    private static $start_snapshot_redis_last_connection_error = '';

    public static function is_available(): bool
    {
        return self::start_snapshot_redis() instanceof Redis;
    }

    /**
     * @return array{
     *   exam_id:int,
     *   redis_available:bool,
     *   redis_error:string,
     *   redis_host:string,
     *   redis_database:int,
     *   revision_meta:array{exam_id:int,version:int,invalidated_at:string,signature:string},
     *   storage_key:string,
     *   snapshot_exists:bool,
     *   snapshot_valid:bool,
     *   snapshot_status:string,
     *   snapshot_message:string,
     *   snapshot_item_count:int,
     *   snapshot_payload_bytes:int,
     *   snapshot_ttl_seconds:int,
     *   question_count:int,
     *   duration_minutes:int,
     *   show_student_result:int,
     *   enable_calculator:int
     * }
     */
    public static function get_exam_snapshot_diagnostics(int $exam_id): array
    {
        $exam_id = absint($exam_id);
        $revision_meta = self::exam_revision_meta($exam_id);
        $storage_key = ($exam_id > 0 && (int) ($revision_meta['exam_id'] ?? 0) === $exam_id)
            ? self::storage_key($exam_id, $revision_meta)
            : '';
        $settings = self::start_snapshot_redis_settings();
        $redis = self::start_snapshot_redis();
        $snapshot_exists = false;
        $snapshot_valid = false;
        $snapshot_item_count = 0;
        $snapshot_payload_bytes = 0;
        $snapshot_ttl_seconds = -2;
        $question_count = 0;
        $duration_minutes = 0;
        $show_student_result = 0;
        $enable_calculator = 0;
        $snapshot_status = 'idle';
        $snapshot_message = 'Masukkan Exam ID untuk memeriksa start snapshot.';

        if ($exam_id <= 0) {
            return [
                'exam_id' => 0,
                'redis_available' => $redis instanceof Redis,
                'redis_error' => self::$start_snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::START_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::START_REDIS_DEFAULT_DATABASE),
                'revision_meta' => $revision_meta,
                'storage_key' => '',
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => $snapshot_status,
                'snapshot_message' => $snapshot_message,
                'snapshot_item_count' => 0,
                'snapshot_payload_bytes' => 0,
                'snapshot_ttl_seconds' => -2,
                'question_count' => 0,
                'duration_minutes' => 0,
                'show_student_result' => 0,
                'enable_calculator' => 0,
            ];
        }

        if (!$redis instanceof Redis) {
            return [
                'exam_id' => $exam_id,
                'redis_available' => false,
                'redis_error' => self::$start_snapshot_redis_last_connection_error,
                'redis_host' => (string) ($settings['host'] ?? self::START_REDIS_DEFAULT_HOST),
                'redis_database' => (int) ($settings['database'] ?? self::START_REDIS_DEFAULT_DATABASE),
                'revision_meta' => $revision_meta,
                'storage_key' => $storage_key,
                'snapshot_exists' => false,
                'snapshot_valid' => false,
                'snapshot_status' => 'unavailable',
                'snapshot_message' => 'Redis start snapshot tidak tersedia. Start attempt akan fallback ke jalur legacy.',
                'snapshot_item_count' => 0,
                'snapshot_payload_bytes' => 0,
                'snapshot_ttl_seconds' => -2,
                'question_count' => 0,
                'duration_minutes' => 0,
                'show_student_result' => 0,
                'enable_calculator' => 0,
            ];
        }

        $raw_payload = $storage_key !== '' ? $redis->get($storage_key) : false;
        if (is_string($raw_payload) && trim($raw_payload) !== '') {
            $snapshot_exists = true;
            $snapshot_payload_bytes = strlen($raw_payload);
            if (method_exists($redis, 'ttl')) {
                $snapshot_ttl_seconds = (int) $redis->ttl($storage_key);
            }

            $decoded = json_decode($raw_payload, true);
            if (is_array($decoded)) {
                $stored_exam_id = absint($decoded['exam_id'] ?? 0);
                $stored_signature = is_scalar($decoded['revision_signature'] ?? null)
                    ? (string) $decoded['revision_signature']
                    : '';
                $expected_signature = self::revision_signature($revision_meta);
                $question_ids = self::normalize_question_ids($decoded['question_ids'] ?? []);
                $snapshot_item_count = count($question_ids);
                $question_count = max(0, (int) ($decoded['question_count'] ?? $snapshot_item_count));
                $duration_minutes = max(0, (int) ($decoded['duration_minutes'] ?? 0));
                $show_student_result = !empty($decoded['show_student_result']) ? 1 : 0;
                $enable_calculator = !empty($decoded['enable_calculator']) ? 1 : 0;
                $snapshot_valid = $stored_exam_id === $exam_id && $stored_signature === $expected_signature;
            }
        }

        if ($snapshot_valid) {
            $snapshot_status = 'ready';
            $snapshot_message = 'Start snapshot Redis siap dipakai untuk kontrak start_attempt.';
        } elseif ($snapshot_exists) {
            $snapshot_status = 'invalid';
            $snapshot_message = 'Start snapshot ditemukan tetapi signature/revision tidak cocok dan akan diabaikan.';
        } else {
            $snapshot_status = 'miss';
            $snapshot_message = 'Start snapshot belum ada untuk revision exam ini. Start attempt akan fallback lalu dapat dipanaskan ulang.';
        }

        return [
            'exam_id' => $exam_id,
            'redis_available' => true,
            'redis_error' => self::$start_snapshot_redis_last_connection_error,
            'redis_host' => (string) ($settings['host'] ?? self::START_REDIS_DEFAULT_HOST),
            'redis_database' => (int) ($settings['database'] ?? self::START_REDIS_DEFAULT_DATABASE),
            'revision_meta' => $revision_meta,
            'storage_key' => $storage_key,
            'snapshot_exists' => $snapshot_exists,
            'snapshot_valid' => $snapshot_valid,
            'snapshot_status' => $snapshot_status,
            'snapshot_message' => $snapshot_message,
            'snapshot_item_count' => $snapshot_item_count,
            'snapshot_payload_bytes' => $snapshot_payload_bytes,
            'snapshot_ttl_seconds' => $snapshot_ttl_seconds,
            'question_count' => $question_count,
            'duration_minutes' => $duration_minutes,
            'show_student_result' => $show_student_result,
            'enable_calculator' => $enable_calculator,
        ];
    }

    /**
     * @param callable(int):array<string,mixed> $producer
     * @return array<string,mixed>
     */
    public static function get_exam_snapshot(int $exam_id, callable $producer): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return [];
        }

        $payload = self::read_exam_snapshot($exam_id, $redis_available);
        if ($payload !== null) {
            return $payload;
        }

        $payload = self::normalize_snapshot_payload($producer($exam_id));
        if ($redis_available) {
            self::write_exam_snapshot($exam_id, $payload);
        }

        return $payload;
    }

    /**
     * @param callable(int):array<string,mixed> $producer
     */
    public static function warm_exam_snapshot(int $exam_id, callable $producer): void
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return;
        }

        $redis = self::start_snapshot_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $payload = self::normalize_snapshot_payload($producer($exam_id));
        self::write_exam_snapshot($exam_id, $payload);
    }

    public static function clear_exam_snapshot(int $exam_id): int
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return 0;
        }

        $redis = self::start_snapshot_redis();
        if (!$redis instanceof Redis) {
            return 0;
        }

        $keys = self::collect_exam_storage_keys($redis, $exam_id);
        if (empty($keys)) {
            return 0;
        }

        return (int) $redis->del(...$keys);
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function read_exam_snapshot(int $exam_id, ?bool &$redis_available = null): ?array
    {
        $redis_available = false;
        $redis = self::start_snapshot_redis();
        if (!$redis instanceof Redis) {
            return null;
        }

        $redis_available = true;
        $revision_meta = self::exam_revision_meta($exam_id);
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            return null;
        }

        $storage_key = self::storage_key($exam_id, $revision_meta);
        $raw_payload = $redis->get($storage_key);
        if (!is_string($raw_payload) || trim($raw_payload) === '') {
            return null;
        }

        $decoded = json_decode($raw_payload, true);
        if (!is_array($decoded)) {
            $redis->del($storage_key);
            return null;
        }

        $stored_exam_id = absint($decoded['exam_id'] ?? 0);
        $stored_signature = is_scalar($decoded['revision_signature'] ?? null)
            ? (string) $decoded['revision_signature']
            : '';
        $expected_signature = self::revision_signature($revision_meta);
        if ($stored_exam_id !== $exam_id || $stored_signature !== $expected_signature) {
            $redis->del($storage_key);
            return null;
        }

        $payload = self::normalize_snapshot_payload($decoded);
        $redis->expire($storage_key, self::START_REDIS_TTL_SECONDS);

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function write_exam_snapshot(int $exam_id, array $payload): void
    {
        $redis = self::start_snapshot_redis();
        if (!$redis instanceof Redis) {
            return;
        }

        $revision_meta = self::exam_revision_meta($exam_id);
        if ((int) ($revision_meta['exam_id'] ?? 0) !== $exam_id) {
            return;
        }

        $storage_key = self::storage_key($exam_id, $revision_meta);
        $encoded_payload = wp_json_encode(array_merge($payload, [
            'exam_id' => $exam_id,
            'revision_signature' => self::revision_signature($revision_meta),
        ]));
        if (!is_string($encoded_payload) || $encoded_payload === '') {
            return;
        }

        $redis->setEx($storage_key, self::START_REDIS_TTL_SECONDS, $encoded_payload);
    }

    /**
     * @return array<int,string>
     */
    private static function collect_exam_storage_keys(Redis $redis, int $exam_id): array
    {
        $exam_id = absint($exam_id);
        if ($exam_id <= 0) {
            return [];
        }

        $pattern = self::START_REDIS_PREFIX . 'exam:' . $exam_id . ':';
        $keys = [];

        if (method_exists($redis, 'scan')) {
            try {
                $iterator = null;
                do {
                    $batch = $redis->scan($iterator, $pattern . '*', 100);
                    if (is_array($batch)) {
                        foreach ($batch as $key) {
                            if (is_string($key) && $key !== '') {
                                $keys[$key] = $key;
                            }
                        }
                    }
                } while ($iterator !== 0 && $iterator !== null);
            } catch (Throwable $throwable) {
                $keys = [];
            }
        }

        if (empty($keys) && isset($GLOBALS['cbt_test_redis_storage']) && is_array($GLOBALS['cbt_test_redis_storage'])) {
            foreach (array_keys($GLOBALS['cbt_test_redis_storage']) as $key) {
                if (!is_string($key) || strpos($key, $pattern) !== 0) {
                    continue;
                }

                $keys[$key] = $key;
            }
        }

        if (empty($keys)) {
            $revision_meta = self::exam_revision_meta($exam_id);
            if ((int) ($revision_meta['exam_id'] ?? 0) === $exam_id) {
                $storage_key = self::storage_key($exam_id, $revision_meta);
                $keys[$storage_key] = $storage_key;
            }
        }

        return array_values($keys);
    }

    /**
     * @return array{exam_id:int,version:int,invalidated_at:string,signature:string}
     */
    private static function exam_revision_meta(int $exam_id): array
    {
        $meta = CBT_Cache::get_exam_revision_meta($exam_id);

        return [
            'exam_id' => absint($meta['exam_id'] ?? $exam_id),
            'version' => max(1, (int) ($meta['version'] ?? 1)),
            'invalidated_at' => is_scalar($meta['invalidated_at'] ?? null)
                ? (string) $meta['invalidated_at']
                : '',
            'signature' => is_scalar($meta['signature'] ?? null)
                ? (string) $meta['signature']
                : '',
        ];
    }

    /**
     * @param array{exam_id:int,version:int,invalidated_at:string,signature:string} $revision_meta
     */
    private static function storage_key(int $exam_id, array $revision_meta): string
    {
        return self::START_REDIS_PREFIX
            . 'exam:' . $exam_id
            . ':rev:' . max(1, (int) ($revision_meta['version'] ?? 1))
            . ':' . md5(self::revision_signature($revision_meta));
    }

    /**
     * @param array{exam_id:int,version:int,invalidated_at:string,signature:string} $revision_meta
     */
    private static function revision_signature(array $revision_meta): string
    {
        $signature = trim((string) ($revision_meta['signature'] ?? ''));
        if ($signature !== '') {
            return $signature;
        }

        return implode('|', [
            (int) ($revision_meta['exam_id'] ?? 0),
            max(1, (int) ($revision_meta['version'] ?? 1)),
            (string) ($revision_meta['invalidated_at'] ?? ''),
        ]);
    }

    /**
     * @param mixed $payload
     * @return array<string,mixed>
     */
    private static function normalize_snapshot_payload($payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        $normalized = [
            'exam_id' => absint($payload['exam_id'] ?? 0),
            'revision_signature' => is_scalar($payload['revision_signature'] ?? null)
                ? (string) $payload['revision_signature']
                : '',
            'question_ids' => self::normalize_question_ids($payload['question_ids'] ?? []),
            'question_count' => max(0, (int) ($payload['question_count'] ?? count((array) ($payload['question_ids'] ?? [])))),
            'question_number_map' => self::normalize_question_number_map($payload['question_number_map'] ?? []),
            'randomize_questions' => !empty($payload['randomize_questions']) ? 1 : 0,
            'randomize_options' => !empty($payload['randomize_options']) ? 1 : 0,
            'duration_minutes' => max(0, (int) ($payload['duration_minutes'] ?? 0)),
            'show_student_result' => !empty($payload['show_student_result']) ? 1 : 0,
            'enable_calculator' => !empty($payload['enable_calculator']) ? 1 : 0,
            'option_randomization_tokens_by_question' => self::normalize_option_randomization_tokens_map(
                $payload['option_randomization_tokens_by_question'] ?? []
            ),
        ];

        return $normalized;
    }

    /**
     * @param mixed $question_ids
     * @return array<int,int>
     */
    private static function normalize_question_ids($question_ids): array
    {
        if (!is_array($question_ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $question_ids), static function (int $question_id): bool {
            return $question_id > 0;
        })));
    }

    /**
     * @param mixed $question_number_map
     * @return array<int,int>
     */
    private static function normalize_question_number_map($question_number_map): array
    {
        if (!is_array($question_number_map)) {
            return [];
        }

        $normalized = [];
        foreach ($question_number_map as $question_id => $question_number) {
            $safe_question_id = (int) $question_id;
            $safe_question_number = (int) $question_number;
            if ($safe_question_id <= 0 || $safe_question_number <= 0) {
                continue;
            }

            $normalized[$safe_question_id] = $safe_question_number;
        }

        return $normalized;
    }

    /**
     * @param mixed $token_map
     * @return array<int,array<int,string>>
     */
    private static function normalize_option_randomization_tokens_map($token_map): array
    {
        if (!is_array($token_map)) {
            return [];
        }

        $normalized = [];
        foreach ($token_map as $question_id => $tokens) {
            $safe_question_id = (int) $question_id;
            if ($safe_question_id <= 0 || !is_array($tokens)) {
                continue;
            }

            $normalized_tokens = [];
            $seen_tokens = [];
            foreach ($tokens as $token) {
                if (!is_scalar($token)) {
                    continue;
                }

                $safe_token = trim((string) $token);
                if ($safe_token === '' || isset($seen_tokens[$safe_token])) {
                    continue;
                }

                $seen_tokens[$safe_token] = true;
                $normalized_tokens[] = $safe_token;
            }

            if (!empty($normalized_tokens)) {
                $normalized[$safe_question_id] = $normalized_tokens;
            }
        }

        return $normalized;
    }

    /**
     * @return Redis|null
     */
    private static function start_snapshot_redis(): ?Redis
    {
        if (self::$start_snapshot_redis_connection_attempted) {
            return (self::$start_snapshot_redis instanceof Redis) ? self::$start_snapshot_redis : null;
        }

        self::$start_snapshot_redis_connection_attempted = true;
        self::$start_snapshot_redis = false;
        self::$start_snapshot_redis_last_connection_error = '';

        if (!class_exists('Redis')) {
            self::$start_snapshot_redis_last_connection_error = 'Redis extension not loaded.';
            return null;
        }

        $config = self::start_snapshot_redis_settings();

        try {
            $redis = new Redis();
            if ((string) ($config['scheme'] ?? '') === 'unix') {
                $redis->connect((string) ($config['host'] ?? ''), 0, (float) ($config['timeout'] ?? self::START_REDIS_TIMEOUT));
            } else {
                $redis->connect(
                    (string) ($config['host'] ?? self::START_REDIS_DEFAULT_HOST),
                    (int) ($config['port'] ?? self::START_REDIS_DEFAULT_PORT),
                    (float) ($config['timeout'] ?? self::START_REDIS_TIMEOUT)
                );
            }

            $password = (string) ($config['password'] ?? '');
            if ($password !== '') {
                $redis->auth($password);
            }

            $database = (int) ($config['database'] ?? self::START_REDIS_DEFAULT_DATABASE);
            if ($database >= 0) {
                $redis->select($database);
            }

            $ping = $redis->ping();
            if ($ping === false) {
                throw new RuntimeException('PING ke Redis start snapshot gagal.');
            }

            self::$start_snapshot_redis = $redis;
            return $redis;
        } catch (Throwable $throwable) {
            self::$start_snapshot_redis_last_connection_error = 'Koneksi Redis start snapshot gagal: ' . $throwable->getMessage();
            self::$start_snapshot_redis = false;
            return null;
        }
    }

    /**
     * @return array{host:string,port:int,database:int,password:string,timeout:float,scheme:string}
     */
    private static function start_snapshot_redis_settings(): array
    {
        $host = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_HOST', ''));
        if ($host === '') {
            $host = trim((string) self::constant_scalar('WP_REDIS_HOST', self::START_REDIS_DEFAULT_HOST));
        }

        $port = (int) self::constant_scalar('CBT_RUNTIME_REDIS_PORT', 0);
        if ($port <= 0) {
            $port = (int) self::constant_scalar('WP_REDIS_PORT', self::START_REDIS_DEFAULT_PORT);
        }
        if ($port <= 0) {
            $port = self::START_REDIS_DEFAULT_PORT;
        }

        $database = (int) self::constant_scalar('CBT_RUNTIME_REDIS_DB', -1);
        if ($database < 0) {
            $database = (int) self::constant_scalar('WP_REDIS_DATABASE', self::START_REDIS_DEFAULT_DATABASE);
        }
        if ($database < 0) {
            $database = self::START_REDIS_DEFAULT_DATABASE;
        }

        $password = trim((string) self::constant_scalar('CBT_RUNTIME_REDIS_PASSWORD', ''));
        if ($password === '') {
            $password = trim((string) self::constant_scalar('WP_REDIS_PASSWORD', ''));
        }

        $timeout = (float) self::constant_scalar('CBT_RUNTIME_REDIS_TIMEOUT', self::START_REDIS_TIMEOUT);
        if ($timeout <= 0) {
            $timeout = self::START_REDIS_TIMEOUT;
        }

        $scheme = 'tcp';
        if (str_starts_with($host, '/')) {
            $scheme = 'unix';
        }

        return [
            'host' => $host !== '' ? $host : self::START_REDIS_DEFAULT_HOST,
            'port' => $port,
            'database' => $database,
            'password' => $password,
            'timeout' => $timeout,
            'scheme' => $scheme,
        ];
    }

    private static function constant_scalar(string $name, $default)
    {
        if (defined($name)) {
            return constant($name);
        }

        return $default;
    }
}
