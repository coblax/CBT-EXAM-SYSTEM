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
                'jenis_kelamin' => 'Laki-laki',
            ],
            [
                'name' => 'Siswa Dua',
                'email' => 'dua@example.test',
                'nisn' => '20260001',
                'username' => 'dua',
                'password' => 'secret',
                'role' => 'siswa',
                'jenis_kelamin' => 'Perempuan',
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

    public function test_parse_student_subject_choices_xlsx_reads_mapel_sheet_from_combined_template(): void
    {
        if (
            !class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)
            || !class_exists(\PhpOffice\PhpSpreadsheet\Writer\Xlsx::class)
        ) {
            self::markTestSkipped('PhpSpreadsheet tidak tersedia di environment test ini.');
        }

        $tempBase = tempnam(sys_get_temp_dir(), 'cbt-mapel-template-');
        self::assertIsString($tempBase);
        $path = $tempBase . '.xlsx';
        @unlink($tempBase);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $usersSheet = $spreadsheet->getActiveSheet();
        $usersSheet->setTitle('users');
        $usersSheet->fromArray([
            ['name', 'email', 'nisn', 'username', 'password', 'role', 'kode_kelas', 'kode_ruang', 'agama', 'jenis_kelamin', 'foto_file'],
            ['Budi Santoso', '', '1000000001', 'budi.santoso', 'Password123', 'siswa', 'X-IPA-1', 'LAB-1', 'Islam', 'Laki-laki', '1000000001.jpg'],
        ]);

        $mapelSheet = $spreadsheet->createSheet();
        $mapelSheet->setTitle('mapel_pilihan');
        $mapelSheet->fromArray([
            ['nisn', 'username', 'mapel_pilihan_1', 'mapel_pilihan_2', 'mapel_pilihan_3'],
            ['1000000001', 'budi.santoso', 'INF', 'PKWU', 'SENBUD'],
        ]);
        $spreadsheet->setActiveSheetIndex(0);

        try {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($path);

            $rows = $this->invokeUsersService('parse_student_subject_choices_xlsx', [$path]);
        } finally {
            @unlink($path);
        }

        self::assertIsArray($rows);
        self::assertSame('1000000001', $rows[0]['nisn'] ?? '');
        self::assertArrayHasKey('mapel_pilihan_1', $rows[0]);
        self::assertArrayHasKey('mapel_pilihan_2', $rows[0]);
        self::assertArrayHasKey('mapel_pilihan_3', $rows[0]);
    }

    public function test_parse_student_subject_choices_csv_accepts_user_template_columns(): void
    {
        $path = dirname(__DIR__, 3) . '/templates/user-import-template.csv';
        self::assertFileExists($path);

        $rows = $this->invokeUsersService('parse_student_subject_choices_csv', [$path]);

        self::assertIsArray($rows);
        self::assertSame('1000000001', $rows[0]['nisn'] ?? '');
        self::assertArrayHasKey('mapel_pilihan_1', $rows[0]);
        self::assertArrayHasKey('mapel_pilihan_2', $rows[0]);
        self::assertArrayHasKey('mapel_pilihan_3', $rows[0]);
    }

    public function test_resolve_subject_choice_ids_from_import_row_accepts_id_code_and_name(): void
    {
        $previousWpdb = $this->installSubjectResolverFakeWpdb();

        try {
            $result = $this->invokeUsersService('resolve_subject_choice_ids_from_import_row', [[
                'mapel_pilihan_1' => 'INF',
                'mapel_pilihan_2' => 'Biologi',
                'mapel_pilihan_3' => '103',
            ]]);
        } finally {
            $this->restoreWpdb($previousWpdb);
        }

        self::assertSame([101, 102, 103], $result);
    }

    public function test_resolve_subject_choice_ids_from_import_row_rejects_unknown_subject(): void
    {
        $previousWpdb = $this->installSubjectResolverFakeWpdb();

        try {
            $result = $this->invokeUsersService('resolve_subject_choice_ids_from_import_row', [[
                'mapel_pilihan_1' => 'TIDAK_ADA',
                'mapel_pilihan_2' => '',
                'mapel_pilihan_3' => '',
            ]]);
        } finally {
            $this->restoreWpdb($previousWpdb);
        }

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('subject_not_found', $result->get_error_code());
        self::assertStringContainsString('TIDAK_ADA', $result->get_error_message());
    }

    public function test_build_user_import_preview_counts_create_and_update(): void
    {
        \cbt_test_register_user([
            'ID' => 31,
            'user_login' => 'siswa.lama',
            'user_email' => 'lama@example.test',
            'display_name' => 'Siswa Lama',
            'roles' => ['siswa_cbt'],
        ]);
        \update_user_meta(31, 'nisn', '700001');

        $preview = \CBT_Admin_Users_Service::build_user_import_preview([
            [
                'name' => 'Siswa Lama Update',
                'email' => 'lama@example.test',
                'nisn' => '700001',
                'username' => 'siswa.lama',
                'password' => 'secret',
                'role' => 'siswa',
                'jenis_kelamin' => 'Laki-laki',
            ],
            [
                'name' => 'Siswa Baru',
                'email' => '',
                'nisn' => '700002',
                'username' => 'siswa.baru',
                'password' => 'secret',
                'role' => 'siswa',
                'jenis_kelamin' => 'Perempuan',
            ],
        ]);

        self::assertSame(2, $preview['total']);
        self::assertSame(1, $preview['updated']);
        self::assertSame(1, $preview['created']);
        self::assertSame(0, $preview['failed']);
        self::assertTrue($preview['can_continue']);
    }

    public function test_build_user_import_preview_marks_duplicate_identity_rows_failed(): void
    {
        $preview = \CBT_Admin_Users_Service::build_user_import_preview([
            [
                'name' => 'Siswa Satu',
                'email' => 'sama@example.test',
                'nisn' => '800001',
                'username' => 'sama',
                'password' => 'secret',
                'role' => 'siswa',
                'jenis_kelamin' => 'Laki-laki',
            ],
            [
                'name' => 'Siswa Dua',
                'email' => 'sama@example.test',
                'nisn' => '800001',
                'username' => 'sama',
                'password' => 'secret',
                'role' => 'siswa',
                'jenis_kelamin' => 'Perempuan',
            ],
        ]);

        self::assertSame(2, $preview['total']);
        self::assertSame(1, $preview['failed']);
        self::assertSame([1], $preview['failed_rows']);
        self::assertStringContainsString('duplikat', implode(' ', array_map('strval', $preview['errors'])));
    }

    public function test_build_user_import_preview_marks_unknown_subject_choice_failed(): void
    {
        $previousWpdb = $this->installSubjectResolverFakeWpdb();

        try {
            $preview = \CBT_Admin_Users_Service::build_user_import_preview([
                [
                    'name' => 'Siswa Mapel',
                    'email' => '',
                    'nisn' => '900001',
                    'username' => 'siswa.mapel',
                    'password' => 'secret',
                    'role' => 'siswa',
                    'jenis_kelamin' => 'Laki-laki',
                    'mapel_pilihan_1' => 'TIDAK_ADA',
                ],
            ]);
        } finally {
            $this->restoreWpdb($previousWpdb);
        }

        self::assertSame(1, $preview['subject_choice_rows']);
        self::assertSame(1, $preview['failed']);
        self::assertStringContainsString('Mapel tidak ditemukan', implode(' ', array_map('strval', $preview['errors'])));
    }

    public function test_build_user_import_preview_marks_missing_photo_failed(): void
    {
        $preview = \CBT_Admin_Users_Service::build_user_import_preview([
            [
                'name' => 'Siswa Foto',
                'email' => '',
                'nisn' => '910001',
                'username' => 'siswa.foto',
                'password' => 'secret',
                'role' => 'siswa',
                'jenis_kelamin' => 'Laki-laki',
                'foto_file' => '910001.png',
            ],
        ]);

        self::assertSame(1, $preview['photo_required']);
        self::assertSame(1, $preview['photo_missing']);
        self::assertSame(1, $preview['failed']);
        self::assertStringContainsString('tidak ditemukan di ZIP Foto', implode(' ', array_map('strval', $preview['errors'])));
    }

    public function test_build_student_subject_choices_import_preview_validates_rows(): void
    {
        \cbt_test_register_user([
            'ID' => 61,
            'user_login' => 'siswa.pilihan',
            'user_email' => 'pilihan@example.test',
            'display_name' => 'Siswa Pilihan',
            'roles' => ['siswa_cbt'],
        ]);
        \update_user_meta(61, 'nisn', '920001');

        $previousWpdb = $this->installSubjectResolverFakeWpdb();

        try {
            $preview = \CBT_Admin_Users_Service::build_student_subject_choices_import_preview([
                [
                    'nisn' => '920001',
                    'username' => 'siswa.pilihan',
                    'mapel_pilihan_1' => 'INF',
                    'mapel_pilihan_2' => '',
                    'mapel_pilihan_3' => '',
                ],
                [
                    'nisn' => '920001',
                    'username' => 'siswa.pilihan',
                    'mapel_pilihan_1' => 'INF',
                    'mapel_pilihan_2' => 'INF',
                    'mapel_pilihan_3' => '',
                ],
                [
                    'nisn' => '920001',
                    'username' => 'siswa.pilihan',
                    'mapel_pilihan_1' => 'TIDAK_ADA',
                    'mapel_pilihan_2' => '',
                    'mapel_pilihan_3' => '',
                ],
            ]);
        } finally {
            $this->restoreWpdb($previousWpdb);
        }

        self::assertSame(3, $preview['total']);
        self::assertSame(1, $preview['updated']);
        self::assertSame(2, $preview['failed']);
        self::assertTrue($preview['can_continue']);
        self::assertStringContainsString('duplikat', implode(' ', array_map('strval', $preview['errors'])));
        self::assertStringContainsString('Mapel tidak ditemukan', implode(' ', array_map('strval', $preview['errors'])));
    }

    /**
     * @return mixed
     */
    private function installSubjectResolverFakeWpdb()
    {
        if (!class_exists('wpdb', false)) {
            eval('class wpdb { public $prefix = "wp_"; }');
        }

        $previous = $GLOBALS['wpdb'] ?? null;
        $GLOBALS['wpdb'] = new class extends \wpdb {
            public function __construct()
            {
                $this->prefix = 'wp_';
            }

            public function prepare($query, ...$args)
            {
                return [
                    'query' => (string) $query,
                    'args' => $args,
                ];
            }

            public function get_var($query)
            {
                $sql = is_array($query) ? (string) ($query['query'] ?? '') : (string) $query;
                $args = is_array($query) ? (array) ($query['args'] ?? []) : [];
                $value = (string) ($args[0] ?? '');

                if (str_contains($sql, 'WHERE id =') && (int) $value === 103) {
                    return 103;
                }
                if (str_contains($sql, 'UPPER(code)') && strtoupper($value) === 'INF') {
                    return 101;
                }
                if (str_contains($sql, 'LOWER(name)') && strtolower($value) === 'biologi') {
                    return 102;
                }

                return 0;
            }
        };

        return $previous;
    }

    /**
     * @param mixed $previous
     */
    private function restoreWpdb($previous): void
    {
        if ($previous === null) {
            unset($GLOBALS['wpdb']);
            return;
        }

        $GLOBALS['wpdb'] = $previous;
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
