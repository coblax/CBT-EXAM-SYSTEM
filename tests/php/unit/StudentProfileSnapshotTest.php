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

    public function test_get_snapshot_normalizes_stale_private_network_photo_urls_even_without_redis(): void
    {
        update_user_meta(11, 'foto', 'http://192.168.1.9/wp-content/plugins/cbt-exam-system/public/Default%20Pria.png');
        $this->setProfileRedisUnavailable();

        $snapshot = CBT_Student_Profile_Cache::get_snapshot(11);

        self::assertSame(
            'http://localhost/wordpress/wp-content/plugins/cbt-exam-system/public/Default%20Pria.png',
            $snapshot['foto']
        );
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

    public function test_warm_clear_and_diagnostics_manage_profile_snapshot_operationally(): void
    {
        CBT_Student_Profile_Cache::warm_snapshot(11);

        $diagnostics = CBT_Student_Profile_Cache::get_snapshot_diagnostics(11);

        self::assertTrue($diagnostics['snapshot_exists']);
        self::assertTrue($diagnostics['snapshot_valid']);
        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame('XI-A', $diagnostics['preview']['kode_kelas']);
        self::assertSame('20260011', $diagnostics['preview']['nisn']);
        self::assertGreaterThan(0, CBT_Student_Profile_Cache::clear_snapshot(11));
        $afterClear = CBT_Student_Profile_Cache::get_snapshot_diagnostics(11);
        self::assertSame('miss', $afterClear['snapshot_status']);
        self::assertSame('manual_clear', $afterClear['snapshot_miss_reason']);
        self::assertSame('Dibersihkan manual', $afterClear['snapshot_miss_reason_label']);
    }

    public function test_profile_snapshot_diagnostics_report_meta_change_and_expired_or_evicted_miss_reasons(): void
    {
        CBT_Student_Profile_Cache::warm_snapshot(11);

        CBT_Student_Profile_Cache::handle_user_meta_change(2, 11, 'kode_kelas', 'XI-A');
        $afterMetaChange = CBT_Student_Profile_Cache::get_snapshot_diagnostics(11);
        self::assertSame('miss', $afterMetaChange['snapshot_status']);
        self::assertSame('meta_changed', $afterMetaChange['snapshot_miss_reason']);
        self::assertSame('Meta profil berubah', $afterMetaChange['snapshot_miss_reason_label']);

        CBT_Student_Profile_Cache::warm_snapshot(11);
        unset($GLOBALS['cbt_test_redis_storage']['cbt_profile:user:11']);
        $afterKeyMissing = CBT_Student_Profile_Cache::get_snapshot_diagnostics(11);
        self::assertSame('miss', $afterKeyMissing['snapshot_status']);
        self::assertSame('expired_or_evicted', $afterKeyMissing['snapshot_miss_reason']);
        self::assertSame('TTL habis / ter-evict', $afterKeyMissing['snapshot_miss_reason_label']);
    }

    public function test_warm_snapshot_result_reports_ready_and_redis_unavailable_states(): void
    {
        $readyResult = CBT_Student_Profile_Cache::warm_snapshot_result(11);
        self::assertTrue($readyResult['ready']);
        self::assertTrue($readyResult['write_success']);
        self::assertSame('ready', $readyResult['reason']);
        self::assertSame('XI-A', $readyResult['snapshot']['kode_kelas']);

        $this->setProfileRedisUnavailable();

        $unavailableResult = CBT_Student_Profile_Cache::warm_snapshot_result(11);
        self::assertFalse($unavailableResult['ready']);
        self::assertFalse($unavailableResult['write_success']);
        self::assertSame('redis_unavailable', $unavailableResult['reason']);
        self::assertSame('XI-A', $unavailableResult['snapshot']['kode_kelas']);
    }

    public function test_warm_snapshot_results_uses_pipeline_batch_write_and_falls_back_when_pipeline_is_disabled(): void
    {
        cbt_test_register_user([
            'ID' => 12,
            'display_name' => 'Bima',
            'roles' => ['student'],
            'user_email' => 'bima@example.com',
            'user_login' => 'bima',
            'user_pass' => 'secret-2',
        ]);
        update_user_meta(12, 'kode_kelas', 'XI-B');
        update_user_meta(12, 'kode_ruang', 'R2');
        update_user_meta(12, 'agama', 'Islam');
        update_user_meta(12, 'foto', 'https://example.com/bima.jpg');
        update_user_meta(12, 'jenis_kelamin', 'Laki-laki');
        update_user_meta(12, 'nisn', '20260012');

        $results = CBT_Student_Profile_Cache::warm_snapshot_results([11, 12]);

        self::assertTrue($results[11]['ready']);
        self::assertTrue($results[12]['ready']);
        self::assertCount(1, (array) ($GLOBALS['cbt_test_redis_pipeline_batches'] ?? []));

        $GLOBALS['cbt_test_redis_pipeline_disabled'] = true;
        $GLOBALS['cbt_test_redis_pipeline_batches'] = [];

        $fallbackResults = CBT_Student_Profile_Cache::warm_snapshot_results([11]);

        self::assertTrue($fallbackResults[11]['ready']);
        self::assertSame([], $GLOBALS['cbt_test_redis_pipeline_batches']);
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
