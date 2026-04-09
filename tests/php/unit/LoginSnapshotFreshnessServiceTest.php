<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-profile-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-auth-snapshot-cache.php';
require_once dirname(__DIR__, 3) . '/includes/class-cbt-login-snapshot-freshness-service.php';

final class LoginSnapshotFreshnessServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['cbt_test_current_time_timestamp'] = 1774353600;
        $GLOBALS['cbt_test_current_time_mysql'] = '2026-03-24 12:00:00';
        $this->useFakeProfileRedis();
        $this->useFakeLoginSnapshotRedis();

        cbt_test_register_user([
            'ID' => 71,
            'display_name' => 'Salsa',
            'roles' => ['student'],
            'user_email' => 'salsa@example.com',
            'user_login' => 'salsa',
            'user_pass' => 'secret',
        ]);
        update_user_meta(71, 'kode_kelas', 'XI-A');
        update_user_meta(71, 'kode_ruang', 'R1');
        update_user_meta(71, 'agama', 'Islam');
        update_user_meta(71, 'nisn', '20260071');

        cbt_test_register_user([
            'ID' => 72,
            'display_name' => 'Bimo',
            'roles' => ['student'],
            'user_email' => 'bimo@example.com',
            'user_login' => 'bimo',
            'user_pass' => 'secret-2',
        ]);
        update_user_meta(72, 'kode_kelas', 'XI-A');
        update_user_meta(72, 'kode_ruang', 'R2');
        update_user_meta(72, 'agama', 'Islam');
        update_user_meta(72, 'nisn', '20260072');

        cbt_test_register_user([
            'ID' => 73,
            'display_name' => 'Dina',
            'roles' => ['student'],
            'user_email' => 'dina@example.com',
            'user_login' => 'dina',
            'user_pass' => 'secret-3',
        ]);
        update_user_meta(73, 'kode_kelas', 'XI-B');
        update_user_meta(73, 'kode_ruang', 'R3');
        update_user_meta(73, 'agama', 'Islam');
        update_user_meta(73, 'nisn', '20260073');

        cbt_test_register_user([
            'ID' => 74,
            'display_name' => 'Rani',
            'roles' => ['student'],
            'user_email' => 'rani@example.com',
            'user_login' => 'rani',
            'user_pass' => 'secret-4',
        ]);
        update_user_meta(74, 'kode_kelas', 'XI-C');
        update_user_meta(74, 'kode_ruang', 'R4');

        $GLOBALS['wpdb'] = new LoginSnapshotFreshnessFakeWpdb();
    }

    public function test_tick_refreshes_window_exam_users_and_honors_round_robin_cursor(): void
    {
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(71, 'seed');
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(73, 'seed');
        $GLOBALS['cbt_test_redis_expiry']['cbt_login_auth:user:73'] = (int) current_time('timestamp') + 300;

        update_option('cbt_login_snapshot_freshness_state', [
            'cursor_exam_id' => 77,
        ]);

        $state = CBT_Login_Snapshot_Freshness_Service::tick();

        self::assertSame(2, (int) $state['window_exam_count']);
        self::assertSame(2, (int) $state['last_exam_batch_count']);
        self::assertSame(2, (int) $state['last_refreshed_user_count']);
        self::assertSame(2, (int) $state['last_refreshed_success_count']);
        self::assertSame(0, (int) $state['last_skipped_exam_count']);
        self::assertSame(77, (int) $state['cursor_exam_id']);
        self::assertSame('ready', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(72)['snapshot_status']);
        self::assertSame('ready', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(73)['snapshot_status']);
    }

    public function test_tick_skips_exam_with_active_preflight_job(): void
    {
        CBT_Login_Auth_Snapshot_Cache::warm_user_snapshot(71, 'seed_skip');

        update_option('cbt_exam_preflight_jobs', [
            54 => [
                'exam_id' => 54,
                'status' => 'active',
                'exam_title' => 'Ujian Biologi',
            ],
        ]);

        $state = CBT_Login_Snapshot_Freshness_Service::tick();

        self::assertSame(2, (int) $state['window_exam_count']);
        self::assertSame(2, (int) $state['last_exam_batch_count']);
        self::assertSame(1, (int) $state['last_refreshed_user_count']);
        self::assertSame(1, (int) $state['last_refreshed_success_count']);
        self::assertSame(1, (int) $state['last_skipped_exam_count']);
        self::assertSame('ready', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(72)['snapshot_status']);
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(73)['snapshot_status']);
    }

    public function test_tick_limits_batch_size_to_150_users_per_exam(): void
    {
        $GLOBALS['wpdb'] = new LoginSnapshotFreshnessSingleExamFakeWpdb();

        for ($index = 0; $index < 200; $index++) {
            $user_id = 200 + $index;
            cbt_test_register_user([
                'ID' => $user_id,
                'display_name' => 'XI-A Fresh ' . $index,
                'roles' => ['student'],
                'user_email' => 'fresh_' . $index . '@example.com',
                'user_login' => 'fresh_' . $index,
                'user_pass' => 'secret',
            ]);
            update_user_meta($user_id, 'kode_kelas', 'XII-Z');
            update_user_meta($user_id, 'kode_ruang', 'RX');
            update_user_meta($user_id, 'nisn', '800' . $index);
        }

        $state = CBT_Login_Snapshot_Freshness_Service::tick();

        self::assertSame(1, (int) $state['last_exam_batch_count']);
        self::assertSame(150, (int) $state['last_refreshed_user_count']);
        self::assertSame(150, (int) $state['last_refreshed_success_count']);
        self::assertSame('ready', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(200)['snapshot_status']);
        self::assertSame('ready', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(349)['snapshot_status']);
        self::assertSame('miss', CBT_Login_Auth_Snapshot_Cache::get_snapshot_diagnostics(350)['snapshot_status']);
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
}

final class LoginSnapshotFreshnessFakeWpdb
{
    public string $prefix = 'wp_';

    /**
     * @return array<int,array<string,mixed>>
     */
    public function get_results($query, $output = null): array
    {
        $sql = (string) $query;
        if (strpos($sql, 'FROM wp_cbt_exams e') === false) {
            return [];
        }

        return [
            [
                'id' => 77,
                'title' => 'Ujian Matematika',
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'target_kelas' => 'XI-A',
                'subject_name' => 'Matematika',
            ],
            [
                'id' => 54,
                'title' => 'Ujian Biologi',
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 60,
                'target_kelas' => 'XI-B',
                'subject_name' => 'Biologi',
            ],
            [
                'id' => 88,
                'title' => 'Ujian Fisika',
                'status' => 'published',
                'starts_at' => '2099-01-01 00:00:00',
                'ends_at' => '2099-01-01 02:00:00',
                'duration_minutes' => 90,
                'target_kelas' => 'XI-C',
                'subject_name' => 'Fisika',
            ],
        ];
    }
}

final class LoginSnapshotFreshnessSingleExamFakeWpdb
{
    public string $prefix = 'wp_';

    /**
     * @return array<int,array<string,mixed>>
     */
    public function get_results($query, $output = null): array
    {
        $sql = (string) $query;
        if (strpos($sql, 'FROM wp_cbt_exams e') === false) {
            return [];
        }

        return [
            [
                'id' => 77,
                'title' => 'Ujian Matematika',
                'status' => 'published',
                'starts_at' => '',
                'ends_at' => '',
                'duration_minutes' => 90,
                'target_kelas' => 'XII-Z',
                'subject_name' => 'Matematika',
            ],
        ];
    }
}
