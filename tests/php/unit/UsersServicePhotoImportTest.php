<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-users-service.php';

use CbtExamSystem\Tests\TestCase;
use ReflectionClass;

final class UsersServicePhotoImportTest extends TestCase
{
    /** @var array<int,string> */
    private array $tempPaths = [];

    public function test_validate_user_import_header_accepts_foto_file_and_rejects_legacy_foto(): void
    {
        $valid = $this->invokeUsersService('validate_user_import_header', [[
            'name',
            'email',
            'nisn',
            'username',
            'password',
            'role',
            'kode_kelas',
            'kode_ruang',
            'agama',
            'foto_file',
        ]]);
        self::assertTrue($valid);

        $legacy = $this->invokeUsersService('validate_user_import_header', [[
            'name',
            'email',
            'nisn',
            'username',
            'password',
            'role',
            'foto',
        ]]);
        self::assertInstanceOf(\WP_Error::class, $legacy);
        self::assertSame('legacy_photo_header', $legacy->get_error_code());
    }

    public function test_prepare_user_import_photo_package_allows_blank_foto_file_without_zip(): void
    {
        $rows = [[
            'name' => 'Budi',
            'email' => '',
            'nisn' => '1000000001',
            'username' => 'budi',
            'password' => 'secret',
            'role' => 'siswa',
            'foto_file' => '',
        ]];

        $references = $this->invokeUsersService('collect_user_import_photo_references', [$rows]);
        self::assertSame([], $references);

        $package = $this->invokeUsersService('prepare_user_import_photo_package', ['tokenblank', $references, null]);
        self::assertSame([], $package);
    }

    public function test_prepare_user_import_photo_package_requires_zip_when_photo_reference_exists(): void
    {
        $rows = [[
            'name' => 'Budi',
            'email' => '',
            'nisn' => '1000000001',
            'username' => 'budi',
            'password' => 'secret',
            'role' => 'siswa',
            'foto_file' => '1000000001.png',
        ]];

        $references = $this->invokeUsersService('collect_user_import_photo_references', [$rows]);
        $package = $this->invokeUsersService('prepare_user_import_photo_package', ['tokenmissingzip', $references, null]);

        self::assertInstanceOf(\WP_Error::class, $package);
        self::assertSame('import_photo_zip_missing', $package->get_error_code());
    }

    public function test_build_user_import_photo_package_rejects_duplicate_basenames_in_zip(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia di environment test ini.');
        }

        $zipPath = $this->createZipFile([
            'kelas-a/1000000001.png' => $this->getTinyPngBinary(),
            'kelas-b/1000000001.png' => $this->getTinyPngBinary(),
        ]);

        $package = $this->invokeUsersService('build_user_import_photo_package_from_zip', [
            'tokenduplicate',
            [
                'name' => 'user-photos.zip',
                'tmp_name' => $zipPath,
            ],
        ]);

