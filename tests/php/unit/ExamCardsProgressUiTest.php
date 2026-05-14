<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class ExamCardsProgressUiTest extends TestCase
{
    private string $viewSource = '';
    private string $printSource = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewSource = (string) file_get_contents(dirname(__DIR__, 3) . '/admin/views/exam-cards/page.php');
        $this->printSource = (string) file_get_contents(dirname(__DIR__, 3) . '/admin/views/exam-cards/print.php');
    }

    public function test_exam_cards_page_declares_local_progress_and_refresh_areas(): void
    {
        foreach ([
            'data-cbt-exam-cards-root',
            'data-cbt-exam-cards-progress',
            'data-cbt-exam-cards-progress-percent',
            'data-cbt-exam-cards-progress-fill',
            'role="progressbar"',
            'tanpa reload halaman global',
            'data-cbt-exam-cards-refresh-area="overview"',
            'data-cbt-exam-cards-refresh-area="notices"',
            'data-cbt-exam-cards-refresh-area="summary"',
            'data-cbt-exam-cards-refresh-area="form"',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_exam_cards_actions_are_wired_for_local_area_updates_and_print_target(): void
    {
        foreach ([
            'data-cbt-exam-cards-print-form',
            'data-cbt-exam-cards-async-link',
            'data-cbt-exam-cards-progress-profile="reset"',
            'data-cbt-exam-cards-refresh-areas="overview,notices,summary,form"',
            'window.open',
            "form.setAttribute('target', targetName)",
            'watchPrintWindow',
            'replaceExamCardsRefreshAreas',
            'runExamCardsLocalRefresh',
            'bindExamCardsUi();',
            'Gagal memperbarui area Administrative Documents',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_exam_cards_page_is_rebranded_as_administrative_documents(): void
    {
        self::assertStringContainsString('CBT Administrative Documents', $this->viewSource);
        self::assertStringContainsString('Filter & Generate Documents', $this->viewSource);
        self::assertStringContainsString('Siap memproses dokumen administrasi', $this->viewSource);
        self::assertStringNotContainsString('<h1>CBT Exam Cards</h1>', $this->viewSource);
    }

    public function test_administrative_documents_declares_attendance_and_minutes_modes(): void
    {
        foreach ([
            'cbt_minutes_subject',
            'cbt_minutes_date',
            'cbt_minutes_start_time',
            'cbt_minutes_end_time',
            'cbt_minutes_room',
            'cbt_minutes_proctor_name',
            'cbt_minutes_supervisor_name',
            'cbt_minutes_notes',
            'cbt-card-minutes-row',
            'minutesInputs',
            'isAttendanceMode',
            'isMinutesMode',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_print_template_declares_attendance_and_minutes_layouts(): void
    {
        foreach ([
            'Daftar Hadir Peserta Ujian',
            'Berita Acara Pelaksanaan',
            'attendance-table',
            'Nama Peserta',
            'NISN / Username',
            'Tanda Tangan',
            'minutes-signatures',
            'Proktor',
            'Pengawas',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->printSource);
        }
    }

    public function test_exam_cards_javascript_avoids_global_reload_fallbacks(): void
    {
        self::assertStringNotContainsString('window.location.href =', $this->viewSource);
        self::assertStringNotContainsString('window.location.assign', $this->viewSource);
        self::assertStringNotContainsString('location.reload', $this->viewSource);
        self::assertStringNotContainsString('form.submit()', $this->viewSource);
        self::assertStringNotContainsString('requestSubmit', $this->viewSource);
    }
}
