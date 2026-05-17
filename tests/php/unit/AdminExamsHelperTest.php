<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

class AdminExamsHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once CBT_EXAM_SYSTEM_PATH . 'admin/class-cbt-admin-exams-helper.php';
    }

    public function test_format_schedule_both_empty(): void
    {
        $this->assertSame('Belum diatur', \CBT_Admin_Exams_Helper::format_schedule('', ''));
    }

    public function test_format_schedule_both_dates(): void
    {
        $result = \CBT_Admin_Exams_Helper::format_schedule('2026-05-01 08:00', '2026-05-01 10:00');
        $this->assertStringContainsString('-', $result);
    }

    public function test_format_schedule_only_start(): void
    {
        $result = \CBT_Admin_Exams_Helper::format_schedule('2026-05-01 08:00', '');
        $this->assertStringContainsString('Mulai:', $result);
    }

    public function test_format_schedule_only_end(): void
    {
        $result = \CBT_Admin_Exams_Helper::format_schedule('', '2026-05-01 10:00');
        $this->assertStringContainsString('Selesai:', $result);
    }

    public function test_format_target_kelas_summary_empty(): void
    {
        $this->assertSame('Belum dipilih', \CBT_Admin_Exams_Helper::format_target_kelas_summary([]));
    }

    public function test_format_target_kelas_summary_one(): void
    {
        $result = \CBT_Admin_Exams_Helper::format_target_kelas_summary(['X']);
        $this->assertSame('X', $result);
    }

    public function test_format_target_kelas_summary_two(): void
    {
        $result = \CBT_Admin_Exams_Helper::format_target_kelas_summary(['X', 'XI']);
        $this->assertSame('X, XI', $result);
    }

    public function test_format_target_kelas_summary_many(): void
    {
        $result = \CBT_Admin_Exams_Helper::format_target_kelas_summary(['X', 'XI', 'XII', 'XIII']);
        $this->assertStringContainsString('+2', $result);
    }

    public function test_format_status_duration_summary_draft(): void
    {
        $result = \CBT_Admin_Exams_Helper::format_status_duration_summary('draft', 0);
        $this->assertStringContainsString('Draft', $result);
        $this->assertStringContainsString('Durasi belum diisi', $result);
    }

    public function test_format_status_duration_summary_published_with_duration(): void
    {
        $result = \CBT_Admin_Exams_Helper::format_status_duration_summary('published', 60);
        $this->assertStringContainsString('Published', $result);
        $this->assertStringContainsString('60 menit', $result);
    }

    public function test_format_selected_questions_summary_zero(): void
    {
        $this->assertSame('Belum ada soal', \CBT_Admin_Exams_Helper::format_selected_questions_summary(0));
    }

    public function test_format_selected_questions_summary_positive(): void
    {
        $this->assertSame('10 soal dipilih', \CBT_Admin_Exams_Helper::format_selected_questions_summary(10));
    }

    public function test_format_exam_list_target_kelas_display_empty(): void
    {
        $this->assertSame('Semua kelas', \CBT_Admin_Exams_Helper::format_exam_list_target_kelas_display(''));
    }

    public function test_format_exam_list_target_kelas_display_csv(): void
    {
        $result = \CBT_Admin_Exams_Helper::format_exam_list_target_kelas_display('X,XI,XII');
        $this->assertStringContainsString('X', $result);
        $this->assertStringContainsString('XI', $result);
        $this->assertStringContainsString('XII', $result);
    }

    public function test_build_question_panel_summary_returns_expected_keys(): void
    {
        $summary = \CBT_Admin_Exams_Helper::build_question_panel_summary([
            'subjects' => [['id' => 1, 'name' => 'Math', 'code' => 'MTK']],
            'selected_subject_id' => 1,
            'title' => 'Ulangan Harian',
            'starts_at' => '2026-05-01 08:00',
            'ends_at' => '2026-05-01 10:00',
            'target_kelas' => ['X', 'XI'],
            'status' => 'published',
            'duration_minutes' => 90,
            'selected_question_count' => 40,
        ]);

        $this->assertArrayHasKey('subject_label', $summary);
        $this->assertArrayHasKey('title_text', $summary);
        $this->assertArrayHasKey('schedule_text', $summary);
        $this->assertArrayHasKey('target_kelas_text', $summary);
        $this->assertArrayHasKey('status_duration_text', $summary);
        $this->assertArrayHasKey('selected_questions_text', $summary);
        $this->assertStringContainsString('Math', $summary['subject_label']);
        $this->assertSame('Ulangan Harian', $summary['title_text']);
    }

    public function test_build_question_panel_summary_no_subject(): void
    {
        $summary = \CBT_Admin_Exams_Helper::build_question_panel_summary([
            'subjects' => [],
            'selected_subject_id' => 0,
            'title' => '',
        ]);
        $this->assertSame('Belum dipilih', $summary['subject_label']);
        $this->assertSame('Belum diisi', $summary['title_text']);
    }
}
