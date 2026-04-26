import { describe, expect, it, vi } from 'vitest';
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

function createDeferredPromise() {
    var resolve;
    var reject;
    var promise = new Promise(function (innerResolve, innerReject) {
        resolve = innerResolve;
        reject = innerReject;
    });

    return {
        promise,
        reject,
        resolve
    };
}

async function waitForAssertion(assertion, attempts) {
    var remaining = Math.max(1, Number(attempts) || 20);
    var lastError = null;

    while (remaining > 0) {
        try {
            assertion();
            return;
        } catch (error) {
            lastError = error;
            remaining -= 1;
            if (remaining <= 0) {
                throw lastError;
            }
            await flushAsyncWork();
        }
    }
}

async function flushAsyncWork() {
    await Promise.resolve();
    await Promise.resolve();
    await new Promise(function (resolve) {
        setTimeout(resolve, 0);
    });
}

function createFixture(overrides = {}) {
    var state = Object.assign({
        attemptId: 0,
        authProgressVisible: false,
        authProgressMode: '',
        authProgressPercent: 0,
        authProgressStepIndex: 0,
        authProgressStepTotal: 0,
        authProgressStatus: '',
        authProgressDetail: '',
        resultProgressVisible: false,
        resultProgressPercent: 0,
        resultProgressStepIndex: 0,
        resultProgressStepTotal: 0,
        resultProgressStatus: '',
        resultProgressDetail: '',
        sessionRecoveryVisible: false,
        sessionRecoveryMode: '',
        sessionRecoveryPercent: 0,
        sessionRecoveryStepIndex: 0,
        sessionRecoveryStepTotal: 0,
        sessionRecoveryStatus: '',
        sessionRecoveryDetail: '',
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
        pendingExamId: 0,
        pendingExamToken: '',
        pendingStartIntentKey: '',
        pendingQueueTicket: '',
        pendingResumeIntent: false,
        pendingOpeningPhase: '',
        pendingLastErrorCode: '',
        pendingLastErrorMessage: '',
        openingAttemptPhase: '',
        openingAttemptCanRetry: false,
        openingAttemptCanRefreshStatus: false,
        openingAttemptCanBack: false,
        openingAttemptQueuePosition: 0,
        openingAttemptQueueEstimatedWaitSeconds: 0,
        openingAttemptQueueLastPolledAt: 0,
        openingAttemptServerState: '',
        openingAttemptServerReason: '',
        openingAttemptServerResumeSource: '',
        openingAttemptWaitAgeSeconds: 0,
        openingAttemptLastStageAt: 0,
        openingRetryAttemptCount: 0,
        openingRetryNextAt: 0,
        openingRetryDelayMs: 0,
        openingRetryReason: '',
        openingRetryCountdownSeconds: 0,
        openingRetryInFlight: false,
        openingRetryLastTrigger: '',
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
        ensureQuestionWindowForIndex: [],
        initializeSubmittedPayloadCache: 0,
        loadQuestionWindow: [],
        ensureResultStageRenderer: [],
        persistCurrentAttemptUiStateLocally: 0,
        persistCurrentQuestionCacheLocally: 0,
        queueLoadedQuestionAnswersForFlush: 0,
        clearMessages: 0,
        renderCalls: [],
        renderSnapshots: []
    };
    var restoredSnapshot = overrides.restoredSnapshot || buildCachedQuestionSnapshot(40);

    function resolveSelectedExam() {
        if (typeof overrides.getSelectedExam === 'function') {
            return overrides.getSelectedExam(state);
        }

        if (overrides.selectedExam) {
            return overrides.selectedExam;
        }

        var selectedExamId = Number(state.selectedExamId) || 0;
        if (selectedExamId <= 0 || !Array.isArray(state.exams)) {
            return null;
        }

        for (var index = 0; index < state.exams.length; index++) {
            if (Number(state.exams[index] && state.exams[index].id) === selectedExamId) {
                return state.exams[index];
            }
        }

        return null;
    }

    function resolveExamById(examId) {
        if (typeof overrides.findExamById === 'function') {
            return overrides.findExamById(examId, state);
        }

        if (overrides.selectedExam && Number(overrides.selectedExam.id) === Number(examId)) {
            return overrides.selectedExam;
        }

        if (!Array.isArray(state.exams)) {
            return null;
        }

        for (var index = 0; index < state.exams.length; index++) {
            if (Number(state.exams[index] && state.exams[index].id) === Number(examId)) {
                return state.exams[index];
            }
        }

        return null;
    }

    var manager = createExamSessionManager({
        recordTimeline: function () {},
        state,
        apiRequest: async function (endpoint, options) {
            calls.apiCalls.push({
                endpoint,
                options: options || null
            });

            if (endpoint === 'entry_flow_metric') {
                return {
                    ok: true,
                    duplicate: false,
                    skipped: false
                };
            }

            if (typeof overrides.apiRequest === 'function') {
                return overrides.apiRequest(endpoint, options);
            }

            if (endpoint === 'exams') {
                return {
                    current_user: null,
                    items: Array.isArray(state.exams) && state.exams.length
                        ? state.exams
                        : (overrides.selectedExam ? [overrides.selectedExam] : [])
                };
            }

            if (endpoint === 'ui_state') {
                return { attempt_state: null };
            }

            throw new Error('Unexpected endpoint: ' + String(endpoint));
        },
        clearAttemptUiStateSyncTimer: function () {},
        clearAttemptUiSyncRuntimeState: function () {},
        clearAutoSaveRuntimeState: function () {},
        clearMessages: function () {
            calls.clearMessages += 1;
        },
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
        ensureResultStageRenderer: function (options) {
            calls.ensureResultStageRenderer.push(options || {});
            if (typeof overrides.ensureResultStageRenderer === 'function') {
                return overrides.ensureResultStageRenderer(options);
            }
            return Promise.resolve(null);
        },
        ensureQuestionWindowForIndex: async function (index, options) {
            calls.ensureQuestionWindowForIndex.push({
                index,
                options
            });
            if (typeof overrides.ensureQuestionWindowForIndex === 'function') {
                return overrides.ensureQuestionWindowForIndex(index, options);
            }
        },
        examTokenLength: 6,
        clearSecurityLoggingRuntimeState: function () {},
        exitFullscreenSilently: function () {},
        findExamById: function (examId) {
            return resolveExamById(examId);
        },
        getNavigatorConnectionStatus: function () {
            return 'online';
        },
        getChangedQuestionCount: function () {
            if (typeof overrides.getChangedQuestionCount === 'function') {
                return overrides.getChangedQuestionCount(state);
            }
            return Object.keys(state.changedQuestionLookup || {}).length;
        },
        getQuestionCount: function () {
            return Array.isArray(state.questionOrderIds) && state.questionOrderIds.length
                ? state.questionOrderIds.length
                : Number(state.totalQuestions) || 0;
        },
        getSelectedExam: function () {
            return resolveSelectedExam();
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
        persistCurrentAttemptUiStateLocally: function () {
            calls.persistCurrentAttemptUiStateLocally += 1;
        },
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
        render: function (reason, meta, options) {
            calls.renderCalls.push({
                meta: meta && typeof meta === 'object' ? Object.assign({}, meta) : {},
                options: options && typeof options === 'object' ? Object.assign({}, options) : {},
                reason: String(reason || '')
            });
            calls.renderSnapshots.push({
                authProgressDetail: String(state.authProgressDetail || ''),
                authProgressMode: String(state.authProgressMode || ''),
                authProgressPercent: Number(state.authProgressPercent) || 0,
                authProgressStatus: String(state.authProgressStatus || ''),
                authProgressStepIndex: Number(state.authProgressStepIndex) || 0,
                authProgressVisible: Boolean(state.authProgressVisible),
                openingAttemptProgressDetail: String(state.openingAttemptProgressDetail || ''),
                openingAttemptPhase: String(state.openingAttemptPhase || ''),
                openingAttemptCanBack: Boolean(state.openingAttemptCanBack),
                openingAttemptCanRefreshStatus: Boolean(state.openingAttemptCanRefreshStatus),
                openingAttemptCanRetry: Boolean(state.openingAttemptCanRetry),
                openingAttemptProgressPercent: Number(state.openingAttemptProgressPercent) || 0,
                openingAttemptProgressStatus: String(state.openingAttemptProgressStatus || ''),
                openingAttemptProgressStepIndex: Number(state.openingAttemptProgressStepIndex) || 0,
                openingAttemptQueuePosition: Number(state.openingAttemptQueuePosition) || 0,
                openingAttemptServerState: String(state.openingAttemptServerState || ''),
                openingAttemptServerReason: String(state.openingAttemptServerReason || ''),
                openingAttemptWaitAgeSeconds: Number(state.openingAttemptWaitAgeSeconds) || 0,
                pendingLastErrorCode: String(state.pendingLastErrorCode || ''),
                pendingLastErrorMessage: String(state.pendingLastErrorMessage || ''),
                openingRetryCountdownSeconds: Number(state.openingRetryCountdownSeconds) || 0,
                openingRetryInFlight: Boolean(state.openingRetryInFlight),
                resultProgressDetail: String(state.resultProgressDetail || ''),
                resultProgressPercent: Number(state.resultProgressPercent) || 0,
                resultProgressStatus: String(state.resultProgressStatus || ''),
                resultProgressStepIndex: Number(state.resultProgressStepIndex) || 0,
                resultProgressVisible: Boolean(state.resultProgressVisible),
                sessionRecoveryDetail: String(state.sessionRecoveryDetail || ''),
                sessionRecoveryMode: String(state.sessionRecoveryMode || ''),
                sessionRecoveryStatus: String(state.sessionRecoveryStatus || ''),
                sessionRecoveryStepIndex: Number(state.sessionRecoveryStepIndex) || 0,
                sessionRecoveryVisible: Boolean(state.sessionRecoveryVisible),
                error: String(state.error || ''),
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
        windowRef: overrides.windowRef || globalThis,
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
        startAttemptStatusTimeoutMs: overrides.startAttemptStatusTimeoutMs,
        startAttemptRecoveryTimeoutMs: overrides.startAttemptRecoveryTimeoutMs,
        startAttemptRecoveryPollDelayMs: overrides.startAttemptRecoveryPollDelayMs,
        openingRetryDiagnosticThreshold: overrides.openingRetryDiagnosticThreshold,
        openingRetryDiagnosticInterval: overrides.openingRetryDiagnosticInterval
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

    it('loads the first question window without waiting for ui_state to finish', async function () {
        var uiStateDeferred = createDeferredPromise();
        var fixture = createFixture({
            selectedExam: {
                id: 55,
                duration_minutes: 60
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'ui_state') {
                    return uiStateDeferred.promise;
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
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

        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint;
        })).toEqual(['ui_state']);
        expect(fixture.calls.apiCalls[0].options).toMatchObject({
            suppressAuthExpiry: true
        });
        expect(fixture.calls.loadQuestionWindow).toHaveLength(1);
        expect(fixture.state.stage).toBe('exam');

        uiStateDeferred.resolve({ attempt_state: null });
        await flushAsyncWork();
    });

    it('reconciles to the server resume index after the first window is ready when the user has not interacted', async function () {
        var uiStateDeferred = createDeferredPromise();
        var fixture = createFixture({
            selectedExam: {
                id: 55,
                duration_minutes: 60
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'ui_state') {
                    return uiStateDeferred.promise;
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
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

        expect(fixture.state.currentIndex).toBe(0);

        uiStateDeferred.resolve({
            attempt_state: {
                current_index: 12,
                doubtful_question_ids: [112]
            }
        });
        await flushAsyncWork();

        expect(fixture.state.currentIndex).toBe(12);
        expect(fixture.calls.ensureQuestionWindowForIndex.map(function (entry) {
            return entry.index;
        })).toContain(12);
        expect(fixture.calls.renderCalls.some(function (entry) {
            return entry.reason === 'attempt-ui-state-deferred-reconcile';
        })).toBe(true);
        expect(fixture.calls.persistCurrentAttemptUiStateLocally).toBeGreaterThanOrEqual(2);
    });

    it('does not auto-jump to the server resume index after the user has already moved locally', async function () {
        var uiStateDeferred = createDeferredPromise();
        var fixture = createFixture({
            selectedExam: {
                id: 55,
                duration_minutes: 60
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'ui_state') {
                    return uiStateDeferred.promise;
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
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

        fixture.state.currentIndex = 4;

        uiStateDeferred.resolve({
            attempt_state: {
                current_index: 12,
                doubtful_question_ids: [112]
            }
        });
        await flushAsyncWork();

        expect(fixture.state.currentIndex).toBe(4);
        expect(fixture.calls.ensureQuestionWindowForIndex.map(function (entry) {
            return entry.index;
        })).not.toContain(12);
        expect(fixture.calls.renderCalls.some(function (entry) {
            return entry.reason === 'attempt-ui-state-deferred-reconcile';
        })).toBe(false);
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

    it('updates recovery progress while reopening an exam from a persisted session refresh', async function () {
        var fixture = createFixture({
            state: {
                sessionRecoveryVisible: true,
                sessionRecoveryMode: 'exam_restore',
                sessionRecoveryStepIndex: 4,
                sessionRecoveryStepTotal: 7,
                selectedExamId: 55
            },
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
            return snapshot.sessionRecoveryVisible
                && snapshot.sessionRecoveryMode === 'exam_restore'
                && snapshot.sessionRecoveryStepIndex === 5
                && snapshot.sessionRecoveryStatus.indexOf('window soal') >= 0;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.sessionRecoveryVisible
                && snapshot.sessionRecoveryStepIndex === 6
                && snapshot.sessionRecoveryStatus.indexOf('jawaban lokal') >= 0;
        })).toBe(true);
        expect(fixture.state.sessionRecoveryStatus).toContain('Menyinkronkan jawaban tertunda');
        expect(fixture.state.sessionRecoveryStepIndex).toBe(7);
    });

    it('shows staged auth progress while login prepares the exam dashboard', async function () {
        var fixture = createFixture({
            state: {
                stage: 'login'
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'login') {
                    return {
                        token: 'token-login-123',
                        user_id: 19,
                        role: 'student',
                        display_name: 'Ayu',
                        username: 'ayu',
                        email: 'ayu@example.test',
                        kode_kelas: 'XII IPA 1',
                        kode_ruang: 'R-3',
                        agama: 'Islam',
                        foto: '/uploads/ayu.jpg'
                    };
                }

                if (endpoint === 'exams') {
                    return {
                        current_user: {
                            user_id: 19,
                            role: 'student',
                            display_name: 'Ayu',
                            username: 'ayu',
                            email: 'ayu@example.test',
                            kode_kelas: 'XII IPA 1',
                            kode_ruang: 'R-3',
                            agama: 'Islam',
                            foto: '/uploads/ayu.jpg'
                        },
                        items: [
                            {
                                id: 55,
                                title: 'TOBK Biologi'
                            }
                        ]
                    };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });
        var form = document.createElement('form');
        form.innerHTML = [
            '<input name="identifier" value="ayu" />',
            '<input name="password" value="secret-pass" />'
        ].join('');

        await fixture.manager.handleLogin(form);

        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint;
        })).toEqual(['login', 'exams', 'entry_flow_metric']);
        expect(fixture.calls.apiCalls[2].options.body).toMatchObject({
            flow: 'login_to_exam_list'
        });
        expect(fixture.calls.apiCalls[2].options.suppressAuthExpiry).toBe(true);
        expect(Number(fixture.calls.apiCalls[2].options.body.duration_ms) || 0).toBeGreaterThanOrEqual(0);
        expect(fixture.calls.apiCalls[2].options.body.phase_durations).toHaveProperty('login_request_ms');
        expect(fixture.calls.apiCalls[2].options.body.phase_durations).toHaveProperty('login_exam_list_ms');
        expect(fixture.calls.renderCalls[0]).toMatchObject({
            meta: {
                phase: 'auth-progress-initial'
            },
            options: {
                immediate: true,
                skipPostRenderEffects: true
            },
            reason: 'login-submit'
        });
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.authProgressMode === 'login'
                && snapshot.authProgressStepIndex === 1
                && snapshot.authProgressStatus.indexOf('Menghubungi server login') >= 0;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.authProgressMode === 'login'
                && snapshot.authProgressStepIndex === 3
                && snapshot.authProgressStatus.indexOf('Memuat daftar ujian') >= 0;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots[fixture.calls.renderSnapshots.length - 1]).toMatchObject({
            authProgressMode: '',
            authProgressPercent: 0,
            authProgressStatus: '',
            authProgressStepIndex: 0,
            authProgressVisible: false,
            stage: 'confirm'
        });
        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.selectedExamId).toBe(55);
        expect(fixture.state.token).toBe('token-login-123');
        expect(fixture.state.authProgressVisible).toBe(false);
        expect(fixture.state.authProgressPercent).toBe(0);
        expect(fixture.state.authProgressStatus).toBe('');
        expect(fixture.state.loginIdentifier).toBe('');
        expect(fixture.state.loginPassword).toBe('');
    });

    it('ignores stale login form submits after the app has left the login stage', async function () {
        var fixture = createFixture({
            state: {
                stage: 'confirm',
                token: 'token-active',
                user: {
                    user_id: 19,
                    display_name: 'Ayu'
                }
            },
            apiRequest: async function (endpoint) {
                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });
        var staleForm = document.createElement('form');
        staleForm.innerHTML = [
            '<input name="identifier" value="ayu" />',
            '<input name="password" value="secret-pass" />'
        ].join('');

        await fixture.manager.handleLogin(staleForm);

        expect(fixture.calls.apiCalls).toEqual([]);
        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.token).toBe('token-active');
        expect(fixture.state.error).toBe('');
    });

    it('keeps login successful and safe when the account has no visible exams', async function () {
        var fixture = createFixture({
            state: {
                selectedExamId: 99,
                stage: 'login'
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'login') {
                    return {
                        token: 'token-login-123',
                        user_id: 19,
                        role: 'student',
                        display_name: 'Ayu',
                        username: 'ayu',
                        email: 'ayu@example.test',
                        kode_kelas: 'XII IPA 1',
                        kode_ruang: 'R-3',
                        agama: 'Islam',
                        foto: ''
                    };
                }

                if (endpoint === 'exams') {
                    return {
                        current_user: {
                            user_id: 19,
                            role: 'student',
                            display_name: 'Ayu',
                            username: 'ayu',
                            email: 'ayu@example.test',
                            kode_kelas: 'XII IPA 1',
                            kode_ruang: 'R-3',
                            agama: 'Islam',
                            foto: ''
                        },
                        items: []
                    };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });
        var form = document.createElement('form');
        form.innerHTML = [
            '<input name="identifier" value="ayu" />',
            '<input name="password" value="secret-pass" />'
        ].join('');

        await fixture.manager.handleLogin(form);

        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint;
        })).toEqual(['login', 'exams', 'entry_flow_metric']);
        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.exams).toEqual([]);
        expect(fixture.state.selectedExamId).toBe(0);
        expect(fixture.state.examToken).toBe('');
        expect(fixture.state.token).toBe('token-login-123');
        expect(fixture.state.error).toBe('');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.isOpeningAttempt).toBe(false);
    });

    it('starts a login retry countdown when invalid credentials include retry_after', async function () {
        vi.useFakeTimers();
        try {
            var fixture = createFixture({
                state: {
                    stage: 'login'
                },
                apiRequest: async function (endpoint) {
                    if (endpoint === 'login') {
                        var error = new Error('Invalid identifier or password');
                        error.code = 'invalid_credentials';
                        error.status = 401;
                        error.data = {
                            retry_after: 5
                        };
                        throw error;
                    }

                    throw new Error('Unexpected endpoint: ' + String(endpoint));
                }
            });
            var form = document.createElement('form');
            form.innerHTML = [
                '<input name="identifier" value="ayu" />',
                '<input name="password" value="wrong-pass" />'
            ].join('');

            await fixture.manager.handleLogin(form);

            expect(fixture.state.stage).toBe('login');
            expect(fixture.state.error).toBe('Invalid identifier or password');
            expect(fixture.state.loginRateLimitRemaining).toBe(5);
            expect(fixture.calls.apiCalls.map(function (entry) {
                return entry.endpoint;
            })).toEqual(['login']);

            vi.advanceTimersByTime(1000);
            expect(fixture.state.loginRateLimitRemaining).toBe(4);

            vi.advanceTimersByTime(4000);
            expect(fixture.state.loginRateLimitRemaining).toBe(0);
            expect(fixture.state.error).toBe('');
        } finally {
            vi.useRealTimers();
        }
    });

    it('keeps the student on confirm when manual exam token is missing', async function () {
        var fixture = createFixture({
            state: {
                examToken: '',
                selectedExamId: 55,
                stage: 'confirm'
            },
            selectedExam: {
                id: 55,
                duration_minutes: 60,
                is_class_allowed: 1,
                latest_attempt_status: '',
                requires_token: 1,
                token_input_required: 1
            },
            apiRequest: async function (endpoint) {
                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.handleStartExam();

        expect(fixture.calls.apiCalls).toHaveLength(0);
        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.isOpeningAttempt).toBe(false);
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.error).toBe('Token ujian wajib diisi.');
        expect(fixture.state.openingAttemptProgressStatus).toBe('');
        expect(fixture.calls.renderCalls[0]).toMatchObject({
            meta: {
                code: 'token_required_local',
                selectedExamId: 55
            },
            reason: 'start-exam-token-validation'
        });
    });

    it('keeps the student on confirm when the selected exam is not locally available', async function () {
        var fixture = createFixture({
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_available_now: 0,
                        is_class_allowed: 1,
                        is_within_schedule: 0,
                        availability_reason: 'not_started',
                        latest_attempt_id: 0,
                        latest_attempt_status: '',
                        requires_token: 0,
                        token_input_required: 0
                    }
                ],
                selectedExamId: 55,
                stage: 'confirm'
            },
            apiRequest: async function (endpoint) {
                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.handleStartExam();

        expect(fixture.calls.apiCalls).toHaveLength(0);
        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.isOpeningAttempt).toBe(false);
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.error).toBe('Exam ini belum dimulai.');
        expect(fixture.calls.renderCalls[0]).toMatchObject({
            meta: {
                code: 'exam_not_started',
                selectedExamId: 55
            },
            reason: 'start-exam-availability-validation'
        });
    });

    it('keeps auth local and shows a terminal opening message when server misses the auth header', async function () {
        var fixture = createFixture({
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_available_now: 1,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: '',
                        requires_token: 0,
                        token_input_required: 0
                    }
                ],
                selectedExamId: 55,
                stage: 'confirm'
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'start_attempt') {
                    var error = new Error('Authorization token not found');
                    error.code = 'missing_token';
                    error.status = 401;
                    throw error;
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.handleStartExam({
            skipExamRefresh: true
        });

        expect(fixture.state.stage).toBe('exam');
        expect(fixture.state.isOpeningAttempt).toBe(true);
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.error).toBe('Authorization token not found');
        expect(fixture.state.openingAttemptPhase).toBe('opening_terminal_error');
        expect(fixture.state.openingAttemptCanBack).toBe(true);
        expect(fixture.state.openingAttemptProgressStatus).toBe('Sesi login tidak diterima server');
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
                if (endpoint === 'exams') {
                    return {
                        current_user: null,
                        items: [
                            {
                                id: 55,
                                duration_minutes: 60,
                                is_class_allowed: 1,
                                latest_attempt_id: 0,
                                latest_attempt_status: ''
                            }
                        ]
                    };
                }

                if (endpoint === 'ui_state') {
                    return { attempt_state: null };
                }

                if (endpoint === 'start_attempt_status') {
                    return {
                        attempt_id: 77,
                        duration_minutes: 60,
                        started_at: '2026-04-03 05:00:00',
                        status: 'resumed',
                        question_order_signature: restoredSnapshot.questionOrderSignature,
                        question_revision: restoredSnapshot.questionRevision
                    };
                }

                if (endpoint === 'start_attempt_status') {
                    return {
                        status: 'admitted',
                        queue_ticket: 'gate-ticket-1',
                        queue_position: 1,
                        poll_after_ms: 0,
                        estimated_wait_seconds: 0,
                        gate_capacity: 50,
                        gate_window_seconds: 5
                    };
                }

                if (endpoint !== 'start_attempt') {
                    throw new Error('Unexpected endpoint: ' + String(endpoint));
                }

                var body = options && options.body ? options.body : {};
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
            'exams:0',
            'start_attempt:0',
            'start_attempt_status:1',
            'ui_state:0',
            'entry_flow_metric:0'
        ]);
        expect(fixture.calls.apiCalls[4].options.body).toMatchObject({
            flow: 'resume_to_first_question',
            attempt_id: 77,
            exam_id: 55
        });
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.openingAttemptProgressStatus.indexOf('Mengecek attempt aktif') >= 0;
        })).toBe(true);
        expect(fixture.calls.loadQuestionWindow).toHaveLength(1);
    });

    it('waits on queued start_attempt responses and continues automatically when admitted', async function () {
        var restoredSnapshot = buildCachedQuestionSnapshot(40);
        var fixture = createFixture({
            restoredSnapshot,
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: ''
                    }
                ],
                selectedExamId: 55
            },
            apiRequest: async function (endpoint, options) {
                if (endpoint === 'exams') {
                    return {
                        current_user: null,
                        items: [
                            {
                                id: 55,
                                duration_minutes: 60,
                                is_class_allowed: 1,
                                latest_attempt_id: 0,
                                latest_attempt_status: ''
                            }
                        ]
                    };
                }

                if (endpoint === 'ui_state') {
                    return { attempt_state: null };
                }

                if (endpoint === 'start_attempt_status') {
                    return {
                        status: 'admitted',
                        queue_ticket: 'gate-ticket-1',
                        queue_position: 1,
                        poll_after_ms: 0,
                        estimated_wait_seconds: 0,
                        gate_capacity: 50,
                        gate_window_seconds: 5
                    };
                }

                if (endpoint !== 'start_attempt') {
                    throw new Error('Unexpected endpoint: ' + String(endpoint));
                }

                var body = options && options.body ? options.body : {};
                if (String(body.queue_ticket || '') === 'gate-ticket-1') {
                    return {
                        attempt_id: 77,
                        duration_minutes: 60,
                        started_at: '2026-04-03 05:00:00',
                        status: 'started',
                        question_order_signature: restoredSnapshot.questionOrderSignature,
                        question_revision: restoredSnapshot.questionRevision
                    };
                }

                return {
                    status: 'queued',
                    queue_ticket: 'gate-ticket-1',
                    queue_position: 14,
                    poll_after_ms: 0,
                    estimated_wait_seconds: 2,
                    gate_capacity: 50,
                    gate_window_seconds: 5
                };
            }
        });

        await fixture.manager.handleStartExam();

        expect(fixture.state.stage).toBe('exam');
        expect(fixture.state.error).toBe('');
        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint + ':' + String(
                entry.options && entry.options.body && entry.options.body.queue_ticket ? 'queue' : 'fresh'
            );
        })).toEqual([
            'exams:fresh',
            'start_attempt:fresh',
            'start_attempt_status:queue',
            'start_attempt:queue',
            'ui_state:fresh',
            'entry_flow_metric:fresh'
        ]);
        expect(fixture.calls.apiCalls[5].options.body).toMatchObject({
            flow: 'start_to_first_question',
            attempt_id: 77,
            exam_id: 55
        });
        expect(fixture.calls.renderCalls[0]).toMatchObject({
            options: {
                immediate: true,
                skipPostRenderEffects: true
            },
            reason: 'attempt-opening-progress'
        });
        expect(fixture.calls.renderCalls.some(function (entry) {
            return entry.reason === 'attempt-opening-progress'
                && entry.options
                && entry.options.immediate === true
                && entry.options.skipPostRenderEffects === true;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return String(snapshot.openingAttemptProgressStatus || '').indexOf('Menunggu giliran masuk ujian') >= 0;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.openingAttemptPhase === 'opening_waiting_queue'
                && snapshot.openingAttemptQueuePosition === 14
                && snapshot.openingAttemptCanRetry === true
                && snapshot.openingAttemptCanRefreshStatus === true;
        })).toBe(true);
        var startAttemptBodies = fixture.calls.apiCalls
            .filter(function (entry) {
                return entry.endpoint === 'start_attempt';
            })
            .map(function (entry) {
                return entry.options && entry.options.body ? entry.options.body : {};
            });
        expect(startAttemptBodies).toHaveLength(2);
        expect(String(startAttemptBodies[0].idempotency_key || '')).not.toBe('');
        expect(String(startAttemptBodies[0].idempotency_key || '')).toBe(String(startAttemptBodies[1].idempotency_key || ''));
        expect(fixture.state.pendingStartIntentKey).toBe('');
    });

    it('opens the loading shell immediately when continuing an in-progress exam', async function () {
        var restoredSnapshot = buildCachedQuestionSnapshot(40);
        var fixture = createFixture({
            restoredSnapshot,
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 77,
                        latest_attempt_status: 'in_progress'
                    }
                ],
                selectedExamId: 55
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'exams') {
                    return {
                        current_user: null,
                        items: [
                            {
                                id: 55,
                                duration_minutes: 60,
                                is_class_allowed: 1,
                                latest_attempt_id: 77,
                                latest_attempt_status: 'in_progress'
                            }
                        ]
                    };
                }

                if (endpoint === 'ui_state') {
                    return { attempt_state: null };
                }

                if (endpoint === 'start_attempt_status') {
                    return {
                        attempt_id: 77,
                        duration_minutes: 60,
                        started_at: '2026-04-03 05:00:00',
                        status: 'resumed',
                        question_order_signature: restoredSnapshot.questionOrderSignature,
                        question_revision: restoredSnapshot.questionRevision
                    };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.handleStartExam();

        expect(fixture.calls.renderSnapshots[0]).toMatchObject({
            stage: 'exam'
        });
        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint;
        })).toEqual(['start_attempt_status', 'ui_state', 'entry_flow_metric']);
        expect(fixture.calls.apiCalls[2].options.body).toMatchObject({
            flow: 'resume_to_first_question',
            attempt_id: 77,
            exam_id: 55
        });
        expect(String(fixture.calls.renderSnapshots[0].openingAttemptProgressStatus || '')).toContain('Menyambungkan sesi ujian');
    });

    it('does not emit entry flow metrics when start flow ends in a terminal error before the first question is ready', async function () {
        var fixture = createFixture({
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: ''
                    }
                ],
                selectedExamId: 55
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'exams') {
                    return {
                        current_user: null,
                        items: [
                            {
                                id: 55,
                                duration_minutes: 60,
                                is_class_allowed: 1,
                                latest_attempt_id: 0,
                                latest_attempt_status: ''
                            }
                        ]
                    };
                }

                if (endpoint === 'start_attempt') {
                    throw Object.assign(new Error('Token ujian tidak valid.'), {
                        code: 'token_invalid',
                        status: 403
                    });
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.handleStartExam();

        expect(fixture.state.stage).toBe('exam');
        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint;
        })).toEqual(['exams', 'start_attempt']);
    });

    it('escalates repeated continue-exam retries into a diagnostic resume check without creating a fresh intent', async function () {
        var restoredSnapshot = buildCachedQuestionSnapshot(40);
        var statusCallCount = 0;
        var startCallCount = 0;
        var fixture = createFixture({
            restoredSnapshot,
            openingRetryDiagnosticThreshold: 1,
            openingRetryDiagnosticInterval: 1,
            startAttemptRecoveryTimeoutMs: 4000,
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 77,
                        latest_attempt_status: 'in_progress'
                    }
                ],
                selectedExamId: 55
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'ui_state') {
                    return { attempt_state: null };
                }

                if (endpoint === 'start_attempt_status') {
                    statusCallCount += 1;
                    return {
                        status: 'pending',
                        error_code: 'attempt_pending',
                        error_message: 'Attempt aktif belum ditemukan. Status sesi masih kami pantau.',
                        retry_after_ms: 1
                    };
                }

                if (endpoint === 'start_attempt') {
                    startCallCount += 1;
                    if (startCallCount === 1) {
                        throw Object.assign(new Error('Permintaan mulai ujian sedang diproses. Coba lagi beberapa detik.'), {
                            code: 'attempt_lock_active',
                            status: 429,
                            retry_after_ms: 1
                        });
                    }

                    return {
                        attempt_id: 77,
                        duration_minutes: 60,
                        started_at: '2026-04-03 05:00:00',
                        status: 'resumed',
                        question_order_signature: restoredSnapshot.questionOrderSignature,
                        question_revision: restoredSnapshot.questionRevision
                    };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.handleStartExam();

        expect(fixture.state.stage).toBe('exam');
        expect(fixture.state.error).toBe('');
        var apiEndpoints = fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint;
        });
        expect(apiEndpoints[0]).toBe('start_attempt_status');
        expect(apiEndpoints[1]).toBe('start_attempt');
        expect(apiEndpoints[apiEndpoints.length - 3]).toBe('start_attempt');
        expect(apiEndpoints[apiEndpoints.length - 2]).toBe('ui_state');
        expect(apiEndpoints[apiEndpoints.length - 1]).toBe('entry_flow_metric');
        expect(apiEndpoints.filter(function (endpoint) {
            return endpoint === 'start_attempt';
        })).toHaveLength(2);
        expect(apiEndpoints.filter(function (endpoint) {
            return endpoint === 'start_attempt_status';
        }).length).toBeGreaterThanOrEqual(2);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return String(snapshot.openingAttemptProgressStatus || '').indexOf('Memeriksa ulang sesi secara lebih tegas') >= 0
                || String(snapshot.openingAttemptProgressDetail || '').indexOf('memeriksa ulang attempt aktif tanpa membuat intent baru') >= 0;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return String(snapshot.pendingLastErrorCode || '') === 'attempt_pending';
        })).toBe(true);
    }, 10000);

    it('refreshes opening status through the lightweight status endpoint without reissuing start_attempt', async function () {
        var fixture = createFixture({
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: ''
                    }
                ],
                selectedExamId: 55,
                stage: 'exam',
                isOpeningAttempt: true,
                pendingExamId: 55,
                pendingExamToken: '',
                pendingQueueTicket: 'gate-ticket-1',
                pendingResumeIntent: false
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'start_attempt_status') {
                    return {
                        status: 'queued',
                        queue_ticket: 'gate-ticket-1',
                        queue_position: 7,
                        poll_after_ms: 0,
                        estimated_wait_seconds: 2,
                        gate_capacity: 50,
                        gate_window_seconds: 5
                    };
                }

                if (endpoint === 'start_attempt') {
                    throw new Error('start_attempt should not be called during refresh status');
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.refreshOpeningAttemptStatus();

        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint;
        })).toEqual(['start_attempt_status']);
        expect(fixture.state.openingAttemptPhase).toBe('opening_waiting_queue');
        expect(fixture.state.openingAttemptQueuePosition).toBe(7);
        expect(String(fixture.state.openingAttemptProgressStatus || '')).toContain('Menunggu giliran masuk ujian');
    });

    it('uses opening_state metadata from pending status to show a more specific opening phase', async function () {
        var fixture = createFixture({
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: 'in_progress'
                    }
                ],
                selectedExamId: 55,
                stage: 'exam',
                isOpeningAttempt: true,
                pendingExamId: 55,
                pendingExamToken: '',
                pendingQueueTicket: '',
                pendingResumeIntent: true
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'start_attempt_status') {
                    return {
                        status: 'pending',
                        error_code: 'attempt_pending',
                        error_message: 'Attempt aktif belum ditemukan. Status sesi masih kami pantau.',
                        retry_after_ms: 1200,
                        opening_state: 'bootstrap_questions',
                        opening_reason: 'question_window_pending',
                        attempt_id: 88,
                        wait_age_seconds: 19
                    };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.refreshOpeningAttemptStatus();

        expect(fixture.state.openingAttemptPhase).toBe('opening_recovering');
        expect(String(fixture.state.openingAttemptProgressStatus || '')).toContain('Sesi siap, memuat soal pertama');
        expect(String(fixture.state.openingAttemptServerState || '')).toBe('bootstrap_questions');
        expect(String(fixture.state.openingAttemptServerReason || '')).toBe('question_window_pending');
        expect(Number(fixture.state.openingAttemptWaitAgeSeconds) || 0).toBe(19);
        expect(Number(fixture.state.openingRetryNextAt) || 0).toBeGreaterThan(0);
    });

    it('does not move opening progress backward after an attempt id is already known', async function () {
        var fixture = createFixture({
            state: {
                attemptId: 77,
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 77,
                        latest_attempt_status: 'in_progress'
                    }
                ],
                selectedExamId: 55,
                stage: 'exam',
                isOpeningAttempt: true,
                openingAttemptProgressPercent: 76,
                openingAttemptProgressStepIndex: 4,
                pendingExamId: 55,
                pendingExamToken: '',
                pendingQueueTicket: '',
                pendingResumeIntent: true
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'start_attempt_status') {
                    return {
                        status: 'pending',
                        error_code: 'attempt_pending',
                        error_message: 'Attempt aktif masih dicek.',
                        retry_after_ms: 1200,
                        opening_state: 'resume_lookup',
                        opening_reason: 'resume_index_miss',
                        attempt_id: 77,
                        wait_age_seconds: 8
                    };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.refreshOpeningAttemptStatus();

        expect(fixture.state.openingAttemptProgressPercent).toBe(76);
        expect(fixture.state.openingAttemptProgressStepIndex).toBe(4);
        expect(String(fixture.state.openingAttemptProgressStatus || '')).toContain('Mengecek attempt aktif');
    });

    it('retries bootstrap instead of restarting start_attempt when admitted status arrives after attempt id is known', async function () {
        var restoredSnapshot = buildCachedQuestionSnapshot(40);
        var fixture = createFixture({
            restoredSnapshot,
            state: {
                attemptId: 77,
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 77,
                        latest_attempt_status: 'in_progress'
                    }
                ],
                selectedExamId: 55,
                stage: 'exam',
                isOpeningAttempt: true,
                openingAttemptProgressPercent: 76,
                openingAttemptProgressStepIndex: 4,
                pendingExamId: 55,
                pendingExamToken: '',
                pendingQueueTicket: 'gate-ticket-1',
                pendingResumeIntent: false,
                questionRevision: restoredSnapshot.questionRevision,
                questionOrderSignature: restoredSnapshot.questionOrderSignature,
                remainingSeconds: 3450
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'start_attempt_status') {
                    return {
                        status: 'admitted',
                        queue_ticket: 'gate-ticket-1',
                        queue_position: 1,
                        poll_after_ms: 0,
                        estimated_wait_seconds: 0,
                        gate_capacity: 50,
                        gate_window_seconds: 5
                    };
                }

                if (endpoint === 'ui_state') {
                    return { attempt_state: null };
                }

                if (endpoint === 'start_attempt') {
                    throw new Error('start_attempt should not be called once attempt id is known');
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.refreshOpeningAttemptStatus();

        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint;
        })).toEqual(['start_attempt_status', 'ui_state']);
        expect(fixture.calls.apiCalls.some(function (entry) {
            return entry.endpoint === 'start_attempt';
        })).toBe(false);
        expect(fixture.calls.loadQuestionWindow).toHaveLength(1);
        expect(fixture.state.attemptId).toBe(77);
        expect(fixture.state.stage).toBe('exam');
        expect(fixture.state.error).toBe('');
    });

    it('does not fire a duplicate refresh request while an opening retry request is in flight', async function () {
        var fixture = createFixture({
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: ''
                    }
                ],
                selectedExamId: 55,
                stage: 'exam',
                isOpeningAttempt: true,
                pendingExamId: 55,
                pendingExamToken: '',
                pendingQueueTicket: 'gate-ticket-1',
                pendingResumeIntent: false,
                openingRetryInFlight: true
            },
            apiRequest: async function (endpoint) {
                throw new Error('Unexpected endpoint while request is in flight: ' + String(endpoint));
            }
        });

        await fixture.manager.refreshOpeningAttemptStatus();

        expect(fixture.calls.apiCalls).toHaveLength(0);
        expect(String(fixture.state.openingAttemptProgressStatus || '')).toContain('Refresh Status sedang berjalan');
        expect(fixture.state.openingRetryInFlight).toBe(true);
        expect(fixture.calls.renderCalls.some(function (entry) {
            return entry.reason === 'attempt-opening-progress'
                && entry.options
                && entry.options.immediate === true
                && entry.options.skipPostRenderEffects === true;
        })).toBe(true);
    });

    it('paints refresh status progress immediately before waiting for the latest opening status response', async function () {
        var deferred = createDeferredPromise();
        var fixture = createFixture({
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: ''
                    }
                ],
                selectedExamId: 55,
                stage: 'exam',
                isOpeningAttempt: true,
                pendingExamId: 55,
                pendingExamToken: '',
                pendingQueueTicket: '',
                pendingResumeIntent: true
            },
            windowRef: {
                document: {
                    visibilityState: 'hidden'
                },
                setTimeout: setTimeout
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'start_attempt_status') {
                    return deferred.promise;
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        var refreshPromise = fixture.manager.refreshOpeningAttemptStatus();
        await flushAsyncWork();

        expect(fixture.calls.renderCalls.some(function (entry) {
            return entry.reason === 'attempt-opening-progress'
                && entry.options
                && entry.options.immediate === true
                && entry.options.skipPostRenderEffects === true;
        })).toBe(true);
        expect(String(fixture.state.openingAttemptProgressStatus || '')).toContain('Mengecek status sesi');
        await waitForAssertion(function () {
            expect(fixture.calls.apiCalls.map(function (entry) {
                return entry.endpoint;
            })).toEqual(['start_attempt_status']);
        });

        deferred.resolve({
            status: 'pending',
            error_code: 'attempt_pending',
            error_message: 'Attempt aktif belum ditemukan. Status sesi masih kami pantau.',
            retry_after_ms: 1200,
            opening_state: 'resume_lookup',
            opening_reason: 'resume_index_miss',
            attempt_id: 88,
            wait_age_seconds: 5
        });

        await refreshPromise;
    });

    it('reuses the same idempotency key when retrying the same opening shell', async function () {
        var restoredSnapshot = buildCachedQuestionSnapshot(40);
        var fixture = createFixture({
            restoredSnapshot,
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: ''
                    }
                ],
                selectedExamId: 55,
                stage: 'exam',
                isOpeningAttempt: true,
                pendingExamId: 55,
                pendingExamToken: '',
                pendingStartIntentKey: 'start_1_retry_key',
                pendingQueueTicket: '',
                pendingResumeIntent: false
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'ui_state') {
                    return { attempt_state: null };
                }

                if (endpoint === 'start_attempt') {
                    return {
                        attempt_id: 77,
                        duration_minutes: 60,
                        started_at: '2026-04-03 05:00:00',
                        status: 'started',
                        question_order_signature: restoredSnapshot.questionOrderSignature,
                        question_revision: restoredSnapshot.questionRevision
                    };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.retryOpeningAttempt();

        var startAttemptCalls = fixture.calls.apiCalls.filter(function (entry) {
            return entry.endpoint === 'start_attempt';
        });
        expect(startAttemptCalls).toHaveLength(1);
        expect(startAttemptCalls[0].options.body.idempotency_key).toBe('start_1_retry_key');
        expect(fixture.state.pendingStartIntentKey).toBe('');
    });

    it('paints retry progress immediately before reusing the same opening request flow', async function () {
        var restoredSnapshot = buildCachedQuestionSnapshot(40);
        var deferred = createDeferredPromise();
        var fixture = createFixture({
            restoredSnapshot,
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: ''
                    }
                ],
                selectedExamId: 55,
                stage: 'exam',
                isOpeningAttempt: true,
                pendingExamId: 55,
                pendingExamToken: '',
                pendingStartIntentKey: 'retry_key_fast',
                pendingQueueTicket: '',
                pendingResumeIntent: false
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'start_attempt') {
                    return deferred.promise;
                }

                if (endpoint === 'ui_state') {
                    return { attempt_state: null };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        var retryPromise = fixture.manager.retryOpeningAttempt();
        await flushAsyncWork();

        expect(fixture.calls.renderCalls.some(function (entry) {
            return entry.reason === 'attempt-opening-progress'
                && entry.options
                && entry.options.immediate === true
                && entry.options.skipPostRenderEffects === true;
        })).toBe(true);
        expect(String(fixture.state.openingAttemptProgressStatus || '')).toContain('Mengulang permintaan sesi');

        deferred.resolve({
            attempt_id: 77,
            duration_minutes: 60,
            started_at: '2026-04-03 05:00:00',
            status: 'started',
            question_order_signature: restoredSnapshot.questionOrderSignature,
            question_revision: restoredSnapshot.questionRevision
        });

        await retryPromise;
    });

    it('keeps the user in confirm when local token format validation fails', async function () {
        var fixture = createFixture({
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: '',
                        requires_token: 1,
                        token_input_required: 1
                    }
                ],
                selectedExamId: 55,
                examToken: 'ABC'
            }
        });

        await fixture.manager.handleStartExam();

        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.isOpeningAttempt).toBe(false);
        expect(fixture.state.openingAttemptPhase).toBe('');
        expect(fixture.state.error).toContain('Token ujian harus 6 karakter');
        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint;
        })).toEqual([]);
    });

    it('paints the result loading flow immediately before fetching review data', async function () {
        var fixture = createFixture({
            state: {
                exams: [
                    {
                        id: 55,
                        title: 'TOBK Biologi',
                        latest_attempt_id: 77,
                        latest_attempt_status: 'completed',
                        latest_attempt_score: 88,
                        latest_attempt_max_score: 100,
                        latest_attempt_percentage: 88,
                        latest_attempt_is_passed: 1,
                        latest_attempt_pass_label: 'LULUS',
                        latest_attempt_result_tone: 'pass',
                        kkm_percentage: 75,
                        show_student_result: 1
                    }
                ],
                selectedExamId: 55,
                stage: 'confirm'
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'exams') {
                    return {
                        current_user: null,
                        items: [
                            {
                                id: 55,
                                title: 'TOBK Biologi',
                                latest_attempt_id: 77,
                                latest_attempt_status: 'completed',
                                latest_attempt_score: 88,
                                latest_attempt_max_score: 100,
                                latest_attempt_percentage: 88,
                                latest_attempt_is_passed: 1,
                                latest_attempt_pass_label: 'LULUS',
                                latest_attempt_result_tone: 'pass',
                                kkm_percentage: 75,
                                show_student_result: 1
                            }
                        ]
                    };
                }

                if (endpoint === 'result') {
                    return {
                        attempt: {
                            id: 77,
                            exam_id: 55,
                            status: 'completed',
                            score: 88,
                            max_score: 100
                        },
                        exam: {
                            id: 55,
                            title: 'TOBK Biologi',
                            kkm_percentage: 75,
                            show_student_result: 1
                        },
                        show_student_result: 1,
                        result_view_mode: 'full',
                        review_items: [],
                        review_summary: {
                            total_questions: 40
                        }
                    };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.handleViewResult();

        expect(fixture.state.stage).toBe('result');
        expect(fixture.calls.renderCalls.some(function (entry) {
            return entry.reason === 'view-result-refresh-selection'
                && entry.options
                && entry.options.immediate === true
                && entry.options.skipPostRenderEffects === true;
        })).toBe(true);
        expect(fixture.calls.renderCalls.some(function (entry) {
            return entry.reason === 'result-view-request'
                && entry.options
                && entry.options.immediate === true
                && entry.options.skipPostRenderEffects === true;
        })).toBe(true);
    });

    it('rechecks the exam list and auto-resumes when the server finishes creating the attempt after a busy recovery timeout', async function () {
        var restoredSnapshot = buildCachedQuestionSnapshot(40);
        var examsRequestCount = 0;
        var fixture = createFixture({
            restoredSnapshot,
            state: {
                exams: [
                    {
                        id: 55,
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: ''
                    }
                ],
                selectedExamId: 55
            },
            apiRequest: async function (endpoint, options) {
                if (endpoint === 'exams') {
                    examsRequestCount += 1;
                    if (examsRequestCount === 1) {
                        return {
                            current_user: null,
                            items: [
                                {
                                    id: 55,
                                    duration_minutes: 60,
                                    is_class_allowed: 1,
                                    latest_attempt_id: 0,
                                    latest_attempt_status: ''
                                }
                            ]
                        };
                    }

                    return {
                        current_user: null,
                        items: [
                            {
                                id: 55,
                                duration_minutes: 60,
                                is_class_allowed: 1,
                                latest_attempt_id: 77,
                                latest_attempt_status: 'in_progress'
                            }
                        ]
                    };
                }

                if (endpoint === 'ui_state') {
                    return { attempt_state: null };
                }

                if (endpoint === 'start_attempt_status') {
                    return {
                        attempt_id: 77,
                        duration_minutes: 60,
                        started_at: '2026-04-03 05:00:00',
                        status: 'resumed',
                        question_order_signature: restoredSnapshot.questionOrderSignature,
                        question_revision: restoredSnapshot.questionRevision
                    };
                }

                if (endpoint !== 'start_attempt') {
                    throw new Error('Unexpected endpoint: ' + String(endpoint));
                }

                throw new Error('Server masih sibuk menyiapkan sesi ujian. Coba lagi beberapa saat.');
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
            'exams:0',
            'start_attempt:0',
            'start_attempt_status:1',
            'ui_state:0',
            'entry_flow_metric:0'
        ]);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.stage === 'exam'
                && String(snapshot.openingAttemptProgressStatus || '').trim() !== '';
        })).toBe(true);
    });

    it('refreshes the exam list before viewing results and reroutes to start when an admin reset makes the attempt active again', async function () {
        var restoredSnapshot = buildCachedQuestionSnapshot(40);
        var fixture = createFixture({
            restoredSnapshot,
            state: {
                exams: [
                    {
                        id: 55,
                        title: 'TOBK Biologi',
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 120,
                        latest_attempt_status: 'completed'
                    }
                ],
                selectedExamId: 55
            },
            apiRequest: async function (endpoint, options) {
                if (endpoint === 'exams') {
                    return {
                        current_user: null,
                        items: [
                            {
                                id: 55,
                                title: 'TOBK Biologi',
                                duration_minutes: 60,
                                is_class_allowed: 1,
                                latest_attempt_id: 99,
                                latest_attempt_status: 'in_progress'
                            }
                        ]
                    };
                }

                if (endpoint === 'ui_state') {
                    return { attempt_state: null };
                }

                if (endpoint === 'start_attempt_status') {
                    return {
                        attempt_id: 99,
                        duration_minutes: 60,
                        started_at: '2026-04-03 05:00:00',
                        status: 'resumed',
                        question_order_signature: restoredSnapshot.questionOrderSignature,
                        question_revision: restoredSnapshot.questionRevision
                    };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.handleViewResult();

        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint;
        })).toEqual(['exams', 'start_attempt_status', 'ui_state', 'entry_flow_metric']);
        expect(fixture.state.stage).toBe('exam');
        expect(fixture.state.error).toBe('');
        expect(fixture.state.attemptId).toBe(99);
        expect(fixture.state.resultProgressVisible).toBe(false);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.stage === 'exam' && snapshot.resultProgressVisible === true;
        })).toBe(false);
    });

    it('shows staged result progress while viewing results from the exam picker', async function () {
        var fixture = createFixture({
            state: {
                exams: [
                    {
                        id: 55,
                        title: 'TOBK Biologi',
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 88,
                        latest_attempt_status: 'completed',
                        latest_attempt_score: 80,
                        latest_attempt_max_score: 100,
                        latest_attempt_percentage: 80,
                        latest_attempt_is_passed: 1,
                        latest_attempt_pass_label: 'LULUS',
                        latest_attempt_result_tone: 'pass',
                        kkm_percentage: 75,
                        show_student_result: 1
                    }
                ],
                selectedExamId: 55
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'exams') {
                    return {
                        current_user: null,
                        items: [
                            {
                                id: 55,
                                title: 'TOBK Biologi',
                                duration_minutes: 60,
                                is_class_allowed: 1,
                                latest_attempt_id: 88,
                                latest_attempt_status: 'completed',
                                latest_attempt_score: 80,
                                latest_attempt_max_score: 100,
                                latest_attempt_percentage: 80,
                                latest_attempt_is_passed: 1,
                                latest_attempt_pass_label: 'LULUS',
                                latest_attempt_result_tone: 'pass',
                                kkm_percentage: 75,
                                show_student_result: 1
                            }
                        ]
                    };
                }

                if (endpoint === 'result') {
                    return {
                        attempt: {
                            exam_id: 55,
                            id: 88,
                            max_score: 100,
                            score: 80,
                            started_at: '2026-04-03 05:00:00',
                            status: 'completed'
                        },
                        exam: {
                            id: 55,
                            kkm_percentage: 75,
                            show_student_result: 1,
                            title: 'TOBK Biologi'
                        },
                        is_passed: 1,
                        kkm_percentage: 75,
                        pass_label: 'LULUS',
                        percentage: 80,
                        result_tone: 'pass',
                        result_view_mode: 'full',
                        review_items: [],
                        review_summary: null,
                        show_student_result: 1,
                        submission_summary: {
                            answered_questions: 10,
                            pending_manual_questions: 0,
                            total_questions: 10
                        }
                    };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.handleViewResult();

        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.resultProgressVisible
                && snapshot.resultProgressStepIndex === 1
                && snapshot.resultProgressStatus.indexOf('status exam') >= 0;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.resultProgressVisible
                && snapshot.resultProgressStepIndex === 2
                && snapshot.resultProgressStatus.indexOf('hasil attempt') >= 0;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.resultProgressVisible
                && snapshot.resultProgressStepIndex === 3
                && snapshot.resultProgressStatus.indexOf('ringkasan nilai') >= 0;
        })).toBe(true);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.resultProgressVisible
                && snapshot.resultProgressStepIndex === 4
                && snapshot.resultProgressStatus.indexOf('halaman hasil') >= 0;
        })).toBe(true);
        expect(fixture.calls.ensureResultStageRenderer).toEqual([
            {
                renderOnResolve: false
            }
        ]);
        expect(fixture.calls.renderSnapshots[fixture.calls.renderSnapshots.length - 1]).toMatchObject({
            resultProgressPercent: 0,
            resultProgressStatus: '',
            resultProgressStepIndex: 0,
            resultProgressVisible: false,
            stage: 'result'
        });
    });

    it('refreshes the exam list before starting and reroutes to result when the latest attempt is already completed', async function () {
        var fixture = createFixture({
            state: {
                exams: [
                    {
                        id: 55,
                        title: 'TOBK Biologi',
                        duration_minutes: 60,
                        is_class_allowed: 1,
                        latest_attempt_id: 0,
                        latest_attempt_status: ''
                    }
                ],
                selectedExamId: 55
            },
            apiRequest: async function (endpoint) {
                if (endpoint === 'exams') {
                    return {
                        current_user: null,
                        items: [
                            {
                                id: 55,
                                title: 'TOBK Biologi',
                                duration_minutes: 60,
                                is_class_allowed: 1,
                                latest_attempt_id: 88,
                                latest_attempt_status: 'completed',
                                latest_attempt_score: 80,
                                latest_attempt_max_score: 100,
                                latest_attempt_percentage: 80,
                                latest_attempt_is_passed: 1,
                                latest_attempt_pass_label: 'LULUS',
                                latest_attempt_result_tone: 'pass',
                                kkm_percentage: 75,
                                show_student_result: 1
                            }
                        ]
                    };
                }

                if (endpoint === 'result') {
                    return {
                        attempt: {
                            exam_id: 55,
                            id: 88,
                            max_score: 100,
                            score: 80,
                            started_at: '2026-04-03 05:00:00',
                            status: 'completed'
                        },
                        exam: {
                            id: 55,
                            kkm_percentage: 75,
                            show_student_result: 1,
                            title: 'TOBK Biologi'
                        },
                        is_passed: 1,
                        kkm_percentage: 75,
                        pass_label: 'LULUS',
                        percentage: 80,
                        result_tone: 'pass',
                        result_view_mode: 'full',
                        review_items: [],
                        review_summary: null,
                        show_student_result: 1,
                        submission_summary: {
                            answered_questions: 10,
                            pending_manual_questions: 0,
                            total_questions: 10
                        }
                    };
                }

                throw new Error('Unexpected endpoint: ' + String(endpoint));
            }
        });

        await fixture.manager.handleStartExam();

        expect(fixture.calls.apiCalls.map(function (entry) {
            return entry.endpoint;
        })).toEqual(['exams', 'result']);
        expect(fixture.state.stage).toBe('result');
        expect(fixture.state.error).toBe('');
        expect(fixture.state.result && fixture.state.result.attempt_id).toBe(88);
    });
});
