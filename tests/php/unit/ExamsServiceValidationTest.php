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

final class ExamsServiceValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';

        global $wpdb;
        $wpdb = new ExamsServiceFakeWpdb();
    }

    #[RunInSeparateProcess]
    public function test_upsert_exam_record_rejects_duplicate_title_in_same_subject_on_create(): void
    {
        global $wpdb;
        $wpdb->exams = [
            7 => [
                'id' => 7,
                'subject_id' => 3,
                'title' => 'UTS Matematika',
                'created_by' => 1,
            ],
        ];

        $result = $this->invokeExamsService('upsert_exam_record_from_payload', [
            $this->buildPayload(0, 3, 'UTS Matematika'),
            true,
            9,
        ]);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('exam_duplicate', $result->get_error_code());
        self::assertSame('Judul exam sudah terdaftar pada mapel ini.', $result->get_error_message());
    }

    #[RunInSeparateProcess]
    public function test_upsert_exam_record_allows_same_title_on_different_subject(): void
    {
        global $wpdb;
        $wpdb->exams = [
            7 => [
                'id' => 7,
                'subject_id' => 3,
                'title' => 'UTS Matematika',
                'created_by' => 1,
            ],
        ];

        $result = $this->invokeExamsService('upsert_exam_record_from_payload', [
            $this->buildPayload(0, 4, 'UTS Matematika'),
            true,
            9,
        ]);

        self::assertIsArray($result);
        self::assertSame(100, (int) ($result['exam_id'] ?? 0));
        self::assertCount(2, $wpdb->exams);
        self::assertSame(4, (int) ($wpdb->exams[100]['subject_id'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_upsert_exam_record_allows_updating_same_record_with_same_title(): void
    {
        global $wpdb;
        $wpdb->exams = [
            7 => [
                'id' => 7,
                'subject_id' => 3,
                'title' => 'UTS Matematika',
                'created_by' => 1,
                'duration_minutes' => 60,
            ],
        ];

        $payload = $this->buildPayload(7, 3, 'UTS Matematika');
        $payload['duration'] = 90;

        $result = $this->invokeExamsService('upsert_exam_record_from_payload', [
            $payload,
            true,
            1,
        ]);

        self::assertIsArray($result);
        self::assertSame(7, (int) ($result['exam_id'] ?? 0));
        self::assertSame(90, (int) ($wpdb->exams[7]['duration_minutes'] ?? 0));
    }

    #[RunInSeparateProcess]
    public function test_upsert_exam_record_rejects_duplicate_title_in_same_subject_on_update(): void
    {
        global $wpdb;
        $wpdb->exams = [
            7 => [
                'id' => 7,
                'subject_id' => 3,
                'title' => 'UTS Matematika',
                'created_by' => 1,
            ],
            8 => [
                'id' => 8,
                'subject_id' => 3,
                'title' => 'UAS Matematika',
                'created_by' => 1,
            ],
        ];

        $result = $this->invokeExamsService('upsert_exam_record_from_payload', [
            $this->buildPayload(7, 3, 'UAS Matematika'),
            true,
            1,
        ]);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('exam_duplicate', $result->get_error_code());
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(int $id, int $subjectId, string $title): array
    {
        return [
            'id' => $id,
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => '',
            'duration' => 60,
            'kkm_percentage' => 75.0,
            'randomize' => 0,
            'randomize_options' => 0,
            'show_student_result' => 1,
            'enable_calculator' => 0,
            'status' => 'draft',
            'starts_at' => null,
            'ends_at' => null,
            'target_kelas' => '',
            'source_question_ids' => [11],
        ];
    }

    /**
     * @param array<int,mixed> $arguments
     * @return mixed
     */
    private function invokeExamsService(string $method, array $arguments = [])
    {
        $reflection = new ReflectionClass(\CBT_Admin_Exams_Service::class);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs(null, $arguments);
    }
}

final class ExamsServiceFakeWpdb extends \wpdb
{
    /** @var array<int,array<string,mixed>> */
    public array $exams = [];

    public int $insert_id = 0;

    private int $nextExamId = 100;

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
     */
    public function get_var($prepared)
    {
        $query = is_array($prepared) ? (string) ($prepared['query'] ?? '') : (string) $prepared;
        $args = is_array($prepared) ? (array) ($prepared['args'] ?? []) : [];

        if (str_contains($query, 'SELECT id') && str_contains($query, 'WHERE subject_id = %d') && str_contains($query, 'AND title = %s') && str_contains($query, 'AND id <> %d')) {
            return $this->findDuplicateExamId((int) ($args[0] ?? 0), (string) ($args[1] ?? ''), (int) ($args[2] ?? 0));
        }

        if (str_contains($query, 'SELECT id') && str_contains($query, 'WHERE subject_id = %d') && str_contains($query, 'AND title = %s')) {
            return $this->findDuplicateExamId((int) ($args[0] ?? 0), (string) ($args[1] ?? ''), 0);
        }

        if (str_contains($query, 'SELECT title FROM') && str_contains($query, 'WHERE id = %d LIMIT 1')) {
            $id = (int) ($args[0] ?? 0);
            return isset($this->exams[$id]) ? (string) ($this->exams[$id]['title'] ?? '') : '';
        }

        if (str_contains($query, 'SELECT COUNT(*) FROM') && str_contains($query, 'cbt_questions')) {
            return 0;
        }

        if (str_contains($query, 'SELECT COUNT(*) FROM') && str_contains($query, 'AND created_by = %d')) {
            $id = (int) ($args[0] ?? 0);
            $createdBy = (int) ($args[1] ?? 0);
            return (isset($this->exams[$id]) && (int) ($this->exams[$id]['created_by'] ?? 0) === $createdBy) ? 1 : 0;
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public function update(string $table, array $data, array $where, array $format = [], array $where_format = []): int|false
    {
        $id = isset($where['id']) ? (int) $where['id'] : 0;
        if ($id <= 0 || !isset($this->exams[$id])) {
            return false;
        }

        $this->exams[$id] = array_merge($this->exams[$id], $data, ['id' => $id]);

        return 1;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insert(string $table, array $data, array $format = []): bool
    {
        $id = $this->nextExamId++;
        $this->insert_id = $id;
        $this->exams[$id] = array_merge($data, ['id' => $id]);

        return true;
    }

    private function findDuplicateExamId(int $subjectId, string $title, int $excludeId = 0): int
    {
        foreach ($this->exams as $examId => $exam) {
            if ($examId === $excludeId) {
                continue;
            }
            if ((int) ($exam['subject_id'] ?? 0) !== $subjectId) {
                continue;
            }
            if ((string) ($exam['title'] ?? '') !== $title) {
                continue;
            }

            return $examId;
        }

        return 0;
    }
}
