import { describe, expect, it } from 'vitest';
import { createExamSessionManager } from '../../../src/frontend/app/core/exam-session.js';

function buildCachedQuestionSnapshot(totalQuestions) {
    var questionOrderIds = [];
    var questionPayloadById = {};
    var questionManifest = [];
    var loadedQuestionWindowOffsets = {};

    for (var index = 0; index < totalQuestions; index++) {
        var questionId = 100 + index;
        questionOrderIds.push(questionId);
        questionPayloadById[questionId] = {
            id: questionId,
            question_number: index + 1,
            question_type: 'multiple_choice',
            question_text: 'Soal ' + String(index + 1)
        };
        questionManifest.push({
            id: questionId,
            question_number: index + 1
        });
        if (index % 10 === 0) {
            loadedQuestionWindowOffsets[index] = true;
        }
    }

    return {
        questionRevision: {
            exam_id: 55,
            version: 3,
            invalidated_at: 1710000000,
            signature: 'exam:55|v:3|t:1710000000'
        },
        questionOrderSignature: 'signature-40',
        questionOrderIds,
        questionManifest,
        questionPayloadById,
        answeredQuestionLookup: {},
        changedQuestionLookup: {},
        questionRevisionMarkerLookup: {},
        acknowledgedRevisionQuestionIds: {},
        answers: {},
        existingAnswerRawByQuestionId: {},
        loadedQuestionWindowOffsets,
        totalQuestions,
        windowOffset: 0,
        windowLimit: 10
    };
}

