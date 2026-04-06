<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-auth-snapshot-cache.php';

final class LoginAuthSnapshotCacheTest extends TestCase
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

        cbt_test_register_user([
            'ID' => 12,
            'display_name' => 'Bimo',
            'roles' => ['student'],
            'user_email' => 'bimo@student.sch.id',
            'user_login' => 'bimo',
            'user_pass' => 'secret-2',
        ]);
        update_user_meta(12, 'kode_kelas', 'XI-A');
        update_user_meta(12, 'kode_ruang', 'R2');
        update_user_meta(12, 'agama', 'Islam');
        update_user_meta(12, 'nisn', '20260012');

        cbt_test_register_user([
            'ID' => 21,
            'display_name' => 'Guru',
            'roles' => ['teacher'],
            'user_email' => 'guru@example.com',
            'user_login' => 'guru',
            'user_pass' => 'teacher-pass',
        ]);

        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();
    }

    public function test_warm_get_and_clear_user_snapshot_with_identifier_index(): void
    {
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(11, 'manual');

        $loginSnapshot = CBT_Login_Auth_Snapshot_Cache::get_snapshot_by_identifier('salsa');
        self::assertIsArray($loginSnapshot);
        self::assertSame(11, $loginSnapshot['user_id']);
        self::assertSame('siswa', $loginSnapshot['role']);

        $emailSnapshot = CBT_Login_Auth_Snapshot_Cache::get_snapshot_by_identifier('salsa@example.com');
        self::assertIsArray($emailSnapshot);
        self::assertSame(11, $emailSnapshot['user_id']);

        $nisnSnapshot = CBT_Login_Auth_Snapshot_Cache::get_snapshot_by_identifier('20260011');
        self::assertIsArray($nisnSnapshot);
        self::assertSame(11, $nisnSnapshot['user_id']);

        $diagnostics = CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(11);
        self::assertSame('ready', $diagnostics['snapshot_status']);
        self::assertSame('XI-A', $diagnostics['preview']['kode_kelas']);

        self::assertGreaterThan(0, CBT_Login_Auth_Snapshot_Cache::clear_user_snapshot(11));
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(11)['snapshot_status']);
    }

    public function test_warm_user_snapshot_result_accepts_prewarmed_profile_snapshot(): void
    {
        $profileResult = CBT_Student_Profile_Cache::warm_snapshot_result(11);
        self::assertTrue($profileResult['ready']);

        $result = CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot_result(
            11,
            'preflight',
            $profileResult['snapshot']
        );

        self::assertTrue($result['ready']);
        self::assertTrue($result['write_success']);
        self::assertSame('ready', $result['reason']);
        self::assertSame('XI-A', $result['snapshot']['kode_kelas']);
        self::assertSame('20260011', $result['snapshot']['nisn']);
    }

    public function test_warm_user_snapshot_results_batches_profile_reuse_and_pipeline_write(): void
    {
        $profileResults = CBT_Student_Profile_Cache::warm_snapshot_results([11, 12]);
        $GLOBALS['cbt_test_redis_pipeline_batches'] = [];
        $results = CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot_results(
            [11, 12],
            'preflight',
            [
                11 => $profileResults[11]['snapshot'],
                12 => array_merge($profileResults[12]['snapshot'], ['kode_kelas' => 'PREWARM-XI-A']),
            ]
        );

        self::assertTrue($results[11]['ready']);
        self::assertTrue($results[12]['ready']);
        self::assertSame('PREWARM-XI-A', $results[12]['snapshot']['kode_kelas']);
        self::assertCount(1, (array) ($GLOBALS['cbt_test_redis_pipeline_batches'] ?? []));
        self::assertNotNull(CBT_Login_Auth_Snapshot_Cache::get_snapshot_by_identifier('bimo@student.sch.id'));
    }

    public function test_warm_user_snapshot_results_marks_only_failed_user_when_index_write_breaks(): void
    {
        $GLOBALS['cbt_test_redis_fail_keys'] = ['cbt_login_auth:index:login:bimo'];

        $results = CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot_results([11, 12], 'preflight');

        self::assertTrue($results[11]['ready']);
        self::assertFalse($results[12]['ready']);
        self::assertSame('write_failed', $results[12]['reason']);
        self::assertSame('ready', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(11)['snapshot_status']);
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(12)['snapshot_status']);
    }

    public function test_clear_user_snapshots_for_rewrite_only_touches_requested_users(): void
    {
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot_results([11, 12], 'preflight');

        $deleted = CBT_Login_Auth_Snapshot_Cache::clear_user_snapshots_for_rewrite([11]);

        self::assertGreaterThan(0, $deleted);
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(11)['snapshot_status']);
        self::assertSame('ready', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(12)['snapshot_status']);
    }

    public function test_warm_user_snapshot_result_reports_ineligible_or_unavailable_states(): void
    {
        $teacherResult = CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot_result(21, 'manual');
        self::assertFalse($teacherResult['ready']);
        self::assertSame('ineligible_user', $teacherResult['reason']);

        $this->useUnavailableLoginSnapshotRedis();
        $studentResult = CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot_result(11, 'manual');
        self::assertFalse($studentResult['ready']);
        self::assertSame('redis_unavailable', $studentResult['reason']);
        self::assertSame('XI-A', $studentResult['snapshot']['kode_kelas']);
    }

    public function test_warm_and_clear_exam_target_snapshots_only_touch_target_students(): void
    {
        $result = CBT_Login_Auth_Snapshot_Cache::warm_exam_target_snapshots([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ], 'cache_exam');

        self::assertSame(2, $result['target_student_count']);
        self::assertSame(2, $result['ready_count']);
        self::assertSame('ready', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(11)['snapshot_status']);
        self::assertSame('ready', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(12)['snapshot_status']);
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(21)['snapshot_status']);

        $clear = CBT_Login_Auth_Snapshot_Cache::clear_exam_target_snapshots([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        self::assertSame(2, $clear['target_student_count']);
        self::assertGreaterThan(0, $clear['deleted_keys']);
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(11)['snapshot_status']);
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(12)['snapshot_status']);
    }

    public function test_exam_target_diagnostics_report_ready_invalid_and_miss_counts(): void
    {
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(11, 'diag');
        $GLOBALS['cbt_test_redis_storage']['cbt_login_auth:user:12'] = '{broken-json';

        $diagnostics = CBT_Login_Auth_Snapshot_Cache::get_exam_target_snapshot_diagnostics([
            'id' => 77,
            'title' => 'Ujian Matematika',
            'status' => 'published',
            'target_kelas' => 'XI-A',
        ]);

        self::assertSame(2, $diagnostics['target_student_count']);
        self::assertSame(1, $diagnostics['ready_count']);
        self::assertSame(1, $diagnostics['invalid_count']);
        self::assertSame(0, $diagnostics['missing_count']);
    }

    public function test_critical_invalidation_handlers_clear_login_snapshot(): void
    {
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(11, 'critical');
        self::assertSame('ready', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(11)['snapshot_status']);

        CBT_Login_Auth_Snapshot_Cache::handle_user_meta_change(1, 11, 'nisn', '20260011');
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(11)['snapshot_status']);

        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(11, 'critical');
        $currentUser = get_user_by('id', 11);
        self::assertInstanceOf(WP_User::class, $currentUser);
        $oldUser = clone $currentUser;
        $currentUser->user_email = 'salsa-baru@example.com';
        $GLOBALS['cbt_test_wp_users'][11] = $currentUser;
        CBT_Login_Auth_Snapshot_Cache::handle_profile_update(11, $oldUser, []);
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(11)['snapshot_status']);

        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(11, 'critical');
        CBT_Login_Auth_Snapshot_Cache::handle_user_role_change(11, 'teacher', ['student']);
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(11)['snapshot_status']);

        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(12, 'critical');
        $passwordUser = get_user_by('id', 12);
        self::assertInstanceOf(WP_User::class, $passwordUser);
        CBT_Login_Auth_Snapshot_Cache::handle_password_reset($passwordUser, 'secret-baru');
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(12)['snapshot_status']);

        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(12, 'critical');
        CBT_Login_Auth_Snapshot_Cache::handle_delete_user(12);
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(12)['snapshot_status']);
    }

    private function useFakeProfileRedis(): void
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

    private function useUnavailableLoginSnapshotRedis(): void
    {
        $reflection = new ReflectionClass(CBT_Login_Auth_Snapshot_Cache::class);

        $redisProperty = $reflection->getProperty('snapshot_redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue(null, false);

        $attemptedProperty = $reflection->getProperty('snapshot_redis_connection_attempted');
        $attemptedProperty->setAccessible(true);
        $attemptedProperty->setValue(null, true);

        $errorProperty = $reflection->getProperty('snapshot_redis_last_connection_error');
        $errorProperty->setAccessible(true);
        $errorProperty->setValue(null, 'disabled in test');
    }
}
