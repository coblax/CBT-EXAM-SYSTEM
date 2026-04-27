<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

if (!class_exists('wpdb')) {
    eval(<<<'PHP'
class wpdb
{
    public string $prefix = 'wp_';

    public function get_charset_collate(): string
    {
        return '';
    }
}
PHP);
}

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';

final class AdminExamsQuestionPrintContextTest extends TestCase
{
    private AdminExamsQuestionPrintFakeWpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = new AdminExamsQuestionPrintFakeWpdb();
        $this->wpdb->exam = [
            'id' => 77,
            'title' => 'UTS',
            'subject_id' => 12,
            'subject_name' => 'KONSENTRASI KEAHLIAN TKJ',
            'status' => 'published',
            'starts_at' => '2026-04-22 15:20:00',
            'ends_at' => '2026-04-27 15:20:00',
            'created_by' => 9,
        ];
        $this->wpdb->questions = [
            [
                'id' => 901,
                'question_text' => '<p>Soal pertama</p>',
                'question_type' => 'multiple_choice',
                'points' => 1,
                'explanation' => '<p>Pembahasan</p>',
                'correct_text' => '',
                'source_question_id' => 801,
                'source_exam_title' => 'Bank Soal - TKJ',
            ],
            [
                'id' => 902,
                'question_text' => '<p>Soal kedua</p>',
                'question_type' => 'essay',
                'points' => 5,
                'explanation' => '',
                'correct_text' => '<p>Rubrik</p>',
                'source_question_id' => 0,
                'source_exam_title' => '',
            ],
        ];
        $this->wpdb->options = [
            [
                'id' => 3001,
                'question_id' => 901,
                'option_key' => 'A',
                'option_text' => '<p>Benar</p>',
                'is_correct' => 1,
            ],
            [
                'id' => 3002,
                'question_id' => 901,
                'option_key' => 'B',
                'option_text' => '<p>Salah</p>',
                'is_correct' => 0,
            ],
        ];

        $GLOBALS['wpdb'] = $this->wpdb;
        $GLOBALS['cbt_test_current_user_id'] = 9;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_current_user_caps']['cbt_manage_exams'] = true;
    }

    public function test_build_print_questions_context_returns_teacher_context_with_questions_options_and_back_url(): void
    {
        $context = \CBT_Admin_Exams_Service::build_print_questions_context([
            'exam_id' => 77,
            'cbt_exam_question_print_mode' => 'teacher',
            'cbt_exam_search' => 'uts',
            'cbt_exam_per_page' => 50,
        ]);

        self::assertIsArray($context);
        self::assertSame('teacher', $context['print_mode']);
        self::assertTrue($context['show_answer_key']);
        self::assertSame('Lembar Guru + Kunci', $context['print_mode_label']);
        self::assertSame(2, (int) $context['question_count']);
        self::assertSame(6.0, (float) $context['total_points']);
        self::assertCount(2, $context['questions']);
        self::assertCount(2, $context['options_map'][901]);
        self::assertStringContainsString('preview_exam_id=77', (string) $context['print_back_url']);
        self::assertStringContainsString('cbt_exam_search=uts', (string) $context['print_back_url']);
    }

    public function test_build_print_questions_context_defaults_unknown_mode_to_student(): void
    {
        $context = \CBT_Admin_Exams_Service::build_print_questions_context([
            'exam_id' => 77,
            'cbt_exam_question_print_mode' => 'unexpected',
        ]);

        self::assertIsArray($context);
        self::assertSame('student', $context['print_mode']);
        self::assertFalse($context['show_answer_key']);
        self::assertSame('Lembar Siswa', $context['print_mode_label']);
    }

    public function test_build_print_questions_context_rejects_inaccessible_exam(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = false;
        $GLOBALS['cbt_test_current_user_caps']['cbt_manage_system'] = false;
        $GLOBALS['cbt_test_current_user_caps']['cbt_manage_exams'] = true;
        $GLOBALS['cbt_test_current_user_id'] = 44;

        $context = \CBT_Admin_Exams_Service::build_print_questions_context([
            'exam_id' => 77,
        ]);

        self::assertInstanceOf(\WP_Error::class, $context);
        self::assertSame('exam_questions_print_not_found', $context->get_error_code());
    }

    public function test_build_print_questions_context_requires_exam_management_capability(): void
    {
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = false;
        $GLOBALS['cbt_test_current_user_caps']['cbt_manage_system'] = false;
        $GLOBALS['cbt_test_current_user_caps']['cbt_manage_exams'] = false;

        $context = \CBT_Admin_Exams_Service::build_print_questions_context([
            'exam_id' => 77,
        ]);

        self::assertInstanceOf(\WP_Error::class, $context);
        self::assertSame('exam_questions_print_forbidden', $context->get_error_code());
    }

    public function test_build_print_questions_context_rejects_empty_exam(): void
    {
        $this->wpdb->questions = [];
        $this->wpdb->options = [];

        $context = \CBT_Admin_Exams_Service::build_print_questions_context([
            'exam_id' => 77,
        ]);

        self::assertInstanceOf(\WP_Error::class, $context);
        self::assertSame('exam_questions_print_empty', $context->get_error_code());
    }
}

final class AdminExamsQuestionPrintFakeWpdb extends \wpdb
{
    /** @var array<string,mixed> */
    public array $exam = [];

    /** @var array<int,array<string,mixed>> */
    public array $questions = [];

    /** @var array<int,array<string,mixed>> */
    public array $options = [];

    /**
     * @param mixed ...$args
     * @return array{query:string,args:array<int,mixed>}
     */
    public function prepare(string $query, ...$args): array
    {
        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /**
     * @param array{query:string,args:array<int,mixed>}|string $prepared
     * @return array<string,mixed>|null
     */
    public function get_row($prepared, $output = ARRAY_A)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (strpos($query, 'FROM wp_cbt_exams e') === false) {
            return null;
        }

        $exam_id = (int) ($args[0] ?? 0);
        if ($exam_id !== (int) ($this->exam['id'] ?? 0)) {
            return null;
        }

        if (strpos($query, 'AND e.created_by = %d') !== false) {
            $created_by = (int) ($args[1] ?? 0);
            if ($created_by !== (int) ($this->exam['created_by'] ?? 0)) {
                return null;
            }
        }

        return $this->exam;
    }

    /**
     * @param array{query:string,args:array<int,mixed>}|string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = ARRAY_A): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (strpos($query, 'FROM wp_cbt_questions q') !== false && strpos($query, 'WHERE q.exam_id = %d') !== false) {
            $exam_id = (int) ($args[0] ?? 0);
            if ($exam_id !== (int) ($this->exam['id'] ?? 0)) {
                return [];
            }

            return $this->questions;
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false) {
            $question_ids = array_map('intval', $args);

            return array_values(array_filter($this->options, static function (array $option) use ($question_ids): bool {
                return in_array((int) ($option['question_id'] ?? 0), $question_ids, true);
            }));
        }

        return [];
    }
}
