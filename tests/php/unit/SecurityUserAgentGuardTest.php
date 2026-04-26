<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class SecurityUserAgentGuardTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_allowed_user_agents_always_include_native_android_default(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-user-agent-guard.php';

        $normalized = \CBT_Security_User_Agent_Guard::normalize_allowed_user_agents(
            "lab-browser\ncbxexamlockandroid\nLAB-BROWSER\n"
        );

        self::assertSame(['CBXExamLockAndroid', 'lab-browser'], $normalized);
    }

    #[RunInSeparateProcess]
    public function test_student_user_agent_guard_allows_contains_match_case_insensitively(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-user-agent-guard.php';

        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);

        $request = new \WP_REST_Request([], [], [
            'user-agent' => 'Mozilla/5.0 cbxexamlockandroid/1.0 Android',
        ]);

        self::assertTrue(\CBT_Security_User_Agent_Guard::guard_student_request($request, 'siswa'));
    }

    #[RunInSeparateProcess]
    public function test_student_user_agent_guard_blocks_missing_or_unknown_user_agent_when_enabled(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-user-agent-guard.php';

        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);

        $blocked = \CBT_Security_User_Agent_Guard::guard_student_request(
            new \WP_REST_Request([], [], ['user-agent' => 'Mozilla/5.0']),
            'student'
        );
        $missing = \CBT_Security_User_Agent_Guard::guard_student_request(
            new \WP_REST_Request([], [], []),
            'student'
        );

        self::assertTrue(is_wp_error($blocked));
        self::assertSame('student_user_agent_forbidden', $blocked->get_error_code());
        self::assertSame(['status' => 403], $blocked->get_error_data());
        self::assertTrue(is_wp_error($missing));
        self::assertSame('student_user_agent_forbidden', $missing->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_guard_ignores_restriction_for_teachers_and_when_toggle_is_off(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-security-user-agent-guard.php';

        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 1,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);

        $request = new \WP_REST_Request([], [], ['user-agent' => 'Mozilla/5.0']);
        self::assertTrue(\CBT_Security_User_Agent_Guard::guard_student_request($request, 'guru'));

        update_option('cbt_setup_security', [
            'restrict_student_user_agent' => 0,
            'allowed_user_agents' => ['CBXExamLockAndroid'],
        ]);

        self::assertTrue(\CBT_Security_User_Agent_Guard::guard_student_request($request, 'student'));
    }
}
