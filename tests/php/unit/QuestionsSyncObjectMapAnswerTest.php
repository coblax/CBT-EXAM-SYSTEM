<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-sync-helper.php';

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionClass;

final class QuestionsSyncObjectMapAnswerTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_remap_question_answer_object_option_ids_updates_answer_text_and_resets_grading(): void
    {
        $this->bootstrapCacheStub();

        global $wpdb;
        $wpdb = new QuestionsSyncObjectMapAnswerFakeWpdb([
            [
                'id' => 1,
                'attempt_id' => 31,
                'question_id' => 77,
                'answer_text' => '{"1":101,"2":102}',
                'is_correct' => 1,
                'score_awarded' => 2.0,
            ],
            [
                'id' => 2,
                'attempt_id' => 32,
                'question_id' => 77,
                'answer_text' => '{"1":999,"2":102,"x":"bad"}',
                'is_correct' => 0,
                'score_awarded' => 1.0,
            ],
            [
                'id' => 3,
                'attempt_id' => 33,
                'question_id' => 77,
                'answer_text' => 'not-json',
                'is_correct' => 1,
                'score_awarded' => 1.0,
            ],
        ]);

        $this->invokeSyncHelper('remap_question_answer_object_option_ids', [77, [
            101 => 201,
            102 => 202,
        ], true]);

        self::assertSame(['1' => 201, '2' => 202], json_decode((string) $wpdb->answers[1]['answer_text'], true));
        self::assertNull($wpdb->answers[1]['is_correct']);
        self::assertSame(0, $wpdb->answers[1]['score_awarded']);
        self::assertSame(['2' => 202], json_decode((string) $wpdb->answers[2]['answer_text'], true));
        self::assertNull($wpdb->answers[2]['is_correct']);
        self::assertSame('not-json', $wpdb->answers[3]['answer_text']);
        self::assertSame([[31, 32]], \CBT_Cache::$invalidatedAttemptsBatches);
    }

    public function test_question_answer_contract_changed_detects_type_detail_and_option_changes(): void
    {
        $snapshot = [
            'question_type' => 'categorization',
            'correct_text' => '{"items":{"1":10}}',
            'normalized_detail_text' => '{"items":[{"item_key":"1"}]}',
            'options' => [
                ['id' => 10, 'option_key' => '1', 'option_text' => 'Mamalia', 'is_correct' => 0],
                ['id' => 11, 'option_key' => '2', 'option_text' => 'Reptil', 'is_correct' => 0],
            ],
        ];

        self::assertFalse($this->invokeSyncHelper('question_answer_contract_changed', [$snapshot, $snapshot]));

        $changedCorrect = $snapshot;
        $changedCorrect['correct_text'] = '{"items":{"1":11}}';
        self::assertTrue($this->invokeSyncHelper('question_answer_contract_changed', [$snapshot, $changedCorrect]));

        $changedOption = $snapshot;
        $changedOption['options'][1]['option_text'] = 'Aves';
        self::assertTrue($this->invokeSyncHelper('question_answer_contract_changed', [$snapshot, $changedOption]));

        $changedType = $snapshot;
        $changedType['question_type'] = 'matching';
        self::assertTrue($this->invokeSyncHelper('question_answer_contract_changed', [$snapshot, $changedType]));
    }

    /** @param array<int,mixed> $args */
    private function invokeSyncHelper(string $method, array $args): mixed
    {
        $reflection = new ReflectionClass(\CBT_Admin_Questions_Sync_Helper::class);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs(null, $args);
    }

    public function test_question_answer_contract_changed_returns_false_for_identical_minimal_snapshots(): void
    {
        $snapshot = [
            'question_type' => 'multiple_choice',
            'correct_text' => 'A',
            'normalized_detail_text' => '',
            'options' => [],
        ];

        self::assertFalse($this->invokeSyncHelper('question_answer_contract_changed', [$snapshot, $snapshot]));
    }

    public function test_question_answer_contract_changed_detects_removed_option(): void
    {
        $before = [
            'question_type' => 'multiple_choice',
            'correct_text' => 'A',
            'normalized_detail_text' => '',
            'options' => [
                ['id' => 10, 'option_key' => '1', 'option_text' => 'Mamalia', 'is_correct' => 1],
                ['id' => 11, 'option_key' => '2', 'option_text' => 'Reptil', 'is_correct' => 0],
            ],
        ];

        $after = [
            'question_type' => 'multiple_choice',
            'correct_text' => 'A',
            'normalized_detail_text' => '',
            'options' => [
                ['id' => 10, 'option_key' => '1', 'option_text' => 'Mamalia', 'is_correct' => 1],
            ],
        ];

        self::assertTrue($this->invokeSyncHelper('question_answer_contract_changed', [$before, $after]));
    }

    private function bootstrapCacheStub(): void
    {
        if (class_exists('CBT_Cache')) {
            return;
        }

        eval(<<<'PHP'
class CBT_Cache
{
    public static array $invalidatedAttemptsBatches = [];

    public static function invalidate_attempts(array $attempt_ids): void
    {
        self::$invalidatedAttemptsBatches[] = array_values(array_map('intval', $attempt_ids));
    }
}
PHP);
    }
}

final class QuestionsSyncObjectMapAnswerFakeWpdb
{
    public string $prefix = 'wp_';

    /** @var array<int,array<string,mixed>> */
    public array $answers = [];

    /**
     * @param array<int,array<string,mixed>> $answers
     */
    public function __construct(array $answers)
    {
        foreach ($answers as $answer) {
            $this->answers[(int) ($answer['id'] ?? 0)] = $answer;
        }
    }

    /** @return array{query:string,args:array<int,mixed>} */
    public function prepare(string $query, ...$args): array
    {
        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /**
     * @param array{query?:string,args?:array<int,mixed>}|string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = null): array
    {
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $questionId = isset($args[0]) ? (int) $args[0] : 0;
        $rows = [];

        foreach ($this->answers as $answer) {
            if ((int) ($answer['question_id'] ?? 0) !== $questionId) {
                continue;
            }
            $answerText = (string) ($answer['answer_text'] ?? '');
            if ($answerText === '') {
                continue;
            }
            $rows[] = $answer;
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     * @param array<int,string> $format
     * @param array<int,string> $whereFormat
     */
    public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): int
    {
        $id = (int) ($where['id'] ?? 0);
        if ($id <= 0 || !isset($this->answers[$id])) {
            return 0;
        }

        foreach ($data as $key => $value) {
            $this->answers[$id][$key] = $value;
        }

        return 1;
    }
}
