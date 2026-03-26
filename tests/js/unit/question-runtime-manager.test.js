import { describe, expect, it } from 'vitest';
import { createQuestionRuntimeManager } from '../../../src/frontend/app/exam/question-runtime.js';

function createQuestion(id, overrides = {}) {
    return Object.assign({
        id: Number(id) || 0,
        options: [],
        question_number: 1,
        question_text: '',
        question_type: 'multiple_choice',
        updated_at: 'rev-1'
    }, overrides || {});
}

function createManifestById(items) {
    return (Array.isArray(items) ? items : []).reduce(function (lookup, item) {
        var questionId = Number(item && item.id) || 0;
        if (questionId > 0) {
            lookup[questionId] = item;
        }
        return lookup;
    }, {});
}

function createFixture(overrides = {}) {
    var calls = {
        markQuestionWindowLoaded: [],
        mergeExistingAnswersFromQuestionItems: [],
        mergeExistingAnswersMap: [],
        restoreQuestionAutoSaveState: [],
        schedulePendingAnswerRetry: [],
        scheduleQuestionCachePersist: [],
        setQuestionWindowFromLoadedPayloads: [],
        updateQuestionPrefetchIndicator: 0
    };
    var state = Object.assign({
        acknowledgedRevisionQuestionIds: {},
        answers: {
            101: 11
        },
        answeredQuestionLookup: {
            101: true
        },
        attemptId: 55,
        changedQuestionLookup: {},
        existingAnswerRawByQuestionId: {},
        loadedQuestionWindowOffsets: {},
        navQuestionFilter: 'all',
        questionManifest: [],
        questionManifestById: {},
        questionOrderIds: [101],
        questionOrderSignature: 'sig-old',
        questionPayloadById: {
            101: createQuestion(101, {
                options: [
                    { id: 11, option_key: 'A' }
                ],
                question_number: 1,
                question_text: 'Old'
            })
        },
        questionRegionRefreshing: false,
        questionRevision: null,
        questionRevisionMarkerLookup: {},
        questions: [createQuestion(101, {
            options: [
                { id: 11, option_key: 'A' }
            ],
            question_number: 1,
            question_text: 'Old'
        })],
        selectedExamId: 9,
        stage: 'exam',
        totalQuestions: 1,
        windowLimit: 1,
        windowOffset: 0
    }, overrides.state || {});
    var manager = createQuestionRuntimeManager({
        apiRequest: async function () {
            throw new Error('apiRequest should not be called in this test');
        },
        applyAttemptUiState: function () {},
        applyPendingRevisionSafeAnswersForLoadedQuestions: function () {
            return [];
        },
        attemptUiStateSyncDelayMs: 300,
        buildAttemptUiStateSnapshot: function () {
            return null;
        },
        buildAutoSaveStateSnapshot: function () {
            return {};
        },
        buildChangedQuestionLookup: function () {
            return {};
        },
        buildQuestionManifestById: createManifestById,
        buildQuestionManifestFromQuestions: function (questions) {
            return (Array.isArray(questions) ? questions : []).map(function (question) {
                return {
                    id: Number(question && question.id) || 0,
                    question_number: Number(question && question.question_number) || 0,
                    question_type: String(question && question.question_type || '')
                };
            });
        },
        buildQuestionOrderSignature: function (orderIds) {
            return 'sig:' + (Array.isArray(orderIds) ? orderIds.join('-') : '');
        },
        captureRevisionSafeLocalAnswers: function () {
            return {};
        },
        clearAttemptUiStateSyncTimer: function () {},
        clearAutoSaveRuntimeState: function () {},
        clearPendingRevisionSafeAnswerRestoreState: function () {},
        clearPersistedQuestionCache: function () {},
        clearQuestionPrefetchRuntimeState: function () {},
        clampQuestionIndex: function (index) {
            var maxIndex = Math.max(0, state.questionOrderIds.length - 1);
            return Math.max(0, Math.min(maxIndex, Math.floor(Number(index) || 0)));
        },
        getQuestionCount: function () {
            return state.questionOrderIds.length;
        },
        getQuestionIdAtIndex: function (index) {
            return Number(state.questionOrderIds[Math.max(0, Math.min(state.questionOrderIds.length - 1, Math.floor(Number(index) || 0)))]) || 0;
        },
        getQuestionManifestById: function (questionId) {
            return state.questionManifestById[Number(questionId) || 0] || null;
        },
        getQuestionPayloadById: function (questionId) {
            return state.questionPayloadById[Number(questionId) || 0] || null;
        },
        hasPendingQueuedAnswerBatchItems: function () {
            return false;
        },
        hasUsableLocalAnswerForQuestion: function (questionId) {
            return Object.prototype.hasOwnProperty.call(state.answers, Number(questionId) || 0);
        },
        initializeSubmittedPayloadCache: function () {},
        isIndexInCurrentWindow: function () {
            return true;
        },
        isQuestionPayloadLoaded: function (questionId) {
            return !!state.questionPayloadById[Number(questionId) || 0];
        },
        markQuestionWindowLoaded: function (offset) {
            calls.markQuestionWindowLoaded.push(Number(offset) || 0);
        },
        mergeExistingAnswersFromQuestionItems: function (items, options) {
            calls.mergeExistingAnswersFromQuestionItems.push({
                items: Array.isArray(items) ? items.map(function (question) {
                    return Number(question && question.id) || 0;
                }) : [],
                options: options || {}
            });
        },
        mergeExistingAnswersMap: function (answersMap, options) {
            calls.mergeExistingAnswersMap.push({
                answersMap: answersMap || null,
                options: options || {}
            });
        },
        normalizeNavigationQuestionFilter: function (filter) {
            return String(filter || 'all');
        },
        normalizeOrUseQuestionCacheSnapshot: function (snapshot) {
            return snapshot && snapshot.questionPayloadById ? snapshot : null;
        },
        normalizeQuestionCacheSnapshot: function (snapshot) {
            return snapshot;
        },
        normalizeQuestionIdList: function (items) {
            return (Array.isArray(items) ? items : []).reduce(function (accumulator, item) {
                var questionId = Number(item) || 0;
                if (questionId > 0 && accumulator.indexOf(questionId) < 0) {
                    accumulator.push(questionId);
                }
                return accumulator;
            }, []);
        },
        normalizeQuestionRevision: function (revision, fallbackExamId) {
            if (!revision) {
                return null;
            }

            return {
                exam_id: Number(revision.exam_id || fallbackExamId) || 0,
                signature: String(revision.signature || ''),
                version: Number(revision.version) || 0
            };
        },
        persistCurrentAttemptUiStateLocally: function () {},
        persistCurrentQuestionCacheLocally: function () {},
        primeSubmittedPayloadCacheFromQuestionItems: function () {},
        pruneAnswerSyncState: function () {},
        prunePendingRevisionSafeAnswerRestoreState: function () {},
        questionOrderSignatureEquals: function (left, right) {
            return String(left || '').trim() === String(right || '').trim();
        },
        questionRevisionEquals: function (left, right) {
            return JSON.stringify(left || null) === JSON.stringify(right || null);
        },
        questionWindowOffsetForIndex: function () {
            return 0;
        },
        questionWindowSize: 2,
        queueLoadedQuestionAnswersForFlush: function () {
            return 0;
        },
        queueQuestionAnswersByIds: function () {
            return 0;
        },
        recordActionTrail: function () {},
        recordTimeline: function () {},
        render: function () {},
        renderExamPartial: function () {
            return false;
        },
        restoreLocalAnswerFromQuestion: function () {
            return false;
        },
        restoreQuestionAutoSaveState: function (snapshot) {
            calls.restoreQuestionAutoSaveState.push(snapshot || null);
        },
        restoreRevisionSafeLocalAnswers: function () {},
        scheduleAttemptUiStateSync: function () {},
        schedulePendingAnswerRetry: function (reason, meta) {
            calls.schedulePendingAnswerRetry.push({
                meta: meta || null,
                reason: String(reason || '')
            });
        },
        scheduleQuestionCachePersist: function (delayMs) {
            calls.scheduleQuestionCachePersist.push(Number(delayMs) || 0);
        },
        setQuestionWindowFromLoadedPayloads: function (offset, limit) {
            calls.setQuestionWindowFromLoadedPayloads.push({
                limit: Number(limit) || 0,
                offset: Number(offset) || 0
            });
            state.windowOffset = Number(offset) || 0;
            state.windowLimit = Number(limit) || 0;
            return true;
        },
        state,
        syncAttemptUiStateSignatureToCurrentState: function () {},
        updateQuestionPrefetchIndicator: function () {
            calls.updateQuestionPrefetchIndicator += 1;
        },
        validAttemptQuestionIds: function () {
            return state.questionOrderIds.reduce(function (lookup, questionId) {
                lookup[Number(questionId) || 0] = true;
                return lookup;
            }, {});
        },
        windowRef: globalThis,
        serializeQuestionRevision: function (revision) {
            return revision;
        }
    });

    return {
        calls,
        manager,
        state
    };
}

