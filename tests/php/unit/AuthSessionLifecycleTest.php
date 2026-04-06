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
        $this->useFakeRedisClient();
        $this->useFakeProfileRedisClient();
        $this->useFakeLoginSnapshotRedisClient();
    }

    public function test_login_blocks_recent_active_session_from_legacy_shadow_and_hydrates_redis(): void
    {
        update_user_meta(9, 'cbt_active_login_session', 'active-session');
        update_user_meta(9, 'cbt_active_login_session_touched_at', time());

        $blocked = CBT_Auth::login('ayu', 'secret');
        self::assertTrue(is_wp_error($blocked));
        self::assertSame('session_already_active', $blocked->get_error_code());
        self::assertSame('active-session', $this->readRedisSessionKey(9));
    }

    public function test_login_allows_login_after_recent_session_expires(): void
    {
        update_user_meta(9, 'cbt_active_login_session', 'active-session');
        update_user_meta(9, 'cbt_active_login_session_touched_at', time() - 120);

        $allowed = CBT_Auth::login('ayu', 'secret');
        self::assertIsArray($allowed);
        self::assertNotSame('', (string) ($allowed['token'] ?? ''));
        self::assertSame('siswa', $allowed['role']);
        self::assertSame((string) get_user_meta(9, 'cbt_active_login_session', true), $this->readRedisSessionKey(9));
    }

    public function test_login_reads_profile_fields_from_profile_snapshot_cache(): void
    {
        CBT_Student_Profile_Cache::get_snapshot(9);

        update_user_meta(9, 'kode_kelas', 'XII-Z');
        update_user_meta(9, 'kode_ruang', 'R9');
        update_user_meta(9, 'agama', 'Kristen');
        update_user_meta(9, 'foto', 'https://example.com/other-avatar.jpg');

        $result = CBT_Auth::login('ayu', 'secret');

        self::assertIsArray($result);
        self::assertSame('XI-A', $result['kode_kelas']);
        self::assertSame('R1', $result['kode_ruang']);
        self::assertSame('Islam', $result['agama']);
        self::assertSame('https://example.com/avatar.jpg', $result['foto']);
    }

    public function test_login_uses_login_snapshot_hit_without_canonical_lookup(): void
    {
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(9, 'test_hit');

        $user = get_user_by('id', 9);
        self::assertInstanceOf(WP_User::class, $user);
        $user->user_pass = 'new-secret';
        $GLOBALS['cbt_test_wp_users'][9] = $user;

        $result = CBT_Auth::login('ayu', 'secret');

        self::assertIsArray($result);
        self::assertSame('ayu', $result['username']);
        self::assertSame('XI-A', $result['kode_kelas']);
    }

    public function test_login_falls_back_to_canonical_when_login_snapshot_hash_is_stale_and_rewrites_snapshot(): void
    {
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(9, 'test_stale');

        $user = get_user_by('id', 9);
        self::assertInstanceOf(WP_User::class, $user);
        $user->user_pass = 'new-secret';
        $GLOBALS['cbt_test_wp_users'][9] = $user;

        $result = CBT_Auth::login('ayu', 'new-secret');

        self::assertIsArray($result);
        self::assertSame('ayu', $result['username']);
        self::assertSame('new-secret', $this->readLoginSnapshotPasswordHash(9));
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

    public function test_verify_request_token_rejects_revoked_session_and_records_security_event_when_redis_mismatches(): void
    {
        update_user_meta(9, 'cbt_active_login_session', 'old-session');
        update_user_meta(9, 'cbt_active_login_session_touched_at', time());
        $this->writeRedisSession(9, 'new-session', time());

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

    public function test_verify_request_token_hydrates_redis_from_legacy_on_miss(): void
    {
        update_user_meta(9, 'cbt_active_login_session', 'legacy-session');
        update_user_meta(9, 'cbt_active_login_session_touched_at', time() - 30);
        $this->useFakeRedisClient();

        $token = CBT_Auth::generate_token(9, 'student', 'legacy-session');
        $request = new WP_REST_Request([], [], [
            'authorization' => 'Bearer ' . $token,
        ], '/cbt/v1/session', 'GET');

        $result = CBT_Auth::verify_request_token($request);

        self::assertIsArray($result);
        self::assertSame('legacy-session', $this->readRedisSessionKey(9));
    }

    public function test_verify_request_token_falls_back_to_legacy_when_redis_is_unavailable(): void
    {
        update_user_meta(9, 'cbt_active_login_session', 'fallback-session');
        update_user_meta(9, 'cbt_active_login_session_touched_at', time() - 30);
        $this->setAuthRedisUnavailable();

        $token = CBT_Auth::generate_token(9, 'student', 'fallback-session');
        $request = new WP_REST_Request([], [], [
            'authorization' => 'Bearer ' . $token,
        ], '/cbt/v1/session', 'GET');

        $result = CBT_Auth::verify_request_token($request);

        self::assertIsArray($result);
        self::assertSame('', $this->readRedisSessionKey(9));
    }

    public function test_verify_request_token_touches_only_redis_when_healthy(): void
    {
        $sessionKey = CBT_Auth::reset_login_session(9);
        $legacyTouchedAt = time() - 30;
        update_user_meta(9, 'cbt_active_login_session_touched_at', $legacyTouchedAt);
        $this->writeRedisSession(9, $sessionKey, time() - 30);
        $this->resetDecodedTokenCache();

        $token = CBT_Auth::generate_token(9, 'student', $sessionKey);
        $request = new WP_REST_Request([], [], [
            'authorization' => 'Bearer ' . $token,
        ], '/cbt/v1/session', 'GET');

        $result = CBT_Auth::verify_request_token($request);

        self::assertIsArray($result);
        self::assertSame($legacyTouchedAt, (int) get_user_meta(9, 'cbt_active_login_session_touched_at', true));
        self::assertGreaterThan($legacyTouchedAt, $this->readRedisTouchedAt(9));
    }

    private function resetDecodedTokenCache(): void
    {
        $reflection = new \ReflectionClass(CBT_Auth::class);
        $property = $reflection->getProperty('decoded_token_cache');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    private function resetAuthRuntimeState(): void
    {
        $reflection = new \ReflectionClass(CBT_Auth::class);
        foreach (['decoded_token_cache', 'auth_redis', 'auth_redis_connection_attempted', 'auth_redis_last_connection_error'] as $property_name) {
            if (!$reflection->hasProperty($property_name)) {
                continue;
            }

            $property = $reflection->getProperty($property_name);
            $property->setAccessible(true);
            if ($property_name === 'decoded_token_cache') {
                $property->setValue(null, []);
            } elseif ($property_name === 'auth_redis_connection_attempted') {
                $property->setValue(null, false);
            } elseif ($property_name === 'auth_redis_last_connection_error') {
                $property->setValue(null, '');
            } else {
                $property->setValue(null, null);
            }
        }
    }

    private function useFakeRedisClient(): void
    {
        $this->resetAuthRuntimeState();
        $reflection = new \ReflectionClass(CBT_Auth::class);

        $redisProperty = $reflection->getProperty('auth_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('auth_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('auth_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function setAuthRedisUnavailable(): void
    {
        $this->resetAuthRuntimeState();
        $reflection = new \ReflectionClass(CBT_Auth::class);

        $redisProperty = $reflection->getProperty('auth_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('auth_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('auth_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'forced unavailable');
    }

    private function useFakeProfileRedisClient(): void
    {
        $reflection = new \ReflectionClass(CBT_Student_Profile_Cache::class);

        $redisProperty = $reflection->getProperty('profile_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('profile_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('profile_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function useFakeLoginSnapshotRedisClient(): void
    {
        $reflection = new \ReflectionClass(CBT_Login_Auth_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new \CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function writeRedisSession(int $userId, string $sessionKey, int $touchedAt): void
    {
        $GLOBALS['cbt_test_redis_storage'][$this->redisSessionKey($userId)] = json_encode([
            'session_key' => $sessionKey,
            'touched_at' => $touchedAt,
            'issued_at' => $touchedAt,
        ]);
        $this->useFakeRedisClient();
    }

    private function readRedisSessionKey(int $userId): string
    {
        $raw = $GLOBALS['cbt_test_redis_storage'][$this->redisSessionKey($userId)] ?? '';
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? (string) ($decoded['session_key'] ?? '') : '';
    }

    private function readRedisTouchedAt(int $userId): int
    {
        $raw = $GLOBALS['cbt_test_redis_storage'][$this->redisSessionKey($userId)] ?? '';
        if (!is_string($raw) || $raw === '') {
            return 0;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? (int) ($decoded['touched_at'] ?? 0) : 0;
    }

    private function redisSessionKey(int $userId): string
    {
        return 'cbt_auth:user:' . $userId . ':session';
    }

    private function readLoginSnapshotPasswordHash(int $userId): string
    {
        $raw = (string) ($GLOBALS['cbt_test_redis_storage']['cbt_login_auth:user:' . $userId] ?? '');
        if ($raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? (string) ($decoded['password_hash'] ?? '') : '';
    }
}
