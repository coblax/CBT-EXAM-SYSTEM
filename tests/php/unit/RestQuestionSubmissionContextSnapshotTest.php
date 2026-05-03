<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestQuestionSubmissionContextSnapshotTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_submit_answers_batch_internal_preloads_contexts_in_single_bulk_hydrate(): void
    {
        $this->bootstrapRestScaffold();
        $this->setSubmissionContextRedisUnavailable();
        $this->setRuntimeRedisUnavailable();

        global $wpdb;
        $wpdb = new RestQuestionSubmissionContextFakeWpdb();

        $method = new ReflectionMethod('CBT_REST', 'submit_answers_batch_internal');
        $method->setAccessible(true);

        $result = $method->invoke(
            null,
            77,
            [
                ['question_id' => 201, 'answer' => 9002],
                ['question_id' => 202, 'answer' => '  JAKARTA.  '],
            ],
            7,
            'siswa'
        );

        self::assertIsArray($result);
        self::assertSame(2, $result['accepted_count']);
        self::assertSame(0, $result['runtime_used']);
        self::assertSame(1, $result['items'][0]['is_correct']);
        self::assertSame(5.0, $result['items'][0]['score_awarded']);
        self::assertSame(1, $result['items'][1]['is_correct']);
        self::assertSame(3.0, $result['items'][1]['score_awarded']);
        self::assertSame(1, $wpdb->attemptGetRowCalls);
        self::assertSame(1, $wpdb->contextQuestionHydrateCalls);
        self::assertSame(1, $wpdb->contextOptionHydrateCalls);
        self::assertSame(1, $wpdb->answerPersistQueryCalls);
    }

    #[RunInSeparateProcess]
    public function test_submit_answers_batch_internal_lets_buffer_entries_ensure_runtime_state_once(): void
    {
        $this->bootstrapRestScaffold();
        $this->setSubmissionContextRedisUnavailable();
        $runtimeRedis = $this->setRuntimeRedisAvailable();

        global $wpdb;
        $wpdb = new RestQuestionSubmissionContextFakeWpdb();

        $method = new ReflectionMethod('CBT_REST', 'submit_answers_batch_internal');
        $method->setAccessible(true);

        $result = $method->invoke(
            null,
            77,
            [
                ['question_id' => 201, 'answer' => 9002],
            ],
            7,
            'siswa'
        );

        self::assertIsArray($result);
        self::assertSame(1, $result['runtime_used']);
        self::assertSame(1, $result['buffered']);
        self::assertSame(0, $result['flushed']);
        self::assertSame(1, $result['pending_count']);
        self::assertSame(1, $runtimeRedis->setExCountFor($this->runtimeKey('attempt:77:meta')));
        self::assertNotFalse($runtimeRedis->hGet($this->runtimeKey('attempt:77:answers'), '201'));
    }

    #[RunInSeparateProcess]
    public function test_build_attempt_review_items_uses_batched_submission_context_without_detail_queries_for_auto_graded_types(): void
    {
        $this->bootstrapRestScaffold();
        $this->setSubmissionContextRedisUnavailable();

        global $wpdb;
        $wpdb = new RestQuestionSubmissionContextFakeWpdb();

        $method = new ReflectionMethod('CBT_REST', 'build_attempt_review_items');
        $method->setAccessible(true);

        $items = $method->invoke(null, [
            'id' => 88,
            'exam_id' => 77,
            'status' => 'completed',
            'question_order' => '[301,302]',
            'option_order' => '',
        ]);

        self::assertIsArray($items);
        self::assertCount(2, $items);
        self::assertSame('correct', $items[0]['status']);
        self::assertSame(1, $items[0]['is_correct']);
        self::assertSame(5.0, $items[0]['score_awarded']);
        self::assertSame('correct', $items[1]['status']);
        self::assertSame(1, $items[1]['is_correct']);
        self::assertSame(3.0, $items[1]['score_awarded']);
        self::assertSame(['Jakarta'], $items[1]['correct_short_answers']);
        self::assertSame(1, $wpdb->contextQuestionHydrateCalls);
        self::assertSame(1, $wpdb->contextOptionHydrateCalls);
        self::assertSame(0, $wpdb->detailGetRowCalls);
    }

    #[RunInSeparateProcess]
    public function test_evaluate_answer_from_submission_context_keeps_multiple_answer_and_true_false_matrix_scoring(): void
    {
        $this->bootstrapRestScaffold();

        $method = new ReflectionMethod('CBT_REST', 'evaluate_answer_from_submission_context');
        $method->setAccessible(true);

        $multipleAnswer = $method->invoke(null, [
            'id' => 401,
            'exam_id' => 90,
            'question_type' => 'multiple_answer',
            'points' => 4,
            'correct_option_ids' => [11, 13],
            'true_false_correct_value' => null,
            'true_false_option_value_by_id' => [],
            'short_answer_values' => [],
            'true_false_matrix_answers' => [],
        ], [11, 13]);

        $matrix = $method->invoke(null, [
            'id' => 402,
            'exam_id' => 90,
            'question_type' => 'true_false_matrix',
            'points' => 6,
            'correct_option_ids' => [],
            'true_false_correct_value' => null,
            'true_false_option_value_by_id' => [],
            'short_answer_values' => [],
            'true_false_matrix_answers' => ['1' => 'true', '2' => 'false'],
        ], ['1' => true, '2' => false]);

        self::assertSame(1, $multipleAnswer['is_correct']);
        self::assertSame(4.0, $multipleAnswer['score_awarded']);
        self::assertSame(1, $matrix['is_correct']);
        self::assertSame(6.0, $matrix['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_evaluate_answer_from_submission_context_covers_all_legacy_and_new_question_types(): void
    {
        $this->bootstrapRestScaffold();

        $method = new ReflectionMethod('CBT_REST', 'evaluate_answer_from_submission_context');
        $method->setAccessible(true);

        $base = [
            'exam_id' => 90,
            'correct_option_ids' => [],
            'true_false_correct_value' => null,
            'true_false_option_value_by_id' => [],
            'short_answer_values' => [],
            'ordering_correct_option_ids' => [],
            'true_false_matrix_answers' => [],
            'matching_correct_option_ids_by_key' => [],
            'cloze_dropdown_correct_option_ids_by_key' => [],
            'categorization_correct_option_ids_by_key' => [],
            'table_completion_answers_by_key' => [],
        ];

        $scenarios = [
            'multiple_choice' => [
                'context' => array_merge($base, [
                    'id' => 501,
                    'question_type' => 'multiple_choice',
                    'points' => 5,
                    'correct_option_ids' => [11],
                ]),
                'answer' => 11,
                'is_correct' => 1,
                'score_awarded' => 5.0,
            ],
            'multiple_answer' => [
                'context' => array_merge($base, [
                    'id' => 502,
                    'question_type' => 'multiple_answer',
                    'points' => 6,
                    'correct_option_ids' => [21, 23],
                ]),
                'answer' => [23, 21, 21],
                'is_correct' => 1,
                'score_awarded' => 6.0,
            ],
            'true_false' => [
                'context' => array_merge($base, [
                    'id' => 503,
                    'question_type' => 'true_false',
                    'points' => 2,
                    'true_false_correct_value' => 1,
                    'true_false_option_value_by_id' => ['31' => 1, '32' => 0],
                ]),
                'answer' => 'true',
                'is_correct' => 1,
                'score_awarded' => 2.0,
            ],
            'true_false_matrix' => [
                'context' => array_merge($base, [
                    'id' => 504,
                    'question_type' => 'true_false_matrix',
                    'points' => 4,
                    'true_false_matrix_answers' => ['1' => 'true', '2' => 'false'],
                ]),
                'answer' => ['1' => 'true', '2' => 'false'],
                'is_correct' => 1,
                'score_awarded' => 4.0,
            ],
            'short_answer' => [
                'context' => array_merge($base, [
                    'id' => 505,
                    'question_type' => 'short_answer',
                    'points' => 3,
                    'short_answer_values' => ['Jakarta', 'Tokyo'],
                ]),
                'answer' => ['input_a' => ' jakarta. ', 'input_b' => 'Tokyo'],
                'is_correct' => 1,
                'score_awarded' => 6.0,
            ],
            'essay' => [
                'context' => array_merge($base, [
                    'id' => 506,
                    'question_type' => 'essay',
                    'points' => 10,
                ]),
                'answer' => '  Jawaban esai siswa.  ',
                'is_correct' => null,
                'score_awarded' => 0.0,
                'answer_text' => 'Jawaban esai siswa.',
            ],
            'ordering' => [
                'context' => array_merge($base, [
                    'id' => 507,
                    'question_type' => 'ordering',
                    'points' => 5,
                    'ordering_correct_option_ids' => [41, 42, 43],
                ]),
                'answer' => [41, 42, 43],
                'is_correct' => 1,
                'score_awarded' => 5.0,
            ],
            'matching' => [
                'context' => array_merge($base, [
                    'id' => 508,
                    'question_type' => 'matching',
                    'points' => 4,
                    'matching_correct_option_ids_by_key' => ['1' => 101, '2' => 102],
                ]),
                'answer' => ['1' => 101, '2' => 102],
                'is_correct' => 1,
                'score_awarded' => 4.0,
            ],
            'cloze_dropdown' => [
                'context' => array_merge($base, [
                    'id' => 509,
                    'question_type' => 'cloze_dropdown',
                    'points' => 4,
                    'cloze_dropdown_correct_option_ids_by_key' => ['1' => 201, '2' => 202],
                ]),
                'answer' => ['1' => 201, '2' => 202],
                'is_correct' => 1,
                'score_awarded' => 4.0,
            ],
            'categorization' => [
                'context' => array_merge($base, [
                    'id' => 510,
                    'question_type' => 'categorization',
                    'points' => 6,
                    'categorization_correct_option_ids_by_key' => ['1' => 301, '2' => 302, '3' => 301],
                ]),
                'answer' => ['1' => 301, '2' => 302, '3' => 301],
                'is_correct' => 1,
                'score_awarded' => 6.0,
            ],
            'table_completion' => [
                'context' => array_merge($base, [
                    'id' => 511,
                    'question_type' => 'table_completion',
                    'points' => 8,
                    'table_completion_answers_by_key' => [
                        'A1' => ['cell_type' => 'text', 'correct_values' => ['Tokyo']],
                        'B1' => ['cell_type' => 'dropdown', 'correct_option_id' => 402],
                    ],
                ]),
                'answer' => ['A1' => ' tokyo. ', 'B1' => 402],
                'is_correct' => 1,
                'score_awarded' => 8.0,
            ],
        ];

        foreach ($scenarios as $label => $scenario) {
            $result = $method->invoke(null, $scenario['context'], $scenario['answer']);
            self::assertSame($scenario['is_correct'], $result['is_correct'], $label . ' correctness mismatch');
            self::assertSame($scenario['score_awarded'], $result['score_awarded'], $label . ' score mismatch');
            if (array_key_exists('answer_text', $scenario)) {
                self::assertSame($scenario['answer_text'], $result['answer_text'], $label . ' answer text mismatch');
            }
        }
    }

    #[RunInSeparateProcess]
    public function test_evaluate_answer_from_submission_context_scores_matching_and_cloze_dropdown_partially(): void
    {
        $this->bootstrapRestScaffold();

        $method = new ReflectionMethod('CBT_REST', 'evaluate_answer_from_submission_context');
        $method->setAccessible(true);

        $matchingContext = [
            'id' => 403,
            'exam_id' => 90,
            'question_type' => 'matching',
            'points' => 4,
            'correct_option_ids' => [],
            'true_false_correct_value' => null,
            'true_false_option_value_by_id' => [],
            'short_answer_values' => [],
            'true_false_matrix_answers' => [],
            'matching_correct_option_ids_by_key' => ['1' => 101, '2' => 102],
            'cloze_dropdown_correct_option_ids_by_key' => [],
        ];
        $clozeContext = [
            'id' => 404,
            'exam_id' => 90,
            'question_type' => 'cloze_dropdown',
            'points' => 6,
            'correct_option_ids' => [],
            'true_false_correct_value' => null,
            'true_false_option_value_by_id' => [],
            'short_answer_values' => [],
            'true_false_matrix_answers' => [],
            'matching_correct_option_ids_by_key' => [],
            'cloze_dropdown_correct_option_ids_by_key' => ['1' => 301, '2' => 302],
        ];

        $matchingFull = $method->invoke(null, $matchingContext, ['1' => 101, '2' => 102]);
        $matchingPartial = $method->invoke(null, $matchingContext, ['1' => 101, '2' => 999]);
        $matchingForeignKey = $method->invoke(null, $matchingContext, ['1' => 101, 'x' => 102]);
        $matchingEmpty = $method->invoke(null, $matchingContext, []);
        $matchingInvalidIds = $method->invoke(null, $matchingContext, ['1' => 0, '2' => 999]);
        $clozePartial = $method->invoke(null, $clozeContext, '{"1":301}');
        $clozeForeignKey = $method->invoke(null, $clozeContext, ['1' => 301, 'x' => 302]);

        self::assertSame(1, $matchingFull['is_correct']);
        self::assertSame(4.0, $matchingFull['score_awarded']);
        self::assertSame(0, $matchingPartial['is_correct']);
        self::assertSame(2.0, $matchingPartial['score_awarded']);
        self::assertSame(0, $matchingForeignKey['is_correct']);
        self::assertSame(2.0, $matchingForeignKey['score_awarded']);
        self::assertSame(0, $matchingEmpty['is_correct']);
        self::assertSame(0.0, $matchingEmpty['score_awarded']);
        self::assertNull($matchingEmpty['answer_text']);
        self::assertSame(0, $matchingInvalidIds['is_correct']);
        self::assertSame(0.0, $matchingInvalidIds['score_awarded']);
        self::assertSame(0, $clozePartial['is_correct']);
        self::assertSame(3.0, $clozePartial['score_awarded']);
        self::assertSame('{"1":301}', $clozePartial['answer_text']);
        self::assertSame(0, $clozeForeignKey['is_correct']);
        self::assertSame(3.0, $clozeForeignKey['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_evaluate_answer_from_submission_context_scores_categorization_and_table_completion_partially(): void
    {
        $this->bootstrapRestScaffold();

        $method = new ReflectionMethod('CBT_REST', 'evaluate_answer_from_submission_context');
        $method->setAccessible(true);

        $categorizationContext = [
            'id' => 405,
            'exam_id' => 90,
            'question_type' => 'categorization',
            'points' => 6,
            'correct_option_ids' => [],
            'true_false_correct_value' => null,
            'true_false_option_value_by_id' => [],
            'short_answer_values' => [],
            'true_false_matrix_answers' => [],
            'matching_correct_option_ids_by_key' => [],
            'cloze_dropdown_correct_option_ids_by_key' => [],
            'categorization_correct_option_ids_by_key' => ['1' => 501, '2' => 502, '3' => 501],
            'table_completion_answers_by_key' => [],
        ];
        $tableContext = [
            'id' => 406,
            'exam_id' => 90,
            'question_type' => 'table_completion',
            'points' => 8,
            'correct_option_ids' => [],
            'true_false_correct_value' => null,
            'true_false_option_value_by_id' => [],
            'short_answer_values' => [],
            'true_false_matrix_answers' => [],
            'matching_correct_option_ids_by_key' => [],
            'cloze_dropdown_correct_option_ids_by_key' => [],
            'categorization_correct_option_ids_by_key' => [],
            'table_completion_answers_by_key' => [
                'A1' => [
                    'cell_type' => 'text',
                    'correct_values' => ['Tokyo'],
                ],
                'B1' => [
                    'cell_type' => 'dropdown',
                    'correct_option_id' => 602,
                ],
            ],
        ];

        $categorizationPartial = $method->invoke(null, $categorizationContext, ['1' => 501, '2' => 999, '3' => 501]);
        $categorizationForeignKey = $method->invoke(null, $categorizationContext, ['1' => 501, 'x' => 502]);
        $categorizationEmpty = $method->invoke(null, $categorizationContext, []);
        $tablePartial = $method->invoke(null, $tableContext, ['A1' => '  tokyo. ', 'B1' => 999]);
        $tableFull = $method->invoke(null, $tableContext, '{"A1":"Tokyo","B1":602}');
        $tableForeignKey = $method->invoke(null, $tableContext, ['A1' => 'Tokyo', 'Z9' => 'Tokyo']);
        $tableEmpty = $method->invoke(null, $tableContext, []);

        self::assertSame(0, $categorizationPartial['is_correct']);
        self::assertSame(4.0, $categorizationPartial['score_awarded']);
        self::assertSame(0, $categorizationForeignKey['is_correct']);
        self::assertSame(2.0, $categorizationForeignKey['score_awarded']);
        self::assertSame(0, $categorizationEmpty['is_correct']);
        self::assertSame(0.0, $categorizationEmpty['score_awarded']);
        self::assertNull($categorizationEmpty['answer_text']);
        self::assertSame(0, $tablePartial['is_correct']);
        self::assertSame(4.0, $tablePartial['score_awarded']);
        self::assertSame(1, $tableFull['is_correct']);
        self::assertSame(8.0, $tableFull['score_awarded']);
        self::assertSame('{"A1":"Tokyo","B1":602}', $tableFull['answer_text']);
        self::assertSame(0, $tableForeignKey['is_correct']);
        self::assertSame(4.0, $tableForeignKey['score_awarded']);
        self::assertSame('{"A1":"Tokyo","Z9":"Tokyo"}', $tableForeignKey['answer_text']);
        self::assertSame(0, $tableEmpty['is_correct']);
        self::assertSame(0.0, $tableEmpty['score_awarded']);
        self::assertNull($tableEmpty['answer_text']);
    }

    private function bootstrapRestScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-runtime.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-question-submission-context-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';
    }

    private function setSubmissionContextRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Question_Submission_Context_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'disabled in test');
    }

    private function setRuntimeRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'disabled in test');
    }

    private function setRuntimeRedisAvailable(): RestQuestionSubmissionRuntimeRedisClient
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);

        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redis = new RestQuestionSubmissionRuntimeRedisClient();
        $redisProperty->setValue(null, $redis);

        $attemptedProperty = $reflection->getProperty('redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');

        $cachedPrefixProperty = $reflection->getProperty('cached_prefix');
        $cachedPrefixProperty->setAccessible(true);
        $cachedPrefixProperty->setValue(null, null);

        return $redis;
    }

    private function runtimeKey(string $suffix): string
    {
        $reflection = new ReflectionClass(CBT_Runtime::class);
        $method = $reflection->getMethod('prefixed_key');
        $method->setAccessible(true);

        return (string) $method->invoke(null, $suffix);
    }
}

