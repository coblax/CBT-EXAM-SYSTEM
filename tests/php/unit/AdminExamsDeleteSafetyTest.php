<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-question-delivery-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-start-attempt-snapshot-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-question-submission-context-cache.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-questions-helper.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';

final class AdminExamsDeleteSafetyTest extends TestCase
{
    private $previousWpdb = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $_GET = [
            'id' => '77',
            'cbt_exam_paged' => '2',
            'cbt_exam_status' => 'published',
        ];
        $_POST = [];
        $_REQUEST = $_GET;

        $this->useFakeDeliveryRedis();
        $this->useFakeStartSnapshotRedis();
        $this->useFakeSubmissionContextRedis();
    }

    protected function tearDown(): void
    {
        if ($this->previousWpdb !== null) {
            $GLOBALS['wpdb'] = $this->previousWpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }
        $this->resetStaticRedisClient(
            CBT_Exam_Question_Delivery_Cache::class,
            'delivery_redis',
            'delivery_redis_connection_attempted',
            'delivery_redis_last_connection_error'
        );
        $this->resetStaticRedisClient(
            CBT_Exam_Start_Attempt_Snapshot_Cache::class,
            'start_snapshot_redis',
            'start_snapshot_redis_connection_attempted',
            'start_snapshot_redis_last_connection_error'
        );
        $this->resetStaticRedisClient(
            CBT_Question_Submission_Context_Cache::class,
            'snapshot_redis',
            'snapshot_redis_connection_attempted',
            'snapshot_redis_last_connection_error'
        );

        parent::tearDown();
    }

    public function test_handle_delete_exam_cleans_runtime_snapshots_and_commits_transaction(): void
    {
        global $wpdb;
        $wpdb = new CBT_Admin_Delete_Safety_Fake_Wpdb();
        $this->seedRuntimeSnapshots(77);
        $wpdb->resetOperationLog();

        $this->invokeDeleteExamExpectRedirect();

        self::assertSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertSame([], $this->storedStartSnapshotKeysFor(77));
        self::assertSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertContains('START TRANSACTION', $wpdb->transactionQueries());
        self::assertContains('COMMIT', $wpdb->transactionQueries());
        self::assertNotContains('ROLLBACK', $wpdb->transactionQueries());
        self::assertStringContainsString('cbt_msg=Exam+deleted', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertGreaterThan(1, (int) CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_exam(77))['version']);
        self::assertGreaterThan(1, (int) CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog())['version']);

        $submissionLookupIndex = $wpdb->firstOperationIndex('submission-context-question-rows:77');
        $questionDeleteIndex = $wpdb->firstOperationIndex('query:DELETE FROM wp_cbt_questions');
        self::assertGreaterThanOrEqual(0, $submissionLookupIndex);
        self::assertGreaterThanOrEqual(0, $questionDeleteIndex);
        self::assertLessThan($questionDeleteIndex, $submissionLookupIndex);
    }

    public function test_handle_delete_exam_rolls_back_on_mysql_failure_without_cache_invalidation(): void
    {
        global $wpdb;
        $wpdb = new CBT_Admin_Delete_Safety_Fake_Wpdb();
        $wpdb->failQueryContains = 'DELETE FROM wp_cbt_answers WHERE attempt_id IN';
        $this->seedRuntimeSnapshots(77);
        $wpdb->resetOperationLog();

        $this->invokeDeleteExamExpectRedirect();

        self::assertContains('START TRANSACTION', $wpdb->transactionQueries());
        self::assertContains('ROLLBACK', $wpdb->transactionQueries());
        self::assertNotContains('COMMIT', $wpdb->transactionQueries());
        self::assertStringContainsString('cbt_err=Gagal+menghapus+ujian+secara+aman', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertSame(1, (int) CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_exam(77))['version']);
        self::assertSame(1, (int) CBT_Cache::get_namespace_registry_entry(CBT_Cache::namespace_catalog())['version']);
    }

    public function test_redis_cleanup_failure_aborts_before_mysql_transaction(): void
    {
        global $wpdb;
        $wpdb = new CBT_Admin_Delete_Safety_Fake_Wpdb();
        $this->useFakeDeliveryRedis(new CBT_Admin_Delete_Throwing_Redis_Client());
        $GLOBALS['cbt_test_redis_storage']['cbt_exam_delivery:exam:77:boom'] = '{"ok":true}';

        $this->invokeDeleteExamExpectRedirect();

        self::assertSame([], $wpdb->transactionQueries());
        self::assertStringContainsString('cbt_err=Gagal+membersihkan+snapshot+runtime+ujian', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertNotSame([], $this->storedExamSnapshotKeysFor(77));
    }

    public function test_bank_exam_is_not_cleaned_or_deleted_from_exam_menu(): void
    {
        global $wpdb;
        $wpdb = new CBT_Admin_Delete_Safety_Fake_Wpdb();
        $wpdb->examTitle = 'Bank Soal - Matematika';
        $this->seedRuntimeSnapshots(77);
        $wpdb->resetOperationLog();

        $this->invokeDeleteExamExpectRedirect();

        self::assertSame([], $wpdb->transactionQueries());
        self::assertNotSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertNotSame([], $this->storedStartSnapshotKeysFor(77));
        self::assertNotSame([], $this->storedSubmissionContextKeysFor(77));
        self::assertStringContainsString('cbt_err=Exam+bank+soal+tidak+boleh+dihapus', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    public function test_non_owner_exam_delete_is_blocked_before_snapshot_cleanup(): void
    {
        global $wpdb;
        $wpdb = new CBT_Admin_Delete_Safety_Fake_Wpdb();
        $wpdb->ownerCount = 0;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = false;
        $GLOBALS['cbt_test_current_user_caps']['cbt_manage_system'] = false;
        $GLOBALS['cbt_test_current_user_caps']['cbt_manage_exams'] = true;
        $this->seedRuntimeSnapshots(77);
        $wpdb->resetOperationLog();

        try {
            CBT_Admin_Exams_Service::handle_delete_exam();
            self::fail('Expected unauthorized delete to stop with wp_die.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Unauthorized exam delete.', $runtimeException->getMessage());
        }

        self::assertSame([], $wpdb->transactionQueries());
        self::assertNotSame([], $this->storedExamSnapshotKeysFor(77));
        self::assertNotSame([], $this->storedStartSnapshotKeysFor(77));
        self::assertNotSame([], $this->storedSubmissionContextKeysFor(77));
    }

    public function test_question_dependent_delete_default_is_silent_but_strict_mode_throws(): void
    {
        global $wpdb;
        $wpdb = new CBT_Admin_Delete_Helper_Failure_Wpdb();

        CBT_Admin_Questions_Helper::delete_question_dependents([101]);
        self::assertNotSame([], $wpdb->queries);

        $wpdb = new CBT_Admin_Delete_Helper_Failure_Wpdb();
        try {
            CBT_Admin_Questions_Helper::delete_question_dependents([101], true, true);
            self::fail('Expected strict question dependent delete to throw.');
        } catch (RuntimeException $runtimeException) {
            self::assertStringContainsString('Gagal menghapus dependent', $runtimeException->getMessage());
        }
    }

    private function invokeDeleteExamExpectRedirect(): void
    {
        try {
            CBT_Admin_Exams_Service::handle_delete_exam();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_exams_redirect__', $runtimeException->getMessage());
        }
    }

    private function seedRuntimeSnapshots(int $examId): void
    {
        CBT_Exam_Question_Delivery_Cache::warm_exam_payload($examId, static function (int $targetExamId): array {
            return [
                [
                    'id' => 977,
                    'exam_id' => $targetExamId,
                    'question_text' => 'Ibu kota Indonesia?',
                    'question_type' => 'multiple_choice',
                    'points' => 1,
                    'options' => [
                        ['id' => 9101, 'option_key' => 'A', 'option_text' => 'Bandung'],
                        ['id' => 9102, 'option_key' => 'B', 'option_text' => 'Jakarta'],
                    ],
                ],
            ];
        });
        CBT_Exam_Start_Attempt_Snapshot_Cache::warm_exam_snapshot($examId, static function (int $targetExamId): array {
            return [
                'exam_id' => $targetExamId,
                'question_ids' => [977, 978],
                'question_count' => 2,
                'question_number_map' => [977 => 1, 978 => 2],
                'randomize_questions' => 0,
                'randomize_options' => 0,
                'duration_minutes' => 90,
                'show_student_result' => 1,
                'enable_calculator' => 1,
                'option_randomization_tokens_by_question' => [],
            ];
        });
        CBT_Question_Submission_Context_Cache::warm_exam_snapshots($examId);
    }

    private function useFakeDeliveryRedis(?Redis $redis = null): void
    {
        $this->setStaticRedisClient(
            CBT_Exam_Question_Delivery_Cache::class,
            'delivery_redis',
            'delivery_redis_connection_attempted',
            'delivery_redis_last_connection_error',
            $redis ?? new CBT_Test_Redis_Client()
        );
    }

    private function useFakeStartSnapshotRedis(?Redis $redis = null): void
    {
        $this->setStaticRedisClient(
            CBT_Exam_Start_Attempt_Snapshot_Cache::class,
            'start_snapshot_redis',
            'start_snapshot_redis_connection_attempted',
            'start_snapshot_redis_last_connection_error',
            $redis ?? new CBT_Test_Redis_Client()
        );
    }

    private function useFakeSubmissionContextRedis(?Redis $redis = null): void
    {
        $this->setStaticRedisClient(
            CBT_Question_Submission_Context_Cache::class,
            'snapshot_redis',
            'snapshot_redis_connection_attempted',
            'snapshot_redis_last_connection_error',
            $redis ?? new CBT_Test_Redis_Client()
        );
    }

    private function setStaticRedisClient(string $className, string $redisProperty, string $attemptedProperty, string $errorProperty, Redis $redis): void
    {
        $reflection = new ReflectionClass($className);

        $property = $reflection->getProperty($redisProperty);
        $property->setAccessible(true);
        $property->setValue(null, $redis);

        $attempted = $reflection->getProperty($attemptedProperty);
        $attempted->setAccessible(true);
        $attempted->setValue(null, true);

        $error = $reflection->getProperty($errorProperty);
        $error->setAccessible(true);
        $error->setValue(null, '');
    }

    private function resetStaticRedisClient(string $className, string $redisProperty, string $attemptedProperty, string $errorProperty): void
    {
        $reflection = new ReflectionClass($className);

        $property = $reflection->getProperty($redisProperty);
        $property->setAccessible(true);
        $property->setValue(null, null);

        $attempted = $reflection->getProperty($attemptedProperty);
        $attempted->setAccessible(true);
        $attempted->setValue(null, false);

        $error = $reflection->getProperty($errorProperty);
        $error->setAccessible(true);
        $error->setValue(null, '');
    }

    /**
     * @return array<int,string>
     */
    private function storedExamSnapshotKeysFor(int $examId): array
    {
        return $this->storedRedisKeysWithPrefix('cbt_exam_delivery:exam:' . $examId . ':');
    }

    /**
     * @return array<int,string>
     */
    private function storedStartSnapshotKeysFor(int $examId): array
    {
        return $this->storedRedisKeysWithPrefix('cbt_exam_start_attempt:exam:' . $examId . ':');
    }

    /**
     * @return array<int,string>
     */
    private function storedSubmissionContextKeysFor(int $examId): array
    {
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key) use ($examId): bool {
            return is_string($key)
                && strpos($key, 'cbt_submit_context:') === 0
                && strpos($key, ':exam:' . $examId . ':') !== false;
        }));
    }

    /**
     * @return array<int,string>
     */
    private function storedRedisKeysWithPrefix(string $prefix): array
    {
        $keys = array_keys((array) ($GLOBALS['cbt_test_redis_storage'] ?? []));

        return array_values(array_filter($keys, static function ($key) use ($prefix): bool {
            return is_string($key) && strpos($key, $prefix) === 0;
        }));
    }
}

