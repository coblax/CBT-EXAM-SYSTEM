import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { createBootstrapSessionManager } from '../../../src/frontend/app/core/bootstrap-session.js';

function createFixture(overrides = {}) {
    var state = Object.assign({
        busy: false,
        error: '',
        sessionRecoveryCanRetry: false,
        sessionRecoveryDetail: '',
        sessionRecoveryMode: '',
        sessionRecoveryPercent: 0,
        sessionRecoveryRetryCount: 0,
        sessionRecoverySlowStage: '',
        sessionRecoveryStartedAt: 0,
        sessionRecoveryStatus: '',
        sessionRecoveryStepIndex: 0,
        sessionRecoveryStepTotal: 0,
        sessionRecoveryVisible: false,
        selectedExamId: 0,
        stage: 'login',
        success: '',
        token: '',
        user: null
    }, overrides.state || {});
    var calls = {
        clearMessages: 0,
        fullLogout: 0,
        loadExams: 0,
        persistAuthSession: 0,
        reconcilePendingPageRefreshSecurityEvent: 0,
        render: 0,
        renderSnapshots: [],
        startSessionHeartbeat: 0,
        triggerPendingSyncLifecycleRetry: [],
        tryResumeActiveAttemptFromExamList: []
    };

    var manager = createBootstrapSessionManager({
        clearMessages: function () {
            calls.clearMessages += 1;
        },
        fullLogout: function () {
            calls.fullLogout += 1;
            state.busy = false;
            state.token = '';
        },
        loadExams: async function () {
            calls.loadExams += 1;
            if (typeof overrides.loadExams === 'function') {
                return overrides.loadExams();
            }
            return null;
        },
        persistAuthSession: function () {
            calls.persistAuthSession += 1;
        },
        readPersistedAuthSession: function () {
            return overrides.persisted || null;
        },
        reconcilePendingPageRefreshSecurityEvent: function () {
            calls.reconcilePendingPageRefreshSecurityEvent += 1;
        },
        render: function () {
            calls.render += 1;
            calls.renderSnapshots.push({
                busy: Boolean(state.busy),
                sessionRecoveryCanRetry: Boolean(state.sessionRecoveryCanRetry),
                sessionRecoveryMode: String(state.sessionRecoveryMode || ''),
                sessionRecoverySlowStage: String(state.sessionRecoverySlowStage || ''),
                sessionRecoveryStatus: String(state.sessionRecoveryStatus || ''),
                sessionRecoveryStepIndex: Number(state.sessionRecoveryStepIndex) || 0,
                sessionRecoveryVisible: Boolean(state.sessionRecoveryVisible),
                stage: String(state.stage || '')
            });
        },
        startSessionHeartbeat: function () {
            calls.startSessionHeartbeat += 1;
        },
        state,
        triggerPendingSyncLifecycleRetry: function (reason, options) {
            calls.triggerPendingSyncLifecycleRetry.push({
                options,
                reason
            });
        },
        tryResumeActiveAttemptFromExamList: async function (options) {
            calls.tryResumeActiveAttemptFromExamList.push(options);
            if (typeof overrides.tryResumeActiveAttemptFromExamList === 'function') {
                return overrides.tryResumeActiveAttemptFromExamList(options);
            }
            return false;
        },
        windowRef: overrides.windowRef || window
    });

    return {
        calls,
        manager,
        state
    };
}

beforeEach(function () {
    vi.useFakeTimers();
});

afterEach(function () {
    vi.useRealTimers();
});

