<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminResultsBulkJobServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_user_caps']['cbt_view_results'] = true;
        $GLOBALS['cbt_test_current_user_id'] = 1;
        $GLOBALS['cbt_test_last_ajax_response'] = null;
        $GLOBALS['cbt_test_last_redirect'] = null;
    }

    #[RunInSeparateProcess]
    public function test_handle_bulk_force_complete_attempts_resumes_existing_active_job(): void
    {
        $this->bootstrapResultsService();

        $token = 'existingjobtoken';
        set_transient('cbt_results_bulk_job_' . $token, [
            'token' => $token,
            'mode' => 'reset',
            'status' => 'running',
            'created_by' => get_current_user_id(),
            'created_at' => time(),
            'updated_at' => time(),
            'return_context' => [
                'exam_id' => 0,
                'status' => '',
                'kelas' => '',
                'student_keyword' => '',
                'paged' => 1,
            ],
            'target_rows' => [
                ['attempt_id' => 44, 'exam_id' => 9, 'student_id' => 7],
            ],
            'cursor' => 0,
            'total' => 1,
            'processed_count' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'reset_count' => 0,
            'abandoned_count' => 0,
            'completed_count' => 0,
            'failed_attempt_ids_sample' => [],
            'last_error_message' => '',
            'last_message' => 'Job lama masih aktif.',
            'last_detail' => '',
            'affected_exam_ids' => [],
            'affected_user_ids' => [],
            'affected_attempt_ids' => [],
        ], HOUR_IN_SECONDS);
        set_transient('cbt_results_bulk_job_active_' . get_current_user_id(), $token, HOUR_IN_SECONDS);

        global $wpdb;
        $wpdb = new AdminResultsBulkJobFakeWpdb([
            44 => ['id' => 44, 'exam_id' => 9, 'student_id' => 7, 'status' => 'completed'],
        ]);

        $_POST = [];

        try {
            CBT_Admin_Results_Service::handle_bulk_force_complete_attempts();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        self::assertStringContainsString('cbt_results_bulk_token=' . $token, (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
        self::assertSame($token, get_transient('cbt_results_bulk_job_active_' . get_current_user_id()));
    }

    #[RunInSeparateProcess]
    public function test_handle_bulk_reset_attempts_starts_job_and_preserves_filters_in_redirect(): void
    {
        $this->bootstrapResultsService();

        global $wpdb;
        $wpdb = new AdminResultsBulkJobFakeWpdb([
            44 => ['id' => 44, 'exam_id' => 9, 'student_id' => 7, 'status' => 'completed'],
            45 => ['id' => 45, 'exam_id' => 10, 'student_id' => 8, 'status' => 'completed'],
        ]);

        $_POST = [
            'cbt_exam_id' => '10',
            'cbt_attempt_status' => '',
            'cbt_result_kelas' => 'X IPA 1',
            'cbt_student_q' => 'siswa0001',
            'cbt_results_paged' => '3',
        ];

        try {
            CBT_Admin_Results_Service::handle_bulk_reset_attempts();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        $redirectUrl = (string) ($GLOBALS['cbt_test_last_redirect'] ?? '');
        self::assertStringContainsString('page=cbt-results', $redirectUrl);
        self::assertStringContainsString('cbt_exam_id=10', $redirectUrl);
        self::assertStringContainsString('cbt_result_kelas=X+IPA+1', $redirectUrl);
        self::assertStringContainsString('cbt_student_q=siswa0001', $redirectUrl);
        self::assertStringContainsString('cbt_results_paged=3', $redirectUrl);
        self::assertMatchesRegularExpression('/cbt_results_bulk_token=[a-z0-9_-]+/', $redirectUrl);

        $token = (string) get_transient('cbt_results_bulk_job_active_' . get_current_user_id());
        self::assertNotSame('', $token);
        $state = get_transient('cbt_results_bulk_job_' . $token);
        self::assertIsArray($state);
        self::assertSame('reset', $state['mode'] ?? '');
        self::assertSame(2, $state['total'] ?? 0);
        self::assertSame('X IPA 1', $state['return_context']['kelas'] ?? '');
    }

    #[RunInSeparateProcess]
    public function test_bulk_reset_tick_processes_two_chunks_and_invalidates_analytics_once_at_end(): void
    {
        $this->bootstrapResultsService();

        $attemptRows = [];
        for ($index = 1; $index <= 101; $index++) {
            $attemptId = 1000 + $index;
            $examId = 2000 + $index;
            $studentId = 3000 + $index;
            $attemptRows[$attemptId] = [
                'id' => $attemptId,
                'exam_id' => $examId,
                'student_id' => $studentId,
                'status' => 'completed',
            ];
            if ($index <= 2) {
                $attemptRows[5000 + $index] = [
                    'id' => 5000 + $index,
                    'exam_id' => $examId,
                    'student_id' => $studentId,
                    'status' => 'in_progress',
                ];
            }
        }

        global $wpdb;
        $wpdb = new AdminResultsBulkJobFakeWpdb($attemptRows);

        $_POST = [];
        try {
            CBT_Admin_Results_Service::handle_bulk_reset_attempts();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        $token = (string) get_transient('cbt_results_bulk_job_active_' . get_current_user_id());
        self::assertNotSame('', $token);

        $_POST = [
            'token' => $token,
            'nonce' => wp_create_nonce('cbt_results_bulk_job_tick'),
        ];
        $firstResponse = $this->invokeBulkTickAjax();

        self::assertTrue($firstResponse['success']);
        self::assertFalse($firstResponse['payload']['complete']);
        self::assertSame(100, $firstResponse['payload']['processed_count']);
        self::assertSame(100, $firstResponse['payload']['reset_count']);
        self::assertSame(0, CBT_Cache::$invalidateAnalyticsCalls);

        $_POST = [
            'token' => $token,
            'nonce' => wp_create_nonce('cbt_results_bulk_job_tick'),
        ];
        $secondResponse = $this->invokeBulkTickAjax();

        self::assertTrue($secondResponse['success']);
        self::assertTrue($secondResponse['payload']['complete']);
        self::assertSame(101, $secondResponse['payload']['processed_count']);
        self::assertSame(101, $secondResponse['payload']['reset_count']);
        self::assertSame(2, $secondResponse['payload']['abandoned_count']);
        self::assertSame(1, CBT_Cache::$invalidateAnalyticsCalls);
        self::assertCount(101, CBT_Cache::$invalidatedAnalyticsExamIdsBatch);
        self::assertSame([], CBT_Auth::$clearedLoginSessionUserIds);
        self::assertCount(2, array_filter(CBT_Runtime::$clearedAttemptIds, static fn ($attemptId): bool => $attemptId >= 5001));
        self::assertSame('', (string) get_transient('cbt_results_bulk_job_active_' . get_current_user_id()));
        self::assertFalse((bool) get_transient('cbt_results_bulk_job_' . $token));
    }

    #[RunInSeparateProcess]
    public function test_bulk_force_complete_tick_continues_after_partial_failures(): void
    {
        $this->bootstrapResultsService();

        global $wpdb;
        $wpdb = new AdminResultsBulkJobFakeWpdb([
            201 => ['id' => 201, 'exam_id' => 11, 'student_id' => 71, 'status' => 'in_progress'],
            202 => ['id' => 202, 'exam_id' => 11, 'student_id' => 72, 'status' => 'in_progress'],
            203 => ['id' => 203, 'exam_id' => 12, 'student_id' => 73, 'status' => 'in_progress'],
        ]);

        CBT_REST::$failingAttemptIds = [202];

        $_POST = [];
        try {
            CBT_Admin_Results_Service::handle_bulk_force_complete_attempts();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        $token = (string) get_transient('cbt_results_bulk_job_active_' . get_current_user_id());
        self::assertNotSame('', $token);

        $_POST = [
            'token' => $token,
            'nonce' => wp_create_nonce('cbt_results_bulk_job_tick'),
        ];
        $response = $this->invokeBulkTickAjax();

        self::assertTrue($response['success']);
        self::assertTrue($response['payload']['complete']);
        self::assertSame('completed_with_errors', $response['payload']['status']);
        self::assertSame(3, $response['payload']['processed_count']);
        self::assertSame(2, $response['payload']['success_count']);
        self::assertSame(1, $response['payload']['failure_count']);
        self::assertSame(2, $response['payload']['completed_count']);
        self::assertSame(1, CBT_Cache::$invalidateAnalyticsCalls);
        $invalidatedAttemptsBatch = CBT_Cache::$invalidatedAttemptsBatches[0] ?? [];
        sort($invalidatedAttemptsBatch);
        self::assertSame([201, 203], $invalidatedAttemptsBatch);
        $invalidatedUsersBatch = CBT_Cache::$invalidatedUsersBatches[0] ?? [];
        sort($invalidatedUsersBatch);
        self::assertSame([71, 73], $invalidatedUsersBatch);
        $attemptIdsCalled = array_column(CBT_REST::$calls, 'attempt_id');
        sort($attemptIdsCalled);
        self::assertSame([201, 202, 203], $attemptIdsCalled);
        self::assertSame([true, true, true], array_column(CBT_REST::$calls, 'defer_invalidation'));
        self::assertStringContainsString('cbt_msg=Berhasil+memaksa+2+attempt+in_progress+menjadi+completed.', (string) ($response['payload']['redirect_url'] ?? ''));
        self::assertStringContainsString('cbt_err=1+attempt+gagal+diproses.', (string) ($response['payload']['redirect_url'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_bulk_reset_stop_request_halts_before_next_chunk(): void
    {
        $this->bootstrapResultsService();

        $attemptRows = [];
        for ($index = 1; $index <= 101; $index++) {
            $attemptId = 7000 + $index;
            $examId = 8000 + $index;
            $studentId = 9000 + $index;
            $attemptRows[$attemptId] = [
                'id' => $attemptId,
                'exam_id' => $examId,
                'student_id' => $studentId,
                'status' => 'completed',
            ];
        }

        global $wpdb;
        $wpdb = new AdminResultsBulkJobFakeWpdb($attemptRows);

        $_POST = [];
        try {
            CBT_Admin_Results_Service::handle_bulk_reset_attempts();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        $token = (string) get_transient('cbt_results_bulk_job_active_' . get_current_user_id());
        self::assertNotSame('', $token);

        $_POST = [
            'token' => $token,
            'nonce' => wp_create_nonce('cbt_results_bulk_job_tick'),
        ];
        $firstTick = $this->invokeBulkTickAjax();
        self::assertFalse($firstTick['payload']['complete']);
        self::assertSame(100, $firstTick['payload']['processed_count']);

        $_POST = [
            'token' => $token,
            'nonce' => wp_create_nonce('cbt_results_bulk_job_stop'),
        ];
        $stopResponse = $this->invokeBulkStopAjax();
        self::assertTrue($stopResponse['success']);
        self::assertFalse($stopResponse['payload']['complete']);
        self::assertTrue($stopResponse['payload']['stop_requested']);
        self::assertSame('Menghentikan', $stopResponse['payload']['status_label']);

        $_POST = [
            'token' => $token,
            'nonce' => wp_create_nonce('cbt_results_bulk_job_tick'),
        ];
        $finalTick = $this->invokeBulkTickAjax();

        self::assertTrue($finalTick['success']);
        self::assertTrue($finalTick['payload']['complete']);
        self::assertSame('stopped', $finalTick['payload']['status']);
        self::assertSame(100, $finalTick['payload']['processed_count']);
        self::assertSame(100, $finalTick['payload']['reset_count']);
        self::assertSame(1, CBT_Cache::$invalidateAnalyticsCalls);
        self::assertStringContainsString('cbt_msg=Batch+reset+dihentikan.', (string) ($finalTick['payload']['redirect_url'] ?? ''));
        self::assertFalse((bool) get_transient('cbt_results_bulk_job_' . $token));
        self::assertSame('', (string) get_transient('cbt_results_bulk_job_active_' . get_current_user_id()));
    }

    #[RunInSeparateProcess]
    public function test_bulk_force_complete_stop_request_halts_before_next_chunk(): void
    {
        $this->bootstrapResultsService();

        $attemptRows = [];
        for ($index = 1; $index <= 21; $index++) {
            $attemptId = 9100 + $index;
            $attemptRows[$attemptId] = [
                'id' => $attemptId,
                'exam_id' => 120 + $index,
                'student_id' => 220 + $index,
                'status' => 'in_progress',
            ];
        }

        global $wpdb;
        $wpdb = new AdminResultsBulkJobFakeWpdb($attemptRows);

        $_POST = [];
        try {
            CBT_Admin_Results_Service::handle_bulk_force_complete_attempts();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        $token = (string) get_transient('cbt_results_bulk_job_active_' . get_current_user_id());
        self::assertNotSame('', $token);

        $_POST = [
            'token' => $token,
            'nonce' => wp_create_nonce('cbt_results_bulk_job_tick'),
        ];
        $firstTick = $this->invokeBulkTickAjax();
        self::assertFalse($firstTick['payload']['complete']);
        self::assertSame(20, $firstTick['payload']['processed_count']);
        self::assertCount(20, CBT_REST::$calls);

        $_POST = [
            'token' => $token,
            'nonce' => wp_create_nonce('cbt_results_bulk_job_stop'),
        ];
        $stopResponse = $this->invokeBulkStopAjax();
        self::assertTrue($stopResponse['success']);
        self::assertTrue($stopResponse['payload']['stop_requested']);

        $_POST = [
            'token' => $token,
            'nonce' => wp_create_nonce('cbt_results_bulk_job_tick'),
        ];
        $finalTick = $this->invokeBulkTickAjax();

        self::assertTrue($finalTick['success']);
        self::assertTrue($finalTick['payload']['complete']);
        self::assertSame('stopped', $finalTick['payload']['status']);
        self::assertSame(20, $finalTick['payload']['processed_count']);
        self::assertSame(20, $finalTick['payload']['completed_count']);
        self::assertCount(20, CBT_REST::$calls);
        self::assertSame(1, CBT_Cache::$invalidateAnalyticsCalls);
        self::assertStringContainsString('cbt_msg=Batch+paksa+complete+dihentikan.', (string) ($finalTick['payload']['redirect_url'] ?? ''));
    }

    /**
     * @return array{success:bool,status_code:int,payload:array<string,mixed>}
     */
    private function invokeBulkTickAjax(): array
    {
        try {
            CBT_Admin_Results_Service::handle_bulk_job_tick_ajax();
            self::fail('Expected AJAX signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $response = $GLOBALS['cbt_test_last_ajax_response'] ?? null;
        self::assertIsArray($response);

        /** @var array{success:bool,status_code:int,payload:array<string,mixed>} $response */
        return $response;
    }

    /**
     * @return array{success:bool,status_code:int,payload:array<string,mixed>}
     */
    private function invokeBulkStopAjax(): array
    {
        try {
            CBT_Admin_Results_Service::handle_bulk_job_stop_ajax();
            self::fail('Expected AJAX signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_ajax__', $runtimeException->getMessage());
        }

        $response = $GLOBALS['cbt_test_last_ajax_response'] ?? null;
        self::assertIsArray($response);

        /** @var array{success:bool,status_code:int,payload:array<string,mixed>} $response */
        return $response;
    }

    private function bootstrapResultsService(): void
    {
        if (!class_exists('CBT_Cache')) {
            eval(<<<'PHP'
class CBT_Cache
{
    public static array $invalidatedAttemptIds = [];
    public static array $invalidatedAttemptsBatches = [];
    public static array $invalidatedUserIds = [];
    public static array $invalidatedUsersBatches = [];
    public static array $invalidatedAnalyticsExamIds = [];
    public static array $invalidatedAnalyticsExamIdsBatch = [];
    public static int $invalidateAnalyticsCalls = 0;

    public static function invalidate_attempt(int $attempt_id): void
    {
        self::$invalidatedAttemptIds[] = $attempt_id;
    }

    public static function invalidate_attempts(array $attempt_ids): void
    {
        self::$invalidatedAttemptsBatches[] = array_values(array_map('intval', $attempt_ids));
    }

    public static function invalidate_user(int $user_id): void
    {
        self::$invalidatedUserIds[] = $user_id;
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

    public static function invalidate_analytics_exams(array $exam_ids): void
    {
        self::$invalidatedAnalyticsExamIdsBatch = array_values(array_map('intval', $exam_ids));
    }
}
PHP);
        }

        if (!class_exists('CBT_Runtime')) {
            eval(<<<'PHP'
class CBT_Runtime
{
    public static array $clearedAttemptIds = [];

    public static function clear_attempt_runtime(int $attempt_id): void
    {
        self::$clearedAttemptIds[] = $attempt_id;
    }
}
PHP);
        }

        if (!class_exists('CBT_UI_State')) {
            eval(<<<'PHP'
class CBT_UI_State
{
    public static array $clearedAttemptStates = [];
    public static array $clearedAttemptStatesByAttemptIds = [];

    public static function clear_attempt_state(int $user_id, int $attempt_id): void
    {
        self::$clearedAttemptStates[] = [$user_id, $attempt_id];
    }

    public static function clear_attempt_states_by_attempt_ids(array $attempt_ids): void
    {
        self::$clearedAttemptStatesByAttemptIds[] = array_values(array_map('intval', $attempt_ids));
    }
}
PHP);
        }

        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $calls = [];
    public static array $failingAttemptIds = [];

    public static function finalize_attempt_completion(int $attempt_id, ?string $finished_at = null, array $options = [])
    {
        self::$calls[] = [
            'attempt_id' => $attempt_id,
            'finished_at' => $finished_at,
            'defer_invalidation' => !empty($options['defer_invalidation']),
        ];

        if (in_array($attempt_id, self::$failingAttemptIds, true)) {
            return new WP_Error('force_complete_failed', 'Simulated failure.');
        }

        return [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
        ];
    }
}
PHP);
        }

        if (!class_exists('CBT_Auth')) {
            eval(<<<'PHP'
class CBT_Auth
{
    public static array $clearedLoginSessionUserIds = [];

    public static function clear_login_session(int $user_id, ?string $session_key = null): bool
    {
        self::$clearedLoginSessionUserIds[] = $user_id;
        return $user_id > 0;
    }
}
PHP);
        }

        CBT_Cache::$invalidatedAttemptIds = [];
        CBT_Cache::$invalidatedAttemptsBatches = [];
        CBT_Cache::$invalidatedUserIds = [];
        CBT_Cache::$invalidatedUsersBatches = [];
        CBT_Cache::$invalidatedAnalyticsExamIds = [];
        CBT_Cache::$invalidatedAnalyticsExamIdsBatch = [];
        CBT_Cache::$invalidateAnalyticsCalls = 0;
        CBT_Runtime::$clearedAttemptIds = [];
        CBT_UI_State::$clearedAttemptStates = [];
        CBT_UI_State::$clearedAttemptStatesByAttemptIds = [];
        CBT_REST::$calls = [];
        CBT_REST::$failingAttemptIds = [];
        CBT_Auth::$clearedLoginSessionUserIds = [];

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-active-attempt-index.php';
        $this->useFakeActiveAttemptRedisClient();
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-service.php';
    }

    private function useFakeActiveAttemptRedisClient(): void
    {
        $reflection = new ReflectionClass(CBT_Active_Attempt_Index::class);

        $redisProperty = $reflection->getProperty('active_attempt_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('active_attempt_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('active_attempt_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}

final class AdminResultsBulkJobFakeWpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';

    /** @var array<int,array<string,mixed>> */
    private array $attemptRows;

    /**
     * @param array<int,array<string,mixed>> $attemptRows
     */
    public function __construct(array $attemptRows)
    {
        $this->attemptRows = $attemptRows;
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

    public function esc_like(string $text): string
    {
        return addslashes($text);
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;

        if (str_contains($query, 'SELECT a.id AS attempt_id, a.exam_id, a.student_id') || str_contains($query, 'SELECT a.id, a.exam_id, a.student_id')) {
            $targetStatus = str_contains($query, "a.status = 'in_progress'") ? 'in_progress' : 'completed';
            $rows = [];
            foreach ($this->getAttemptRowsByStatus($targetStatus) as $row) {
                $rows[] = [
                    'attempt_id' => (int) ($row['id'] ?? 0),
                    'exam_id' => (int) ($row['exam_id'] ?? 0),
                    'student_id' => (int) ($row['student_id'] ?? 0),
                ];
            }

            return $rows;
        }

        if (str_contains($query, "FROM {$this->prefix}cbt_attempts") && str_contains($query, "WHERE status = 'completed'") && str_contains($query, 'id IN (')) {
            $ids = $this->parseIdsFromQuery($query);
            $rows = [];
            foreach ($ids as $attemptId) {
                $row = $this->attemptRows[$attemptId] ?? null;
                if (!is_array($row) || (string) ($row['status'] ?? '') !== 'completed') {
                    continue;
                }

                $rows[] = [
                    'attempt_id' => (int) ($row['id'] ?? 0),
                    'exam_id' => (int) ($row['exam_id'] ?? 0),
                    'student_id' => (int) ($row['student_id'] ?? 0),
                ];
            }

            return $rows;
        }

        return [];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_col($prepared): array
    {
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $pairs = $this->buildPairsFromArgs($args, 0);
        $results = [];
        foreach ($this->attemptRows as $attemptRow) {
            if ((string) ($attemptRow['status'] ?? '') !== 'in_progress') {
                continue;
            }

            foreach ($pairs as $pair) {
                if (
                    (int) ($attemptRow['exam_id'] ?? 0) === $pair['exam_id']
                    && (int) ($attemptRow['student_id'] ?? 0) === $pair['student_id']
                ) {
                    $results[] = (int) ($attemptRow['id'] ?? 0);
                    break;
                }
            }
        }

        sort($results);
        return $results;
    }

    /** @param array<string,mixed>|string $prepared */
    public function query($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (str_contains($query, "SET status = 'abandoned'")) {
            $pairs = $this->buildPairsFromArgs($args, 1);
            $affected = 0;
            foreach ($this->attemptRows as &$attemptRow) {
                if ((string) ($attemptRow['status'] ?? '') !== 'in_progress') {
                    continue;
                }

                foreach ($pairs as $pair) {
                    if (
                        (int) ($attemptRow['exam_id'] ?? 0) === $pair['exam_id']
                        && (int) ($attemptRow['student_id'] ?? 0) === $pair['student_id']
                    ) {
                        $attemptRow['status'] = 'abandoned';
                        $affected++;
                        break;
                    }
                }
            }
            unset($attemptRow);

            return $affected;
        }

        if (str_contains($query, "SET status = 'in_progress'")) {
            $ids = $this->parseIdsFromQuery($query);
            $affected = 0;
            foreach ($ids as $attemptId) {
                if (!isset($this->attemptRows[$attemptId]) || (string) ($this->attemptRows[$attemptId]['status'] ?? '') !== 'completed') {
                    continue;
                }

                $this->attemptRows[$attemptId]['status'] = 'in_progress';
                $affected++;
            }

            return $affected;
        }

        return 0;
    }

    private function parseIdsFromQuery(string $query): array
    {
        if (!preg_match('/id IN \(([\d,\s]+)\)/', $query, $matches)) {
            return [];
        }

        return array_values(array_filter(array_map('absint', explode(',', (string) ($matches[1] ?? '')))));
    }

    /**
     * @param array<int,mixed> $args
     * @return array<int,array{exam_id:int,student_id:int}>
     */
    private function buildPairsFromArgs(array $args, int $offset): array
    {
        $pairs = [];
        for ($index = $offset; $index < count($args); $index += 2) {
            $examId = isset($args[$index]) ? (int) $args[$index] : 0;
            $studentId = isset($args[$index + 1]) ? (int) $args[$index + 1] : 0;
            if ($examId <= 0 || $studentId <= 0) {
                continue;
            }

            $pairs[] = [
                'exam_id' => $examId,
                'student_id' => $studentId,
            ];
        }

        return $pairs;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getAttemptRowsByStatus(string $status): array
    {
        $rows = array_values(array_filter($this->attemptRows, static function (array $attemptRow) use ($status): bool {
            return (string) ($attemptRow['status'] ?? '') === $status;
        }));

        usort($rows, static function (array $left, array $right): int {
            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return $rows;
    }
}
