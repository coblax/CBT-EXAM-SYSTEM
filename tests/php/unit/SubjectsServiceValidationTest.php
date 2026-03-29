<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionClass;

if (!class_exists('wpdb')) {
    eval(<<<'PHP'
class wpdb
{
    public string $prefix = 'wp_';

    public function get_charset_collate(): string
    {
        return '';
    }
}
PHP);
}

final class SubjectsServiceValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-subjects-service.php';

        global $wpdb;
        $wpdb = new SubjectsServiceFakeWpdb();
    }

    #[RunInSeparateProcess]
    public function test_validate_subject_import_rows_rejects_duplicate_name_in_same_file(): void
    {
        $result = $this->invokeSubjectService('validate_subject_import_rows', [[
            [
                'name' => 'Matematika',
                'code' => 'MAT',
                'description' => '',
            ],
            [
                'name' => 'Matematika',
                'code' => 'MAT2',
                'description' => '',
            ],
        ]]);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('subject_import_duplicate_name', $result->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_validate_subject_import_rows_rejects_duplicate_code_in_same_file(): void
    {
        $result = $this->invokeSubjectService('validate_subject_import_rows', [[
            [
                'name' => 'Matematika',
                'code' => 'MAT',
                'description' => '',
            ],
            [
                'name' => 'Fisika',
                'code' => 'MAT',
                'description' => '',
            ],
        ]]);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('subject_import_duplicate_code', $result->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_save_subject_record_rejects_duplicate_name_and_code_on_other_subject(): void
    {
        global $wpdb;
        $wpdb->subjects = [
            7 => [
                'id' => 7,
                'name' => 'Matematika',
                'code' => 'MAT',
                'description' => '',
            ],
            8 => [
                'id' => 8,
                'name' => 'Bahasa Indonesia',
                'code' => 'IND',
                'description' => '',
            ],
        ];

        $duplicateName = \CBT_Admin_Subjects_Service::save_subject_record(0, 'Matematika', 'MAT2', '');
        self::assertInstanceOf(\WP_Error::class, $duplicateName);
        self::assertSame('subject_duplicate', $duplicateName->get_error_code());
        self::assertSame('Nama subject sudah terdaftar pada subject lain.', $duplicateName->get_error_message());

        $duplicateCode = \CBT_Admin_Subjects_Service::save_subject_record(0, 'Kimia', 'IND', '');
        self::assertInstanceOf(\WP_Error::class, $duplicateCode);
        self::assertSame('subject_duplicate', $duplicateCode->get_error_code());
        self::assertSame('Code subject sudah terdaftar pada subject lain.', $duplicateCode->get_error_message());
    }

    #[RunInSeparateProcess]
    public function test_save_subject_record_rejects_missing_subject_when_updating(): void
    {
        $result = \CBT_Admin_Subjects_Service::save_subject_record(99, 'Kimia', 'KIM', '');

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('subject_not_found', $result->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_upsert_subject_from_row_fails_when_code_and_name_point_to_different_existing_subjects(): void
    {
        global $wpdb;
        $wpdb->subjects = [
            11 => [
                'id' => 11,
                'name' => 'Matematika',
                'code' => 'MAT',
                'description' => '',
            ],
            12 => [
                'id' => 12,
                'name' => 'Fisika',
                'code' => 'FIS',
                'description' => '',
            ],
        ];

        $result = $this->invokeSubjectService('upsert_subject_from_row', [[
            'name' => 'Fisika',
            'code' => 'MAT',
            'description' => 'Bentrok',
        ]]);

        self::assertSame('failed', $result);
    }

    #[RunInSeparateProcess]
    public function test_upsert_subject_from_row_updates_existing_subject_by_code_without_creating_duplicate(): void
    {
        global $wpdb;
        $wpdb->subjects = [
            15 => [
                'id' => 15,
                'name' => 'Matematika',
                'code' => 'MAT',
                'description' => 'Lama',
            ],
        ];

        $result = $this->invokeSubjectService('upsert_subject_from_row', [[
            'name' => 'Matematika Wajib',
            'code' => 'MAT',
            'description' => 'Baru',
        ]]);

        self::assertSame('updated', $result);
        self::assertSame('Matematika Wajib', $wpdb->subjects[15]['name']);
        self::assertSame('Baru', $wpdb->subjects[15]['description']);
        self::assertCount(1, $wpdb->subjects);
    }

    /**
     * @param array<int,mixed> $arguments
     * @return mixed
     */
    private function invokeSubjectService(string $method, array $arguments = [])
    {
        $reflection = new ReflectionClass(\CBT_Admin_Subjects_Service::class);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs(null, $arguments);
    }
}

final class SubjectsServiceFakeWpdb extends \wpdb
{
    /** @var array<int,array<string,mixed>> */
    public array $subjects = [];

    private int $nextSubjectId = 100;

    /**
     * @param mixed ...$args
     * @return array{query:string,args:array<int,mixed>}
     */
    public function prepare(string $query, ...$args): array
    {
        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    /**
     * @param array{query:string,args:array<int,mixed>}|string $prepared
     * @return array<string,mixed>|null
     */
    public function get_row($prepared, $output = ARRAY_A): ?array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (str_contains($query, 'WHERE id = %d')) {
            $id = isset($args[0]) ? (int) $args[0] : 0;
            return isset($this->subjects[$id]) ? $this->subjects[$id] : null;
        }

        if (str_contains($query, 'WHERE code = %s AND id <> %d')) {
            return $this->findSubjectByCode((string) ($args[0] ?? ''), (int) ($args[1] ?? 0));
        }

        if (str_contains($query, 'WHERE code = %s ORDER BY id ASC LIMIT 1')) {
            return $this->findSubjectByCode((string) ($args[0] ?? ''), 0);
        }

        if (str_contains($query, 'WHERE name = %s AND id <> %d')) {
            return $this->findSubjectByName((string) ($args[0] ?? ''), (int) ($args[1] ?? 0));
        }

        if (str_contains($query, 'WHERE name = %s ORDER BY id ASC LIMIT 1')) {
            return $this->findSubjectByName((string) ($args[0] ?? ''), 0);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public function update(string $table, array $data, array $where, array $format = [], array $where_format = []): int|false
    {
        $id = isset($where['id']) ? (int) $where['id'] : 0;
        if ($id <= 0 || !isset($this->subjects[$id])) {
            return false;
        }

        $this->subjects[$id] = array_merge($this->subjects[$id], $data);
        return 1;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insert(string $table, array $data, array $format = []): int|false
    {
        $id = $this->nextSubjectId++;
        $this->subjects[$id] = array_merge(['id' => $id], $data);
        return 1;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findSubjectByCode(string $code, int $excludeId): ?array
    {
        foreach ($this->subjects as $subject) {
            if ((int) ($subject['id'] ?? 0) === $excludeId) {
                continue;
            }
            if ((string) ($subject['code'] ?? '') === $code) {
                return $subject;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findSubjectByName(string $name, int $excludeId): ?array
    {
        foreach ($this->subjects as $subject) {
            if ((int) ($subject['id'] ?? 0) === $excludeId) {
                continue;
            }
            if ((string) ($subject['name'] ?? '') === $name) {
                return $subject;
            }
        }

        return null;
    }
}
