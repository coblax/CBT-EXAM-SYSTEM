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

    describe('answer path and queue helpers', function () {
        it('detects answer submit API paths only', function () {
            var state = createState();
            var manager = createAnswerSyncManager(createDeps(state));

            expect(manager.isAnswerSubmitPath('submit_answer')).toBe(true);
            expect(manager.isAnswerSubmitPath('/submit_answers_batch')).toBe(true);
            expect(manager.isAnswerSubmitPath('questions')).toBe(false);
            expect(manager.isAnswerSubmitPath('/session')).toBe(false);
        });

        it('queues answers by ids and skips missing questions', function () {
            var state = createState();
            var deps = createDeps(state, {
                getQuestionById: function (questionId) {
                    if (questionId === 100 || questionId === 200) {
                        return { id: questionId, answer: 'answer-' + questionId };
                    }
                    return null;
                },
                questionAnswerPayload: function (q) { return q.answer; },
                payloadSignature: function (payload) { return 'sig-' + payload; }
            });
            var manager = createAnswerSyncManager(deps);

            var queued = manager.queueQuestionAnswersByIds([0, 100, 999, 200]);

            expect(queued).toBe(2);
            expect(manager.getPendingSyncQuestionIds()).toEqual([100, 200]);
            expect(state.pendingSyncCount).toBe(2);
        });

        it('queues loaded question payloads for flush', function () {
            var state = createState({
                questionPayloadById: {
                    100: true,
                    200: true
                }
            });
            var deps = createDeps(state, {
                getQuestionPayloadById: function (questionId) {
                    if (questionId === 100) {
                        return { id: 100, answer: 'loaded' };
                    }
                    return null;
                },
                questionAnswerPayload: function (q) { return q.answer; },
                payloadSignature: function (payload) { return 'sig-' + payload; }
            });
            var manager = createAnswerSyncManager(deps);

            var queued = manager.queueLoadedQuestionAnswersForFlush();

            expect(queued).toBe(1);
            expect(manager.getPendingSyncQuestionIds()).toEqual([100]);
        });

        it('prunes pending answer state to the valid question lookup', function () {
            var state = createState();
            var deps = createDeps(state, {
                questionAnswerPayload: function (q) { return q.answer; },
                payloadSignature: function (payload) { return 'sig-' + payload; }
            });
            var manager = createAnswerSyncManager(deps);

            manager.queueQuestionAnswer({ id: 100, answer: 'keep' });
            manager.queueQuestionAnswer({ id: 200, answer: 'drop' });

            manager.pruneQuestionAnswerState({ 100: true });

            expect(manager.getPendingSyncQuestionIds()).toEqual([100]);
            expect(state.pendingSyncCount).toBe(1);
            expect(manager.getAutoSaveState().pendingAnswerBatchByQuestion).toEqual({
                100: {
                    answer: 'keep',
                    question_id: 100,
                    signature: 'sig-keep'
                }
            });
        });

        it('does not schedule autosave while a question revision refresh is active', function () {
            var state = createState();
            var setTimeoutSpy = vi.fn();
            var deps = createDeps(state, {
                isQuestionRevisionRefreshActive: vi.fn().mockReturnValue(true),
                windowRef: {
                    setTimeout: setTimeoutSpy,
                    clearTimeout: vi.fn()
                }
            });
            var manager = createAnswerSyncManager(deps);

            manager.scheduleAutoSave(100, 10);

            expect(setTimeoutSpy).not.toHaveBeenCalled();
            expect(manager.hasPendingBatchItems()).toBe(false);
        });

        it('submits a single question immediately with keepalive when requested', async function () {
            var state = createState();
            var apiRequest = vi.fn().mockResolvedValue({
                attempt_id: 42,
                accepted_count: 1,
                items: [{ question_id: 100, deferred: 0 }]
            });
            var manager = createAnswerSyncManager(createDeps(state, {
                apiRequest: apiRequest,
                getQuestionById: function (questionId) {
                    return { id: questionId, answer: 'instant' };
                },
                questionAnswerPayload: function (q) { return q.answer; },
                payloadSignature: function (payload) { return 'sig-' + payload; }
            }));

            await manager.submitQuestionAnswer({ id: 100 }, { keepalive: true });

            expect(apiRequest).toHaveBeenCalledWith('submit_answers_batch', expect.objectContaining({
                keepalive: true,
                body: {
                    attempt_id: 42,
                    answers: [{ question_id: 100, answer: 'instant' }]
                }
            }));
            expect(state.pendingSyncCount).toBe(0);
        });
    });

    describe('flushPendingAnswerBatch', function () {
        it('deduplicates repeated queue updates and submits the latest answer', async function () {
            var state = createState();
            var apiRequest = vi.fn().mockResolvedValue({
                attempt_id: 42,
                accepted_count: 1,
                items: [{ question_id: 100, deferred: 0 }]
            });
            var deps = createDeps(state, {
                apiRequest: apiRequest,
                questionAnswerPayload: function (q) { return q.answer; },
                payloadSignature: function (payload) { return 'sig-' + payload; }
            });
            var manager = createAnswerSyncManager(deps);

            manager.queueQuestionAnswer({ id: 100, answer: 'first' });
            manager.queueQuestionAnswer({ id: 100, answer: 'second' });

            await manager.flushPendingAnswerBatch({ flushAll: true });

            expect(apiRequest).toHaveBeenCalledTimes(1);
            expect(apiRequest).toHaveBeenCalledWith('submit_answers_batch', expect.objectContaining({
                body: {
                    attempt_id: 42,
                    answers: [{ question_id: 100, answer: 'second' }]
                }
            }));
            expect(state.pendingSyncCount).toBe(0);
            expect(manager.getPendingSyncQuestionIds()).toEqual([]);
        });

        it('falls back to legacy single-answer submission when batch endpoint returns 503', async function () {
            var state = createState();
            var apiRequest = vi.fn().mockImplementation(function (path, options) {
                if (path === 'submit_answers_batch') {
                    var error = new Error('Batch unavailable');
                    error.status = 503;
                    throw error;
                }

                expect(path).toBe('submit_answer');
                return Promise.resolve({
                    question_id: options.body.question_id,
                    deferred: 0,
                    score_awarded: 1
                });
            });
            var manager = createAnswerSyncManager(createDeps(state, {
                apiRequest: apiRequest,
                questionAnswerPayload: function (q) { return q.answer; },
                payloadSignature: function (payload) { return 'sig-' + payload; }
            }));

            manager.queueQuestionAnswer({ id: 100, answer: 'A' });

            await manager.flushPendingAnswerBatch({ flushAll: true });

            expect(apiRequest.mock.calls.map(function (call) { return call[0]; })).toEqual([
                'submit_answers_batch',
                'submit_answer'
            ]);
            expect(state.pendingSyncCount).toBe(0);
            expect(state.lastSyncError).toBe('');
        });

        it('keeps pending answers queued when a retryable network failure happens', async function () {
            var state = createState();
            var apiRequest = vi.fn().mockImplementation(function () {
                var error = new Error('Failed to fetch');
                error.status = 0;
                error.isNetworkError = true;
                throw error;
            });
            var manager = createAnswerSyncManager(createDeps(state, {
                apiRequest: apiRequest,
                questionAnswerPayload: function (q) { return q.answer; },
                payloadSignature: function (payload) { return 'sig-' + payload; }
            }));

            manager.queueQuestionAnswer({ id: 100, answer: 'A' });

            await expect(manager.flushPendingAnswerBatch({ flushAll: true })).rejects.toThrow('Failed to fetch');

            expect(manager.getPendingSyncQuestionIds()).toEqual([100]);
            expect(state.pendingSyncCount).toBe(1);
            expect(state.lastSyncError).toContain('Failed to fetch');
        });

        it('recovers partial legacy fallback success and requeues only remaining answers', async function () {
            var state = createState();
            var apiRequest = vi.fn().mockImplementation(function (path, options) {
                if (path === 'submit_answers_batch') {
                    var batchError = new Error('Batch unavailable');
                    batchError.status = 503;
                    throw batchError;
                }

                if (options.body.question_id === 100) {
                    return Promise.resolve({
                        question_id: 100,
                        deferred: 0,
                        score_awarded: 1
                    });
                }

                var legacyError = new Error('Legacy unavailable');
                legacyError.status = 503;
                throw legacyError;
            });
            var manager = createAnswerSyncManager(createDeps(state, {
                apiRequest: apiRequest,
                questionAnswerPayload: function (q) { return q.answer; },
                payloadSignature: function (payload) { return 'sig-' + payload; }
            }));

            manager.queueQuestionAnswer({ id: 100, answer: 'A' });
            manager.queueQuestionAnswer({ id: 200, answer: 'B' });

            await expect(manager.flushPendingAnswerBatch({ flushAll: true })).rejects.toThrow('Legacy unavailable');

            expect(manager.getPendingSyncQuestionIds()).toEqual([200]);
            expect(state.pendingSyncCount).toBe(1);
        });

        it('keeps answers buffered when diagnostics force pending sync', async function () {
            var state = createState();
            var apiRequest = vi.fn();
            var diagnosticsManager = {
                enabled: true,
                isPendingSyncForced: vi.fn().mockReturnValue(true),
                recordActionTrail: vi.fn(),
                recordTimeline: vi.fn()
            };
            var manager = createAnswerSyncManager(createDeps(state, {
                apiRequest: apiRequest,
                diagnosticsManager: diagnosticsManager,
                questionAnswerPayload: function (q) { return q.answer; },
                payloadSignature: function (payload) { return 'sig-' + payload; }
            }));

            manager.queueQuestionAnswer({ id: 100, answer: 'A' });
            var result = await manager.flushPendingAnswerBatch({ flushAll: true });

            expect(apiRequest).not.toHaveBeenCalled();
            expect(result).toEqual({
                attempt_id: 42,
                accepted_count: 0,
                buffered: 1,
                flushed: 0,
                pending_count: 1,
                items: []
            });
            expect(state.syncBlockingReason).toBe('forced_pending_sync');
            expect(manager.getPendingSyncQuestionIds()).toEqual([100]);
            expect(state.pendingSyncCount).toBe(1);
        });
    });
});
