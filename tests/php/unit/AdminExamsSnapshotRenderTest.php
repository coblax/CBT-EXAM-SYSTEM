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
                'kelas' => 'XI-A',
                'ruang' => 'R1',
                'paged' => 2,
                'per_page' => 25,
            ],
            'student_snapshot_kelas_options' => ['XI-A', 'XI-B'],
            'student_snapshot_ruang_options' => ['R1', 'R2'],
            'student_snapshot_total' => 2,
            'student_snapshot_total_pages' => 2,
            'student_snapshot_current_page' => 2,
            'student_snapshot_per_page' => 25,
            'student_snapshot_active_filters' => [
                ['label' => 'Cari Siswa', 'value' => 'salsa'],
                ['label' => 'Kelas', 'value' => 'XI-A'],
                ['label' => 'Ruang', 'value' => 'R1'],
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
                    'availability_status_label' => 'AUTO-WARM',
                    'availability_status_tone' => 'success',
                    'availability' => [
                        'item_count' => 3,
                        'ttl_seconds' => 44100,
                        'payload_bytes' => 1536,
                        'redis_host' => '127.0.0.1',
                        'redis_database' => 2,
                        'snapshot_source' => 'prepared',
                        'snapshot_exists' => true,
                        'snapshot_valid' => true,
                        'storage_key' => 'cbt_exam_availability:student:user:71',
                        'snapshot_message' => 'Snapshot ketersediaan exam siap dipakai untuk student GET /exams.',
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
                ],
            ],
            'exam_snapshot_rows' => [
                [
                    'exam_id' => 77,
                    'title' => 'Ujian Matematika',
                    'subject_name' => 'Matematika',
                    'status' => 'published',
                    'target_kelas' => 'XI-A',
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
                    'readiness' => [
                        'overall_label' => 'PERLU PERHATIAN',
                        'overall_tone' => 'warning',
                        'blockers' => [],
                        'warnings' => [
                            '2 siswa target belum memiliki Snapshot Profil READY.',
                            'Auto-Warm Availability belum aktif untuk exam ini.',
                        ],
                        'target_kelas' => ['XI-A', 'XI-B'],
                        'target_student_count' => 18,
                        'profile_ready_count' => 16,
                        'profile_missing_count' => 2,
                        'availability_ready_count' => 9,
                        'availability_auto_warm_count' => 7,
                        'availability_missing_count' => 2,
                        'question_snapshot_ready' => true,
                        'auto_warm_status' => 'active',
                        'token_enabled' => true,
                        'token_frontend_auto_apply' => false,
                        'token_label' => 'MANUAL',
                        'starts_at' => '',
                        'ends_at' => '',
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
                        'batch_size' => 50,
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
                ],
            ],
        ]);

        self::assertStringContainsString('Persiapan Exam', $html);
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
        self::assertStringContainsString('data-cbt-exam-snapshot-readiness-row="77"', $html);
        self::assertStringContainsString('data-cbt-exam-snapshot-preview-row="77"', $html);
        self::assertStringContainsString('<tr class="cbt-exam-snapshot-preview-row" data-cbt-exam-snapshot-preview-row="77">', $html);
        self::assertStringContainsString('<tr class="cbt-exam-snapshot-readiness-row" data-cbt-exam-snapshot-readiness-row="77">', $html);
        self::assertStringContainsString('data-cbt-exam-snapshot-auto-warm-row="77"', $html);
        self::assertStringContainsString('<tr class="cbt-exam-snapshot-auto-warm-row" data-cbt-exam-snapshot-auto-warm-row="77">', $html);
        self::assertStringContainsString('colspan="3"', $html);
        self::assertStringContainsString('Exam Readiness', $html);
        self::assertStringContainsString('PERLU PERHATIAN', $html);
        self::assertStringContainsString('Cek Ulang Readiness', $html);
        self::assertStringContainsString('Siswa Bermasalah', $html);
        self::assertStringContainsString('Halaman 2 dari 2 · 11 siswa', $html);
        self::assertStringContainsString('2 siswa target belum memiliki Snapshot Profil READY.', $html);
        self::assertStringContainsString('Auto-Warm Availability belum aktif untuk exam ini.', $html);
        self::assertStringContainsString('<details class="cbt-exam-snapshot-preview-dropdown" data-cbt-exam-snapshot-preview-dropdown="77" open="open">', $html);
        self::assertStringContainsString('Preview Soal (8-8 dari 8)', $html);
        self::assertStringContainsString('Hal. 2 / 2', $html);
        self::assertStringContainsString('Halaman 2 dari 2', $html);
        self::assertStringContainsString('Sebelumnya', $html);
        self::assertStringContainsString('Soal preview pertama', $html);
        self::assertStringContainsString('Siapkan Snapshot Soal', $html);
        self::assertStringContainsString('Bersihkan Snapshot Soal', $html);
        self::assertStringContainsString('Auto-Warm Availability', $html);
        self::assertStringContainsString('Mulai Auto-Warm Availability', $html);
        self::assertStringContainsString('Hentikan Auto-Warm Availability', $html);
        self::assertStringContainsString('Success / Failure:', $html);
        self::assertStringContainsString('Detail teknis', $html);
        self::assertStringContainsString('Session ID:', $html);
        self::assertStringContainsString('warm-77-abc', $html);
        self::assertStringContainsString('Status Key:', $html);
        self::assertStringContainsString('active', $html);
        self::assertStringContainsString('Target Kelas:', $html);
        self::assertStringContainsString('XI-A, XI-B', $html);
        self::assertStringContainsString('Cursor:', $html);
        self::assertStringContainsString('12', $html);
        self::assertStringContainsString('Batch Size:', $html);
        self::assertStringContainsString('50', $html);
        self::assertStringContainsString('Last Skip:', $html);
        self::assertStringContainsString('3', $html);
        self::assertStringContainsString('Redis Availability:', $html);
        self::assertStringContainsString('Siap', $html);
        self::assertStringContainsString('AKTIF', $html);
        self::assertStringContainsString('Monitoring Siswa', $html);
        self::assertStringContainsString('Monitoring Snapshot Siswa', $html);
        self::assertStringContainsString('Snapshot availability di panel ini adalah katalog exam milik siswa, bukan cache satu exam tunggal.', $html);
        self::assertStringContainsString('cbt_exam_snapshot_tab" value="students"', $html);
        self::assertStringContainsString('Cari Siswa', $html);
        self::assertStringContainsString('Kelas', $html);
        self::assertStringContainsString('Ruang', $html);
        self::assertStringContainsString('Katalog Exam Siswa', $html);
        self::assertStringContainsString('Snapshot Profil', $html);
        self::assertStringContainsString('Salsa', $html);
        self::assertStringContainsString('Ujian Biologi', $html);
        self::assertStringContainsString('Ujian Kimia', $html);
        self::assertStringContainsString('AUTO-WARM', $html);
        self::assertStringContainsString('Snapshot ini memuat katalog exam siswa yang tersedia, bukan snapshot satu exam tunggal.', $html);
        self::assertStringContainsString('+1 lainnya', $html);
        self::assertStringContainsString('Semua exam di snapshot (3)', $html);
        self::assertStringContainsString('Persiapan memakai Auto-Warm Availability dari tab Persiapan Exam', $html);
        self::assertStringContainsString('Bersihkan Availability', $html);
        self::assertStringContainsString('Siapkan Profil', $html);
        self::assertStringContainsString('Bersihkan Profil', $html);
        self::assertStringContainsString('Bersihkan Semua Availability', $html);
        self::assertStringContainsString('Siapkan Semua Profil', $html);
        self::assertStringContainsString('Bersihkan Semua Profil', $html);
        self::assertStringContainsString('menyediakan `Bersihkan Semua Availability` khusus untuk troubleshooting', $html);
        self::assertStringContainsString('name="cbt_student_snapshot_q"', $html);
        self::assertStringContainsString('value="salsa"', $html);
        self::assertStringContainsString('name="cbt_student_snapshot_kelas"', $html);
        self::assertStringContainsString('value="XI-A" selected="selected"', $html);
        self::assertStringContainsString('name="cbt_student_snapshot_ruang"', $html);
        self::assertStringContainsString('value="R1" selected="selected"', $html);
        self::assertStringContainsString('name="cbt_student_snapshot_paged" value="2"', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_exam_id" value="77"', $html);
        self::assertStringContainsString('name="cbt_exam_snapshot_page_77" value="2"', $html);
        self::assertStringContainsString('name="cbt_exam_readiness_paged" value="2"', $html);
        self::assertStringContainsString('Halaman 2 dari 2 · 2 siswa', $html);
        self::assertStringContainsString('class="cbt-student-snapshot-photo"', $html);
        self::assertStringContainsString('https://example.com/salsa.jpg', $html);
        self::assertStringContainsString('Foto URL:', $html);
        self::assertStringContainsString('Snapshot Source:', $html);
        self::assertStringContainsString('Snapshot Exists:', $html);
        self::assertStringContainsString('Snapshot Valid:', $html);
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
                'kelas' => '',
                'ruang' => '',
                'paged' => 1,
                'per_page' => 25,
            ],
            'student_snapshot_kelas_options' => ['XI-A'],
            'student_snapshot_ruang_options' => ['R1'],
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

    public function test_render_snapshot_panel_shows_preparation_hint_for_availability_miss(): void
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
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS,
            'exam_snapshot_filter_state' => [
                'exam_id' => 0,
            ],
            'exam_snapshot_exam_options' => [],
            'exam_snapshot_total' => 0,
            'exam_snapshot_preview_pages' => [],
            'exam_snapshot_reset_url' => 'http://example.com/wp-admin/admin.php?page=cbt-exams&cbt_exam_panel=snapshot',
            'student_snapshot_filter_state' => [
                'search' => '',
                'kelas' => '',
                'ruang' => '',
                'paged' => 1,
                'per_page' => 25,
            ],
            'student_snapshot_kelas_options' => [],
            'student_snapshot_ruang_options' => [],
            'student_snapshot_total' => 1,
            'student_snapshot_total_pages' => 1,
            'student_snapshot_current_page' => 1,
            'student_snapshot_per_page' => 25,
            'student_snapshot_active_filters' => [],
            'student_snapshot_reset_url' => 'http://example.com/wp-admin/admin.php?page=cbt-exams&cbt_exam_panel=snapshot',
            'student_snapshot_rows' => [
                [
                    'user_id' => 88,
                    'display_name' => 'Bimo',
                    'user_login' => 'bimo',
                    'user_email' => 'bimo@example.com',
                    'kode_kelas' => 'XI-B',
                    'kode_ruang' => 'R2',
                    'availability_status_label' => 'MISS',
                    'availability_status_tone' => 'warning',
                    'availability' => [
                        'item_count' => 0,
                        'ttl_seconds' => -2,
                        'payload_bytes' => 0,
                        'snapshot_source' => 'miss',
                        'snapshot_exists' => false,
                        'snapshot_valid' => false,
                        'storage_key' => 'cbt_exam_availability:student:user:88',
                        'snapshot_message' => 'Snapshot belum ada. Request student berikutnya akan hydrate dan menulis ke Redis.',
                        'preview_items' => [],
                    ],
                    'profile_status_label' => 'MISS',
                    'profile_status_tone' => 'warning',
                    'profile' => [
                        'ttl_seconds' => -2,
                        'payload_bytes' => 0,
                        'snapshot_exists' => false,
                        'snapshot_valid' => false,
                        'storage_key' => 'cbt_profile:user:88',
                        'snapshot_message' => 'Snapshot profil belum ada.',
                        'preview' => [],
                    ],
                ],
            ],
            'exam_snapshot_rows' => [],
        ]);

        self::assertStringContainsString('MISS', $html);
        self::assertStringContainsString('Request student berikutnya akan hydrate dan menulis ke Redis.', $html);
        self::assertStringContainsString('Untuk persiapan pra-ujian, gunakan Auto-Warm Availability di tab Persiapan Exam.', $html);
    }

    public function test_render_snapshot_panel_shows_inactive_prepared_hint_for_ready_availability(): void
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
            'exam_snapshot_tab' => \CBT_Admin_Exams_Service::SNAPSHOT_TAB_STUDENTS,
            'exam_snapshot_filter_state' => [
                'exam_id' => 0,
            ],
            'exam_snapshot_exam_options' => [],
            'exam_snapshot_total' => 0,
            'exam_snapshot_preview_pages' => [],
            'exam_snapshot_reset_url' => 'http://example.com/wp-admin/admin.php?page=cbt-exams&cbt_exam_panel=snapshot',
            'student_snapshot_filter_state' => [
                'search' => '',
                'kelas' => '',
                'ruang' => '',
                'paged' => 1,
                'per_page' => 25,
            ],
            'student_snapshot_kelas_options' => [],
            'student_snapshot_ruang_options' => [],
            'student_snapshot_total' => 1,
            'student_snapshot_total_pages' => 1,
            'student_snapshot_current_page' => 1,
            'student_snapshot_per_page' => 25,
            'student_snapshot_active_filters' => [],
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
                        'snapshot_source' => 'prepared',
                        'snapshot_exists' => true,
                        'snapshot_valid' => true,
                        'storage_key' => 'cbt_exam_availability:student:user:71',
                        'snapshot_message' => 'Prepared snapshot availability siap dipakai untuk student GET /exams.',
                        'preview_items' => [
                            ['id' => 77, 'title' => 'Ujian Matematika'],
                        ],
                    ],
                    'profile_status_label' => 'MISS',
                    'profile_status_tone' => 'warning',
                    'profile' => [
                        'ttl_seconds' => -2,
                        'payload_bytes' => 0,
                        'snapshot_exists' => false,
                        'snapshot_valid' => false,
                        'storage_key' => 'cbt_profile:user:71',
                        'snapshot_message' => 'Snapshot profil belum ada.',
                        'preview' => [],
                    ],
                ],
            ],
            'exam_snapshot_rows' => [],
        ]);

        self::assertStringContainsString('READY', $html);
        self::assertStringContainsString('Prepared snapshot availability siap dipakai untuk student GET /exams.', $html);
        self::assertStringContainsString('Prepared snapshot masih tersimpan dari auto-warm sebelumnya.', $html);
        self::assertStringContainsString('Saat ini loop auto-warm tidak aktif', $html);
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
