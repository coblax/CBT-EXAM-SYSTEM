<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-ui-helper.php';

final class AdminUiHelperRenderTest extends TestCase
{
    public function test_render_empty_state_outputs_compact_component(): void
    {
        $html = \CBT_Admin_UI_Helper::render_empty_state([
            'title' => 'Belum ada exam',
            'message' => 'Buat exam baru atau ubah filter.',
            'action_label' => 'Buat Exam',
            'action_url' => 'https://example.test/wp-admin/admin.php?page=cbt-exams',
            'action_class' => 'button button-primary',
        ]);

        self::assertStringContainsString('cbt-admin-empty-state', $html);
        self::assertStringContainsString('Belum ada exam', $html);
        self::assertStringContainsString('Buat exam baru atau ubah filter.', $html);
        self::assertStringContainsString('button button-primary', $html);
        self::assertStringContainsString('Buat Exam', $html);
    }

    public function test_render_table_empty_state_wraps_component_in_colspan_row(): void
    {
        $html = \CBT_Admin_UI_Helper::render_table_empty_state(7, [
            'title' => 'Belum ada hasil',
            'message' => 'Pilih exam lain atau tunggu siswa mengumpulkan.',
        ]);

        self::assertStringContainsString('<tr class="cbt-admin-empty-row">', $html);
        self::assertStringContainsString('colspan="7"', $html);
        self::assertStringContainsString('cbt-admin-empty-state', $html);
        self::assertStringContainsString('Belum ada hasil', $html);
    }
}
