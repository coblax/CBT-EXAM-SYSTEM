<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

if (!class_exists('CBT_Security_Log')) {
    class CBT_Security_Log
    {
        /** @var array<int,array<string,mixed>> */
        public static array $events = [];

        /** @param array<string,mixed> $context */
        public static function record_latest_student_attempt_event(int $user_id, string $event_type, array $context = []): bool
        {
            self::$events[] = [
                'context' => $context,
                'event_type' => $event_type,
                'user_id' => $user_id,
            ];

            return true;
        }
    }
}

require_once dirname(__DIR__, 3) . '/includes/class-cbt-auth.php';

final class AuthSessionLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CBT_Security_Log::$events = [];
        cbt_test_register_user([
            'ID' => 9,
            'display_name' => 'Ayu',
            'roles' => ['student'],
            'user_email' => 'ayu@example.com',
            'user_login' => 'ayu',
            'user_pass' => 'secret',
        ]);

        update_user_meta(9, 'kode_kelas', 'XI-A');
        update_user_meta(9, 'kode_ruang', 'R1');
        update_user_meta(9, 'agama', 'Islam');
        update_user_meta(9, 'foto', 'https://example.com/avatar.jpg');
    }

    public function test_login_blocks_recent_active_session_and_allows_login_after_session_expires(): void
    {
        update_user_meta(9, 'cbt_active_login_session', 'active-session');
        update_user_meta(9, 'cbt_active_login_session_touched_at', time());

        $blocked = CBT_Auth::login('ayu', 'secret');
        self::assertTrue(is_wp_error($blocked));
        self::assertSame('session_already_active', $blocked->get_error_code());

        update_user_meta(9, 'cbt_active_login_session_touched_at', time() - 120);

        $allowed = CBT_Auth::login('ayu', 'secret');
        self::assertIsArray($allowed);
        self::assertNotSame('', (string) ($allowed['token'] ?? ''));
        self::assertSame('siswa', $allowed['role']);
    }

    public function test_clear_login_session_rejects_wrong_session_key_and_logout_current_session_only_clears_matching_session(): void
    {
        $sessionKey = CBT_Auth::reset_login_session(9);
        self::assertNotSame('', $sessionKey);

        self::assertFalse(CBT_Auth::clear_login_session(9, 'wrong-key'));
        self::assertSame($sessionKey, get_user_meta(9, 'cbt_active_login_session', true));

        $validToken = CBT_Auth::generate_token(9, 'student', $sessionKey);
        $validRequest = new WP_REST_Request([], [], [
            'authorization' => 'Bearer ' . $validToken,
        ], '/cbt/v1/logout', 'POST');

        self::assertTrue(CBT_Auth::logout_current_session($validRequest));
        self::assertSame('', get_user_meta(9, 'cbt_active_login_session', true));

        $newSessionKey = CBT_Auth::reset_login_session(9);
        $staleToken = CBT_Auth::generate_token(9, 'student', 'stale-session');
        $staleRequest = new WP_REST_Request([], [], [
            'authorization' => 'Bearer ' . $staleToken,
        ], '/cbt/v1/logout', 'POST');

        self::assertFalse(CBT_Auth::logout_current_session($staleRequest));
        self::assertSame($newSessionKey, get_user_meta(9, 'cbt_active_login_session', true));
    }

    public function test_verify_request_token_rejects_revoked_session_and_records_security_event(): void
    {
        update_user_meta(9, 'cbt_active_login_session', 'new-session');
        update_user_meta(9, 'cbt_active_login_session_touched_at', time());

        $staleToken = CBT_Auth::generate_token(9, 'student', 'old-session');
        $request = new WP_REST_Request([], [], [
            'authorization' => 'Bearer ' . $staleToken,
        ], '/cbt/v1/session', 'GET');

        $result = CBT_Auth::verify_request_token($request);

        self::assertTrue(is_wp_error($result));
        self::assertSame('session_revoked', $result->get_error_code());
        self::assertCount(1, CBT_Security_Log::$events);
        self::assertSame('session_revoked', CBT_Security_Log::$events[0]['event_type']);
        self::assertSame(9, CBT_Security_Log::$events[0]['user_id']);
    }
}
