import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createSessionLifecycleManager } from '../../../src/frontend/app/core/session-lifecycle.js';

function createTimerRoot() {
    var root = document.createElement('div');
    root.innerHTML = [
        '<div class="cbt-timer-chip">',
        '<span data-cbt-timer></span>',
        '</div>'
    ].join('');
    document.body.appendChild(root);
    return root;
}

function createLifecycleFixture(overrides = {}) {
    var calls = {
        bumpQuestionDataGeneration: 0,
        clearAttemptUiStateSyncTimer: 0,
        clearAttemptUiSyncRuntimeState: 0,
        clearAutoSaveRuntimeState: 0,
        clearMessages: 0,
        clearPendingRevisionSafeAnswerRestoreState: 0,
        clearPersistedAttemptUiState: [],
        clearPersistedAuthSession: 0,
        clearPersistedQuestionCache: [],
        clearQuestionCachePersistTimer: 0,
        clearQuestionPrefetchRuntimeState: 0,
        clearQuestionRevisionRefreshState: 0,
        clearSecurityLoggingRuntimeState: 0,
        exitFullscreenSilently: 0,
        flushAttemptUiState: 0,
        flushPendingAnswerBatch: 0,
        handleFinish: [],
        queueLoadedQuestionAnswersForFlush: 0,
        render: [],
        resetQuestionDataState: 0,
        sendLogoutRequestSilently: [],
        stopSessionHeartbeat: 0,
    };
    var root = overrides.root || createTimerRoot();
    var state = Object.assign({
        attemptId: 55,
        busy: false,
        calculatorError: 'bad calc',
        calculatorExpression: '1+1',
        calculatorResult: '2',
        calculatorVisible: true,
        error: '',
        examLockedForPendingFinish: false,
        examPickerMobileOpen: true,
        examToken: 'ABC123',
        finishConfirmOpen: true,
        finishConfirmSummary: { total: 2 },
        isFinishing: false,
        isFullscreenActive: true,
        loginIdentifier: 'ayu',
        loginPassword: 'secret',
        loginPasswordVisible: true,
        notice: '',
        pendingFinishAutoSubmit: true,
        questionOrderIds: [101, 102],
        questions: [{ id: 101 }, { id: 102 }],
        remainingSeconds: 120,
        result: { status: 'completed' },
        selectedExamId: 9,
        stage: 'exam',
        success: 'ok',
        timerId: 0,
        token: 'token-123',
        totalQuestions: 2,
        user: { user_id: 9 },
        userPhotoModalOpen: true
    }, overrides.state || {});

    var manager = createSessionLifecycleManager({
        bumpQuestionDataGeneration: function () {
            calls.bumpQuestionDataGeneration += 1;
        },
        clearAttemptUiStateSyncTimer: function () {
            calls.clearAttemptUiStateSyncTimer += 1;
        },
        clearAttemptUiSyncRuntimeState: function () {
            calls.clearAttemptUiSyncRuntimeState += 1;
        },
        clearAutoSaveRuntimeState: function () {
            calls.clearAutoSaveRuntimeState += 1;
        },
        clearMessages: function () {
            calls.clearMessages += 1;
        },
        clearPendingRevisionSafeAnswerRestoreState: function () {
            calls.clearPendingRevisionSafeAnswerRestoreState += 1;
        },
        clearPersistedAttemptUiState: function (attemptId) {
            calls.clearPersistedAttemptUiState.push(attemptId);
        },
        clearPersistedAuthSession: function () {
            calls.clearPersistedAuthSession += 1;
        },
        clearPersistedQuestionCache: function (attemptId) {
            calls.clearPersistedQuestionCache.push(attemptId);
        },
        clearQuestionCachePersistTimer: function () {
            calls.clearQuestionCachePersistTimer += 1;
        },
        clearQuestionPrefetchRuntimeState: function () {
            calls.clearQuestionPrefetchRuntimeState += 1;
        },
        clearQuestionRevisionRefreshState: function () {
            calls.clearQuestionRevisionRefreshState += 1;
        },
        clearSecurityLoggingRuntimeState: function () {
            calls.clearSecurityLoggingRuntimeState += 1;
        },
        exitFullscreenSilently: function () {
            calls.exitFullscreenSilently += 1;
        },
        flushAttemptUiState: async function () {
            calls.flushAttemptUiState += 1;
            return null;
        },
        flushPendingAnswerBatch: async function () {
            calls.flushPendingAnswerBatch += 1;
            return null;
        },
        formatSeconds: overrides.formatSeconds || function (seconds) {
            return 'T-' + String(seconds);
        },
        handleFinish: function (forced) {
            calls.handleFinish.push(Boolean(forced));
        },
        queueLoadedQuestionAnswersForFlush: function () {
            calls.queueLoadedQuestionAnswersForFlush += 1;
        },
        recordTimeline: function () {},
        render: function (reason, meta) {
            calls.render.push({
                meta: meta || null,
                reason: reason || ''
            });
        },
        resetQuestionDataState: function () {
            calls.resetQuestionDataState += 1;
        },
        root,
        sendLogoutRequestSilently: function (token) {
            calls.sendLogoutRequestSilently.push(String(token || ''));
        },
        state,
        stopSessionHeartbeat: function () {
            calls.stopSessionHeartbeat += 1;
        },
        windowRef: globalThis,
        logoutSyncTimeoutMs: 8000
    });

    return {
        calls,
        manager,
        root,
        state
    };
}

