<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class QuestionsActionHandlersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['cbt_test_current_user_caps']['cbt_manage_questions'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
    }

    #[RunInSeparateProcess]
    public function test_bulk_action_mark_inactive_updates_selected_questions_and_preserves_search_redirect(): void
    {
        $this->bootstrapQuestionHandlers();

        global $wpdb;
        $wpdb = new QuestionsActionHandlersFakeWpdb([
            101 => [
                'id' => 101,
                'exam_id' => 7,
                'source_question_id' => 0,
            ],
        ]);

        $_POST = [
            'bulk_question_action' => 'mark_inactive',
            'return_page' => 'cbt-question-bank',
            'redirect_question_search' => 'integral option',
            'question_ids' => [101],
        ];

        try {
            CBT_Admin_Questions_Service::handle_bulk_questions_action();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_questions_redirect__', $runtimeException->getMessage());
        }

        self::assertNotEmpty($wpdb->updateQueries);
        self::assertStringContainsString('SET is_active = %d, updated_at = %s', $wpdb->updateQueries[0]['query']);
        self::assertSame(0, (int) ($wpdb->updateQueries[0]['args'][0] ?? -1));
        self::assertContains(101, array_map('intval', $wpdb->updateQueries[0]['args']));
        self::assertStringContainsString('question_search=integral+option', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Soal+terpilih+berhasil+ditandai+inactive.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_duplicate_bank_backed_question_copies_source_as_standalone_question(): void
    {
        $this->bootstrapQuestionHandlers();

        global $wpdb;
        $wpdb = new QuestionsActionHandlersFakeWpdb(
            [
                20 => [
                    'id' => 20,
                    'exam_id' => 200,
                    'source_question_id' => 10,
                    'exam_is_bank_exam' => 0,
                    'source_exam_is_bank_exam' => 1,
                    'exam_created_by' => 1,
                    'is_active' => 1,
                    'question_text' => 'Operational copy',
                    'question_type' => 'multiple_choice',
                    'points' => 1.0,
                    'correct_text' => null,
                    'explanation' => null,
                ],
                10 => [
                    'id' => 10,
                    'exam_id' => 100,
                    'source_question_id' => 0,
                    'exam_is_bank_exam' => 1,
                    'source_exam_is_bank_exam' => 0,
                    'exam_created_by' => 1,
                    'is_active' => 1,
                    'question_text' => 'Source bank question',
                    'question_type' => 'multiple_choice',
                    'points' => 2.5,
                    'correct_text' => null,
                    'explanation' => 'Pembahasan',
                ],
            ],
            [
                10 => [
                    ['id' => 501, 'option_key' => 'A', 'option_text' => 'Alpha', 'is_correct' => 1],
                    ['id' => 502, 'option_key' => 'B', 'option_text' => 'Beta', 'is_correct' => 0],
                ],
            ]
        );

        $_GET = [
            'id' => 20,
            'return_page' => 'cbt-question-bank',
            'question_search' => 'bank copy',
        ];

        try {
            CBT_Admin_Questions_Service::handle_duplicate_question();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_questions_redirect__', $runtimeException->getMessage());
        }

        self::assertSame(100, (int) ($wpdb->insertedQuestion['exam_id'] ?? 0));
        self::assertSame(0, (int) ($wpdb->insertedQuestion['source_question_id'] ?? -1));
        self::assertSame('Source bank question', (string) ($wpdb->insertedQuestion['question_text'] ?? ''));
        self::assertSame(2.5, (float) ($wpdb->insertedQuestion['points'] ?? 0));
        self::assertCount(2, $wpdb->insertedOptions);
        self::assertSame('Alpha', (string) ($wpdb->insertedOptions[0]['option_text'] ?? ''));
        self::assertStringContainsString('edit=900', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('question_search=bank+copy', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertStringContainsString('cbt_msg=Sumber+soal+berhasil+diduplikasi.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    private function bootstrapQuestionHandlers(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-import-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-sync-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-service.php';
    }
}

final class QuestionsActionHandlersFakeWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    /** @var array<int,array<string,mixed>> */
    public array $updateQueries = [];
    /** @var array<string,mixed> */
    public array $insertedQuestion = [];
    /** @var array<int,array<string,mixed>> */
    public array $insertedOptions = [];

    /** @var array<int,array<string,mixed>> */
    private array $questionRowsById = [];
    /** @var array<int,array<int,array<string,mixed>>> */
    private array $optionsByQuestionId = [];

    /**
     * @param array<int,array<string,mixed>> $questionRowsById
     * @param array<int,array<int,array<string,mixed>>> $optionsByQuestionId
     */
    public function __construct(array $questionRowsById, array $optionsByQuestionId = [])
    {
        $this->questionRowsById = $questionRowsById;
        $this->optionsByQuestionId = $optionsByQuestionId;
    }

    /** @return array{query:string,args:array<int,mixed>} */
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

    /** @param array{query:string,args:array<int,mixed>}|string $prepared */
    public function get_var($prepared)
    {
        return 0;
    }

    /** @param array{query:string,args:array<int,mixed>}|string $prepared */
    public function get_col($prepared): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        if (strpos($query, 'SELECT q.id') !== false && strpos($query, 'e.created_by') !== false) {
            return array_values(array_filter(array_map('intval', $args), static function (int $value): bool {
                return $value > 0 && $value !== 1;
            }));
        }

        return [];
    }

    /** @param array{query:string,args:array<int,mixed>}|string $prepared */
    public function get_row($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        if (strpos($query, 'SELECT q.*') !== false && strpos($query, 'source_exam.is_bank_exam') !== false) {
            $questionId = (int) ($args[0] ?? 0);
            return (array) ($this->questionRowsById[$questionId] ?? []);
        }

        return [];
    }

    /** @param array{query:string,args:array<int,mixed>}|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (strpos($query, 'SELECT id, exam_id') !== false && strpos($query, 'FROM wp_cbt_questions') !== false) {
            $rows = [];
            foreach ($args as $questionId) {
                $questionId = (int) $questionId;
                if (isset($this->questionRowsById[$questionId])) {
                    $rows[] = [
                        'id' => $questionId,
                        'exam_id' => (int) ($this->questionRowsById[$questionId]['exam_id'] ?? 0),
                    ];
                }
            }

            return $rows;
        }

        if (strpos($query, 'SELECT DISTINCT exam_id') !== false && strpos($query, 'source_question_id IN') !== false) {
            return [];
        }

        if (strpos($query, 'SELECT id, option_key, option_text, is_correct') !== false && strpos($query, 'FROM wp_cbt_options') !== false) {
            $questionId = (int) ($args[0] ?? 0);
            return array_values($this->optionsByQuestionId[$questionId] ?? []);
        }

        return [];
    }

    /** @param array{query:string,args:array<int,mixed>}|string $prepared */
    public function query($prepared): int
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        if (strpos($query, 'UPDATE wp_cbt_questions') !== false) {
            $this->updateQueries[] = is_array($prepared) ? $prepared : ['query' => $query, 'args' => []];
        }

        return 1;
    }

    public function insert(string $table, array $data, array $format = [])
    {
        if ($table === 'wp_cbt_questions') {
            $this->insert_id = 900;
            $this->insertedQuestion = $data;
            return 1;
        }

        if ($table === 'wp_cbt_options') {
            $this->insert_id++;
            $this->insertedOptions[] = $data;
            return 1;
        }

        return 1;
    }

    public function delete($table, $where, $whereFormat = null): int
    {
        return 1;
    }
}
