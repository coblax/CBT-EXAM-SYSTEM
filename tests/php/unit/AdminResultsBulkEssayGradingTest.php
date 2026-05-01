<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminResultsBulkEssayGradingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_user_caps'] = [];
        $GLOBALS['cbt_test_current_user_id'] = 7;
        $GLOBALS['cbt_test_last_redirect'] = null;
    }

    #[RunInSeparateProcess]
    public function test_teacher_scope_limits_essay_questions_to_owned_exam(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['cbt_grade_essay'] = true;
        $GLOBALS['cbt_test_current_user_id'] = 7;

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'questions' => [
                [
                    'id' => 55,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan proses fotosintesis pada daun.',
                    'points' => 5,
                    'rubric_text' => 'Menyebut cahaya, klorofil, CO2, air, dan glukosa.',
                ],
            ],
        ]);

        $questions = CBT_Admin_Results_Service::get_exam_essay_questions(44, false, 7);

        self::assertCount(1, $questions);
        self::assertSame(55, $questions[0]['id']);
        self::assertStringContainsString('ex.created_by = %d', $wpdb->lastPreparedQuery);
        self::assertContains(7, $wpdb->lastPreparedArgs);
    }

    #[RunInSeparateProcess]
    public function test_workspace_returns_completed_attempts_only_and_marks_empty_answer(): void
    {
        $this->bootstrapResultsService();

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'answers' => [
                [
                    'answer_id' => 901,
                    'answer_text' => 'Karena terjadi perubahan energi.',
                    'score_awarded' => 2.5,
                    'answer_updated_at' => '2026-04-30 10:00:00',
                    'attempt_id' => 301,
                    'student_id' => 81,
                    'exam_id' => 44,
                    'attempt_score' => 20,
                    'finished_at' => '2026-04-30 10:10:00',
                    'question_id' => 55,
                    'points' => 5,
                    'question_text' => 'Jelaskan proses fotosintesis.',
                    'rubric_text' => 'Rubrik singkat',
                    'exam_title' => 'Biologi',
                    'student_name' => 'Ani Siswa',
                    'student_username' => 'ani',
                    'student_kelas' => 'X IPA 1',
                    'student_nisn' => '123',
                ],
                [
                    'answer_id' => 0,
                    'answer_text' => '',
                    'score_awarded' => null,
                    'attempt_id' => 302,
                    'student_id' => 82,
                    'exam_id' => 44,
                    'finished_at' => '2026-04-30 10:15:00',
                    'question_id' => 55,
                    'points' => 5,
                    'question_text' => 'Jelaskan proses fotosintesis.',
                    'rubric_text' => '',
                    'exam_title' => 'Biologi',
                    'student_name' => 'Budi Siswa',
                    'student_username' => 'budi',
                    'student_kelas' => 'X IPA 1',
                    'student_nisn' => '124',
                ],
            ],
        ]);

        $rows = CBT_Admin_Results_Service::get_student_answers_for_essay_question(44, 55, [
            'is_admin_scope' => true,
            'current_user_id' => 7,
        ]);

        self::assertCount(2, $rows);
        self::assertSame('graded', $rows[0]['status_key']);
        self::assertSame('empty', $rows[1]['status_key']);
        self::assertStringContainsString("a.status = 'completed'", $wpdb->lastPreparedQuery);
    }

    #[RunInSeparateProcess]
    public function test_bulk_grade_updates_changed_scores_recalculates_attempts_and_invalidates_cache(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['cbt_grade_essay'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_current_user_id'] = 7;

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'questions' => [
                [
                    'id' => 55,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan proses fotosintesis.',
                    'points' => 5,
                    'rubric_text' => '',
                ],
            ],
            'bulk_answer_rows' => [
                901 => [
                    'answer_id' => 901,
                    'attempt_id' => 301,
                    'student_id' => 81,
                    'exam_id' => 44,
                    'score_awarded' => 2.0,
                    'points' => 5,
                ],
                902 => [
                    'answer_id' => 902,
                    'attempt_id' => 302,
                    'student_id' => 82,
                    'exam_id' => 44,
                    'score_awarded' => 3.0,
                    'points' => 5,
                ],
            ],
            'attempt_sums' => [
                301 => 4.5,
            ],
        ]);

        $_POST = [
            'cbt_essay_exam_id' => '44',
            'cbt_essay_question_id' => '55',
            'cbt_essay_kelas' => 'X IPA 1',
            'cbt_essay_q' => 'Ani',
            'essay_scores' => [
                '901' => '4.5',
                '902' => '3.0',
            ],
        ];

        try {
            CBT_Admin_Results_Service::handle_bulk_grade_essay();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        self::assertCount(2, $wpdb->updates);
        self::assertSame('wp_cbt_answers', $wpdb->updates[0]['table']);
        self::assertSame(901, $wpdb->updates[0]['where']['id']);
        self::assertSame(4.5, $wpdb->updates[0]['data']['score_awarded']);
        self::assertSame('wp_cbt_attempts', $wpdb->updates[1]['table']);
        self::assertSame(301, $wpdb->updates[1]['where']['id']);
        self::assertSame(4.5, $wpdb->updates[1]['data']['score']);
        self::assertSame(['START TRANSACTION', 'COMMIT'], $wpdb->queries);
        self::assertSame([[301]], CBT_Cache::$invalidatedAttemptsBatches);
        self::assertSame([[81]], CBT_Cache::$invalidatedUsersBatches);
        self::assertSame([44], CBT_Cache::$invalidatedAnalyticsExamIds);
        self::assertSame(1, CBT_Cache::$invalidateAnalyticsCalls);

        $redirect = (string) ($GLOBALS['cbt_test_last_redirect'] ?? '');
        self::assertStringContainsString('cbt_results_tab=essay', $redirect);
        self::assertStringContainsString('cbt_essay_exam_id=44', $redirect);
        self::assertStringContainsString('cbt_essay_question_id=55', $redirect);
    }

    #[RunInSeparateProcess]
    public function test_bulk_grade_rejects_invalid_score_without_updates(): void
    {
        $this->bootstrapResultsService();

        $GLOBALS['cbt_test_current_user_caps']['cbt_grade_essay'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;

        global $wpdb;
        $wpdb = new AdminResultsBulkEssayFakeWpdb([
            'questions' => [
                [
                    'id' => 55,
                    'exam_id' => 44,
                    'question_text' => 'Jelaskan proses fotosintesis.',
                    'points' => 5,
                    'rubric_text' => '',
                ],
            ],
            'bulk_answer_rows' => [
                901 => [
                    'answer_id' => 901,
                    'attempt_id' => 301,
                    'student_id' => 81,
                    'exam_id' => 44,
                    'score_awarded' => 2.0,
                    'points' => 5,
                ],
            ],
        ]);

        $_POST = [
            'cbt_essay_exam_id' => '44',
            'cbt_essay_question_id' => '55',
            'essay_scores' => [
                '901' => '9.0',
            ],
        ];

        try {
            CBT_Admin_Results_Service::handle_bulk_grade_essay();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([], $wpdb->updates);
        self::assertStringContainsString('nilai+essay+invalid', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_bulk_grade_rejects_unauthorized_user(): void
    {
        $this->bootstrapResultsService();

        $_POST = [
            'cbt_essay_exam_id' => '44',
            'cbt_essay_question_id' => '55',
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unauthorized');

        CBT_Admin_Results_Service::handle_bulk_grade_essay();
    }

    private function bootstrapResultsService(): void
    {
        if (!class_exists('CBT_Cache')) {
            eval(<<<'PHP'
class CBT_Cache
{
    public static array $invalidatedAttemptsBatches = [];
    public static array $invalidatedUsersBatches = [];
    public static array $invalidatedAnalyticsExamIds = [];
    public static int $invalidateAnalyticsCalls = 0;

    public static function invalidate_attempts(array $attempt_ids): void
    {
        self::$invalidatedAttemptsBatches[] = array_values(array_map('intval', $attempt_ids));
    }

    public static function invalidate_users(array $user_ids): void
    {
        self::$invalidatedUsersBatches[] = array_values(array_map('intval', $user_ids));
    }

    public static function invalidate_analytics(): void
    {
        self::$invalidateAnalyticsCalls++;
    }

    public static function invalidate_analytics_exam(int $exam_id): void
    {
        self::$invalidatedAnalyticsExamIds[] = $exam_id;
    }
}
PHP);
        }

        CBT_Cache::$invalidatedAttemptsBatches = [];
        CBT_Cache::$invalidatedUsersBatches = [];
        CBT_Cache::$invalidatedAnalyticsExamIds = [];
        CBT_Cache::$invalidateAnalyticsCalls = 0;

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-service.php';
    }
}

final class AdminResultsBulkEssayFakeWpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';
    public string $lastPreparedQuery = '';

    /** @var array<int,mixed> */
    public array $lastPreparedArgs = [];

    /** @var list<string> */
    public array $queries = [];

    /** @var list<array<string,mixed>> */
    public array $updates = [];

    /** @var array<int,array<string,mixed>> */
    private array $questionRows;

    /** @var array<int,array<string,mixed>> */
    private array $answerRows;

    /** @var array<int,array<string,mixed>> */
    private array $bulkAnswerRows;

    /** @var array<int,float> */
    private array $attemptSums;

    /**
     * @param array<string,mixed> $fixtures
     */
    public function __construct(array $fixtures = [])
    {
        $this->questionRows = array_values((array) ($fixtures['questions'] ?? []));
        $this->answerRows = array_values((array) ($fixtures['answers'] ?? []));
        $this->bulkAnswerRows = (array) ($fixtures['bulk_answer_rows'] ?? []);
        $this->attemptSums = (array) ($fixtures['attempt_sums'] ?? []);
    }

    /**
     * @return array{query:string,args:array<int,mixed>}
     */
    public function prepare(string $query, ...$args): array
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $this->lastPreparedQuery = $query;
        $this->lastPreparedArgs = array_values($args);

        return [
            'query' => $query,
            'args' => array_values($args),
        ];
    }

    public function esc_like(string $text): string
    {
        return addslashes($text);
    }

    /**
     * @param array<string,mixed>|string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? array_values((array) ($prepared['args'] ?? [])) : [];

        if (str_contains($query, 'FROM wp_cbt_questions q') && str_contains($query, "q.question_type = 'essay'")) {
            return $this->questionRows;
        }

        if (str_contains($query, 'LEFT JOIN wp_cbt_answers ans') && str_contains($query, "a.status = 'completed'")) {
            return $this->answerRows;
        }

        if (str_contains($query, 'FROM wp_cbt_answers ans') && str_contains($query, "att.status = 'completed'")) {
            $requestedIds = array_slice($args, 0, max(0, count($args) - 2));
            $rows = [];
            foreach ($requestedIds as $answerId) {
                $answerId = (int) $answerId;
                if (isset($this->bulkAnswerRows[$answerId])) {
                    $rows[] = $this->bulkAnswerRows[$answerId];
                }
            }

            return $rows;
        }

        return [];
    }

    /**
     * @param array<string,mixed>|string $prepared
     */
    public function get_var($prepared)
    {
        $args = is_array($prepared) ? array_values((array) ($prepared['args'] ?? [])) : [];
        $attemptId = isset($args[0]) ? (int) $args[0] : 0;

        return $this->attemptSums[$attemptId] ?? 0.0;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     * @param array<int,string>|null $format
     * @param array<int,string>|null $where_format
     */
    public function update(string $table, array $data, array $where, ?array $format = null, ?array $where_format = null)
    {
        $this->updates[] = [
            'table' => $table,
            'data' => $data,
            'where' => $where,
        ];

        return 1;
    }

    public function query($query)
    {
        $this->queries[] = is_array($query) ? (string) ($query['query'] ?? '') : (string) $query;

        return true;
    }
}
