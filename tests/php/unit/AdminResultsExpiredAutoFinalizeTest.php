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
        ]);

        self::assertSame(4, $result['processed_count']);
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
