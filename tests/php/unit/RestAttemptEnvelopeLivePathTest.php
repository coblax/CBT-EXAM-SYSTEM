<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestAttemptEnvelopeLivePathTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_get_attempt_for_submission_prefers_runtime_envelope_without_attempt_query(): void
    {
        $this->bootstrapRuntimeRest();
        $this->useFakeRuntimeRedisClient();

        CBT_Runtime::ensure_attempt_state([
            'id' => 45,
            'exam_id' => 9,
            'student_id' => 7,
            'status' => 'in_progress',
            'started_at' => '2026-03-24 11:00:00',
            'extra_time_minutes' => 5,
        ], 90);

        global $wpdb;
        $wpdb = new RestAttemptEnvelopeFakeWpdb();

        $method = new ReflectionMethod('CBT_REST', 'get_attempt_for_submission');
        $method->setAccessible(true);

        $attempt = $method->invoke(null, 45, 7);

        self::assertIsArray($attempt);
        self::assertSame(45, $attempt['id']);
        self::assertSame(9, $attempt['exam_id']);
        self::assertSame(7, $attempt['student_id']);
        self::assertSame('in_progress', $attempt['status']);
        self::assertSame(5, $attempt['extra_time_minutes']);
        self::assertSame(90, $attempt['exam_duration_minutes']);
        self::assertSame(0, $wpdb->getRowCalls);
        self::assertSame(0, $wpdb->getVarCalls);
    }

    #[RunInSeparateProcess]
    public function test_get_attempt_for_submission_hydrates_runtime_after_db_fallback(): void
    {
        $this->bootstrapRuntimeRest();
        $this->useFakeRuntimeRedisClient();

        global $wpdb;
        $wpdb = new RestAttemptEnvelopeFakeWpdb(
            [
                46 => [
                    'id' => 46,
                    'exam_id' => 10,
                    'student_id' => 7,
                    'status' => 'in_progress',
                    'started_at' => '2026-03-24 12:00:00',
                    'extra_time_minutes' => 15,
                ],
            ],
            [
                10 => 75,
            ]
        );

        $method = new ReflectionMethod('CBT_REST', 'get_attempt_for_submission');
        $method->setAccessible(true);

        $attempt = $method->invoke(null, 46, 7);

        self::assertIsArray($attempt);
        self::assertSame(46, $attempt['id']);
        self::assertSame('in_progress', $attempt['status']);
        self::assertSame(1, $wpdb->getRowCalls);
        self::assertSame(1, $wpdb->getVarCalls);
        self::assertGreaterThanOrEqual(1, $wpdb->getResultsCalls);

        $meta = CBT_Runtime::get_attempt_meta(46, $stateFound);
        self::assertTrue($stateFound);
        self::assertSame(46, $meta['attempt_id']);
        self::assertSame(10, $meta['exam_id']);
        self::assertSame(7, $meta['student_id']);
        self::assertSame(15, $meta['extra_time_minutes']);
        self::assertSame(90, $meta['duration_minutes']);
    }

    #[RunInSeparateProcess]
    public function test_get_attempt_for_question_revision_prefers_runtime_envelope_for_student_attempt(): void
    {
        $this->bootstrapRuntimeRest();
        $this->useFakeRuntimeRedisClient();

        CBT_Runtime::ensure_attempt_state([
            'id' => 47,
            'exam_id' => 11,
            'student_id' => 7,
            'status' => 'in_progress',
            'started_at' => '2026-03-24 13:00:00',
            'extra_time_minutes' => 10,
        ], 80);

        global $wpdb;
        $wpdb = new RestAttemptEnvelopeFakeWpdb();

        $method = new ReflectionMethod('CBT_REST', 'get_attempt_for_question_revision');
        $method->setAccessible(true);

        $attempt = $method->invoke(null, 47, 7, 'siswa');

        self::assertIsArray($attempt);
        self::assertSame(47, $attempt['id']);
        self::assertSame(11, $attempt['exam_id']);
        self::assertSame(7, $attempt['student_id']);
        self::assertSame(80, $attempt['exam_duration_minutes']);
        self::assertSame(10, $attempt['extra_time_minutes']);
        self::assertSame(0, $wpdb->getRowCalls);
    }

    #[RunInSeparateProcess]
    public function test_get_attempt_for_question_payload_hydrates_runtime_after_db_fallback_for_student(): void
    {
        $this->bootstrapRuntimeRest();
        $this->useFakeRuntimeRedisClient();

        global $wpdb;
        $wpdb = new RestAttemptEnvelopeFakeWpdb(
            [
                48 => [
                    'id' => 48,
                    'exam_id' => 12,
                    'student_id' => 7,
                    'status' => 'in_progress',
                    'question_order' => '[101,102]',
                    'option_order' => '{"101":["1","2"]}',
                    'score' => 0,
                    'max_score' => 0,
                    'started_at' => '2026-03-24 14:00:00',
                    'extra_time_minutes' => 0,
                ],
            ]
        );

        $method = new ReflectionMethod('CBT_REST', 'get_attempt_for_question_payload');
        $method->setAccessible(true);

        $attempt = $method->invoke(null, 48, 7, 'siswa', 'wp_cbt_attempts', 95);

        self::assertIsArray($attempt);
        self::assertSame(48, $attempt['id']);
        self::assertSame('[101,102]', $attempt['question_order']);
        self::assertSame(1, $wpdb->getRowCalls);

        $meta = CBT_Runtime::get_attempt_meta(48, $stateFound);
        self::assertTrue($stateFound);
        self::assertSame(48, $meta['attempt_id']);
        self::assertSame(95, $meta['duration_minutes']);
    }

    private function bootstrapRuntimeRest(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';
    }

    private function useFakeRuntimeRedisClient(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Runtime_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}

final class RestAttemptEnvelopeFakeWpdb
{
    public string $prefix = 'wp_';

    /** @var array<int,array<string,mixed>> */
    private array $attemptRows;

    /** @var array<int,int> */
    private array $examDurations;

    public int $getRowCalls = 0;
    public int $getVarCalls = 0;
    public int $getResultsCalls = 0;

    /**
     * @param array<int,array<string,mixed>> $attemptRows
     * @param array<int,int> $examDurations
     */
    public function __construct(array $attemptRows = [], array $examDurations = [])
    {
        $this->attemptRows = $attemptRows;
        $this->examDurations = $examDurations;
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
        $this->getRowCalls++;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $attemptId = isset($args[0]) ? (int) $args[0] : 0;
        $attempt = $this->attemptRows[$attemptId] ?? null;

        return is_array($attempt) ? $attempt : null;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $this->getResultsCalls++;
        return [];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_var($prepared)
    {
        $this->getVarCalls++;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $examId = isset($args[0]) ? (int) $args[0] : 0;
        return $this->examDurations[$examId] ?? 0;
    }
}

if (!class_exists('CBT_Runtime_Test_Redis_Client')) {
    class CBT_Runtime_Test_Redis_Client extends CBT_Test_Redis_Client
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

        public function hMGet($key, $fields): array
        {
            $key = (string) $key;
            $items = [];
            foreach ((array) $fields as $field) {
                $items[(string) $field] = $this->hashStorage[$key][(string) $field] ?? false;
            }

            return $items;
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
    }
}
