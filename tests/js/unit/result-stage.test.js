import { describe, expect, it } from 'vitest';
import { createResultStageRenderer } from '../../../src/frontend/app/stages/result.js';
import { questionOptionKey } from '../../../src/frontend/app/exam/question-helpers.js';

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function createFixture(overrides = {}) {
    var state = Object.assign({
        archivedReviewItems: [],
        result: {
            attempt: {
                id: 91,
                max_score: 0,
                score: 0,
                status: 'completed'
            },
            exam: {
                id: 9,
                kkm_percentage: 75,
                title: 'Result Fixture',
                total_questions: 0
            },
            percentage: 0,
            result_view_mode: 'full',
            review_items: [],
            review_summary: {
                answered_questions: 0,
                correct_questions: 0,
                manual_questions: 0,
                total_questions: 0,
                unanswered_questions: 0,
                wrong_questions: 0
            },
            score: 0,
            show_student_result: 1,
            submission_summary: {
                answered_questions: 0,
                pending_manual_questions: 0,
                total_questions: 0
            }
        }
    }, overrides.state || {});

    var renderer = createResultStageRenderer({
        escapeHtml,
        formatQuestionType: function (type) {
            return String(type || '').toUpperCase();
        },
        formatScoreValue: function (value) {
            var number = Number(value);
            return Number.isFinite(number) ? String(number % 1 === 0 ? number.toFixed(0) : number.toFixed(2).replace(/0+$/, '').replace(/\.$/, '')) : '0';
        },
        getSelectedExam: function () {
            return overrides.selectedExam || {
                id: 9,
                kkm_percentage: 75,
                title: 'Result Fixture',
                total_questions: 0
            };
        },
        questionOptionKey,
        renderAlert: function () {
            return '';
        },
        safeRichHtml: function (value) {
            return String(value || '');
        },
        state
    });

    return {
        renderer,
        state
    };
}

describe('createResultStageRenderer', function () {
    it('renders an empty breakdown segment when every review count is zero', function () {
        var fixture = createFixture();

        var markup = fixture.renderer.renderResultStage();

        expect(markup).toContain('BELUM ADA JAWABAN');
        expect(markup).toContain('KKM 75');
        expect(markup).toContain('TIDAK LULUS');
    });

    it('renders restricted mode without leaking score or review detail', function () {
        var fixture = createFixture({
            state: {
                result: {
                    exam: {
                        id: 9,
                        title: 'Restricted Fixture'
                    },
                    result_view_mode: 'restricted',
                    review_items: [
                        {
                            id: 1
                        }
                    ],
                    show_student_result: 0,
                    submission_summary: {
                        answered_questions: 8,
                        pending_manual_questions: 0,
                        total_questions: 10
                    }
                }
            }
        });

        var markup = fixture.renderer.renderResultStage();

        expect(markup).toContain('HASIL BELUM DITAMPILKAN');
        expect(markup).toContain('JAWABAN TERSIMPAN : 8 DARI 10 SOAL');
        expect(markup).not.toContain('REVIEW JAWABAN');
        expect(markup).not.toContain('SKOR AKHIR');
    });

    it('shows pending essay messaging without claiming a final pass label incorrectly', function () {
        var fixture = createFixture({
            state: {
                result: {
                    exam: {
                        id: 9,
                        kkm_percentage: 75,
                        title: 'Essay Pending Fixture',
                        total_questions: 4
                    },
                    is_passed: 0,
                    kkm_percentage: 75,
                    max_score: 100,
                    pass_label: 'TIDAK LULUS',
                    percentage: 40,
                    result_tone: 'fail',
                    review_items: [
                        {
                            points: 10,
                            question_number: 3,
                            question_text: 'Esai 1',
                            question_type: 'essay',
                            score_awarded: 0,
                            status: 'manual'
                        },
                        {
                            points: 20,
                            question_number: 4,
                            question_text: 'Esai 2',
                            question_type: 'essay',
                            score_awarded: 0,
                            status: 'manual'
                        }
                    ],
                    review_summary: {
                        answered_questions: 4,
                        correct_questions: 1,
                        manual_questions: 2,
                        total_questions: 4,
                        unanswered_questions: 0,
                        wrong_questions: 1
                    },
                    score: 40,
                    show_student_result: 1,
                    submission_summary: {
                        answered_questions: 4,
                        pending_manual_questions: 2,
                        total_questions: 4
                    }
                }
            }
        });

        var markup = fixture.renderer.renderResultStage();

        expect(markup).toContain('Menunggu Koreksi');
        expect(markup).toContain('2 SOAL ESAI');
        expect(markup).toContain('MAX POIN TAMBAHAN : 30 POIN');
        expect(markup).toContain('Hasil ini masih sementara');
    });

    it('treats graded essay as finalized instead of pending manual review', function () {
        var fixture = createFixture({
            state: {
                result: {
                    exam: {
                        id: 9,
                        kkm_percentage: 75,
                        title: 'Essay Graded Fixture',
                        total_questions: 2
                    },
                    max_score: 10,
                    pass_label: 'LULUS',
                    percentage: 50,
                    review_items: [
                        {
                            points: 5,
                            question_number: 1,
                            question_text: 'Esai 1',
                            question_type: 'essay',
                            score_awarded: 5,
                            status: 'graded'
                        },
                        {
                            points: 5,
                            question_number: 2,
                            question_text: 'Esai 2',
                            question_type: 'essay',
                            score_awarded: 0,
                            status: 'unanswered'
                        }
                    ],
                    review_summary: {
                        answered_questions: 1,
                        correct_questions: 0,
                        graded_questions: 1,
                        manual_questions: 0,
                        total_questions: 2,
                        unanswered_questions: 1,
                        wrong_questions: 0
                    },
                    score: 5,
                    show_student_result: 1,
                    submission_summary: {
                        answered_questions: 1,
                        pending_manual_questions: 0,
                        total_questions: 2
                    }
                }
            }
        });

        var markup = fixture.renderer.renderResultStage();

        expect(markup).not.toContain('Menunggu Koreksi');
        expect(markup).toContain('DINILAI');
    });
});
