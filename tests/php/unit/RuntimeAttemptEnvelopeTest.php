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

    public function test_buffer_entries_uses_pipeline_for_buffer_writes(): void
    {
        $result = CBT_Runtime::buffer_entries($this->attemptFixture(47), 90, [
            [
                'question_id' => 101,
                'selected_option_ids' => '[2]',
                'answer_text' => '',
                'is_correct' => null,
                'score_awarded' => 0,
                'answered_at' => '2026-03-24 11:04:00',
            ],
        ]);

        $stored = $this->runtimeRedis->hMGet($this->runtimeKey('attempt:47:answers'), ['101']);
        $decoded = json_decode((string) ($stored['101'] ?? ''), true);

        self::assertSame(1, $result['buffered']);
        self::assertSame(1, $result['pending_count']);
        self::assertSame(1, $this->runtimeRedis->pipelineExecCount);
        self::assertSame(['hSet', 'zAdd', 'expire', 'expire', 'zAdd'], $this->runtimeRedis->pipelineCommandNames());
        self::assertIsArray($decoded);
        self::assertSame(101, (int) ($decoded['question_id'] ?? 0));
        self::assertContains('101', $this->runtimeRedis->zMembers($this->runtimeKey('attempt:47:dirty')));
        self::assertContains('47', $this->runtimeRedis->zMembers($this->runtimeKey('flush:due')));
    }

    public function test_buffer_entries_falls_back_to_direct_writes_when_pipeline_fails(): void
    {
        $this->runtimeRedis->pipelineExecShouldFail = true;

        $result = CBT_Runtime::buffer_entries($this->attemptFixture(48), 90, [
            [
                'question_id' => 102,
                'selected_option_ids' => '[3]',
                'answer_text' => '',
                'is_correct' => null,
                'score_awarded' => 0,
                'answered_at' => '2026-03-24 11:05:00',
            ],
        ]);

        $stored = $this->runtimeRedis->hMGet($this->runtimeKey('attempt:48:answers'), ['102']);

        self::assertSame(1, $result['buffered']);
        self::assertSame(1, $result['pending_count']);
        self::assertSame(1, $this->runtimeRedis->pipelineExecCount);
        self::assertNotFalse($stored['102'] ?? false);
        self::assertContains('102', $this->runtimeRedis->zMembers($this->runtimeKey('attempt:48:dirty')));
        self::assertContains('48', $this->runtimeRedis->zMembers($this->runtimeKey('flush:due')));
    }

    public function test_buffer_entries_pipeline_handles_clear_entries(): void
    {
        $answersKey = $this->runtimeKey('attempt:49:answers');
        $this->runtimeRedis->hSet($answersKey, '103', wp_json_encode([
            'question_id' => 103,
            'selected_option_ids' => '[4]',
            'answer_text' => '',
            'is_correct' => null,
            'score_awarded' => 0,
            'answered_at' => '2026-03-24 11:06:00',
        ]));

        $result = CBT_Runtime::buffer_entries($this->attemptFixture(49), 90, [
            [
                'question_id' => 103,
                'clear' => 1,
            ],
        ]);

        $stored = $this->runtimeRedis->hMGet($answersKey, ['103']);

        self::assertSame(1, $result['buffered']);
        self::assertSame(1, $result['pending_count']);
        self::assertSame(1, $this->runtimeRedis->pipelineExecCount);
        self::assertFalse($stored['103']);
        self::assertContains('103', $this->runtimeRedis->zMembers($this->runtimeKey('attempt:49:dirty')));
    }

    public function test_buffer_entries_avoids_second_meta_touch_after_ensure_state(): void
    {
        $attemptId = 50;
        $result = CBT_Runtime::buffer_entries($this->attemptFixture($attemptId), 90, [
            [
                'question_id' => 104,
                'selected_option_ids' => '[5]',
                'answer_text' => '',
                'is_correct' => null,
                'score_awarded' => 0,
                'answered_at' => '2026-03-24 11:07:00',
            ],
        ]);

        self::assertSame(1, $result['buffered']);
        self::assertSame(1, $this->runtimeRedis->setExCountFor($this->runtimeKey('attempt:50:meta')));
    }

    public function test_update_meta_touch_skips_fresh_meta_without_redis_read_when_existing_meta_passed(): void
    {
        $attemptId = 51;
        CBT_Runtime::ensure_attempt_state($this->attemptFixture($attemptId), 90);
        $meta = $this->runtimeMeta($attemptId);

        $this->runtimeRedis->resetCommandStats();
        $this->invokeUpdateMetaTouch($attemptId, 90, $this->attemptFixture($attemptId), 0, $meta, false);

        self::assertSame(0, $this->runtimeRedis->getCount);
        self::assertSame(0, $this->runtimeRedis->setExCount);
        self::assertSame(0, $this->runtimeRedis->zAddCount);
        self::assertSame(0, $this->runtimeRedis->expireCount);
    }

    public function test_update_meta_touch_writes_stale_meta_without_extra_read(): void
    {
        $attemptId = 52;
        CBT_Runtime::ensure_attempt_state($this->attemptFixture($attemptId), 90);
        $meta = $this->runtimeMeta($attemptId);
        $meta['last_touch_at'] = time() - 31;

        $this->runtimeRedis->resetCommandStats();
        $this->invokeUpdateMetaTouch($attemptId, 90, $this->attemptFixture($attemptId), 0, $meta, false);

        $storedMeta = $this->runtimeMeta($attemptId);
        self::assertSame(0, $this->runtimeRedis->getCount);
        self::assertSame(1, $this->runtimeRedis->setExCountFor($this->runtimeKey('attempt:52:meta')));
        self::assertSame(1, $this->runtimeRedis->zAddCountFor($this->runtimeKey('active_attempts')));
        self::assertGreaterThan((int) $meta['last_touch_at'], (int) ($storedMeta['last_touch_at'] ?? 0));
    }

    public function test_update_meta_touch_material_changes_bypass_debounce(): void
    {
        $attemptId = 53;
        CBT_Runtime::ensure_attempt_state($this->attemptFixture($attemptId), 90);
        $meta = $this->runtimeMeta($attemptId);
        $attempt = $this->attemptFixture($attemptId);
        $attempt['status'] = 'submitted';
        $attempt['extra_time_minutes'] = 12;

        $this->runtimeRedis->resetCommandStats();
        $this->invokeUpdateMetaTouch($attemptId, 120, $attempt, 0, $meta, false);

        $storedMeta = $this->runtimeMeta($attemptId);
        self::assertSame(1, $this->runtimeRedis->setExCountFor($this->runtimeKey('attempt:53:meta')));
        self::assertSame('submitted', (string) ($storedMeta['status'] ?? ''));
        self::assertSame(12, (int) ($storedMeta['extra_time_minutes'] ?? 0));
        self::assertSame(120, (int) ($storedMeta['duration_minutes'] ?? 0));
    }

    public function test_buffer_entries_does_not_self_flush_at_legacy_threshold(): void
    {
        $result = CBT_Runtime::buffer_entries($this->attemptFixture(46), 90, $this->answerEntries(1, 10));

        self::assertSame(1, $result['runtime_used']);
        self::assertSame(10, $result['buffered']);
        self::assertSame(0, $result['flushed']);
        self::assertSame(10, $result['pending_count']);
        self::assertSame(0, $this->runtimeWpdb->queryCalls);
        self::assertSame([], CBT_Cache::$invalidatedAttemptIds);
    }

    public function test_buffer_entries_still_flushes_current_attempt_at_default_threshold(): void
    {
        $result = CBT_Runtime::buffer_entries($this->attemptFixture(46), 90, $this->answerEntries(1, 30));

        self::assertSame(1, $result['runtime_used']);
        self::assertSame(30, $result['buffered']);
        self::assertSame(30, $result['flushed']);
        self::assertSame(0, $result['pending_count']);
        self::assertSame(1, $this->runtimeWpdb->queryCalls);
        self::assertSame([46], CBT_Cache::$invalidatedAttemptIds);

        $meta = $this->runtimeMeta(46);
        self::assertGreaterThan(0, (int) ($meta['last_flushed_at'] ?? 0));
    }

    public function test_buffer_entries_schedules_flush_due_with_default_delay(): void
    {
        $before = time();
        CBT_Runtime::buffer_entries($this->attemptFixture(55), 90, $this->answerEntries(1, 1));
        $after = time();

        $score = $this->runtimeRedis->zScoreForTest($this->runtimeKey('flush:due'), '55');

        self::assertNotNull($score);
        self::assertGreaterThanOrEqual($before + 10, (int) $score);
        self::assertLessThanOrEqual($after + 10, (int) $score);
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

    public function test_runtime_flush_threshold_and_delay_are_clamped(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $thresholdMethod = $reflection->getMethod('normalize_runtime_flush_threshold');
        $thresholdMethod->setAccessible(true);
        self::assertSame(1, $thresholdMethod->invoke(null, 0));
        self::assertSame(30, $thresholdMethod->invoke(null, 30));
        self::assertSame(200, $thresholdMethod->invoke(null, 500));

        $delayMethod = $reflection->getMethod('normalize_runtime_flush_delay_seconds');
        $delayMethod->setAccessible(true);
        self::assertSame(1, $delayMethod->invoke(null, 0));
        self::assertSame(10, $delayMethod->invoke(null, 10));
        self::assertSame(60, $delayMethod->invoke(null, 500));
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

    /**
     * @return array<int,array<string,mixed>>
     */
    private function answerEntries(int $startQuestionId, int $count): array
    {
        $entries = [];
        for ($offset = 0; $offset < $count; $offset++) {
            $questionId = $startQuestionId + $offset;
            $entries[] = [
                'question_id' => $questionId,
                'selected_option_ids' => '[' . $questionId . ']',
                'answer_text' => '',
                'is_correct' => null,
                'score_awarded' => 0,
                'answered_at' => '2026-03-24 11:03:00',
            ];
        }

        return $entries;
    }

    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed>|null $existingMeta
     */
    private function invokeUpdateMetaTouch(
        int $attemptId,
        int $durationMinutes,
        array $attempt,
        int $lastFlushedAt,
        ?array $existingMeta,
        bool $force
    ): void {
        $reflection = new ReflectionClass(CBT_Runtime::class);
        $method = $reflection->getMethod('update_meta_touch');
        $method->setAccessible(true);
        $method->invoke(
            null,
            $this->runtimeRedis,
            $attemptId,
            $durationMinutes,
            $attempt,
            $lastFlushedAt,
            $existingMeta,
            $force
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function runtimeMeta(int $attemptId): array
    {
        $decoded = json_decode($this->runtimeRedis->rawString($this->runtimeKey('attempt:' . $attemptId . ':meta')), true);
        return is_array($decoded) ? $decoded : [];
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

        public int $pipelineExecCount = 0;

        public bool $pipelineExecShouldFail = false;

        /** @var array<int,array<int,array<string,mixed>>> */
        public array $pipelineBatches = [];

        public int $getCount = 0;

        public int $setExCount = 0;

        public int $expireCount = 0;

        public int $zAddCount = 0;

        /** @var array<int,string> */
        public array $getKeys = [];

        /** @var array<int,string> */
        public array $setExKeys = [];

        /** @var array<int,string> */
        public array $expireKeys = [];

        /** @var array<int,string> */
        public array $zAddKeys = [];

        public function get($key)
        {
            $this->getCount++;
            $this->getKeys[] = (string) $key;
            return parent::get($key);
        }

        public function setEx($key, $ttl, $value)
        {
            $this->setExCount++;
            $this->setExKeys[] = (string) $key;
            return parent::setEx($key, $ttl, $value);
        }

        public function rawString(string $key): string
        {
            return (string) ($GLOBALS['cbt_test_redis_storage'][$key] ?? '');
        }

        public function resetCommandStats(): void
        {
            $this->getCount = 0;
            $this->setExCount = 0;
            $this->expireCount = 0;
            $this->zAddCount = 0;
            $this->getKeys = [];
            $this->setExKeys = [];
            $this->expireKeys = [];
            $this->zAddKeys = [];
        }

        public function setExCountFor(string $key): int
        {
            return count(array_filter($this->setExKeys, static function (string $candidate) use ($key): bool {
                return $candidate === $key;
            }));
        }

        public function zAddCountFor(string $key): int
        {
            return count(array_filter($this->zAddKeys, static function (string $candidate) use ($key): bool {
                return $candidate === $key;
            }));
        }

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
            $this->zAddCount++;
            $this->zAddKeys[] = $key;
            if (!isset($this->zsetStorage[$key])) {
                $this->zsetStorage[$key] = [];
            }

            $this->zsetStorage[$key][(string) $member] = (int) $score;
            return 1;
        }

        public function pipeline()
        {
            return new CBT_Runtime_Test_Redis_Pipeline($this);
        }

        /**
         * @param array<int,array<string,mixed>> $commands
         * @return array<int,mixed>|false
         */
        public function executePipelineCommands(array $commands)
        {
            $this->pipelineExecCount++;
            $this->pipelineBatches[] = $commands;
            if ($this->pipelineExecShouldFail) {
                return false;
            }

            $results = [];
            foreach ($commands as $command) {
                $name = (string) ($command['command'] ?? '');
                if ($name === 'hSet') {
                    $results[] = $this->hSet(
                        (string) ($command['key'] ?? ''),
                        (string) ($command['field'] ?? ''),
                        (string) ($command['value'] ?? '')
                    );
                    continue;
                }

                if ($name === 'hDel') {
                    $results[] = $this->hDel(
                        (string) ($command['key'] ?? ''),
                        (string) ($command['field'] ?? '')
                    );
                    continue;
                }

                if ($name === 'zAdd') {
                    $results[] = $this->zAdd(
                        (string) ($command['key'] ?? ''),
                        (int) ($command['score'] ?? 0),
                        (string) ($command['member'] ?? '')
                    );
                    continue;
                }

                if ($name === 'expire') {
                    $results[] = $this->expire(
                        (string) ($command['key'] ?? ''),
                        (int) ($command['ttl'] ?? 0)
                    );
                    continue;
                }

                $results[] = false;
            }

            return $results;
        }

        /**
         * @return array<int,string>
         */
        public function pipelineCommandNames(): array
        {
            $commands = $this->pipelineBatches[0] ?? [];
            return array_values(array_map(static function (array $command): string {
                return (string) ($command['command'] ?? '');
            }, $commands));
        }

        public function set($key, $value, $options = null): bool
        {
            $GLOBALS['cbt_test_redis_storage'][(string) $key] = (string) $value;
            return true;
        }

        public function expire($key, $ttl): bool
        {
            $this->expireCount++;
            $this->expireKeys[] = (string) $key;
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

        public function zScoreForTest(string $key, string $member): ?int
        {
            return isset($this->zsetStorage[$key][$member])
                ? (int) $this->zsetStorage[$key][$member]
                : null;
        }
    }
}

if (!class_exists('CBT_Runtime_Test_Redis_Pipeline')) {
    class CBT_Runtime_Test_Redis_Pipeline
    {
        private CBT_Runtime_Test_Redis_Client $redis;

        /** @var array<int,array<string,mixed>> */
        private array $commands = [];

        public function __construct(CBT_Runtime_Test_Redis_Client $redis)
        {
            $this->redis = $redis;
        }

        public function hSet($key, $field, $value): self
        {
            $this->commands[] = [
                'command' => 'hSet',
                'key' => (string) $key,
                'field' => (string) $field,
                'value' => (string) $value,
            ];

            return $this;
        }

        public function hDel($key, ...$fields): self
        {
            foreach ($fields as $field) {
                $this->commands[] = [
                    'command' => 'hDel',
                    'key' => (string) $key,
                    'field' => (string) $field,
                ];
            }

            return $this;
        }

        public function zAdd($key, $score, $member, ...$extra_args): self
        {
            $this->commands[] = [
                'command' => 'zAdd',
                'key' => (string) $key,
                'score' => (int) $score,
                'member' => (string) $member,
            ];

            return $this;
        }

        public function expire($key, $ttl): self
        {
            $this->commands[] = [
                'command' => 'expire',
                'key' => (string) $key,
                'ttl' => (int) $ttl,
            ];

            return $this;
        }

        /**
         * @return array<int,mixed>|false
         */
        public function exec()
        {
            return $this->redis->executePipelineCommands($this->commands);
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
