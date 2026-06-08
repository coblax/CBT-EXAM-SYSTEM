<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminQuestionDeleteGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['cbt_test_current_user_caps']['cbt_manage_questions'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
    }

    #[RunInSeparateProcess]
    public function test_handle_delete_question_blocks_direct_exam_question_when_live_roster_has_active_attempt(): void
    {
        $this->bootstrapQuestionsDeleteGuardScaffold();
        $this->useFakeRosterRedis();

        global $wpdb;
        $wpdb = new AdminQuestionDeleteGuardFakeWpdb([
            ['id' => 201, 'exam_id' => 55, 'source_question_id' => 0],
        ]);

        CBT_Live_Attempt_Roster_Index::sync_attempt(
            ['id' => 77, 'exam_id' => 55, 'student_id' => 7, 'status' => 'in_progress'],
            [
                'teacher_id' => 9,
                'exam_title' => 'Exam Aktif',
                'student_name' => 'Siswa',
                'student_login' => 'siswa',
                'student_kode_kelas' => 'XI-A',
                'student_kode_ruang' => 'R1',
                'last_seen_at' => '2026-04-03 06:00:00',
            ]
        );

        $_GET = [
            'id' => 201,
            'return_page' => 'cbt-question-bank',
        ];

        try {
            CBT_Admin_Questions_Service::handle_delete_question();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_questions_redirect__', $runtimeException->getMessage());
        }

        self::assertSame(0, $wpdb->deleteCalls);
        self::assertStringContainsString('cbt_err=Soal+tidak+bisa+dihapus+saat+masih+ada+peserta+aktif+pada+exam+terkait.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_delete_question_blocks_source_bank_question_when_descendant_exam_is_active(): void
    {
        $this->bootstrapQuestionsDeleteGuardScaffold();
        $this->useFakeRosterRedis();

        global $wpdb;
        $wpdb = new AdminQuestionDeleteGuardFakeWpdb([
            ['id' => 301, 'exam_id' => 99, 'source_question_id' => 0],
            ['id' => 302, 'exam_id' => 55, 'source_question_id' => 301],
        ]);

        CBT_Live_Attempt_Roster_Index::sync_attempt(
            ['id' => 88, 'exam_id' => 55, 'student_id' => 7, 'status' => 'in_progress'],
            [
                'teacher_id' => 9,
                'exam_title' => 'Exam Turunan Aktif',
                'student_name' => 'Siswa',
                'student_login' => 'siswa',
                'student_kode_kelas' => 'XI-A',
                'student_kode_ruang' => 'R1',
                'last_seen_at' => '2026-04-03 06:00:00',
            ]
        );

        $_GET = [
            'id' => 301,
            'return_page' => 'cbt-question-bank',
        ];

        try {
            CBT_Admin_Questions_Service::handle_delete_question();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_questions_redirect__', $runtimeException->getMessage());
        }

        self::assertSame(0, $wpdb->deleteCalls);
        self::assertStringContainsString('cbt_err=Soal+tidak+bisa+dihapus+saat+masih+ada+peserta+aktif+pada+exam+terkait.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_bulk_delete_questions_uses_db_fallback_guard_when_roster_redis_is_unavailable(): void
    {
        $this->bootstrapQuestionsDeleteGuardScaffold();
        $this->setRosterRedisUnavailable();

        global $wpdb;
        $wpdb = new AdminQuestionDeleteGuardFakeWpdb(
            [
                ['id' => 401, 'exam_id' => 71, 'source_question_id' => 0],
                ['id' => 402, 'exam_id' => 71, 'source_question_id' => 0],
            ],
            [71]
        );

        $_POST = [
            'return_page' => 'cbt-question-bank',
            'question_ids' => [401, 402],
        ];

        try {
            CBT_Admin_Questions_Service::handle_bulk_delete_questions();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_questions_redirect__', $runtimeException->getMessage());
        }

        self::assertSame(0, $wpdb->deleteCalls);
        self::assertSame(1, $wpdb->activeAttemptGuardQueryCalls);
        self::assertStringContainsString('cbt_err=Soal+tidak+bisa+dihapus+saat+masih+ada+peserta+aktif+pada+exam+terkait.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_delete_question_cleans_detail_tables_before_deleting_parent_question(): void
    {
        $this->bootstrapQuestionsDeleteGuardScaffold();
        $this->setRosterRedisUnavailable();

        global $wpdb;
        $wpdb = new AdminQuestionDeleteGuardFakeWpdb([
            ['id' => 501, 'exam_id' => 72, 'source_question_id' => 0],
        ]);

        $_GET = [
            'id' => 501,
            'return_page' => 'cbt-question-bank',
        ];

        try {
            CBT_Admin_Questions_Service::handle_delete_question();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_questions_redirect__', $runtimeException->getMessage());
        }

        self::assertSame(1, $wpdb->deleteCalls);
        self::assertSame('wp_cbt_question_table_completion_cell_options', $wpdb->cleanupTables[0] ?? '');
        self::assertSame('wp_cbt_question_cloze_dropdown_options', $wpdb->cleanupTables[1] ?? '');
        self::assertStringContainsString('WHERE cell_id IN', $wpdb->cleanupQueries[0] ?? '');
        self::assertStringContainsString('FROM wp_cbt_question_table_completion_cells', $wpdb->cleanupQueries[0] ?? '');
        self::assertStringContainsString('WHERE blank_id IN', $wpdb->cleanupQueries[1] ?? '');
        self::assertStringContainsString('FROM wp_cbt_question_cloze_dropdown_blanks', $wpdb->cleanupQueries[1] ?? '');
        self::assertContains('wp_cbt_essay_ai_suggestions', $wpdb->cleanupTables);
        self::assertContains('wp_cbt_answers', $wpdb->cleanupTables);
        self::assertContains('wp_cbt_question_matching_items', $wpdb->cleanupTables);
        self::assertContains('wp_cbt_question_cloze_dropdown_options', $wpdb->cleanupTables);
        self::assertContains('wp_cbt_question_categorization_items', $wpdb->cleanupTables);
        self::assertContains('wp_cbt_question_table_completion_cell_options', $wpdb->cleanupTables);
        self::assertSame('wp_cbt_options', end($wpdb->cleanupTables));
        self::assertStringContainsString('cbt_msg=Question+deleted', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_delete_question_clears_runtime_snapshots_before_warming_exam_again(): void
    {
        $this->bootstrapQuestionsDeleteGuardScaffold();
        $this->bootstrapRuntimeSnapshotCacheStubs();
        $this->setRosterRedisUnavailable();

        global $wpdb;
        $wpdb = new AdminQuestionDeleteGuardFakeWpdb([
            ['id' => 601, 'exam_id' => 72, 'source_question_id' => 0],
        ]);

        $_GET = [
            'id' => 601,
            'return_page' => 'cbt-question-bank',
        ];

        try {
            CBT_Admin_Questions_Service::handle_delete_question();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_questions_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([72], CBT_Exam_Question_Delivery_Cache::$clearedExamIds);
        self::assertSame([72], CBT_Exam_Start_Attempt_Snapshot_Cache::$clearedExamIds);
        self::assertSame([72], CBT_Question_Submission_Context_Cache::$clearedExamIds);
        self::assertSame([72], CBT_REST::$warmedDeliveryExamIds);
        self::assertSame([72], CBT_REST::$warmedStartAttemptExamIds);
        self::assertSame(1, $wpdb->deleteCalls);
        self::assertStringContainsString('cbt_msg=Question+deleted', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    private function bootstrapQuestionsDeleteGuardScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-attempt-roster-index.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-service.php';
    }

    private function useFakeRosterRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Live_Attempt_Roster_Index::class);

        $redisProperty = $reflection->getProperty('roster_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('roster_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('roster_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function setRosterRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Live_Attempt_Roster_Index::class);

        $redisProperty = $reflection->getProperty('roster_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('roster_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('roster_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'disabled in test');
    }

    private function bootstrapRuntimeSnapshotCacheStubs(): void
    {
        if (!class_exists('CBT_Exam_Question_Delivery_Cache')) {
            eval(<<<'PHP'
class CBT_Exam_Question_Delivery_Cache
{
    /** @var int[] */
    public static array $clearedExamIds = [];

    public static function clear_exam_payload(int $exam_id): int
    {
        self::$clearedExamIds[] = $exam_id;
        return 1;
    }
}
PHP);
        }

        if (!class_exists('CBT_Exam_Start_Attempt_Snapshot_Cache')) {
            eval(<<<'PHP'
class CBT_Exam_Start_Attempt_Snapshot_Cache
{
    /** @var int[] */
    public static array $clearedExamIds = [];

    public static function clear_exam_snapshot(int $exam_id): int
    {
        self::$clearedExamIds[] = $exam_id;
        return 1;
    }
}
PHP);
        }

        if (!class_exists('CBT_Question_Submission_Context_Cache')) {
            eval(<<<'PHP'
class CBT_Question_Submission_Context_Cache
{
    /** @var int[] */
    public static array $clearedExamIds = [];

    /** @return array<string,int> */
    public static function clear_exam_snapshots(int $exam_id): array
    {
        self::$clearedExamIds[] = $exam_id;
        return ['deleted_keys' => 1];
    }
}
PHP);
        }

        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    /** @var int[] */
    public static array $warmedDeliveryExamIds = [];
    /** @var int[] */
    public static array $warmedStartAttemptExamIds = [];

    public static function warm_exam_question_delivery_snapshot(int $exam_id): void
    {
        self::$warmedDeliveryExamIds[] = $exam_id;
    }

    public static function warm_exam_start_attempt_snapshot(int $exam_id): void
    {
        self::$warmedStartAttemptExamIds[] = $exam_id;
    }
}
PHP);
        }
    }
}

final class AdminQuestionDeleteGuardFakeWpdb
{
    public string $prefix = 'wp_';

    public int $deleteCalls = 0;
    public int $activeAttemptGuardQueryCalls = 0;
    /** @var string[] */
    public array $cleanupTables = [];
    /** @var string[] */
    public array $cleanupQueries = [];

    /** @var array<int,array<string,int>> */
    private array $questionRowsById = [];

    /** @var array<int,int> */
    private array $activeAttemptExamIds = [];

    /**
     * @param array<int,array<string,int>> $questionRows
     * @param array<int,int> $activeAttemptExamIds
     */
    public function __construct(array $questionRows, array $activeAttemptExamIds = [])
    {
        foreach ($questionRows as $questionRow) {
            $questionId = (int) ($questionRow['id'] ?? 0);
            if ($questionId <= 0) {
                continue;
            }

            $this->questionRowsById[$questionId] = [
                'id' => $questionId,
                'exam_id' => (int) ($questionRow['exam_id'] ?? 0),
                'source_question_id' => (int) ($questionRow['source_question_id'] ?? 0),
            ];
        }

        $this->activeAttemptExamIds = array_values(array_unique(array_filter(array_map('intval', $activeAttemptExamIds))));
    }

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
    public function get_var($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (strpos($query, 'SELECT id') !== false && strpos($query, 'FROM wp_cbt_attempts') !== false) {
            $this->activeAttemptGuardQueryCalls++;
            $examIds = array_values(array_filter(array_map('intval', array_slice($args, 1))));
            foreach ($examIds as $examId) {
                if (in_array($examId, $this->activeAttemptExamIds, true)) {
                    return 999;
                }
            }

            return 0;
        }

        return 0;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (strpos($query, 'SELECT id, exam_id') !== false && strpos($query, 'FROM wp_cbt_questions') !== false) {
            $rows = [];
            foreach ($args as $questionId) {
                $questionId = (int) $questionId;
                if (isset($this->questionRowsById[$questionId])) {
                    $rows[] = $this->questionRowsById[$questionId];
                }
            }

            return $rows;
        }

        if (strpos($query, 'SELECT DISTINCT exam_id') !== false && strpos($query, 'source_question_id IN') !== false) {
            $rows = [];
            $examIds = [];
            foreach ($args as $sourceQuestionId) {
                $sourceQuestionId = (int) $sourceQuestionId;
                foreach ($this->questionRowsById as $questionRow) {
                    if ((int) ($questionRow['source_question_id'] ?? 0) !== $sourceQuestionId) {
                        continue;
                    }

                    $examId = (int) ($questionRow['exam_id'] ?? 0);
                    if ($examId > 0 && !isset($examIds[$examId])) {
                        $examIds[$examId] = true;
                        $rows[] = ['exam_id' => $examId];
                    }
                }
            }

            return $rows;
        }

        return [];
    }

    public function delete($table, $where, $whereFormat = null): int
    {
        $this->deleteCalls++;

        return 1;
    }

    /** @param array<string,mixed>|string $prepared */
    public function query($prepared): int
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        if (preg_match('/DELETE FROM\\s+(\\S+)/i', $query, $matches)) {
            $this->cleanupTables[] = (string) ($matches[1] ?? '');
            $this->cleanupQueries[] = preg_replace('/\\s+/', ' ', trim($query)) ?? $query;
        }

        return 1;
    }
}
