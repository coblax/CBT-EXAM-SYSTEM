import { describe, expect, it } from 'vitest';
import { createQuestionStateManager } from '../../../src/frontend/app/exam/question-state.js';

function createQuestion(id, type, overrides = {}) {
    return Object.assign({
        id: Number(id) || 0,
        options: [],
        question_type: String(type || 'essay'),
        updated_at: 'rev-1'
    }, overrides || {});
}

function createFixture(overrides = {}) {
    var state = Object.assign({
        answers: {},
        answeredQuestionLookup: {},
        existingAnswerRawByQuestionId: {}
    }, overrides.state || {});
    var questionsById = Object.assign({}, overrides.questionsById || {});
    var manager = createQuestionStateManager({
        getQuestionById: function (questionId) {
            return questionsById[Number(questionId) || 0] || null;
        },
        state
    });

    return {
        manager,
        questionsById,
        state
    };
}

describe('createQuestionStateManager', function () {
    it('normalizes answer payloads consistently across question types', function () {
        var fixture = createFixture({
            questionsById: {
                1: createQuestion(1, 'multiple_choice', {
                    options: [
                        { id: 11, option_key: 'A' },
                        { id: 12, option_key: 'B' }
                    ]
                }),
                2: createQuestion(2, 'multiple_answer', {
                    options: [
                        { id: 21, option_key: 'A' },
                        { id: 22, option_key: 'B' },
                        { id: 23, option_key: 'C' }
                    ]
                }),
                3: createQuestion(3, 'true_false_matrix', {
                    true_false_matrix_meta: {
                        items: [
                            { key: 'R1', text: 'Row 1' },
                            { key: 'R2', text: 'Row 2' }
                        ]
                    }
                }),
                4: createQuestion(4, 'short_answer', {
                    short_answer_meta: {
                        input_keys: ['A', 'B']
                    }
                }),
                5: createQuestion(5, 'essay'),
                6: createQuestion(6, 'ordering', {
                    options: [
                        { id: 61, option_key: 'A' },
                        { id: 62, option_key: 'B' },
                        { id: 63, option_key: 'C' }
                    ]
                })
            },
            state: {
                answers: {
                    1: 12,
                    2: [22, 21, 22, 999],
                    3: {
                        R1: 'true',
                        Z9: 'false'
                    },
                    4: {
                        A: '  alpha  ',
                        B: ' '
                    },
                    5: '  paragraf  ',
                    6: [62, 61, 62, 999, 63]
                },
                answeredQuestionLookup: {
                    1: true,
                    2: true,
                    3: true,
                    4: true,
                    5: true,
                    6: true
                }
            }
        });

        expect(fixture.manager.questionAnswerPayload(fixture.questionsById[1])).toBe(12);
        expect(fixture.manager.questionAnswerPayload(fixture.questionsById[2])).toEqual([22, 21]);
        expect(fixture.manager.questionAnswerPayload(fixture.questionsById[3])).toEqual({
            R1: 'true'
        });
        expect(fixture.manager.questionAnswerPayload(fixture.questionsById[4])).toEqual({
            input_a: 'alpha'
        });
        expect(fixture.manager.questionAnswerPayload(fixture.questionsById[5])).toBe('paragraf');
        expect(fixture.manager.questionAnswerPayload(fixture.questionsById[6])).toEqual([62, 61, 63]);
    });

    it('restores revision-safe answers by option key when option ids change', function () {
        var fixture = createFixture({
            questionsById: {
                10: createQuestion(10, 'multiple_choice', {
                    options: [
                        { id: 101, option_key: 'A' },
                        { id: 102, option_key: 'B' }
                    ]
                })
            },
            state: {
                answers: {
                    10: 102
                },
                answeredQuestionLookup: {
                    10: true
                }
            }
        });

        var preservedAnswers = fixture.manager.captureRevisionSafeLocalAnswers();

        fixture.state.answers = {};
        fixture.state.answeredQuestionLookup = {};
        fixture.questionsById[10] = createQuestion(10, 'multiple_choice', {
            options: [
                { id: 501, option_key: 'A' },
                { id: 777, option_key: 'B' }
            ]
        });

        expect(fixture.manager.restoreRevisionSafeLocalAnswers(preservedAnswers)).toEqual([10]);
        expect(fixture.state.answers[10]).toBe(777);
        expect(fixture.state.answeredQuestionLookup[10]).toBe(true);
    });

    it('defers missing restored answers and reapplies them when the shifted question window loads', function () {
        var fixture = createFixture();
        var preservedAnswers = {
            55: {
                kind: 'short_answer',
                question_updated_at: 'rev-1',
                value: {
                    A: 'catatan'
                }
            }
        };

        expect(fixture.manager.restoreRevisionSafeLocalAnswers(preservedAnswers)).toEqual([]);

        fixture.questionsById[55] = createQuestion(55, 'short_answer', {
            short_answer_meta: {
                input_keys: ['A']
            },
            updated_at: 'rev-1'
        });

        expect(fixture.manager.applyPendingRevisionSafeAnswersForLoadedQuestions([
            fixture.questionsById[55]
        ])).toEqual([55]);
        expect(fixture.state.answers[55]).toEqual({
            A: 'catatan'
        });
        expect(fixture.state.answeredQuestionLookup[55]).toBe(true);
    });
});
