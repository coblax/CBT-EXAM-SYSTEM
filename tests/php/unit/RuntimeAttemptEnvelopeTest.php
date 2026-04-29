<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

if (!class_exists('CBT_Cache')) {
    class CBT_Cache
    {
        /** @var array<int,int> */
        public static array $invalidatedAttemptIds = [];

        public static function invalidate_attempt(int $attempt_id): void
        {
            self::$invalidatedAttemptIds[] = $attempt_id;
        }
    }
}

require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';

final class RuntimeAttemptEnvelopeTest extends TestCase
{
    /** @var CBT_Runtime_Test_Redis_Client */
    private $runtimeRedis;

    /** @var CBT_Runtime_Test_Wpdb */
    private $runtimeWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        CBT_Cache::$invalidatedAttemptIds = [];
        $this->runtimeWpdb = new CBT_Runtime_Test_Wpdb();
        $GLOBALS['wpdb'] = $this->runtimeWpdb;
        $this->useFakeRuntimeRedisClient();
    }

    public function test_ensure_attempt_state_persists_attempt_envelope_with_extra_time(): void
    {
        $saved = CBT_Runtime::ensure_attempt_state([
            'id' => 45,
            'exam_id' => 9,
            'student_id' => 7,
            'status' => 'in_progress',
            'started_at' => '2026-03-24 11:00:00',
            'extra_time_minutes' => 15,
            'question_order' => '[101,102]',
            'option_order' => '{"101":["A","B"]}',
        ], 90);

        self::assertTrue($saved);

        $meta = CBT_Runtime::get_attempt_meta(45, $stateFound);

        self::assertTrue($stateFound);
        self::assertSame(45, $meta['attempt_id']);
        self::assertSame(9, $meta['exam_id']);
        self::assertSame(7, $meta['student_id']);
        self::assertSame('in_progress', $meta['status']);
        self::assertSame('2026-03-24 11:00:00', $meta['started_at']);
        self::assertSame(15, $meta['extra_time_minutes']);
        self::assertSame(90, $meta['duration_minutes']);
    }

    public function test_prefixed_key_caches_runtime_prefix(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $cachedPrefixProperty = $reflection->getProperty('cached_prefix');
        $cachedPrefixProperty->setAccessible(true);
        $cachedPrefixProperty->setValue(null, null);

        $runtimeSettingsMethod = $reflection->getMethod('runtime_settings');
        $runtimeSettingsMethod->setAccessible(true);
        $settings = $runtimeSettingsMethod->invoke(null);
        $expectedPrefix = (string) ($settings['prefix'] ?? 'cbt_runtime:');

        $prefixedKeyMethod = $reflection->getMethod('prefixed_key');
        $prefixedKeyMethod->setAccessible(true);

        self::assertSame($expectedPrefix . 'attempt:45:meta', $prefixedKeyMethod->invoke(null, 'attempt:45:meta'));
        self::assertSame($expectedPrefix, $cachedPrefixProperty->getValue());

        $cachedPrefixProperty->setValue(null, 'cached_test:');

        self::assertSame('cached_test:flush:due', $prefixedKeyMethod->invoke(null, ':flush:due'));
    }

    public function test_buffer_entries_does_not_flush_due_attempts_from_other_students(): void
    {
        $this->runtimeRedis->zAdd($this->runtimeKey('flush:due'), time() - 30, '999');
        $this->runtimeRedis->zAdd($this->runtimeKey('attempt:999:dirty'), time() - 30, '701');
        $this->runtimeRedis->hSet(
            $this->runtimeKey('attempt:999:answers'),
            '701',
            wp_json_encode([
                'question_id' => 701,
                'selected_option_ids' => '[1]',
                'answer_text' => '',
                'is_correct' => null,
                'score_awarded' => 0,
                'answered_at' => '2026-03-24 11:01:00',
            ])
        );

        $result = CBT_Runtime::buffer_entries($this->attemptFixture(45), 90, [
            [
                'question_id' => 101,
                'selected_option_ids' => '[2]',
                'answer_text' => '',
                'is_correct' => null,
                'score_awarded' => 0,
                'answered_at' => '2026-03-24 11:02:00',
            ],
        ]);

        self::assertSame(1, $result['runtime_used']);
        self::assertSame(1, $result['buffered']);
        self::assertSame(0, $result['flushed']);
        self::assertSame(1, $result['pending_count']);
        self::assertSame(0, $this->runtimeWpdb->getVarCalls);
        self::assertSame(0, $this->runtimeWpdb->queryCalls);
        self::assertContains('999', $this->runtimeRedis->zMembers($this->runtimeKey('flush:due')));
        self::assertContains('45', $this->runtimeRedis->zMembers($this->runtimeKey('flush:due')));
    }

    public function test_buffer_entries_still_flushes_current_attempt_at_threshold(): void
    {
        $entries = [];
        for ($question_id = 1; $question_id <= 10; $question_id++) {
            $entries[] = [
                'question_id' => $question_id,
                'selected_option_ids' => '[' . $question_id . ']',
                'answer_text' => '',
                'is_correct' => null,
                'score_awarded' => 0,
                'answered_at' => '2026-03-24 11:03:00',
            ];
        }

        $result = CBT_Runtime::buffer_entries($this->attemptFixture(46), 90, $entries);

        self::assertSame(1, $result['runtime_used']);
        self::assertSame(10, $result['buffered']);
        self::assertSame(10, $result['flushed']);
        self::assertSame(0, $result['pending_count']);
        self::assertSame(1, $this->runtimeWpdb->queryCalls);
        self::assertSame([46], CBT_Cache::$invalidatedAttemptIds);
    }

    public function test_cron_flush_uses_default_due_limit(): void
    {
        $flushDueKey = $this->runtimeKey('flush:due');
        for ($attempt_id = 1; $attempt_id <= 120; $attempt_id++) {
            $this->runtimeRedis->zAdd($flushDueKey, time() - 60, (string) $attempt_id);
        }

        CBT_Runtime::handle_cron_flush();

        self::assertSame(100, $this->runtimeWpdb->getVarCalls);
        self::assertCount(20, $this->runtimeRedis->zMembers($flushDueKey));
    }

    public function test_runtime_flush_due_cron_limit_is_clamped(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);
        $method = $reflection->getMethod('normalize_runtime_flush_due_cron_limit');
        $method->setAccessible(true);

        self::assertSame(1, $method->invoke(null, 0));
        self::assertSame(100, $method->invoke(null, 100));
        self::assertSame(1000, $method->invoke(null, 5000));
    }

    private function useFakeRuntimeRedisClient(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $this->runtimeRedis = new CBT_Runtime_Test_Redis_Client();
        $redisProperty->setValue(null, $this->runtimeRedis);

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');

        $cachedPrefixProperty = $reflection->getProperty('cached_prefix');
        $cachedPrefixProperty->setAccessible(true);
        $cachedPrefixProperty->setValue(null, null);
    }

    /**
     * @return array<string,mixed>
     */
    private function attemptFixture(int $attempt_id): array
    {
        return [
            'id' => $attempt_id,
            'exam_id' => 9,
            'student_id' => 7,
            'status' => 'in_progress',
            'started_at' => '2026-03-24 11:00:00',
            'extra_time_minutes' => 0,
        ];
    }

    private function runtimeKey(string $suffix): string
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);
        $method = $reflection->getMethod('prefixed_key');
        $method->setAccessible(true);

        return (string) $method->invoke(null, $suffix);
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

        public function set($key, $value, $options = null): bool
        {
            $GLOBALS['cbt_test_redis_storage'][(string) $key] = (string) $value;
            return true;
        }

        public function zCard($key): int
        {
            return count($this->zsetStorage[(string) $key] ?? []);
        }

        public function zRange($key, $start, $end, $scores = false): array
        {
            $items = $this->zsetStorage[(string) $key] ?? [];
            if (empty($items)) {
                return [];
            }

            asort($items, SORT_NUMERIC);
            $members = array_keys($items);
            $slice = ((int) $end < 0)
                ? array_slice($members, (int) $start)
                : array_slice($members, (int) $start, ((int) $end - (int) $start) + 1);

            if (!$scores) {
                return array_values($slice);
            }

            $scored = [];
            foreach ($slice as $member) {
                $scored[(string) $member] = (int) $items[(string) $member];
            }

            return $scored;
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
            $source = $this->zsetStorage[(string) $key] ?? [];
            asort($source, SORT_NUMERIC);
            foreach ($source as $member => $score) {
                if ((int) $score <= (int) $max) {
                    $items[] = (string) $member;
                }
            }

            if (isset($options['limit']) && is_array($options['limit'])) {
                $offset = max(0, (int) ($options['limit'][0] ?? 0));
                $count = max(0, (int) ($options['limit'][1] ?? count($items)));
                return array_slice($items, $offset, $count);
            }

            return $items;
        }

        public function zRemRangeByScore($key, $min, $max): int
        {
            $key = (string) $key;
            $deleted = 0;
            foreach (($this->zsetStorage[$key] ?? []) as $member => $score) {
                if ((int) $score <= (int) $max) {
                    unset($this->zsetStorage[$key][$member]);
                    $deleted++;
                }
            }

            return $deleted;
        }

        /**
         * @return array<int,string>
         */
        public function zMembers(string $key): array
        {
            $items = array_map('strval', array_keys($this->zsetStorage[$key] ?? []));
            sort($items, SORT_NATURAL);
            return array_values($items);
        }
    }
}

if (!class_exists('CBT_Runtime_Test_Wpdb')) {
    class CBT_Runtime_Test_Wpdb
    {
        public string $prefix = 'wp_';
        public int $getVarCalls = 0;
        public int $queryCalls = 0;

        public function prepare($query, ...$args): string
        {
            $params = (count($args) === 1 && is_array($args[0])) ? $args[0] : $args;
            foreach ($params as $arg) {
                $replacement = is_numeric($arg)
                    ? (string) $arg
                    : "'" . str_replace("'", "''", (string) $arg) . "'";
                $query = preg_replace('/%d|%f|%s/', $replacement, (string) $query, 1) ?? (string) $query;
            }

            return (string) $query;
        }

        public function get_var($query): int
        {
            $this->getVarCalls++;
            return 1;
        }

        public function query($query): int
        {
            $this->queryCalls++;
            return 1;
        }
    }
}
