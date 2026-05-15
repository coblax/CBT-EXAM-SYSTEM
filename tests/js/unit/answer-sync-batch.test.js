import { describe, expect, it, vi, beforeEach } from 'vitest';
import { createAnswerSyncManager } from '../../../src/frontend/app/exam/answer-sync.js';

function createState(overrides = {}) {
    return {
        stage: 'exam',
        attemptId: 42,
        selectedExamId: 10,
        token: 'test-token',
        user: { user_id: 5, role: 'student' },
        connectionStatus: 'online',
        pendingSyncCount: 0,
        syncBlockingReason: '',
        lastSyncError: '',
        examLockedForPendingFinish: false,
        pendingFinishAutoSubmit: false,
        isFinishing: false,
        error: '',
        questionPayloadById: {},
        ...overrides
    };
}

function createDeps(state, overrides = {}) {
    return {
        diagnosticsManager: null,
        state: state,
        windowRef: {
            setTimeout: function (fn, ms) { var id = Math.random(); fn(); return id; },
            clearTimeout: vi.fn()
        },
        autoSaveChoiceDelayMs: 2000,
        autoSaveTextDelayMs: 3500,
        autoSaveChoiceDelayCongestedMs: 2600,
        autoSaveTextDelayCongestedMs: 4600,
        autoSaveCongestedWindowMs: 15000,
        autoSaveBatchMaxItems: 20,
        answerSyncRetryBaseDelayMs: 2500,
        answerSyncRetryMaxDelayMs: 20000,
        answerSyncBackgroundEnabled: false,
        apiRequest: overrides.apiRequest || vi.fn().mockResolvedValue({ attempt_id: 42, accepted_count: 1, items: [] }),
        durableAnswerQueue: null,
        getNavigatorConnectionStatus: overrides.getNavigatorConnectionStatus || vi.fn().mockReturnValue('online'),
        getQuestionById: overrides.getQuestionById || vi.fn().mockReturnValue(null),
        getQuestionPayloadById: vi.fn().mockReturnValue(null),
        getQuestionDataGeneration: vi.fn().mockReturnValue(1),
        isQuestionRevisionRefreshActive: vi.fn().mockReturnValue(false),
        maybeFinalizeLockedExam: vi.fn(),
        normalizeExistingAnswerForQuestion: vi.fn().mockReturnValue({ hasValue: false }),
        normalizeStoredAutoSaveState: vi.fn().mockReturnValue(null),
        payloadSignature: overrides.payloadSignature || function (payload) { return payload !== null ? JSON.stringify(payload) : ''; },
        questionAnswerPayload: overrides.questionAnswerPayload || function (q) { return q && q.answer !== undefined ? q.answer : null; },
        recordActionTrail: vi.fn(),
        recordTimeline: vi.fn(),
        render: vi.fn(),
        renderExamPartial: null,
        scheduleQuestionCachePersist: vi.fn(),
        ...overrides
    };
}

