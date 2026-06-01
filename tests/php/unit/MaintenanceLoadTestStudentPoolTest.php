<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class MaintenanceLoadTestStudentPoolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-users-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-maintenance-seed-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-maintenance-load-test-service.php';
    }

    #[RunInSeparateProcess]
    public function test_load_test_student_pool_excludes_reserved_seed_student_username(): void
    {
        $reservedUserId = wp_insert_user([
            'ID' => 71,
            'user_login' => \CBT_Admin_Maintenance_Seed_Service::get_seed_special_student_username(),
            'user_email' => 'coblax@example.local',
            'display_name' => 'COBLAX',
            'role' => 'siswa_cbt',
        ]);
        update_user_meta($reservedUserId, 'cbt_plain_password', \CBT_Admin_Maintenance_Seed_Service::get_seed_special_student_password());
        update_user_meta($reservedUserId, 'kode_kelas', 'KELAS_TEST_01');

        $readyUserId = wp_insert_user([
            'ID' => 72,
            'user_login' => 'bulkstudent01',
            'user_email' => 'bulkstudent01@example.local',
            'display_name' => 'Bulk Student 01',
            'role' => 'siswa_cbt',
        ]);
        \CBT_User_Password_Secret::store_user_plain_password((int) $readyUserId, 'BulkPass01');
        update_user_meta($readyUserId, 'kode_kelas', 'KELAS_TEST_02');

        $missingPasswordUserId = wp_insert_user([
            'ID' => 73,
            'user_login' => 'bulkstudent02',
            'user_email' => 'bulkstudent02@example.local',
            'display_name' => 'Bulk Student 02',
            'role' => 'siswa_cbt',
        ]);
        update_user_meta($missingPasswordUserId, 'kode_kelas', 'KELAS_TEST_03');

        $service = new \ReflectionClass(\CBT_Admin_Maintenance_Load_Test_Service::class);
        $method = $service->getMethod('get_load_test_student_pool');
        $method->setAccessible(true);

        $pool = $method->invoke(null);
        $usernames = array_map(
            static fn(array $row): string => (string) ($row['username'] ?? ''),
            (array) ($pool['rows'] ?? [])
        );

        self::assertSame(['bulkstudent01'], array_values($usernames));
        self::assertSame('BulkPass01', (string) ($pool['rows'][0]['password'] ?? ''));
        self::assertSame(2, (int) ($pool['total_count'] ?? 0));
        self::assertSame(1, (int) ($pool['valid_count'] ?? 0));
        self::assertSame(1, (int) ($pool['missing_password_count'] ?? 0));
        self::assertSame(1, (int) ($pool['reserved_excluded_count'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_load_test_student_pool_returns_empty_when_no_siswa_users_exist(): void
    {
        $service = new \ReflectionClass(\CBT_Admin_Maintenance_Load_Test_Service::class);
        $method = $service->getMethod('get_load_test_student_pool');
        $method->setAccessible(true);

        $pool = $method->invoke(null);

        self::assertSame(0, (int) ($pool['total_count'] ?? 0));
        self::assertSame(0, (int) ($pool['valid_count'] ?? 0));
        self::assertSame([], (array) ($pool['rows'] ?? []));
    }

    #[RunInSeparateProcess]
    public function test_load_test_student_pool_counts_all_missing_password_users(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $userId = wp_insert_user([
                'ID' => 80 + $i,
                'user_login' => "nopwd{$i}",
                'user_email' => "nopwd{$i}@example.local",
                'display_name' => "No Password {$i}",
                'role' => 'siswa_cbt',
            ]);
            update_user_meta($userId, 'kode_kelas', 'KELAS_TEST_01');
        }

        $service = new \ReflectionClass(\CBT_Admin_Maintenance_Load_Test_Service::class);
        $method = $service->getMethod('get_load_test_student_pool');
        $method->setAccessible(true);

        $pool = $method->invoke(null);

        self::assertSame(3, (int) ($pool['total_count'] ?? 0));
        self::assertSame(0, (int) ($pool['valid_count'] ?? 0));
        self::assertSame(3, (int) ($pool['missing_password_count'] ?? 0));
    }
}
