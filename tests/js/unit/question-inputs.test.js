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
            if (typeof overrides.clearMessages === 'function') {
                overrides.clearMessages(state);
            }
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

    it('moves ordering items and schedules autosave with the ordered option ids', function () {
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
            '<div data-cbt-ordering-list="1" data-qid="71">',
            '<div class="cbt-ordering-item" data-option-id="31"><button type="button" data-action="answer-ordering-move" data-qid="71" data-option-id="31" data-direction="down">Down</button></div>',
            '<div class="cbt-ordering-item" data-option-id="32"></div>',
            '<div class="cbt-ordering-item" data-option-id="33"></div>',
            '</div>'
        ].join('');

        var button = fixture.root.querySelector('[data-action="answer-ordering-move"]');

        expect(fixture.manager.handleClickTarget(button)).toBe(true);
        expect(fixture.state.answers[71]).toEqual([32, 31, 33]);
        expect(fixture.state.answeredQuestionLookup[71]).toBe(true);
        expect(fixture.calls.scheduleQuestionCachePersist).toEqual([200]);
        expect(fixture.calls.scheduleAutoSave).toEqual([
            {
                delayMs: 250,
                questionId: 71
            }
        ]);
        expect(fixture.calls.renderExamPartial[0]).toMatchObject({
            meta: {
                inputType: 'ordering',
                questionId: 71
            },
            reason: 'answer-change'
        });
    });

    it('updates structured dropdown object-map answers without remounting the input region', function () {
        var fixture = createFixture({
            renderExamPartial: function () {
                return true;
            },
            state: {
                answers: {},
                answeredQuestionLookup: {}
            }
        });
        var cases = [
            {
                action: 'answer-matching',
                attr: 'data-matching-key',
                inputType: 'matching',
                key: '1',
                optionId: 701,
                qid: 81
            },
            {
                action: 'answer-cloze-dropdown',
                attr: 'data-cloze-key',
                inputType: 'cloze-dropdown',
                key: '2',
                optionId: 702,
                qid: 82
            },
            {
                action: 'answer-categorization',
                attr: 'data-categorization-key',
                inputType: 'categorization',
                key: '3',
                optionId: 703,
                qid: 83
            }
        ];

        cases.forEach(function (testCase) {
            var select = document.createElement('select');
            select.setAttribute('data-action', testCase.action);
            select.setAttribute('data-qid', String(testCase.qid));
            select.setAttribute(testCase.attr, testCase.key);
            select.innerHTML = '<option value=""></option><option value="' + testCase.optionId + '">Option</option>';
            select.value = String(testCase.optionId);

            fixture.manager.handleChangeTarget(select);

            expect(fixture.state.answers[testCase.qid]).toEqual({
                [testCase.key]: testCase.optionId
            });
            expect(fixture.state.answeredQuestionLookup[testCase.qid]).toBe(true);
            expect(fixture.calls.renderExamPartial.at(-1)).toEqual({
                meta: {
                    inputType: testCase.inputType,
                    questionId: testCase.qid
                },
                reason: 'answer-change',
                regions: {
                    navigation: true,
                    questionFooterProgress: true,
                    questionSaveFeedback: true
                }
            });
        });

        var clearSelect = document.createElement('select');
        clearSelect.setAttribute('data-action', 'answer-matching');
        clearSelect.setAttribute('data-qid', '81');
        clearSelect.setAttribute('data-matching-key', '1');
        clearSelect.innerHTML = '<option value=""></option><option value="701">Option</option>';
        clearSelect.value = '';

        fixture.manager.handleChangeTarget(clearSelect);

        expect(fixture.state.answers[81]).toEqual({});
        expect(Object.prototype.hasOwnProperty.call(fixture.state.answeredQuestionLookup, 81)).toBe(false);
        expect(fixture.calls.scheduleQuestionCachePersist).toEqual([240, 240, 240, 240]);
        expect(fixture.calls.scheduleAutoSave).toEqual([
            {
                delayMs: 250,
                questionId: 81
            },
            {
                delayMs: 250,
                questionId: 82
            },
            {
                delayMs: 250,
                questionId: 83
            },
            {
                delayMs: 250,
                questionId: 81
            }
        ]);
    });

    it('updates table completion mixed cell answers and keeps notice refresh scoped', function () {
        var fixture = createFixture({
            clearMessages: function (state) {
                state.error = '';
                state.notice = '';
                state.success = '';
            },
            renderExamPartial: function () {
                return true;
            },
            state: {
                answers: {},
                answeredQuestionLookup: {},
                error: 'Pesan lama',
                notice: '',
                success: ''
            }
        });
        var dropdown = document.createElement('select');
        dropdown.setAttribute('data-action', 'answer-table-completion-dropdown');
        dropdown.setAttribute('data-qid', '91');
        dropdown.setAttribute('data-table-key', 'b1');
        dropdown.innerHTML = '<option value=""></option><option value="801">Jepang</option>';
        dropdown.value = '801';

        fixture.manager.handleChangeTarget(dropdown);

        expect(fixture.state.answers[91]).toEqual({
            B1: 801
        });
        expect(fixture.state.answeredQuestionLookup[91]).toBe(true);
        expect(fixture.calls.renderExamPartial[0]).toEqual({
            meta: {
                inputType: 'table-completion',
                questionId: 91
            },
            reason: 'answer-change',
            regions: {
                navigation: true,
                notice: true,
                questionFooterProgress: true,
                questionSaveFeedback: true
            }
        });

        var textInput = document.createElement('input');
        textInput.setAttribute('data-action', 'answer-table-completion-text');
        textInput.setAttribute('data-qid', '91');
        textInput.setAttribute('data-table-key', 'a1');
        textInput.value = '  Tokyo  ';

        fixture.manager.handleInputTarget(textInput);

        expect(fixture.state.answers[91]).toEqual({
            A1: '  Tokyo  ',
            B1: 801
        });
        expect(fixture.calls.renderExamPartial[1]).toEqual({
            meta: {
                inputType: 'table-completion',
                questionId: 91
            },
            reason: 'answer-input',
            regions: {
                navigation: true,
                questionFooterProgress: true,
                questionSaveFeedback: true
            }
        });

        dropdown.value = '';
        fixture.manager.handleChangeTarget(dropdown);
        textInput.value = '';
        fixture.manager.handleInputTarget(textInput);

        expect(fixture.state.answers[91]).toEqual({});
        expect(Object.prototype.hasOwnProperty.call(fixture.state.answeredQuestionLookup, 91)).toBe(false);
        expect(fixture.calls.scheduleQuestionCachePersist).toEqual([240, 500, 240, 500]);
        expect(fixture.calls.scheduleAutoSave).toEqual([
            {
                delayMs: 250,
                questionId: 91
            },
            {
                delayMs: 500,
                questionId: 91
            },
            {
                delayMs: 250,
                questionId: 91
            },
            {
                delayMs: 500,
                questionId: 91
            }
        ]);
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
