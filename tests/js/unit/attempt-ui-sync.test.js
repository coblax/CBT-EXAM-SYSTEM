import { describe, expect, it, vi } from 'vitest';
import { createAttemptUiSyncManager } from '../../../src/frontend/app/core/attempt-ui-sync.js';

function cloneValue(value) {
    return JSON.parse(JSON.stringify(value));
}

function createDeferred() {
    var resolvePromise;
    var rejectPromise;
    var promise = new Promise(function (resolve, reject) {
        resolvePromise = resolve;
        rejectPromise = reject;
    });

    return {
        promise,
        resolve: resolvePromise,
        reject: rejectPromise
    };
}

function createAttemptUiSyncFixture(overrides = {}) {
    var currentSnapshot = cloneValue(overrides.initialSnapshot || {
        attempt_id: 55,
        current_index: 0,
        current_question_id: 101,
        updated_at: 100
    });
    var persistCalls = [];

    var manager = createAttemptUiSyncManager({
        attemptUiStateSyncDelayMs: 250,
        apiRequest: overrides.apiRequest || (async function () {
            return null;
        }),
        buildAttemptUiStateSnapshot: function () {
            return currentSnapshot ? cloneValue(currentSnapshot) : null;
        },
        payloadSignature: function (snapshot) {
            return snapshot ? JSON.stringify(snapshot) : '';
        },
        persistAttemptUiStateLocally: function (snapshot) {
            persistCalls.push(cloneValue(snapshot));
        },
        state: Object.assign({
            attemptId: 55,
            isFinishing: false,
            stage: 'exam',
            token: 'token-123'
        }, overrides.state || {}),
        windowRef: globalThis
    });

    return {
        manager,
        persistCalls,
        setCurrentSnapshot: function (snapshot) {
            currentSnapshot = snapshot ? cloneValue(snapshot) : null;
        }
    };
}

describe('createAttemptUiSyncManager', function () {
    it('does not overwrite a newer local snapshot with an older ui_state response payload', async function () {
        vi.useFakeTimers();
        var deferred = createDeferred();
        var fixture = createAttemptUiSyncFixture({
            apiRequest: function () {
                return deferred.promise;
            }
        });

        var flushPromise = fixture.manager.flush();
        fixture.setCurrentSnapshot({
            attempt_id: 55,
            current_index: 1,
            current_question_id: 102,
            updated_at: 200
        });
        deferred.resolve({
            attempt_state: {
                attempt_id: 55,
                current_index: 0,
                current_question_id: 101,
                updated_at: 100
            }
        });

        await flushPromise;

        expect(fixture.persistCalls).toEqual([
            {
                attempt_id: 55,
                current_index: 0,
                current_question_id: 101,
                updated_at: 100
            },
            {
                attempt_id: 55,
                current_index: 1,
                current_question_id: 102,
                updated_at: 200
            }
        ]);

        vi.useRealTimers();
    });

    it('accepts a response attempt_state when it is newer than the latest local snapshot', async function () {
        var fixture = createAttemptUiSyncFixture({
            initialSnapshot: {
                attempt_id: 55,
                current_index: 0,
                current_question_id: 101,
                updated_at: 100
            },
            apiRequest: async function () {
                return {
                    attempt_state: {
                        attempt_id: 55,
                        current_index: 2,
                        current_question_id: 103,
                        updated_at: 300
                    }
                };
            }
        });

        await fixture.manager.flush();

        expect(fixture.persistCalls).toEqual([
            {
                attempt_id: 55,
                current_index: 0,
                current_question_id: 101,
                updated_at: 100
            },
            {
                attempt_id: 55,
                current_index: 2,
                current_question_id: 103,
                updated_at: 300
            }
        ]);
    });

    it('marks ui_state sync as best-effort so auth expiry is not triggered by that request', async function () {
        var requestOptions = null;
        var fixture = createAttemptUiSyncFixture({
            apiRequest: async function (endpoint, options) {
                requestOptions = options || {};
                return null;
            }
        });

        await fixture.manager.flush();

        expect(requestOptions).toMatchObject({
            method: 'POST',
            suppressAuthExpiry: true
        });
    });

    it('does not re-sync when only updated_at changes in the local snapshot', async function () {
        vi.useFakeTimers();
        var apiCalls = 0;
        var fixture = createAttemptUiSyncFixture({
            apiRequest: async function () {
                apiCalls += 1;
                return null;
            }
        });

        fixture.manager.syncSignatureToCurrentState();
        fixture.setCurrentSnapshot({
            attempt_id: 55,
            current_index: 0,
            current_question_id: 101,
            updated_at: 200
        });

        fixture.manager.scheduleSync(50);
        await vi.advanceTimersByTimeAsync(60);

        expect(apiCalls).toBe(0);

        vi.useRealTimers();
    });

    it('re-syncs when attempt ui content changes even if updated_at is also refreshed', async function () {
        var apiCalls = 0;
        var fixture = createAttemptUiSyncFixture({
            apiRequest: async function () {
                apiCalls += 1;
                return null;
            }
        });

        fixture.manager.syncSignatureToCurrentState();
        fixture.setCurrentSnapshot({
            attempt_id: 55,
            current_index: 1,
            current_question_id: 102,
            updated_at: 200
        });

        await fixture.manager.flush();

        expect(apiCalls).toBe(1);
    });

    it('still allows force flush when only updated_at changes', async function () {
        var apiCalls = 0;
        var fixture = createAttemptUiSyncFixture({
            apiRequest: async function () {
                apiCalls += 1;
                return null;
            }
        });

        fixture.manager.syncSignatureToCurrentState();
        fixture.setCurrentSnapshot({
            attempt_id: 55,
            current_index: 0,
            current_question_id: 101,
            updated_at: 200
        });

        await fixture.manager.flush({
            force: true
        });

        expect(apiCalls).toBe(1);
    });

    it('does not send ui_state when the auth token is already empty', async function () {
        var apiCalls = 0;
        var fixture = createAttemptUiSyncFixture({
            apiRequest: async function () {
                apiCalls += 1;
                return null;
            },
            state: {
                token: ''
            }
        });

        await fixture.manager.flush();
        fixture.manager.scheduleSync(50);

        expect(apiCalls).toBe(0);
        expect(fixture.persistCalls).toEqual([]);
    });

    it('does not reschedule ui_state sync after an unauthorized response', async function () {
        vi.useFakeTimers();
        var apiCalls = 0;
        var fixture = createAttemptUiSyncFixture({
            apiRequest: async function () {
                apiCalls += 1;
                var error = new Error('Unauthorized');
                error.status = 401;
                error.code = 'unauthorized';
                throw error;
            }
        });

        await expect(fixture.manager.flush()).rejects.toMatchObject({
            status: 401
        });

        await vi.advanceTimersByTimeAsync(500);
        expect(apiCalls).toBe(1);

        vi.useRealTimers();
    });
});
