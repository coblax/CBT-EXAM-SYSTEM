<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-users-service.php';

use CbtExamSystem\Tests\TestCase;
use ReflectionClass;

final class UsersServiceValidationTest extends TestCase
{
    public function test_validate_user_nisn_for_role_requires_nisn_for_student_and_allows_blank_for_teacher(): void
    {
        self::assertSame(
            'NISN wajib diisi untuk user siswa.',
            $this->invokeUsersService('validate_user_nisn_for_role', ['', 'siswa_cbt'])
        );
        self::assertSame(
            '',
            $this->invokeUsersService('validate_user_nisn_for_role', ['', 'guru_cbt'])
        );
    }

    public function test_validate_user_nisn_for_role_rejects_duplicate_nisn_on_other_user(): void
    {
        \cbt_test_register_user([
            'ID' => 12,
            'user_login' => 'siswa.satu',
            'user_email' => 'siswa1@example.test',
            'display_name' => 'Siswa Satu',
            'roles' => ['siswa_cbt'],
        ]);
        \update_user_meta(12, 'nisn', '1234567890');

        self::assertSame(
            'NISN sudah terdaftar pada user lain.',
            $this->invokeUsersService('validate_user_nisn_for_role', ['1234567890', 'siswa_cbt', 0])
        );
        self::assertSame(
            '',
            $this->invokeUsersService('validate_user_nisn_for_role', ['1234567890', 'siswa_cbt', 12])
        );
    }

    public function test_validate_user_import_rows_rejects_student_without_nisn(): void
    {
        $result = $this->invokeUsersService('validate_user_import_rows', [[
            [
                'name' => 'Siswa Tanpa NISN',
                'email' => 'tanpa-nisn@example.test',
                'nisn' => '',
                'username' => 'tanpa.nisn',
                'password' => 'secret',
                'role' => 'siswa',
            ],
        ]]);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('import_row_invalid', $result->get_error_code());
        self::assertStringContainsString('NISN wajib diisi untuk user siswa.', $result->get_error_message());
    }

    public function test_validate_user_import_rows_rejects_duplicate_student_nisn_in_same_file(): void
    {
        $result = $this->invokeUsersService('validate_user_import_rows', [[
            [
                'name' => 'Siswa Satu',
                'email' => 'satu@example.test',
                'nisn' => '20260001',
                'username' => 'satu',
                'password' => 'secret',
                'role' => 'siswa',
            ],
            [
                'name' => 'Siswa Dua',
                'email' => 'dua@example.test',
                'nisn' => '20260001',
                'username' => 'dua',
                'password' => 'secret',
                'role' => 'siswa',
            ],
        ]]);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('import_duplicate_nisn', $result->get_error_code());
        self::assertStringContainsString('20260001', $result->get_error_message());
    }

    public function test_validate_user_import_rows_rejects_duplicate_username_in_same_file(): void
    {
        $result = $this->invokeUsersService('validate_user_import_rows', [[
            [
                'name' => 'Guru Satu',
                'email' => 'guru1@example.test',
                'nisn' => '',
                'username' => 'guru.sama',
                'password' => 'secret',
                'role' => 'guru',
            ],
            [
                'name' => 'Guru Dua',
                'email' => 'guru2@example.test',
                'nisn' => '',
                'username' => 'guru.sama',
                'password' => 'secret',
                'role' => 'guru',
            ],
        ]]);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('import_duplicate_username', $result->get_error_code());
        self::assertStringContainsString('guru.sama', $result->get_error_message());
    }

    public function test_validate_user_import_rows_rejects_duplicate_email_in_same_file(): void
    {
        $result = $this->invokeUsersService('validate_user_import_rows', [[
            [
                'name' => 'Guru Satu',
                'email' => 'guru@example.test',
                'nisn' => '',
                'username' => 'guru.satu',
                'password' => 'secret',
                'role' => 'guru',
            ],
            [
                'name' => 'Guru Dua',
                'email' => 'guru@example.test',
                'nisn' => '',
                'username' => 'guru.dua',
                'password' => 'secret',
                'role' => 'guru',
            ],
        ]]);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('import_duplicate_email', $result->get_error_code());
        self::assertStringContainsString('guru@example.test', $result->get_error_message());
    }

    public function test_build_user_import_lookup_registers_existing_user_by_nisn(): void
    {
        \cbt_test_register_user([
            'ID' => 21,
            'user_login' => 'siswa.lookup',
            'user_email' => 'lookup@example.test',
            'display_name' => 'Siswa Lookup',
            'roles' => ['siswa_cbt'],
        ]);
        \update_user_meta(21, 'nisn', '99887766');

        $lookup = \CBT_Admin_Users_Service::build_user_import_lookup([
            [
                'name' => 'Siswa Lookup Baru',
                'email' => '',
                'nisn' => '99887766',
                'username' => '',
            ],
        ], 0, 1);

        self::assertIsArray($lookup);
        self::assertSame(21, (int) ($lookup['nisns']['99887766'] ?? 0));
    }

    /**
     * @param array<int,mixed> $arguments
     * @return mixed
     */
    private function invokeUsersService(string $method, array $arguments = [])
    {
        $reflection = new ReflectionClass(\CBT_Admin_Users_Service::class);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs(null, $arguments);
    }
}
