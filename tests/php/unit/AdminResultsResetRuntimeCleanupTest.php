<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminResultsResetRuntimeCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_user_caps']['cbt_view_results'] = true;
    }

    #[RunInSeparateProcess]
    public function test_handle_reset_attempt_clears_runtime_and_ui_state_for_abandoned_siblings(): void
    {
        $this->bootstrapResultsService();

        global $wpdb;
        $wpdb = new AdminResultsResetRuntimeCleanupFakeWpdb(
            singleAttempt: [
                'id' => 44,
                'exam_id' => 9,
                'student_id' => 7,
                'status' => 'completed',
            ],
            abandonedIdsByPair: [
                '9:7' => [31, 32],
            ],
            bulkTargetRows: []
        );

        $_POST = [
            'attempt_id' => 44,
        ];

        try {
            CBT_Admin_Results_Service::handle_reset_attempt();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([31, 32, 44], CBT_Runtime::$clearedAttemptIds);
        self::assertSame([[31, 32]], CBT_Cache::$invalidatedAttemptsBatches);
        self::assertSame([44], CBT_Cache::$invalidatedAttemptIds);
        self::assertSame([7], CBT_Cache::$invalidatedUserIds);
        self::assertSame([9], CBT_Cache::$invalidatedAnalyticsExamIds);
        self::assertSame([[31, 32]], CBT_UI_State::$clearedAttemptStatesByAttemptIds);
        self::assertSame([[7, 44]], CBT_UI_State::$clearedAttemptStates);
        self::assertSame([], CBT_Auth::$clearedLoginSessionUserIds);
        self::assertSame(44, CBT_Active_Attempt_Index::get_active_attempt_id(7, 9));
        self::assertStringContainsString('cbt_msg=Attempt+berhasil+di-reset.', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_handle_bulk_reset_attempts_starts_bulk_job_and_redirects_to_token(): void
    {
        $this->bootstrapResultsService();

        global $wpdb;
        $wpdb = new AdminResultsResetRuntimeCleanupFakeWpdb(
            singleAttempt: null,
            abandonedIdsByPair: [
                '9:7' => [31],
                '10:8' => [32, 33],
            ],
            bulkTargetRows: [
                [
                    'id' => 44,
                    'exam_id' => 9,
                    'student_id' => 7,
                ],
                [
                    'id' => 45,
                    'exam_id' => 10,
                    'student_id' => 8,
                ],
            ]
        );

        $_POST = [];

        try {
            CBT_Admin_Results_Service::handle_bulk_reset_attempts();
            self::fail('Expected redirect signal was not thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('__cbt_admin_results_redirect__', $runtimeException->getMessage());
        }

        self::assertSame([], CBT_Runtime::$clearedAttemptIds);
        self::assertSame([], CBT_Cache::$invalidatedAttemptsBatches);
        self::assertSame([], CBT_Cache::$invalidatedUsersBatches);
        self::assertSame([], CBT_UI_State::$clearedAttemptStatesByAttemptIds);
        self::assertSame(0, CBT_Active_Attempt_Index::get_active_attempt_id(7, 9));
        self::assertMatchesRegularExpression('/cbt_results_bulk_token=[a-z0-9_-]+/', (string) ($GLOBALS['cbt_test_last_redirect'] ?? ''));

        $token = (string) get_transient('cbt_results_bulk_job_active_' . get_current_user_id());
        self::assertNotSame('', $token);

        $state = get_transient('cbt_results_bulk_job_' . $token);
        self::assertIsArray($state);
        self::assertSame('reset', $state['mode'] ?? '');
        self::assertSame(2, $state['total'] ?? 0);
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

final class AdminResultsResetRuntimeCleanupFakeWpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';

    /** @var array<string,mixed>|null */
    private ?array $singleAttempt;

    /** @var array<string,array<int,int>> */
    private array $abandonedIdsByPair;

    /** @var array<int,array<string,mixed>> */
    private array $bulkTargetRows;

    private int $bulkAbandonQueryIndex = 0;

    /**
     * @param array<string,mixed>|null $singleAttempt
     * @param array<string,array<int,int>> $abandonedIdsByPair
     * @param array<int,array<string,mixed>> $bulkTargetRows
     */
    public function __construct(?array $singleAttempt, array $abandonedIdsByPair, array $bulkTargetRows)
    {
        $this->singleAttempt = $singleAttempt;
        $this->abandonedIdsByPair = $abandonedIdsByPair;
        $this->bulkTargetRows = $bulkTargetRows;
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
    public function get_row($prepared, $output = null): ?array
    {
        return $this->singleAttempt;
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        return $this->bulkTargetRows;
    }

    public function esc_like(string $text): string
    {
        return addslashes($text);
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_col($prepared): array
    {
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $examId = isset($args[0]) ? (int) $args[0] : 0;
        $studentId = isset($args[1]) ? (int) $args[1] : 0;
        $key = $examId . ':' . $studentId;

        return $this->abandonedIdsByPair[$key] ?? [];
    }

    /** @param array<string,mixed>|string $prepared */
    public function query($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (str_contains($query, "SET status = 'abandoned'")) {
            if (isset($args[1], $args[2])) {
                $key = ((int) $args[1]) . ':' . ((int) $args[2]);
                return count($this->abandonedIdsByPair[$key] ?? []);
            }

            if (!empty($this->abandonedIdsByPair)) {
                $values = array_values($this->abandonedIdsByPair);
                $current = $values[$this->bulkAbandonQueryIndex] ?? [];
                $this->bulkAbandonQueryIndex++;
                return count((array) $current);
            }
        }

        if (str_contains($query, "SET status = 'in_progress'")) {
            return count($this->bulkTargetRows);
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        return 1;
    }
}
