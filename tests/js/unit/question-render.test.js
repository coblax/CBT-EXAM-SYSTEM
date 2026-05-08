import { describe, expect, it } from 'vitest';
import { createQuestionRenderManager } from '../../../src/frontend/app/exam/question-render.js';

function createManager(overrides) {
    overrides = overrides || {};

    return createQuestionRenderManager({
        escapeHtml: function (value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },
        isExamAnswerEditingLocked: function () {
            return false;
        },
        resolveStoredAnswerValueForQuestion: overrides.resolveStoredAnswerValueForQuestion || function () {
            return null;
        },
        renderExamRichHtml: function (value, options) {
            return '<div class="rendered-' + String(options && options.context || 'question') + '">' + String(value || '') + '<button data-action="open-rich-zoom" type="button">Perbesar</button></div>';
        },
        safeRichHtml: function (value) {
            return String(value || '');
        }
    });
}

describe('createQuestionRenderManager', function () {
    it('renders choice option content inside block containers so rich tables stay valid', function () {
        var manager = createManager();
        var html = manager.renderQuestionInput({
            id: 12,
            question_type: 'multiple_choice',
            options: [
                {
                    id: 101,
                    option_key: 'A',
                    option_text: '<table><tbody><tr><td>Isi</td></tr></tbody></table>'
                }
            ]
        });

        expect(html).toContain('<div class="cbt-option-row">');
        expect(html).toContain('<div class="cbt-option-label"><div class="rendered-option"><table>');
        expect(html).not.toContain('<span class="cbt-option-label"><table>');
    });

    it('uses exam rich renderer for choice options so zoom controls can be injected', function () {
        var manager = createManager();
        var html = manager.renderQuestionInput({
            id: 12,
            question_type: 'multiple_choice',
            options: [
                {
                    id: 101,
                    option_key: 'A',
                    option_text: '<img src="/diagram.png" alt="Diagram" />'
                }
            ]
        });

        expect(html).toContain('rendered-option');
        expect(html).toContain('data-action="open-rich-zoom"');
    });

    it('renders multiple answer with dedicated multi-select affordance', function () {
        var manager = createManager({
            resolveStoredAnswerValueForQuestion: function () {
                return [201];
            }
        });
        var html = manager.renderQuestionInput({
            id: 21,
            question_type: 'multiple_answer',
            options: [
                {
                    id: 201,
                    option_key: 'A',
                    option_text: 'Pilihan A'
                },
                {
                    id: 202,
                    option_key: 'B',
                    option_text: 'Pilihan B'
                }
            ]
        });

        expect(html).toContain('class="cbt-choice-mode cbt-choice-mode--multi"');
        expect(html).toContain('Pilih satu atau lebih jawaban.');
        expect(html).toContain('class="cbt-options cbt-options--multi"');
        expect(html).toContain('class="cbt-option cbt-option--multi is-selected"');
        expect(html).toContain('data-action="answer-multi"');
    });

    it('renders ordering items with rich content and up down controls', function () {
        var manager = createManager();
        var html = manager.renderQuestionInput({
            id: 42,
            question_type: 'ordering',
            options: [
                {
                    id: 301,
                    option_key: 'A',
                    option_text: '<table><tbody><tr><td>Langkah 1</td></tr></tbody></table>'
                },
                {
                    id: 302,
                    option_key: 'B',
                    option_text: '<p>Langkah 2</p>'
                }
            ]
        });

        expect(html).toContain('class="cbt-ordering"');
        expect(html).toContain('data-cbt-ordering-list="1"');
        expect(html).toContain('data-action="answer-ordering-move"');
        expect(html).toContain('data-direction="up"');
        expect(html).toContain('data-direction="down"');
        expect(html).toContain('cbt-ordering-btn-icon-up');
        expect(html).toContain('cbt-ordering-btn-icon-down');
        expect(html).toContain('<div class="cbt-ordering-position">1</div>');
        expect(html).not.toContain('<span class="cbt-option-key">');
        expect(html).toContain('<div class="rendered-option"><table>');
    });

    it('restores selected matching dropdown answers', function () {
        var manager = createManager({
            resolveStoredAnswerValueForQuestion: function () {
                return { 1: 202 };
            }
        });
        var html = manager.renderQuestionInput({
            id: 51,
            question_type: 'matching',
            matching_meta: {
                items: [
                    {
                        key: '1',
                        text: 'Ibukota Jepang'
                    }
                ]
            },
            options: [
                {
                    id: 201,
                    option_key: 'A',
                    option_text: 'Seoul'
                },
                {
                    id: 202,
                    option_key: 'B',
                    option_text: 'Tokyo'
                }
            ]
        });

        expect(html).toContain('data-action="answer-matching"');
        expect(html).toContain('data-action="answer-select-toggle"');
        expect(html).toContain('data-action="answer-select-option"');
        expect(html).toContain('cbt-answer-select-ui');
        expect(html).toContain('data-matching-key="1"');
        expect(html).toContain('class="cbt-matching-row"');
        expect(html).toContain('class="cbt-matching-index">1</span>');
        expect(html).toContain('class="cbt-matching-prompt-text"');
        expect(html).not.toContain('class="cbt-matching-table"');
        expect(html).toContain('<option value="202" selected>Tokyo</option>');
    });

    it('restores selected cloze dropdown answers inside placeholders', function () {
        var manager = createManager({
            resolveStoredAnswerValueForQuestion: function () {
                return { 1: 302 };
            }
        });
        var html = manager.renderQuestionStem({
            id: 52,
            question_type: 'cloze_dropdown',
            question_text: 'Ibu kota Jepang adalah [DROPDOWN_1].',
            cloze_dropdown_meta: {
                blanks: [
                    {
                        key: '1',
                        position: 1,
                        options: [
                            {
                                id: 301,
                                option_key: 'A',
                                option_text: 'Seoul'
                            },
                            {
                                id: 302,
                                option_key: 'B',
                                option_text: 'Tokyo'
                            }
                        ]
                    }
                ]
            }
        });

        expect(html).toContain('data-action="answer-cloze-dropdown"');
        expect(html).toContain('data-action="answer-select-toggle"');
        expect(html).toContain('data-action="answer-select-option"');
        expect(html).toContain('cbt-answer-select-ui');
        expect(html).toContain('data-cloze-key="1"');
        expect(html).toContain('<option value="302" selected>Tokyo</option>');
    });

    it('does not render a separate essay box for cloze dropdown questions', function () {
        var manager = createManager();
        var html = manager.renderQuestionInput({
            id: 520,
            question_type: 'cloze_dropdown',
            question_text: 'Nilai [DROPDOWN_1]',
            cloze_dropdown_meta: {
                blanks: [
                    {
                        key: '1',
                        options: [
                            { id: 301, option_key: 'A', option_text: 'Satu' },
                            { id: 302, option_key: 'B', option_text: 'Dua' }
                        ]
                    }
                ]
            }
        });

        expect(html).toBe('');
        expect(html).not.toContain('data-action="answer-text"');
        expect(html).not.toContain('cbt-textarea');
    });

    it('restores selected categorization dropdown answers', function () {
        var manager = createManager({
            resolveStoredAnswerValueForQuestion: function () {
                return { 1: 502 };
            }
        });
        var html = manager.renderQuestionInput({
            id: 53,
            question_type: 'categorization',
            categorization_meta: {
                items: [
                    {
                        key: '1',
                        text: 'Kucing'
                    }
                ]
            },
            options: [
                {
                    id: 501,
                    option_key: 'A',
                    option_text: 'Reptil'
                },
                {
                    id: 502,
                    option_key: 'B',
                    option_text: 'Mamalia'
                }
            ]
        });

        expect(html).toContain('data-action="answer-categorization"');
        expect(html).toContain('cbt-answer-select-ui');
        expect(html).toContain('data-categorization-key="1"');
        expect(html).toContain('<option value="502" selected>Mamalia</option>');
    });

    it('renders table completion answer cells with compact labels and restored values', function () {
        var manager = createManager({
            resolveStoredAnswerValueForQuestion: function () {
                return { A1: 'Tokyo', B1: 402 };
            }
        });
        var html = manager.renderQuestionInput({
            id: 61,
            question_type: 'table_completion',
            table_completion_meta: {
                rows: 1,
                columns: 2,
                cells: [
                    {
                        key: 'A1',
                        row: 1,
                        column: 1,
                        type: 'text',
                        text: 'Kota'
                    },
                    {
                        key: 'B1',
                        row: 1,
                        column: 2,
                        type: 'dropdown',
                        text: 'Negara',
                        options: [
                            {
                                id: 401,
                                option_text: 'Korea'
                            },
                            {
                                id: 402,
                                option_text: 'Jepang'
                            }
                        ]
                    }
                ]
            }
        });

        expect(html).toContain('cbt-table-completion-cell is-answer is-text');
        expect(html).toContain('cbt-table-completion-cell-head');
        expect(html).toContain('cbt-table-completion-cell-key">A1</span>');
        expect(html).toContain('cbt-table-completion-cell-label');
        expect(html).toContain('cbt-answer-select-ui');
        expect(html).toContain('aria-label="Jawaban sel A1"');
        expect(html).toContain('value="Tokyo"');
        expect(html).toContain('<option value="402" selected>Jepang</option>');
    });

    it('renders all supported question types with their runtime controls', function () {
        var storedAnswers = {
            101: 1001,
            102: [2001],
            103: 3001,
            104: { 1: 'true' },
            105: { A: 'Tokyo' },
            106: 'Esai siswa',
            107: [7002, 7001],
            108: { 1: 8002 },
            109: { 1: 9002 },
            110: { 1: 10002 },
            111: { A1: 'Tokyo', B1: 11002 }
        };
        var manager = createManager({
            resolveStoredAnswerValueForQuestion: function (question) {
                return storedAnswers[Number(question && question.id) || 0] || null;
            }
        });
        var baseOptions = [
            { id: 1001, option_key: 'A', option_text: 'Benar' },
            { id: 1002, option_key: 'B', option_text: 'Salah' }
        ];
        var cases = [
            {
                expected: ['data-action="answer-single"', 'checked'],
                input: {
                    id: 101,
                    question_type: 'multiple_choice',
                    options: baseOptions
                },
                type: 'multiple_choice'
            },
            {
                expected: ['data-action="answer-multi"', 'checked', 'cbt-options--multi', 'cbt-option--multi'],
                input: {
                    id: 102,
                    question_type: 'multiple_answer',
                    options: [
                        { id: 2001, option_key: 'A', option_text: 'Pilihan A' },
                        { id: 2002, option_key: 'B', option_text: 'Pilihan B' }
                    ]
                },
                type: 'multiple_answer'
            },
            {
                expected: ['data-action="answer-single"', 'checked'],
                input: {
                    id: 103,
                    question_type: 'true_false',
                    options: [
                        { id: 3001, option_key: 'true', option_text: 'Benar' },
                        { id: 3002, option_key: 'false', option_text: 'Salah' }
                    ]
                },
                type: 'true_false'
            },
            {
                expected: ['data-action="answer-tf-matrix"', 'data-key="1"', 'rendered-question'],
                input: {
                    id: 104,
                    question_type: 'true_false_matrix',
                    true_false_matrix_meta: {
                        items: [{ key: '1', text: '<table><tbody><tr><td>Pernyataan</td></tr></tbody></table>' }]
                    }
                },
                type: 'true_false_matrix'
            },
            {
                expected: ['data-action="answer-short"', 'data-short-key="A"', 'value="Tokyo"'],
                input: {
                    id: 105,
                    question_text: 'Kota [INPUT_A]',
                    question_type: 'short_answer',
                    short_answer_meta: {
                        input_keys: ['A']
                    }
                },
                render: 'stem',
                type: 'short_answer'
            },
            {
                expected: ['data-action="answer-text"', 'Esai siswa'],
                input: {
                    id: 106,
                    question_type: 'essay'
                },
                type: 'essay'
            },
            {
                expected: ['data-action="answer-ordering-move"', 'data-option-id="7002"', 'data-option-id="7001"'],
                input: {
                    id: 107,
                    question_type: 'ordering',
                    options: [
                        { id: 7001, option_key: 'A', option_text: 'Langkah 1' },
                        { id: 7002, option_key: 'B', option_text: 'Langkah 2' }
                    ]
                },
                type: 'ordering'
            },
            {
                expected: ['data-action="answer-matching"', 'data-matching-key="1"', '<option value="8002" selected>Tokyo</option>'],
                input: {
                    id: 108,
                    question_type: 'matching',
                    matching_meta: {
                        items: [{ key: '1', text: 'Ibukota Jepang' }]
                    },
                    options: [
                        { id: 8001, option_key: 'A', option_text: 'Seoul' },
                        { id: 8002, option_key: 'B', option_text: 'Tokyo' }
                    ]
                },
                type: 'matching'
            },
            {
                expected: ['data-action="answer-cloze-dropdown"', 'data-cloze-key="1"', '<option value="9002" selected>Tokyo</option>'],
                input: {
                    id: 109,
                    question_text: 'Kota [DROPDOWN_1]',
                    question_type: 'cloze_dropdown',
                    cloze_dropdown_meta: {
                        blanks: [
                            {
                                key: '1',
                                options: [
                                    { id: 9001, option_key: 'A', option_text: 'Seoul' },
                                    { id: 9002, option_key: 'B', option_text: 'Tokyo' }
                                ]
                            }
                        ]
                    }
                },
                render: 'stem',
                type: 'cloze_dropdown'
            },
            {
                expected: ['data-action="answer-categorization"', 'data-categorization-key="1"', '<option value="10002" selected>Mamalia</option>'],
                input: {
                    id: 110,
                    question_type: 'categorization',
                    categorization_meta: {
                        items: [{ key: '1', text: 'Kucing' }]
                    },
                    options: [
                        { id: 10001, option_key: 'A', option_text: 'Reptil' },
                        { id: 10002, option_key: 'B', option_text: 'Mamalia' }
                    ]
                },
                type: 'categorization'
            },
            {
                expected: ['data-action="answer-table-completion-text"', 'data-action="answer-table-completion-dropdown"', 'value="Tokyo"', '<option value="11002" selected>Jepang</option>'],
                input: {
                    id: 111,
                    question_type: 'table_completion',
                    table_completion_meta: {
                        rows: 1,
                        columns: 2,
                        cells: [
                            { key: 'A1', row: 1, column: 1, type: 'text', text: 'Kota' },
                            {
                                key: 'B1',
                                row: 1,
                                column: 2,
                                type: 'dropdown',
                                text: 'Negara',
                                options: [
                                    { id: 11001, option_key: 'A', option_text: 'Korea' },
                                    { id: 11002, option_key: 'B', option_text: 'Jepang' }
                                ]
                            }
                        ]
                    }
                },
                type: 'table_completion'
            }
        ];

        cases.forEach(function (testCase) {
            var html = testCase.render === 'stem'
                ? manager.renderQuestionStem(testCase.input)
                : manager.renderQuestionInput(testCase.input);

            testCase.expected.forEach(function (fragment) {
                expect(html, testCase.type + ' should render ' + fragment).toContain(fragment);
            });
        });
    });
});