describe('createQuestionRuntimeManager', function () {
    it('keeps rich content payload, option order, and question metadata aligned when applying a questions response', function () {
        var fixture = createFixture();
        var questionPayload = {
            answered_question_ids: [201],
            existing_answers_map: {
                201: 901
            },
            items: [
                createQuestion(201, {
                    options: [
                        { id: 900, option_key: 'B', option_text: 'Beta' },
                        { id: 901, option_key: 'A', option_text: 'Alpha' }
                    ],
                    question_text: '<p><strong>Rich</strong> content</p>',
                    question_type: 'multiple_choice'
                })
            ],
            limit: 1,
            offset: 0,
            question_manifest: [
                {
                    id: 201,
                    question_number: 4,
                    question_type: 'multiple_choice'
                }
            ],
            question_order_ids: [201],
            question_order_signature: 'sig:201',
            total_questions: 1
        };

        fixture.manager.applyQuestionsResponse(questionPayload, {
            overwriteExisting: true
        });

        expect(fixture.state.questionOrderIds).toEqual([201]);
        expect(fixture.state.questionOrderSignature).toBe('sig:201');
        expect(fixture.state.totalQuestions).toBe(1);
        expect(fixture.state.questions[0].id).toBe(201);
        expect(fixture.state.questionPayloadById[201].question_number).toBe(4);
        expect(fixture.state.questionPayloadById[201].question_text).toBe('<p><strong>Rich</strong> content</p>');
        expect(fixture.state.questionPayloadById[201].options.map(function (option) {
            return Number(option.id) || 0;
        })).toEqual([900, 901]);
        expect(fixture.calls.mergeExistingAnswersMap).toEqual([
            {
                answersMap: {
                    201: 901
                },
                options: {
                    overwriteExisting: true
                }
            }
        ]);
        expect(fixture.calls.markQuestionWindowLoaded).toEqual([0]);
        expect(fixture.calls.updateQuestionPrefetchIndicator).toBe(1);
    });

    it('blocks unsafe persisted question runtime state on order signature conflict while keeping safe answer restore', function () {
        var fixture = createFixture({
            state: {
                answers: {
                    101: 11
                },
                answeredQuestionLookup: {
                    101: true
                },
                questionOrderIds: [101],
                questionOrderSignature: 'sig-old',
                questionPayloadById: {
                    101: createQuestion(101, {
                        options: [
                            { id: 11, option_key: 'A' }
                        ],
                        question_number: 1,
                        question_text: 'Old'
                    })
                }
            }
        });
        var snapshot = {
            acknowledgedRevisionQuestionIds: {},
            answers: {
                202: 77
            },
            answeredQuestionLookup: {
                202: true
            },
            changedQuestionLookup: {},
            examId: 9,
            existingAnswerRawByQuestionId: {},
            loadedQuestionWindowOffsets: {
                0: true
            },
            questionManifest: [
                {
                    id: 202,
                    question_number: 2,
                    question_type: 'multiple_choice'
                }
            ],
            questionOrderIds: [202],
            questionOrderSignature: 'sig-new',
            questionPayloadById: {
                202: createQuestion(202, {
                    options: [
                        { id: 77, option_key: 'A' }
                    ],
                    question_number: 2,
                    question_text: 'New'
                })
            },
            questionRevision: null,
            questionRevisionMarkerLookup: {},
            totalQuestions: 1,
            windowLimit: 1,
            windowOffset: 0
        };

        expect(fixture.manager.applyPersistedQuestionCache(snapshot, {
            expectedQuestionOrderSignature: 'sig-old'
        })).toBe(false);
        expect(fixture.state.questionOrderIds).toEqual([101]);
        expect(Object.keys(fixture.state.questionPayloadById).map(Number)).toEqual([101]);
        expect(fixture.state.answers).toEqual({
            101: 11,
            202: 77
        });
        expect(fixture.state.answeredQuestionLookup).toEqual({
            101: true,
            202: true
        });
    });
});
