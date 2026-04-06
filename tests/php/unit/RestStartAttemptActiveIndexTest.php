<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestStartAttemptActiveIndexTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_start_attempt_prefers_active_attempt_index_and_skips_latest_attempt_query(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        CBT_Runtime::ensure_attempt_state([
            'id' => 81,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
            'started_at' => '2026-04-02 10:00:00',
            'question_order' => '[]',
            'option_order' => '',
            'extra_time_minutes' => 0,
        ], 90);
        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 81,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
        ]);

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: [
                'id' => 99,
                'status' => 'completed',
                'started_at' => '2026-04-02 08:00:00',
                'finished_at' => '2026-04-02 09:00:00',
                'question_order' => '[]',
                'option_order' => '',
                'extra_time_minutes' => 0,
            ]
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('resumed', $response['status']);
        self::assertSame(81, $response['attempt_id']);
        self::assertSame(0, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, $wpdb->attemptByIdQueryCount);
        self::assertSame(81, CBT_Active_Attempt_Index::get_active_attempt_id(7, 15));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_clears_stale_active_index_and_falls_back_to_completed_guard(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 91,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
        ]);

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: [
                'id' => 92,
                'status' => 'completed',
                'started_at' => '2026-04-02 08:00:00',
                'finished_at' => '2026-04-02 09:00:00',
                'question_order' => '[]',
                'option_order' => '',
                'extra_time_minutes' => 0,
            ],
            attemptRowsById: []
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertTrue(is_wp_error($response));
        self::assertSame('attempt_already_completed', $response->get_error_code());
        self::assertSame(1, $wpdb->attemptByIdQueryCount);
        self::assertSame(1, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, CBT_Active_Attempt_Index::get_active_attempt_id(7, 15));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_writes_active_attempt_index_after_creating_new_attempt(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('started', $response['status']);
        self::assertSame(123, $response['attempt_id']);
        self::assertSame(1, $wpdb->latestAttemptQueryCount);
        self::assertSame(1, $wpdb->insertCalls);
        self::assertSame(123, CBT_Active_Attempt_Index::get_active_attempt_id(7, 15));
        self::assertArrayHasKey('cbt_attempt_session:attempt:123', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
        self::assertArrayHasKey('cbt_attempt_contract:attempt:123', (array) ($GLOBALS['cbt_test_redis_storage'] ?? []));
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_returns_resumed_attempt_when_lock_is_busy_but_active_index_is_ready(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];
        $GLOBALS['cbt_test_acquire_lock_result'] = false;

        CBT_Runtime::ensure_attempt_state([
            'id' => 81,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
            'started_at' => '2026-04-02 10:00:00',
            'question_order' => '[]',
            'option_order' => '',
            'extra_time_minutes' => 0,
        ], 90);
        CBT_Active_Attempt_Index::set_active_attempt([
            'id' => 81,
            'exam_id' => 15,
            'student_id' => 7,
            'status' => 'in_progress',
        ]);

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: []
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('resumed', $response['status']);
        self::assertSame(81, $response['attempt_id']);
        self::assertSame(0, $wpdb->latestAttemptQueryCount);
        self::assertSame(0, $wpdb->insertCalls);
    }

    #[RunInSeparateProcess]
    public function test_start_attempt_uses_ready_start_snapshot_without_hydrating_full_question_payload(): void
    {
        $this->bootstrapStartAttemptScaffold();
        $this->useFakeRuntimeRedisClient();
        $this->useFakeActiveAttemptRedisClient();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeAttemptSessionSnapshotRedis();
        $this->useFakeAttemptContractSnapshotRedis();

        $GLOBALS['cbt_test_rest_auth_user_id'] = 7;
        $GLOBALS['cbt_test_rest_auth_role'] = 'student';
        $GLOBALS['cbt_test_global_exam_token_meta'] = ['token' => ''];

        global $wpdb;
        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 1,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123,
            startSnapshotQuestionRows: [
                ['id' => 201, 'question_type' => 'multiple_choice', 'correct_text' => ''],
            ],
            startSnapshotOptionRows: [
                ['id' => 9001, 'question_id' => 201],
                ['id' => 9002, 'question_id' => 201],
                ['id' => 9003, 'question_id' => 201],
            ]
        );

        CBT_REST::warm_exam_start_attempt_snapshot(15);

        $wpdb = new RestStartAttemptActiveIndexFakeWpdb(
            examRow: [
                'id' => 15,
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'randomize_questions' => 0,
                'randomize_options' => 1,
                'target_kelas' => '',
            ],
            latestAttemptRow: null,
            attemptRowsById: [],
            insertId: 123
        );

        $response = CBT_REST::start_attempt(new WP_REST_Request([
            'exam_id' => 15,
        ]));

        self::assertFalse(is_wp_error($response));
        self::assertSame('started', $response['status']);
        self::assertSame(123, $response['attempt_id']);
        self::assertSame(0, $wpdb->questionQueryCount);
        self::assertSame(0, $wpdb->startSnapshotQuestionQueryCount);
        self::assertSame([201], json_decode((string) $wpdb->lastInsertData['question_order'], true));
        $optionOrder = json_decode((string) $wpdb->lastInsertData['option_order'], true);
        self::assertCount(1, $optionOrder);
        self::assertCount(3, $optionOrder['201']);
        self::assertSame(123, CBT_Active_Attempt_Index::get_active_attempt_id(7, 15));
    }

    private function bootstrapStartAttemptScaffold(): void
    {
        if (!class_exists('CBT_Auth')) {
            eval(<<<'PHP'
class CBT_Auth
{
    public static function current_user_id(\WP_REST_Request $request): int
    {
        return (int) ($GLOBALS['cbt_test_rest_auth_user_id'] ?? 0);
    }

    public static function current_user_role(\WP_REST_Request $request): string
    {
        return (string) ($GLOBALS['cbt_test_rest_auth_role'] ?? 'student');
    }

    public static function normalize_exam_token_input(string $token): string
    {
        return strtoupper(trim($token));
    }

    public static function get_global_exam_token(bool $auto_rotate = true): array
    {
        $meta = $GLOBALS['cbt_test_global_exam_token_meta'] ?? [];
        return is_array($meta) ? $meta : ['token' => ''];
    }

    public static function is_frontend_auto_exam_token_enabled(): bool
    {
        return false;
    }
}
PHP);
        }

        if (!class_exists('CBT_Cache')) {
            eval(<<<'PHP'
class CBT_Cache
{
    public static function acquire_lock(string $key, int $ttl, array $context = []): bool
    {
        if (array_key_exists('cbt_test_acquire_lock_result', $GLOBALS)) {
            return (bool) $GLOBALS['cbt_test_acquire_lock_result'];
        }

        return true;
    }

    public static function release_lock(string $key): void
    {
    }

    public static function invalidate_user(int $user_id): void
    {
    }

    public static function invalidate_attempt(int $attempt_id): void
    {
    }

    public static function remember(string $key, int $ttl, array $namespaces, callable $producer)
    {
        return $producer();
    }

    public static function namespace_exam(int $exam_id): string
    {
        return 'exam:' . $exam_id;
    }

    public static function get_exam_revision_meta(int $exam_id): array
    {
        return [
            'revision' => 1,
            'updated_at' => '2026-04-02 09:00:00',
        ];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-active-attempt-index.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';
    }

    private function useFakeRuntimeRedisClient(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Start_Attempt_Runtime_Redis_Client());

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeActiveAttemptRedisClient(): void
    {
        $reflection = new ReflectionClass(CBT_Active_Attempt_Index::class);

        $redisProperty = $reflection->getProperty('active_attempt_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('active_attempt_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('active_attempt_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeStartSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Exam_Start_Attempt_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('start_snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('start_snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('start_snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeAttemptSessionSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Attempt_Session_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeAttemptContractSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Attempt_Question_Contract_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}

final class RestStartAttemptActiveIndexFakeWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public int $examQueryCount = 0;
    public int $latestAttemptQueryCount = 0;
    public int $attemptByIdQueryCount = 0;
    public int $questionQueryCount = 0;
    public int $startSnapshotQuestionQueryCount = 0;
    public int $startSnapshotOptionQueryCount = 0;
    public int $insertCalls = 0;
    /** @var array<string,mixed> */
    public array $lastInsertData = [];

    /** @var array<string,mixed>|null */
    private ?array $examRow;

    /** @var array<string,mixed>|null */
    private ?array $latestAttemptRow;

    /** @var array<int,array<string,mixed>> */
    private array $attemptRowsById;

    /** @var array<int,array<string,mixed>> */
    private array $startSnapshotQuestionRows;

    /** @var array<int,array<string,mixed>> */
    private array $startSnapshotOptionRows;

    /**
     * @param array<string,mixed>|null $examRow
     * @param array<string,mixed>|null $latestAttemptRow
     * @param array<int,array<string,mixed>> $attemptRowsById
     * @param array<int,array<string,mixed>> $startSnapshotQuestionRows
     * @param array<int,array<string,mixed>> $startSnapshotOptionRows
     */
    public function __construct(?array $examRow, ?array $latestAttemptRow, array $attemptRowsById = [], int $insertId = 123, array $startSnapshotQuestionRows = [], array $startSnapshotOptionRows = [])
    {
        $this->examRow = $examRow;
        $this->latestAttemptRow = $latestAttemptRow;
        $this->attemptRowsById = $attemptRowsById;
        $this->insert_id = $insertId;
        $this->startSnapshotQuestionRows = $startSnapshotQuestionRows;
        $this->startSnapshotOptionRows = $startSnapshotOptionRows;
    }

    /** @return array<string,mixed> */
    public function prepare(string $query, ...$args): array
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_row($prepared, $output = null): ?array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (str_contains($query, 'FROM wp_cbt_exams')) {
            $this->examQueryCount++;
            return $this->examRow;
        }

        if (str_contains($query, "WHERE exam_id = %d AND student_id = %d AND status IN ('in_progress', 'completed')")) {
            $this->latestAttemptQueryCount++;
            return $this->latestAttemptRow;
        }

        if (str_contains($query, 'FROM wp_cbt_attempts') && str_contains($query, 'WHERE id = %d')) {
            $this->attemptByIdQueryCount++;
            $attemptId = isset($args[0]) ? (int) $args[0] : 0;
            return $this->attemptRowsById[$attemptId] ?? null;
        }

        return null;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;

        if (str_contains($query, 'SELECT q.id, q.question_type, q.correct_text')) {
            $this->startSnapshotQuestionQueryCount++;
            return $this->startSnapshotQuestionRows;
        }

        if (str_contains($query, 'SELECT id, question_id') && str_contains($query, 'FROM wp_cbt_options')) {
            $this->startSnapshotOptionQueryCount++;
            return $this->startSnapshotOptionRows;
        }

        if (str_contains($query, 'FROM wp_cbt_questions q')) {
            $this->questionQueryCount++;
            return [];
        }

        return [];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,string>|null $format
     */
    public function insert(string $table, array $data, ?array $format = null): bool
    {
        $this->insertCalls++;
        $this->lastInsertData = $data;
        return true;
    }
}

if (!class_exists('CBT_Start_Attempt_Runtime_Redis_Client')) {
    class CBT_Start_Attempt_Runtime_Redis_Client extends CBT_Test_Redis_Client
    {
        /** @var array<string,array<string,string>> */
        private array $hashStorage = [];

        /** @var array<string,array<string,int>> */
        private array $zsetStorage = [];

        public function exists($key, ...$other_keys): int
        {
            $keys = array_merge([(string) $key], array_map('strval', $other_keys));
            foreach ($keys as $safeKey) {
                if (array_key_exists($safeKey, $GLOBALS['cbt_test_redis_storage'])) {
                    return 1;
                }

                if (isset($this->hashStorage[$safeKey]) || isset($this->zsetStorage[$safeKey])) {
                    return 1;
                }
            }

            return 0;
        }

        public function hLen($key): int
        {
            return count($this->hashStorage[(string) $key] ?? []);
        }

        public function hMSet($key, $pairs): bool
        {
            $key = (string) $key;
            if (!isset($this->hashStorage[$key])) {
                $this->hashStorage[$key] = [];
            }

            foreach ((array) $pairs as $field => $value) {
                $this->hashStorage[$key][(string) $field] = (string) $value;
            }

            return true;
        }

        public function hSet($key, $field, $value): bool
        {
            $key = (string) $key;
            if (!isset($this->hashStorage[$key])) {
                $this->hashStorage[$key] = [];
            }

            $this->hashStorage[$key][(string) $field] = (string) $value;
            return true;
        }

        public function hDel($key, ...$fields): int
        {
            $key = (string) $key;
            $deleted = 0;
            foreach ($fields as $field) {
                $safeField = (string) $field;
                if (isset($this->hashStorage[$key][$safeField])) {
                    unset($this->hashStorage[$key][$safeField]);
                    $deleted++;
                }
            }

            return $deleted;
        }

        public function hGetAll($key): array
        {
            return $this->hashStorage[(string) $key] ?? [];
        }

        public function hMGet($key, $fields): array
        {
            $key = (string) $key;
            $items = [];
            foreach ((array) $fields as $field) {
                $items[(string) $field] = $this->hashStorage[$key][(string) $field] ?? false;
            }

            return $items;
        }

        public function zAdd($key, $score, $member, ...$extra_args): int
        {
            $key = (string) $key;
            if (!isset($this->zsetStorage[$key])) {
                $this->zsetStorage[$key] = [];
            }

            $this->zsetStorage[$key][(string) $member] = (int) $score;
            return 1;
        }

        public function zCard($key): int
        {
            return count($this->zsetStorage[(string) $key] ?? []);
        }

        public function zRange($key, $start, $end, $scores = false): array
        {
            $items = array_keys($this->zsetStorage[(string) $key] ?? []);
            sort($items);
            return $items;
        }

        public function zRangeByScore($key, $min, $max, $options = []): array
        {
            $items = [];
            foreach (($this->zsetStorage[(string) $key] ?? []) as $member => $score) {
                if ((int) $score <= (int) $max) {
                    $items[] = (string) $member;
                }
            }

            return $items;
        }

        public function zRem($key, ...$members): int
        {
            $key = (string) $key;
            $deleted = 0;
            foreach ($members as $member) {
                $safeMember = (string) $member;
                if (isset($this->zsetStorage[$key][$safeMember])) {
                    unset($this->zsetStorage[$key][$safeMember]);
                    $deleted++;
                }
            }

            return $deleted;
        }
    }
}
