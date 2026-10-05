<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class QuestionsSaveGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['cbt_test_current_user_caps']['cbt_manage_questions'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
    }

    #[RunInSeparateProcess]
    public function test_invalid_multiple_choice_edit_does_not_touch_question_or_options(): void
    {
        $this->bootstrapQuestionHandlers();

        global $wpdb;
        $wpdb = new QuestionsSaveGuardFakeWpdb();

        $_POST = [
            'id' => 321,
            'exam_id' => 55,
            'subject_id' => 4,
            'return_page' => 'cbt-question-bank',
            'question_type' => 'multiple_choice',
            'question_text' => '<p>Berapa 2 + 2?</p>',
            'points' => '1',
            // Hanya 2 opsi dan kunci menunjuk opsi ke-3 yang kosong: wajib ditolak.
            'options' => (string) json_encode([
                ['option_text' => '3', 'is_correct' => 0],
                ['option_text' => '4', 'is_correct' => 0],
            ]),
            'validation_meta' => (string) json_encode(['type' => 'multiple_choice', 'selected_correct_index' => 3]),
            'cbt_mc_option_1' => '3',
            'cbt_mc_option_2' => '4',
        ];

        $this->expectRedirect(static function (): void {
            CBT_Admin_Questions_Service::handle_save_question();
        });

        self::assertSame(0, $wpdb->updateCalls, 'Soal lama tidak boleh ter-update saat validasi opsi gagal.');
        self::assertSame(0, $wpdb->deleteCalls, 'Opsi lama tidak boleh terhapus saat validasi opsi gagal.');
        self::assertSame(0, $wpdb->insertCalls);
        $redirect = (string) ($GLOBALS['cbt_test_last_redirect'] ?? '');
        self::assertStringContainsString('cbt_err=', $redirect);
        self::assertStringContainsString('edit=321', $redirect);
    }

    #[RunInSeparateProcess]
    public function test_new_question_with_invalid_points_is_rejected_before_insert(): void
    {
        $this->bootstrapQuestionHandlers();

        global $wpdb;
        $wpdb = new QuestionsSaveGuardFakeWpdb();

        $_POST = [
            'id' => 0,
            'subject_id' => 4,
            'return_page' => 'cbt-question-bank',
            'question_type' => 'true_false',
            'question_text' => '<p>Bumi itu bulat.</p>',
            'correct_text' => 'true',
            'points' => '-5',
        ];

        $this->expectRedirect(static function (): void {
            CBT_Admin_Questions_Service::handle_save_question();
        });

        self::assertSame(0, $wpdb->insertCalls);
        self::assertSame(0, $wpdb->deleteCalls);
        $redirect = (string) ($GLOBALS['cbt_test_last_redirect'] ?? '');
        self::assertStringContainsString('cbt_err=Points+soal+harus+antara+0.01+sampai+999.99.', $redirect);
        self::assertStringContainsString('cbt_question_tab=form', $redirect);
        self::assertStringNotContainsString('edit=', $redirect);
    }

    #[RunInSeparateProcess]
    public function test_editing_bank_question_keeps_it_in_its_current_bank_exam(): void
    {
        $this->bootstrapQuestionHandlers();

        global $wpdb;
        $wpdb = new QuestionsSaveGuardFakeWpdb();
        // Soal tersimpan di bank exam #77 (milik guru A); bank terbaru subject ini (#55) milik guru lain.
        $wpdb->storedExamId = 77;
        $wpdb->stopOnQuestionUpdate = true;

        $_POST = [
            'id' => 321,
            'exam_id' => 0,
            'subject_id' => 4,
            'return_page' => 'cbt-question-bank',
            'question_type' => 'true_false',
            'question_text' => '<p>Bumi itu bulat.</p>',
            'correct_text' => 'true',
            'points' => '1',
        ];
        $wpdb->questionTypeForEdit = 'true_false';

        try {
            CBT_Admin_Questions_Service::handle_save_question();
            self::fail('Update soal seharusnya dipanggil.');
        } catch (RuntimeException $exception) {
            self::assertSame('__stop_after_question_update__', $exception->getMessage());
        }

        $questionUpdate = $wpdb->updates[0] ?? [];
        self::assertSame('wp_cbt_questions', $questionUpdate['table'] ?? '');
        self::assertSame(77, $questionUpdate['data']['exam_id'] ?? null, 'Edit tidak boleh memindahkan soal ke bank exam lain.');
    }

    #[RunInSeparateProcess]
    public function test_editing_missing_question_is_rejected_without_writes(): void
    {
        $this->bootstrapQuestionHandlers();

        global $wpdb;
        $wpdb = new QuestionsSaveGuardFakeWpdb();
        $wpdb->storedExamId = 0;
        $wpdb->questionTypeForEdit = 'true_false';

        $_POST = [
            'id' => 999,
            'exam_id' => 0,
            'subject_id' => 4,
            'return_page' => 'cbt-question-bank',
            'question_type' => 'true_false',
            'question_text' => '<p>Bumi itu bulat.</p>',
            'correct_text' => 'true',
            'points' => '1',
        ];

        $this->expectRedirect(static function (): void {
            CBT_Admin_Questions_Service::handle_save_question();
        });

        self::assertSame(0, $wpdb->updateCalls);
        self::assertSame(0, $wpdb->deleteCalls);
        self::assertSame(0, $wpdb->insertCalls);
        self::assertStringContainsString('Soal+yang+diedit+tidak+ditemukan', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    private function expectRedirect(callable $callback): void
    {
        try {
            $callback();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_questions_redirect__', $runtimeException->getMessage());
        }
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

final class QuestionsSaveGuardFakeWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;
    public int $updateCalls = 0;
    public int $deleteCalls = 0;
    public int $insertCalls = 0;
    public int $storedExamId = 55;
    public string $questionTypeForEdit = 'multiple_choice';
    public bool $stopOnQuestionUpdate = false;
    /** @var array<int,array{table:string,data:array<string,mixed>}> */
    public array $updates = [];

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

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    /** @param array{query:string,args:array<int,mixed>}|string $prepared */
    public function get_var($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        if (strpos($query, 'SELECT question_type FROM wp_cbt_questions') !== false) {
            return $this->questionTypeForEdit;
        }
        if (strpos($query, 'SELECT exam_id FROM wp_cbt_questions') !== false) {
            return $this->storedExamId;
        }
        if (strpos($query, 'FROM wp_cbt_exams') !== false && strpos($query, 'subject_id = %d') !== false) {
            return 55;
        }

        return 0;
    }

    /** @param array{query:string,args:array<int,mixed>}|string $prepared */
    public function get_row($prepared, $output = null): array
    {
        return [];
    }

    /** @param array{query:string,args:array<int,mixed>}|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        return [];
    }

    /** @param array{query:string,args:array<int,mixed>}|string $prepared */
    public function get_col($prepared): array
    {
        return [];
    }

    /** @param array{query:string,args:array<int,mixed>}|string $prepared */
    public function query($prepared): int
    {
        return 0;
    }

    public function update(string $table, array $data, array $where, $format = null, $whereFormat = null): int
    {
        $this->updateCalls++;
        $this->updates[] = ['table' => $table, 'data' => $data];
        if ($this->stopOnQuestionUpdate && $table === 'wp_cbt_questions') {
            throw new RuntimeException('__stop_after_question_update__');
        }
        return 1;
    }

    public function insert(string $table, array $data, array $format = []): int
    {
        $this->insertCalls++;
        $this->insert_id++;
        return 1;
    }

    public function delete($table, $where, $whereFormat = null): int
    {
        $this->deleteCalls++;
        return 1;
    }
}
