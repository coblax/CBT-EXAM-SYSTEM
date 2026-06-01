<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class QuestionsAuditCompletionTest extends TestCase
{
    private string $serviceSource = '';
    private string $schemaSource = '';
    private string $viewSource = '';

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 3);
        $this->serviceSource = (string) file_get_contents($root . '/admin/class-cbt-admin-questions-service.php');
        $this->schemaSource = (string) file_get_contents($root . '/sql/cbt_schema.sql');
        $this->viewSource = (string) file_get_contents($root . '/admin/views/questions/page.php');
    }

    public function test_schema_snapshot_contains_bank_exam_flag_and_index(): void
    {
        self::assertStringContainsString('is_bank_exam TINYINT(1) NOT NULL DEFAULT 0', $this->schemaSource);
        self::assertStringContainsString('KEY idx_is_bank_exam (is_bank_exam)', $this->schemaSource);
    }

    public function test_question_list_sort_uses_bank_exam_index_without_case_expression(): void
    {
        self::assertStringContainsString('ORDER BY e.is_bank_exam DESC, q.id DESC', $this->serviceSource);
        self::assertStringNotContainsString('ORDER BY CASE WHEN e.is_bank_exam = 1 THEN 0 ELSE 1 END ASC, q.id DESC', $this->serviceSource);
    }

    public function test_question_search_is_wired_through_query_and_actions(): void
    {
        foreach ([
            'normalize_question_search',
            'q.question_text LIKE %s OR EXISTS',
            'search_o.option_text LIKE %s',
            '$question_list_args[\'question_search\'] = $list_filter_search;',
            '[\'question_search\'] = $filter_search;',
            'name="question_search"',
            'redirect_question_search',
        ] as $needle) {
            self::assertStringContainsString($needle, $needle === 'name="question_search"' || $needle === 'redirect_question_search' ? $this->viewSource : $this->serviceSource);
        }
    }

    public function test_continue_urls_include_nonce_actions(): void
    {
        self::assertStringContainsString("'_wpnonce' => wp_create_nonce('cbt_continue_import_questions')", $this->serviceSource);
        self::assertStringContainsString("'_wpnonce' => wp_create_nonce('cbt_continue_bulk_delete_questions')", $this->serviceSource);
        self::assertStringContainsString("wp_verify_nonce(sanitize_key((string) wp_unslash(\$_GET['_wpnonce'])), 'cbt_continue_bulk_delete_questions')", $this->serviceSource);
    }

    public function test_duplicate_and_bulk_edit_handlers_are_present(): void
    {
        foreach ([
            'public static function handle_duplicate_question()',
            'private static function duplicate_question_record(',
            'copy_question_type_detail_for_duplicate',
            'public static function handle_bulk_questions_action()',
            "in_array(\$bulk_action, ['set_points', 'mark_active', 'mark_inactive'], true)",
            'propagate_bank_question_update_with_targets',
            'cbt_duplicate_question',
            'cbt_bulk_questions_action',
        ] as $needle) {
            $source = str_starts_with($needle, 'cbt_') ? $this->viewSource : $this->serviceSource;
            self::assertStringContainsString($needle, $source);
        }
    }

    public function test_context_return_is_explicit_and_does_not_leak_wpdb(): void
    {
        self::assertStringNotContainsString('return get_defined_vars();', $this->serviceSource);
        self::assertStringContainsString('return compact(', $this->serviceSource);

        $compactStart = strpos($this->serviceSource, 'return compact(');
        self::assertIsInt($compactStart);
        $compactEnd = strpos($this->serviceSource, ');', $compactStart);
        self::assertIsInt($compactEnd);
        $compactBlock = substr($this->serviceSource, $compactStart, $compactEnd - $compactStart);

        self::assertStringNotContainsString("'wpdb'", $compactBlock);
    }
}