function createFixture(overrides = {}) {
    var state = Object.assign({
        attemptId: 0,
        openingAttemptProgressPercent: 0,
        openingAttemptProgressStepIndex: 0,
        openingAttemptProgressStepTotal: 0,
        openingAttemptProgressStatus: '',
        openingAttemptProgressDetail: '',
        stage: 'confirm',
        isOpeningAttempt: false,
        isFinishing: false,
        busy: false,
        remainingSeconds: 0,
        selectedExamId: 0,
        questionRevision: null,
        questions: [],
        questionOrderIds: [],
        questionManifest: [],
        questionManifestById: {},
        questionPayloadById: {},
        answeredQuestionLookup: {},
        changedQuestionLookup: {},
        questionRevisionMarkerLookup: {},
        acknowledgedRevisionQuestionIds: {},
        answers: {},
        existingAnswerRawByQuestionId: {},
        loadedQuestionWindowOffsets: {},
        windowOffset: 0,
        windowLimit: 0,
        totalQuestions: 0,
        currentIndex: 0,
        pendingSyncCount: 0,
        examLockedForPendingFinish: false,
        navPanelVisible: false,
        calculatorVisible: false,
        calculatorExpression: '',
        calculatorResult: '',
        calculatorError: '',
        notice: '',
        success: '',
        error: ''
    }, overrides.state || {});

    var calls = {
        apiCalls: [],
        initializeSubmittedPayloadCache: 0,
        loadQuestionWindow: [],
        persistCurrentQuestionCacheLocally: 0,
        queueLoadedQuestionAnswersForFlush: 0,
        renderSnapshots: []
    };
    var restoredSnapshot = overrides.restoredSnapshot || buildCachedQuestionSnapshot(40);

    var manager = createExamSessionManager({
        recordTimeline: function () {},
        state,
        apiRequest: async function (endpoint, options) {
            calls.apiCalls.push({
                endpoint,
                options: options || null
            });

            if (typeof overrides.apiRequest === 'function') {
                return overrides.apiRequest(endpoint, options);
            }

            if (endpoint === 'ui_state') {
                return { attempt_state: null };
            }

            throw new Error('Unexpected endpoint: ' + String(endpoint));
        },
        clearAttemptUiStateSyncTimer: function () {},
        clearAttemptUiSyncRuntimeState: function () {},
        clearAutoSaveRuntimeState: function () {},
        clearMessages: function () {},
        clearPendingRevisionSafeAnswerRestoreState: function () {},
        clearQuestionPrefetchRuntimeState: function () {},
        clearQuestionRevisionRefreshState: function () {},
        choosePreferredAttemptUiState: function () {
            return { current_index: 0 };
        },
        clearPersistedAttemptUiState: function () {},
        clearPersistedQuestionCache: function () {},
        ensureExamRuntimeBundle: async function () {},
        ensureExamStageRenderer: function () {
            return Promise.resolve(null);
        },
        ensureQuestionWindowForIndex: async function () {},
        examTokenLength: 6,
        clearSecurityLoggingRuntimeState: function () {},
        exitFullscreenSilently: function () {},
        findExamById: function () {
            return overrides.selectedExam || null;
        },
        getNavigatorConnectionStatus: function () {
            return 'online';
        },
        getQuestionCount: function () {
            return Array.isArray(state.questionOrderIds) && state.questionOrderIds.length
                ? state.questionOrderIds.length
                : Number(state.totalQuestions) || 0;
        },
        getSelectedExam: function () {
            return overrides.selectedExam || null;
        },
        initializeSubmittedPayloadCache: function () {
            calls.initializeSubmittedPayloadCache += 1;
        },
        isExamFullscreenRequired: function () {
            return false;
        },
        loadQuestionWindow: async function (offset, options) {
            calls.loadQuestionWindow.push({
                offset,
                options
            });
        },
        maybeFinalizeLockedExam: function () {},
        normalizeExamToken: function (value) {
            return String(value || '');
        },
        parseDateTime: function (value) {
            return value ? new Date(value) : null;
        },
        persistAuthSession: function () {},
        persistCurrentAttemptUiStateLocally: function () {},
        persistCurrentQuestionCacheLocally: function () {
            calls.persistCurrentQuestionCacheLocally += 1;
        },
        prefetchCalculatorFeature: function () {},
        prefetchResultStageRenderer: function () {},
        queueLoadedQuestionAnswersForFlush: function () {
            calls.queueLoadedQuestionAnswersForFlush += 1;
            return 0;
        },
        questionRevisionEquals: function () {
            return true;
        },
        questionWindowOffsetForIndex: function () {
            return 0;
        },
        readPersistedAttemptUiState: function () {
            return null;
        },
        readPersistedQuestionCache: async function () {
            return restoredSnapshot;
        },
        recordActionTrail: function () {},
        render: function () {
            calls.renderSnapshots.push({
                openingAttemptProgressDetail: String(state.openingAttemptProgressDetail || ''),
                openingAttemptProgressPercent: Number(state.openingAttemptProgressPercent) || 0,
                openingAttemptProgressStatus: String(state.openingAttemptProgressStatus || ''),
                openingAttemptProgressStepIndex: Number(state.openingAttemptProgressStepIndex) || 0,
                stage: String(state.stage || '')
            });
        },
        requestExamFullscreen: function () {
            return Promise.resolve(null);
        },
        resetQuestionDataState: function (options) {
            state.questions = [];
            state.questionOrderIds = [];
            state.questionManifest = [];
            state.questionManifestById = {};
            state.questionPayloadById = {};
            state.answeredQuestionLookup = {};
            state.changedQuestionLookup = {};
            state.questionRevisionMarkerLookup = {};
            state.acknowledgedRevisionQuestionIds = {};
            state.answers = {};
            state.existingAnswerRawByQuestionId = {};
            state.loadedQuestionWindowOffsets = {};
            state.windowOffset = 0;
            state.windowLimit = 0;
            state.totalQuestions = 0;
            if (!(options && options.preserveQuestionRevision)) {
                state.questionRevision = null;
            }
        },
        resetQuestionPrefetchIdleTimer: function () {},
        scheduleAttemptUiStateSync: function () {},
        schedulePendingAnswerRetry: function () {},
        setConnectionStatus: function () {},
        setQuestionRevision: function (revision) {
            state.questionRevision = revision;
        },
        startSessionHeartbeat: function () {},
        startTimer: function () {},
        syncAttemptUiStateSignatureToCurrentState: function () {},
        syncFullscreenState: function () {},
        syncPendingAnswerRuntimeState: function () {},
        applyAttemptUiState: function (attemptUiState) {
            state.currentIndex = Number(attemptUiState && attemptUiState.current_index !== undefined ? attemptUiState.current_index : 0) || 0;
        },
        applyPersistedQuestionCache: function (snapshot) {
            if (!snapshot) {
                return false;
            }

            state.questionRevision = snapshot.questionRevision;
            state.questionOrderIds = snapshot.questionOrderIds.slice();
            state.totalQuestions = snapshot.totalQuestions;
            state.questionPayloadById = Object.assign({}, snapshot.questionPayloadById);
            state.loadedQuestionWindowOffsets = Object.assign({}, snapshot.loadedQuestionWindowOffsets);
            state.questionManifest = snapshot.questionManifest.slice();
            state.windowOffset = snapshot.windowOffset;
            state.windowLimit = snapshot.windowLimit;
            state.questions = snapshot.questionOrderIds.slice(0, snapshot.windowLimit).map(function (questionId) {
                return snapshot.questionPayloadById[questionId];
            });

            return true;
        },
        bumpQuestionDataGeneration: function () {},
        startAttemptTimeoutMs: overrides.startAttemptTimeoutMs,
        startAttemptRecoveryTimeoutMs: overrides.startAttemptRecoveryTimeoutMs,
        startAttemptRecoveryPollDelayMs: overrides.startAttemptRecoveryPollDelayMs
    });

    return {
        calls,
        manager,
        restoredSnapshot,
        state
    };
}

