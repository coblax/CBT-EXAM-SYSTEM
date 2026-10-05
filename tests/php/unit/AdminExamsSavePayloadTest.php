<?php

declare(strict_types=1);

use CbtExamSystem\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AdminExamsSavePayloadTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_published_exam_without_target_class_is_rejected(): void
    {
        $result = $this->normalizePayload([
            'status' => 'published',
            'target_kelas' => [],
        ]);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('invalid_target_kelas', $result->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_draft_exam_without_target_class_is_allowed(): void
    {
        $result = $this->normalizePayload([
            'status' => 'draft',
            'target_kelas' => [],
        ]);

        self::assertIsArray($result);
        self::assertSame('draft', $result['status']);
    }

    #[RunInSeparateProcess]
    public function test_exam_title_cannot_use_bank_soal_prefix(): void
    {
        $result = $this->normalizePayload([
            'title' => 'bank soal - Matematika',
        ]);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('invalid_title', $result->get_error_code());
    }

    #[RunInSeparateProcess]
    public function test_valid_published_exam_payload_is_accepted(): void
    {
        $result = $this->normalizePayload([]);

        self::assertIsArray($result);
        self::assertSame('published', $result['status']);
        self::assertSame('XI-A', $result['target_kelas']);
    }

    #[RunInSeparateProcess]
    public function test_schedule_minutes_are_saved_with_zero_seconds(): void
    {
        $result = $this->normalizePayload([
            'starts_at' => '2026-10-01T07:00',
            'ends_at' => '2026-10-01T09:30',
        ]);

        self::assertIsArray($result);
        self::assertSame('2026-10-01 07:00:00', $result['starts_at']);
        self::assertSame('2026-10-01 09:30:00', $result['ends_at']);
    }

    #[RunInSeparateProcess]
    public function test_schedule_with_seconds_is_kept_in_site_timezone(): void
    {
        $result = $this->normalizePayload([
            'starts_at' => '2026-10-01T07:00:00',
            'ends_at' => '2026-10-01T09:30:15',
        ]);

        self::assertIsArray($result);
        self::assertSame('2026-10-01 07:00:00', $result['starts_at']);
        self::assertSame('2026-10-01 09:30:15', $result['ends_at']);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>|WP_Error
     */
    private function normalizePayload(array $overrides)
    {
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-cache.php';
        require_once dirname(__DIR__, 3) . '/includes/class-cbt-exam-audience-service.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-helper.php';
        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';

        $request = array_merge([
            'id' => '0',
            'subject_id' => '4',
            'title' => 'PAS Matematika',
            'duration_minutes' => '90',
            'kkm_percentage' => '75',
            'status' => 'published',
            'starts_at' => '2026-10-01T07:00',
            'ends_at' => '2026-10-01T09:00',
            'target_kelas' => ['XI-A'],
            'source_question_ids' => ['11', '12'],
        ], $overrides);

        $method = new ReflectionMethod(CBT_Admin_Exams_Service::class, 'normalize_exam_save_payload');
        $method->setAccessible(true);

        return $method->invoke(null, $request);
    }
}