final class RestQuestionSubmissionRuntimeRedisClient extends CBT_Test_Redis_Client
{
    /** @var array<int,string> */
    public array $setExKeys = [];

    public function setEx($key, $ttl, $value)
    {
        $this->setExKeys[] = (string) $key;
        return parent::setEx($key, $ttl, $value);
    }

    public function exists($key, ...$other_keys): int
    {
        $keys = array_merge([(string) $key], array_map('strval', $other_keys));
        foreach ($keys as $safeKey) {
            if (
                array_key_exists($safeKey, $GLOBALS['cbt_test_redis_storage'] ?? [])
                || array_key_exists($safeKey, $GLOBALS['cbt_test_redis_hashes'] ?? [])
                || array_key_exists($safeKey, $GLOBALS['cbt_test_redis_zsets'] ?? [])
            ) {
                return 1;
            }
        }

        return 0;
    }

    public function hLen($key): int
    {
        $items = $GLOBALS['cbt_test_redis_hashes'][(string) $key] ?? [];
        return is_array($items) ? count($items) : 0;
    }

    public function hMGet($key, $fields): array
    {
        $key = (string) $key;
        $items = [];
        foreach ((array) $fields as $field) {
            $items[(string) $field] = $GLOBALS['cbt_test_redis_hashes'][$key][(string) $field] ?? false;
        }

        return $items;
    }