describe('createExamSessionManager', function () {
    it('keeps restored prefetched question cache after refresh instead of dropping back to the initial window size', async function () {
        var fixture = createFixture({
            selectedExam: {
                id: 55,
                duration_minutes: 60
            }
        });

        await fixture.manager.openAttemptSession(
            {
                id: 55,
                duration_minutes: 60
            },
            {
                attempt_id: 77,
                duration_minutes: 60,
                started_at: '2026-04-03 05:00:00',
                status: 'resume',
                question_order_signature: 'signature-40',
                question_revision: fixture.restoredSnapshot.questionRevision
            }
        );

        expect(Object.keys(fixture.state.questionPayloadById)).toHaveLength(40);
        expect(fixture.state.totalQuestions).toBe(40);
        expect(fixture.state.loadedQuestionWindowOffsets).toEqual({
            0: true,
            10: true,
            20: true,
            30: true
        });
        expect(fixture.calls.initializeSubmittedPayloadCache).toBe(0);
        expect(fixture.calls.loadQuestionWindow).toHaveLength(1);
        expect(fixture.calls.persistCurrentQuestionCacheLocally).toBe(1);
        expect(fixture.calls.queueLoadedQuestionAnswersForFlush).toBe(1);
    });

    it('emits staged opening progress while preparing the initial exam window', async function () {
        var fixture = createFixture({
            selectedExam: {
                id: 55,
                duration_minutes: 60
            }
        });

        await fixture.manager.openAttemptSession(
            {
                id: 55,
                duration_minutes: 60
            },
            {
                attempt_id: 77,
                duration_minutes: 60,
                started_at: '2026-04-03 05:00:00',
                status: 'resume',
                question_order_signature: 'signature-40',
                question_revision: fixture.restoredSnapshot.questionRevision
            }
        );

        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.openingAttemptProgressStepIndex === 2
                && snapshot.openingAttemptProgressStatus.indexOf('runtime ujian') >= 0;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.openingAttemptProgressStepIndex === 3
                && snapshot.openingAttemptProgressStatus.indexOf('data lokal') >= 0;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.openingAttemptProgressStepIndex === 4
                && snapshot.openingAttemptProgressStatus.indexOf('soal awal') >= 0;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.openingAttemptProgressStepIndex === 5
                && snapshot.openingAttemptProgressStatus.indexOf('Finalisasi') >= 0;
        })).toBe(true);
        expect(fixture.state.openingAttemptProgressPercent).toBe(0);
        expect(fixture.state.openingAttemptProgressStepIndex).toBe(0);
        expect(fixture.state.openingAttemptProgressStatus).toBe('');
        expect(fixture.state.openingAttemptProgressDetail).toBe('');
    });

    it('recovers a congested start flow by resuming the active attempt after a lock response', async function () {
        var restoredSnapshot = buildCachedQuestionSnapshot(40);
        var fixture = createFixture({
            restoredSnapshot,
            selectedExam: {
                id: 55,
                duration_minutes: 60,
                is_class_allowed: 1
            },
            startAttemptRecoveryPollDelayMs: 0,
            apiRequest: async function (endpoint, options) {
                if (endpoint === 'ui_state') {
                    return { attempt_state: null };
                }

                if (endpoint !== 'start_attempt') {
                    throw new Error('Unexpected endpoint: ' + String(endpoint));
                }

                var body = options && options.body ? options.body : {};
                if (Number(body.resume_only) === 1) {
                    return {
                        attempt_id: 77,
                        duration_minutes: 60,
                        started_at: '2026-04-03 05:00:00',
                        status: 'resumed',
                        question_order_signature: restoredSnapshot.questionOrderSignature,
                        question_revision: restoredSnapshot.questionRevision
                    };
                }

                throw Object.assign(new Error('Permintaan mulai ujian sedang diproses. Coba lagi beberapa detik.'), {
                    code: 'attempt_lock_active',
                    status: 429
                });
            }
        });

        await fixture.manager.handleStartExam();

        expect(fixture.state.stage).toBe('exam');
        expect(fixture.state.error).toBe('');
        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint + ':' + String(
                entry.options && entry.options.body && entry.options.body.resume_only ? 1 : 0
            );
        })).toEqual([
            'start_attempt:0',
            'start_attempt:1',
            'ui_state:0'
        ]);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.openingAttemptProgressStatus.indexOf('Mengecek attempt aktif') >= 0;
        })).toBe(true);
        expect(fixture.calls.loadQuestionWindow).toHaveLength(1);
    });
});
