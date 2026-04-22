import { afterEach, describe, expect, it, vi } from 'vitest';
import { createAnswerInputManager } from '../../../src/frontend/app/exam/answer-inputs.js';

function createFixture(overrides = {}) {
    var calls = {
        clearMessages: 0,
        renderExamPartial: [],
        render: [],
        scheduleAutoSave: [],
        scheduleQuestionCachePersist: [],
        updateSelectedExam: []
    };
    var root = document.createElement('div');
    document.body.appendChild(root);
    var state = Object.assign({
        answers: {},
        answeredQuestionLookup: {},
        examToken: '',
        loginIdentifier: '',
        loginPassword: ''
    }, overrides.state || {});

    var manager = createAnswerInputManager({
        autoSaveChoiceDelayMs: 250,
        autoSaveTextDelayMs: 500,
        clearMessages: function () {
            calls.clearMessages += 1;
        },
        documentRef: document,
        normalizeExamToken: overrides.normalizeExamToken || function (value) {
            return String(value || '').trim().toUpperCase();
        },
        render: function (reason, meta) {
            calls.render.push({
                meta: meta || null,
                reason: String(reason || '')
            });
        },
        renderExamPartial: function (regions, reason, meta) {
            calls.renderExamPartial.push({
                meta: meta || null,
                reason: String(reason || ''),
                regions: regions || {}
            });
            if (typeof overrides.renderExamPartial === 'function') {
                return overrides.renderExamPartial(regions, reason, meta);
            }
            return false;
        },
        root,
        scheduleAutoSave: function (questionId, delayMs) {
            calls.scheduleAutoSave.push({
                delayMs: Number(delayMs) || 0,
                questionId: Number(questionId) || 0
            });
        },
        scheduleQuestionCachePersist: function (delayMs) {
            calls.scheduleQuestionCachePersist.push(Number(delayMs) || 0);
        },
        state,
        updateSelectedExam: function (examId) {
            calls.updateSelectedExam.push(String(examId || ''));
        },
        windowRef: window
    });

    return {
        calls,
        manager,
        root,
        state
    };
}

afterEach(function () {
    document.body.innerHTML = '';
});

