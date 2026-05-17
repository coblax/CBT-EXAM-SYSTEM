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
    public function test_login_succeeds_with_email_identifier(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(21, 'siswa_email', 'password123', 'student');

        $response = CBT_REST::login($this->makeLoginRequest('siswa_email@test.com', 'password123'));

        self::assertIsArray($response);
        self::assertSame(21, (int) ($response['user_id'] ?? 0));
        self::assertSame('siswa_email@test.com', (string) ($response['email'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_login_succeeds_with_username_param_alias(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(26, 'siswa_alias', 'password123', 'student');

        $response = CBT_REST::login(new WP_REST_Request(
            ['username' => 'siswa_alias', 'password' => 'password123'],
            [],
            [],
            '/cbt/v1/login',
            'POST'
        ));

        self::assertIsArray($response);
        self::assertSame(26, (int) ($response['user_id'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_login_succeeds_with_nisn_identifier(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(22, 'siswa_nisn', 'password123', 'student');
        update_user_meta(22, 'nisn', '0099887766');

        $response = CBT_REST::login($this->makeLoginRequest('0099887766', 'password123'));

        self::assertIsArray($response);
        self::assertSame(22, (int) ($response['user_id'] ?? 0));
        self::assertSame('siswa_nisn', (string) ($response['username'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_login_succeeds_with_student_email_local_part_fallback(): void
    {
        $this->bootstrapLoginScaffold();
        cbt_test_register_user([
            'ID' => 23,
            'user_login' => 'generated_login_23',
            'user_email' => 'nisn-local@student.sch.id',
            'user_pass' => 'password123',
            'display_name' => 'Fallback Email Student',
            'roles' => ['student'],
        ]);
        update_user_meta(23, 'kode_kelas', 'XII-IPA-1');
        update_user_meta(23, 'kode_ruang', 'R01');

        $response = CBT_REST::login($this->makeLoginRequest('nisn-local', 'password123'));

        self::assertIsArray($response);
        self::assertSame(23, (int) ($response['user_id'] ?? 0));
        self::assertSame('generated_login_23', (string) ($response['username'] ?? ''));
    }

    #[RunInSeparateProcess]
    public function test_login_rejects_fresh_active_session_takeover(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(24, 'siswa_active', 'password123', 'student');
        $now = time();
        $this->writeLegacyActiveSession(24, 'fresh-session-key', $now, $now);

        $response = CBT_REST::login($this->makeLoginRequest('siswa_active', 'password123'));

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('session_already_active', $response->get_error_code());
        self::assertSame(409, (int) ($response->get_error_data()['status'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_login_allows_stale_active_session_takeover(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(25, 'siswa_stale', 'password123', 'student');
        $old = time() - 60;
        $this->writeLegacyActiveSession(25, 'stale-session-key', $old, $old);

        $response = CBT_REST::login($this->makeLoginRequest('siswa_stale', 'password123'));

        self::assertIsArray($response);
        self::assertSame(25, (int) ($response['user_id'] ?? 0));
        self::assertNotSame('stale-session-key', (string) get_user_meta(25, 'cbt_active_login_session', true));
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
    public function test_login_rate_limit_is_scoped_by_identifier(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(29, 'siswa_scope_a', 'password123', 'student');
        $this->registerStudent(30, 'siswa_scope_b', 'password123', 'student');

        $_SERVER['REMOTE_ADDR'] = '192.168.1.102';

        for ($i = 0; $i < 5; $i++) {
            CBT_REST::login($this->makeLoginRequest('siswa_scope_a', 'wrong'));
        }

        $blocked = CBT_REST::login($this->makeLoginRequest('siswa_scope_a', 'wrong'));
        $otherIdentifier = CBT_REST::login($this->makeLoginRequest('siswa_scope_b', 'wrong'));

        self::assertInstanceOf(WP_Error::class, $blocked);
        self::assertSame('too_many_requests', $blocked->get_error_code());
        self::assertInstanceOf(WP_Error::class, $otherIdentifier);
        self::assertSame('invalid_credentials', $otherIdentifier->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_successful_login_clears_rate_limit_counters(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(27, 'siswa_counter', 'password123', 'student');
        $_SERVER['REMOTE_ADDR'] = '192.168.1.101';

        for ($i = 0; $i < 4; $i++) {
            $response = CBT_REST::login($this->makeLoginRequest('siswa_counter', 'wrong'));
            self::assertInstanceOf(WP_Error::class, $response);
            self::assertSame('invalid_credentials', $response->get_error_code());
        }

        $limitKey = $this->loginRateLimitKey('192.168.1.101', 'siswa_counter');
        self::assertSame(4, (int) get_transient($limitKey));

        $success = CBT_REST::login($this->makeLoginRequest('siswa_counter', 'password123'));

        self::assertIsArray($success);
        self::assertFalse(get_transient($limitKey));
        self::assertFalse(get_transient($limitKey . '_block'));
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
    public function test_login_flow_user_agent_guard_blocks_and_clears_created_session(): void
    {
        $this->bootstrapLoginScaffold();
        $this->registerStudent(28, 'siswa_guarded', 'password123', 'student');
        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);

        $response = CBT_REST::login(new WP_REST_Request(
            ['identifier' => 'siswa_guarded', 'password' => 'password123'],
            [],
            ['user-agent' => 'Mozilla/5.0 Chrome/120'],
            '/cbt/v1/login',
            'POST'
        ));

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('student_user_agent_forbidden', $response->get_error_code());
        self::assertSame('', (string) get_user_meta(28, 'cbt_active_login_session', true));
        self::assertSame('', (string) get_user_meta(28, 'cbt_active_login_session_touched_at', true));
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
    public function test_user_agent_guard_normalizes_multiline_patterns_and_matches_case_insensitively(): void
    {
        $this->bootstrapLoginScaffold();

        $patterns = CBT_Security_User_Agent_Guard::normalize_allowed_user_agents(" CustomLock \n customlock \n\n");

        self::assertSame(['CBXExamLockAndroid', 'CustomLock'], $patterns);
        self::assertTrue(CBT_Security_User_Agent_Guard::is_user_agent_allowed('Mozilla/5.0 customlock/1.0', $patterns));
        self::assertFalse(CBT_Security_User_Agent_Guard::is_user_agent_allowed('', $patterns));
    }

    #[RunInSeparateProcess]
    public function test_user_agent_guard_reads_user_agent_header_alias(): void
    {
        $this->bootstrapLoginScaffold();

        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CustomLock'],
        ]);

        $request = new WP_REST_Request([], [], ['user_agent' => 'CustomLock/3.0'], '', 'POST');

        self::assertSame('CustomLock/3.0', CBT_Security_User_Agent_Guard::request_user_agent($request));
        self::assertTrue(CBT_Security_User_Agent_Guard::guard_student_request($request, 'student'));
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

        $this->useFakeLoginSnapshotRedis();
    }

    private function makeLoginRequest(string $identifier, string $password): WP_REST_Request
    {
        return new WP_REST_Request(
            ['identifier' => $identifier, 'password' => $password],
            [],
            [],
            '/cbt/v1/login',
            'POST'
        );
    }

    private function writeLegacyActiveSession(int $userId, string $sessionKey, int $touchedAt, int $issuedAt): void
    {
        update_user_meta($userId, 'cbt_active_login_session', $sessionKey);
        update_user_meta($userId, 'cbt_active_login_session_touched_at', $touchedAt);
        update_user_meta($userId, 'cbt_active_login_session_issued_at', $issuedAt);
    }

    private function loginRateLimitKey(string $ip, string $identifier): string
    {
        return 'cbt_rl_' . md5($ip . '_' . strtolower($identifier));
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

    private function useFakeLoginSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Login_Auth_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }
}
