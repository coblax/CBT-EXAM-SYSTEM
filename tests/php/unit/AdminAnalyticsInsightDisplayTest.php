<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;
use ReflectionMethod;

final class AdminAnalyticsInsightDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once dirname(__DIR__, 3) . '/admin/class-cbt-admin-analytics-service.php';
    }

    public function test_localized_insight_labels_match_expected_indonesian_copy(): void
    {
        self::assertSame('Data Belum Cukup', $this->invokeAnalytics('localize_item_insight_label', ['Insufficient Data']));
        self::assertSame('Menunggu Koreksi Manual', $this->invokeAnalytics('localize_item_insight_label', ['Pending Manual Review']));
        self::assertSame('Daya Beda Terbalik', $this->invokeAnalytics('localize_item_insight_label', ['Inverse Discrimination']));
        self::assertSame('Daya Beda Lemah', $this->invokeAnalytics('localize_item_insight_label', ['Weak Discrimination']));
        self::assertSame('Sering Dikosongkan', $this->invokeAnalytics('localize_item_insight_label', ['High Omission']));
        self::assertSame('Distraktor Menarik', $this->invokeAnalytics('localize_item_insight_label', ['Attractive Distractor']));
        self::assertSame('Distraktor Bermasalah', $this->invokeAnalytics('localize_item_insight_label', ['Distractor Issue']));
        self::assertSame('Stabil', $this->invokeAnalytics('localize_item_insight_label', ['Stable']));
    }

    public function test_insight_reason_detail_uses_live_metrics_and_option_flags(): void
    {
        $inverseReason = (string) $this->invokeAnalytics('build_item_insight_reason_detail', [
            'Inverse Discrimination',
            'multiple_choice',
            40,
            0,
            ['display' => '-0.12', 'label' => 'Inverse'],
            ['value' => 0.0, 'label' => 'Low', 'tone' => 'ok'],
            [],
        ]);
        $omissionReason = (string) $this->invokeAnalytics('build_item_insight_reason_detail', [
            'High Omission',
            'multiple_choice',
            40,
            0,
            ['display' => '0.31', 'label' => 'Good'],
            ['value' => 25.0, 'label' => 'High', 'tone' => 'fail'],
            [],
        ]);
        $manualReason = (string) $this->invokeAnalytics('build_item_insight_reason_detail', [
            'Pending Manual Review',
            'essay',
            12,
            4,
            ['display' => 'Insufficient Data', 'label' => 'Insufficient Data'],
            ['value' => 0.0, 'label' => 'Low', 'tone' => 'ok'],
            [],
        ]);
        $attractiveReason = (string) $this->invokeAnalytics('build_item_insight_reason_detail', [
            'Attractive Distractor',
            'multiple_choice',
            61,
            0,
            ['display' => '0.29', 'label' => 'Fair'],
            ['value' => 0.0, 'label' => 'Low', 'tone' => 'ok'],
            [
                [
                    'label' => 'B',
                    'flags' => ['Attractive Distractor'],
                ],
            ],
        ]);
        $distractorIssueReason = (string) $this->invokeAnalytics('build_item_insight_reason_detail', [
            'Distractor Issue',
            'multiple_choice',
            61,
            0,
            ['display' => '0.29', 'label' => 'Fair'],
            ['value' => 0.0, 'label' => 'Low', 'tone' => 'ok'],
            [
                [
                    'label' => 'C',
                    'flags' => ['Non-Functioning Distractor'],
                ],
            ],
        ]);

        self::assertStringContainsString('-0.12', $inverseReason);
        self::assertStringContainsString('25.00%', $omissionReason);
        self::assertStringContainsString('4 jawaban', $manualReason);
        self::assertStringContainsString('opsi B', $attractiveReason);
        self::assertStringContainsString('opsi C', $distractorIssueReason);
    }

    public function test_item_search_text_keeps_canonical_and_indonesian_terms(): void
    {
        $searchText = (string) $this->invokeAnalytics('build_item_search_text', [
            [
                'question_number' => 1,
                'question_type_label' => 'Multiple Choice',
                'question_preview' => 'Contoh soal',
                'difficulty_label' => 'Mudah',
                'difficulty_short_explainer' => 'Mayoritas peserta menjawab butir ini dengan benar.',
                'discrimination_label' => 'Fair',
                'insight_label' => 'Attractive Distractor',
                'insight_display_label' => 'Distraktor Menarik',
                'insight_short_explainer' => 'Ada opsi salah yang efektif menarik peserta yang lebih lemah.',
                'omission_label' => 'Low',
            ],
            [
                [
                    'label' => 'B',
                    'flags' => ['Attractive Distractor'],
                    'flags_display' => ['Distraktor Menarik'],
                ],
                [
                    'label' => 'C',
                    'flags' => ['Non-Functioning Distractor'],
                    'flags_display' => ['Distraktor Tidak Berfungsi'],
                ],
            ],
        ]);

        self::assertStringContainsString('attractive distractor', $searchText);
        self::assertStringContainsString('distraktor menarik', $searchText);
        self::assertStringContainsString('distraktor tidak berfungsi', $searchText);
    }

    public function test_option_flag_display_is_localized_after_decoration(): void
    {
        $decorated = $this->invokeAnalytics('decorate_item_option_analysis_display', [[
            [
                'label' => 'B',
                'flags' => ['Attractive Distractor', 'Non-Functioning Distractor'],
            ],
        ]]);

        self::assertIsArray($decorated);
        self::assertSame(
            ['Distraktor Menarik', 'Distraktor Tidak Berfungsi'],
            $decorated[0]['flags_display']
        );
    }

    public function test_every_primary_insight_has_non_empty_next_step(): void
    {
        $labels = [
            'Insufficient Data',
            'Pending Manual Review',
            'Inverse Discrimination',
            'Weak Discrimination',
            'High Omission',
            'Attractive Distractor',
            'Distractor Issue',
            'Stable',
        ];

        foreach ($labels as $label) {
            $nextStep = (string) $this->invokeAnalytics('build_item_insight_next_step', [$label]);
            self::assertNotSame('', trim($nextStep), 'Next step should not be empty for insight: ' . $label);
        }
    }

    /**
     * @param array<int,mixed> $arguments
     * @return mixed
     */
    private function invokeAnalytics(string $methodName, array $arguments = [])
    {
        $method = new ReflectionMethod(\CBT_Admin_Analytics_Service::class, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(null, $arguments);
    }
}
