import { afterEach, describe, expect, it, vi } from 'vitest';
import { createExamNavigationManager } from '../../../src/frontend/app/exam/navigation.js';
import { createAnswerInputManager } from '../../../src/frontend/app/exam/answer-inputs.js';
import {
    getCategorizationItems,
    getClozeDropdownBlanks,
    getMatchingItems,
    getShortAnswerKeys,
    getTableCompletionCells,
    getTrueFalseMatrixItems,
    normalizeDropdownOptionAnswer,
    normalizeTableCompletionAnswer,
    normalizeTrueFalseMatrixAnswer,
    questionOptionKey
} from '../../../src/frontend/app/exam/question-helpers.js';

function createQuestion(id, type, overrides = {}) {
    return Object.assign({
        id: Number(id) || 0,
        options: [],
        question_number: Number(overrides.question_number || 0) || 0,
        question_type: String(type || 'essay')
    }, overrides || {});
}

function createFixture(overrides = {}) {
    var calls = {
        acknowledgeQuestionRevisionMarker: [],
        clearMessages: 0,
        ensureQuestionWindowForIndex: [],
        operationOrder: [],
        persistCurrentAttemptUiStateLocally: 0,
        prefetchNextQuestionBatch: 0,
        prefetchNextQuestionWindow: 0,
        queueQuestionAnswer: [],
        render: [],
        renderExamPartial: [],
        resetQuestionPrefetchIdleTimer: 0,
        scheduleAttemptUiStateSync: [],
        schedulePendingAnswerRetry: [],
        scheduleQuestionCachePersist: [],
        setActiveQuestionWindowForIndex: []
    };
    var questionLookup = Object.assign({
        101: createQuestion(101, 'multiple_choice', {
            options: [
                { id: 11, option_key: 'A' },
                { id: 12, option_key: 'B' }
            ],
            question_number: 1
        }),
        102: createQuestion(102, 'short_answer', {
            question_number: 2,
            short_answer_meta: {
                input_keys: ['A']
            }
        }),
        103: createQuestion(103, 'multiple_answer', {
            options: [
                { id: 31, option_key: 'A' },
                { id: 32, option_key: 'B' }
            ],
            question_number: 3
        })
    }, overrides.questionLookup || {});
    var questionOrderIds = Array.isArray(overrides.questionOrderIds) ? overrides.questionOrderIds.slice() : [101, 102, 103];
    var loadedQuestionIds = new Set(Array.isArray(overrides.loadedQuestionIds) ? overrides.loadedQuestionIds : questionOrderIds);
    var navigatorStatus = String(overrides.navigatorStatus || 'online');
    var state = Object.assign({
        answers: {
            101: 12,
            102: { A: 'jawaban' }
        },
        answeredQuestionLookup: {
            101: true,
            102: true
        },
        attemptId: 55,
        busy: false,
        currentIndex: 0,
        doubtful: {},
        error: '',
        finishConfirmOpen: false,
        isFinishing: false,
        navQuestionFilter: 'all',
        notice: '',
        questionOrderIds: questionOrderIds,
        questionRegionRefreshing: false,
        questions: questionOrderIds.map(function (questionId) {
            return questionLookup[questionId];
        }),
        stage: 'exam',
        userPhotoModalOpen: false
    }, overrides.state || {});
    var root = document.createElement('div');
    document.body.appendChild(root);

    var navigationManager = createExamNavigationManager({
        acknowledgeQuestionRevisionMarker: function (questionId) {
            calls.acknowledgeQuestionRevisionMarker.push(Number(questionId) || 0);
            return false;
        },
        attemptUiStateNavigationSyncDelayMs: 150,
        attemptUiStateSyncDelayMs: 300,
        clampQuestionIndex: function (index) {
            var maxIndex = Math.max(0, questionOrderIds.length - 1);
            return Math.max(0, Math.min(maxIndex, Math.floor(Number(index) || 0)));
        },
        clearStickyQuestionRevisionNotice: function () {
            return false;
        },
        clearMessages: function () {
            calls.clearMessages += 1;
            calls.operationOrder.push('clearMessages');
        },
        documentRef: document,
        ensureQuestionWindowForIndex: async function (index) {
            var safeIndex = Math.max(0, Math.min(questionOrderIds.length - 1, Math.floor(Number(index) || 0)));
            var questionId = questionOrderIds[safeIndex];
            loadedQuestionIds.add(questionId);
            calls.ensureQuestionWindowForIndex.push(safeIndex);
            calls.operationOrder.push('ensureQuestionWindowForIndex:' + String(safeIndex));
            return questionLookup[questionId] || null;
        },
        escapeHtml: function (value) {
            return String(value || '');
        },
        getNavigatorConnectionStatus: function () {
            return navigatorStatus;
        },
        getQuestionAtIndex: function (index) {
            var questionId = questionOrderIds[Math.max(0, Math.min(questionOrderIds.length - 1, Math.floor(Number(index) || 0)))] || 0;
            return questionLookup[questionId] || null;
        },
        getQuestionById: function (questionId) {
            return questionLookup[Number(questionId) || 0] || null;
        },
        getQuestionCount: function () {
            return questionOrderIds.length;
        },
        getQuestionDisplayNumber: function (question, fallbackIndex) {
            var questionNumber = Number(question && question.question_number !== undefined ? question.question_number : 0) || 0;
            return questionNumber > 0 ? questionNumber : Math.max(1, Number(fallbackIndex) + 1);
        },
        getQuestionIdAtIndex: function (index) {
            return Number(questionOrderIds[Math.max(0, Math.min(questionOrderIds.length - 1, Math.floor(Number(index) || 0)))]) || 0;
        },
        getCategorizationItems: getCategorizationItems,
        getClozeDropdownBlanks: getClozeDropdownBlanks,
        getMatchingItems: getMatchingItems,
        getPendingSyncQuestionIds: overrides.getPendingSyncQuestionIds || function () {
            return [];
        },
        getShortAnswerKeys: getShortAnswerKeys,
        getTableCompletionCells: getTableCompletionCells,
        getTrueFalseMatrixItems: getTrueFalseMatrixItems,
        hasUsableLocalAnswerForQuestion: function (questionId) {
            return Object.prototype.hasOwnProperty.call(state.answers, Number(questionId) || 0);
        },
        isExamAnswerEditingLocked: function () {
            return false;
        },
        isNetworkConnectivityError: function (error) {
            return !!(error && error.isNetworkError);
        },
        isQuestionPayloadLoaded: function (questionId) {
            return loadedQuestionIds.has(Number(questionId) || 0);
        },
        navQuestionFilterAll: 'all',
        navQuestionFilterAnswered: 'answered',
        navQuestionFilterDoubtful: 'doubtful',
        navQuestionFilterUnanswered: 'unanswered',
        navigationQuestionTypeBadgeConfig: function (type) {
            return {
                className: 'type-' + String(type || ''),
                code: String(type || '').slice(0, 2).toUpperCase()
            };
        },
        normalizeDropdownOptionAnswer: normalizeDropdownOptionAnswer,
        normalizeTableCompletionAnswer: normalizeTableCompletionAnswer,
        normalizeTrueFalseMatrixAnswer: normalizeTrueFalseMatrixAnswer,
        persistCurrentAttemptUiStateLocally: function () {
            calls.persistCurrentAttemptUiStateLocally += 1;
            calls.operationOrder.push('persistCurrentAttemptUiStateLocally');
        },
        prefetchNextQuestionBatch: function () {
            calls.prefetchNextQuestionBatch += 1;
        },
        prefetchNextQuestionWindow: function () {
            calls.prefetchNextQuestionWindow += 1;
        },
        questionOptionKey: questionOptionKey,
        questionWindowSize: 2,
        queueQuestionAnswer: function (question) {
            calls.queueQuestionAnswer.push(Number(question && question.id) || 0);
            calls.operationOrder.push('queueQuestionAnswer:' + String(Number(question && question.id) || 0));
            return true;
        },
        render: function (reason, meta) {
            calls.render.push({
                meta: meta || null,
                reason: String(reason || '')
            });
            calls.operationOrder.push('render:' + String(reason || ''));
        },
        renderExamPartial: function (regions, reason, meta) {
            calls.renderExamPartial.push({
                meta: meta || null,
                reason: String(reason || ''),
                regions: regions || null
            });
            calls.operationOrder.push('renderExamPartial:' + String(reason || ''));
            return false;
        },
        resetQuestionPrefetchIdleTimer: function () {
            calls.resetQuestionPrefetchIdleTimer += 1;
        },
        resolveStoredAnswerValueForQuestion: function (question) {
            var questionId = Number(question && question.id) || 0;
            return Object.prototype.hasOwnProperty.call(state.answers, questionId)
                ? state.answers[questionId]
                : (question && Object.prototype.hasOwnProperty.call(question, 'existing_answer') ? question.existing_answer : null);
        },
        scheduleAttemptUiStateSync: function (delayMs) {
            calls.scheduleAttemptUiStateSync.push(Number(delayMs) || 0);
        },
        schedulePendingAnswerRetry: function (reason, meta) {
            calls.schedulePendingAnswerRetry.push({
                meta: meta || null,
                reason: String(reason || '')
            });
        },
        scheduleQuestionCachePersist: function (delayMs) {
            calls.scheduleQuestionCachePersist.push(Number(delayMs) || 0);
        },
        setActiveQuestionWindowForIndex: function (index, size) {
            calls.setActiveQuestionWindowForIndex.push({
                index: Number(index) || 0,
                size: Number(size) || 0
            });
        },
        state
    });

    var inputManager = createAnswerInputManager({
        autoSaveChoiceDelayMs: 200,
        autoSaveTextDelayMs: 500,
        clearMessages: function () {
            calls.clearMessages += 1;
        },
        normalizeExamToken: function (value) {
            return String(value || '');
        },
        render: function () {},
        root,
        scheduleAutoSave: function () {},
        scheduleQuestionCachePersist: function () {},
        state,
        updateSelectedExam: function () {}
    });

    return {
        calls,
        inputManager,
        navigationManager,
        questionLookup,
        setNavigatorStatus: function (nextStatus) {
            navigatorStatus = String(nextStatus || 'online');
        },
        state
    };
}

