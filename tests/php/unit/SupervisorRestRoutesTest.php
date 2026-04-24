<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class SupervisorRestRoutesTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_supervisor_dashboard_rejects_student_role(): void
    {
        $this->bootstrapRouteStubs('siswa');
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-supervisor.php';
        $this->defineHarnessClass();

        $response = \SupervisorRestRoutesHarness::supervisor_dashboard(
            new \WP_REST_Request([], [], [], '/cbt/v1/supervisor_dashboard', 'GET')
        );

        self::assertInstanceOf(\WP_Error::class, $response);
        self::assertSame('forbidden', $response->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_supervisor_dashboard_returns_service_payload_for_teacher(): void
    {
        $this->bootstrapRouteStubs('teacher');
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-supervisor.php';
        $this->defineHarnessClass();

        $response = \SupervisorRestRoutesHarness::supervisor_dashboard(
            new \WP_REST_Request([
                'exam_id' => 8,
            ], [], [], '/cbt/v1/supervisor_dashboard', 'GET')
        );
        $data = is_array($response)
            ? $response
            : ($response instanceof \WP_REST_Response ? $response->get_data() : []);

        self::assertSame(true, $data['ok'] ?? false);
        self::assertSame(8, $GLOBALS['cbt_test_supervisor_dashboard_query']['exam_id'] ?? 0);
        self::assertSame(77, $GLOBALS['cbt_test_supervisor_dashboard_scope']['user_id'] ?? 0);
        self::assertSame('teacher', $GLOBALS['cbt_test_supervisor_dashboard_scope']['role'] ?? '');
    }

    #[RunInSeparateProcess]
    public function test_supervisor_reset_login_requires_attempt_id(): void
    {
        $this->bootstrapRouteStubs('administrator');
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-supervisor.php';
        $this->defineHarnessClass();

        $response = \SupervisorRestRoutesHarness::supervisor_reset_login(
            new \WP_REST_Request([], [], [], '/cbt/v1/supervisor_reset_login', 'POST')
        );

        self::assertInstanceOf(\WP_Error::class, $response);
        self::assertSame('invalid_payload', $response->get_error_code());
    }

    private function bootstrapRouteStubs(string $role): void
    {
        if (!class_exists('CBT_Auth', false)) {
            eval(sprintf(<<<'PHP'
class CBT_Auth
{
    public static function current_user_id(\WP_REST_Request $request): int
    {
        return 77;
    }

    public static function current_user_role(\WP_REST_Request $request): string
    {
        return %s;
    }

    public static function is_supervisor_role(string $role): bool
    {
        return in_array(strtolower($role), ['admin', 'administrator', 'guru', 'teacher'], true);
    }
}
PHP, var_export($role, true)));
        }

        if (!class_exists('CBT_Supervisor_Dashboard_Service', false)) {
            eval(<<<'PHP'
class CBT_Supervisor_Dashboard_Service
{
    public static function build_dashboard_payload(int $user_id, string $role, array $query = []): array
    {
        $GLOBALS['cbt_test_supervisor_dashboard_scope'] = [
            'user_id' => $user_id,
            'role' => $role,
        ];
        $GLOBALS['cbt_test_supervisor_dashboard_query'] = $query;

        return [
            'ok' => true,
            'scope' => [
                'user_id' => $user_id,
                'role' => $role,
            ],
        ];
    }

    public static function reset_login_for_attempt(int $attempt_id, int $user_id, string $role): array
    {
        return [
            'attempt_id' => $attempt_id,
            'user_id' => $user_id,
            'role' => $role,
            'message' => 'Login siswa berhasil di-reset.',
        ];
    }
}
PHP);
        }

    }

    private function defineHarnessClass(): void
    {
        if (class_exists('SupervisorRestRoutesHarness', false)) {
            return;
        }

        eval(<<<'PHP'
class SupervisorRestRoutesHarness
{
    use CBT_REST_Supervisor_Routes;

    public static function get_request_payload_value(\WP_REST_Request $request, string $key)
    {
        return $request->get_param($key);
    }
}
PHP);
    }
}
