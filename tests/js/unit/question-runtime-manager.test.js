import { describe, expect, it } from 'vitest';
import { createQuestionRuntimeManager } from '../../../src/frontend/app/exam/question-runtime.js';
import { createQuestionCacheStorage } from '../../../src/frontend/app/storage/question-cache.js';

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

function createQuestionCacheHelpers(state) {
    return createQuestionCacheStorage({
        state,
        getSessionStorage: function () {
            return null;
        },
        getLocalStorage: function () {
            return null;
        },
        getIndexedDb: function () {
            return null;
        },
        indexedDbName: '',
        indexedDbStore: '',
        sessionStorageKeyPrefix: 'test-question-cache',
        metaLocalStorageKeyPrefix: 'test-question-cache-meta',
        itemLocalStorageKeyPrefix: 'test-question-cache-item',
        normalizeExistingAnswerForQuestion: function (value) {
            return value;
        },
        getQuestionPayloadById: function (questionId) {
            return state.questionPayloadById[Number(questionId) || 0] || null;
        },
        payloadSignature: function (value) {
            return JSON.stringify(value);
        },
        getAutoSaveState: function () {
            return {};
        },
        now: function () {
            return 1700000000000;
        },
        windowRef: globalThis,
    });
}

function createRevision(version, examId) {
    var safeVersion = Number(version) || 0;
    var safeExamId = Number(examId) || 0;

    return {
        exam_id: safeExamId,
        invalidated_at: safeVersion,
        namespace: 'exam:' + safeExamId,
        signature: 'exam:' + safeExamId + '|v:' + safeVersion + '|t:' + safeVersion,
        version: safeVersion,
    };
}

function buildQuestionResponse(cacheHelpers, items, revision, overrides = {}) {
    var payloadItems = Array.isArray(items) ? items : [];
    var questionOrderIds = Array.isArray(overrides.question_order_ids)
        ? overrides.question_order_ids.slice()
        : payloadItems.map(function (question) {
            return Number(question && question.id) || 0;
        });
    var questionManifest = Array.isArray(overrides.question_manifest)
        ? overrides.question_manifest.slice()
        : cacheHelpers.buildQuestionManifestFromQuestions(payloadItems);

    return Object.assign({
        items: payloadItems,
        limit: Math.max(1, Number(overrides.limit) || payloadItems.length || 1),
        offset: 0,
        question_manifest: questionManifest,
        question_order_ids: questionOrderIds,
        question_order_signature: cacheHelpers.buildQuestionOrderSignature(
            questionOrderIds,
            questionManifest,
            payloadItems
        ),
        question_revision: revision || null,
        total_questions: questionOrderIds.length,
    }, overrides || {});
}

