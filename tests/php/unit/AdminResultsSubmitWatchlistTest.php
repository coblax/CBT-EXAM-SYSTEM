<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminResultsSubmitWatchlistTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_build_submit_flow_monitoring_context_and_results_render_show_watchlist_details(): void
    {
        $this->bootstrapResultsService();
        $this->useFakeSubmitMetricsRedis();

        $GLOBALS['cbt_test_current_user_caps']['cbt_view_results'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['cbt_test_current_user_id'] = 1;
        $GLOBALS['cbt_test_current_time_timestamp'] = strtotime('2026-04-12 08:00:00');
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-04-12 08:00:00';

        CBT_Submit_Flow_Metrics_Service::record_event(
            501,
            22,
            'finish_acknowledged',
            'ack-501',
            1712908500000,
            1800,
            [],
            ['user_id' => 91, 'ack_source' => 'finish_exam']
        );
        CBT_Submit_Flow_Metrics_Service::record_event(
            501,
            22,
            'finish_result_recovery_failed',
            'failed-501',
            1712908565000,
            null,
            [],
            ['user_id' => 91, 'ack_source' => 'finish_exam', 'error_message' => 'Result fetch gagal dari browser test.']
        );

        $GLOBALS['wpdb'] = new AdminResultsSubmitWatchlistFakeWpdb([
            [
                'id' => 501,
                'exam_id' => 22,
                'student_id' => 91,
                'status' => 'completed',
                'started_at' => '2026-04-12 07:00:00',
                'finished_at' => '2026-04-12 07:45:00',
                'exam_title' => 'Matematika XII',
                'student_name' => 'Siswa Watchlist',
                'student_username' => '223611',
                'student_kelas' => 'XII IPA 1',
                'student_nisn' => '223611',
            ],
        ]);

        $contextMethod = new ReflectionMethod(CBT_Admin_Results_Service::class, 'build_submit_flow_monitoring_context');
        $contextMethod->setAccessible(true);
        $monitoring = $contextMethod->invoke(null, [
            'is_admin_scope' => true,
            'current_user_id' => 1,
            'selected_exam_id' => 0,
            'selected_status' => '',
            'selected_kelas' => '',
            'student_keyword' => '',
            'show_exam_column' => true,
        ]);

        self::assertIsArray($monitoring);
        self::assertTrue($monitoring['submit_health']['available']);
        self::assertSame(1, $monitoring['submit_health']['finish_ack_total']);
        self::assertSame(1, $monitoring['submit_health']['recovery_failed_total']);
        self::assertSame(1, $monitoring['submit_watchlist']['total']);
        self::assertSame('Recovery Failed', $monitoring['submit_watchlist']['items'][0]['state_label']);
        self::assertTrue($monitoring['submit_watchlist']['items'][0]['server_completed']);

        $pageContext = CBT_Admin_Results_Service::build_page_context([]);
        extract($pageContext, EXTR_SKIP);

        ob_start();
        require dirname(__DIR__, 3) . '/admin/views/results/page.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('Submit Health', $html);
        self::assertStringContainsString('Submit Watchlist', $html);
        self::assertStringContainsString('Recovery Failed', $html);
        self::assertStringContainsString('Server Completed', $html);
        self::assertStringContainsString('Lompat ke Attempt', $html);
        self::assertStringContainsString('Result fetch gagal dari browser test.', $html);
        self::assertStringContainsString('cbt-results-attempt-row-501', $html);
    }

    #[RunInSeparateProcess]
    public function test_build_submit_flow_monitoring_context_handles_metrics_unavailable_safely(): void
    {
        $this->bootstrapResultsService();
        $this->setSubmitMetricsRedisUnavailable();

        $contextMethod = new ReflectionMethod(CBT_Admin_Results_Service::class, 'build_submit_flow_monitoring_context');
        $contextMethod->setAccessible(true);
        $monitoring = $contextMethod->invoke(null, [
            'is_admin_scope' => true,
            'current_user_id' => 1,
            'selected_exam_id' => 0,
            'selected_status' => '',
            'selected_kelas' => '',
            'student_keyword' => '',
            'show_exam_column' => true,
        ]);

        self::assertFalse($monitoring['submit_health']['available']);
        self::assertSame('N/A', $monitoring['submit_health']['ack_to_result_ready_p95_label']);
        self::assertFalse($monitoring['submit_watchlist']['available']);
        self::assertNotSame('', (string) ($monitoring['submit_watchlist']['note'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_build_submit_flow_monitoring_context_returns_empty_watchlist_when_no_events(): void
    {
        $this->bootstrapResultsService();
        $this->useFakeSubmitMetricsRedis();

        $GLOBALS['wpdb'] = new AdminResultsSubmitWatchlistFakeWpdb([]);

        $contextMethod = new ReflectionMethod(CBT_Admin_Results_Service::class, 'build_submit_flow_monitoring_context');
        $contextMethod->setAccessible(true);
        $monitoring = $contextMethod->invoke(null, [
            'is_admin_scope' => true,
            'current_user_id' => 1,
            'selected_exam_id' => 0,
            'selected_status' => '',
            'selected_kelas' => '',
            'student_keyword' => '',
            'show_exam_column' => true,
        ]);

        self::assertTrue($monitoring['submit_health']['available']);
        self::assertSame(0, $monitoring['submit_health']['finish_ack_total']);
        self::assertSame(0, $monitoring['submit_watchlist']['total']);
        self::assertSame([], $monitoring['submit_watchlist']['items']);
    }

    #[RunInSeparateProcess]
    public function test_build_page_context_includes_submit_monitoring_keys(): void
    {
        $this->bootstrapResultsService();
        $this->useFakeSubmitMetricsRedis();

        $GLOBALS['cbt_test_current_user_caps']['cbt_view_results'] = true;
        $GLOBALS['cbt_test_current_user_caps']['manage_options'] = true;
        $GLOBALS['wpdb'] = new AdminResultsSubmitWatchlistFakeWpdb([]);

        $pageContext = CBT_Admin_Results_Service::build_page_context([]);

        self::assertArrayHasKey('submit_health', $pageContext);
        self::assertArrayHasKey('submit_watchlist', $pageContext);
        self::assertIsArray($pageContext['submit_health']);
        self::assertIsArray($pageContext['submit_watchlist']);
    }

    private function bootstrapResultsService(): void
    {
        if (!function_exists('selected')) {
            eval(<<<'PHP'
function selected($selected, $current = true, $display = true): string
{
    $result = ((string) $selected === (string) $current) ? 'selected="selected"' : '';
    if ($display) {
        echo $result;
    }
    return $result;
}
PHP);
        }

        if (!function_exists('disabled')) {
            eval(<<<'PHP'
function disabled($disabled, $current = true, $display = true): string
{
    $result = ((string) $disabled === (string) $current) ? 'disabled="disabled"' : '';
    if ($display) {
        echo $result;
    }
    return $result;
}
PHP);
        }

        if (!function_exists('checked')) {
            eval(<<<'PHP'
function checked($checked, $current = true, $display = true): string
{
    $result = ((string) $checked === (string) $current) ? 'checked="checked"' : '';
    if ($display) {
        echo $result;
    }
    return $result;
}
PHP);
        }

        if (!class_exists('CBT_Admin_Users_Service')) {
            eval(<<<'PHP'
class CBT_Admin_Users_Service
{
    public static function get_distinct_user_meta_values(string $meta_key): array
    {
        return [];
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-submit-flow-metrics-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-ui-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-results-service.php';
    }

    private function useFakeSubmitMetricsRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Submit_Flow_Metrics_Service::class);

        $redisProperty = $reflection->getProperty('metrics_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('metrics_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('metrics_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function setSubmitMetricsRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Submit_Flow_Metrics_Service::class);

        $redisProperty = $reflection->getProperty('metrics_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('metrics_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('metrics_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'Redis unavailable in submit watchlist test');
    }
}

final class AdminResultsSubmitWatchlistFakeWpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';

    /** @var array<int,array<string,mixed>> */
    private array $watchlistRows;

    /**
     * @param array<int,array<string,mixed>> $watchlistRows
     */
    public function __construct(array $watchlistRows)
    {
        $this->watchlistRows = [];
        foreach ($watchlistRows as $row) {
            $attemptId = (int) ($row['id'] ?? 0);
            if ($attemptId > 0) {
                $this->watchlistRows[$attemptId] = $row;
            }
        }
    }

    public function esc_like(string $text): string
    {
        return addslashes($text);
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
    public function get_results($prepared, $output = null): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? array_values((array) ($prepared['args'] ?? [])) : [];

        if (str_contains($query, 'SELECT id, title FROM')) {
            return [
                ['id' => 22, 'title' => 'Matematika XII'],
            ];
        }

        if (str_contains($query, 'answer_count')) {
            return [];
        }

        if (str_contains($query, 'SELECT ans.id AS answer_id')) {
            return [];
        }

        if (str_contains($query, 'student_username') && str_contains($query, 'a.finished_at') && !str_contains($query, 'answer_count')) {
            $attemptIds = array_map('intval', array_slice($args, 0, count($this->watchlistRows)));
            $rows = [];
            foreach ($attemptIds as $attemptId) {
                if (isset($this->watchlistRows[$attemptId])) {
                    $rows[] = $this->watchlistRows[$attemptId];
                }
            }
            return $rows;
        }

        return [];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_var($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        if (str_contains($query, 'SELECT a.id') && str_contains($query, "a.status = 'in_progress'")) {
            return 0;
        }

        return 0;
    }
}