beforeEach(function () {
    vi.useFakeTimers();
});

afterEach(function () {
    document.body.innerHTML = '';
    vi.useRealTimers();
});

describe('createSessionLifecycleManager', function () {
    it('clamps timer payload safely and ignores mismatched attempts', function () {
        var formatSeconds = vi.fn(function (seconds) {
            return 'T-' + String(seconds);
        });
        var fixture = createLifecycleFixture({
            formatSeconds: formatSeconds,
            state: {
                remainingSeconds: 120
            }
        });

        fixture.manager.applyAttemptTimerPayload({
            attempt_id: 99,
            remaining_seconds: 15
        });
        expect(fixture.state.remainingSeconds).toBe(120);
        expect(formatSeconds).not.toHaveBeenCalled();

        fixture.manager.applyAttemptTimerPayload({
            attempt_id: 55,
            remaining_seconds: -5
        });
        expect(fixture.state.remainingSeconds).toBe(0);
        expect(fixture.calls.handleFinish).toEqual([true]);
        expect(formatSeconds).toHaveBeenCalled();
    });

    it('ignores tiny timer drift and keeps the current remaining time untouched', function () {
        var formatSeconds = vi.fn(function (seconds) {
            return 'T-' + String(seconds);
        });
        var fixture = createLifecycleFixture({
            formatSeconds: formatSeconds,
            state: {
                remainingSeconds: 120
            }
        });

        fixture.manager.applyAttemptTimerPayload({
            attempt_id: 55,
            remaining_seconds: 119
        });

        expect(fixture.state.remainingSeconds).toBe(120);
        expect(formatSeconds).not.toHaveBeenCalled();
        expect(fixture.calls.handleFinish).toEqual([]);
    });

    it('updates remaining time cleanly when resume payload brings a larger timer change', function () {
        var fixture = createLifecycleFixture({
            state: {
                remainingSeconds: 90
            }
        });

        fixture.manager.applyAttemptTimerPayload({
            attempt_id: 55,
            remaining_seconds: 240
        });

        expect(fixture.state.remainingSeconds).toBe(240);
        expect(fixture.root.querySelector('[data-cbt-timer]').textContent).toBe('T-240');
    });

    it('counts down to zero and triggers finish transition once without leaving a timer running', function () {
        var fixture = createLifecycleFixture({
            state: {
                remainingSeconds: 1
            }
        });

        fixture.manager.startTimer();
        vi.advanceTimersByTime(2000);

        expect(fixture.state.remainingSeconds).toBe(0);
        expect(fixture.calls.handleFinish).toEqual([true]);
        expect(fixture.state.timerId).toBe(0);
    });

    it('clears timer, runtime state, and persisted snapshots when authenticated frontend state is reset', function () {
        var fixture = createLifecycleFixture();

        fixture.manager.startTimer();
        expect(fixture.state.timerId).not.toBe(0);

        fixture.manager.clearAuthenticatedFrontendState({
            error: 'expired',
            stage: 'login'
        });

        expect(fixture.state.stage).toBe('login');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.attemptId).toBe(0);
        expect(fixture.state.remainingSeconds).toBe(0);
        expect(fixture.state.calculatorVisible).toBe(false);
        expect(fixture.calls.stopSessionHeartbeat).toBe(1);
        expect(fixture.calls.clearSecurityLoggingRuntimeState).toBe(1);
        expect(fixture.calls.clearAutoSaveRuntimeState).toBe(1);
        expect(fixture.calls.clearQuestionPrefetchRuntimeState).toBe(1);
        expect(fixture.calls.clearAttemptUiStateSyncTimer).toBe(1);
        expect(fixture.calls.clearQuestionCachePersistTimer).toBe(1);
        expect(fixture.calls.clearAttemptUiSyncRuntimeState).toBe(1);
        expect(fixture.calls.clearPendingRevisionSafeAnswerRestoreState).toBe(1);
        expect(fixture.calls.clearQuestionRevisionRefreshState).toBe(1);
        expect(fixture.calls.bumpQuestionDataGeneration).toBe(1);
        expect(fixture.calls.resetQuestionDataState).toBe(1);
        expect(fixture.calls.clearPersistedAttemptUiState).toEqual([55]);
        expect(fixture.calls.clearPersistedQuestionCache).toEqual([55]);
        expect(fixture.calls.clearPersistedAuthSession).toBe(1);
    });

    it('resets exam session cleanup without leaving calculator or attempt state behind when stage already changed', function () {
        var fixture = createLifecycleFixture({
            state: {
                stage: 'result'
            }
        });

        fixture.manager.startTimer();
        fixture.manager.resetExamSession();

        expect(fixture.state.timerId).toBe(0);
        expect(fixture.state.attemptId).toBe(0);
        expect(fixture.state.remainingSeconds).toBe(0);
        expect(fixture.state.calculatorVisible).toBe(false);
        expect(fixture.state.calculatorExpression).toBe('');
        expect(fixture.state.calculatorResult).toBe('');
        expect(fixture.state.calculatorError).toBe('');
        expect(fixture.calls.exitFullscreenSilently).toBe(1);
        expect(fixture.calls.clearPersistedAttemptUiState).toEqual([55]);
        expect(fixture.calls.clearPersistedQuestionCache).toEqual([55]);
    });

    it('waits for logout request completion before clearing local auth state during normal logout', async function () {
        var resolveLogoutRequest;
        var logoutRequestPromise = new Promise(function (resolve) {
            resolveLogoutRequest = resolve;
        });
        var fixture = createLifecycleFixture({
            state: {
                stage: 'confirm',
                attemptId: 0,
                questionOrderIds: [],
                questions: [],
                totalQuestions: 0
            }
        });

        fixture.calls.sendLogoutRequestSilently = [];
        fixture.manager = createSessionLifecycleManager({
            bumpQuestionDataGeneration: function () {
                fixture.calls.bumpQuestionDataGeneration += 1;
            },
            clearAttemptUiStateSyncTimer: function () {
                fixture.calls.clearAttemptUiStateSyncTimer += 1;
            },
            clearAttemptUiSyncRuntimeState: function () {
                fixture.calls.clearAttemptUiSyncRuntimeState += 1;
            },
            clearAutoSaveRuntimeState: function () {
                fixture.calls.clearAutoSaveRuntimeState += 1;
            },
            clearMessages: function () {
                fixture.calls.clearMessages += 1;
            },
            clearPendingRevisionSafeAnswerRestoreState: function () {
                fixture.calls.clearPendingRevisionSafeAnswerRestoreState += 1;
            },
            clearPersistedAttemptUiState: function (attemptId) {
                fixture.calls.clearPersistedAttemptUiState.push(attemptId);
            },
            clearPersistedAuthSession: function () {
                fixture.calls.clearPersistedAuthSession += 1;
            },
            clearPersistedQuestionCache: function (attemptId) {
                fixture.calls.clearPersistedQuestionCache.push(attemptId);
            },
            clearQuestionCachePersistTimer: function () {
                fixture.calls.clearQuestionCachePersistTimer += 1;
            },
            clearQuestionPrefetchRuntimeState: function () {
                fixture.calls.clearQuestionPrefetchRuntimeState += 1;
            },
            clearQuestionRevisionRefreshState: function () {
                fixture.calls.clearQuestionRevisionRefreshState += 1;
            },
            clearSecurityLoggingRuntimeState: function () {
                fixture.calls.clearSecurityLoggingRuntimeState += 1;
            },
            exitFullscreenSilently: function () {
                fixture.calls.exitFullscreenSilently += 1;
            },
            flushAttemptUiState: async function () {
                fixture.calls.flushAttemptUiState += 1;
                return null;
            },
            flushPendingAnswerBatch: async function () {
                fixture.calls.flushPendingAnswerBatch += 1;
                return null;
            },
            formatSeconds: function (seconds) {
                return 'T-' + String(seconds);
            },
            handleFinish: function (forced) {
                fixture.calls.handleFinish.push(Boolean(forced));
            },
            queueLoadedQuestionAnswersForFlush: function () {
                fixture.calls.queueLoadedQuestionAnswersForFlush += 1;
            },
            recordTimeline: function () {},
            render: function (reason, meta) {
                fixture.calls.render.push({
                    meta: meta || null,
                    reason: reason || ''
                });
            },
            resetQuestionDataState: function () {
                fixture.calls.resetQuestionDataState += 1;
            },
            root: fixture.root,
            sendLogoutRequestSilently: function (token) {
                fixture.calls.sendLogoutRequestSilently.push(String(token || ''));
                return logoutRequestPromise;
            },
            state: fixture.state,
            stopSessionHeartbeat: function () {
                fixture.calls.stopSessionHeartbeat += 1;
            },
            windowRef: globalThis,
            logoutSyncTimeoutMs: 8000
        });

        var logoutPromise = fixture.manager.fullLogout();
        await Promise.resolve();

        expect(fixture.calls.sendLogoutRequestSilently).toEqual(['token-123']);
        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.calls.clearPersistedAuthSession).toBe(0);

        resolveLogoutRequest({ ok: true });
        await logoutPromise;

        expect(fixture.state.stage).toBe('login');
        expect(fixture.calls.clearPersistedAuthSession).toBe(1);
    });
});
