<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';

final class StudentProfileSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        cbt_test_register_user([
            'ID' => 11,
            'display_name' => 'Salsa',
            'roles' => ['student'],
            'user_email' => 'salsa@example.com',
            'user_login' => 'salsa',
            'user_pass' => 'secret',
        ]);

        update_user_meta(11, 'kode_kelas', 'XI-A');
        update_user_meta(11, 'kode_ruang', 'R1');
        update_user_meta(11, 'agama', 'Islam');
        update_user_meta(11, 'foto', 'https://example.com/salsa.jpg');
        update_user_meta(11, 'jenis_kelamin', 'Perempuan');
        update_user_meta(11, 'nisn', '20260011');
        $this->useFakeRedisClient();
    }

    public function test_get_snapshot_hydrates_redis_and_reuses_cached_snapshot_until_invalidated(): void
    {
        $snapshot = CBT_Student_Profile_Cache::get_snapshot(11);

        self::assertSame([
            'kode_kelas' => 'XI-A',
            'kode_ruang' => 'R1',
            'agama' => 'Islam',
            'foto' => 'https://example.com/salsa.jpg',
            'jenis_kelamin' => 'Perempuan',
            'nisn' => '20260011',
        ], $snapshot);
        self::assertNotSame('', $this->readStoredSnapshotPayload(11));

        update_user_meta(11, 'kode_kelas', 'XII-B');
        $cachedSnapshot = CBT_Student_Profile_Cache::get_snapshot(11);

        self::assertSame('XI-A', $cachedSnapshot['kode_kelas']);
    }

    public function test_get_snapshot_falls_back_to_usermeta_when_redis_is_unavailable(): void
    {
        $this->setProfileRedisUnavailable();

        $snapshot = CBT_Student_Profile_Cache::get_snapshot(11);

        self::assertSame('XI-A', $snapshot['kode_kelas']);
        self::assertSame('', $this->readStoredSnapshotPayload(11));
    }

    public function test_handle_user_meta_change_invalidates_only_relevant_keys(): void
    {
        CBT_Student_Profile_Cache::get_snapshot(11);
        self::assertNotSame('', $this->readStoredSnapshotPayload(11));

        CBT_Student_Profile_Cache::handle_user_meta_change(1, 11, 'first_name', 'Salsa');
        self::assertNotSame('', $this->readStoredSnapshotPayload(11));

        CBT_Student_Profile_Cache::handle_user_meta_change(2, 11, 'kode_kelas', 'XI-A');
        self::assertSame('', $this->readStoredSnapshotPayload(11));
    }

    public function test_handle_delete_user_invalidates_snapshot(): void
    {
        CBT_Student_Profile_Cache::get_snapshot(11);
        self::assertNotSame('', $this->readStoredSnapshotPayload(11));

        CBT_Student_Profile_Cache::handle_delete_user(11);

        self::assertSame('', $this->readStoredSnapshotPayload(11));
    }

    private function useFakeRedisClient(): void
    {
        $reflection = new ReflectionClass(CBT_Student_Profile_Cache::class);

        $redisProperty = $reflection->getProperty('profile_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, new CBT_Test_Redis_Client());

        $attemptedProperty = $reflection->getProperty('profile_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('profile_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, '');
    }

    private function setProfileRedisUnavailable(): void
    {
        $reflection = new ReflectionClass(CBT_Student_Profile_Cache::class);

        $redisProperty = $reflection->getProperty('profile_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('profile_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('profile_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'disabled in test');
    }

    private function readStoredSnapshotPayload(int $userId): string
    {
        return (string) ($GLOBALS['cbt_test_redis_storage']['cbt_profile:user:' . $userId] ?? '');
    }
}
