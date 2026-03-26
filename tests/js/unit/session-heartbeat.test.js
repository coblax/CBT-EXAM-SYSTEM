import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createSessionHeartbeatManager } from '../../../src/frontend/app/core/session-heartbeat.js';

function createDeferred() {
    var resolvePromise;
    var rejectPromise;
    var promise = new Promise(function (resolve, reject) {
        resolvePromise = resolve;
        rejectPromise = reject;
    });

    return {
        promise,
        reject: rejectPromise,
        resolve: resolvePromise
    };
}

function createHeartbeatFixture(overrides = {}) {
    var calls = {
        apiRequest: [],
        applyAttemptTimerPayload: [],
        clearCalculatorRuntimeState: 0,
        recordActionTrail: [],
        recordTimeline: [],
        refreshAttemptQuestionRevision: [],
        render: [],
        setQuestionRevision: []
    };
    var state = Object.assign({
        attemptId: 55,
        currentIndex: 1,
        exams: [
            {
                enable_calculator: 1,
                id: 9
            }
        ],
        notice: '',
        questionOrderSignature: 'order-1',
        questionRevision: null,
        selectedExamId: 9,
        stage: 'exam',
        token: 'token-123'
    }, overrides.state || {});

    var manager = createSessionHeartbeatManager({
        apiRequest: async function (path, options) {
            calls.apiRequest.push({
                options: options || {},
                path: path
            });
            if (typeof overrides.apiRequest === 'function') {
                return overrides.apiRequest(path, options);
            }
            return {
                attempt_timer: {
                    attempt_id: 55,
                    remaining_seconds: 240
                },
                enable_calculator: 1
            };
        },
        applyAttemptTimerPayload: function (payload) {
            calls.applyAttemptTimerPayload.push(payload || null);
        },
        clearCalculatorRuntimeState: function () {
            calls.clearCalculatorRuntimeState += 1;
        },
        diagnosticsManager: overrides.diagnosticsManager || null,
        getQuestionCount: function () {
            return overrides.localQuestionCount !== undefined ? overrides.localQuestionCount : 3;
        },
        normalizeQuestionRevision: function (payload) {
            return payload || null;
        },
        questionOrderSignatureEquals: function (incoming, current) {
            return String(incoming || '') === String(current || '');
        },
        questionRevisionEquals: function (incoming, current) {
            return JSON.stringify(incoming || null) === JSON.stringify(current || null);
        },
        recordActionTrail: function (kind, summary, meta) {
            calls.recordActionTrail.push({
                kind,
                meta: meta || {},
                summary
            });
        },
        recordTimeline: function (kind, summary, meta) {
            calls.recordTimeline.push({
                kind,
                meta: meta || {},
                summary
            });
        },
        refreshAttemptQuestionRevision: async function (revision, options) {
            calls.refreshAttemptQuestionRevision.push({
                options: options || {},
                revision: revision || null
            });
            return null;
        },
        render: function (reason, meta) {
            calls.render.push({
                meta: meta || {},
                reason: reason || ''
            });
        },
        sessionHeartbeatIntervalMs: overrides.sessionHeartbeatIntervalMs || 5000,
        setQuestionRevision: function (revision, examId) {
            calls.setQuestionRevision.push({
                examId,
                revision
            });
            state.questionRevision = revision;
        },
        state,
        windowRef: globalThis
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

describe('createSessionHeartbeatManager', function () {
    it('reuses the active heartbeat request and avoids duplicate fetches in parallel runs', async function () {
        var deferred = createDeferred();
        var fixture = createHeartbeatFixture({
            apiRequest: function () {
                return deferred.promise;
            }
        });

        var firstRun = fixture.manager.run();
        var secondRun = fixture.manager.run();

        expect(secondRun).toBe(firstRun);
        await Promise.resolve();
        expect(fixture.calls.apiRequest).toHaveLength(1);

        deferred.resolve({
            attempt_timer: {
                attempt_id: 55,
                remaining_seconds: 180
            }
        });

        await firstRun;
    });

    it('syncs timer payload and seeds question revision without refreshing the active attempt when session data is already aligned', async function () {
        var fixture = createHeartbeatFixture({
            apiRequest: async function () {
                return {
                    attempt_timer: {
                        attempt_id: 55,
                        remaining_seconds: 180
                    },
                    question_count: 3,
                    question_order_signature: 'order-1',
                    question_revision: {
                        revision_id: 'rev-2'
                    }
                };
            }
        });

        var payload = await fixture.manager.run();

        expect(payload.attempt_timer.remaining_seconds).toBe(180);
        expect(fixture.calls.applyAttemptTimerPayload).toEqual([
            {
                attempt_id: 55,
                remaining_seconds: 180
            }
        ]);
        expect(fixture.calls.setQuestionRevision).toEqual([
            {
                examId: 9,
                revision: {
                    revision_id: 'rev-2'
                }
            }
        ]);
        expect(fixture.calls.refreshAttemptQuestionRevision).toEqual([]);
    });

    it('refreshes question runtime when heartbeat detects question count drift', async function () {
        var fixture = createHeartbeatFixture({
            apiRequest: async function () {
                return {
                    attempt_timer: {
                        attempt_id: 55,
                        remaining_seconds: 150
                    },
                    question_count: 4,
                    question_order_signature: 'order-1',
                    question_revision: {
                        revision_id: 'rev-3'
                    }
                };
            },
            localQuestionCount: 2
        });

        await fixture.manager.run();

        expect(fixture.calls.refreshAttemptQuestionRevision).toEqual([
            {
                options: {
                    attemptId: 55,
                    examId: 9,
                    expectedQuestionOrderSignature: 'order-1',
                    force: true,
                    preferredIndex: 1,
                    source: 'heartbeat-count'
                },
                revision: {
                    revision_id: 'rev-3'
                }
            }
        ]);
    });

    it('disables calculator runtime from heartbeat and publishes a notice consistently', async function () {
        var fixture = createHeartbeatFixture({
            apiRequest: async function () {
                return {
                    attempt_timer: {
                        attempt_id: 55,
                        remaining_seconds: 210
                    },
                    enable_calculator: 0
                };
            }
        });

        await fixture.manager.run();

        expect(fixture.state.exams[0].enable_calculator).toBe(0);
        expect(fixture.calls.clearCalculatorRuntimeState).toBe(1);
        expect(fixture.state.notice).toBe('Kalkulator dinonaktifkan oleh guru untuk exam ini.');
        expect(fixture.calls.render).toEqual([
            {
                meta: {
                    attemptId: 55,
                    selectedExamId: 9
                },
                reason: 'heartbeat-calculator-availability'
            }
        ]);
    });

    it('starts a single heartbeat interval and stop clears it cleanly', function () {
        var fixture = createHeartbeatFixture();
        var intervalSpy = vi.spyOn(globalThis, 'setInterval');
        var clearIntervalSpy = vi.spyOn(globalThis, 'clearInterval');

        fixture.manager.start({
            immediate: false
        });
        fixture.manager.start({
            immediate: false
        });

        expect(intervalSpy).toHaveBeenCalledTimes(1);

        fixture.manager.stop();
        expect(clearIntervalSpy).toHaveBeenCalledTimes(1);
    });
});