final class CBT_Admin_Delete_Throwing_Redis_Client extends CBT_Test_Redis_Client
{
    public function del(...$keys)
    {
        throw new RuntimeException('Redis del failed.');
    }
}

final class CBT_Admin_Delete_Safety_Fake_Wpdb
{
    public string $prefix = 'wp_';
    public string $examTitle = 'Ujian Matematika';
    public int $ownerCount = 1;
    public string $failQueryContains = '';
    public string $failDeleteTable = '';

    /** @var array<int,string> */
    public array $queries = [];

    /** @var array<int,array{table:string,where:array<string,mixed>}> */
    public array $deletes = [];

    /** @var array<int,string> */
    public array $operationLog = [];

    /** @var array<int,array<string,mixed>> */
    private array $questionRows;

    /** @var array<int,array<int,array<string,mixed>>> */
    private array $optionsByQuestion;

    /** @var array<int,int> */
    private array $attemptIds = [501, 502];

    public function __construct()
    {
        $this->questionRows = [
            977 => [
                'id' => 977,
                'exam_id' => 77,
                'question_type' => 'multiple_choice',
                'points' => 1,
                'correct_text' => '',
                'true_false_correct_value' => null,
                'short_answer_correct_text' => null,
            ],
            978 => [
                'id' => 978,
                'exam_id' => 77,
                'question_type' => 'short_answer',
                'points' => 1,
                'correct_text' => '',
                'true_false_correct_value' => null,
                'short_answer_correct_text' => 'Jakarta',
            ],
        ];
        $this->optionsByQuestion = [
            977 => [
                ['id' => 9101, 'question_id' => 977, 'option_text' => 'Bandung', 'is_correct' => 0],
                ['id' => 9102, 'question_id' => 977, 'option_text' => 'Jakarta', 'is_correct' => 1],
            ],
        ];
    }

