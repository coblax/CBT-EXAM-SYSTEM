<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

if (!class_exists('wpdb')) {
    class wpdb
    {
        public string $prefix = 'wp_';
        public string $users = 'wp_users';
        public string $usermeta = 'wp_usermeta';
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = ''): string
    {
        return 'http://example.test/wp-json/' . ltrim((string) $path, '/');
    }
}

final class AdminSecurityServiceLiveRosterTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_build_page_context_exposes_live_roster_groups_when_redis_is_available(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-attempt-roster-index.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';

        global $wpdb;
        $wpdb = new AdminSecurityServiceLiveRosterFakeWpdb();

        $this->useFakeRosterRedis();
        CBT_Live_Attempt_Roster_Index::sync_attempt(
            [
                'id' => 15,
                'exam_id' => 90,
                'student_id' => 41,
                'status' => 'in_progress',
            ],
            [
                'teacher_id' => 0,
                'exam_title' => 'Exam Context',
                'student_name' => 'Roster Context',
                'student_login' => 'roster_context',
                'student_kode_kelas' => 'KELAS_CTX',
                'student_kode_ruang' => 'RUANG_CTX',
                'last_seen_at' => '2026-04-03 06:00:00',
            ]
        );

        $context = CBT_Admin_Security_Service::build_page_context([]);

        self::assertArrayHasKey('security_live_roster_groups', $context);
        self::assertCount(1, $context['security_live_roster_groups']);
        self::assertSame('Exam Context', $context['security_live_roster_groups'][0]['exam_title']);
    }

    #[RunInSeparateProcess]
    public function test_build_page_context_keeps_live_roster_empty_when_redis_is_unavailable(): void
    {
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-setup-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-log.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-live-attempt-roster-index.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-security-service.php';

        global $wpdb;
        $wpdb = new AdminSecurityServiceLiveRosterFakeWpdb();

        $this->setRosterRedisUnavailable();

        $context = CBT_Admin_Security_Service::build_page_context([]);

        self::assertArrayHasKey('security_live_roster_groups', $context);
        self::assertSame([], $context['security_live_roster_groups']);
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
        $errorProperty->setValue(null, 'disabled in security service roster test');
    }
}

final class AdminSecurityServiceLiveRosterFakeWpdb extends wpdb
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
