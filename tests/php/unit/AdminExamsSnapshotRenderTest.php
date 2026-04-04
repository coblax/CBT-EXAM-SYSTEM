<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

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
