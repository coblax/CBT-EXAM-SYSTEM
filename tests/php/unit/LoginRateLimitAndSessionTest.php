<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class LoginRateLimitAndSessionTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_login_succeeds_with_valid_credentials(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(20, 'siswa_fresh', 'password123', 'student');

        $request = new WP_REST_Request(
            ['identifier' => 'siswa_fresh', 'password' => 'password123'],
            [],
            [],
            '/cbt/v1/login',
            'POST'
        );
        $response = CBT_REST::login($request);

        self::assertIsArray($response);
        self::assertArrayHasKey('token', $response);
        self::assertSame(20, (int) ($response['user_id'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_login_fails_with_wrong_password(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(7, 'siswa01', 'password123', 'student');

        $request = new WP_REST_Request(
            ['identifier' => 'siswa01', 'password' => 'wrong'],
            [],
            [],
            '/cbt/v1/login',
            'POST'
        );
        $response = CBT_REST::login($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('invalid_credentials', $response->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_login_rate_limited_after_5_failures(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(7, 'siswa01', 'password123', 'student');

        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        for ($i = 0; $i < 5; $i++) {
            $request = new WP_REST_Request(
                ['identifier' => 'siswa01', 'password' => 'wrong'],
                [],
                [],
                '/cbt/v1/login',
                'POST'
            );
            CBT_REST::login($request);
        }

        $request = new WP_REST_Request(
            ['identifier' => 'siswa01', 'password' => 'wrong'],
            [],
            [],
            '/cbt/v1/login',
            'POST'
        );
        $response = CBT_REST::login($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('too_many_requests', $response->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_login_rejects_empty_identifier(): void
    {
        $this->bootstrapLoginScaffold();

        $request = new WP_REST_Request(
            ['identifier' => '', 'password' => 'test'],
            [],
            [],
            '/cbt/v1/login',
            'POST'
        );
        $response = CBT_REST::login($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('invalid_payload', $response->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_login_rejects_too_long_identifier(): void
    {
        $this->bootstrapLoginScaffold();

        $request = new WP_REST_Request(
            ['identifier' => str_repeat('a', 200), 'password' => 'test'],
            [],
            [],
            '/cbt/v1/login',
            'POST'
        );
        $response = CBT_REST::login($request);

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('invalid_payload', $response->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_login_user_agent_guard_blocks_student_from_browser(): void
    {
        $this->bootstrapLoginScaffold();

        // Test the guard directly — this is what the login flow calls
        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);

        $request = new WP_REST_Request([], [], ['user-agent' => 'Mozilla/5.0 Chrome/120'], '', 'POST');
        $result = CBT_Security_User_Agent_Guard::guard_student_request($request, 'siswa');

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('student_user_agent_forbidden', $result->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_login_user_agent_guard_allows_registered_agent(): void
    {
        $this->bootstrapLoginScaffold();

        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);

        $request = new WP_REST_Request([], [], ['user-agent' => 'CBXExamLockAndroid/2.1'], '', 'POST');
        $result = CBT_Security_User_Agent_Guard::guard_student_request($request, 'siswa');

        self::assertTrue($result);
    }

    #[RunInSeparateProcess]
    public function test_login_user_agent_guard_allows_teacher_regardless(): void
    {
        $this->bootstrapLoginScaffold();

        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);

        $request = new WP_REST_Request([], [], ['user-agent' => 'Mozilla/5.0 Chrome/120'], '', 'POST');
        $result = CBT_Security_User_Agent_Guard::guard_student_request($request, 'guru');

        self::assertTrue($result);
    }

    #[RunInSeparateProcess]
    public function test_logout_clears_session(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(9, 'siswa03', 'password789', 'student');

        $loginRequest = new WP_REST_Request(
            ['identifier' => 'siswa03', 'password' => 'password789'],
            [],
            [],
            '/cbt/v1/login',
            'POST'
        );
        $loginResponse = CBT_REST::login($loginRequest);
        self::assertIsArray($loginResponse);

        $token = (string) ($loginResponse['token'] ?? '');
        $logoutRequest = new WP_REST_Request(
            [],
            [],
            ['authorization' => 'Bearer ' . $token],
            '/cbt/v1/logout',
            'POST'
        );
        $logoutResponse = CBT_REST::logout($logoutRequest);

        self::assertIsArray($logoutResponse);
        self::assertTrue((bool) ($logoutResponse['ok'] ?? false));
    }

    private function bootstrapLoginScaffold(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-user-agent-guard.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-cohort-index-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-auth-snapshot-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-snapshot-metrics-service.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-auth.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-login.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-shared.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest.php';
    }

    private function registerStudent(int $id, string $login, string $password, string $role): void
    {
        cbt_test_register_user([
            'ID' => $id,
            'user_login' => $login,
            'user_email' => $login . '@test.com',
            'user_pass' => $password,
            'display_name' => ucfirst($login),
            'roles' => [$role],
        ]);
        update_user_meta($id, 'kode_kelas', 'XII-IPA-1');
        update_user_meta($id, 'kode_ruang', 'R01');
    }
}
