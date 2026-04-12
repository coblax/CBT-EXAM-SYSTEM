<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-student-cohort-index-service.php';

if (!class_exists('wpdb')) {
    class wpdb
    {
    }
}

final class StudentCohortIndexServiceTest extends TestCase
{
    private StudentCohortIndexFakeWpdb $fakeWpdb;

    protected function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->fakeWpdb = new StudentCohortIndexFakeWpdb();
        $wpdb = $this->fakeWpdb;
        CBT_Student_Cohort_Index_Service::reset_availability_cache();
        update_option('cbt_student_cohort_index_enabled', '1');
    }

    public function test_upsert_query_filter_options_and_delete_use_index_rows(): void
    {
        cbt_test_register_user([
            'ID' => 11,
            'display_name' => 'Salsa',
            'user_login' => 'salsa',
            'user_email' => 'salsa@example.com',
            'roles' => ['student'],
        ]);
        update_user_meta(11, 'kode_kelas', 'xi-a');
        update_user_meta(11, 'kode_ruang', 'r1');
        update_user_meta(11, 'nisn', '71001');
        update_user_meta(11, 'agama', 'Islam');

        cbt_test_register_user([
            'ID' => 12,
            'display_name' => 'Bimo',
            'user_login' => 'bimo',
            'user_email' => 'bimo@example.com',
            'roles' => ['siswa_cbt'],
        ]);
        update_user_meta(12, 'kode_kelas', 'XI-B');
        update_user_meta(12, 'kode_ruang', 'R2');

        cbt_test_register_user([
            'ID' => 13,
            'display_name' => 'Operator',
            'user_login' => 'operator',
            'user_email' => 'operator@example.com',
            'roles' => ['administrator'],
        ]);
        update_user_meta(13, 'kode_kelas', 'XI-A');
        update_user_meta(13, 'kode_ruang', 'R1');

        self::assertTrue(CBT_Student_Cohort_Index_Service::upsert_user(11));
        self::assertTrue(CBT_Student_Cohort_Index_Service::upsert_user(12));
        self::assertTrue(CBT_Student_Cohort_Index_Service::upsert_user(13));

        $health = CBT_Student_Cohort_Index_Service::get_health_summary();
        self::assertTrue($health['ready']);
        self::assertSame(3, $health['indexed_total']);
        self::assertSame(2, $health['student_total']);

        $query = CBT_Student_Cohort_Index_Service::query_students([
            'kelas' => 'XI-A',
            'search' => 'salsa',
        ]);
        self::assertFalse($query['fallback_required']);
        self::assertSame([11], $query['user_ids']);
        self::assertSame('XI-A', $query['rows'][0]['kode_kelas']);
        self::assertSame('R1', $query['rows'][0]['kode_ruang']);

        $options = CBT_Student_Cohort_Index_Service::get_filter_options();
        self::assertSame(['XI-A', 'XI-B'], $options['kelas']);
        self::assertSame(['R1', 'R2'], $options['ruang']);

        $targetIds = CBT_Student_Cohort_Index_Service::resolve_target_student_ids_for_exam([
            'target_kelas' => 'XI-A, XI-B',
        ]);
        self::assertSame([11, 12], $targetIds);
        self::assertSame(2, CBT_Student_Cohort_Index_Service::count_target_students_for_exam([
            'target_kelas' => 'XI-A, XI-B',
        ]));

        self::assertTrue(CBT_Student_Cohort_Index_Service::delete_user(11));
        $queryAfterDelete = CBT_Student_Cohort_Index_Service::query_students(['kelas' => 'XI-A']);
        self::assertSame([], $queryAfterDelete['user_ids']);
    }

    public function test_rebuild_batch_indexes_users_by_cursor_without_scanning_user_objects_first(): void
    {
        cbt_test_register_user([
            'ID' => 21,
            'display_name' => 'Ayu',
            'user_login' => 'ayu',
            'user_email' => 'ayu@example.com',
            'roles' => ['student'],
        ]);
        update_user_meta(21, 'kode_kelas', 'XII-A');
        cbt_test_register_user([
            'ID' => 22,
            'display_name' => 'Nara',
            'user_login' => 'nara',
            'user_email' => 'nara@example.com',
            'roles' => ['student'],
        ]);
        update_user_meta(22, 'kode_kelas', 'XII-A');
        cbt_test_register_user([
            'ID' => 23,
            'display_name' => 'Admin',
            'user_login' => 'admin',
            'user_email' => 'admin@example.com',
            'roles' => ['administrator'],
        ]);

        $this->fakeWpdb->userIds = [21, 22, 23];

        $firstBatch = CBT_Student_Cohort_Index_Service::rebuild_batch(2, 0);
        self::assertSame(2, $firstBatch['processed']);
        self::assertSame(22, $firstBatch['next_cursor_user_id']);
        self::assertFalse($firstBatch['done']);

        $secondBatch = CBT_Student_Cohort_Index_Service::rebuild_batch(2, (int) $firstBatch['next_cursor_user_id']);
        self::assertSame(1, $secondBatch['processed']);
        self::assertSame(23, $secondBatch['next_cursor_user_id']);
        self::assertTrue($secondBatch['done']);

        $query = CBT_Student_Cohort_Index_Service::query_students(['kelas' => 'XII-A']);
        self::assertSame([21, 22], $query['user_ids']);
    }

    public function test_start_rebuild_and_tick_processes_background_batches(): void
    {
        cbt_test_register_user([
            'ID' => 31,
            'display_name' => 'Cika',
            'user_login' => 'cika',
            'user_email' => 'cika@example.com',
            'roles' => ['student'],
        ]);
        update_user_meta(31, 'kode_kelas', 'X-A');
        cbt_test_register_user([
            'ID' => 32,
            'display_name' => 'Dito',
            'user_login' => 'dito',
            'user_email' => 'dito@example.com',
            'roles' => ['student'],
        ]);
        update_user_meta(32, 'kode_kelas', 'X-A');
        $this->fakeWpdb->userIds = [31, 32];

        $start = CBT_Student_Cohort_Index_Service::start_rebuild('unit_test');
        self::assertTrue($start['success']);
        self::assertTrue($start['state']['active']);
        self::assertSame('active', $start['state']['status']);
        self::assertSame(2, $start['state']['total_users']);

        $firstTick = CBT_Student_Cohort_Index_Service::tick(1, 'unit_test');
        self::assertTrue($firstTick['active']);
        self::assertSame(2, $firstTick['total_users']);
        self::assertSame(1, $firstTick['processed_total']);
        self::assertSame(31, $firstTick['cursor_user_id']);

        $secondTick = CBT_Student_Cohort_Index_Service::tick(1, 'unit_test');
        self::assertTrue($secondTick['active']);
        self::assertSame(2, $secondTick['processed_total']);
        self::assertSame(32, $secondTick['cursor_user_id']);

        $finalTick = CBT_Student_Cohort_Index_Service::tick(1, 'unit_test');
        self::assertFalse($finalTick['active']);
        self::assertSame('completed', $finalTick['status']);
        self::assertSame(2, $finalTick['processed_total']);

        $query = CBT_Student_Cohort_Index_Service::query_students(['kelas' => 'X-A']);
        self::assertSame([31, 32], $query['user_ids']);
    }
}

