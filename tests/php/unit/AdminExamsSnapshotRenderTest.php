<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-service.php';
require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-exams-page.php';

final class AdminExamsSnapshotRenderTest extends TestCase
{
    public function test_render_snapshot_panel_outputs_full_exam_details_and_bulk_action(): void
    {
        $html = $this->renderSnapshotPanel([
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
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS,
            'exam_snapshot_filter_state' => [
                'exam_id' => 77,
            ],
            'exam_snapshot_exam_options' => [
                ['id' => 77, 'title' => 'Ujian Matematika'],
                ['id' => 54, 'title' => 'Ujian Biologi'],
            ],
            'exam_snapshot_total' => 1,
            'exam_snapshot_preview_pages' => [
                77 => 2,
            ],
            'exam_snapshot_reset_url' => 'http://example.com/wp-admin/admin.php?page=cbt-exams&cbt_exam_panel=snapshot',
            'student_snapshot_filter_state' => [
                'search' => 'salsa',
                'paged' => 2,
                'per_page' => 25,
            ],
            'student_snapshot_total' => 2,
            'student_snapshot_total_pages' => 2,
            'student_snapshot_current_page' => 2,
            'student_snapshot_per_page' => 25,
            'student_snapshot_active_filters' => [
                ['label' => 'Cari Siswa', 'value' => 'salsa'],
            ],
            'student_snapshot_reset_url' => 'http://example.com/wp-admin/admin.php?page=cbt-exams&cbt_exam_panel=snapshot',
            'student_snapshot_rows' => [
                [
                    'user_id' => 71,
                    'display_name' => 'Salsa',
                    'user_login' => 'salsa',
                    'user_email' => 'salsa@example.com',
                    'kode_kelas' => 'XI-A',
                    'kode_ruang' => 'R1',
                    'availability_status_label' => 'READY',
                    'availability_status_tone' => 'success',
                    'availability' => [
                        'item_count' => 2,
                        'ttl_seconds' => 44100,
                        'payload_bytes' => 1536,
                        'storage_key' => 'cbt_exam_availability:student:user:71',
                        'snapshot_message' => 'Snapshot ketersediaan exam siap dipakai untuk student GET /exams.',
                        'current_user_preview' => [
                            'display_name' => 'Salsa',
                        ],
                        'preview_items' => [
                            ['id' => 77, 'title' => 'Ujian Matematika'],
                            ['id' => 54, 'title' => 'Ujian Biologi'],
                        ],
                    ],
                    'profile_status_label' => 'READY',
                    'profile_status_tone' => 'success',
                    'profile' => [
                        'ttl_seconds' => 44100,
                        'payload_bytes' => 512,
                        'storage_key' => 'cbt_profile:user:71',
                        'snapshot_message' => 'Snapshot profil siswa siap dipakai untuk live payload.',
                        'preview' => [
                            'agama' => 'Islam',
                            'jenis_kelamin' => 'Perempuan',
                            'nisn' => '20260071',
                        ],
                    ],
                ],
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
                    'revision_meta' => [
                        'version' => 4,
                        'invalidated_at' => '2026-04-03 10:00:00',
                        'signature' => 'sig-77',
                    ],
                    'snapshot_item_count' => 8,
                    'snapshot_ttl_seconds' => 44100,
                    'snapshot_payload_bytes' => 2048,
                    'preview_current_page' => 2,
                    'preview_total_pages' => 2,
                    'preview_per_page' => 7,
                    'preview_is_expanded' => true,
                    'storage_key' => 'cbt_exam_delivery:exam:77:rev:4',
                    'redis_host' => '127.0.0.1',
                    'redis_database' => 2,
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
                ],
            ],
        ]);

