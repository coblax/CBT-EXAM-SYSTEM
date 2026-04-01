<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';

final class RuntimeAttemptEnvelopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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
