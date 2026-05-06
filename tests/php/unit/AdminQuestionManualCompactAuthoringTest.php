<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

use CbtExamSystem\Tests\TestCase;

final class AdminQuestionManualCompactAuthoringTest extends TestCase
{
    private string $viewSource = '';
    private string $serviceSource = '';

    protected function setUp(): void
    {
        parent::setUp();

        $root = dirname(__DIR__, 3);
        $this->viewSource = (string) file_get_contents($root . '/admin/views/questions/page.php');
        $this->serviceSource = (string) file_get_contents($root . '/admin/class-cbt-admin-questions-service.php');
    }

    public function test_manual_question_type_tabs_cover_current_question_types(): void
    {
        foreach ([
            'multiple_choice',
            'multiple_answer',
            'true_false',
            'true_false_matrix',
            'short_answer',
            'essay',
            'ordering',
            'matching',
            'cloze_dropdown',
            'categorization',
            'table_completion',
        ] as $questionType) {
            self::assertStringContainsString(
                'data-qtype="' . $questionType . '"',
                $this->viewSource,
                'Manual authoring type picker must expose ' . $questionType . '.'
            );
        }

        self::assertStringContainsString('id="cbt-question-type-tabs"', $this->viewSource);
        self::assertStringContainsString('role="tablist"', $this->viewSource);
    }

    public function test_manual_compact_count_controls_keep_expected_bounds(): void
    {
        $controls = [
            'cbt_mc_option_count' => ['mc_option', 3, 5],
            'cbt_ma_option_count' => ['ma_option', 3, 12],
            'cbt_tfm_statement_count' => ['tfm_statement', 2, 10],
            'cbt_short_answer_input_count' => ['short_answer_input', 1, 8],
            'cbt_ordering_item_count' => ['ordering_item', 2, 12],
            'cbt_matching_pair_count' => ['matching_pair', 2, 12],
            'cbt_cloze_dropdown_count' => ['cloze_dropdown', 1, 8],
            'cbt_cloze_option_count' => ['cloze_option', 2, 6],
            'cbt_cat_category_count' => ['cat_category', 2, 8],
            'cbt_cat_item_count' => ['cat_item', 2, 24],
        ];

        foreach ($controls as $fieldId => [$target, $min, $max]) {
            self::assertStringContainsString('id="' . $fieldId . '" name="' . $fieldId . '"', $this->viewSource);
            self::assertStringContainsString('data-cbt-manual-count data-target="' . $target . '" data-min="' . $min . '" data-max="' . $max . '"', $this->viewSource);
        }

        foreach ([
            'mc_option',
            'ma_option',
            'tfm_statement',
            'short_answer_input',
            'ordering_item',
            'matching_pair',
            'cloze_dropdown',
            'cat_item',
        ] as $warningGroup) {
            self::assertStringContainsString('data-cbt-count-warning="' . $warningGroup . '"', $this->viewSource);
        }

        foreach ([
            'cbt_table_rows' => [2, 8],
            'cbt_table_cols' => [2, 6],
        ] as $fieldId => [$min, $max]) {
            self::assertStringContainsString('id="' . $fieldId . '" name="' . $fieldId . '"', $this->viewSource);
            self::assertStringContainsString('for ($i = ' . $min . '; $i <= ' . $max . '; $i++)', $this->viewSource);
        }
    }