    public function zCard($key): int
    {
        $items = $GLOBALS['cbt_test_redis_zsets'][(string) $key] ?? [];
        return is_array($items) ? count($items) : 0;
    }

    public function zRange($key, $start, $end, $scores = false)
    {
        $items = $GLOBALS['cbt_test_redis_zsets'][(string) $key] ?? [];
        if (!is_array($items) || empty($items)) {
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
            $scored[(string) $member] = (float) $items[(string) $member];
        }

        return $scored;
    }

    public function setExCountFor(string $key): int
    {
        return count(array_filter($this->setExKeys, static function (string $candidate) use ($key): bool {
            return $candidate === $key;
        }));
    }
}

final class RestQuestionSubmissionContextFakeWpdb
{
    public string $prefix = 'wp_';

    public int $attemptGetRowCalls = 0;
    public int $contextQuestionHydrateCalls = 0;
    public int $contextOptionHydrateCalls = 0;
    public int $answerPersistQueryCalls = 0;
    public int $detailGetRowCalls = 0;

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

        if (strpos($query, 'FROM wp_cbt_attempts') !== false) {
            $this->attemptGetRowCalls++;
            $attemptId = isset($args[0]) ? (int) $args[0] : 0;
            if ($attemptId === 77) {
                return [
                    'id' => 77,
                    'exam_id' => 55,
                    'student_id' => 7,
                    'status' => 'in_progress',
                    'started_at' => '2026-04-02 08:00:00',
                    'extra_time_minutes' => 0,
                ];
            }
        }

