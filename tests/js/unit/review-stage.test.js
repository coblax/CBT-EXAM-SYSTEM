import { describe, expect, it } from 'vitest';
import { createReviewRenderer } from '../../../src/frontend/app/stages/review.js';

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function createFixture(reviewItems) {
    var state = {
        archivedReviewItems: [],
        result: {
            review_items: reviewItems || []
        }
    };

    return createReviewRenderer({
        escapeHtml,
        formatQuestionType: function (type) {
            return String(type || '').toUpperCase();
        },
        formatScoreValue: function (value) {
            return String(Number(value || 0));
        },
        questionOptionKey: function (_option, index) {
            return String.fromCharCode(65 + Number(index || 0));
        },
        safeRichHtml: function (value) {
            return String(value || '');
        },
        state
    });
}

describe('createReviewRenderer', function () {
    it('renders legacy choice and short answer review items', function () {
        var renderer = createFixture([
            {
                question_number: 1,
                question_text: '<p>Pilih satu</p>',
                question_type: 'multiple_choice',
                points: 2,
                score_awarded: 2,
                status: 'correct',
                options: [
                    { id: 1, option_text: '<p>Benar</p>', is_selected: 1, is_correct: 1 },
                    { id: 2, option_text: '<p>Salah</p>', is_selected: 0, is_correct: 0 }
                ]
            },
            {
                question_number: 2,
                question_text: '<p>Pilih beberapa</p>',
                question_type: 'multiple_answer',
                points: 4,
                score_awarded: 0,
                status: 'wrong',
                options: [
                    { id: 3, option_text: '<p>A</p>', is_selected: 1, is_correct: 1 },
                    { id: 4, option_text: '<p>B</p>', is_selected: 0, is_correct: 1 }
                ]
            },
            {
                question_number: 3,
                question_text: '<p>Pernyataan benar?</p>',
                question_type: 'true_false',
                points: 2,
                score_awarded: 0,
                status: 'wrong',
                options: [
                    { id: 5, option_text: 'Benar', is_selected: 0, is_correct: 1 },
                    { id: 6, option_text: 'Salah', is_selected: 1, is_correct: 0 }
                ]
            },
            {
                question_number: 4,
                question_text: '<p>Kota [INPUT_1]</p>',
                question_type: 'short_answer',
                points: 3,
                score_awarded: 3,
                status: 'correct',
                submitted_short_answers: ['Tokyo'],
                correct_short_answers: ['Tokyo'],
                short_answer_input_keys: ['1']
            }
        ]);

        var markup = renderer.renderResultReviewSection();

        expect(markup).toContain('MULTIPLE_CHOICE');
        expect(markup).toContain('MULTIPLE_ANSWER');
        expect(markup).toContain('TRUE_FALSE');
        expect(markup).toContain('SHORT_ANSWER');
        expect(markup).toContain('Jawaban Anda');
        expect(markup).toContain('Kunci');
        expect(markup).toContain('INPUT_1');
        expect(markup).toContain('Tokyo');
    });

    it('renders rich html for true false matrix statements', function () {
        var renderer = createFixture([
            {
                question_number: 1,
                question_text: '<p>TF Matrix</p>',
                question_type: 'true_false_matrix',
                points: 2,
                score_awarded: 2,
                status: 'correct',
                true_false_matrix_rows: [
                    {
                        text: '<ul><li>Butir 1</li><li>Butir 2</li></ul>',
                        submitted: 'Benar',
                        correct: 'Benar',
                        is_match: 1
                    }
                ]
            }
        ]);

        var markup = renderer.renderResultReviewSection();

        expect(markup).toContain('<ul><li>Butir 1</li><li>Butir 2</li></ul>');
        expect(markup).toContain('Benar');
    });

    it('renders rich html for essay rubric in review section', function () {
        var renderer = createFixture([
            {
                question_number: 2,
                question_text: '<p>Essay</p>',
                question_type: 'essay',
                points: 5,
                score_awarded: 0,
                status: 'manual',
                answer_text: 'Jawaban siswa',
                essay_rubric: '<ol><li>Konsep utama</li><li>Langkah kerja</li></ol>'
            }
        ]);

        var markup = renderer.renderResultReviewSection();

        expect(markup).toContain('<ol><li>Konsep utama</li><li>Langkah kerja</li></ol>');
        expect(markup).toContain('Jawaban siswa');
    });

    it('labels graded essay review items as already reviewed', function () {
        var renderer = createFixture([
            {
                question_number: 4,
                question_text: '<p>Essay graded</p>',
                question_type: 'essay',
                points: 5,
                score_awarded: 3,
                status: 'graded',
                answer_text: 'Jawaban dengan skor parsial',
                essay_rubric: '<p>Rubrik</p>'
            }
        ]);

        var markup = renderer.renderResultReviewSection();

        expect(markup).toContain('Sudah dinilai');
        expect(markup).toContain('Jawaban dengan skor parsial');
    });

    it('renders ordering review rows with submitted and correct order', function () {
        var renderer = createFixture([
            {
                question_number: 5,
                question_text: '<p>Susun prosedur</p>',
                question_type: 'ordering',
                points: 3,
                score_awarded: 0,
                status: 'incorrect',
                ordering_rows: [
                    {
                        position: 1,
                        submitted_text: '<p>Langkah B</p>',
                        correct_text: '<p>Langkah A</p>',
                        is_match: 0
                    },
                    {
                        position: 2,
                        submitted_text: '<p>Langkah A</p>',
                        correct_text: '<p>Langkah B</p>',
                        is_match: 0
                    }
                ]
            }
        ]);

        var markup = renderer.renderResultReviewSection();

        expect(markup).toContain('cbt-ordering-review-table');
        expect(markup).toContain('Jawaban Anda');
        expect(markup).toContain('Kunci');
        expect(markup).toContain('<p>Langkah B</p>');
        expect(markup).toContain('<p>Langkah A</p>');
        expect(markup).toContain('<span class="cbt-review-status is-wrong">Salah</span>');
        expect(markup).toContain('is-mismatch');
    });

    it('renders object-map review rows for matching cloze and categorization', function () {
        var renderer = createFixture([
            {
                question_number: 6,
                question_text: '<p>Pasangkan</p>',
                question_type: 'matching',
                points: 4,
                score_awarded: 2,
                status: 'incorrect',
                matching_rows: [
                    {
                        prompt_text: '<p>Ibu kota Jepang</p>',
                        submitted_text: 'Seoul',
                        correct_text: 'Tokyo',
                        is_match: 0
                    }
                ]
            },
            {
                question_number: 7,
                question_text: '<p>Isi dropdown</p>',
                question_type: 'cloze_dropdown',
                points: 4,
                score_awarded: 4,
                status: 'correct',
                cloze_dropdown_rows: [
                    {
                        key: '1',
                        submitted_text: 'Tokyo',
                        correct_text: 'Tokyo',
                        is_match: 1
                    }
                ]
            },
            {
                question_number: 8,
                question_text: '<p>Kategorikan</p>',
                question_type: 'categorization',
                points: 4,
                score_awarded: 2,
                status: 'incorrect',
                categorization_rows: [
                    {
                        item_text: '<strong>Kucing</strong>',
                        submitted_text: 'Reptil',
                        correct_text: 'Mamalia',
                        is_match: 0
                    }
                ]
            }
        ]);

        var markup = renderer.renderResultReviewSection();

        expect(markup).toContain('<p>Ibu kota Jepang</p>');
        expect(markup).toContain('Seoul');
        expect(markup).toContain('Dropdown 1');
        expect(markup).toContain('Tokyo');
        expect(markup).toContain('<strong>Kucing</strong>');
        expect(markup).toContain('Mamalia');
        expect(markup).toContain('is-match');
        expect(markup).toContain('is-mismatch');
    });

    it('renders table completion review with per-cell status labels', function () {
        var renderer = createFixture([
            {
                question_number: 6,
                question_text: '<p>Lengkapi tabel</p>',
                question_type: 'table_completion',
                points: 4,
                score_awarded: 2,
                status: 'incorrect',
                table_completion_rows: [
                    {
                        key: 'A1',
                        cell_type: 'text',
                        submitted_text: 'Tokyo',
                        correct_text: 'Tokyo',
                        is_match: 1
                    },
                    {
                        key: 'B1',
                        cell_type: 'dropdown',
                        submitted_text: '<script>alert(1)</script>Korea',
                        correct_text: 'Jepang',
                        is_match: 0
                    }
                ]
            }
        ]);

        var markup = renderer.renderResultReviewSection();

        expect(markup).toContain('cbt-table-completion-review-table');
        expect(markup).toContain('<th>Status</th>');
        expect(markup).toContain('data-review-label="Jawaban Anda"');
        expect(markup).toContain('<span>Benar</span>');
        expect(markup).toContain('<span>Salah</span>');
        expect(markup).toContain('<small>dropdown</small>');
        expect(markup).toContain('&lt;script&gt;alert(1)&lt;/script&gt;Korea');
        expect(markup).not.toContain('<script>alert(1)</script>');
    });

    it('preserves cbt math wrappers inside review rich html payloads', function () {
        var renderer = createFixture([
            {
                question_number: 3,
                question_text: '<p><span class="cbt-math" data-cbt-math="x^{2}" data-cbt-math-display="inline">x^(2)</span></p>',
                question_type: 'multiple_choice',
                points: 2,
                score_awarded: 2,
                status: 'correct',
                explanation: '<p><span class="cbt-math" data-cbt-math="\\frac{1}{2}" data-cbt-math-display="inline">(1)/(2)</span></p>',
                options: [
                    {
                        option_text: '<span class="cbt-math" data-cbt-math="\\sqrt{9}" data-cbt-math-display="inline">√(9)</span>',
                        is_correct: 1,
                        is_selected: 1,
                    }
                ]
            }
        ]);

        var markup = renderer.renderResultReviewSection();

        expect(markup).toContain('class="cbt-math"');
        expect(markup).toContain('data-cbt-math="x^{2}"');
        expect(markup).toContain('data-cbt-math="\\frac{1}{2}"');
        expect(markup).toContain('data-cbt-math="\\sqrt{9}"');
    });
});