describe('createBootstrapSessionManager', function () {
    it('restores a valid persisted session, resumes an active attempt, and starts heartbeat safely', async function () {
        var fixture = createFixture({
            persisted: {
                token: 'token-123',
                user: {
                    user_id: 9,
                    role: 'student'
                },
                selectedExamId: 44
            },
            state: {
                exams: [
                    {
                        id: 44,
                        latest_attempt_id: 91,
                        latest_attempt_status: 'in_progress'
                    }
                ]
            },
            tryResumeActiveAttemptFromExamList: async function () {
                return true;
            }
        });

        await fixture.manager.bootstrapFromPersistedSession();

        expect(fixture.state.token).toBe('token-123');
        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.sessionRecoveryVisible).toBe(false);
        expect(fixture.calls.startSessionHeartbeat).toBe(1);
        expect(fixture.calls.persistAuthSession).toBe(1);
        expect(fixture.calls.reconcilePendingPageRefreshSecurityEvent).toBe(1);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.sessionRecoveryVisible
                && snapshot.sessionRecoveryMode === 'exam_restore';
        })).toBe(true);
        expect(fixture.calls.triggerPendingSyncLifecycleRetry).toEqual([
            {
                options: { delayMs: 220 },
                reason: 'bootstrap-resume'
            }
        ]);
    });

    it('keeps a valid session bootstrapped even when no active attempt is resumed', async function () {
        var fixture = createFixture({
            persisted: {
                token: 'token-123',
                user: {
                    user_id: 9,
                    role: 'student'
                },
                selectedExamId: 44
            }
        });

        await fixture.manager.bootstrapFromPersistedSession();

        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.sessionRecoveryVisible).toBe(false);
        expect(fixture.calls.startSessionHeartbeat).toBe(1);
        expect(fixture.calls.reconcilePendingPageRefreshSecurityEvent).toBe(0);
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.sessionRecoveryVisible
                && snapshot.sessionRecoveryMode === 'confirm_restore'
                && snapshot.sessionRecoveryStepIndex === 4;
        })).toBe(true);
        expect(fixture.calls.triggerPendingSyncLifecycleRetry).toEqual([
            {
                options: { delayMs: 220 },
                reason: 'bootstrap-session'
            }
        ]);
    });

    it('clears the session path when bootstrap hits a revoked session style failure', async function () {
        var fixture = createFixture({
            persisted: {
                token: 'token-123',
                user: {
                    user_id: 9,
                    role: 'student'
                },
                selectedExamId: 44
            },
            loadExams: async function () {
                throw new Error('Sesi login ini sudah digantikan oleh login lain.');
            }
        });

        await fixture.manager.bootstrapFromPersistedSession();

        expect(fixture.calls.fullLogout).toBe(1);
        expect(fixture.state.error).toBe('Sesi login ini sudah digantikan oleh login lain.');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.sessionRecoveryVisible).toBe(false);
        expect(fixture.calls.render).toBeGreaterThan(0);
    });

    it('renders safely when no persisted session is available', async function () {
        var fixture = createFixture();

        await fixture.manager.bootstrapFromPersistedSession();

        expect(fixture.calls.render).toBe(1);
        expect(fixture.calls.loadExams).toBe(0);
    });

    it('exposes a safe retry path that keeps the persisted session and increments retry count', async function () {
        var firstLoadResolver = null;
        var fixture = createFixture({
            persisted: {
                token: 'token-123',
                user: {
                    user_id: 9,
                    role: 'student'
                },
                selectedExamId: 44
            },
            state: {
                exams: [
                    {
                        id: 44,
                        latest_attempt_id: 91,
                        latest_attempt_status: 'in_progress'
                    }
                ]
            },
            loadExams: async function () {
                if (firstLoadResolver === null) {
                    await new Promise(function (resolve) {
                        firstLoadResolver = resolve;
                    });
                    return null;
                }
                return null;
            }
        });

        var initialPromise = fixture.manager.bootstrapFromPersistedSession();
        vi.advanceTimersByTime(15010);
        await Promise.resolve();
        var retryPromise = fixture.manager.retrySessionRecovery();
        firstLoadResolver();
        await retryPromise;
        await initialPromise;

        expect(fixture.calls.loadExams).toBe(2);
        expect(fixture.state.token).toBe('token-123');
        expect(fixture.calls.renderSnapshots.some(function (snapshot) {
            return snapshot.sessionRecoveryVisible
                && snapshot.sessionRecoveryCanRetry;
        })).toBe(true);
    });
});