        if (strpos($query, 'SELECT id, randomize_questions') !== false && strpos($query, 'FROM wp_cbt_exams') !== false) {
            return [
                'id' => 77,
                'randomize_questions' => 0,
            ];
        }

        if (strpos($query, 'FROM wp_cbt_question_') !== false) {
            $this->detailGetRowCalls++;
            return null;
        }

        return null;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (strpos($query, 'FROM wp_cbt_questions q') !== false) {
            $this->contextQuestionHydrateCalls++;
            $ids = $this->extractIdsFromInClause($query);
            $rows = [
                201 => [
                    'id' => 201,
                    'exam_id' => 55,
                    'question_type' => 'multiple_choice',
                    'points' => 5,
                    'correct_text' => '',
                    'true_false_correct_value' => null,
                    'short_answer_correct_text' => null,
                ],
                202 => [
                    'id' => 202,
                    'exam_id' => 55,
                    'question_type' => 'short_answer',
                    'points' => 3,
                    'correct_text' => '',
                    'true_false_correct_value' => null,
                    'short_answer_correct_text' => 'Jakarta',
                ],
                301 => [
                    'id' => 301,
                    'exam_id' => 77,
                    'question_type' => 'true_false',
                    'points' => 5,
                    'correct_text' => '',
                    'true_false_correct_value' => 1,
                    'short_answer_correct_text' => null,
                ],
                302 => [
                    'id' => 302,
                    'exam_id' => 77,
                    'question_type' => 'short_answer',
                    'points' => 3,
                    'correct_text' => '',
                    'true_false_correct_value' => null,
                    'short_answer_correct_text' => 'Jakarta',
                ],
            ];

            $results = [];
            foreach ($ids as $questionId) {
                if (isset($rows[$questionId])) {
                    $results[] = $rows[$questionId];
                }
            }

            return $results;
        }