        self::assertStringContainsString('Snapshot Soal', $html);
        self::assertStringContainsString('cbt-exam-snapshot-subtabs', $html);
        self::assertStringContainsString('cbt_exam_snapshot_tab', $html);
        self::assertStringContainsString('cbt-exam-snapshot-subtab is-active', $html);
        self::assertStringContainsString('Siapkan Semua Snapshot', $html);
        self::assertStringContainsString('Bersihkan Semua Snapshot', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_exam_id"', $html);
        self::assertStringContainsString('Ujian Biologi', $html);
        self::assertStringContainsString('value="77" selected="selected"', $html);
        self::assertStringContainsString('Ringkasan', $html);
        self::assertStringContainsString('Ujian Matematika', $html);
        self::assertStringContainsString('READY', $html);
        self::assertStringContainsString('Jumlah Soal', $html);
        self::assertStringContainsString('Ukuran Payload', $html);
        self::assertStringContainsString('Storage Key:', $html);
        self::assertStringContainsString('Question IDs:', $html);
        self::assertStringContainsString('data-cbt-exam-snapshot-summary-row="77"', $html);
        self::assertStringContainsString('data-cbt-exam-snapshot-preview-row="77"', $html);
        self::assertStringContainsString('<tr class="cbt-exam-snapshot-preview-row" data-cbt-exam-snapshot-preview-row="77">', $html);
        self::assertStringContainsString('colspan="3"', $html);
        self::assertStringContainsString('<details class="cbt-exam-snapshot-preview-dropdown" data-cbt-exam-snapshot-preview-dropdown="77" open="open">', $html);
        self::assertStringContainsString('Preview Soal (8-8 dari 8)', $html);
        self::assertStringContainsString('Hal. 2 / 2', $html);
        self::assertStringContainsString('Halaman 2 dari 2', $html);
        self::assertStringContainsString('Sebelumnya', $html);
        self::assertStringContainsString('Soal preview pertama', $html);
        self::assertStringContainsString('Siapkan Snapshot Soal', $html);
        self::assertStringContainsString('Bersihkan Snapshot Soal', $html);
        self::assertStringContainsString('Snapshot Siswa', $html);
        self::assertStringContainsString('cbt_exam_snapshot_tab" value="students"', $html);
        self::assertStringContainsString('Cari Siswa', $html);
        self::assertStringContainsString('Snapshot Ketersediaan', $html);
        self::assertStringContainsString('Snapshot Profil', $html);
        self::assertStringContainsString('Salsa', $html);
        self::assertStringContainsString('Ujian Biologi', $html);
        self::assertStringContainsString('Siapkan Availability', $html);
        self::assertStringContainsString('Bersihkan Availability', $html);
        self::assertStringContainsString('Siapkan Profil', $html);
        self::assertStringContainsString('Bersihkan Profil', $html);
        self::assertStringContainsString('Siapkan Semua Availability', $html);
        self::assertStringContainsString('Bersihkan Semua Availability', $html);
        self::assertStringContainsString('Siapkan Semua Profil', $html);
        self::assertStringContainsString('Bersihkan Semua Profil', $html);
        self::assertStringContainsString('name="cbt_student_snapshot_q"', $html);
        self::assertStringContainsString('value="salsa"', $html);
        self::assertStringContainsString('name="cbt_student_snapshot_paged" value="2"', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_exam_id" value="77"', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_page_77" value="2"', $html);
        self::assertStringContainsString('Halaman 2 dari 2 · 2 siswa', $html);
    }

    public function test_render_snapshot_panel_shows_lightweight_empty_state_before_exam_is_selected(): void
    {
        $html = $this->renderSnapshotPanel([
            'subjects' => [
                ['id' => 3, 'name' => 'Matematika', 'code' => 'MAT'],
            ],
            'exam_status_labels' => [
                'published' => 'Published',
            ],
            'exam_list_state' => [
                'per_page' => 20,
                'paged' => 1,
                'search' => '',
                'status' => '',
                'subject_id' => 0,
                'kelas' => '',
            ],
            'exam_list_kelas_options' => [],
            'exam_per_page' => 20,
            'exam_active_filters' => [],
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_QUESTIONS,
            'exam_snapshot_filter_state' => [
                'exam_id' => 0,
            ],
            'exam_snapshot_exam_options' => [
                ['id' => 77, 'title' => 'Ujian Matematika'],
            ],
            'exam_snapshot_total' => 0,
            'exam_snapshot_preview_pages' => [],
            'exam_snapshot_reset_url' => 'http://example.com/wp-admin/admin.php?page=cbt-exams&cbt_exam_panel=snapshot',
            'student_snapshot_filter_state' => [
                'search' => '',
                'paged' => 1,
                'per_page' => 25,
            ],
            'student_snapshot_total' => 0,
            'student_snapshot_total_pages' => 1,
            'student_snapshot_current_page' => 1,
            'student_snapshot_per_page' => 25,
            'student_snapshot_active_filters' => [],
            'student_snapshot_reset_url' => 'http://example.com/wp-admin/admin.php?page=cbt-exams&cbt_exam_panel=snapshot',
            'student_snapshot_rows' => [],
            'exam_snapshot_rows' => [],
        ]);

        self::assertStringContainsString('Pilih exam dulu', $html);
        self::assertStringContainsString('Panel snapshot soal baru memuat detail setelah Anda memilih satu exam dari dropdown.', $html);
        self::assertStringContainsString('Pilih satu exam pada dropdown di atas untuk memeriksa snapshot soal.', $html);
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
}