        self::assertInstanceOf(\WP_Error::class, $package);
        self::assertSame('import_photo_zip_duplicate_name', $package->get_error_code());
    }

    public function test_prepare_user_import_photo_package_rejects_missing_referenced_file(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia di environment test ini.');
        }

        $rows = [[
            'name' => 'Budi',
            'email' => '',
            'nisn' => '1000000001',
            'username' => 'budi',
            'password' => 'secret',
            'role' => 'siswa',
            'foto_file' => '1000000002.png',
        ]];
        $references = $this->invokeUsersService('collect_user_import_photo_references', [$rows]);
        $zipPath = $this->createZipFile([
            '1000000001.png' => $this->getTinyPngBinary(),
        ]);

        $package = $this->invokeUsersService('prepare_user_import_photo_package', [
            'tokenmissingfile',
            $references,
            [
                'name' => 'user-photos.zip',
                'tmp_name' => $zipPath,
                'error' => UPLOAD_ERR_OK,
            ],
        ]);

        self::assertInstanceOf(\WP_Error::class, $package);
        self::assertSame('import_photo_file_missing', $package->get_error_code());
    }

    public function test_build_user_import_photo_package_rejects_non_image_file_inside_zip(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia di environment test ini.');
        }

        $zipPath = $this->createZipFile([
            '1000000001.png' => 'ini bukan gambar',
        ]);

        $package = $this->invokeUsersService('build_user_import_photo_package_from_zip', [
            'tokennonimage',
            [
                'name' => 'user-photos.zip',
                'tmp_name' => $zipPath,
            ],
        ]);

        self::assertInstanceOf(\WP_Error::class, $package);
        self::assertContains($package->get_error_code(), [
            'import_photo_file_type_invalid',
            'import_photo_file_type_unknown',
        ]);
    }

    public function test_build_user_import_photo_package_returns_manifest_with_lowercase_keys(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia di environment test ini.');
        }

        $zipPath = $this->createZipFile([
            'Folder/Photo-One.PNG' => $this->getTinyPngBinary(),
        ]);

        $package = $this->invokeUsersService('build_user_import_photo_package_from_zip', [
            'tokenmanifest',
            [
                'name' => 'user-photos.zip',
                'tmp_name' => $zipPath,
            ],
        ]);

        self::assertIsArray($package);
        self::assertArrayHasKey('manifest', $package);
        self::assertArrayHasKey('photo-one.png', $package['manifest']);
        self::assertSame('Photo-One.PNG', $package['manifest']['photo-one.png']['basename']);
        self::assertFileExists($package['manifest']['photo-one.png']['path']);
    }

    public function test_resolve_user_import_photo_url_reuses_cached_upload_url_for_repeated_reference(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive tidak tersedia di environment test ini.');
        }

        $zipPath = $this->createZipFile([
            '1000000001.png' => $this->getTinyPngBinary(),
        ]);

        $photoPackage = $this->invokeUsersService('build_user_import_photo_package_from_zip', [
            'tokencache',
            [
                'name' => 'user-photos.zip',
                'tmp_name' => $zipPath,
            ],
        ]);

        self::assertIsArray($photoPackage);

        $urlOne = $this->invokeUsersServiceByReference('resolve_user_import_photo_url', ['1000000001.png', &$photoPackage]);
        $urlTwo = $this->invokeUsersServiceByReference('resolve_user_import_photo_url', ['1000000001.png', &$photoPackage]);

        self::assertNotSame('', $urlOne);
        self::assertSame($urlOne, $urlTwo);
        self::assertSame($urlOne, $photoPackage['uploaded_urls']['1000000001.png']);

        $uploads = \wp_upload_dir();
        $storedFiles = glob($uploads['basedir'] . '/cbt-user-import-photos/tokencache/*.png');
        self::assertIsArray($storedFiles);
        self::assertCount(1, $storedFiles);
        self::assertFileExists($storedFiles[0]);
    }

    public function test_resolve_manual_create_user_photo_only_uses_upload_and_student_default(): void
    {
        $expectedStudentPhoto = $this->invokeUsersService('get_default_student_photo_url', ['']);
        $studentPhoto = $this->invokeUsersService('resolve_manual_create_user_photo', [
            'siswa_cbt',
            ['status' => 'empty', 'url' => 'http://malicious.example/avatar.png'],
        ]);
        self::assertSame($expectedStudentPhoto, $studentPhoto);

        $teacherPhoto = $this->invokeUsersService('resolve_manual_create_user_photo', [
            'guru_cbt',
            ['status' => 'empty', 'url' => 'http://malicious.example/avatar.png'],
        ]);
        self::assertSame('', $teacherPhoto);

        $uploadedPhoto = $this->invokeUsersService('resolve_manual_create_user_photo', [
            'siswa_cbt',
            ['status' => 'uploaded', 'url' => 'http://localhost/uploads/foto-baru.png'],
        ]);
        self::assertSame('http://localhost/uploads/foto-baru.png', $uploadedPhoto);
    }

    public function test_apply_manual_update_user_photo_preserves_existing_photo_without_upload(): void
    {
        \cbt_test_register_user([
            'ID' => 41,
            'user_login' => 'siswa.foto',
            'user_email' => 'siswafoto@example.test',
            'display_name' => 'Siswa Foto',
            'roles' => ['siswa_cbt'],
        ]);
        \update_user_meta(41, 'foto', 'http://localhost/uploads/lama.png');

        $this->invokeUsersServiceByReference('apply_manual_update_user_photo', [
            41,
            ['status' => 'empty', 'url' => 'http://malicious.example/new.png'],
            false,
        ]);
        self::assertSame('http://localhost/uploads/lama.png', (string) \get_user_meta(41, 'foto', true));

        $this->invokeUsersServiceByReference('apply_manual_update_user_photo', [
            41,
            ['status' => 'uploaded', 'url' => 'http://localhost/uploads/resmi.png'],
            false,
        ]);
        self::assertSame('http://localhost/uploads/resmi.png', (string) \get_user_meta(41, 'foto', true));

        $this->invokeUsersServiceByReference('apply_manual_update_user_photo', [
            41,
            ['status' => 'empty'],
            true,
        ]);
        self::assertSame('', (string) \get_user_meta(41, 'foto', true));
    }

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $tempPath) {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        $this->tempPaths = [];
        parent::tearDown();
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

    /**
     * @param array<int,mixed> $arguments
     * @return mixed
     */
    private function invokeUsersServiceByReference(string $method, array $arguments = [])
    {
        $reflection = new ReflectionClass(\CBT_Admin_Users_Service::class);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs(null, $arguments);
    }

    /**
     * @param array<string,string> $entries
     */
    private function createZipFile(array $entries): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'cbt-user-photo-');
        if ($zipPath === false) {
            self::fail('Gagal menyiapkan file ZIP sementara untuk test.');
        }

        $this->tempPaths[] = $zipPath;

        $zip = new \ZipArchive();
        $opened = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($opened !== true) {
            self::fail('Gagal membuka file ZIP sementara untuk test.');
        }

        foreach ($entries as $entryName => $contents) {
            $zip->addFromString($entryName, $contents);
        }

        $zip->close();

        return $zipPath;
    }

    private function getTinyPngBinary(): string
    {
        $binary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0ioAAAAASUVORK5CYII=',
            true
        );

        if (!is_string($binary)) {
            self::fail('Gagal membuat binary PNG kecil untuk test.');
        }

        return $binary;
    }
}