function createFixture(overrides = {}) {
    var calls = {
        apiRequest: [],
        clearPersistedQuestionCache: [],
        markQuestionWindowLoaded: [],
        mergeExistingAnswersFromQuestionItems: [],
        mergeExistingAnswersMap: [],
        recordActionTrail: [],
        recordTimeline: [],
        render: [],
        renderExamPartial: [],
        restoreQuestionAutoSaveState: [],
        restoreRevisionSafeLocalAnswers: [],
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
        currentIndex: 0,
        existingAnswerRawByQuestionId: {},
        loadedQuestionWindowOffsets: {},
        navQuestionFilter: 'all',
        navigationRefreshing: false,
        questionManifest: [],
        questionManifestById: {},
        questionOrderIds: [101],
        questionOrderSignature: '',
        questionPayloadById: {
            101: createQuestion(101, {
                options: [
                    { id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }
                ],
                question_number: 1,
                question_text: 'Old'
            })
        },
        questionRegionRefreshing: false,
        questionRevision: null,
        questionRevisionMarkerLookup: {},
        questionRevisionNotice: null,
        questionRevisionRefreshing: false,
        questionRevisionToastTimerId: 0,
        questions: [createQuestion(101, {
            options: [
                { id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }
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
    var cacheHelpers = createQuestionCacheHelpers(state);
    var deps = overrides.deps || {};

    if (!Array.isArray(state.questionOrderIds) || !state.questionOrderIds.length) {
        state.questionOrderIds = Object.keys(state.questionPayloadById || {}).map(function (key) {
            return Number(key) || 0;
        }).filter(function (questionId) {
            return questionId > 0;
        });
    }

    if (!Array.isArray(state.questions) || !state.questions.length) {
        state.questions = state.questionOrderIds.map(function (questionId) {
            return state.questionPayloadById[questionId] || null;
        }).filter(Boolean);
    }

    if (!Array.isArray(state.questionManifest) || !state.questionManifest.length) {
        state.questionManifest = cacheHelpers.buildQuestionManifestFromQuestions(state.questions);
    }

    if (!state.questionManifestById || !Object.keys(state.questionManifestById).length) {
        state.questionManifestById = cacheHelpers.buildQuestionManifestById(state.questionManifest);
    }

    if (String(state.questionOrderSignature || '').trim() === '') {
        state.questionOrderSignature = cacheHelpers.buildQuestionOrderSignature(
            state.questionOrderIds,
            state.questionManifest,
            state.questions
        );
    }

    if (!state.questionRevision) {
        state.questionRevision = createRevision(1, state.selectedExamId);
    }

    var manager = createQuestionRuntimeManager({
        apiRequest: async function (path, options) {
            calls.apiRequest.push({
                options: options || {},
                path: String(path || '')
            });
            if (typeof deps.apiRequest === 'function') {
                return deps.apiRequest(path, options);
            }
            throw new Error('apiRequest should not be called in this test');
        },
        applyAttemptUiState: function (snapshot) {
            if (typeof deps.applyAttemptUiState === 'function') {
                return deps.applyAttemptUiState(snapshot);
            }

            if (!snapshot || typeof snapshot !== 'object') {
                return undefined;
            }

            state.currentIndex = Number(snapshot.current_index) || 0;
            return undefined;
        },
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
        buildChangedQuestionLookup: typeof deps.buildChangedQuestionLookup === 'function'
            ? deps.buildChangedQuestionLookup
            : cacheHelpers.buildChangedQuestionLookup,
        buildQuestionManifestById: typeof deps.buildQuestionManifestById === 'function'
            ? deps.buildQuestionManifestById
            : cacheHelpers.buildQuestionManifestById,
        buildQuestionManifestFromQuestions: typeof deps.buildQuestionManifestFromQuestions === 'function'
            ? deps.buildQuestionManifestFromQuestions
            : cacheHelpers.buildQuestionManifestFromQuestions,
        buildQuestionOrderSignature: typeof deps.buildQuestionOrderSignature === 'function'
            ? deps.buildQuestionOrderSignature
            : cacheHelpers.buildQuestionOrderSignature,
        captureRevisionSafeLocalAnswers: function () {
            if (typeof deps.captureRevisionSafeLocalAnswers === 'function') {
                return deps.captureRevisionSafeLocalAnswers();
            }
            return {};
        },
        clearAttemptUiStateSyncTimer: function () {},
        clearAutoSaveRuntimeState: function () {},
        clearPendingRevisionSafeAnswerRestoreState: function () {},
        clearPersistedQuestionCache: function (attemptId) {
            calls.clearPersistedQuestionCache.push(Number(attemptId) || 0);
        },
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
        normalizeOrUseQuestionCacheSnapshot: typeof deps.normalizeOrUseQuestionCacheSnapshot === 'function'
            ? deps.normalizeOrUseQuestionCacheSnapshot
            : cacheHelpers.normalizeOrUseQuestionCacheSnapshot,
        normalizeQuestionCacheSnapshot: typeof deps.normalizeQuestionCacheSnapshot === 'function'
            ? deps.normalizeQuestionCacheSnapshot
            : cacheHelpers.normalizeQuestionCacheSnapshot,
        normalizeQuestionIdList: typeof deps.normalizeQuestionIdList === 'function'
            ? deps.normalizeQuestionIdList
            : cacheHelpers.normalizeQuestionIdList,
        normalizeQuestionRevision: typeof deps.normalizeQuestionRevision === 'function'
            ? deps.normalizeQuestionRevision
            : cacheHelpers.normalizeQuestionRevision,
        persistCurrentAttemptUiStateLocally: function () {},
        persistCurrentQuestionCacheLocally: function () {},
        primeSubmittedPayloadCacheFromQuestionItems: function () {},
        pruneAnswerSyncState: function () {},
        prunePendingRevisionSafeAnswerRestoreState: function () {},
        questionOrderSignatureEquals: typeof deps.questionOrderSignatureEquals === 'function'
            ? deps.questionOrderSignatureEquals
            : cacheHelpers.questionOrderSignatureEquals,
        questionRevisionEquals: typeof deps.questionRevisionEquals === 'function'
            ? deps.questionRevisionEquals
            : cacheHelpers.questionRevisionEquals,
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
        recordActionTrail: function (kind, summary, meta) {
            calls.recordActionTrail.push({
                kind: String(kind || ''),
                meta: meta || {},
                summary: String(summary || '')
            });
        },
        recordTimeline: function (kind, summary, meta) {
            calls.recordTimeline.push({
                kind: String(kind || ''),
                meta: meta || {},
                summary: String(summary || '')
            });
        },
        resetQuestionPrefetchIdleTimer: function () {},
        render: function (reason, meta) {
            calls.render.push({
                meta: meta || {},
                reason: String(reason || '')
            });
        },
        renderExamPartial: function (regions, reason, meta) {
            calls.renderExamPartial.push({
                meta: meta || {},
                reason: String(reason || ''),
                regions: regions || {}
            });
            return false;
        },
        restoreLocalAnswerFromQuestion: function () {
            return false;
        },
        restoreQuestionAutoSaveState: function (snapshot) {
            calls.restoreQuestionAutoSaveState.push(snapshot || null);
        },
        restoreRevisionSafeLocalAnswers: function (snapshot, options) {
            calls.restoreRevisionSafeLocalAnswers.push({
                options: options || {},
                snapshot: snapshot || null
            });
            if (typeof deps.restoreRevisionSafeLocalAnswers === 'function') {
                return deps.restoreRevisionSafeLocalAnswers(snapshot, options);
            }
            return undefined;
        },
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
        serializeQuestionRevision: typeof deps.serializeQuestionRevision === 'function'
            ? deps.serializeQuestionRevision
            : cacheHelpers.serializeQuestionRevision
    });

    return {
        cacheHelpers,
        calls,
        manager,
        state
    };
}

describe('createQuestionRuntimeManager', function () {
    it('keeps rich content payload, option order, and question metadata aligned when applying a questions response', function () {
        var fixture = createFixture();
        var questionPayload = buildQuestionResponse(
            fixture.cacheHelpers,
            [
                createQuestion(201, {
                    options: [
                        { id: 900, option_key: 'B', option_text: 'Beta' },
                        { id: 901, option_key: 'A', option_text: 'Alpha' }
                    ],
                    question_number: 4,
                    question_text: '<p><strong>Rich</strong> content</p>',
                    question_type: 'multiple_choice'
                })
            ],
            createRevision(2, 9),
            {
                answered_question_ids: [201],
                existing_answers_map: {
                    201: 901
                },
                total_questions: 1
            }
        );

        fixture.manager.applyQuestionsResponse(questionPayload, {
            overwriteExisting: true
        });

        expect(fixture.state.questionOrderIds).toEqual([201]);
        expect(fixture.state.questionOrderSignature).toBe(questionPayload.question_order_signature);
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

    it('refreshes the active question, preserves revision-safe answers, and acknowledges the current revision marker', async function () {
        var fixture = createFixture({
            deps: {
                apiRequest: async function () {
                    return buildQuestionResponse(
                        fixture.cacheHelpers,
                        [
                            createQuestion(101, {
                                options: [
                                    { id: 21, option_key: 'A', option_text: 'Alpha baru', is_correct: 1 },
                                    { id: 22, option_key: 'B', option_text: 'Beta baru', is_correct: 0 }
                                ],
                                question_number: 1,
                                question_text: 'Stem revisi aktif',
                                updated_at: 'rev-1'
                            })
                        ],
                        createRevision(2, 9)
                    );
                },
                captureRevisionSafeLocalAnswers: function () {
                    return {
                        101: {
                            selected_option_keys: ['A']
                        }
                    };
                },
                restoreRevisionSafeLocalAnswers: function (snapshot) {
                    var question = fixture.state.questionPayloadById[101] || null;
                    var selectedKeys = snapshot && snapshot[101] && Array.isArray(snapshot[101].selected_option_keys)
                        ? snapshot[101].selected_option_keys
                        : [];
                    var matchedOption = question && Array.isArray(question.options)
                        ? question.options.find(function (option) {
                            return selectedKeys.indexOf(String(option && option.option_key || '')) >= 0;
                        })
                        : null;

                    fixture.state.answers[101] = Number(matchedOption && matchedOption.id) || fixture.state.answers[101];
                    fixture.state.answeredQuestionLookup[101] = true;
                }
            },
            state: {
                answers: {
                    101: 11
                },
                answeredQuestionLookup: {
                    101: true
                },
                questionManifest: [
                    {
                        id: 101,
                        options: [
                            { id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 },
                            { id: 12, option_key: 'B', option_text: 'Beta', is_correct: 0 }
                        ],
                        question_number: 1,
                        question_text: 'Stem lama',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    }
                ],
                questionManifestById: {
                    101: {
                        id: 101,
                        options: [
                            { id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 },
                            { id: 12, option_key: 'B', option_text: 'Beta', is_correct: 0 }
                        ],
                        question_number: 1,
                        question_text: 'Stem lama',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    }
                },
                questionPayloadById: {
                    101: createQuestion(101, {
                        options: [
                            { id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 },
                            { id: 12, option_key: 'B', option_text: 'Beta', is_correct: 0 }
                        ],
                        question_number: 1,
                        question_text: 'Stem lama',
                        updated_at: 'rev-1'
                    })
                },
                questions: [
                    createQuestion(101, {
                        options: [
                            { id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 },
                            { id: 12, option_key: 'B', option_text: 'Beta', is_correct: 0 }
                        ],
                        question_number: 1,
                        question_text: 'Stem lama',
                        updated_at: 'rev-1'
                    })
                ],
                questionRevision: createRevision(1, 9)
            }
        });

        var payload = await fixture.manager.refreshAttemptQuestionRevision(createRevision(2, 9), {
            attemptId: 55,
            examId: 9,
            preferredIndex: 0
        });

        expect(payload && payload.items && payload.items[0] && payload.items[0].question_text).toBe('Stem revisi aktif');
        expect(fixture.calls.apiRequest).toEqual([
            {
                options: {
                    query: {
                        attempt_id: 55,
                        exam_id: 9,
                        include_answer_manifest: 1,
                        include_existing: 1,
                        limit: 2,
                        offset: 0
                    }
                },
                path: 'questions'
            }
        ]);
        expect(fixture.state.questionPayloadById[101].question_text).toBe('Stem revisi aktif');
        expect(fixture.state.answers[101]).toBe(21);
        expect(fixture.state.changedQuestionLookup).toEqual({
            101: true
        });
        expect(fixture.state.questionRevisionMarkerLookup).toEqual({});
        expect(fixture.state.acknowledgedRevisionQuestionIds).toEqual({
            101: true
        });
        expect(fixture.state.questionRevisionNotice && fixture.state.questionRevisionNotice.message).toBe('1 soal berubah.');
    });

    it('tracks added questions without displacing the current question and keeps current answers intact', async function () {
        var fixture = createFixture({
            deps: {
                apiRequest: async function () {
                    return buildQuestionResponse(
                        fixture.cacheHelpers,
                        [
                            createQuestion(101, {
                                options: [
                                    { id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }
                                ],
                                question_number: 1,
                                question_text: 'Stem lama tetap',
                                updated_at: 'rev-1'
                            }),
                            createQuestion(202, {
                                question_number: 2,
                                question_text: 'Soal baru ditambahkan',
                                question_type: 'essay',
                                updated_at: 'rev-2'
                            })
                        ],
                        createRevision(2, 9),
                        {
                            limit: 2,
                            total_questions: 2
                        }
                    );
                }
            },
            state: {
                answers: {
                    101: 11
                },
                answeredQuestionLookup: {
                    101: true
                },
                currentIndex: 0,
                questionManifest: [
                    {
                        id: 101,
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem lama tetap',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    }
                ],
                questionManifestById: {
                    101: {
                        id: 101,
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem lama tetap',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    }
                },
                questionOrderIds: [101],
                questionPayloadById: {
                    101: createQuestion(101, {
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem lama tetap',
                        updated_at: 'rev-1'
                    })
                },
                questions: [
                    createQuestion(101, {
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem lama tetap',
                        updated_at: 'rev-1'
                    })
                ],
                questionRevision: createRevision(1, 9),
                totalQuestions: 1,
                windowLimit: 2
            }
        });

        await fixture.manager.refreshAttemptQuestionRevision(createRevision(2, 9), {
            attemptId: 55,
            examId: 9,
            preferredIndex: 0
        });

        expect(fixture.state.questionOrderIds).toEqual([101, 202]);
        expect(fixture.state.totalQuestions).toBe(2);
        expect(fixture.state.currentIndex).toBe(0);
        expect(fixture.state.answers[101]).toBe(11);
        expect(fixture.state.changedQuestionLookup).toEqual({
            202: true
        });
        expect(fixture.state.questionRevisionMarkerLookup).toEqual({
            202: true
        });
        expect(fixture.state.questionRevisionNotice && fixture.state.questionRevisionNotice.message).toBe('1 soal baru ditambahkan.');
        expect(fixture.calls.recordTimeline).toEqual(expect.arrayContaining([
            expect.objectContaining({
                kind: 'question-revision:added',
                meta: expect.objectContaining({
                    addedQuestionCount: 1,
                    totalQuestions: 2
                })
            })
        ]));
        expect(fixture.calls.recordActionTrail).toEqual(expect.arrayContaining([
            expect.objectContaining({
                kind: 'question-revision:added',
                meta: expect.objectContaining({
                    addedQuestionCount: 1,
                    totalQuestions: 2
                })
            })
        ]));
    });

    it('moves to the next valid question when the active question is removed without falling back to manual reload', async function () {
        var fixture = createFixture({
            deps: {
                apiRequest: async function () {
                    return buildQuestionResponse(
                        fixture.cacheHelpers,
                        [
                            createQuestion(202, {
                                options: [
                                    { id: 22, option_key: 'A', option_text: 'Soal pengganti', is_correct: 1 }
                                ],
                                question_number: 1,
                                question_text: 'Stem soal pengganti',
                                updated_at: 'rev-1'
                            })
                        ],
                        createRevision(2, 9),
                        {
                            limit: 2,
                            question_order_ids: [202],
                            total_questions: 1
                        }
                    );
                }
            },
            state: {
                answers: {
                    101: 11
                },
                answeredQuestionLookup: {
                    101: true
                },
                currentIndex: 0,
                questionManifest: [
                    {
                        id: 101,
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem aktif lama',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    },
                    {
                        id: 202,
                        options: [{ id: 22, option_key: 'A', option_text: 'Soal pengganti', is_correct: 1 }],
                        question_number: 2,
                        question_text: 'Stem soal pengganti',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    }
                ],
                questionManifestById: {
                    101: {
                        id: 101,
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem aktif lama',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    },
                    202: {
                        id: 202,
                        options: [{ id: 22, option_key: 'A', option_text: 'Soal pengganti', is_correct: 1 }],
                        question_number: 2,
                        question_text: 'Stem soal pengganti',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    }
                },
                questionOrderIds: [101, 202],
                questionPayloadById: {
                    101: createQuestion(101, {
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem aktif lama',
                        updated_at: 'rev-1'
                    }),
                    202: createQuestion(202, {
                        options: [{ id: 22, option_key: 'A', option_text: 'Soal pengganti', is_correct: 1 }],
                        question_number: 2,
                        question_text: 'Stem soal pengganti',
                        updated_at: 'rev-1'
                    })
                },
                questions: [
                    createQuestion(101, {
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem aktif lama',
                        updated_at: 'rev-1'
                    }),
                    createQuestion(202, {
                        options: [{ id: 22, option_key: 'A', option_text: 'Soal pengganti', is_correct: 1 }],
                        question_number: 2,
                        question_text: 'Stem soal pengganti',
                        updated_at: 'rev-1'
                    })
                ],
                questionRevision: createRevision(1, 9),
                totalQuestions: 2,
                windowLimit: 2
            }
        });

        await fixture.manager.refreshAttemptQuestionRevision(createRevision(2, 9), {
            attemptId: 55,
            examId: 9,
            preferredIndex: 0
        });

        expect(fixture.state.currentIndex).toBe(0);
        expect(fixture.state.questionOrderIds).toEqual([202]);
        expect(fixture.state.totalQuestions).toBe(1);
        expect(Object.keys(fixture.state.questionPayloadById).map(Number)).toEqual([202]);
        expect(fixture.state.answers).toEqual({});
        expect(fixture.state.answeredQuestionLookup).toEqual({});
        expect(fixture.state.questionRevisionNotice).toMatchObject({
            kind: 'current-question-warning',
            sticky: true,
            tone: 'warning'
        });
        expect(fixture.state.questionRevisionNotice.message).toContain('Soal aktif berubah karena revisi exam.');
        expect(fixture.calls.render.every(function (entry) {
            return entry.reason !== 'question-revision:manual-reload-notice';
        })).toBe(true);
    });

    it('keeps the current question stable and marks only non-current revised questions', async function () {
        var fixture = createFixture({
            deps: {
                apiRequest: async function () {
                    return buildQuestionResponse(
                        fixture.cacheHelpers,
                        [
                            createQuestion(101, {
                                options: [
                                    { id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }
                                ],
                                question_number: 1,
                                question_text: 'Stem lama tetap',
                                updated_at: 'rev-1'
                            }),
                            createQuestion(202, {
                                options: [
                                    { id: 77, option_key: 'A', option_text: 'Gamma baru', is_correct: 1 }
                                ],
                                question_number: 2,
                                question_text: 'Stem kedua direvisi',
                                updated_at: 'rev-2'
                            })
                        ],
                        createRevision(2, 9),
                        {
                            limit: 2,
                            total_questions: 2
                        }
                    );
                }
            },
            state: {
                currentIndex: 0,
                questionManifest: [
                    {
                        id: 101,
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem lama tetap',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    },
                    {
                        id: 202,
                        options: [{ id: 66, option_key: 'A', option_text: 'Gamma lama', is_correct: 1 }],
                        question_number: 2,
                        question_text: 'Stem kedua lama',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    }
                ],
                questionManifestById: {
                    101: {
                        id: 101,
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem lama tetap',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    },
                    202: {
                        id: 202,
                        options: [{ id: 66, option_key: 'A', option_text: 'Gamma lama', is_correct: 1 }],
                        question_number: 2,
                        question_text: 'Stem kedua lama',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    }
                },
                questionOrderIds: [101, 202],
                questionPayloadById: {
                    101: createQuestion(101, {
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem lama tetap',
                        updated_at: 'rev-1'
                    }),
                    202: createQuestion(202, {
                        options: [{ id: 66, option_key: 'A', option_text: 'Gamma lama', is_correct: 1 }],
                        question_number: 2,
                        question_text: 'Stem kedua lama',
                        updated_at: 'rev-1'
                    })
                },
                questions: [
                    createQuestion(101, {
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem lama tetap',
                        updated_at: 'rev-1'
                    }),
                    createQuestion(202, {
                        options: [{ id: 66, option_key: 'A', option_text: 'Gamma lama', is_correct: 1 }],
                        question_number: 2,
                        question_text: 'Stem kedua lama',
                        updated_at: 'rev-1'
                    })
                ],
                questionRevision: createRevision(1, 9),
                totalQuestions: 2,
                windowLimit: 2
            }
        });

        await fixture.manager.refreshAttemptQuestionRevision(createRevision(2, 9), {
            attemptId: 55,
            examId: 9,
            preferredIndex: 0
        });

        expect(fixture.state.currentIndex).toBe(0);
        expect(fixture.state.changedQuestionLookup).toEqual({
            202: true
        });
        expect(fixture.state.questionRevisionMarkerLookup).toEqual({
            202: true
        });
        expect(fixture.state.acknowledgedRevisionQuestionIds).toEqual({});
        expect(fixture.state.questionRevisionNotice && fixture.state.questionRevisionNotice.message).toBe('1 soal berubah.');
    });

    it('falls back to a sticky manual reload warning when the refreshed question order contract is invalid', async function () {
        var fixture = createFixture({
            deps: {
                apiRequest: async function () {
                    return buildQuestionResponse(
                        fixture.cacheHelpers,
                        [
                            createQuestion(202, {
                                options: [
                                    { id: 77, option_key: 'A', option_text: 'Payload tidak sinkron', is_correct: 1 }
                                ],
                                question_number: 2,
                                question_text: 'Payload konflik',
                                updated_at: 'rev-2'
                            })
                        ],
                        createRevision(2, 9),
                        {
                            question_order_ids: [101],
                            total_questions: 1
                        }
                    );
                }
            },
            state: {
                answers: {
                    101: 11
                },
                answeredQuestionLookup: {
                    101: true
                },
                questionManifest: [
                    {
                        id: 101,
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem lama',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    }
                ],
                questionManifestById: {
                    101: {
                        id: 101,
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem lama',
                        question_type: 'multiple_choice',
                        updated_at: 'rev-1'
                    }
                },
                questionPayloadById: {
                    101: createQuestion(101, {
                        options: [{ id: 11, option_key: 'A', option_text: 'Alpha', is_correct: 1 }],
                        question_number: 1,
                        question_text: 'Stem lama',
                        updated_at: 'rev-1'
                    })
                },
                questionRevision: createRevision(1, 9)
            }
        });

        var payload = await fixture.manager.refreshAttemptQuestionRevision(createRevision(2, 9), {
            attemptId: 55,
            examId: 9,
            preferredIndex: 0
        });

        expect(payload).toBe(null);
        expect(fixture.state.questionOrderIds).toEqual([101]);
        expect(fixture.state.questionPayloadById[101].question_text).toBe('Stem lama');
        expect(fixture.state.answers[101]).toBe(11);
        expect(fixture.calls.restoreQuestionAutoSaveState).toEqual([{}]);
        expect(fixture.state.questionRevisionNotice).toMatchObject({
            sticky: true,
            tone: 'warning'
        });
        expect(fixture.state.questionRevisionNotice.message).toContain('Muat ulang halaman');
        expect(fixture.calls.render[fixture.calls.render.length - 1]).toEqual({
            meta: {
                currentIndex: 0
            },
            reason: 'question-revision:manual-reload-notice'
        });
    });
});
