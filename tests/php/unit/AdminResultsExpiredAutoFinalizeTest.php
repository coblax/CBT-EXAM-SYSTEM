<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminResultsExpiredAutoFinalizeTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_maybe_auto_finalize_expired_attempt_rows_only_completes_expired_in_progress_attempts(): void
    {
        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $finalizedAttemptIds = [];
    public static array $failingAttemptIds = [];

    public static function finalize_attempt_completion(int $attempt_id, ?string $finished_at = null, array $options = [])
    {
        if (in_array($attempt_id, self::$failingAttemptIds, true)) {
            return new WP_Error('auto_finalize_failed', 'Simulated failure.');
        }

        self::$finalizedAttemptIds[] = $attempt_id;

        return [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
        ];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-service.php';
        if (!function_exists('wp_clear_scheduled_hook') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-includes/cron.php';
        }

        CBT_REST::$finalizedAttemptIds = [];
        CBT_REST::$failingAttemptIds = [14];
        $notYetExpiredStartedAt = (new DateTimeImmutable('now', wp_timezone()))->modify('-6 minutes')->format('Y-m-d H:i:s');
        $GLOBALS['wpdb'] = new AdminResultsExpiredAutoFinalizeReloadFakeWpdb([
            11 => [
                'id' => 11,
                'exam_id' => 2,
                'student_id' => 101,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'exam_duration_minutes' => 30,
                'extra_time_minutes' => 0,
            ],
            14 => [
                'id' => 14,
                'exam_id' => 2,
                'student_id' => 104,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'exam_duration_minutes' => 30,
                'extra_time_minutes' => 0,
            ],
        ]);

        $method = new ReflectionMethod(CBT_Admin_Results_Service::class, 'maybe_auto_finalize_expired_attempt_rows');
        $method->setAccessible(true);

        $result = $method->invoke(null, [
            [
                'id' => 11,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'exam_duration_minutes' => 30,
                'extra_time_minutes' => 0,
            ],
            [
                'id' => 12,
                'status' => 'in_progress',
                'started_at' => '2999-01-01 00:00:00',
                'exam_duration_minutes' => 30,
                'extra_time_minutes' => 0,
            ],
            [
                'id' => 13,
                'status' => 'completed',
                'started_at' => '2000-01-01 00:00:00',
                'exam_duration_minutes' => 30,
                'extra_time_minutes' => 0,
            ],
            [
                'id' => 14,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'exam_duration_minutes' => 30,
                'extra_time_minutes' => 0,
            ],
            [
                'id' => 15,
                'status' => 'in_progress',
                'started_at' => $notYetExpiredStartedAt,
                'exam_duration_minutes' => 5,
                'extra_time_minutes' => 10,
            ],
        ]);

        self::assertSame(5, $result['processed_count']);
        self::assertSame([11], $result['completed_attempt_ids']);
        self::assertSame([11], CBT_REST::$finalizedAttemptIds);
    }

    #[RunInSeparateProcess]
    public function test_results_scope_only_schedules_background_auto_finalize_tick(): void
    {
        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $finalizedAttemptIds = [];

    public static function finalize_attempt_completion(int $attempt_id, ?string $finished_at = null, array $options = [])
    {
        self::$finalizedAttemptIds[] = $attempt_id;

        return [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
        ];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-service.php';
        if (!defined('WP_CRON_LOCK_TIMEOUT')) {
            define('WP_CRON_LOCK_TIMEOUT', 60);
        }
        if (!function_exists('wp_next_scheduled') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-includes/cron.php';
        }

        CBT_REST::$finalizedAttemptIds = [];
        $GLOBALS['wpdb'] = new AdminResultsExpiredAutoFinalizeScheduleFakeWpdb();

        add_filter('pre_http_request', static function ($preempt, array $args, string $url) {
            if (strpos($url, 'wp-cron.php') === false) {
                return $preempt;
            }

            return [
                'headers' => [],
                'body' => '',
                'response' => [
                    'code' => 200,
                    'message' => 'OK',
                ],
                'cookies' => [],
                'filename' => null,
            ];
        }, 10, 3);

        $method = new ReflectionMethod(CBT_Admin_Results_Service::class, 'maybe_schedule_expired_attempt_auto_finalize_for_results_scope');
        $method->setAccessible(true);
        $result = $method->invoke(null, true, 1);

        self::assertTrue($result['has_pending']);
        self::assertTrue($result['scheduled']);
        self::assertSame([], CBT_REST::$finalizedAttemptIds);
    }

    #[RunInSeparateProcess]
    public function test_adaptive_policy_limits_critical_batch_size(): void
    {
        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $finalizedAttemptIds = [];

    public static function finalize_attempt_completion(int $attempt_id, ?string $finished_at = null, array $options = [])
    {
        self::$finalizedAttemptIds[] = $attempt_id;

        return [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
        ];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-expired-attempt-finalize-service.php';

        CBT_REST::$finalizedAttemptIds = [];
        $GLOBALS['wpdb'] = new AdminResultsExpiredAutoFinalizeBatchFakeWpdb();

        $policy = [
            'level' => 'critical',
            'batch_size' => 2,
            'time_budget_seconds' => 1,
            'reschedule_delay_seconds' => 30,
        ];
        $result = CBT_Expired_Attempt_Finalize_Service::process_batch(0, $policy);

        self::assertSame(2, $result['processed_count']);
        self::assertSame(2, $result['completed_count']);
        self::assertTrue($result['has_remaining']);
        self::assertSame([201, 202], CBT_REST::$finalizedAttemptIds);
    }

    #[RunInSeparateProcess]
    public function test_auto_finalize_skips_attempt_when_finish_lock_is_held(): void
    {
        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $finalizedAttemptIds = [];

    public static function finalize_attempt_completion(int $attempt_id, ?string $finished_at = null, array $options = [])
    {
        self::$finalizedAttemptIds[] = $attempt_id;

        return [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
        ];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-expired-attempt-finalize-service.php';

        CBT_REST::$finalizedAttemptIds = [];
        $GLOBALS['wpdb'] = new AdminResultsExpiredAutoFinalizeReloadFakeWpdb([
            301 => [
                'id' => 301,
                'exam_id' => 9,
                'student_id' => 91,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'extra_time_minutes' => 0,
                'exam_duration_minutes' => 30,
            ],
        ]);
        CBT_Cache::acquire_lock('finish_attempt:301', 45, ['test' => 'held_by_student']);

        $result = CBT_Expired_Attempt_Finalize_Service::maybe_auto_finalize_attempt_rows([
            [
                'id' => 301,
                'exam_id' => 9,
                'student_id' => 91,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'extra_time_minutes' => 0,
                'exam_duration_minutes' => 30,
            ],
        ]);

        self::assertSame(1, $result['processed_count']);
        self::assertSame([], $result['completed_attempt_ids']);
        self::assertSame([], CBT_REST::$finalizedAttemptIds);
    }

    #[RunInSeparateProcess]
    public function test_auto_finalize_skips_when_reloaded_attempt_is_already_closed(): void
    {
        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $finalizedAttemptIds = [];

    public static function finalize_attempt_completion(int $attempt_id, ?string $finished_at = null, array $options = [])
    {
        self::$finalizedAttemptIds[] = $attempt_id;

        return [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
        ];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-expired-attempt-finalize-service.php';

        CBT_REST::$finalizedAttemptIds = [];
        $GLOBALS['wpdb'] = new AdminResultsExpiredAutoFinalizeReloadFakeWpdb([
            401 => [
                'id' => 401,
                'exam_id' => 10,
                'student_id' => 92,
                'status' => 'completed',
                'started_at' => '2000-01-01 00:00:00',
                'extra_time_minutes' => 0,
                'exam_duration_minutes' => 30,
            ],
            402 => [
                'id' => 402,
                'exam_id' => 10,
                'student_id' => 93,
                'status' => 'cancelled',
                'started_at' => '2000-01-01 00:00:00',
                'extra_time_minutes' => 0,
                'exam_duration_minutes' => 30,
            ],
        ]);

        $result = CBT_Expired_Attempt_Finalize_Service::maybe_auto_finalize_attempt_rows([
            [
                'id' => 401,
                'exam_id' => 10,
                'student_id' => 92,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'extra_time_minutes' => 0,
                'exam_duration_minutes' => 30,
            ],
            [
                'id' => 402,
                'exam_id' => 10,
                'student_id' => 93,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'extra_time_minutes' => 0,
                'exam_duration_minutes' => 30,
            ],
        ]);

        self::assertSame(2, $result['processed_count']);
        self::assertSame([], $result['completed_attempt_ids']);
        self::assertSame([], CBT_REST::$finalizedAttemptIds);
    }

    #[RunInSeparateProcess]
    public function test_auto_finalize_releases_finish_lock_after_success(): void
    {
        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $finalizedAttemptIds = [];

    public static function finalize_attempt_completion(int $attempt_id, ?string $finished_at = null, array $options = [])
    {
        self::$finalizedAttemptIds[] = $attempt_id;

        return [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
        ];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-expired-attempt-finalize-service.php';

        CBT_REST::$finalizedAttemptIds = [];
        $GLOBALS['wpdb'] = new AdminResultsExpiredAutoFinalizeReloadFakeWpdb([
            501 => [
                'id' => 501,
                'exam_id' => 11,
                'student_id' => 94,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'extra_time_minutes' => 0,
                'exam_duration_minutes' => 30,
            ],
        ]);

        $result = CBT_Expired_Attempt_Finalize_Service::maybe_auto_finalize_attempt_rows([
            [
                'id' => 501,
                'exam_id' => 11,
                'student_id' => 94,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'extra_time_minutes' => 0,
                'exam_duration_minutes' => 30,
            ],
        ]);
        $canAcquireAgain = CBT_Cache::acquire_lock('finish_attempt:501', 45, ['test' => 'after_finalize']);

        self::assertSame([501], $result['completed_attempt_ids']);
        self::assertSame([501], CBT_REST::$finalizedAttemptIds);
        self::assertTrue($canAcquireAgain);
    }

    public function test_registers_proactive_cron_schedule(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-expired-attempt-finalize-service.php';

        $schedules = CBT_Expired_Attempt_Finalize_Service::register_cron_schedule([]);

        self::assertArrayHasKey('cbt_expired_attempt_finalize_every_minute', $schedules);
        self::assertSame(MINUTE_IN_SECONDS, $schedules['cbt_expired_attempt_finalize_every_minute']['interval']);
    }

    public function test_current_worker_policy_follows_adaptive_load_level(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-adaptive-load-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-expired-attempt-finalize-service.php';

        update_option('cbt_adaptive_load_state', [
            'effective_level' => 'busy',
            'candidate_level' => 'busy',
            'source' => 'auto',
            'reasons' => ['Attempt aktif tinggi.'],
            'last_evaluated_at' => current_time('mysql'),
        ]);

        $policy = CBT_Expired_Attempt_Finalize_Service::get_current_worker_policy();

        self::assertSame('busy', $policy['level']);
        self::assertSame(5, $policy['batch_size']);
        self::assertSame(2, $policy['time_budget_seconds']);
        self::assertSame(15, $policy['reschedule_delay_seconds']);
        self::assertSame(5000, $policy['finalize_poll_after_ms']);
    }

    #[RunInSeparateProcess]
    public function test_adaptive_admin_finalize_skips_locked_attempt_without_failure(): void
    {
        if (!class_exists('CBT_REST')) {
            eval(<<<'PHP'
class CBT_REST
{
    public static array $finalizedAttemptIds = [];

    public static function finalize_attempt_completion(int $attempt_id, ?string $finished_at = null, array $options = [])
    {
        self::$finalizedAttemptIds[] = $attempt_id;

        return [
            'attempt_id' => $attempt_id,
            'status' => 'completed',
        ];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-adaptive-load-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-expired-attempt-finalize-service.php';

        if (class_exists('CBT_Runtime')) {
            $runtimeReflection = new ReflectionClass(CBT_Runtime::class);
            foreach ([
                'redis' => false,
                'redis_connection_attempted' => true,
                'last_connection_error' => 'Runtime lock disabled for cache lock test.',
            ] as $propertyName => $value) {
                if (!$runtimeReflection->hasProperty($propertyName)) {
                    continue;
                }

                $property = $runtimeReflection->getProperty($propertyName);
                $property->setAccessible(true);
                $property->setValue(null, $value);
            }
        }

        CBT_REST::$finalizedAttemptIds = [];
        $GLOBALS['wpdb'] = new AdminResultsExpiredAutoFinalizeAdaptiveFakeWpdb([
            601 => [
                'id' => 601,
                'exam_id' => 12,
                'student_id' => 95,
                'status' => 'in_progress',
                'started_at' => '2000-01-01 00:00:00',
                'extra_time_minutes' => 0,
                'exam_duration_minutes' => 30,
            ],
        ]);
        CBT_Cache::acquire_lock('finish_attempt:601', 45, ['test' => 'held_by_finish_exam']);

        $result = CBT_Adaptive_Load_Service::finalize_expired_in_progress_attempts(10);

        self::assertSame(1, $result['scanned_count']);
        self::assertSame(0, $result['completed_count']);
        self::assertSame(0, $result['failed_count']);
        self::assertSame([], CBT_REST::$finalizedAttemptIds);
        self::assertStringContainsString('a.deadline_at IS NOT NULL', $GLOBALS['wpdb']->lastDeadlineQuery);
        self::assertStringContainsString('ORDER BY a.deadline_at ASC, a.id ASC', $GLOBALS['wpdb']->lastDeadlineQuery);
    }
}

final class AdminResultsExpiredAutoFinalizeScheduleFakeWpdb
{
    public string $prefix = 'wp_';

    public function prepare(string $query, array ...$args): string
    {
        return $query;
    }

    public function get_var(string $query)
    {
        if (strpos($query, 'SELECT a.id') !== false && strpos($query, 'LIMIT 1') !== false) {
            return '77';
        }

        return null;
    }
}

class AdminResultsExpiredAutoFinalizeReloadFakeWpdb
{
    public string $prefix = 'wp_';

    private int $lastAttemptId = 0;

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, ...$args): string
    {
        $flatArgs = count($args) === 1 && is_array($args[0]) ? $args[0] : $args;
        $firstArg = reset($flatArgs);
        if (is_numeric($firstArg) && strpos($query, 'WHERE a.id = %d') !== false) {
            $this->lastAttemptId = (int) $firstArg;
        }

        return $query;
    }

    public function get_row(string $query, string $output = ARRAY_A): ?array
    {
        if (strpos($query, 'WHERE a.id = %d') === false) {
            return null;
        }

        return isset($this->rows[$this->lastAttemptId]) && is_array($this->rows[$this->lastAttemptId])
            ? $this->rows[$this->lastAttemptId]
            : null;
    }
}

final class AdminResultsExpiredAutoFinalizeBatchFakeWpdb
{
    public string $prefix = 'wp_';

    private int $lastLimit = 10;

    /**
     * @var array<int,array<string,mixed>>
     */
    private array $rows = [
        [
            'id' => 201,
            'exam_id' => 7,
            'student_id' => 81,
            'status' => 'in_progress',
            'started_at' => '2000-01-01 00:00:00',
            'extra_time_minutes' => 0,
            'exam_duration_minutes' => 30,
        ],
        [
            'id' => 202,
            'exam_id' => 7,
            'student_id' => 82,
            'status' => 'in_progress',
            'started_at' => '2000-01-01 00:00:00',
            'extra_time_minutes' => 0,
            'exam_duration_minutes' => 30,
        ],
        [
            'id' => 203,
            'exam_id' => 7,
            'student_id' => 83,
            'status' => 'in_progress',
            'started_at' => '2000-01-01 00:00:00',
            'extra_time_minutes' => 0,
            'exam_duration_minutes' => 30,
        ],
    ];

    public function prepare(string $query, ...$args): string
    {
        $flatArgs = count($args) === 1 && is_array($args[0]) ? $args[0] : $args;
        $lastArg = end($flatArgs);
        if (is_numeric($lastArg)) {
            $this->lastLimit = max(1, (int) $lastArg);
        }

        return $query;
    }

    public function get_results(string $query, string $output = ARRAY_A): array
    {
        if (strpos($query, 'SELECT a.id,') !== false) {
            return array_slice($this->rows, 0, $this->lastLimit);
        }

        return [];
    }

    public function get_row(string $query, string $output = ARRAY_A): ?array
    {
        if (strpos($query, 'WHERE a.id = %d') === false) {
            return null;
        }

        foreach ($this->rows as $row) {
            if ((int) ($row['id'] ?? 0) === $this->lastLimit) {
                return $row;
            }
        }

        return null;
    }

    public function get_var(string $query)
    {
        if (strpos($query, 'SELECT a.id') !== false && strpos($query, 'LIMIT 1') !== false) {
            return '203';
        }

        return null;
    }
}

final class AdminResultsExpiredAutoFinalizeAdaptiveFakeWpdb extends AdminResultsExpiredAutoFinalizeReloadFakeWpdb
{
    public string $lastDeadlineQuery = '';

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public function __construct(private array $adaptiveRows)
    {
        parent::__construct($adaptiveRows);
    }

    public function get_col(string $query): array
    {
        if (strpos($query, 'SELECT a.id') !== false) {
            if (strpos($query, 'a.deadline_at IS NOT NULL') !== false) {
                $this->lastDeadlineQuery = $query;
                return array_map('strval', array_keys($this->adaptiveRows));
            }
        }

        return [];
    }
}
