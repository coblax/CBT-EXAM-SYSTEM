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
                'diagnostic_tags' => [
                    [
                        'key' => 'failed_distractor',
                        'label' => 'Pengecoh Gagal',
                        'message' => 'Opsi salah tidak dipilih peserta.',
                    ],
                ],
                'cognitive_alerts' => [
                    [
                        'key' => 'cognitive_trap',
                        'label' => 'Trap Alert',
                        'message' => 'Kelompok atas terjebak di opsi B.',
                    ],
                ],
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
        self::assertStringContainsString('pengecoh gagal', $searchText);
        self::assertStringContainsString('trap alert', $searchText);
    }

    public function test_smart_diagnostic_tags_are_generated_from_item_metrics(): void
    {
        $payload = $this->invokeAnalytics('build_item_diagnostic_payload', [
            [
                'is_objective' => true,
                'question_type' => 'multiple_choice',
            ],
            12,
            50.0,
            ['value' => -0.21],
            [
                [
                    'label' => 'A',
                    'is_correct' => true,
                    'selection_rate' => 50.0,
                    'upper_rate' => 30.0,
                ],
                [
                    'label' => 'B',
                    'is_correct' => false,
                    'selection_rate' => 0.0,
                    'upper_rate' => 0.0,
                ],
            ],
        ]);

        self::assertIsArray($payload);
        $keys = array_map(static function (array $tag): string {
            return (string) ($tag['key'] ?? '');
        }, $payload['diagnostic_tags']);

        self::assertContains('suspect_key', $keys);
        self::assertContains('failed_distractor', $keys);
    }

    public function test_anchor_item_and_cognitive_trap_are_detected_for_single_answer_items(): void
    {
        $payload = $this->invokeAnalytics('build_item_diagnostic_payload', [
            [
                'is_objective' => true,
                'question_type' => 'multiple_choice',
            ],
            18,
            55.0,
            ['value' => 0.52],
            [
                [
                    'label' => 'A',
                    'is_correct' => true,
                    'selection_rate' => 45.0,
                    'upper_rate' => 20.0,
                ],
                [
                    'label' => 'C',
                    'is_correct' => false,
                    'selection_rate' => 35.0,
                    'upper_rate' => 48.0,
                ],
            ],
        ]);

        $tagKeys = array_map(static function (array $tag): string {
            return (string) ($tag['key'] ?? '');
        }, $payload['diagnostic_tags']);
        $alertKeys = array_map(static function (array $alert): string {
            return (string) ($alert['key'] ?? '');
        }, $payload['cognitive_alerts']);

        self::assertContains('anchor_item', $tagKeys);
        self::assertContains('cognitive_trap', $alertKeys);
    }

    public function test_smart_tags_are_suppressed_when_attempt_sample_is_small(): void
    {
        $payload = $this->invokeAnalytics('build_item_diagnostic_payload', [
            [
                'is_objective' => true,
                'question_type' => 'multiple_choice',
            ],
            9,
            50.0,
            ['value' => -0.3],
            [
                [
                    'label' => 'B',
                    'is_correct' => false,
                    'selection_rate' => 0.0,
                    'upper_rate' => 40.0,
                ],
            ],
        ]);

        self::assertSame([], $payload['diagnostic_tags']);
        self::assertSame([], $payload['cognitive_alerts']);
    }

    public function test_behavioral_quadrant_classifies_all_four_segments(): void
    {
        $payload = $this->invokeAnalytics('build_behavioral_quadrant', [
            [
                ['id' => 1, 'student_name' => 'A', 'student_kelas' => 'X', 'duration_seconds' => 300, 'percentage' => 90.0],
                ['id' => 2, 'student_name' => 'B', 'student_kelas' => 'X', 'duration_seconds' => 900, 'percentage' => 90.0],
                ['id' => 3, 'student_name' => 'C', 'student_kelas' => 'Y', 'duration_seconds' => 300, 'percentage' => 40.0],
                ['id' => 4, 'student_name' => 'D', 'student_kelas' => 'Y', 'duration_seconds' => 900, 'percentage' => 40.0],
            ],
            20,
            75.0,
        ]);

        self::assertSame('ok', $payload['status']);
        self::assertSame(1, $payload['counts']['mastery']);
        self::assertSame(1, $payload['counts']['diligent']);
        self::assertSame(1, $payload['counts']['blind_guessing']);
        self::assertSame(1, $payload['counts']['struggling']);
        self::assertSame(50.0, $payload['duration_median_percent']);
    }

    public function test_benchmark_overlay_selects_default_class_and_delta(): void
    {
        $payload = $this->invokeAnalytics('build_benchmark_overlay', [
            [
                ['student_kelas' => 'X IPA 1', 'percentage' => 90.0],
                ['student_kelas' => 'X IPA 1', 'percentage' => 70.0],
                ['student_kelas' => 'X IPA 2', 'percentage' => 50.0],
                ['student_kelas' => 'X IPA 2', 'percentage' => 30.0],
            ],
            '',
        ]);

        self::assertSame('ok', $payload['status']);
        self::assertSame('X IPA 1', $payload['selected_kelas']);
        self::assertSame(60.0, $payload['global_average']);
        self::assertSame(80.0, $payload['class_average']);
        self::assertSame(20.0, $payload['delta_average']);
        self::assertSame(2, array_sum($payload['class_counts']));
    }

    public function test_predictive_pass_rate_tracks_insufficient_and_trend_projection(): void
    {
        $insufficient = $this->invokeAnalytics('build_predictive_pass_rate', [4, 3, 2]);
        $projected = $this->invokeAnalytics('build_predictive_pass_rate', [10, 6, 5]);

        self::assertSame('insufficient_data', $insufficient['status']);
        self::assertSame(75.0, $insufficient['current_pass_rate']);
        self::assertSame('ok', $projected['status']);
        self::assertSame(60.0, $projected['current_pass_rate']);
        self::assertSame(60.0, $projected['predicted_final_pass_rate']);
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
