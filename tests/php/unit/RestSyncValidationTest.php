<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestSyncValidationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_submit_answer_and_batch_reject_invalid_payloads_with_expected_error_codes(): void
    {
        $this->bootstrapRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $invalidSingle = \CBT_REST::submit_answer(new \WP_REST_Request([
            'attempt_id' => 0,
            'question_id' => 0,
        ]));
        self::assertTrue(is_wp_error($invalidSingle));
        self::assertSame('invalid_payload', $invalidSingle->get_error_code());
        self::assertSame(['status' => 400], $invalidSingle->get_error_data());

        $invalidBatch = \CBT_REST::submit_answers_batch(new \WP_REST_Request([
            'attempt_id' => 0,
            'answers' => [],
        ]));
        self::assertTrue(is_wp_error($invalidBatch));
        self::assertSame('invalid_payload', $invalidBatch->get_error_code());
        self::assertSame(['status' => 400], $invalidBatch->get_error_data());
    }

    #[RunInSeparateProcess]
    public function test_finish_exam_only_tries_finish_lock_for_in_progress_attempts_and_rejects_closed_attempts_safely(): void
    {
        $this->bootstrapRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new RestSyncValidationFakeWpdb([
            55 => [
                'id' => 55,
                'exam_id' => 9,
                'student_id' => 7,
                'status' => 'cancelled',
                'started_at' => '2026-03-24 11:00:00',
            ],
            56 => [
                'id' => 56,
                'exam_id' => 9,
                'student_id' => 7,
                'status' => 'in_progress',
                'started_at' => '2026-03-24 11:00:00',
            ],
        ], [
            9 => [
                'kkm_percentage' => 75,
                'show_student_result' => 1,
            ],
        ]);

        \CBT_Runtime::$acquireFinishLockCalls = [];
        \CBT_Cache::$acquireLockCalls = [];

        $closedAttempt = \CBT_REST::finish_exam(new \WP_REST_Request([
            'attempt_id' => 55,
        ]));
        self::assertTrue(is_wp_error($closedAttempt));
        self::assertSame('attempt_closed', $closedAttempt->get_error_code());
        self::assertSame(['status' => 400], $closedAttempt->get_error_data());
        self::assertSame([], \CBT_Cache::$acquireLockCalls);
        self::assertSame([], \CBT_Runtime::$acquireFinishLockCalls);

        \CBT_Runtime::$finishToken = '';
        \CBT_Runtime::$acquireFinishLockCalls = [];
        \CBT_Cache::$acquireLockResult = false;
        \CBT_Cache::$acquireLockCalls = [];

        $lockedAttempt = \CBT_REST::finish_exam(new \WP_REST_Request([
            'attempt_id' => 56,
        ]));
        self::assertTrue(is_wp_error($lockedAttempt));
        self::assertSame('attempt_finish_locked', $lockedAttempt->get_error_code());
        self::assertSame(['status' => 429], $lockedAttempt->get_error_data());
        self::assertSame(['finish_attempt:56'], \CBT_Cache::$acquireLockCalls);
        self::assertSame([56], \CBT_Runtime::$acquireFinishLockCalls);
    }

    #[RunInSeparateProcess]
    public function test_finish_exam_is_idempotent_for_same_completed_attempt(): void
    {
        $this->bootstrapRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new RestSyncValidationFakeWpdb([
            57 => [
                'id' => 57,
                'exam_id' => 9,
                'student_id' => 7,
                'status' => 'completed',
                'started_at' => '2026-03-24 11:00:00',
                'finished_at' => '2026-03-24 11:45:00',
                'score' => 80,
                'max_score' => 100,
                'duration_seconds' => 2700,
            ],
        ], [
            9 => [
                'kkm_percentage' => 75,
                'show_student_result' => 1,
            ],
        ]);

        \CBT_Runtime::$acquireFinishLockCalls = [];
        \CBT_Cache::$acquireLockCalls = [];

        $result = \CBT_REST::finish_exam(new \WP_REST_Request([
            'attempt_id' => 57,
        ]));

        self::assertFalse(is_wp_error($result));
        self::assertSame(57, $result['attempt_id']);
        self::assertSame('completed', $result['status']);
        self::assertSame('2026-03-24 11:45:00', $result['finished_at']);
        self::assertSame(1, $result['show_student_result']);
        self::assertSame('full', $result['result_view_mode']);
        self::assertSame(80.0, $result['score']);
        self::assertSame(100.0, $result['max_score']);
        self::assertSame(80.0, $result['percentage']);
        self::assertSame(75.0, $result['kkm_percentage']);
        self::assertSame(75.0, $result['passing_score']);
        self::assertSame(1, $result['is_passed']);
        self::assertSame('LULUS', $result['pass_label']);
        self::assertSame('pass', $result['result_tone']);
        self::assertSame(
            [
                'total_questions' => 0,
                'answered_questions' => 0,
                'pending_manual_questions' => 0,
            ],
            $result['submission_summary']
        );
        self::assertSame([], \CBT_Runtime::$acquireFinishLockCalls);
        self::assertSame([], \CBT_Cache::$acquireLockCalls);
    }

    #[RunInSeparateProcess]
    public function test_finish_exam_rejects_completion_when_runtime_flush_still_has_pending_answers(): void
    {
        $this->bootstrapRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new RestSyncValidationFakeWpdb([
            58 => [
                'id' => 58,
                'exam_id' => 9,
                'student_id' => 7,
                'status' => 'in_progress',
                'started_at' => '2026-03-24 11:00:00',
                'score' => 0,
                'max_score' => 0,
            ],
        ], [
            9 => [
                'kkm_percentage' => 75,
                'show_student_result' => 1,
            ],
        ]);

        \CBT_Runtime::$finishToken = 'finish-token';
        \CBT_Runtime::$flushAttemptCalls = [];
        \CBT_Runtime::$flushAttemptResult = [
            'runtime_used' => 1,
            'flushed' => 0,
            'pending_count' => 2,
        ];

        $result = \CBT_REST::finish_exam(new \WP_REST_Request([
            'attempt_id' => 58,
        ]));

        self::assertTrue(is_wp_error($result));
        self::assertSame('runtime_flush_pending', $result->get_error_code());
        self::assertSame(
            [
                'status' => 409,
                'pending_count' => 2,
                'flushed' => 0,
            ],
            $result->get_error_data()
        );
        self::assertSame([
            ['attempt_id' => 58, 'force' => true],
        ], \CBT_Runtime::$flushAttemptCalls);
    }

    #[RunInSeparateProcess]
    public function test_finalize_attempt_completion_uses_created_at_fallback_when_started_at_is_invalid(): void
    {
        $this->bootstrapRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new RestSyncValidationFakeWpdb([
            59 => [
                'id' => 59,
                'exam_id' => 9,
                'student_id' => 7,
                'status' => 'in_progress',
                'question_order' => '[]',
                'option_order' => '[]',
                'started_at' => 'not-a-valid-date',
                'created_at' => '2026-03-24 11:00:00',
                'duration_seconds' => 123,
                'score' => 0,
                'max_score' => 0,
            ],
        ], [
            9 => [
                'duration_minutes' => 45,
                'kkm_percentage' => 75,
                'show_student_result' => 1,
            ],
        ]);

        \CBT_Runtime::$flushAttemptResult = [
            'runtime_used' => 0,
            'flushed' => 0,
            'pending_count' => 0,
        ];

        $result = \CBT_REST::finalize_attempt_completion(
            59,
            '2026-03-24 11:30:00',
            ['defer_invalidation' => true]
        );

        self::assertFalse(is_wp_error($result));
        self::assertSame('completed', $result['status']);
        self::assertSame('2026-03-24 11:30:00', $result['finished_at']);
        self::assertCount(1, $wpdb->updateCalls);
        self::assertSame(1800, $wpdb->updateCalls[0]['data']['duration_seconds']);
        self::assertSame('2026-03-24 11:30:00', $wpdb->updateCalls[0]['data']['finished_at']);
    }

    #[RunInSeparateProcess]
    public function test_ui_state_submit_and_restricted_result_helpers_keep_expected_contract_keys(): void
    {
        $this->bootstrapRestScaffold();
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $uiStateResponse = \CBT_REST::save_ui_state(new \WP_REST_Request(
            [],
            [
                'preferences' => [
                    'font_scale' => 1.1,
                ],
            ]
        ));
        self::assertSame(['preferences', 'attempt_state'], array_keys($uiStateResponse));
        self::assertSame(1.1, $uiStateResponse['preferences']['font_scale']);
        self::assertNull($uiStateResponse['attempt_state']);

        $submissionFormatter = new ReflectionMethod('CBT_REST', 'format_submission_response_item');
        $submissionFormatter->setAccessible(true);
        $submissionShape = $submissionFormatter->invoke(null, [
            'question_id' => 101,
            'is_correct' => 1,
            'score_awarded' => 5,
            'deferred' => 1,
            'clear' => 1,
        ]);
        self::assertSame(
            ['question_id', 'is_correct', 'score_awarded', 'deferred', 'cleared'],
            array_keys($submissionShape)
        );

        $restrictedFormatter = new ReflectionMethod('CBT_REST', 'build_restricted_student_result_payload');
        $restrictedFormatter->setAccessible(true);
        $restrictedPayload = $restrictedFormatter->invoke(null, [
            'attempt' => [
                'id' => 88,
                'exam_id' => 9,
                'student_id' => 7,
                'status' => 'completed',
                'started_at' => '2026-03-24 11:00:00',
                'finished_at' => '2026-03-24 11:45:00',
            ],
            'exam' => [
                'id' => 9,
                'title' => 'Sync Fixture',
                'duration_minutes' => 45,
            ],
            'review_summary' => [
                'total_questions' => 2,
                'answered_questions' => 2,
                'pending_manual_questions' => 0,
            ],
        ]);

        self::assertSame(
            ['attempt', 'exam', 'show_student_result', 'result_view_mode', 'submission_summary'],
            array_keys($restrictedPayload)
        );
        self::assertSame(0, $restrictedPayload['show_student_result']);
        self::assertSame('restricted', $restrictedPayload['result_view_mode']);
    }

    #[RunInSeparateProcess]
    public function test_short_answer_evaluation_is_case_insensitive_and_tolerates_spacing_and_edge_punctuation(): void
    {
        $this->bootstrapRestScaffold();
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $evaluator = new ReflectionMethod('CBT_REST', 'evaluate_answer');
        $evaluator->setAccessible(true);

        $result = $evaluator->invoke(
            null,
            [
                'question_type' => 'short_answer',
                'points' => 5,
                'correct_text' => 'Jakarta',
            ],
            [],
            '  JAKARTA.  ',
            [
                'correct_text' => 'Jakarta',
            ]
        );

        self::assertSame(1, $result['is_correct']);
        self::assertSame(5.0, $result['score_awarded']);
    }

    #[RunInSeparateProcess]
    public function test_true_false_matrix_rest_normalizer_preserves_rich_statement_html(): void
    {
        $this->bootstrapRestScaffold();
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $normalizer = new ReflectionMethod('CBT_REST', 'normalize_true_false_matrix_config');
        $normalizer->setAccessible(true);

        $items = $normalizer->invoke(null, wp_json_encode([
            'statements' => [
                [
                    'text' => '<ul><li>Butir 1</li><li>Butir 2</li></ul>',
                    'answer' => 'true',
                ],
                [
                    'text' => '<p>Pernyataan kedua</p>',
                    'answer' => 'false',
                ],
            ],
        ]));

        self::assertIsArray($items);
        self::assertSame('<ul><li>Butir 1</li><li>Butir 2</li></ul>', $items[0]['text']);
        self::assertSame('true', $items[0]['answer']);
        self::assertSame('<p>Pernyataan kedua</p>', $items[1]['text']);
        self::assertSame('false', $items[1]['answer']);
    }

    private function bootstrapRestScaffold(): void
    {
        if (!class_exists('CBT_Auth')) {
            eval(<<<'PHP'
class CBT_Auth
{
    public static function current_user_id(\WP_REST_Request $request): int
    {
        return 7;
    }

    public static function current_user_role(\WP_REST_Request $request): string
    {
        return 'student';
    }

    public static function normalize_exam_token_input(string $token): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $token) ?? '');
    }

    public static function get_global_exam_token(bool $includeMeta = false): array
    {
        return ['token' => ''];
    }

    public static function is_frontend_auto_exam_token_enabled(): bool
    {
        return false;
    }
}
PHP);
        }

        if (!class_exists('CBT_Runtime')) {
            eval(<<<'PHP'
class CBT_Runtime
{
    public static string $finishToken = '';
    /** @var int[] */
    public static array $acquireFinishLockCalls = [];
    /** @var array<int,array{attempt_id:int,force:bool}> */
    public static array $flushAttemptCalls = [];
    /** @var array<string,int> */
    public static array $flushAttemptResult = [
        'runtime_used' => 0,
        'flushed' => 0,
        'pending_count' => 0,
    ];

    public static function acquire_finish_lock(int $attempt_id): string
    {
        self::$acquireFinishLockCalls[] = $attempt_id;
        return self::$finishToken;
    }

    public static function release_finish_lock(int $attempt_id, string $token): void
    {
    }

    public static function is_ready(): bool
    {
        return false;
    }

    public static function flush_attempt(int $attempt_id, bool $force = false): array
    {
        self::$flushAttemptCalls[] = [
            'attempt_id' => $attempt_id,
            'force' => $force,
        ];

        return self::$flushAttemptResult;
    }

    public static function clear_attempt_runtime(int $attempt_id): void
    {
    }
}
PHP);
        }

        if (!class_exists('CBT_Cache')) {
            eval(<<<'PHP'
class CBT_Cache
{
    public static bool $acquireLockResult = false;

    /** @var string[] */
    public static array $acquireLockCalls = [];

    public static function acquire_lock(string $key, int $ttl = 0, array $context = []): bool
    {
        self::$acquireLockCalls[] = $key;
        return self::$acquireLockResult;
    }

    public static function release_lock(string $key): void
    {
    }

    public static function get_exam_revision_meta(int $exam_id): array
    {
        return [];
    }

    public static function invalidate_attempt(int $attempt_id): void
    {
    }

    public static function invalidate_analytics(): void
    {
    }

    public static function invalidate_analytics_exam(int $exam_id): void
    {
    }

    public static function invalidate_user(int $user_id): void
    {
    }
}
PHP);
        }

        if (!class_exists('CBT_UI_State')) {
            eval(<<<'PHP'
class CBT_UI_State
{
    public static function get_state(int $user_id, int $attempt_id = 0): array
    {
        return [
            'preferences' => self::get_preferences($user_id),
            'attempt_state' => null,
        ];
    }

    public static function get_preferences(int $user_id): array
    {
        return [
            'font_scale' => 1.0,
        ];
    }

    public static function save_preferences(int $user_id, array $preferences): array
    {
        return array_merge(self::get_preferences($user_id), $preferences);
    }

    public static function save_attempt_state(int $user_id, int $attempt_id, array $attempt_state): ?array
    {
        return $attempt_state;
    }

    public static function clear_attempt_state(int $user_id, int $attempt_id): void
    {
    }
}
PHP);
        }
    }
}

