<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class AdminExamsUiHardeningTest extends TestCase
{
    private string $viewSource = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->viewSource = (string) file_get_contents(dirname(__DIR__, 3) . '/admin/views/exams/page.php');
    }

    public function test_exam_snapshot_preview_ignores_stale_pagination_responses(): void
    {
        foreach ([
            'examSnapshotPreviewRequestSeq',
            'const requestSeq = examSnapshotPreviewRequestSeq',
            'requestSeq !== examSnapshotPreviewRequestSeq',
            'requestSeq === examSnapshotPreviewRequestSeq',
            'const liveSummaryRow = document.querySelector',
            'const livePreviewRow = document.querySelector',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }

    public function test_exam_question_catalog_keeps_existing_stale_response_guard(): void
    {
        foreach ([
            'questionCatalogRequestSeq',
            'const requestSeq = questionCatalogRequestSeq',
            'requestSeq !== questionCatalogRequestSeq',
            'requestSeq === questionCatalogRequestSeq',
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }
    }
}