        if (strpos($query, 'SELECT id, question_text, question_type, points, correct_text, explanation') !== false) {
            $examId = isset($args[0]) ? (int) $args[0] : 0;
            if ($examId !== 77) {
                return [];
            }

            return [
                [
                    'id' => 301,
                    'question_text' => '2 + 2 = 4',
                    'question_type' => 'true_false',
                    'points' => 5,
                    'correct_text' => '',
                    'explanation' => '',
                    'is_active' => 1,
                ],
                [
                    'id' => 302,
                    'question_text' => 'Ibu kota Indonesia adalah [[A]].',
                    'question_type' => 'short_answer',
                    'points' => 3,
                    'correct_text' => '',
                    'explanation' => '',
                    'is_active' => 1,
                ],
            ];
        }

        if (strpos($query, 'SELECT id, question_id, option_key, option_text, is_correct') !== false) {
            $ids = $this->extractIdsFromInClause($query);
            $rows = [];
            foreach ($ids as $questionId) {
                foreach ($this->reviewOptionsByQuestion()[$questionId] ?? [] as $optionRow) {
                    $rows[] = $optionRow;
                }
            }

            return $rows;
        }

        if (strpos($query, 'SELECT id, question_id, option_text, is_correct') !== false) {
            $this->contextOptionHydrateCalls++;
            $ids = $this->extractIdsFromInClause($query);
            $rows = [];
            foreach ($ids as $questionId) {
                foreach ($this->contextOptionsByQuestion()[$questionId] ?? [] as $optionRow) {
                    $rows[] = $optionRow;
                }
            }

            return $rows;
        }