final class StudentCohortIndexFakeWpdb extends wpdb
{
    public string $prefix = 'wp_';
    public string $users = 'wp_users';
    public array $rows = [];
    public array $userIds = [];
    public bool $tableExists = true;

    public function get_charset_collate(): string
    {
        return '';
    }

    public function prepare(string $query, ...$args): array
    {
        return [
            'query' => $query,
            'args' => $args,
        ];
    }

    public function get_var($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        if (str_starts_with($query, 'SHOW TABLES LIKE')) {
            return $this->tableExists ? $this->prefix . 'cbt_student_cohort_index' : null;
        }
        if (str_contains($query, 'SELECT COUNT(*) FROM') && str_contains($query, 'cbt_student_cohort_index')) {
            return count($this->filterRows($prepared));
        }
        if (str_contains($query, 'SELECT COUNT(*) FROM')) {
            return count($this->userIds);
        }

        return null;
    }

    public function replace(string $table, array $data, array $format = []): int|false
    {
        $userId = (int) ($data['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $this->rows[$userId] = $data;
        ksort($this->rows, SORT_NUMERIC);
        return 1;
    }

    public function delete(string $table, array $where, array $format = []): int|false
    {
        $userId = (int) ($where['user_id'] ?? 0);
        $exists = isset($this->rows[$userId]);
        unset($this->rows[$userId]);
        return $exists ? 1 : 0;
    }

    public function get_row($query, $output = ARRAY_A): array
    {
        $indexed = count($this->rows);
        $students = count(array_filter($this->rows, static function (array $row): bool {
            return (int) ($row['is_student'] ?? 0) === 1;
        }));
        $lastIndexedAt = '';
        foreach ($this->rows as $row) {
            $lastIndexedAt = max($lastIndexedAt, (string) ($row['indexed_at'] ?? ''));
        }

        return [
            'indexed_total' => $indexed,
            'student_total' => $students,
            'non_student_total' => max(0, $indexed - $students),
            'last_indexed_at' => $lastIndexedAt,
        ];
    }

    public function get_results($prepared, $output = ARRAY_A): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        if (!str_contains($query, 'SELECT user_id')) {
            return [];
        }

        $rows = $this->filterRows($prepared);

        usort($rows, static function (array $left, array $right): int {
            $compare = strnatcasecmp((string) ($left['display_name'] ?? ''), (string) ($right['display_name'] ?? ''));
            return $compare !== 0 ? $compare : ((int) ($left['user_id'] ?? 0) <=> (int) ($right['user_id'] ?? 0));
        });

        return $rows;
    }

    private function filterRows($prepared): array
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];
        $kelasValues = [];
        $argIndex = 0;
        if (preg_match('/kode_kelas IN \(([^)]+)\)/', $query, $match)) {
            $kelasCount = substr_count((string) ($match[1] ?? ''), '%s');
            $kelasValues = array_values(array_map('strval', array_slice($args, $argIndex, $kelasCount)));
            $argIndex += $kelasCount;
        }

