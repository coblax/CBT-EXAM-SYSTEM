<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-page.php';

final class AdminExamsSnapshotRenderTest extends TestCase
{
    public function test_render_snapshot_panel_defaults_to_preflight_tab(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs());

        self::assertStringContainsString('One-Click Pra Ujian', $html);
        self::assertStringContainsString('Monitor Snapshot Soal', $html);
        self::assertStringContainsString('Monitor Snapshot Start', $html);
        self::assertStringContainsString('Monitor Snapshot Submit', $html);
        self::assertStringContainsString('Monitor Session Runtime', $html);
        self::assertStringContainsString('Monitor Snapshot Exam', $html);
        self::assertStringContainsString('Monitor Snapshot Profile', $html);
        self::assertStringContainsString('Monitor Snapshot Login', $html);
        self::assertStringContainsString('Jalankan One-Click Pra Ujian', $html);
        self::assertStringContainsString('Bersihkan Semua Snapshot', $html);
        self::assertStringContainsString('Bersihkan Semua Redis CBT', $html);
        self::assertStringContainsString('Adaptive Load', $html);
        self::assertStringContainsString('Paksa Busy (15 menit)', $html);
        self::assertStringContainsString('Paksa Critical (15 menit)', $html);
        self::assertStringContainsString('Heartbeat: 20 detik', $html);
        self::assertStringContainsString('Snapshot refresh: 10 detik', $html);
        self::assertStringContainsString('data-cbt-preflight-clean-form="1"', $html);
        self::assertStringContainsString('data-cbt-clean-target-count="18"', $html);
        self::assertStringContainsString('reset runtime harian antar beberapa exam', $html);
        self::assertStringContainsString('Siswa Bermasalah', $html);
        self::assertStringContainsString('Auto-Warm Availability', $html);
        self::assertStringContainsString('Login Snapshot', $html);
        self::assertStringContainsString('Submission Context', $html);
        self::assertStringContainsString('Kesiapan Saat Ini', $html);
        self::assertStringContainsString('Jadwal & Waktu', $html);
        self::assertStringContainsString('Peserta Target', $html);
        self::assertStringContainsString('Target Kelas', $html);
        self::assertStringContainsString('Token MANUAL', $html);
        self::assertStringContainsString('Siap 8/8 · Belum 0', $html);
        self::assertStringContainsString('Total 8 soal · Cache Siap · Warm Siap', $html);
        self::assertStringContainsString('Siap 12/18 · Pending 6', $html);
        self::assertStringContainsString('Reuse 4 · Gagal 1 · Diproses 13', $html);
        self::assertStringContainsString('Siap 11/18 · Pending 7', $html);
        self::assertStringContainsString('Reuse 3 · Gagal 2 · Diproses 13', $html);
        self::assertStringContainsString('Siap 8/8 · Belum 0', $html);
        self::assertStringContainsString('Total 8 soal · INVALID 0', $html);
        self::assertStringContainsString('Siap 9/18 · Pending 2', $html);
        self::assertStringContainsString('Reuse 7 · Gagal 0 · Queue 1', $html);
        self::assertStringContainsString('Mode Global', $html);
        self::assertStringContainsString('Batch 150', $html);
        self::assertStringContainsString('Queue Rewarm Availability', $html);
        self::assertStringContainsString('Warm Login Readiness', $html);
        self::assertStringContainsString('Mulai Warm Login Readiness', $html);
        self::assertStringContainsString('Hentikan Queue', $html);
        self::assertStringContainsString('Target Source', $html);
        self::assertStringContainsString('Cohort Index', $html);
        self::assertStringContainsString('66.7% · 12 / 18 siap', $html);
        self::assertStringContainsString('Diproses Batch Terakhir', $html);
        self::assertStringContainsString('Queue rewarm memproses 2 user.', $html);
        self::assertStringContainsString('Global Runner Owner:', $html);
        self::assertStringContainsString('Mode Global:', $html);
        self::assertStringContainsString('Batch Size:', $html);
        self::assertStringContainsString('Queued Exams:', $html);
        self::assertStringContainsString('Mode global sekarang berjalan paralel', $html);
        self::assertStringContainsString('diperbarui otomatis setiap 10 detik', $html);
        self::assertStringNotContainsString('Ringkasan Profil', $html);
        self::assertStringNotContainsString('Ringkasan Availability', $html);
        self::assertStringNotContainsString('Status Operasional', $html);
        self::assertStringNotContainsString('Preview Soal (8-8 dari 8)', $html);
        self::assertStringNotContainsString('name="cbt_exam_snapshot_tab" value="preflight"', $html);
    }

    public function test_render_snapshot_panel_renders_question_monitor_tab(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR,
        ]));

        self::assertStringContainsString('Pantau snapshot delivery soal per exam terpilih', $html);
        self::assertStringContainsString('Preview Soal (8-8 dari 8)', $html);
        self::assertStringContainsString('Soal preview pertama', $html);
        self::assertStringContainsString('Siapkan Snapshot Exam', $html);
        self::assertStringContainsString('Bersihkan Snapshot Exam', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_tab" value="question_monitor"', $html);
        self::assertStringNotContainsString('Jalankan One-Click Pra Ujian', $html);
    }

    public function test_render_snapshot_panel_shows_question_monitor_miss_reason_note(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR,
            'exam_snapshot_rows' => [
                array_replace_recursive($this->snapshotPanelArgs()['exam_snapshot_rows'][0], [
                    'snapshot_status_label' => 'MISS',
                    'snapshot_status_tone' => 'warning',
                    'snapshot_exists' => false,
                    'snapshot_valid' => false,
                    'snapshot_miss_reason_label' => 'Revision berubah',
                    'snapshot_message' => 'Snapshot MISS karena revision exam berubah. Key revision sebelumnya tidak lagi dianggap current dan perlu dihangatkan ulang.',
                    'snapshot_item_count' => 0,
                    'snapshot_payload_bytes' => 0,
                    'snapshot_ttl_seconds' => -2,
                    'preview_question_ids' => [],
                    'preview_items' => [],
                ]),
            ],
        ]));

        self::assertStringContainsString('Alasan MISS: Revision berubah', $html);
        self::assertStringContainsString('Snapshot MISS karena revision exam berubah', $html);
    }

    public function test_render_snapshot_panel_shows_question_monitor_repair_note(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR,
            'exam_snapshot_rows' => [
                array_replace_recursive($this->snapshotPanelArgs()['exam_snapshot_rows'][0], [
                    'snapshot_status' => 'ready',
                    'snapshot_status_label' => 'READY',
                    'snapshot_status_tone' => 'success',
                    'repair_status' => 'auto_healed',
                    'repair_message' => 'Dipulihkan otomatis dari revision exam terbaru',
                    'snapshot_message' => 'Snapshot Redis siap dipakai untuk base payload student GET /questions.',
                ]),
            ],
        ]));

        self::assertStringContainsString('Dipulihkan otomatis dari revision exam terbaru', $html);
        self::assertStringNotContainsString('Alasan MISS:', $html);
    }

    public function test_render_snapshot_panel_renders_start_monitor_tab(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR,
        ]));

        self::assertStringContainsString('Pantau start snapshot per exam', $html);
        self::assertStringContainsString('Start snapshot Redis siap dipakai.', $html);
        self::assertStringContainsString('Siapkan Snapshot Exam + Start', $html);
        self::assertStringContainsString('Bersihkan Snapshot Exam + Start', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_tab" value="start_monitor"', $html);
        self::assertStringNotContainsString('Preview Soal (8-8 dari 8)', $html);
        self::assertStringNotContainsString('Jalankan One-Click Pra Ujian', $html);
    }

    public function test_render_snapshot_panel_shows_start_monitor_miss_reason_note(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR,
            'exam_snapshot_rows' => [
                array_replace_recursive($this->snapshotPanelArgs()['exam_snapshot_rows'][0], [
                    'start_snapshot_status' => 'miss',
                    'start_snapshot_status_label' => 'MISS',
                    'start_snapshot_status_tone' => 'warning',
                    'start_snapshot_exists' => false,
                    'start_snapshot_valid' => false,
                    'start_snapshot_miss_reason_label' => 'Dibersihkan manual',
                    'start_snapshot_message' => 'Start snapshot MISS karena dibersihkan manual dari panel admin. Start attempt berikutnya akan fallback lalu dapat dipanaskan ulang.',
                    'start_snapshot_item_count' => 0,
                    'start_snapshot_payload_bytes' => 0,
                    'start_snapshot_ttl_seconds' => -2,
                ]),
            ],
        ]));

        self::assertStringContainsString('Alasan MISS: Dibersihkan manual', $html);
        self::assertStringContainsString('Start snapshot MISS karena dibersihkan manual', $html);
    }

    public function test_render_snapshot_panel_shows_start_monitor_repair_note(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_START_MONITOR,
            'exam_snapshot_rows' => [
                array_replace_recursive($this->snapshotPanelArgs()['exam_snapshot_rows'][0], [
                    'start_snapshot_status' => 'ready',
                    'start_snapshot_status_label' => 'READY',
                    'start_snapshot_status_tone' => 'success',
                    'start_snapshot_repair_status' => 'auto_healed',
                    'start_snapshot_repair_message' => 'Dipulihkan otomatis dari revision exam terbaru',
                    'start_snapshot_message' => 'Start snapshot Redis siap dipakai.',
                ]),
            ],
        ]));

        self::assertStringContainsString('Dipulihkan otomatis dari revision exam terbaru', $html);
        self::assertStringNotContainsString('Alasan MISS:', $html);
    }

    public function test_render_snapshot_panel_renders_submission_context_monitor_tab(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR,
        ]));

        self::assertStringContainsString('Monitor Snapshot Submit', $html);
        self::assertStringContainsString('konteks evaluasi jawaban', $html);
        self::assertStringContainsString('Siapkan Semua Submission Context', $html);
        self::assertStringContainsString('Bersihkan Semua Submission Context', $html);
        self::assertStringContainsString('Siapkan Submission Context', $html);
        self::assertStringContainsString('Bersihkan Submission Context', $html);
        self::assertStringContainsString('Q#901', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_tab" value="submission_context_monitor"', $html);
        self::assertStringNotContainsString('Snapshot ini memuat katalog exam siswa yang tersedia, bukan snapshot satu exam tunggal.', $html);
    }

    public function test_render_snapshot_panel_renders_submission_context_reason_notes(): void
    {
        $args = $this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_SUBMISSION_CONTEXT_MONITOR,
        ]);

        $args['exam_snapshot_rows'][0]['submission_context_status_label'] = 'MISS';
        $args['exam_snapshot_rows'][0]['submission_context_status_tone'] = 'warning';
        $args['exam_snapshot_rows'][0]['submission_context'] = [
            'question_count' => 8,
            'ready_count' => 0,
            'missing_count' => 8,
            'invalid_count' => 0,
            'payload_bytes_total' => 0,
            'snapshot_exists' => false,
            'snapshot_valid' => false,
            'snapshot_status' => 'miss',
            'snapshot_miss_reason' => 'expired_or_evicted',
            'snapshot_miss_reason_label' => 'TTL habis / ter-evict',
            'snapshot_message' => 'Submission context MISS karena key sebelumnya kemungkinan sudah expired atau ter-evict.',
            'redis_host' => '127.0.0.1',
            'redis_database' => 2,
            'preview_items' => [
                ['question_id' => 901, 'question_type' => 'multiple_choice', 'status' => 'miss', 'payload_bytes' => 0, 'reason' => 'storage_missing', 'reason_label' => 'Payload hilang'],
            ],
        ];

        $html = $this->renderSnapshotPanel($args);

        self::assertStringContainsString('Alasan MISS: TTL habis / ter-evict', $html);
        self::assertStringContainsString('Payload hilang', $html);

        $args['exam_snapshot_rows'][0]['submission_context_status_label'] = 'INVALID';
        $args['exam_snapshot_rows'][0]['submission_context_status_tone'] = 'error';
        $args['exam_snapshot_rows'][0]['submission_context']['snapshot_status'] = 'invalid';
        $args['exam_snapshot_rows'][0]['submission_context']['snapshot_miss_reason'] = 'revision_changed';
        $args['exam_snapshot_rows'][0]['submission_context']['snapshot_miss_reason_label'] = 'Revision berubah';
        $args['exam_snapshot_rows'][0]['submission_context']['snapshot_message'] = 'Submission context INVALID karena revision exam berubah.';
        $args['exam_snapshot_rows'][0]['submission_context']['missing_count'] = 0;
        $args['exam_snapshot_rows'][0]['submission_context']['invalid_count'] = 8;
        $args['exam_snapshot_rows'][0]['submission_context']['preview_items'][0]['status'] = 'invalid';
        $args['exam_snapshot_rows'][0]['submission_context']['preview_items'][0]['reason'] = 'revision_changed';
        $args['exam_snapshot_rows'][0]['submission_context']['preview_items'][0]['reason_label'] = 'Revision berubah';

        $htmlInvalid = $this->renderSnapshotPanel($args);

        self::assertStringContainsString('Alasan INVALID: Revision berubah', $htmlInvalid);

        $args['exam_snapshot_rows'][0]['submission_context_status_label'] = 'READY';
        $args['exam_snapshot_rows'][0]['submission_context_status_tone'] = 'success';
        $args['exam_snapshot_rows'][0]['submission_context']['snapshot_status'] = 'ready';
        $args['exam_snapshot_rows'][0]['submission_context']['snapshot_miss_reason'] = '';
        $args['exam_snapshot_rows'][0]['submission_context']['snapshot_miss_reason_label'] = '';
        $args['exam_snapshot_rows'][0]['submission_context']['repair_status'] = 'auto_healed';
        $args['exam_snapshot_rows'][0]['submission_context']['repair_message'] = 'Dipulihkan otomatis dari data submit canonical';
        $args['exam_snapshot_rows'][0]['submission_context']['snapshot_message'] = 'Submission context siap dipakai untuk submit jawaban dan scoring objektif.';

        $htmlHealed = $this->renderSnapshotPanel($args);

        self::assertStringContainsString('Dipulihkan otomatis dari data submit canonical', $htmlHealed);
    }

    public function test_render_snapshot_panel_renders_session_runtime_monitor_tab(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR,
            'student_snapshot_status_options' => [
                ['value' => 'ready', 'label' => 'READY'],
                ['value' => 'session_miss', 'label' => 'SESSION MISS'],
                ['value' => 'contract_miss', 'label' => 'CONTRACT MISS'],
                ['value' => 'runtime_miss', 'label' => 'RUNTIME MISS'],
                ['value' => 'stale', 'label' => 'STALE LAST SEEN'],
                ['value' => 'low_remaining', 'label' => 'LOW REMAINING'],
                ['value' => '!ready', 'label' => '! READY'],
                ['value' => '!session_miss', 'label' => '! SESSION MISS'],
                ['value' => '!contract_miss', 'label' => '! CONTRACT MISS'],
                ['value' => '!runtime_miss', 'label' => '! RUNTIME MISS'],
                ['value' => '!stale', 'label' => '! STALE LAST SEEN'],
                ['value' => '!low_remaining', 'label' => '! LOW REMAINING'],
            ],
        ]));

        self::assertStringContainsString('Monitor Session Runtime', $html);
        self::assertStringContainsString('Pantau attempt siswa yang sedang `in_progress`', $html);
        self::assertStringContainsString('Cari Siswa', $html);
        self::assertStringContainsString('Semua kelas', $html);
        self::assertStringContainsString('Semua ruang', $html);
        self::assertStringContainsString('Status', $html);
        self::assertStringContainsString('RUNTIME MISS', $html);
        self::assertStringContainsString('! READY', $html);
        self::assertStringContainsString('Attempt Aktif', $html);
        self::assertStringContainsString('Start Gate', $html);
        self::assertStringContainsString('Queue Depth', $html);
        self::assertStringContainsString('Bucket Tokens', $html);
        self::assertStringContainsString('Release Rate', $html);
        self::assertStringContainsString('Oldest Wait', $html);
        self::assertStringContainsString('Redis-First', $html);
        self::assertStringContainsString('Legacy', $html);
        self::assertStringContainsString('Session Ready', $html);
        self::assertStringContainsString('Contract Ready', $html);
        self::assertStringContainsString('Runtime Ready', $html);
        self::assertStringContainsString('Stale Last Seen', $html);
        self::assertStringContainsString('<th>Session Snapshot</th>', $html);
        self::assertStringContainsString('<th>Contract Snapshot</th>', $html);
        self::assertStringNotContainsString('<th>Delivery Snapshot</th>', $html);
        self::assertStringContainsString('Detail Delivery Snapshot', $html);
        self::assertStringContainsString('Actionable Flags:', $html);
        self::assertStringContainsString('Fallback Breakdown:', $html);
        self::assertStringContainsString('GATED', $html);
        self::assertStringContainsString('50 / 5 detik', $html);
        self::assertStringContainsString('Delivery Storage Key:', $html);
        self::assertStringContainsString('<th>Runtime Answers</th>', $html);
        self::assertStringContainsString('<th>Issue Summary</th>', $html);
        self::assertStringContainsString('LEGACY delivery', $html);
        self::assertStringContainsString('delivery miss', $html);
        self::assertStringContainsString('Refresh Delivery Snapshot', $html);
        self::assertStringContainsString('Refresh Runtime Snapshot', $html);
        self::assertStringContainsString('cbt_attempt_session:attempt:501', $html);
        self::assertStringContainsString('cbt_attempt_contract:attempt:501', $html);
        self::assertStringContainsString('cbt_exam_delivery:exam:77:rev:4', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_tab" value="session_runtime_monitor"', $html);
    }

    public function test_render_snapshot_panel_renders_session_runtime_pagination_and_filter_summary(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_SESSION_RUNTIME_MONITOR,
            'student_snapshot_active_filters' => [
                ['label' => 'Cari Siswa', 'value' => 'Salsa'],
                ['label' => 'Kelas', 'value' => 'XI-A'],
            ],
            'student_snapshot_status_options' => [
                ['value' => 'ready', 'label' => 'READY'],
                ['value' => 'session_miss', 'label' => 'SESSION MISS'],
                ['value' => 'contract_miss', 'label' => 'CONTRACT MISS'],
                ['value' => 'runtime_miss', 'label' => 'RUNTIME MISS'],
                ['value' => 'stale', 'label' => 'STALE LAST SEEN'],
                ['value' => 'low_remaining', 'label' => 'LOW REMAINING'],
            ],
            'student_snapshot_filter_state' => [
                'search' => 'Salsa',
                'kelas' => 'XI-A',
                'ruang' => '',
                'status' => '',
                'paged' => 2,
                'per_page' => 25,
            ],
            'exam_snapshot_rows' => [
                array_replace_recursive($this->snapshotPanelArgs()['exam_snapshot_rows'][0], [
                    'session_runtime' => [
                        'attempt_total' => 40,
                        'attempt_total_overall' => 75,
                        'visible_count' => 25,
                        'rows_total' => 40,
                        'rows_total_pages' => 2,
                        'rows_current_page' => 2,
                        'filters_applied' => true,
                    ],
                ]),
            ],
        ]));

        self::assertStringContainsString('40 attempt cocok', $html);
        self::assertStringContainsString('Ringkasan pada kartu ini mengikuti attempt yang tampil pada halaman dan filter aktif', $html);
        self::assertStringContainsString('Halaman 2 dari 2 · 40 attempt', $html);
        self::assertStringContainsString('25 / 40', $html);
        self::assertStringContainsString('Total exam · 75', $html);
    }

    public function test_render_snapshot_panel_renders_exam_monitor_tab(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR,
        ]));

        self::assertStringContainsString('<th>Snapshot Exam</th>', $html);
        self::assertStringContainsString('Status', $html);
        self::assertStringContainsString('Semua status', $html);
        self::assertStringContainsString('! READY', $html);
        self::assertStringContainsString('name="cbt_student_snapshot_status"', $html);
        self::assertStringContainsString('Snapshot ini memuat katalog exam siswa yang tersedia, bukan snapshot satu exam tunggal.', $html);
        self::assertStringContainsString('Siapkan Semua Snapshot Exam', $html);
        self::assertStringContainsString('Bersihkan Semua Snapshot Exam', $html);
        self::assertStringContainsString('Siapkan Snapshot Exam', $html);
        self::assertStringContainsString('Bersihkan Snapshot Exam', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_tab" value="exam_monitor"', $html);
        self::assertStringContainsString('Alasan MISS: Minute rollover', $html);
        self::assertStringContainsString('Snapshot MISS karena minute rollover.', $html);
        self::assertStringContainsString('Version Current:</strong> catalog 5 · user 9', $html);
        self::assertStringContainsString('Minute Current:</strong> 29572561', $html);
        self::assertStringContainsString('Key Terdeteksi:</strong> minute · catalog 5 · user 9 · minute 29572560', $html);
        self::assertStringContainsString('Queue Rewarm Availability', $html);
        self::assertStringContainsString('gunakan Auto-Warm Availability di tab One-Click Pra Ujian.', $html);
    }

    public function test_render_snapshot_panel_shows_exam_monitor_repair_status_notes(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_EXAM_MONITOR,
            'student_snapshot_rows' => [
                [
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'user_login' => 'salsa',
                    'user_email' => 'salsa@example.com',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                    'availability_status_label' => 'QUEUED REWARM',
                    'availability_status_tone' => 'warning',
                    'availability' => [
                        'item_count' => 0,
                        'ttl_seconds' => -2,
                        'payload_bytes' => 0,
                        'snapshot_source' => 'miss',
                        'snapshot_exists' => false,
                        'snapshot_valid' => false,
                        'storage_key' => 'cbt_exam_availability:student:user:71',
                        'snapshot_miss_reason_label' => 'Version berubah',
                        'snapshot_message' => 'Snapshot MISS karena version berubah.',
                        'repair_status' => 'queued_rewarm',
                        'repair_message' => 'MISS karena Version berubah. Siswa ini sudah masuk antrean rewarm.',
                        'repair_queued_at' => '2026-04-08 09:00:00',
                        'repair_source' => 'admin',
                        'current_catalog_version' => 6,
                        'current_user_version' => 10,
                        'current_minute_bucket' => 29592561,
                        'detected_snapshot_source' => 'prepared',
                        'detected_catalog_version' => 5,
                        'detected_user_version' => 9,
                        'detected_minute_bucket' => 0,
                        'preview_items' => [],
                    ],
                    'profile_status_label' => 'READY',
                    'profile_status_tone' => 'success',
                    'profile' => [],
                    'login_status_label' => 'READY',
                    'login_status_tone' => 'success',
                    'login' => [],
                ],
            ],
            'student_snapshot_total' => 1,
        ]));

        self::assertStringContainsString('QUEUED REWARM', $html);
        self::assertStringContainsString('MISS karena Version berubah. Siswa ini sudah masuk antrean rewarm.', $html);
        self::assertStringContainsString('Queued rewarm · Source admin · 2026-04-08 09:00:00', $html);
    }

    public function test_render_snapshot_panel_renders_profile_monitor_tab(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_PROFILE_MONITOR,
            'student_snapshot_status_options' => [
                ['value' => 'ready', 'label' => 'READY'],
                ['value' => 'warning', 'label' => 'WARNING'],
                ['value' => 'miss', 'label' => 'MISS'],
                ['value' => 'invalid', 'label' => 'INVALID'],
                ['value' => 'unavailable', 'label' => 'UNAVAILABLE'],
                ['value' => 'idle', 'label' => 'IDLE'],
                ['value' => '!ready', 'label' => '! READY'],
                ['value' => '!warning', 'label' => '! WARNING'],
                ['value' => '!miss', 'label' => '! MISS'],
                ['value' => '!invalid', 'label' => '! INVALID'],
                ['value' => '!unavailable', 'label' => '! UNAVAILABLE'],
                ['value' => '!idle', 'label' => '! IDLE'],
            ],
        ]));

        self::assertStringContainsString('<th>Snapshot Profile</th>', $html);
        self::assertStringContainsString('Status', $html);
        self::assertStringContainsString('Semua status', $html);
        self::assertStringContainsString('WARNING', $html);
        self::assertStringContainsString('! WARNING', $html);
        self::assertStringContainsString('Siapkan Semua Profil', $html);
        self::assertStringContainsString('Bersihkan Semua Profil', $html);
        self::assertStringContainsString('Siapkan Profil', $html);
        self::assertStringContainsString('Bersihkan Profil', $html);
        self::assertStringContainsString('https://example.com/salsa.jpg', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_tab" value="profile_monitor"', $html);
        self::assertStringNotContainsString('Snapshot ini memuat katalog exam siswa yang tersedia, bukan snapshot satu exam tunggal.', $html);
    }

    public function test_render_snapshot_panel_shows_profile_miss_reason_note(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_PROFILE_MONITOR,
            'student_snapshot_rows' => [
                [
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'user_login' => 'salsa',
                    'user_email' => 'salsa@example.com',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                    'profile_status_label' => 'MISS',
                    'profile_status_tone' => 'warning',
                    'profile' => [
                        'ttl_seconds' => -2,
                        'payload_bytes' => 0,
                        'storage_key' => 'cbt_profile:user:71',
                        'snapshot_exists' => false,
                        'snapshot_valid' => false,
                        'snapshot_miss_reason_label' => 'Meta profil berubah',
                        'snapshot_message' => 'Snapshot profil MISS karena meta profil siswa berubah, jadi key sebelumnya sengaja dihapus dan akan dihydrate ulang pada pembacaan berikutnya.',
                        'preview' => [
                            'foto' => '',
                            'agama' => '',
                            'jenis_kelamin' => '',
                            'nisn' => '',
                        ],
                    ],
                    'availability_status_label' => 'READY',
                    'availability_status_tone' => 'success',
                    'availability' => [],
                    'login_status_label' => 'READY',
                    'login_status_tone' => 'success',
                    'login' => [],
                ],
            ],
            'student_snapshot_total' => 1,
        ]));

        self::assertStringContainsString('Alasan MISS: Meta profil berubah', $html);
        self::assertStringContainsString('Snapshot profil MISS karena meta profil siswa berubah', $html);
    }

    public function test_render_snapshot_panel_shows_profile_auto_heal_note(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_PROFILE_MONITOR,
            'student_snapshot_rows' => [
                [
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'user_login' => 'salsa',
                    'user_email' => 'salsa@example.com',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                    'profile_status_label' => 'READY',
                    'profile_status_tone' => 'success',
                    'profile' => [
                        'ttl_seconds' => 44100,
                        'payload_bytes' => 164,
                        'storage_key' => 'cbt_profile:user:71',
                        'snapshot_exists' => true,
                        'snapshot_valid' => true,
                        'snapshot_message' => 'Snapshot profil siswa siap dipakai untuk live payload.',
                        'repair_status' => 'auto_healed',
                        'repair_message' => 'Dipulihkan otomatis dari usermeta',
                        'preview' => [
                            'foto' => '',
                            'agama' => 'Islam',
                            'jenis_kelamin' => 'Perempuan',
                            'nisn' => '71001',
                        ],
                    ],
                    'availability_status_label' => 'READY',
                    'availability_status_tone' => 'success',
                    'availability' => [],
                    'login_status_label' => 'READY',
                    'login_status_tone' => 'success',
                    'login' => [],
                ],
            ],
            'student_snapshot_total' => 1,
        ]));

        self::assertStringContainsString('Dipulihkan otomatis dari usermeta', $html);
        self::assertStringNotContainsString('Alasan MISS:', $html);
    }

    public function test_render_snapshot_panel_renders_login_monitor_tab(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR,
            'student_snapshot_status_options' => [
                ['value' => 'ready', 'label' => 'READY'],
                ['value' => 'warning', 'label' => 'WARNING'],
                ['value' => 'miss', 'label' => 'MISS'],
                ['value' => 'invalid', 'label' => 'INVALID'],
                ['value' => 'unavailable', 'label' => 'UNAVAILABLE'],
                ['value' => 'idle', 'label' => 'IDLE'],
                ['value' => '!ready', 'label' => '! READY'],
                ['value' => '!warning', 'label' => '! WARNING'],
                ['value' => '!miss', 'label' => '! MISS'],
                ['value' => '!invalid', 'label' => '! INVALID'],
                ['value' => '!unavailable', 'label' => '! UNAVAILABLE'],
                ['value' => '!idle', 'label' => '! IDLE'],
            ],
        ]));

        self::assertStringContainsString('<th>Snapshot Login</th>', $html);
        self::assertStringContainsString('Status', $html);
        self::assertStringContainsString('Semua status', $html);
        self::assertStringContainsString('IDLE', $html);
        self::assertStringContainsString('! IDLE', $html);
        self::assertStringContainsString('auth/login accelerator per siswa', $html);
        self::assertStringContainsString('Siapkan Semua Login Snapshot', $html);
        self::assertStringContainsString('Bersihkan Semua Login Snapshot', $html);
        self::assertStringContainsString('Siapkan Login Snapshot', $html);
        self::assertStringContainsString('Bersihkan Login Snapshot', $html);
        self::assertStringContainsString('Login Snapshot Health', $html);
        self::assertStringContainsString('Hit Rate 15 menit', $html);
        self::assertStringContainsString('Canonical Fallback', $html);
        self::assertStringContainsString('Freshness Window Jobs', $html);
        self::assertStringContainsString('login:salsa', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_tab" value="login_monitor"', $html);
        self::assertStringNotContainsString('Snapshot ini memuat katalog exam siswa yang tersedia, bukan snapshot satu exam tunggal.', $html);
    }

    public function test_render_snapshot_panel_shows_login_miss_reason_note(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR,
            'student_snapshot_rows' => [
                [
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'user_login' => 'salsa',
                    'user_email' => 'salsa@example.com',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                    'profile_status_label' => 'READY',
                    'profile_status_tone' => 'success',
                    'profile' => [],
                    'availability_status_label' => 'READY',
                    'availability_status_tone' => 'success',
                    'availability' => [],
                    'login_status_label' => 'MISS',
                    'login_status_tone' => 'warning',
                    'login' => [
                        'ttl_seconds' => -2,
                        'payload_bytes' => 0,
                        'storage_key' => 'cbt_login_auth:user:71',
                        'snapshot_exists' => false,
                        'snapshot_valid' => false,
                        'snapshot_miss_reason_label' => 'TTL habis / ter-evict',
                        'snapshot_message' => 'Login snapshot MISS karena key sebelumnya kemungkinan sudah expired atau ter-evict. Login berikutnya akan fallback ke auth WordPress lalu mencoba hydrate ulang.',
                        'preview' => [
                            'foto' => '',
                        ],
                    ],
                ],
            ],
            'student_snapshot_total' => 1,
        ]));

        self::assertStringContainsString('Alasan MISS: TTL habis / ter-evict', $html);
        self::assertStringContainsString('Login snapshot MISS karena key sebelumnya kemungkinan sudah expired atau ter-evict', $html);
    }

    public function test_render_snapshot_panel_shows_login_auto_heal_note(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_LOGIN_MONITOR,
            'student_snapshot_rows' => [
                [
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'user_login' => 'salsa',
                    'user_email' => 'salsa@example.com',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                    'profile_status_label' => 'READY',
                    'profile_status_tone' => 'success',
                    'profile' => [],
                    'availability_status_label' => 'READY',
                    'availability_status_tone' => 'success',
                    'availability' => [],
                    'login_status_label' => 'READY',
                    'login_status_tone' => 'success',
                    'login' => [
                        'ttl_seconds' => 43200,
                        'payload_bytes' => 768,
                        'storage_key' => 'cbt_login_auth:user:71',
                        'snapshot_exists' => true,
                        'snapshot_valid' => true,
                        'snapshot_message' => 'Login snapshot siap dipakai untuk login siswa.',
                        'repair_status' => 'auto_healed',
                        'repair_message' => 'Dipulihkan otomatis dari data login canonical',
                        'preview' => [
                            'foto' => '',
                        ],
                    ],
                ],
            ],
            'student_snapshot_total' => 1,
        ]));

        self::assertStringContainsString('Dipulihkan otomatis dari data login canonical', $html);
        self::assertStringNotContainsString('Alasan MISS:', $html);
    }

    public function test_render_snapshot_panel_renders_bulk_preflight_mode_for_multiple_selected_exams(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT,
            'exam_snapshot_filter_state' => [
                'exam_id' => 77,
                'exam_ids' => [77, 54],
            ],
            'exam_snapshot_rows' => [],
            'exam_snapshot_total' => 2,
            'bulk_preflight' => [
                'selected_exam_total' => 2,
                'queued_exam_total' => 1,
                'active_exam_id' => 77,
                'completed_count' => 0,
                'completed_with_warnings_count' => 0,
                'failed_count' => 0,
                'can_start_bulk' => true,
                'limit_max_exams' => 10,
                'rows' => [
                    [
                        'exam_id' => 77,
                        'title' => 'Ujian Matematika',
                        'subject_name' => 'Matematika',
                        'status' => 'published',
                        'queue_position' => 0,
                        'preflight_status_label' => 'AKTIF',
                        'preflight_status_tone' => 'success',
                        'target_student_count' => 18,
                        'last_message' => 'Exam pertama sedang diproses.',
                        'started_at' => '2026-04-04 07:30:00',
                        'finished_at' => '',
                        'last_tick_at' => '2026-04-04 07:31:00',
                        'stage_question_label' => 'READY',
                        'stage_question_tone' => 'success',
                        'stage_start_snapshot_label' => 'READY',
                        'stage_start_snapshot_tone' => 'success',
                        'stage_submission_context_label' => 'READY',
                        'stage_submission_context_tone' => 'success',
                        'stage_profiles_label' => 'AKTIF',
                        'stage_profiles_tone' => 'success',
                        'stage_login_snapshot_label' => 'AKTIF',
                        'stage_login_snapshot_tone' => 'success',
                        'stage_auto_warm_label' => 'AKTIF',
                        'stage_auto_warm_tone' => 'success',
                        'question_stage_summary' => 'Total 8 soal',
                        'start_stage_summary' => 'Total 8 item',
                        'submission_stage_summary' => 'Siap 8/8 · Invalid 0',
                        'profiles_stage_summary' => 'Siap 12/18',
                        'login_stage_summary' => 'Siap 11/18',
                        'availability_stage_summary' => 'Siap 9/18',
                    ],
                    [
                        'exam_id' => 54,
                        'title' => 'Ujian Biologi',
                        'subject_name' => 'Biologi',
                        'status' => 'published',
                        'queue_position' => 1,
                        'preflight_status_label' => 'MENUNGGU',
                        'preflight_status_tone' => 'warning',
                        'target_student_count' => 12,
                        'last_message' => 'Exam kedua masuk antrean preflight.',
                        'started_at' => '2026-04-04 07:32:00',
                        'finished_at' => '',
                        'last_tick_at' => '2026-04-04 07:32:00',
                        'stage_question_label' => 'READY',
                        'stage_question_tone' => 'success',
                        'stage_start_snapshot_label' => 'READY',
                        'stage_start_snapshot_tone' => 'success',
                        'stage_submission_context_label' => 'READY',
                        'stage_submission_context_tone' => 'success',
                        'stage_profiles_label' => 'MENUNGGU',
                        'stage_profiles_tone' => 'warning',
                        'stage_login_snapshot_label' => 'MENUNGGU',
                        'stage_login_snapshot_tone' => 'warning',
                        'stage_auto_warm_label' => 'MENUNGGU',
                        'stage_auto_warm_tone' => 'warning',
                        'question_stage_summary' => 'Total 6 soal',
                        'start_stage_summary' => 'Total 6 item',
                        'submission_stage_summary' => 'Siap 6/6 · Invalid 0',
                        'profiles_stage_summary' => 'Siap 0/12',
                        'login_stage_summary' => 'Siap 0/12',
                        'availability_stage_summary' => 'Siap 0/12',
                    ],
                ],
            ],
        ]));

        self::assertStringContainsString('Bulk One-Click Pra Ujian', $html);
        self::assertStringContainsString('Jalankan Bulk One-Click', $html);
        self::assertStringContainsString('data-cbt-bulk-preflight-form="1"', $html);
        self::assertStringContainsString('Bersihkan Bulk Snapshot', $html);
        self::assertStringContainsString('name="action" value="cbt_clean_bulk_exam_snapshots"', $html);
        self::assertStringContainsString('dijalankan berurutan', $html);
        self::assertStringContainsString('Fokuskan exam ini', $html);
        self::assertStringContainsString('2 exam dipilih', $html);
        self::assertStringContainsString('Dalam Antrean', $html);
        self::assertStringContainsString('Queue', $html);
        self::assertStringContainsString('Exam kedua masuk antrean preflight.', $html);
        self::assertStringContainsString('Mode bulk dibatasi maksimal 10 exam per run.', $html);
        self::assertStringContainsString('Bersihkan Semua Redis CBT', $html);
        self::assertStringContainsString('reset runtime harian antar beberapa exam', $html);
        self::assertStringNotContainsString('Siswa Bermasalah', $html);
        self::assertStringNotContainsString('Bersihkan Semua Snapshot', $html);
    }

    public function test_render_snapshot_panel_shows_empty_exam_monitor_state_before_exam_is_selected(): void
    {
        $html = $this->renderSnapshotPanel($this->snapshotPanelArgs([
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTION_MONITOR,
            'exam_snapshot_filter_state' => [
                'exam_id' => 0,
                'exam_ids' => [],
            ],
            'exam_snapshot_total' => 0,
            'exam_snapshot_rows' => [],
        ]));

        self::assertStringContainsString('Pilih satu atau beberapa exam', $html);
        self::assertStringContainsString('Pilih satu atau beberapa exam pada dropdown di atas untuk memantau snapshot soal.', $html);
        self::assertStringContainsString('disabled="disabled"', $html);
    }

    /**
     * @param array<string,mixed> $args
     */
    private function renderSnapshotPanel(array $args): string
    {
        ob_start();
        \CBT_Admin_Exams_Page::render_snapshot_panel($args);

        return (string) ob_get_clean();
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    private function snapshotPanelArgs(array $overrides = []): array
    {
        $base = [
            'subjects' => [
                ['id' => 3, 'name' => 'Matematika', 'code' => 'MAT'],
            ],
            'exam_status_labels' => [
                'draft' => 'Draft',
                'published' => 'Published',
            ],
            'exam_list_state' => [
                'per_page' => 20,
                'paged' => 1,
                'search' => '',
                'status' => 'published',
                'subject_id' => 3,
                'kelas' => 'XI-A',
            ],
            'exam_list_kelas_options' => ['XI-A'],
            'exam_per_page' => 20,
            'exam_active_filters' => [
                ['label' => 'Status', 'value' => 'Published'],
            ],
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_PREFLIGHT,
            'exam_snapshot_filter_state' => [
                'exam_id' => 77,
                'exam_ids' => [77],
            ],
            'exam_snapshot_exam_options' => [
                ['id' => 77, 'title' => 'Ujian Matematika'],
                ['id' => 54, 'title' => 'Ujian Biologi'],
            ],
            'exam_snapshot_total' => 1,
            'availability_rewarm_queue' => [
                'queued_count' => 3,
                'last_processed_count' => 2,
                'last_success_count' => 1,
                'last_failure_count' => 1,
                'last_skip_count' => 0,
                'last_tick_at' => '2026-04-08 08:45:00',
                'last_message' => 'Queue rewarm memproses 2 user.',
            ],
            'login_readiness_warm_queue_context' => [
                'active' => true,
                'status' => 'active',
                'status_label' => 'RUNNING',
                'status_tone' => 'warning',
                'source' => 'cohort_index',
                'source_label' => 'Cohort Index',
                'filter' => [
                    'kelas' => 'XI-A',
                    'ruang' => 'R1',
                    'exam_id' => 77,
                ],
                'target_count' => 18,
                'processed_count' => 12,
                'ready_count' => 12,
                'failure_count' => 0,
                'pending_count' => 6,
                'last_batch_processed' => 6,
                'progress_percent' => 66.7,
                'progress_label' => '12 / 18 siap',
                'started_at' => '2026-04-08 08:41:00',
                'updated_at' => '2026-04-08 08:46:00',
                'finished_at' => '',
                'last_message' => 'Warm Login Readiness memproses 6 siswa. Siap 12/18.',
                'scheduled' => true,
                'next_run_at' => '2026-04-08 08:47:00',
                'kelas_options' => ['XI-A', 'XI-B'],
                'ruang_options' => ['R1', 'R2'],
                'cohort_summary' => [
                    'available' => true,
                    'ready' => true,
                    'status' => 'ready',
                    'label' => 'Ready',
                ],
            ],
            'exam_snapshot_preview_pages' => [
                77 => 2,
            ],
            'exam_readiness_pages' => [
                77 => 2,
            ],
            'exam_snapshot_reset_url' => 'http://example.com/wp-admin/admin.php?page=cbt-exams&cbt_exam_panel=snapshot',
            'student_snapshot_filter_state' => [
                'search' => 'salsa',
                'kelas' => 'XI-A',
                'ruang' => 'R1',
                'status' => '',
                'paged' => 2,
                'per_page' => 25,
            ],
            'student_snapshot_kelas_options' => ['XI-A', 'XI-B'],
            'student_snapshot_ruang_options' => ['R1', 'R2'],
            'student_snapshot_status_options' => [
                ['value' => 'ready', 'label' => 'READY'],
                ['value' => 'auto_warm', 'label' => 'AUTO-WARM'],
                ['value' => 'queued_rewarm', 'label' => 'QUEUED REWARM'],
                ['value' => 'miss', 'label' => 'MISS'],
                ['value' => 'invalid', 'label' => 'INVALID'],
                ['value' => 'unavailable', 'label' => 'UNAVAILABLE'],
                ['value' => '!ready', 'label' => '! READY'],
                ['value' => '!auto_warm', 'label' => '! AUTO-WARM'],
                ['value' => '!queued_rewarm', 'label' => '! QUEUED REWARM'],
                ['value' => '!miss', 'label' => '! MISS'],
                ['value' => '!invalid', 'label' => '! INVALID'],
                ['value' => '!unavailable', 'label' => '! UNAVAILABLE'],
            ],
            'student_snapshot_total' => 1,
            'student_snapshot_total_pages' => 1,
            'student_snapshot_current_page' => 1,
            'student_snapshot_per_page' => 25,
            'student_snapshot_active_filters' => [
                ['label' => 'Cari Siswa', 'value' => 'salsa'],
                ['label' => 'Kelas', 'value' => 'XI-A'],
                ['label' => 'Ruang', 'value' => 'R1'],
            ],
            'student_snapshot_reset_url' => 'http://example.com/wp-admin/admin.php?page=cbt-exams&cbt_exam_panel=snapshot',
            'adaptive_load_context' => [
                'level' => 'normal',
                'level_label' => 'NORMAL',
                'source' => 'auto',
                'source_label' => 'Auto',
                'primary_reason' => 'Tekanan sistem saat ini normal.',
                'heartbeat_interval_ms' => 20000,
                'heartbeat_interval_label' => '20 detik',
                'admin_snapshot_refresh_seconds' => 10,
                'admin_snapshot_refresh_label' => '10 detik',
                'last_evaluated_at' => '2026-04-08 08:46:00',
                'override_expires_at' => '',
                'tone' => 'success',
            ],
            'student_snapshot_rows' => [
                [
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'user_login' => 'salsa',
                    'user_email' => 'salsa@example.com',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                    'availability_status_label' => 'MISS',
                    'availability_status_tone' => 'warning',
                    'availability' => [
                        'item_count' => 3,
                        'ttl_seconds' => 44100,
                        'payload_bytes' => 1536,
                        'redis_host' => '127.0.0.1',
                        'redis_database' => 2,
                        'snapshot_source' => 'minute',
                        'snapshot_exists' => true,
                        'snapshot_valid' => true,
                        'storage_key' => 'cbt_exam_availability:student:user:71',
                        'snapshot_miss_reason_label' => 'Minute rollover',
                        'snapshot_message' => 'Snapshot MISS karena minute rollover. Bucket menit saat ini sudah berganti, jadi key minute sebelumnya tidak lagi dianggap current.',
                        'current_catalog_version' => 5,
                        'current_user_version' => 9,
                        'current_minute_bucket' => 29572561,
                        'detected_snapshot_source' => 'minute',
                        'detected_catalog_version' => 5,
                        'detected_user_version' => 9,
                        'detected_minute_bucket' => 29572560,
                        'current_user_preview' => [
                            'display_name' => 'Salsa',
                            'kode_kelas' => 'XI-A',
                            'kode_ruang' => 'R1',
                        ],
                        'preview_items' => [
                            ['id' => 77, 'title' => 'Ujian Matematika'],
                            ['id' => 54, 'title' => 'Ujian Biologi'],
                            ['id' => 31, 'title' => 'Ujian Kimia'],
                        ],
                    ],
                    'profile_status_label' => 'READY',
                    'profile_status_tone' => 'success',
                    'profile' => [
                        'ttl_seconds' => 44100,
                        'payload_bytes' => 512,
                        'redis_host' => '127.0.0.1',
                        'redis_database' => 2,
                        'snapshot_exists' => true,
                        'snapshot_valid' => true,
                        'storage_key' => 'cbt_profile:user:71',
                        'snapshot_message' => 'Snapshot profil siswa siap dipakai untuk live payload.',
                        'preview' => [
                            'kode_kelas' => 'XI-A',
                            'kode_ruang' => 'R1',
                            'agama' => 'Islam',
                            'foto' => 'https://example.com/salsa.jpg',
                            'jenis_kelamin' => 'Perempuan',
                            'nisn' => '20260071',
                        ],
                    ],
                    'login_status_label' => 'READY',
                    'login_status_tone' => 'success',
                    'login' => [
                        'ttl_seconds' => 43200,
                        'payload_bytes' => 768,
                        'generated_at' => '2026-04-04 07:35:00',
                        'redis_host' => '127.0.0.1',
                        'redis_database' => 2,
                        'snapshot_source' => 'preflight',
                        'snapshot_exists' => true,
                        'snapshot_valid' => true,
                        'storage_key' => 'cbt_login_auth:user:71',
                        'snapshot_message' => 'Login snapshot siap dipakai untuk login siswa.',
                        'identifiers' => [
                            'login:salsa',
                            'email:salsa@example.com',
                            'fallback:salsa',
                            'nisn:20260071',
                        ],
                        'preview' => [
                            'user_login' => 'salsa',
                            'user_email' => 'salsa@example.com',
                            'role' => 'siswa',
                            'nisn' => '20260071',
                            'kode_kelas' => 'XI-A',
                            'kode_ruang' => 'R1',
                            'agama' => 'Islam',
                            'foto' => 'https://example.com/salsa.jpg',
                            'jenis_kelamin' => 'Perempuan',
                        ],
                    ],
                ],
            ],
            'login_snapshot_health_context' => [
                'available' => true,
                'tone' => 'success',
                'window_minutes' => 15,
                'hit_rate_label' => '75.0%',
                'snapshot_success' => 9,
                'canonical_fallback' => 3,
                'top_miss_reason_label' => 'TTL habis / ter-evict',
                'top_miss_reason_count' => 4,
                'freshness_window_jobs' => 2,
                'freshness_last_tick_at' => '2026-04-08 09:05:00',
                'freshness_last_refreshed_user_count' => 6,
                'freshness_last_refreshed_success_count' => 5,
                'freshness_last_message' => 'Freshness login snapshot memeriksa 2 exam window. Refresh 6 siswa (5 sukses), skip 0 exam.',
                'metrics_redis_error' => '',
            ],
            'exam_snapshot_rows' => [
                [
                    'exam_id' => 77,
                    'title' => 'Ujian Matematika',
                    'subject_name' => 'Matematika',
                    'status' => 'published',
                    'snapshot_status_label' => 'READY',
                    'snapshot_status_tone' => 'success',
                    'snapshot_message' => 'Snapshot Redis siap dipakai.',
                    'start_snapshot_status_label' => 'READY',
                    'start_snapshot_status_tone' => 'success',
                    'start_snapshot_message' => 'Start snapshot Redis siap dipakai.',
                    'submission_context_status_label' => 'READY',
                    'submission_context_status_tone' => 'success',
                    'revision_meta' => [
                        'version' => 4,
                        'invalidated_at' => '2026-04-03 10:00:00',
                        'signature' => 'sig-77',
                    ],
                    'start_snapshot_revision_meta' => [
                        'version' => 4,
                        'invalidated_at' => '2026-04-03 10:00:00',
                        'signature' => 'start-sig-77',
                    ],
                    'snapshot_item_count' => 8,
                    'snapshot_ttl_seconds' => 44100,
                    'snapshot_payload_bytes' => 2048,
                    'start_snapshot_item_count' => 8,
                    'start_snapshot_ttl_seconds' => 44100,
                    'start_snapshot_payload_bytes' => 640,
                    'preview_current_page' => 2,
                    'preview_total_pages' => 2,
                    'preview_per_page' => 7,
                    'preview_is_expanded' => true,
                    'storage_key' => 'cbt_exam_delivery:exam:77:rev:4',
                    'start_snapshot_storage_key' => 'cbt_exam_start_attempt:exam:77:rev:4',
                    'redis_host' => '127.0.0.1',
                    'redis_database' => 2,
                    'start_snapshot_redis_host' => '127.0.0.1',
                    'start_snapshot_redis_database' => 2,
                    'submission_context' => [
                        'question_count' => 8,
                        'ready_count' => 8,
                        'missing_count' => 0,
                        'invalid_count' => 0,
                        'payload_bytes_total' => 1024,
                        'snapshot_exists' => true,
                        'snapshot_valid' => true,
                        'snapshot_status' => 'ready',
                        'snapshot_message' => 'Submission context siap dipakai untuk submit jawaban dan scoring objektif.',
                        'redis_host' => '127.0.0.1',
                        'redis_database' => 2,
                        'preview_items' => [
                            ['question_id' => 901, 'question_type' => 'multiple_choice', 'status' => 'ready', 'payload_bytes' => 128],
                            ['question_id' => 902, 'question_type' => 'short_answer', 'status' => 'ready', 'payload_bytes' => 128],
                        ],
                    ],
                    'preview_question_ids' => [908],
                    'preview_items' => [
                        [
                            'id' => 908,
                            'question_type' => 'multiple_choice',
                            'points' => 5,
                            'question_text_excerpt' => 'Soal preview pertama',
                            'option_count' => 4,
                        ],
                    ],
                    'readiness' => [
                        'overall_label' => 'PERLU PERHATIAN',
                        'overall_tone' => 'warning',
                        'blockers' => [],
                        'warnings' => [
                            '2 siswa target belum memiliki Snapshot Profil READY.',
                            'Auto-Warm Availability belum aktif untuk exam ini.',
                        ],
                        'profile_missing_count' => 2,
                        'availability_ready_count' => 9,
                        'availability_auto_warm_count' => 7,
                        'availability_missing_count' => 2,
                        'token_label' => 'MANUAL',
                        'schedule_label' => 'Belum diatur',
                        'duration_minutes' => 90,
                        'show_student_result' => 1,
                        'enable_calculator' => 1,
                        'problem_total' => 11,
                        'problem_page' => 2,
                        'problem_total_pages' => 2,
                        'problem_students' => [
                            [
                                'display_name' => 'Bimo',
                                'kode_kelas' => 'XI-B',
                                'kode_ruang' => 'R2',
                                'profile_status_label' => 'MISS',
                                'profile_status_tone' => 'warning',
                                'availability_status_label' => 'MISS',
                                'availability_status_tone' => 'warning',
                                'reason' => 'Profil MISS · Availability MISS',
                            ],
                        ],
                    ],
                    'auto_warm' => [
                        'status' => 'active',
                        'status_label' => 'AKTIF',
                        'status_tone' => 'success',
                        'session_id' => 'warm-77-abc',
                        'target_student_count' => 18,
                        'prepared_count' => 12,
                        'cursor' => 12,
                        'batch_size' => 150,
                        'started_at' => '2026-04-04 07:30:00',
                        'stop_after_at' => '2026-04-04 08:00:00',
                        'last_tick_at' => '2026-04-04 07:41:00',
                        'last_success_count' => 8,
                        'last_failure_count' => 1,
                        'last_skip_count' => 3,
                        'last_message' => 'Batch awal auto-warm memproses 9 siswa.',
                        'can_start' => true,
                        'can_stop' => true,
                        'target_kelas' => ['XI-A', 'XI-B'],
                        'redis_available' => true,
                        'blocking_exam_id' => 0,
                        'blocking_exam_title' => '',
                    ],
                    'preflight' => [
                        'status' => 'active',
                        'status_label' => 'AKTIF',
                        'status_tone' => 'success',
                        'session_id' => 'preflight-77-xyz',
                        'target_kelas' => ['XI-A', 'XI-B'],
                        'target_student_count' => 18,
                        'target_source' => 'cohort_index',
                        'target_source_label' => 'Cohort Index',
                        'target_snapshot_created_at' => '2026-04-04 07:31:00',
                        'target_kelas_signature' => 'XI-A|XI-B',
                        'profile_success_count' => 12,
                        'profile_failure_count' => 1,
                        'profile_processed_count' => 13,
                        'profiles_reuse_count' => 4,
                        'profiles_pending_count' => 6,
                        'login_snapshot_success_count' => 11,
                        'login_snapshot_failure_count' => 2,
                        'login_snapshot_ready_count' => 11,
                        'login_snapshot_missing_count' => 7,
                        'login_reuse_count' => 3,
                        'login_pending_count' => 7,
                        'availability_ready_count' => 9,
                        'availability_reuse_count' => 7,
                        'availability_pending_count' => 2,
                        'availability_failure_count' => 0,
                        'submission_context_question_count' => 8,
                        'submission_context_ready_count' => 8,
                        'submission_context_missing_count' => 0,
                        'submission_context_invalid_count' => 0,
                        'started_at' => '2026-04-04 07:31:00',
                        'finished_at' => '',
                        'last_tick_at' => '2026-04-04 07:41:00',
                        'last_message' => 'Batch awal one-click memproses 13 siswa.',
                        'stage_question_label' => 'READY',
                        'stage_question_tone' => 'success',
                        'stage_start_snapshot_label' => 'READY',
                        'stage_start_snapshot_tone' => 'success',
                        'stage_submission_context_label' => 'READY',
                        'stage_submission_context_tone' => 'success',
                        'stage_profiles_label' => 'AKTIF',
                        'stage_profiles_tone' => 'success',
                        'stage_login_snapshot_label' => 'AKTIF',
                        'stage_login_snapshot_tone' => 'success',
                        'stage_auto_warm_label' => 'AKTIF',
                        'stage_auto_warm_tone' => 'success',
                        'can_start' => true,
                        'question_cache_ready' => true,
                        'start_cache_ready' => true,
                        'availability_cache_ready' => true,
                        'profile_cache_ready' => true,
                        'submission_context_cache_ready' => true,
                        'login_snapshot_cache_ready' => true,
                        'rest_warm_ready' => true,
                        'start_warm_ready' => true,
                        'submission_context_warm_ready' => true,
                        'blocking_exam_id' => 0,
                        'blocking_exam_title' => '',
                        'blocking_auto_warm_exam_id' => 0,
                        'blocking_auto_warm_exam_title' => '',
                        'queue_position' => 0,
                        'queue_total' => 1,
                        'global_runner_exam_id' => 77,
                        'global_runner_exam_title' => 'Ujian Matematika',
                        'global_mode_label' => 'PARALEL',
                        'global_batch_size' => 150,
                        'active_global_layer' => 'parallel',
                        'queued_exam_titles' => ['Ujian Biologi'],
                    ],
                    'session_runtime' => [
                        'attempt_total' => 1,
                        'start_gate' => [
                            'status_label' => 'GATED',
                            'status_tone' => 'warning',
                            'queue_depth' => 14,
                            'bucket_tokens' => 3.0,
                            'release_rate_label' => '50 / 5 detik',
                            'oldest_wait_seconds' => 11,
                        ],
                        'redis_first_count' => 0,
                        'legacy_count' => 1,
                        'session_ready_count' => 1,
                        'session_nonready_count' => 0,
                        'contract_ready_count' => 1,
                        'contract_nonready_count' => 0,
                        'runtime_ready_count' => 1,
                        'runtime_missing_count' => 0,
                        'stale_last_seen_count' => 0,
                        'low_remaining_count' => 0,
                        'fallback_breakdown' => [
                            [
                                'label' => 'LEGACY delivery',
                                'count' => 1,
                            ],
                        ],
                        'issue_flags' => [
                            'Delivery snapshot miss',
                        ],
                        'delivery_status_label' => 'MISS',
                        'delivery_status_tone' => 'warning',
                        'delivery_snapshot' => [
                            'storage_key' => 'cbt_exam_delivery:exam:77:rev:4',
                            'snapshot_exists' => false,
                            'snapshot_valid' => false,
                            'snapshot_item_count' => 8,
                        ],
                        'rows' => [
                            [
                                'attempt_id' => 501,
                                'student_id' => 71,
                                'display_name' => 'Salsa',
                                'user_login' => 'salsa',
                                'kode_kelas' => 'XI-A',
                                'kode_ruang' => 'R1',
                                'status' => 'in_progress',
                                'session_status_label' => 'READY',
                                'session_status_tone' => 'success',
                                'session_snapshot' => [
                                    'storage_key' => 'cbt_attempt_session:attempt:501',
                                    'snapshot_exists' => true,
                                    'snapshot_valid' => true,
                                    'question_count' => 8,
                                    'question_order_signature' => 'runtime-sig-501',
                                ],
                                'contract_status_label' => 'READY',
                                'contract_status_tone' => 'success',
                                'contract_snapshot' => [
                                    'storage_key' => 'cbt_attempt_contract:attempt:501',
                                    'snapshot_exists' => true,
                                    'snapshot_valid' => true,
                                    'question_count' => 8,
                                    'question_order_signature' => 'runtime-sig-501',
                                ],
                                'runtime_answers_status_label' => 'READY',
                                'runtime_answers_status_tone' => 'success',
                                'last_seen_at' => '2026-04-04 07:41:09',
                                'last_seen_is_stale' => false,
                                'remaining_label' => '00:42:11',
                                'low_remaining' => false,
                                'fallback_mode' => 'LEGACY delivery',
                                'issue_summary' => 'delivery miss',
                            ],
                        ],
                    ],
                ],
            ],
            'bulk_preflight' => [],
        ];

        $args = array_replace_recursive($base, $overrides);

        foreach (['exam_snapshot_filter_state', 'exam_snapshot_exam_options', 'exam_snapshot_rows', 'student_snapshot_rows', 'student_snapshot_active_filters'] as $replace_key) {
            if (array_key_exists($replace_key, $overrides)) {
                $args[$replace_key] = $overrides[$replace_key];
            }
        }

        return $args;
    }
}