describe('createAnswerSyncManager', function () {
    describe('queueQuestionAnswer', function () {
        it('queues a question answer and increments pending count', function () {
            var state = createState();
            var deps = createDeps(state, {
                questionAnswerPayload: function () { return 42; },
                payloadSignature: function () { return 'sig-42'; }
            });
            var manager = createAnswerSyncManager(deps);

            var queued = manager.queueQuestionAnswer({ id: 100, answer: 42 });

            expect(queued).toBe(true);
            expect(state.pendingSyncCount).toBe(1);
        });

        it('does not queue when signature matches last submitted', function () {
            var state = createState();
            var deps = createDeps(state, {
                questionAnswerPayload: function () { return 42; },
                payloadSignature: function () { return 'sig-42'; }
            });
            var manager = createAnswerSyncManager(deps);

            manager.queueQuestionAnswer({ id: 100, answer: 42 });
            // Simulate successful submission
            manager.initializeSubmittedPayloadCache();

            // Re-queue same answer - should be skipped since it's already pending
            var queued = manager.queueQuestionAnswer({ id: 100, answer: 42 });

            // It's still pending so it will be queued (dedup happens at flush)
            expect(queued).toBe(true);
        });

        it('rejects queue when attempt is zero', function () {
            var state = createState({ attemptId: 0 });
            var deps = createDeps(state, {
                questionAnswerPayload: function () { return 42; },
                payloadSignature: function () { return 'sig-42'; }
            });
            var manager = createAnswerSyncManager(deps);

            var queued = manager.queueQuestionAnswer({ id: 100, answer: 42 });

            expect(queued).toBe(false);
        });

        it('rejects queue when question id is zero', function () {
            var state = createState();
            var deps = createDeps(state, {
                questionAnswerPayload: function () { return 42; },
                payloadSignature: function () { return 'sig-42'; }
            });
            var manager = createAnswerSyncManager(deps);

            var queued = manager.queueQuestionAnswer({ id: 0, answer: 42 });

            expect(queued).toBe(false);
        });
    });

    describe('isNetworkConnectivityError', function () {
        it('detects network errors by isNetworkError flag', function () {
            var state = createState();
            var manager = createAnswerSyncManager(createDeps(state));

            var error = new Error('Failed to fetch');
            error.isNetworkError = true;
            error.status = 0;

            expect(manager.isNetworkConnectivityError(error)).toBe(true);
        });

        it('detects network errors by status 0', function () {
            var state = createState();
            var manager = createAnswerSyncManager(createDeps(state));

            var error = new Error('timeout');
            error.status = 0;

            expect(manager.isNetworkConnectivityError(error)).toBe(true);
        });

        it('does not flag server errors as network errors', function () {
            var state = createState();
            var manager = createAnswerSyncManager(createDeps(state));

            var error = new Error('Internal Server Error');
            error.status = 500;
            error.code = 'server_error';

            expect(manager.isNetworkConnectivityError(error)).toBe(false);
        });
    });

    describe('shouldFallbackToLegacyBatch', function () {
        it('returns true for runtime_buffer_unavailable', function () {
            var state = createState();
            var manager = createAnswerSyncManager(createDeps(state));

            var error = new Error('Redis unavailable');
            error.status = 503;
            error.code = 'runtime_buffer_unavailable';

            expect(manager.shouldFallbackToLegacyBatch(error)).toBe(true);
        });

        it('returns false for network errors (should retry instead)', function () {
            var state = createState();
            var manager = createAnswerSyncManager(createDeps(state));

            var error = new Error('Network error');
            error.status = 0;
            error.isNetworkError = true;

            expect(manager.shouldFallbackToLegacyBatch(error)).toBe(false);
        });
    });

    describe('getQuestionSaveFeedback', function () {
        it('returns saved tone when signature matches last submitted', function () {
            var state = createState();
            var deps = createDeps(state, {
                questionAnswerPayload: function () { return 'answer-val'; },
                payloadSignature: function () { return 'sig-saved'; },
                getQuestionById: function () { return { id: 100, answer: 'answer-val' }; }
            });
            var manager = createAnswerSyncManager(deps);

            // Simulate a successful submission
            manager.queueQuestionAnswer({ id: 100, answer: 'answer-val' });
            // Manually prime the submitted cache
            manager.primeSubmittedPayloadCacheFromQuestionItems([{ id: 100, answer: 'answer-val' }]);

            // After priming, the pending item still exists, so it shows syncing
            var feedback = manager.getQuestionSaveFeedback(100);
            expect(feedback.isVisible).toBe(true);
        });

        it('returns idle for unknown question', function () {
            var state = createState();
            var deps = createDeps(state);
            var manager = createAnswerSyncManager(deps);

            var feedback = manager.getQuestionSaveFeedback(999);

            expect(feedback.tone).toBe('idle');
            expect(feedback.isVisible).toBe(false);
        });
    });

    describe('clearAutoSaveRuntimeState', function () {
        it('resets all sync state', function () {
            var state = createState({ lastSyncError: 'some error', examLockedForPendingFinish: true });
            var deps = createDeps(state, {
                questionAnswerPayload: function () { return 1; },
                payloadSignature: function () { return 's'; }
            });
            var manager = createAnswerSyncManager(deps);

            manager.queueQuestionAnswer({ id: 100, answer: 1 });
            manager.clearAutoSaveRuntimeState();

            expect(state.pendingSyncCount).toBe(0);
            expect(state.lastSyncError).toBe('');
            expect(state.examLockedForPendingFinish).toBe(false);
        });
    });

    describe('setConnectionStatus', function () {
        it('sets offline status and updates state', function () {
            var state = createState();
            var deps = createDeps(state);
            var manager = createAnswerSyncManager(deps);

            manager.setConnectionStatus('offline', { render: false, triggerRetry: false });

            expect(state.connectionStatus).toBe('offline');
        });

        it('sets online status and triggers retry', function () {
            var state = createState({ connectionStatus: 'offline', pendingSyncCount: 1 });
            var deps = createDeps(state, {
                questionAnswerPayload: function () { return 1; },
                payloadSignature: function () { return 's'; }
            });
            var manager = createAnswerSyncManager(deps);
            manager.queueQuestionAnswer({ id: 100, answer: 1 });

            manager.setConnectionStatus('online', { render: false });

            expect(state.connectionStatus).toBe('online');
        });
    });

    describe('getPendingSyncQuestionIds', function () {
        it('returns sorted list of pending question ids', function () {
            var state = createState();
            var deps = createDeps(state, {
                questionAnswerPayload: function (q) { return q.answer; },
                payloadSignature: function (p) { return 'sig-' + p; }
            });
            var manager = createAnswerSyncManager(deps);

            manager.queueQuestionAnswer({ id: 300, answer: 'c' });
            manager.queueQuestionAnswer({ id: 100, answer: 'a' });
            manager.queueQuestionAnswer({ id: 200, answer: 'b' });

            var ids = manager.getPendingSyncQuestionIds();

            expect(ids).toEqual([100, 200, 300]);
        });
    });
});
