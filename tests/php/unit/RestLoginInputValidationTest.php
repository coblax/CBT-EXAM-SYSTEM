<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class RestLoginInputValidationTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_login_rejects_object_identifier_without_calling_auth(): void
    {
        $this->bootstrapLoginRouteHarness();

        $response = RestLoginRoutesHarness::login(new WP_REST_Request([
            'identifier' => (object) ['value' => 'siswa01'],
            'password' => 'secret',
        ]));

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('invalid_payload', $response->get_error_code());
        self::assertSame(['status' => 400], $response->get_error_data());
        self::assertSame(0, (int) ($GLOBALS['cbt_test_login_auth_calls'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_login_rejects_array_password_without_calling_auth(): void
    {
        $this->bootstrapLoginRouteHarness();

        $response = RestLoginRoutesHarness::login(new WP_REST_Request([
            'identifier' => 'siswa01',
            'password' => ['secret'],
        ]));

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('invalid_payload', $response->get_error_code());
        self::assertSame(['status' => 400], $response->get_error_data());
        self::assertSame(0, (int) ($GLOBALS['cbt_test_login_auth_calls'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_login_rejects_overlong_identifier_before_auth(): void
    {
        $this->bootstrapLoginRouteHarness();

        $response = RestLoginRoutesHarness::login(new WP_REST_Request([
            'identifier' => str_repeat('a', 192),
            'password' => 'secret',
        ]));

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('invalid_payload', $response->get_error_code());
        self::assertSame(['status' => 400], $response->get_error_data());
        self::assertSame(0, (int) ($GLOBALS['cbt_test_login_auth_calls'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_sql_like_identifier_is_treated_as_plain_credential_text(): void
    {
        $this->bootstrapLoginRouteHarness();

        $identifier = "' OR 1=1 --";
        $response = RestLoginRoutesHarness::login(new WP_REST_Request([
            'identifier' => $identifier,
            'password' => 'wrong-password',
        ]));

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('invalid_credentials', $response->get_error_code());
        self::assertSame(0, (int) ($response->get_error_data()['retry_after'] ?? 0));
        self::assertSame(1, (int) ($GLOBALS['cbt_test_login_auth_calls'] ?? 0));
        self::assertSame($identifier, $GLOBALS['cbt_test_login_auth_identifier'] ?? '');
        self::assertSame('wrong-password', $GLOBALS['cbt_test_login_auth_password'] ?? '');
    }

    #[RunInSeparateProcess]
    public function test_failed_login_starts_five_second_retry_after_on_fifth_attempt(): void
    {
        $this->bootstrapLoginRouteHarness();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $response = RestLoginRoutesHarness::login(new WP_REST_Request([
                'identifier' => 'siswa01',
                'password' => 'wrong-password',
            ]));

            self::assertInstanceOf(WP_Error::class, $response);
            self::assertSame('invalid_credentials', $response->get_error_code());
            self::assertSame(0, (int) ($response->get_error_data()['retry_after'] ?? 0));
        }

        $fifth = RestLoginRoutesHarness::login(new WP_REST_Request([
            'identifier' => 'siswa01',
            'password' => 'wrong-password',
        ]));

        self::assertInstanceOf(WP_Error::class, $fifth);
        self::assertSame('invalid_credentials', $fifth->get_error_code());
        self::assertSame(5, (int) ($fifth->get_error_data()['retry_after'] ?? 0));
        self::assertSame(5, (int) ($GLOBALS['cbt_test_login_auth_calls'] ?? 0));

        $blocked = RestLoginRoutesHarness::login(new WP_REST_Request([
            'identifier' => 'siswa01',
            'password' => 'wrong-password',
        ]));

        self::assertInstanceOf(WP_Error::class, $blocked);
        self::assertSame('too_many_requests', $blocked->get_error_code());
        self::assertSame(429, (int) ($blocked->get_error_data()['status'] ?? 0));
        self::assertGreaterThanOrEqual(1, (int) ($blocked->get_error_data()['retry_after'] ?? 0));
        self::assertLessThanOrEqual(5, (int) ($blocked->get_error_data()['retry_after'] ?? 0));
        self::assertSame(5, (int) ($GLOBALS['cbt_test_login_auth_calls'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_failed_login_retry_after_increases_by_five_seconds_until_four_minutes(): void
    {
        $this->bootstrapLoginRouteHarness();
        [$limitKey, $blockKey] = $this->rateLimitKeys('siswa01');

        set_transient($limitKey, 4, 3600);
        $fifth = RestLoginRoutesHarness::login(new WP_REST_Request([
            'identifier' => 'siswa01',
            'password' => 'wrong-password',
        ]));
        self::assertInstanceOf(WP_Error::class, $fifth);
        self::assertSame(5, (int) ($fifth->get_error_data()['retry_after'] ?? 0));

        delete_transient($blockKey);
        $second = RestLoginRoutesHarness::login(new WP_REST_Request([
            'identifier' => 'siswa01',
            'password' => 'wrong-password',
        ]));
        self::assertInstanceOf(WP_Error::class, $second);
        self::assertSame(10, (int) ($second->get_error_data()['retry_after'] ?? 0));

        set_transient($limitKey, 51, 3600);
        delete_transient($blockKey);
        $maxed = RestLoginRoutesHarness::login(new WP_REST_Request([
            'identifier' => 'siswa01',
            'password' => 'wrong-password',
        ]));
        self::assertInstanceOf(WP_Error::class, $maxed);
        self::assertSame(240, (int) ($maxed->get_error_data()['retry_after'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_student_login_with_disallowed_user_agent_returns_forbidden_and_clears_session(): void
    {
        $this->bootstrapLoginRouteHarness();
        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);
        $GLOBALS['cbt_test_login_auth_result'] = [
            'token' => 'student-token',
            'user_id' => 77,
            'role' => 'siswa',
            'display_name' => 'Siswa',
        ];

        $response = RestLoginRoutesHarness::login(new WP_REST_Request(
            [
                'identifier' => 'siswa01',
                'password' => 'secret',
            ],
            [],
            [
                'user-agent' => 'Mozilla/5.0',
            ]
        ));

        self::assertInstanceOf(WP_Error::class, $response);
        self::assertSame('student_user_agent_forbidden', $response->get_error_code());
        self::assertSame(['status' => 403], $response->get_error_data());
        self::assertSame(1, (int) ($GLOBALS['cbt_test_login_clear_session_calls'] ?? 0));
        self::assertSame(77, (int) ($GLOBALS['cbt_test_login_clear_session_user_id'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_teacher_login_ignores_student_user_agent_restriction(): void
    {
        $this->bootstrapLoginRouteHarness();
        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);
        $GLOBALS['cbt_test_login_auth_result'] = [
            'token' => 'teacher-token',
            'user_id' => 88,
            'role' => 'guru',
            'display_name' => 'Guru',
        ];

        $response = RestLoginRoutesHarness::login(new WP_REST_Request(
            [
                'identifier' => 'guru01',
                'password' => 'secret',
            ],
            [],
            [
                'user-agent' => 'Mozilla/5.0',
            ]
        ));

        self::assertIsArray($response);
        self::assertSame('teacher-token', $response['token']);
        self::assertSame(0, (int) ($GLOBALS['cbt_test_login_clear_session_calls'] ?? 0));
    }

    private function bootstrapLoginRouteHarness(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $GLOBALS['cbt_test_login_auth_calls'] = 0;
        $GLOBALS['cbt_test_login_auth_identifier'] = null;
        $GLOBALS['cbt_test_login_auth_password'] = null;
        $GLOBALS['cbt_test_login_auth_result'] = null;
        $GLOBALS['cbt_test_login_clear_session_calls'] = 0;
        $GLOBALS['cbt_test_login_clear_session_user_id'] = 0;

        if (!class_exists('CBT_Auth', false)) {
            eval(<<<'PHP'
class CBT_Auth
{
    public static function login(string $identifier, string $password)
    {
        $GLOBALS['cbt_test_login_auth_calls'] = (int) ($GLOBALS['cbt_test_login_auth_calls'] ?? 0) + 1;
        $GLOBALS['cbt_test_login_auth_identifier'] = $identifier;
        $GLOBALS['cbt_test_login_auth_password'] = $password;

        if (is_array($GLOBALS['cbt_test_login_auth_result'] ?? null)) {
            return $GLOBALS['cbt_test_login_auth_result'];
        }

        return new WP_Error('invalid_credentials', 'Invalid identifier or password', ['status' => 401]);
    }

    public static function clear_login_session(int $user_id, ?string $session_key = null): bool
    {
        $GLOBALS['cbt_test_login_clear_session_calls'] = (int) ($GLOBALS['cbt_test_login_clear_session_calls'] ?? 0) + 1;
        $GLOBALS['cbt_test_login_clear_session_user_id'] = $user_id;
        $GLOBALS['cbt_test_login_clear_session_key'] = $session_key;

        return true;
    }
}
PHP);
        }

        require_once dirname(__DIR__, 3) . '/includes/class-cbt-rest-login.php';

        if (!class_exists('RestLoginRoutesHarness', false)) {
            eval(<<<'PHP'
class RestLoginRoutesHarness
{
    use CBT_REST_Login_Routes;

    public static function mark_priority_window(string $window): void
    {
        $GLOBALS['cbt_test_login_priority_window'] = $window;
    }
}
PHP);
        }
    }

    /**
     * @return array{0:string,1:string}
     */
    private function rateLimitKeys(string $identifier): array
    {
        $limitKey = 'cbt_rl_' . md5('127.0.0.1_' . strtolower($identifier));
        return [$limitKey, $limitKey . '_block'];
    }
}