        $ruang = '';
        if (str_contains($query, 'kode_ruang = %s')) {
            $ruang = (string) ($args[$argIndex] ?? '');
            $argIndex++;
        }

        $search = '';
        if (str_contains($query, 'LOWER(display_name) LIKE %s')) {
            $search = trim((string) ($args[$argIndex] ?? ''), '%');
        }

        return array_values(array_filter($this->rows, static function (array $row) use ($kelasValues, $ruang, $search): bool {
            if ((int) ($row['is_student'] ?? 0) !== 1) {
                return false;
            }
            if (!empty($kelasValues) && !in_array((string) ($row['kode_kelas'] ?? ''), $kelasValues, true)) {
                return false;
            }
            if ($ruang !== '' && (string) ($row['kode_ruang'] ?? '') !== $ruang) {
                return false;
            }
            if ($search !== '') {
                $haystack = strtolower(implode(' ', [
                    (string) ($row['display_name'] ?? ''),
                    (string) ($row['user_login'] ?? ''),
                    (string) ($row['user_email'] ?? ''),
                    (string) ($row['nisn'] ?? ''),
                    (string) ($row['kode_kelas'] ?? ''),
                    (string) ($row['kode_ruang'] ?? ''),
                ]));
                return str_contains($haystack, strtolower($search));
            }

            return true;
        }));
    }

    public function get_col($query): array
    {
        $queryString = is_array($query) ? (string) ($query['query'] ?? '') : (string) $query;
        $args = is_array($query) ? (array) ($query['args'] ?? []) : [];

        if (str_contains($queryString, 'SELECT ID FROM')) {
            $cursor = (int) ($args[0] ?? 0);
            $limit = max(1, (int) ($args[1] ?? 500));
            return array_slice(array_values(array_filter($this->userIds, static function (int $userId) use ($cursor): bool {
                return $userId > $cursor;
            })), 0, $limit);
        }

        if (str_contains($queryString, 'DISTINCT kode_kelas')) {
            return $this->distinctColumn('kode_kelas');
        }

        if (str_contains($queryString, 'DISTINCT kode_ruang')) {
            return $this->distinctColumn('kode_ruang');
        }

        return [];
    }

    private function distinctColumn(string $column): array
    {
        $values = [];
        foreach ($this->rows as $row) {
            if ((int) ($row['is_student'] ?? 0) !== 1) {
                continue;
            }
            $value = (string) ($row[$column] ?? '');
            if ($value !== '') {
                $values[$value] = $value;
            }
        }
        natcasesort($values);

        return array_values($values);
    }
}