afterEach(function () {
    document.body.innerHTML = '';
});

describe('createExamNavigationManager', function () {
    it('renders ordering navigation status as completed item count', function () {
        var fixture = createFixture({
            questionLookup: {
                104: createQuestion(104, 'ordering', {
                    options: [
                        { id: 201, option_key: 'A' },
                        { id: 202, option_key: 'B' },
                        { id: 203, option_key: 'C' }
                    ],
                    question_number: 4
                })
            },
            questionOrderIds: [104],
            state: {
                answers: {
                    104: [201, 203, 202]
                },
                answeredQuestionLookup: {
                    104: true
                }
            }
        });

        var question = fixture.questionLookup[104];

        expect(fixture.navigationManager.isQuestionAnswered(question)).toBe(true);
        expect(fixture.navigationManager.renderNavigationAnswerBadges(question)).toContain('3/3');
    });

    it('does not mark untouched ordering questions as answered from the default display order', function () {
        var fixture = createFixture({
            questionLookup: {
                104: createQuestion(104, 'ordering', {
                    options: [
                        { id: 201, option_key: 'A' },
                        { id: 202, option_key: 'B' },
                        { id: 203, option_key: 'C' }
                    ],
                    question_number: 4
                })
            },
            questionOrderIds: [104],
            state: {
                answers: {},
                answeredQuestionLookup: {}
            }
        });

        var question = fixture.questionLookup[104];

        expect(fixture.navigationManager.isQuestionAnswered(question)).toBe(false);
        expect(fixture.navigationManager.renderNavigationAnswerBadges(question)).toBe('');
        expect(fixture.navigationManager.getExamProgressSummary().unansweredQuestionNumbers).toEqual(['4']);
    });

    it('renders structured answer progress badges for object-map question types', function () {
        var fixture = createFixture({
            questionLookup: {
                201: createQuestion(201, 'matching', {
                    matching_meta: {
                        items: [
                            { key: '1', text: 'Kiri 1' },
                            { key: '2', text: 'Kiri 2' },
                            { key: '3', text: 'Kiri 3' },
                            { key: '4', text: 'Kiri 4' }
                        ]
                    },
                    options: [
                        { id: 901, option_key: 'A' },
                        { id: 902, option_key: 'B' },
                        { id: 903, option_key: 'C' }
                    ],
                    question_number: 1
                }),
                202: createQuestion(202, 'cloze_dropdown', {
                    cloze_dropdown_meta: {
                        blanks: [
                            { key: '1', options: [{ id: 1001 }, { id: 1002 }] },
                            { key: '2', options: [{ id: 1003 }, { id: 1004 }] },
                            { key: '3', options: [{ id: 1005 }, { id: 1006 }] }
                        ]
                    },
                    question_number: 2
                }),
                203: createQuestion(203, 'categorization', {
                    categorization_meta: {
                        items: [
                            { key: '1', text: 'Item 1' },
                            { key: '2', text: 'Item 2' },
                            { key: '3', text: 'Item 3' },
                            { key: '4', text: 'Item 4' },
                            { key: '5', text: 'Item 5' },
                            { key: '6', text: 'Item 6' },
                            { key: '7', text: 'Item 7' },
                            { key: '8', text: 'Item 8' }
                        ]
                    },
                    options: [
                        { id: 1101, option_key: 'A' },
                        { id: 1102, option_key: 'B' }
                    ],
                    question_number: 3
                }),
                204: createQuestion(204, 'table_completion', {
                    question_number: 4,
                    table_completion_meta: {
                        cells: [
                            { key: 'A1', type: 'text' },
                            { key: 'B1', type: 'dropdown', options: [{ id: 1201 }, { id: 1202 }] },
                            { key: 'C1', type: 'text' },
                            { key: 'A2', type: 'dropdown', options: [{ id: 1203 }, { id: 1204 }] },
                            { key: 'B2', type: 'text' },
                            { key: 'C2', type: 'dropdown', options: [{ id: 1205 }, { id: 1206 }] },
                            { key: 'D2', type: 'static' }
                        ]
                    }
                })
            },
            questionOrderIds: [201, 202, 203, 204],
            state: {
                answers: {
                    201: { 1: 901, 2: 999, 3: 902, x: 901 },
                    202: { 1: 1001, 2: 999, x: 1002 },
                    203: { 1: 1101, 2: 1102, 3: 1101, 4: 1102, 5: 1101, 9: 1102 },
                    204: { A1: 'Tokyo', B1: 1201, C1: ' ', A2: 999, B2: 'Osaka', C2: 0, D2: 'ignored' }
                },
                answeredQuestionLookup: {}
            }
        });

        expect(fixture.navigationManager.isQuestionAnswered(fixture.questionLookup[201])).toBe(true);
        expect(fixture.navigationManager.isQuestionAnswered(fixture.questionLookup[202])).toBe(true);
        expect(fixture.navigationManager.isQuestionAnswered(fixture.questionLookup[203])).toBe(true);
        expect(fixture.navigationManager.isQuestionAnswered(fixture.questionLookup[204])).toBe(true);
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[201])).toContain('2/4');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[202])).toContain('1/3');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[203])).toContain('5/8');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[204])).toContain('3/6');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[201])).toContain('is-partial');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[202])).toContain('is-partial');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[203])).toContain('is-partial');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[204])).toContain('is-partial');
    });

    it('marks partial and complete progress badges for all structured progress question types', function () {
        var fixture = createFixture({
            questionLookup: {
                221: createQuestion(221, 'short_answer', {
                    question_number: 1,
                    short_answer_meta: {
                        input_keys: ['A', 'B', 'C', 'D']
                    }
                }),
                222: createQuestion(222, 'true_false_matrix', {
                    question_number: 2,
                    true_false_matrix_meta: {
                        items: [
                            { key: '1', text: 'Pernyataan 1' },
                            { key: '2', text: 'Pernyataan 2' }
                        ]
                    }
                }),
                223: createQuestion(223, 'matching', {
                    matching_meta: {
                        items: [
                            { key: '1', text: 'Kiri 1' },
                            { key: '2', text: 'Kiri 2' }
                        ]
                    },
                    options: [
                        { id: 901, option_key: 'A' },
                        { id: 902, option_key: 'B' }
                    ],
                    question_number: 3
                }),
                224: createQuestion(224, 'cloze_dropdown', {
                    cloze_dropdown_meta: {
                        blanks: [
                            { key: '1', options: [{ id: 1001 }, { id: 1002 }] },
                            { key: '2', options: [{ id: 1003 }, { id: 1004 }] }
                        ]
                    },
                    question_number: 4
                }),
                225: createQuestion(225, 'categorization', {
                    categorization_meta: {
                        items: [
                            { key: '1', text: 'Item 1' },
                            { key: '2', text: 'Item 2' }
                        ]
                    },
                    options: [
                        { id: 1101, option_key: 'A' },
                        { id: 1102, option_key: 'B' }
                    ],
                    question_number: 5
                }),
                226: createQuestion(226, 'table_completion', {
                    question_number: 6,
                    table_completion_meta: {
                        cells: [
                            { key: 'A1', type: 'text' },
                            { key: 'B1', type: 'dropdown', options: [{ id: 1201 }, { id: 1202 }] }
                        ]
                    }
                })
            },
            questionOrderIds: [221, 222, 223, 224, 225, 226],
            state: {
                answers: {
                    221: { A: 'Alpha', C: 'Charlie' },
                    222: { 1: 'true', 2: 'false' },
                    223: { 1: 901, 2: 902 },
                    224: { 1: 1001 },
                    225: { 1: 1101, 2: 1102 },
                    226: { A1: 'Tokyo' }
                },
                answeredQuestionLookup: {}
            }
        });

        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[221])).toContain('2/4');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[221])).toContain('is-partial');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[222])).toContain('2/2');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[222])).toContain('is-complete');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[223])).toContain('2/2');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[223])).toContain('is-complete');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[224])).toContain('1/2');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[224])).toContain('is-partial');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[225])).toContain('2/2');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[225])).toContain('is-complete');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[226])).toContain('1/2');
        expect(fixture.navigationManager.renderNavigationAnswerBadges(fixture.questionLookup[226])).toContain('is-partial');

        var summary = fixture.navigationManager.getExamProgressSummary();
        expect(summary.partialQuestionNumbers).toEqual(['1', '4', '6']);
        expect(summary.partialQuestionItems.map(function (item) {
            return item.progressLabel;
        })).toEqual(['2/4', '1/2', '1/2']);
    });

    it('ignores invalid structured answer keys, invalid options, static cells, and blank text', function () {
        var fixture = createFixture({
            questionLookup: {
                211: createQuestion(211, 'matching', {
                    matching_meta: {
                        items: [{ key: '1', text: 'Kiri 1' }]
                    },
                    options: [{ id: 901, option_key: 'A' }],
                    question_number: 1
                }),
                212: createQuestion(212, 'cloze_dropdown', {
                    cloze_dropdown_meta: {
                        blanks: [{ key: '1', options: [{ id: 1001 }, { id: 1002 }] }]
                    },
                    question_number: 2
                }),
                213: createQuestion(213, 'categorization', {
                    categorization_meta: {
                        items: [{ key: '1', text: 'Item 1' }]
                    },
                    options: [{ id: 1101, option_key: 'A' }],
                    question_number: 3
                }),
                214: createQuestion(214, 'table_completion', {
                    question_number: 4,
                    table_completion_meta: {
                        cells: [
                            { key: 'A1', type: 'text' },
                            { key: 'B1', type: 'dropdown', options: [{ id: 1201 }, { id: 1202 }] },
                            { key: 'C1', type: 'static' }
                        ]
                    }
                })
            },
            questionOrderIds: [211, 212, 213, 214],
            state: {
                answers: {
                    211: { 1: 999, x: 901 },
                    212: { 1: 999, x: 1001 },
                    213: { 1: 999, x: 1101 },
                    214: { A1: ' ', B1: 999, C1: 'static should not count' }
                },
                answeredQuestionLookup: {}
            }
        });

        [211, 212, 213, 214].forEach(function (questionId) {
            var question = fixture.questionLookup[questionId];
            expect(fixture.navigationManager.isQuestionAnswered(question)).toBe(false);
            expect(fixture.navigationManager.renderNavigationAnswerBadges(question)).toBe('');
        });
    });

    it('keeps currentIndex clamped to a valid question and preserves per-question state across navigation jumps', async function () {
        var fixture = createFixture();

        await fixture.navigationManager.goToQuestion(99);

        expect(fixture.state.currentIndex).toBe(2);
        expect(fixture.state.answers[101]).toBe(12);
        expect(fixture.state.answers[102]).toEqual({ A: 'jawaban' });
        expect(fixture.calls.queueQuestionAnswer).toEqual([101]);
        expect(fixture.calls.setActiveQuestionWindowForIndex).toEqual([
            {
                index: 2,
                size: 2
            }
        ]);

        await fixture.navigationManager.goToQuestion(-10);

        expect(fixture.state.currentIndex).toBe(0);
        expect(fixture.calls.queueQuestionAnswer).toEqual([101, 103]);
    });

    it('keeps doubtful flags intact when answers change and the user moves away and back', async function () {
        var fixture = createFixture();
        var doubtfulButton = document.createElement('button');
        doubtfulButton.setAttribute('data-qid', '101');

        fixture.navigationManager.handleAction('toggle-doubtful', doubtfulButton);

        var input = document.createElement('textarea');
        input.setAttribute('data-action', 'answer-text');
        input.setAttribute('data-qid', '102');
        input.value = 'jawaban revisi';
        fixture.inputManager.handleInputTarget(input);

        await fixture.navigationManager.goToQuestion(1);
        await fixture.navigationManager.goToQuestion(0);

        expect(fixture.state.doubtful[101]).toBe(true);
        expect(fixture.state.answers[102]).toBe('jawaban revisi');
        expect(fixture.state.currentIndex).toBe(0);
    });

    it('filters navigation entries using answered and doubtful state without corrupting the active question', function () {
        var fixture = createFixture({
            state: {
                answers: {
                    101: 12
                },
                answeredQuestionLookup: {
                    101: true
                },
                currentIndex: 1,
                doubtful: {
                    103: true
                }
            }
        });

        expect(fixture.navigationManager.normalizeNavigationQuestionFilter('??')).toBe('all');
        expect(fixture.navigationManager.getNavigationQuestionEntries('answered').map(function (entry) {
            return entry.questionId;
        })).toEqual([101]);
        expect(fixture.navigationManager.getNavigationQuestionEntries('doubtful').map(function (entry) {
            return entry.questionId;
        })).toEqual([103]);
        expect(fixture.state.currentIndex).toBe(1);
    });

    it('includes unanswered and doubtful question numbers in the exam progress summary', function () {
        var fixture = createFixture({
            state: {
                answers: {
                    101: 12
                },
                answeredQuestionLookup: {
                    101: true
                },
                doubtful: {
                    102: true
                }
            }
        });

        var summary = fixture.navigationManager.getExamProgressSummary();

        expect(summary.totalQuestions).toBe(3);
        expect(summary.answeredQuestions).toBe(1);
        expect(summary.unansweredQuestions).toBe(2);
        expect(summary.doubtfulQuestions).toBe(1);
        expect(summary.unansweredQuestionNumbers).toEqual(['2', '3']);
        expect(summary.doubtfulQuestionNumbers).toEqual(['2']);
        expect(summary.unansweredQuestionItems.map(function (item) {
            return item.questionId;
        })).toEqual([102, 103]);
    });

    it('includes pending sync question numbers in question order and ignores stale ids', function () {
        var fixture = createFixture({
            getPendingSyncQuestionIds: function () {
                return [103, 999, 101];
            }
        });

        var summary = fixture.navigationManager.getExamProgressSummary();

        expect(summary.pendingSyncQuestionNumbers).toEqual(['1', '3']);
        expect(summary.pendingSyncQuestionItems.map(function (item) {
            return item.questionId;
        })).toEqual([101, 103]);
    });

    it('closes the finish review modal and focuses the first unanswered or doubtful question', function () {
        var fixture = createFixture({
            state: {
                answers: {
                    101: 12
                },
                answeredQuestionLookup: {
                    101: true
                },
                currentIndex: 0,
                doubtful: {
                    103: true
                },
                finishConfirmOpen: true,
                finishConfirmSummary: { totalQuestions: 3 }
            }
        });
        var button = document.createElement('button');

        expect(fixture.navigationManager.handleAction('finish-review-unanswered', button)).toBe(true);
        expect(fixture.state.finishConfirmOpen).toBe(false);
        expect(fixture.state.finishConfirmSummary).toBeNull();
        expect(fixture.state.navQuestionFilter).toBe('unanswered');
        expect(fixture.state.currentIndex).toBe(1);

        fixture.state.finishConfirmOpen = true;
        fixture.state.finishConfirmSummary = { totalQuestions: 3 };
        expect(fixture.navigationManager.handleAction('finish-review-doubtful', button)).toBe(true);
        expect(fixture.state.finishConfirmOpen).toBe(false);
        expect(fixture.state.navQuestionFilter).toBe('doubtful');
        expect(fixture.state.currentIndex).toBe(2);
    });

    it('closes the finish review modal and focuses the first pending sync question', function () {
        var fixture = createFixture({
            getPendingSyncQuestionIds: function () {
                return [103];
            },
            state: {
                currentIndex: 0,
                finishConfirmOpen: true,
                finishConfirmSummary: { totalQuestions: 3 }
            }
        });
        var button = document.createElement('button');

        expect(fixture.navigationManager.handleAction('finish-review-pending-sync', button)).toBe(true);

        expect(fixture.state.finishConfirmOpen).toBe(false);
        expect(fixture.state.finishConfirmSummary).toBeNull();
        expect(fixture.state.navQuestionFilter).toBe('all');
        expect(fixture.state.currentIndex).toBe(2);
    });

    it('closes the finish review modal and focuses the first partial answer question', function () {
        var fixture = createFixture({
            questionLookup: {
                221: createQuestion(221, 'short_answer', {
                    question_number: 1,
                    short_answer_meta: {
                        input_keys: ['A', 'B', 'C']
                    }
                }),
                222: createQuestion(222, 'matching', {
                    matching_meta: {
                        items: [
                            { key: '1', text: 'Kiri 1' },
                            { key: '2', text: 'Kiri 2' }
                        ]
                    },
                    options: [
                        { id: 901, option_key: 'A' },
                        { id: 902, option_key: 'B' }
                    ],
                    question_number: 2
                })
            },
            questionOrderIds: [221, 222],
            state: {
                answers: {
                    221: { A: 'Alpha' },
                    222: { 1: 901, 2: 902 }
                },
                answeredQuestionLookup: {},
                currentIndex: 1,
                finishConfirmOpen: true,
                finishConfirmSummary: { totalQuestions: 2 }
            }
        });
        var button = document.createElement('button');

        expect(fixture.navigationManager.handleAction('finish-review-partial', button)).toBe(true);

        expect(fixture.state.finishConfirmOpen).toBe(false);
        expect(fixture.state.finishConfirmSummary).toBeNull();
        expect(fixture.state.navQuestionFilter).toBe('all');
        expect(fixture.state.currentIndex).toBe(0);
    });

    it('restores the exam view when the first finish review target is already active', function () {
        var fixture = createFixture({
            state: {
                answers: {},
                answeredQuestionLookup: {},
                currentIndex: 0,
                finishConfirmOpen: true,
                finishConfirmSummary: { totalQuestions: 3 }
            }
        });
        var button = document.createElement('button');

        expect(fixture.navigationManager.handleAction('finish-review-unanswered', button)).toBe(true);

        expect(fixture.state.finishConfirmOpen).toBe(false);
        expect(fixture.state.finishConfirmSummary).toBeNull();
        expect(fixture.state.navQuestionFilter).toBe('unanswered');
        expect(fixture.state.currentIndex).toBe(0);
        expect(fixture.state.navigationRefreshing).toBe(false);
        expect(fixture.state.questionRegionRefreshing).toBe(false);
        expect(fixture.calls.render.some(function (call) {
            return call.reason === 'finish-review:focus-current';
        })).toBe(true);
        expect(fixture.calls.renderExamPartial.some(function (call) {
            return call.reason === 'finish-review:focus-current';
        })).toBe(false);
        expect(fixture.calls.setActiveQuestionWindowForIndex).toEqual([
            {
                index: 0,
                size: 2
            }
        ]);
        expect(fixture.calls.scheduleAttemptUiStateSync).toEqual([150]);
    });

    it('shows a navigation transition before flushing the previous answer when the target payload is already loaded', async function () {
        var fixture = createFixture();

        await fixture.navigationManager.goToQuestion(1);

        expect(fixture.state.currentIndex).toBe(1);
        expect(fixture.state.navigationRefreshing).toBe(false);
        expect(fixture.calls.prefetchNextQuestionWindow).toBe(1);
        expect(fixture.calls.prefetchNextQuestionBatch).toBe(1);
        expect(fixture.calls.operationOrder.indexOf('render:navigation:question-transition')).toBeGreaterThanOrEqual(0);
        expect(fixture.calls.operationOrder.indexOf('queueQuestionAnswer:101')).toBeGreaterThanOrEqual(0);
        expect(fixture.calls.operationOrder.indexOf('render:navigation:question-transition')).toBeLessThan(
            fixture.calls.operationOrder.indexOf('queueQuestionAnswer:101')
        );
        expect(fixture.calls.operationOrder.indexOf('persistCurrentAttemptUiStateLocally')).toBeGreaterThan(
            fixture.calls.operationOrder.indexOf('queueQuestionAnswer:101')
        );
    });

    it('shows the question loading patch before loading an unloaded target question', async function () {
        var fixture = createFixture({
            loadedQuestionIds: [101]
        });

        await fixture.navigationManager.goToQuestion(1);

        expect(fixture.state.currentIndex).toBe(1);
        expect(fixture.state.navigationRefreshing).toBe(false);
        expect(fixture.state.questionRegionRefreshing).toBe(false);
        expect(fixture.calls.operationOrder.indexOf('render:navigation:question-loading')).toBeGreaterThanOrEqual(0);
        expect(fixture.calls.operationOrder.indexOf('ensureQuestionWindowForIndex:1')).toBeGreaterThanOrEqual(0);
        expect(fixture.calls.operationOrder.indexOf('render:navigation:question-loading')).toBeLessThan(
            fixture.calls.operationOrder.indexOf('ensureQuestionWindowForIndex:1')
        );
    });
});
