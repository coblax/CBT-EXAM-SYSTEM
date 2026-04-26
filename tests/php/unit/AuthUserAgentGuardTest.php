<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AuthUserAgentGuardTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_permission_blocks_student_token_with_disallowed_user_agent(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-auth.php';

        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);

        $session_key = \CBT_Auth::reset_login_session(9);
        $token = \CBT_Auth::generate_token(9, 'student', $session_key);
        $request = new \WP_REST_Request([], [], [
            'authorization' => 'Bearer ' . $token,
            'user-agent' => 'Mozilla/5.0',
        ], '/cbt/v1/session', 'GET');

        $result = \CBT_Auth::permission_teacher_or_student($request);

        self::assertTrue(is_wp_error($result));
        self::assertSame('student_user_agent_forbidden', $result->get_error_code());
        self::assertSame(['status' => 403], $result->get_error_data());
    }

    #[RunInSeparateProcess]
    public function test_permission_allows_student_token_with_allowed_user_agent(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-auth.php';

        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);

        $session_key = \CBT_Auth::reset_login_session(9);
        $token = \CBT_Auth::generate_token(9, 'student', $session_key);
        $request = new \WP_REST_Request([], [], [
            'authorization' => 'Bearer ' . $token,
            'user-agent' => 'Mozilla/5.0 CBXExamLockAndroid/1.0',
        ], '/cbt/v1/session', 'GET');

        self::assertTrue(\CBT_Auth::permission_teacher_or_student($request));
    }

    #[RunInSeparateProcess]
    public function test_permission_does_not_apply_student_user_agent_guard_to_teacher_token(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-auth.php';

        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);

        $session_key = \CBT_Auth::reset_login_session(12);
        $token = \CBT_Auth::generate_token(12, 'guru', $session_key);
        $request = new \WP_REST_Request([], [], [
            'authorization' => 'Bearer ' . $token,
            'user-agent' => 'Mozilla/5.0',
        ], '/cbt/v1/session', 'GET');

        self::assertTrue(\CBT_Auth::permission_teacher_or_student($request));
    }
}