    public function resetOperationLog(): void
    {
        $this->queries = [];
        $this->deletes = [];
        $this->operationLog = [];
    }

    public function prepare($query, ...$args): string
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $prepared = (string) $query;
        foreach ($args as $arg) {
            $replacement = is_int($arg) || is_float($arg)
                ? (string) $arg
                : "'" . str_replace("'", "''", (string) $arg) . "'";
            $prepared = preg_replace('/%[dfs]/', $replacement, $prepared, 1) ?? $prepared;
        }

        return $prepared;
    }

    public function get_var($prepared)
    {
        $query = (string) $prepared;

        if (strpos($query, 'SELECT title FROM wp_cbt_exams WHERE id = 77') !== false) {
            return $this->examTitle;
        }
        if (strpos($query, 'SELECT COUNT(*) FROM wp_cbt_exams WHERE id = 77 AND created_by =') !== false) {
            return $this->ownerCount;
        }

        return 0;
    }

    /**
     * @return array<int,int>
     */
    public function get_col($prepared): array
    {
        $query = (string) $prepared;

        if (strpos($query, 'SELECT id FROM wp_cbt_questions WHERE exam_id = 77') !== false) {
            $this->operationLog[] = 'get_col:questions:77';
            return array_keys($this->questionRows);
        }
        if (strpos($query, 'SELECT id FROM wp_cbt_attempts WHERE exam_id = 77') !== false) {
            $this->operationLog[] = 'get_col:attempts:77';
            return $this->attemptIds;
        }

        return [];
    }

    public function get_row($prepared, $output = null): ?array
    {
        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = null): array
    {
        $query = (string) $prepared;

        if (strpos($query, 'FROM wp_cbt_questions q') !== false) {
            $rows = [];
            foreach ($this->extractIdsFromInClause($query) as $questionId) {
                if (isset($this->questionRows[$questionId])) {
                    $rows[] = $this->questionRows[$questionId];
                }
            }

            return $rows;
        }

        if (strpos($query, 'FROM wp_cbt_questions') !== false && strpos($query, 'WHERE exam_id = 77') !== false) {
            $this->operationLog[] = 'submission-context-question-rows:77';
            return array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'question_type' => (string) ($row['question_type'] ?? ''),
                ];
            }, array_values($this->questionRows));
        }

        if (strpos($query, 'FROM wp_cbt_options') !== false) {
            $rows = [];
            foreach ($this->extractIdsFromInClause($query) as $questionId) {
                foreach ($this->optionsByQuestion[$questionId] ?? [] as $optionRow) {
                    $rows[] = $optionRow;
                }
            }

            return $rows;
        }

        return [];
    }

    public function query($query)
    {
        $query = trim((string) $query);
        $this->queries[] = $query;
        $this->operationLog[] = 'query:' . preg_replace('/\s+/', ' ', $query);

        if ($this->failQueryContains !== '' && strpos($query, $this->failQueryContains) !== false) {
            return false;
        }

        return 1;
    }

    /**
     * @param array<string,mixed> $where
     * @param array<int,string> $where_format
     */
    public function delete($table, $where, $where_format = null)
    {
        $table = (string) $table;
        $this->deletes[] = [
            'table' => $table,
            'where' => (array) $where,
        ];
        $this->operationLog[] = 'delete:' . $table;

        if ($this->failDeleteTable !== '' && $table === $this->failDeleteTable) {
            return false;
        }

        return 1;
    }

    /**
     * @return array<int,string>
     */
    public function transactionQueries(): array
    {
        return array_values(array_filter($this->queries, static function (string $query): bool {
            return in_array($query, ['START TRANSACTION', 'COMMIT', 'ROLLBACK'], true);
        }));
    }

    public function firstOperationIndex(string $needle): int
    {
        foreach ($this->operationLog as $index => $operation) {
            if (strpos($operation, $needle) !== false) {
                return $index;
            }
        }

        return -1;
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

final class CBT_Admin_Delete_Helper_Failure_Wpdb
{
    public string $prefix = 'wp_';

    /** @var array<int,string> */
    public array $queries = [];

    public function prepare($query, ...$args): string
    {
        $prepared = (string) $query;
        foreach ($args as $arg) {
            $replacement = is_int($arg) || is_float($arg)
                ? (string) $arg
                : "'" . str_replace("'", "''", (string) $arg) . "'";
            $prepared = preg_replace('/%[dfs]/', $replacement, $prepared, 1) ?? $prepared;
        }

        return $prepared;
    }

    public function query($query)
    {
        $this->queries[] = (string) $query;
        return false;
    }
}
