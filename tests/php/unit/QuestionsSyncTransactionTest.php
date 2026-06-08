<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-sync-helper.php';

use CbtExamSystem\Tests\TestCase;
use RuntimeException;

final class QuestionsSyncTransactionTest extends TestCase
{
    public function test_propagate_bank_question_update_commits_descendant_sync_transaction(): void
    {
        global $wpdb;
        $wpdb = new QuestionsSyncTransactionFakeWpdb();

        $targets = \CBT_Admin_Questions_Sync_Helper::propagate_bank_question_update_with_targets(
            1,
            $this->bankSnapshot('Sumber lama'),
            $this->bankSnapshot('Sumber baru')
        );

        self::assertSame(['START TRANSACTION', 'COMMIT'], $wpdb->transactionQueries);
        self::assertSame([
            ['exam_id' => 20, 'question_id' => 2],
            ['exam_id' => 30, 'question_id' => 3],
        ], $targets);
        self::assertSame('Sumber baru', $wpdb->questions[2]['question_text']);
        self::assertSame('Sumber baru', $wpdb->questions[3]['question_text']);
    }

    public function test_propagate_bank_question_update_rolls_back_when_descendant_write_fails(): void
    {
        global $wpdb;
        $wpdb = new QuestionsSyncTransactionFakeWpdb();
        $wpdb->failOptionInsertForQuestionId = 3;

        try {
            \CBT_Admin_Questions_Sync_Helper::propagate_bank_question_update_with_targets(
                1,
                $this->bankSnapshot('Sumber lama'),
                $this->bankSnapshot('Sumber baru')
            );
            self::fail('Expected sync failure.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Gagal menambah opsi soal turunan Bank Soal', $exception->getMessage());
        }

        self::assertSame(['START TRANSACTION', 'ROLLBACK'], $wpdb->transactionQueries);
        self::assertSame('Target 2 lama', $wpdb->questions[2]['question_text']);
        self::assertSame('Target 3 lama', $wpdb->questions[3]['question_text']);
        self::assertCount(2, $wpdb->optionsByQuestionId[2]);
    }

    /**
     * @return array<string,mixed>
     */
    private function bankSnapshot(string $questionText): array
    {
        return [
            'question_id' => 1,
            'exam_id' => 10,
            'subject_id' => 5,
            'exam_title' => 'Bank Soal - Matematika',
            'source_question_id' => 0,
            'question_text' => $questionText,
            'question_type' => 'multiple_choice',
            'points' => 1.0,
            'correct_text' => '',
            'explanation' => '',
            'normalized_detail_text' => '',
            'options' => [
                ['id' => 11, 'option_key' => 'A', 'option_text' => 'Opsi A baru', 'is_correct' => 1],
                ['id' => 12, 'option_key' => 'B', 'option_text' => 'Opsi B baru', 'is_correct' => 0],
                ['id' => 13, 'option_key' => 'C', 'option_text' => 'Opsi C baru', 'is_correct' => 0],
            ],
            'question_detail' => [],
        ];
    }
}

final class QuestionsSyncTransactionFakeWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 300;
    public int $failOptionInsertForQuestionId = 0;
    /** @var string[] */
    public array $transactionQueries = [];
    /** @var array<int,array<string,mixed>> */
    public array $questions = [];
    /** @var array<int,array<int,array<string,mixed>>> */
    public array $optionsByQuestionId = [];
    /** @var array<string,mixed>|null */
    private ?array $transactionBackup = null;

    public function __construct()
    {
        $this->questions = [
            1 => $this->questionRow(1, 10, 'Sumber lama'),
            2 => $this->questionRow(2, 20, 'Target 2 lama', 1),
            3 => $this->questionRow(3, 30, 'Target 3 lama', 1),
        ];
        $this->optionsByQuestionId = [
            2 => [
                ['id' => 201, 'question_id' => 2, 'option_key' => 'A', 'option_text' => 'Opsi A lama', 'is_correct' => 1],
                ['id' => 202, 'question_id' => 2, 'option_key' => 'B', 'option_text' => 'Opsi B lama', 'is_correct' => 0],
            ],
            3 => [
                ['id' => 301, 'question_id' => 3, 'option_key' => 'A', 'option_text' => 'Opsi A lama', 'is_correct' => 1],
                ['id' => 302, 'question_id' => 3, 'option_key' => 'B', 'option_text' => 'Opsi B lama', 'is_correct' => 0],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function questionRow(int $id, int $examId, string $text, int $sourceQuestionId = 0): array
    {
        return [
            'id' => $id,
            'exam_id' => $examId,
            'subject_id' => 5,
            'exam_title' => $id === 1 ? 'Bank Soal - Matematika' : 'Exam Turunan',
            'source_question_id' => $sourceQuestionId,
            'question_text' => $text,
            'question_type' => 'multiple_choice',
            'points' => 1.0,
            'correct_text' => '',
            'explanation' => '',
        ];
    }

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

    public function query($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $normalized = strtoupper(trim((string) preg_replace('/\s+/', ' ', $query)));
        if (in_array($normalized, ['START TRANSACTION', 'COMMIT', 'ROLLBACK'], true)) {
            $this->transactionQueries[] = $normalized;
            if ($normalized === 'START TRANSACTION') {
                $this->transactionBackup = [
                    'questions' => $this->questions,
                    'optionsByQuestionId' => $this->optionsByQuestionId,
                    'insert_id' => $this->insert_id,
                ];
            } elseif ($normalized === 'ROLLBACK' && is_array($this->transactionBackup)) {
                $this->questions = $this->transactionBackup['questions'];
                $this->optionsByQuestionId = $this->transactionBackup['optionsByQuestionId'];
                $this->insert_id = (int) $this->transactionBackup['insert_id'];
            }

            return 1;
        }

        return 1;
    }

    public function get_col($prepared, $column = 0): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        if (strpos($query, 'WHERE source_question_id =') !== false) {
            $sourceQuestionId = (int) ($args[0] ?? 0);
            return array_values(array_map(static fn(array $row): int => (int) $row['id'], array_filter(
                $this->questions,
                static fn(array $row): bool => (int) ($row['source_question_id'] ?? 0) === $sourceQuestionId
            )));
        }

        return [];
    }

    public function get_row($prepared, $output = null): ?array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $questionId = (int) ($args[0] ?? 0);
        if (strpos($query, 'FROM wp_cbt_questions q') !== false) {
            return $this->questions[$questionId] ?? null;
        }
        if (strpos($query, 'cbt_question_multiple_choice') !== false) {
            return ['question_id' => $questionId];
        }

        return null;
    }

    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        if (strpos($query, 'FROM wp_cbt_options') !== false) {
            return array_values($this->optionsByQuestionId[(int) ($args[0] ?? 0)] ?? []);
        }

        return [];
    }

    public function update($table, array $data, array $where, array $format = [], array $whereFormat = [])
    {
        if (str_ends_with((string) $table, 'cbt_questions')) {
            $id = (int) ($where['id'] ?? 0);
            if (!isset($this->questions[$id])) {
                return 0;
            }
            $this->questions[$id] = array_merge($this->questions[$id], $data);
            return 1;
        }

        if (str_ends_with((string) $table, 'cbt_options')) {
            $id = (int) ($where['id'] ?? 0);
            foreach ($this->optionsByQuestionId as $questionId => $options) {
                foreach ($options as $idx => $option) {
                    if ((int) ($option['id'] ?? 0) === $id) {
                        $this->optionsByQuestionId[$questionId][$idx] = array_merge($option, $data);
                        return 1;
                    }
                }
            }
        }

        return 0;
    }

    public function insert($table, array $data, array $format = null)
    {
        if (str_ends_with((string) $table, 'cbt_options')) {
            $questionId = (int) ($data['question_id'] ?? 0);
            if ($questionId === $this->failOptionInsertForQuestionId) {
                return false;
            }
            $this->insert_id++;
            $data['id'] = $this->insert_id;
            $this->optionsByQuestionId[$questionId][] = $data;
            return 1;
        }

        $this->insert_id++;
        return 1;
    }

    public function delete($table, array $where, array $whereFormat = [])
    {
        if (str_ends_with((string) $table, 'cbt_options')) {
            $id = (int) ($where['id'] ?? 0);
            foreach ($this->optionsByQuestionId as $questionId => $options) {
                foreach ($options as $idx => $option) {
                    if ((int) ($option['id'] ?? 0) === $id) {
                        unset($this->optionsByQuestionId[$questionId][$idx]);
                        $this->optionsByQuestionId[$questionId] = array_values($this->optionsByQuestionId[$questionId]);
                        return 1;
                    }
                }
            }
        }

        return 0;
    }
}
