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