final class RestSyncValidationFakeWpdb
{
    public string $prefix = 'wp_';
    /** @var array<int,array{table:string,data:array<string,mixed>,where:array<string,mixed>}> */
    public array $updateCalls = [];

    /**
     * @param array<int,array<string,mixed>> $attemptRows
     * @param array<int,array<string,mixed>> $examRows
     */
    public function __construct(private array $attemptRows, private array $examRows = [])
    {
    }

    /** @return array<string,mixed> */
    public function prepare(string $query, ...$args): array
    {
        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_row($prepared, $output = null): ?array
    {
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $attemptId = isset($args[0]) ? (int) $args[0] : 0;

        return $this->attemptRows[$attemptId] ?? null;
    }

    public function get_var($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $examId = isset($args[0]) ? (int) $args[0] : 0;
        $exam = $this->examRows[$examId] ?? [];

        if (stripos($query, 'show_student_result') !== false) {
            return $exam['show_student_result'] ?? 1;
        }

        if (stripos($query, 'kkm_percentage') !== false) {
            return $exam['kkm_percentage'] ?? 75.0;
        }

        return null;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        return [];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public function update($table, array $data, array $where, $format = null, $where_format = null): int
    {
        $this->updateCalls[] = [
            'table' => (string) $table,
            'data' => $data,
            'where' => $where,
        ];

        $attemptId = (int) ($where['id'] ?? 0);
        if ($attemptId > 0 && isset($this->attemptRows[$attemptId])) {
            $this->attemptRows[$attemptId] = array_merge($this->attemptRows[$attemptId], $data);
        }

        return 1;
    }
}
