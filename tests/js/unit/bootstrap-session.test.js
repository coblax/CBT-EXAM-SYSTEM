import { describe, expect, it } from 'vitest';
import { createBootstrapSessionManager } from '../../../src/frontend/app/core/bootstrap-session.js';

function createFixture(overrides = {}) {
    var state = Object.assign({
        busy: false,
        error: '',
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
        render: 0,
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
        render: function () {
            calls.render += 1;
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
        }
    });

    return {
        calls,
        manager,
        state
    };
}

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
            tryResumeActiveAttemptFromExamList: async function () {
                return true;
            }
        });

        await fixture.manager.bootstrapFromPersistedSession();

        expect(fixture.state.token).toBe('token-123');
        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.calls.startSessionHeartbeat).toBe(1);
        expect(fixture.calls.persistAuthSession).toBe(1);
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
        expect(fixture.calls.startSessionHeartbeat).toBe(1);
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
        expect(fixture.calls.render).toBeGreaterThan(0);
    });

    it('renders safely when no persisted session is available', async function () {
        var fixture = createFixture();

        await fixture.manager.bootstrapFromPersistedSession();

        expect(fixture.calls.render).toBe(1);
        expect(fixture.calls.loadExams).toBe(0);
    });
});
