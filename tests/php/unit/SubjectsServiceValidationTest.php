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

    #[RunInSeparateProcess]
    public function test_upsert_subject_from_row_keeps_existing_code_and_description_when_file_cells_are_empty(): void
    {
        global $wpdb;
        $wpdb->subjects = [
            21 => [
                'id' => 21,
                'name' => 'Matematika',
                'code' => 'MAT',
                'description' => 'Deskripsi lama',
            ],
        ];

        $result = $this->invokeSubjectService('upsert_subject_from_row', [[
            'name' => 'Matematika',
        ]]);

        self::assertSame('updated', $result);
        self::assertSame('MAT', $wpdb->subjects[21]['code']);
        self::assertSame('Deskripsi lama', $wpdb->subjects[21]['description']);
    }

    #[RunInSeparateProcess]
    public function test_upsert_subject_from_row_reports_reason_when_code_and_name_conflict(): void
    {
        global $wpdb;
        $wpdb->subjects = [
            11 => ['id' => 11, 'name' => 'Matematika', 'code' => 'MAT', 'description' => ''],
            12 => ['id' => 12, 'name' => 'Fisika', 'code' => 'FIS', 'description' => ''],
        ];

        $reflection = new ReflectionClass(\CBT_Admin_Subjects_Service::class);
        $method = $reflection->getMethod('upsert_subject_from_row');
        $method->setAccessible(true);
        $reason = null;
        $result = $method->invokeArgs(null, [['name' => 'Fisika', 'code' => 'MAT'], &$reason]);

        self::assertSame('failed', $result);
        self::assertStringContainsString('MAT', (string) $reason);
        self::assertStringContainsString('Matematika', (string) $reason);
    }

    #[RunInSeparateProcess]
    public function test_renaming_subject_updates_bank_exam_title(): void
    {
        global $wpdb;
        $wpdb->subjects = [
            30 => ['id' => 30, 'name' => 'Matematika', 'code' => 'MAT', 'description' => ''],
        ];

        $result = \CBT_Admin_Subjects_Service::save_subject_record(30, 'Matematika Wajib', 'MAT', '');

        self::assertIsArray($result);
        self::assertSame('updated', $result['status']);
        $bankTitleUpdates = array_values(array_filter($wpdb->queries, static function (array $query): bool {
            return str_contains($query['query'], 'UPDATE wp_cbt_exams SET title');
        }));
        self::assertCount(1, $bankTitleUpdates);
        self::assertSame('Bank Soal - Matematika Wajib', $bankTitleUpdates[0]['args'][0]);
        self::assertSame(30, $bankTitleUpdates[0]['args'][2]);
    }

    #[RunInSeparateProcess]
    public function test_saving_subject_without_rename_does_not_touch_bank_exam(): void
    {
        global $wpdb;
        $wpdb->subjects = [
            31 => ['id' => 31, 'name' => 'Kimia', 'code' => 'KIM', 'description' => ''],
        ];

        \CBT_Admin_Subjects_Service::save_subject_record(31, 'Kimia', 'KIM', 'Baru');

        self::assertSame([], $wpdb->queries);
    }

    #[RunInSeparateProcess]
    public function test_save_subject_record_rejects_name_longer_than_column_limit(): void
    {
        $result = \CBT_Admin_Subjects_Service::save_subject_record(0, str_repeat('A', 121), '', '');

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('subject_name_too_long', $result->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_import_header_accepts_indonesian_aliases(): void
    {
        $header = $this->invokeSubjectService('normalize_subject_import_header', [["\xEF\xBB\xBFNama Mapel", 'KODE', 'Deskripsi']]);

        self::assertSame(['name', 'code', 'description'], $header);
    }

    #[RunInSeparateProcess]
    public function test_delete_subject_is_blocked_while_exams_use_it(): void
    {
        global $wpdb;
        $wpdb->subjects = [
            40 => ['id' => 40, 'name' => 'Biologi', 'code' => 'BIO', 'description' => ''],
        ];
        $wpdb->examCounts = [40 => 2];

        $result = \CBT_Admin_Subjects_Service::delete_subject(40);

        self::assertSame('in_use', $result['status']);
        self::assertStringContainsString('2 ujian', $result['message']);
        self::assertArrayHasKey(40, $wpdb->subjects);
        self::assertSame([], $wpdb->deleted);
    }

    #[RunInSeparateProcess]
    public function test_delete_subject_removes_empty_bank_exam_and_student_choices(): void
    {
        global $wpdb;
        $wpdb->subjects = [
            41 => ['id' => 41, 'name' => 'Sejarah', 'code' => 'SEJ', 'description' => ''],
        ];
        $wpdb->emptyBankExamIds = [501];
        $wpdb->remainingExamCount = 1;
        $wpdb->choiceUserIds = [7, 8];

        $result = \CBT_Admin_Subjects_Service::delete_subject(41);

        self::assertSame('deleted', $result['status']);
        self::assertArrayNotHasKey(41, $wpdb->subjects);
        self::assertContains(['table' => 'wp_cbt_exams', 'where' => ['id' => 501]], $wpdb->deleted);
        self::assertContains(['table' => 'wp_cbt_student_subject_choices', 'where' => ['subject_id' => 41]], $wpdb->deleted);
        self::assertSame('START TRANSACTION', $wpdb->queries[0]['query']);
        self::assertSame('COMMIT', $wpdb->queries[count($wpdb->queries) - 1]['query']);
    }

    #[RunInSeparateProcess]
    public function test_delete_subject_rolls_back_when_exam_appears_during_delete(): void
    {
        global $wpdb;
        $wpdb->subjects = [
            42 => ['id' => 42, 'name' => 'Geografi', 'code' => 'GEO', 'description' => ''],
        ];
        $wpdb->remainingExamCount = 1;

        $result = \CBT_Admin_Subjects_Service::delete_subject(42);

        self::assertSame('in_use', $result['status']);
        self::assertArrayHasKey(42, $wpdb->subjects);
        self::assertSame('ROLLBACK', $wpdb->queries[count($wpdb->queries) - 1]['query']);
    }

    #[RunInSeparateProcess]
    public function test_delete_subject_reports_missing_subject(): void
    {
        $result = \CBT_Admin_Subjects_Service::delete_subject(999);

        self::assertSame('not_found', $result['status']);
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

    /** @var array<int,int> non-bank exam count per subject */
    public array $examCounts = [];

    /** @var array<int,int> */
    public array $emptyBankExamIds = [];

    /** @var array<int,int> */
    public array $choiceUserIds = [];

    /** Exams still referencing the subject before empty bank exams are deleted. */
    public int $remainingExamCount = 0;

    /** @var array<int,array{query:string,args:array<int,mixed>}> */
    public array $queries = [];

    /** @var array<int,array{table:string,where:array<string,mixed>}> */
    public array $deleted = [];

    private int $nextSubjectId = 100;

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }

    /**
     * @param array{query:string,args:array<int,mixed>}|string $prepared
     */
    public function query($prepared): int
    {
        $this->queries[] = [
            'query' => is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared,
            'args' => is_array($prepared) ? (array) ($prepared['args'] ?? []) : [],
        ];

        return 0;
    }

    /**
     * @param array{query:string,args:array<int,mixed>}|string $prepared
     * @return array<int,array<string,mixed>>
     */
    public function get_results($prepared, $output = ARRAY_A): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        if (str_contains($query, 'AS exam_count')) {
            $rows = [];
            foreach ($this->examCounts as $subjectId => $count) {
                $rows[] = ['subject_id' => $subjectId, 'exam_count' => $count];
            }
            return $rows;
        }

        return [];
    }

    /**
     * @param array{query:string,args:array<int,mixed>}|string $prepared
     * @return array<int,int>
     */
    public function get_col($prepared): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        if (str_contains($query, 'SELECT e.id FROM')) {
            return $this->emptyBankExamIds;
        }
        if (str_contains($query, 'SELECT DISTINCT user_id')) {
            return $this->choiceUserIds;
        }

        return [];
    }

    /**
     * @param array{query:string,args:array<int,mixed>}|string $prepared
     */
    public function get_var($prepared): int
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        if (str_contains($query, 'SELECT COUNT(*) FROM wp_cbt_exams WHERE subject_id')) {
            return $this->remainingExamCount;
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $where
     */
    public function delete(string $table, array $where, $where_format = null): int|false
    {
        $this->deleted[] = ['table' => $table, 'where' => $where];
        if ($table === 'wp_cbt_exams') {
            $this->remainingExamCount = max(0, $this->remainingExamCount - 1);
        }
        if ($table === 'wp_cbt_subjects') {
            $id = (int) ($where['id'] ?? 0);
            if (!isset($this->subjects[$id])) {
                return 0;
            }
            unset($this->subjects[$id]);
        }

        return 1;
    }

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