describe('createAnswerInputManager', function () {
    it('dedupes multiple answer selections and clears answered lookup when the last choice is unchecked', function () {
        var fixture = createFixture({
            state: {
                answers: {
                    41: [501, 501]
                },
                answeredQuestionLookup: {
                    41: true
                }
            }
        });
        var input = document.createElement('input');
        input.type = 'checkbox';
        input.setAttribute('data-action', 'answer-multi');
        input.setAttribute('data-qid', '41');
        input.setAttribute('data-option-id', '501');
        input.checked = true;

        fixture.manager.handleChangeTarget(input);

        expect(fixture.state.answers[41]).toEqual([501]);
        expect(fixture.state.answeredQuestionLookup[41]).toBe(true);
        expect(fixture.calls.scheduleQuestionCachePersist).toEqual([200]);
        expect(fixture.calls.scheduleAutoSave).toEqual([
            {
                delayMs: 250,
                questionId: 41
            }
        ]);

        input.checked = false;
        fixture.manager.handleChangeTarget(input);

        expect(fixture.state.answers[41]).toEqual([]);
        expect(Object.prototype.hasOwnProperty.call(fixture.state.answeredQuestionLookup, 41)).toBe(false);
    });

    it('syncs mirrored short answer inputs without overwriting other question state', function () {
        var fixture = createFixture({
            state: {
                answers: {
                    99: {
                        A: 'keep'
                    }
                },
                answeredQuestionLookup: {
                    99: true
                }
            }
        });

        fixture.root.innerHTML = [
            '<input data-action="answer-short" data-qid="88" data-short-key="A" />',
            '<textarea data-action="answer-short" data-qid="88" data-short-key="A"></textarea>',
            '<input data-action="answer-short" data-qid="99" data-short-key="A" value="keep" />'
        ].join('');

        var primary = fixture.root.querySelector('input[data-qid="88"]');
        var mirror = fixture.root.querySelector('textarea[data-qid="88"]');
        primary.value = '  Alpha ';

        fixture.manager.handleInputTarget(primary);

        expect(fixture.state.answers[88]).toEqual({
            A: '  Alpha '
        });
        expect(fixture.state.answeredQuestionLookup[88]).toBe(true);
        expect(mirror.value).toBe('  Alpha ');
        expect(fixture.state.answers[99]).toEqual({
            A: 'keep'
        });
        expect(fixture.calls.scheduleQuestionCachePersist).toEqual([500]);
        expect(fixture.calls.scheduleAutoSave).toEqual([
            {
                delayMs: 500,
                questionId: 88
            }
        ]);

        primary.value = '';
        fixture.manager.handleInputTarget(primary);

        expect(mirror.value).toBe('');
        expect(fixture.state.answers[88]).toEqual({
            A: ''
        });
        expect(Object.prototype.hasOwnProperty.call(fixture.state.answeredQuestionLookup, 88)).toBe(false);
        expect(fixture.state.answers[99]).toEqual({
            A: 'keep'
        });
    });

    it('updates answer state only for the targeted question input', function () {
        var fixture = createFixture({
            state: {
                answers: {
                    71: 'jawaban lama',
                    72: 'tetap'
                },
                answeredQuestionLookup: {
                    71: true,
                    72: true
                }
            }
        });
        var input = document.createElement('textarea');
        input.setAttribute('data-action', 'answer-text');
        input.setAttribute('data-qid', '71');
        input.value = 'jawaban baru';

        fixture.manager.handleInputTarget(input);

        expect(fixture.state.answers[71]).toBe('jawaban baru');
        expect(fixture.state.answers[72]).toBe('tetap');
        expect(fixture.state.answeredQuestionLookup[71]).toBe(true);
        expect(fixture.state.answeredQuestionLookup[72]).toBe(true);
    });

    it('uses partial question patch for single choice changes without remounting the full question region', function () {
        var fixture = createFixture({
            renderExamPartial: function () {
                return true;
            },
            state: {
                answers: {},
                answeredQuestionLookup: {},
                error: '',
                notice: '',
                success: ''
            }
        });
        fixture.root.innerHTML = [
            '<div data-cbt-exam-question-region="questionHead"><div class="cbt-question-head"></div></div>',
            '<label class="cbt-option"><div class="cbt-option-row"><input type="radio" name="cbt_q_41" data-action="answer-single" data-qid="41" data-option-id="501" checked /><span class="cbt-option-key">A</span><div class="cbt-option-label">Alpha</div></div></label>',
            '<label class="cbt-option is-selected"><div class="cbt-option-row"><input type="radio" name="cbt_q_41" data-action="answer-single" data-qid="41" data-option-id="502" /><span class="cbt-option-key">B</span><div class="cbt-option-label">Beta</div></div></label>'
        ].join('');
        var input = fixture.root.querySelector('input[data-option-id="501"]');

        fixture.manager.handleChangeTarget(input);

        expect(fixture.calls.renderExamPartial).toEqual([
            {
                meta: {
                    inputType: 'single',
                    questionId: 41
                },
                reason: 'answer-change',
                regions: {
                    navigation: true,
                    questionFooterProgress: true,
                    questionSaveFeedback: true
                }
            }
        ]);
        expect(fixture.calls.render).toEqual([]);
        expect(fixture.root.querySelector('input[data-option-id="501"]').closest('.cbt-option').classList.contains('is-selected')).toBe(true);
        expect(fixture.root.querySelector('input[data-option-id="502"]').closest('.cbt-option').classList.contains('is-selected')).toBe(false);
        expect(fixture.root.querySelector('.cbt-question-head').classList.contains('is-answered')).toBe(true);
    });

    it('restores exam shell focus after pointer-based radio selection so arrow shortcuts do not stay trapped in the radio group', function () {
        vi.useFakeTimers();
        try {
            var fixture = createFixture({
                renderExamPartial: function () {
                    return true;
                },
                state: {
                    answers: {},
                    answeredQuestionLookup: {}
                }
            });
            fixture.root.innerHTML = [
                '<div data-cbt-exam-shell="1"><section class="cbt-question-card">',
                '<div data-cbt-exam-question-region="questionHead"><div class="cbt-question-head"></div></div>',
                '<label class="cbt-option"><div class="cbt-option-row"><input type="radio" name="cbt_q_41" data-action="answer-single" data-qid="41" data-option-id="501" /><span class="cbt-option-key">A</span><div class="cbt-option-label">Alpha</div></div></label>',
                '</section></div>'
            ].join('');
            var input = fixture.root.querySelector('input[data-option-id="501"]');
            var optionLabel = fixture.root.querySelector('.cbt-option-label');
            var shell = fixture.root.querySelector('[data-cbt-exam-shell="1"]');

            fixture.manager.handlePointerTarget(optionLabel);
            input.focus();

            fixture.manager.handleChangeTarget(input);
            vi.runAllTimers();

            expect(document.activeElement).toBe(shell);
        } finally {
            vi.useRealTimers();
        }
    });

    it('preserves radio focus for keyboard-origin answer changes', function () {
        vi.useFakeTimers();
        try {
            var fixture = createFixture({
                renderExamPartial: function () {
                    return true;
                },
                state: {
                    answers: {},
                    answeredQuestionLookup: {}
                }
            });
            fixture.root.innerHTML = [
                '<div data-cbt-exam-shell="1"><section class="cbt-question-card">',
                '<div data-cbt-exam-question-region="questionHead"><div class="cbt-question-head"></div></div>',
                '<label class="cbt-option"><div class="cbt-option-row"><input type="radio" name="cbt_q_41" data-action="answer-single" data-qid="41" data-option-id="501" /><span class="cbt-option-key">A</span><div class="cbt-option-label">Alpha</div></div></label>',
                '</section></div>'
            ].join('');
            var input = fixture.root.querySelector('input[data-option-id="501"]');

            input.focus();
            fixture.manager.handleChangeTarget(input);
            vi.runAllTimers();

            expect(document.activeElement).toBe(input);
        } finally {
            vi.useRealTimers();
        }
    });

    it('uses a save-feedback partial patch for text input changes without remounting the input region', function () {
        var fixture = createFixture({
            renderExamPartial: function () {
                return true;
            },
            state: {
                answers: {},
                answeredQuestionLookup: {}
            }
        });
        var input = document.createElement('textarea');
        input.setAttribute('data-action', 'answer-text');
        input.setAttribute('data-qid', '71');
        input.value = 'jawaban esai';

        fixture.manager.handleInputTarget(input);

        expect(fixture.calls.renderExamPartial).toEqual([
            {
                meta: {
                    inputType: 'text',
                    questionId: 71
                },
                reason: 'answer-input',
                regions: {
                    navigation: true,
                    questionFooterProgress: true,
                    questionSaveFeedback: true
                }
            }
        ]);
        expect(fixture.calls.render).toEqual([]);
    });
});
