<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AuthAnswerSyncTokenTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_scoped_answer_sync_token_is_accepted_only_for_answer_submission(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-auth.php';

        $session_key = \CBT_Auth::reset_login_session(9);
        $token = \CBT_Auth::generate_answer_sync_token(9, 'student', 55, $session_key, 600);
        $request = new \WP_REST_Request([
            'attempt_id' => 55,
        ], [], [
            'authorization' => 'Bearer ' . $token,
            'user-agent' => 'Mozilla/5.0',
        ], '/cbt/v1/submit_answers_batch', 'POST');

        self::assertTrue(\CBT_Auth::permission_answer_submission($request));
        self::assertSame(9, \CBT_Auth::current_user_id($request));
        self::assertSame('student', \CBT_Auth::current_user_role($request));

        $normalPermission = \CBT_Auth::permission_teacher_or_student($request);
        self::assertTrue(is_wp_error($normalPermission));
        self::assertSame('answer_sync_token_scope', $normalPermission->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_scoped_answer_sync_token_rejects_attempt_mismatch_and_revoked_session(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-auth.php';

        $session_key = \CBT_Auth::reset_login_session(9);
        $token = \CBT_Auth::generate_answer_sync_token(9, 'student', 55, $session_key, 600);
        $mismatch = new \WP_REST_Request([
            'attempt_id' => 56,
        ], [], [
            'authorization' => 'Bearer ' . $token,
            'user-agent' => 'Mozilla/5.0',
        ], '/cbt/v1/submit_answers_batch', 'POST');

        $mismatchResult = \CBT_Auth::verify_answer_sync_token($mismatch, 56);
        self::assertTrue(is_wp_error($mismatchResult));
        self::assertSame('answer_sync_token_attempt_mismatch', $mismatchResult->get_error_code());

        \CBT_Auth::clear_login_session(9, $session_key);

        $revoked = new \WP_REST_Request([
            'attempt_id' => 55,
        ], [], [
            'authorization' => 'Bearer ' . $token,
            'user-agent' => 'Mozilla/5.0',
        ], '/cbt/v1/submit_answers_batch', 'POST');
        $revokedResult = \CBT_Auth::verify_answer_sync_token($revoked, 55);
        self::assertTrue(is_wp_error($revokedResult));
        self::assertSame('session_revoked', $revokedResult->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_scoped_answer_sync_token_rejects_empty_bearer_header(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-auth.php';

        $request = new \WP_REST_Request([
            'attempt_id' => 55,
        ], [], [
            'authorization' => '',
            'user-agent' => 'Mozilla/5.0',
        ], '/cbt/v1/submit_answers_batch', 'POST');

        $result = \CBT_Auth::permission_answer_submission($request);
        self::assertTrue(is_wp_error($result));
    }

    #[RunInSeparateProcess]
    public function test_scoped_answer_sync_token_rejects_malformed_bearer(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-auth.php';

        $request = new \WP_REST_Request([
            'attempt_id' => 55,
        ], [], [
            'authorization' => 'Bearer invalid.token.here',
            'user-agent' => 'Mozilla/5.0',
        ], '/cbt/v1/submit_answers_batch', 'POST');

        $result = \CBT_Auth::permission_answer_submission($request);
        self::assertTrue(is_wp_error($result));
    }
}
