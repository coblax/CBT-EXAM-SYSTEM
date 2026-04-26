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
        findPersistedFinishRecoveryForExam: [],
        fullLogout: 0,
        loadExams: 0,
        persistAuthSession: 0,
        readPersistedQuestionCache: [],
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
        findPersistedFinishRecoveryForExam: async function (examId) {
            calls.findPersistedFinishRecoveryForExam.push(Number(examId) || 0);
            if (typeof overrides.findPersistedFinishRecoveryForExam === 'function') {
                return overrides.findPersistedFinishRecoveryForExam(examId);
            }
            return null;
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
        readPersistedQuestionCache: async function (attemptId) {
            calls.readPersistedQuestionCache.push(Number(attemptId) || 0);
            if (typeof overrides.readPersistedQuestionCache === 'function') {
                return overrides.readPersistedQuestionCache(attemptId);
            }
            return null;
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
                lastStage: 'exam',
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
                lastStage: 'confirm',
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

    it('keeps the user on confirm when refresh happens from preparation even if an attempt is still in progress', async function () {
        var fixture = createFixture({
            persisted: {
                lastStage: 'confirm',
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
            }
        });

        await fixture.manager.bootstrapFromPersistedSession();

        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.calls.tryResumeActiveAttemptFromExamList).toEqual([]);
        expect(fixture.calls.reconcilePendingPageRefreshSecurityEvent).toBe(0);
        expect(fixture.calls.triggerPendingSyncLifecycleRetry).toEqual([
            {
                options: { delayMs: 220 },
                reason: 'bootstrap-session'
            }
        ]);
    });

    it('restores a persisted finish receipt into locked recovery mode instead of reopening exam editing', async function () {
        var fixture = createFixture({
            persisted: {
                lastStage: 'exam',
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
                        latest_attempt_status: 'completed'
                    }
                ]
            },
            readPersistedQuestionCache: async function () {
                return {
                    finishReceipt: {
                        attemptId: 91,
                        examId: 44,
                        finishedAt: '2026-04-09 08:00:00',
                        status: 'completed',
                        resultViewModeHint: 'full',
                        showStudentResultHint: 1,
                        ackSource: 'finish_exam',
                        pendingResultFetch: true,
                        updatedAt: 1710000000000
                    }
                };
            }
        });

        await fixture.manager.bootstrapFromPersistedSession();

        expect(fixture.state.stage).toBe('exam');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.attemptId).toBe(91);
        expect(fixture.state.examLockedForPendingFinish).toBe(true);
        expect(fixture.state.finishResultPending).toBe(true);
        expect(fixture.state.finishReceipt).toMatchObject({
            attempt_id: 91,
            exam_id: 44,
            status: 'completed'
        });
        expect(fixture.calls.readPersistedQuestionCache).toEqual([91]);
        expect(fixture.calls.tryResumeActiveAttemptFromExamList).toEqual([]);
        expect(fixture.calls.triggerPendingSyncLifecycleRetry).toEqual([
            {
                options: { delayMs: 220 },
                reason: 'bootstrap-finish-recovery'
            }
        ]);
    });

    it('falls back to a persisted finish receipt lookup by exam when latest attempt data is missing from exam list', async function () {
        var fixture = createFixture({
            persisted: {
                lastStage: 'exam',
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
                        latest_attempt_id: 0,
                        latest_attempt_status: ''
                    }
                ]
            },
            findPersistedFinishRecoveryForExam: async function () {
                return {
                    attemptId: 91,
                    finishReceipt: {
                        attemptId: 91,
                        examId: 44,
                        finishedAt: '2026-04-09 08:00:00',
                        status: 'completed',
                        resultViewModeHint: 'full',
                        showStudentResultHint: 1,
                        ackSource: 'finish_exam',
                        pendingResultFetch: 1,
                        updatedAt: 123456
                    },
                    snapshot: {
                        finishReceipt: {
                            attemptId: 91,
                            examId: 44,
                            finishedAt: '2026-04-09 08:00:00',
                            status: 'completed',
                            resultViewModeHint: 'full',
                            showStudentResultHint: 1,
                            ackSource: 'finish_exam',
                            pendingResultFetch: 1,
                            updatedAt: 123456
                        }
                    }
                };
            }
        });

        await fixture.manager.bootstrapFromPersistedSession();

        expect(fixture.state.stage).toBe('exam');
        expect(fixture.state.attemptId).toBe(91);
        expect(fixture.state.examLockedForPendingFinish).toBe(true);
        expect(fixture.state.finishResultPending).toBe(true);
        expect(fixture.calls.readPersistedQuestionCache).toEqual([]);
        expect(fixture.calls.findPersistedFinishRecoveryForExam).toEqual([44]);
        expect(fixture.calls.tryResumeActiveAttemptFromExamList).toEqual([]);
        expect(fixture.calls.triggerPendingSyncLifecycleRetry).toEqual([
            {
                options: { delayMs: 220 },
                reason: 'bootstrap-finish-recovery'
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

    it('keeps active exam recovery on the exam stage when a recovered request is still rejected', async function () {
        var fixture = createFixture({
            persisted: {
                lastStage: 'exam',
                token: 'token-123',
                user: {
                    user_id: 9,
                    role: 'student'
                },
                selectedExamId: 44
            },
            state: {
                attemptId: 91,
                exams: [
                    {
                        id: 44,
                        latest_attempt_id: 91,
                        latest_attempt_status: 'in_progress'
                    }
                ],
                stage: 'exam',
                token: 'token-123',
                user: {
                    user_id: 9,
                    role: 'student'
                }
            },
            loadExams: async function () {
                var error = new Error('Sesi login ini sudah digantikan oleh login lain.');
                error.code = 'session_revoked';
                error.status = 401;
                throw error;
            }
        });

        await fixture.manager.bootstrapFromPersistedSession({
            incrementRetry: true,
            preserveActiveExamStage: true
        });

        expect(fixture.calls.fullLogout).toBe(0);
        expect(fixture.state.stage).toBe('exam');
        expect(fixture.state.token).toBe('token-123');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.sessionRecoveryVisible).toBe(true);
        expect(fixture.state.sessionRecoveryCanRetry).toBe(true);
        expect(fixture.state.sessionRecoveryMode).toBe('exam_restore');
        expect(fixture.calls.renderSnapshots.every(function (snapshot) {
            return snapshot.stage !== 'login';
        })).toBe(true);
    });

    it('keeps recovery retryable instead of logging out when bootstrap hits a network failure', async function () {
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
                var error = new Error('Gagal terhubung ke server.');
                error.code = 'network_error';
                error.status = 0;
                throw error;
            }
        });

        await fixture.manager.bootstrapFromPersistedSession();

        expect(fixture.calls.fullLogout).toBe(0);
        expect(fixture.state.token).toBe('token-123');
        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.error).toBe('Gagal terhubung ke server.');
        expect(fixture.state.sessionRecoveryVisible).toBe(true);
        expect(fixture.state.sessionRecoveryCanRetry).toBe(true);
        expect(fixture.state.sessionRecoverySlowStage).toBe('hold');
        expect(fixture.calls.render).toBeGreaterThan(0);
    });

    it('keeps recovery retryable when an existing token is rejected as missing by the server', async function () {
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
                var error = new Error('Authorization token not found');
                error.code = 'missing_token';
                error.status = 401;
                throw error;
            }
        });

        await fixture.manager.bootstrapFromPersistedSession();

        expect(fixture.calls.fullLogout).toBe(0);
        expect(fixture.state.token).toBe('token-123');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.sessionRecoveryVisible).toBe(true);
        expect(fixture.state.sessionRecoveryCanRetry).toBe(true);
        expect(fixture.state.sessionRecoveryStatus).toBe('Sesi belum dapat dipulihkan');
        expect(fixture.state.sessionRecoveryDetail).toBe('Authorization token not found');
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
