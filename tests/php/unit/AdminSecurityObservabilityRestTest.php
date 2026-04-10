<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

if (!class_exists('wpdb')) {
    eval(<<<'PHP'
class wpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';
}
PHP);
}

if (!function_exists('rest_url')) {
    function rest_url($path = ''): string
    {
        return 'http://example.test/wp-json/' . ltrim((string) $path, '/');
    }
}

final class AdminSecurityObservabilityRestTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_security_observability_snapshot_returns_lightweight_html_payload(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-live-counters.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-event-ingest.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-attempt-roster-index.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-page.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new AdminSecurityObservabilityRestFakeWpdb();

        $response = \CBT_REST::security_observability_snapshot(
            new \WP_REST_Request([], [], [], '/cbt/v1/security_observability_snapshot', 'GET')
        );
        $data = is_array($response)
            ? $response
            : ($response instanceof \WP_REST_Response ? $response->get_data() : []);

        self::assertSame(true, $data['ok'] ?? false);
        self::assertContains($data['mode'] ?? '', ['redis_live', 'mysql_fallback']);
        self::assertStringContainsString('Must Watch', (string) ($data['must_watch_html'] ?? ''));
        self::assertStringContainsString('Live Roster', (string) ($data['live_roster_html'] ?? ''));
        self::assertArrayHasKey('status_snapshot', $data);
        self::assertIsArray($data['status_snapshot']);
        self::assertArrayHasKey('live_label', $data['status_snapshot']);
        self::assertArrayHasKey('ingest_label', $data['status_snapshot']);
        self::assertArrayHasKey('persist_label', $data['status_snapshot']);
        self::assertArrayHasKey('worker_scheduled', $data['status_snapshot']);
        self::assertArrayHasKey('next_flush_at', $data['status_snapshot']);
    }

    #[RunInSeparateProcess]
    public function test_security_logs_page_returns_history_html_payload(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-page.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        global $wpdb;
        $wpdb = new AdminSecurityObservabilityRestFakeWpdb();

        $response = \CBT_REST::security_logs_page(
            new \WP_REST_Request([], [], [], '/cbt/v1/security_logs_page', 'GET')
        );
        $data = is_array($response)
            ? $response
            : ($response instanceof \WP_REST_Response ? $response->get_data() : []);

        self::assertSame(true, $data['ok'] ?? false);
        self::assertStringContainsString('Belum ada histori security log', (string) ($data['history_html'] ?? ''));
        self::assertSame(0, $data['total'] ?? -1);
    }

    #[RunInSeparateProcess]
    public function test_security_ingest_admin_action_rejects_invalid_action(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $response = \CBT_REST::security_ingest_admin_action(
            new \WP_REST_Request([], ['action' => 'bad_action'], [], '/cbt/v1/security_ingest_admin_action', 'POST')
        );

        self::assertInstanceOf(\WP_Error::class, $response);
        self::assertSame('invalid_action', $response->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_security_ingest_admin_action_returns_skipped_when_redis_is_unavailable(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-live-counters.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-event-ingest.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $response = \CBT_REST::security_ingest_admin_action(
            new \WP_REST_Request([], ['action' => 'micro_drain'], [], '/cbt/v1/security_ingest_admin_action', 'POST')
        );
        $data = is_array($response)
            ? $response
            : ($response instanceof \WP_REST_Response ? $response->get_data() : []);

        self::assertSame(true, $data['ok'] ?? false);
        self::assertSame('micro_drain', $data['action'] ?? '');
        self::assertSame(1, $data['action_result']['skipped'] ?? 0);
        self::assertSame('redis_unavailable', $data['action_result']['reason'] ?? '');
        self::assertArrayHasKey('status_snapshot', $data);
        self::assertIsArray($data['status_snapshot']);
    }

    #[RunInSeparateProcess]
    public function test_security_ingest_admin_action_accepts_flush_now(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-live-counters.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-event-ingest.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';

        $response = \CBT_REST::security_ingest_admin_action(
            new \WP_REST_Request([], ['action' => 'flush_now'], [], '/cbt/v1/security_ingest_admin_action', 'POST')
        );
        $data = is_array($response)
            ? $response
            : ($response instanceof \WP_REST_Response ? $response->get_data() : []);

        self::assertSame(true, $data['ok'] ?? false);
        self::assertSame('flush_now', $data['action'] ?? '');
        self::assertIsArray($data['action_result'] ?? null);
        self::assertSame('admin_force_flush', $data['action_result']['source'] ?? '');
    }
}

final class AdminSecurityObservabilityRestFakeWpdb extends \wpdb
{
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

    /** @param array<string,mixed>|string $prepared */
    public function get_results($prepared, $output = null): array
    {
        return [];
    }

    /** @param array<string,mixed>|string $prepared */
    public function get_row($prepared, $output = null)
    {
        return null;
    }

    /** @param string $query */
    public function query($query)
    {
        return 0;
    }
}