        if (strpos($query, 'FROM wp_cbt_answers') !== false) {
            $attemptId = isset($args[0]) ? (int) $args[0] : 0;
            if ($attemptId !== 88) {
                return [];
            }

            return [
                [
                    'question_id' => 301,
                    'selected_option_ids' => '[9201]',
                    'answer_text' => '',
                    'is_correct' => null,
                    'score_awarded' => 0,
                ],
                [
                    'question_id' => 302,
                    'selected_option_ids' => '',
                    'answer_text' => '  JAKARTA.  ',
                    'is_correct' => null,
                    'score_awarded' => 0,
                ],
            ];
        }

        return [];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_var($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (strpos($query, 'SELECT duration_minutes') !== false && strpos($query, 'FROM wp_cbt_exams') !== false) {
            $examId = isset($args[0]) ? (int) $args[0] : 0;
            if ($examId === 55) {
                return 60;
            }
        }

        return 0;
    }

    /** @param array<string,mixed>|string $prepared */
    public function query($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        if (strpos($query, 'INSERT INTO wp_cbt_answers') !== false) {
            $this->answerPersistQueryCalls++;
            return 2;
        }

        return 1;
    }

    /**
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function contextOptionsByQuestion(): array
    {
        return [
            201 => [
                ['id' => 9001, 'question_id' => 201, 'option_text' => 'Bandung', 'is_correct' => 0],
                ['id' => 9002, 'question_id' => 201, 'option_text' => 'Jakarta', 'is_correct' => 1],
            ],
            301 => [
                ['id' => 9201, 'question_id' => 301, 'option_text' => 'Benar', 'is_correct' => 1],
                ['id' => 9202, 'question_id' => 301, 'option_text' => 'Salah', 'is_correct' => 0],
            ],
        ];
    }

    /**
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function reviewOptionsByQuestion(): array
    {
        return [
            301 => [
                ['id' => 9201, 'question_id' => 301, 'option_key' => 'A', 'option_text' => 'Benar', 'is_correct' => 1],
                ['id' => 9202, 'question_id' => 301, 'option_key' => 'B', 'option_text' => 'Salah', 'is_correct' => 0],
            ],
        ];
    }

    /**
     * @return array<int,int>
     */
    private function extractIdsFromInClause(string $query): array
    {
        if (!preg_match('/IN\s*\(([^)]+)\)/', $query, $matches)) {
            return [];
        }

        $parts = array_map('trim', explode(',', (string) ($matches[1] ?? '')));
        return array_values(array_filter(array_map('intval', $parts), static function (int $value): bool {
            return $value > 0;
        }));
    }
}