    public function test_manual_submit_uses_active_counts_and_omits_hidden_rows(): void
    {
        foreach ([
            "mcOption: getManualCountValue('cbt_mc_option_count', 5, 3, 5)",
            "maOption: getManualCountValue('cbt_ma_option_count', 5, 3, 12)",
            "tfmStatement: getManualCountValue('cbt_tfm_statement_count', 5, 2, 10)",
            "shortAnswerInput: getManualCountValue('cbt_short_answer_input_count', 3, 1, 8)",
            "orderingItem: getManualCountValue('cbt_ordering_item_count', 4, 2, 12)",
            "matchingPair: getManualCountValue('cbt_matching_pair_count', 3, 2, 12)",
            "clozeDropdown: getManualCountValue('cbt_cloze_dropdown_count', 2, 1, 8)",
            "clozeOption: getManualCountValue('cbt_cloze_option_count', 3, 2, 6)",
            "catCategory: getManualCountValue('cbt_cat_category_count', 2, 2, 8)",
            "catItem: getManualCountValue('cbt_cat_item_count', 3, 2, 24)",
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }

        foreach ([
            'for (let i = 1; i <= manualCounts.mcOption; i += 1)',
            'for (let i = 1; i <= manualCounts.maOption; i += 1)',
            'for (let i = 1; i <= manualCounts.tfmStatement; i += 1)',
            'for (let i = 1; i <= manualCounts.shortAnswerInput; i += 1)',
            'for (let i = 1; i <= manualCounts.orderingItem; i += 1)',
            'for (let i = 1; i <= manualCounts.matchingPair; i += 1)',
            'for (let optionIndex = 1; optionIndex <= manualCounts.clozeOption; optionIndex += 1)',
            'for (let i = 1; i <= manualCounts.catCategory; i += 1)',
            'for (let i = 1; i <= manualCounts.catItem; i += 1)',
            'for (let row = 1; row <= rowCount; row += 1)',
            'for (let col = 1; col <= colCount; col += 1)',
        ] as $loopNeedle) {
            self::assertStringContainsString($loopNeedle, $this->viewSource);
        }

        foreach ([
            'option_count: manualCounts.mcOption',
            'option_count: manualCounts.maOption',
            'statement_count: manualCounts.tfmStatement',
            'input_count: manualCounts.shortAnswerInput',
            'item_count: manualCounts.orderingItem',
            'pair_count: manualCounts.matchingPair',
            'dropdown_count: manualCounts.clozeDropdown',
            'dropdown_option_count: manualCounts.clozeOption',
            'category_count: manualCounts.catCategory',
            'item_count: manualCounts.catItem',
        ] as $metaNeedle) {
            self::assertStringContainsString($metaNeedle, $this->viewSource);
        }
    }

    public function test_backend_detail_readers_clamp_manual_count_fields_server_side(): void
    {
        self::assertStringContainsString('private static function read_manual_count_from_post', $this->serviceSource);

        foreach ([
            "self::read_manual_count_from_post('cbt_matching_pair_count', 12, 2, 12)",
            "self::read_manual_count_from_post('cbt_cloze_dropdown_count', 8, 1, 8)",
            "self::read_manual_count_from_post('cbt_cloze_option_count', 6, 2, 6)",
            "self::read_manual_count_from_post('cbt_cat_category_count', 8, 2, 8)",
            "self::read_manual_count_from_post('cbt_cat_item_count', 24, 2, 24)",
            "self::read_manual_count_from_post('cbt_table_rows', 2, 2, 8)",
            "self::read_manual_count_from_post('cbt_table_cols', 2, 2, 6)",
        ] as $needle) {
            self::assertStringContainsString($needle, $this->serviceSource);
        }
    }

    public function test_import_template_controls_match_current_dynamic_parameters(): void
    {
        foreach ([
            'option_count',
            'input_count',
            'statement_count',
            'item_count',
            'pair_count',
            'dropdown_count',
            'dropdown_option_count',
            'category_count',
            'categorization_item_count',
            'table_rows',
            'table_cols',
        ] as $parameter) {
            self::assertStringContainsString('data-template-control="' . $parameter . '"', $this->viewSource);
            self::assertStringContainsString('data-template-select="' . $parameter . '"', $this->viewSource);
        }

        foreach ([
            'multiple_choice' => 'option_count: { min: 3, max: 5, defaultValue: 5 }',
            'multiple_answer' => 'option_count: { min: 3, max: 12, defaultValue: 5 }',
            'short_answer' => 'input_count: { min: 1, max: 8, defaultValue: 3 }',
            'true_false_matrix' => 'statement_count: { min: 2, max: 10, defaultValue: 5 }',
            'ordering' => 'item_count: { min: 2, max: 12, defaultValue: 4 }',
            'matching' => 'pair_count: { min: 2, max: 12, defaultValue: 3 }',
            'cloze_dropdown' => 'dropdown_option_count: { min: 2, max: 6, defaultValue: 3 }',
            'categorization' => 'categorization_item_count: { min: 2, max: 24, defaultValue: 3 }',
            'table_completion' => 'table_cols: { min: 2, max: 6, defaultValue: 2 }',
        ] as $questionType => $controlNeedle) {
            self::assertStringContainsString($questionType . ': {', $this->viewSource);
            self::assertStringContainsString($controlNeedle, $this->viewSource);
        }
    }

    public function test_import_template_download_url_uses_only_active_type_parameters(): void
    {
        foreach ([
            'const templateParams = {',
            'question_count: selectedCount',
            'const config = templateControls[key];',
            'const isActive = !!config;',
            'control.hidden = !isActive;',
            'select.disabled = !isActive;',
            'if (!isActive) {',
            'return;',
            'templateParams[key] = selectedValue;',
            'Object.keys(templateParams).map((key) => {',
            "wordTemplateButton.setAttribute('href', templateUrl);",
        ] as $needle) {
            self::assertStringContainsString($needle, $this->viewSource);
        }

        $inactiveGuardPosition = strpos($this->viewSource, 'if (!isActive) {');
        $parameterAssignmentPosition = strpos($this->viewSource, 'templateParams[key] = selectedValue;');

        self::assertNotFalse($inactiveGuardPosition);
        self::assertNotFalse($parameterAssignmentPosition);
        self::assertGreaterThan(
            $inactiveGuardPosition,
            $parameterAssignmentPosition,
            'Dynamic template parameters must be appended only after inactive controls return early.'
        );
    }
}
