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
