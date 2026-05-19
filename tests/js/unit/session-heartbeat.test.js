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
        renderExamPartial: [],
        sendSecurityEventSilently: [],
        setQuestionRevision: []
    };
    var state = Object.assign({
        attemptId: 55,
        connectionStatus: 'online',
        currentIndex: 1,
        exams: [
            {
                enable_calculator: 1,
                id: 9
            }
        ],
        heartbeatLostActive: false,
        heartbeatLostFailureCount: 0,
        heartbeatLostLastErrorCode: '',
        notice: '',
        questionOrderSignature: 'order-1',
        questionRevision: null,
        selectedExamId: 9,
        stage: 'exam',
        token: 'token-123'
    }, overrides.state || {});
    var documentRef = {
        hasFocus: function () {
            return overrides.hasDocumentFocus !== false;
        },
        visibilityState: overrides.visibilityState || 'visible'
    };
    var windowRef = overrides.windowRef || globalThis;

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
        documentRef: documentRef,
        getQuestionCount: function () {
            return overrides.localQuestionCount !== undefined ? overrides.localQuestionCount : 3;
        },
        isHeartbeatLostDetectionEnabled: function () {
            return overrides.heartbeatLostDetectionEnabled !== false;
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
        renderExamPartial: function (regions, reason, meta) {
            calls.renderExamPartial.push({
                meta: meta || {},
                reason: reason || '',
                regions: regions || {}
            });
            if (typeof overrides.renderExamPartial === 'function') {
                return overrides.renderExamPartial(regions, reason, meta);
            }
            return false;
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
        sendSecurityEventSilently: function (eventType, context, options) {
            calls.sendSecurityEventSilently.push({
                context: context || {},
                eventType,
                options: options || {}
            });
            return true;
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
        windowRef: windowRef
    });

    return {
        calls,
        documentRef: documentRef,
        manager,
        state,
        windowRef: windowRef
    };
}

beforeEach(function () {
    vi.useFakeTimers();
});

afterEach(function () {
    vi.useRealTimers();
});

describe('createSessionHeartbeatManager', function () {
    it('keeps the default heartbeat interval at 20 seconds when no adaptive payload is applied', async function () {
        var fixture = createHeartbeatFixture({
            sessionHeartbeatIntervalMs: 20000
        });

        fixture.manager.start({
            immediate: false
        });

        await vi.advanceTimersByTimeAsync(19000);
        expect(fixture.calls.apiRequest).toHaveLength(0);

        await vi.advanceTimersByTimeAsync(1000);
        expect(fixture.calls.apiRequest).toHaveLength(1);
    });

    it('updates the heartbeat interval to 30 seconds for busy adaptive load', async function () {
        var fixture = createHeartbeatFixture({
            sessionHeartbeatIntervalMs: 20000
        });

        fixture.manager.applyAdaptiveLoadPayload({
            adaptive_load: {
                level: 'busy',
                heartbeat_interval_ms: 30000,
                admin_snapshot_refresh_seconds: 20,
                reasons: ['Start queue mulai padat.'],
                source: 'auto'
            }
        });
        fixture.manager.start({
            immediate: false
        });

        await vi.advanceTimersByTimeAsync(29000);
        expect(fixture.calls.apiRequest).toHaveLength(0);

        await vi.advanceTimersByTimeAsync(1000);
        expect(fixture.calls.apiRequest).toHaveLength(1);
        expect(fixture.state.adaptiveLoadLevel).toBe('busy');
        expect(fixture.state.adaptiveLoadHeartbeatIntervalMs).toBe(30000);
    });

    it('updates the heartbeat interval to 45 seconds for critical adaptive load without breaking the running timer', async function () {
        var fixture = createHeartbeatFixture({
            sessionHeartbeatIntervalMs: 20000
        });

        fixture.manager.applyAdaptiveLoadPayload({
            adaptive_load: {
                level: 'busy',
                heartbeat_interval_ms: 30000,
                admin_snapshot_refresh_seconds: 20,
                reasons: ['Start queue mulai padat.'],
                source: 'auto'
            }
        });
        fixture.manager.start({
            immediate: false
        });

        await vi.advanceTimersByTimeAsync(30000);
        expect(fixture.calls.apiRequest).toHaveLength(1);

        fixture.manager.applyAdaptiveLoadPayload({
            adaptive_load: {
                level: 'critical',
                heartbeat_interval_ms: 45000,
                admin_snapshot_refresh_seconds: 40,
                reasons: ['Start queue sangat padat.'],
                source: 'manual_override'
            }
        });

        fixture.calls.apiRequest.length = 0;

        await vi.advanceTimersByTimeAsync(30000);
        expect(fixture.calls.apiRequest).toHaveLength(0);

        await vi.advanceTimersByTimeAsync(15000);
        expect(fixture.calls.apiRequest).toHaveLength(1);
        expect(fixture.state.adaptiveLoadLevel).toBe('critical');
        expect(fixture.state.adaptiveLoadHeartbeatIntervalMs).toBe(45000);
    });

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

    it('sends live presence fields on exam heartbeat requests', async function () {
        var fixture = createHeartbeatFixture({
            hasDocumentFocus: false,
            state: {
                connectionStatus: 'degraded',
                heartbeatLostActive: true,
                pendingSyncCount: 3
            },
            visibilityState: 'hidden'
        });

        await fixture.manager.run();

        expect(fixture.calls.apiRequest).toHaveLength(1);
        expect(fixture.calls.apiRequest[0]).toEqual({
            options: {
                query: {
                    attempt_id: 55,
                    presence_connection_status: 'degraded',
                    presence_has_focus: 0,
                    presence_heartbeat_lost_active: 1,
                    presence_pending_sync_count: 3,
                    presence_visibility_state: 'hidden'
                }
            },
            path: 'session'
        });
    });

    it('skips heartbeat and clears lost state when token is missing or stage is login', async function () {
        var fixture = createHeartbeatFixture({
            state: {
                heartbeatLostActive: true,
                heartbeatLostFailureCount: 4,
                heartbeatLostLastErrorCode: 'network_error',
                stage: 'login',
                token: ''
            }
        });

        var result = await fixture.manager.run();

        expect(result).toBeNull();
        expect(fixture.calls.apiRequest).toHaveLength(0);
        expect(fixture.state.heartbeatLostActive).toBe(false);
        expect(fixture.state.heartbeatLostFailureCount).toBe(0);
        expect(fixture.state.heartbeatLostLastErrorCode).toBe('');
        expect(fixture.calls.render).toEqual([]);
    });

    it('skips heartbeat outside active exam attempts so transition screens do not call session with an empty attempt id', async function () {
        var fixture = createHeartbeatFixture({
            state: {
                attemptId: 0,
                heartbeatLostActive: true,
                heartbeatLostFailureCount: 2,
                heartbeatLostLastErrorCode: 'invalid_attempt_id',
                stage: 'confirm'
            }
        });

        var result = await fixture.manager.run();

        expect(result).toBeNull();
        expect(fixture.calls.apiRequest).toHaveLength(0);
        expect(fixture.state.heartbeatLostActive).toBe(false);
        expect(fixture.state.heartbeatLostFailureCount).toBe(0);
        expect(fixture.state.heartbeatLostLastErrorCode).toBe('');
        expect(fixture.calls.render).toEqual([]);
    });

    it('does not start the heartbeat timer on non-exam transition screens', function () {
        var windowRef = {
            clearInterval: vi.fn(),
            navigator: {
                onLine: true
            },
            setInterval: vi.fn(),
            setTimeout: vi.fn()
        };
        var fixture = createHeartbeatFixture({
            state: {
                attemptId: 0,
                stage: 'result'
            },
            windowRef: windowRef
        });

        fixture.manager.start();

        expect(windowRef.setInterval).not.toHaveBeenCalled();
        expect(fixture.calls.apiRequest).toHaveLength(0);
    });

    it('pauses an existing heartbeat timer after leaving an active exam attempt', async function () {
        var windowRef = {
            clearInterval: vi.fn(),
            navigator: {
                onLine: true
            },
            setInterval: vi.fn(function () {
                return 77;
            }),
            setTimeout: vi.fn()
        };
        var fixture = createHeartbeatFixture({
            windowRef: windowRef
        });

        fixture.manager.start({
            immediate: false
        });
        fixture.state.attemptId = 0;
        fixture.state.stage = 'result';

        var result = await fixture.manager.run();

        expect(result).toBeNull();
        expect(windowRef.clearInterval).toHaveBeenCalledWith(77);
        expect(fixture.calls.apiRequest).toHaveLength(0);

        fixture.state.attemptId = 55;
        fixture.state.stage = 'exam';
        fixture.manager.start({
            immediate: false
        });

        expect(windowRef.setInterval).toHaveBeenCalledTimes(2);
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

    it('refreshes question runtime when heartbeat detects order signature drift', async function () {
        var fixture = createHeartbeatFixture({
            apiRequest: async function () {
                return {
                    attempt_timer: {
                        attempt_id: 55,
                        remaining_seconds: 150
                    },
                    question_count: 3,
                    question_order_signature: 'order-2',
                    question_revision: {
                        revision_id: 'rev-3'
                    }
                };
            }
        });

        await fixture.manager.run();

        expect(fixture.calls.refreshAttemptQuestionRevision).toEqual([
            {
                options: {
                    attemptId: 55,
                    examId: 9,
                    expectedQuestionOrderSignature: 'order-2',
                    force: true,
                    preferredIndex: 1,
                    source: 'heartbeat-order'
                },
                revision: {
                    revision_id: 'rev-3'
                }
            }
        ]);
    });

    it('refreshes question runtime when heartbeat detects revision drift only', async function () {
        var fixture = createHeartbeatFixture({
            apiRequest: async function () {
                return {
                    attempt_timer: {
                        attempt_id: 55,
                        remaining_seconds: 150
                    },
                    question_count: 3,
                    question_order_signature: 'order-1',
                    question_revision: {
                        revision_id: 'rev-2'
                    }
                };
            },
            state: {
                questionRevision: {
                    revision_id: 'rev-1'
                }
            }
        });

        await fixture.manager.run();

        expect(fixture.calls.refreshAttemptQuestionRevision).toEqual([
            {
                options: {
                    attemptId: 55,
                    examId: 9,
                    expectedQuestionOrderSignature: 'order-1',
                    force: false,
                    preferredIndex: 1,
                    source: 'heartbeat'
                },
                revision: {
                    revision_id: 'rev-2'
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

    it('re-enables calculator from heartbeat and clears the disabled notice', async function () {
        var fixture = createHeartbeatFixture({
            apiRequest: async function () {
                return {
                    attempt_timer: {
                        attempt_id: 55,
                        remaining_seconds: 210
                    },
                    enable_calculator: 1
                };
            },
            state: {
                exams: [
                    {
                        enable_calculator: 0,
                        id: 9
                    }
                ],
                notice: 'Kalkulator dinonaktifkan oleh guru untuk exam ini.'
            }
        });

        await fixture.manager.run();

        expect(fixture.state.exams[0].enable_calculator).toBe(1);
        expect(fixture.state.notice).toBe('');
        expect(fixture.calls.clearCalculatorRuntimeState).toBe(0);
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

    it('marks heartbeat lost after three online failures, renders warning once, and logs a security event', async function () {
        var fixture = createHeartbeatFixture({
            apiRequest: async function () {
                var error = new Error('Heartbeat timeout.');
                error.status = 0;
                error.code = 'network_error';
                error.isNetworkError = true;
                throw error;
            }
        });

        await fixture.manager.run();
        await fixture.manager.run();
        await fixture.manager.run();

        expect(fixture.state.heartbeatLostActive).toBe(true);
        expect(fixture.state.heartbeatLostFailureCount).toBe(3);
        expect(fixture.state.heartbeatLostLastErrorCode).toBe('network_error');
        expect(fixture.calls.render).toEqual([
            {
                meta: {
                    active: true,
                    attemptId: 55,
                    failureCount: 3
                },
                reason: 'heartbeat-lost-state'
            }
        ]);
        expect(fixture.calls.sendSecurityEventSilently).toEqual([
            {
                context: {
                    connection_status: 'online',
                    failure_count: 3,
                    has_focus: 1,
                    last_error_code: 'network_error',
                    source: 'session_heartbeat',
                    visibility_state: 'visible'
                },
                eventType: 'heartbeat_lost',
                options: {
                    attemptId: 55,
                    keepalive: true
                }
            }
        ]);
    });

    it('uses partial notice and sync patch when heartbeat lost warning becomes active', async function () {
        var fixture = createHeartbeatFixture({
            apiRequest: async function () {
                var error = new Error('Heartbeat timeout.');
                error.status = 0;
                error.code = 'network_error';
                error.isNetworkError = true;
                throw error;
            },
            renderExamPartial: function () {
                return true;
            }
        });

        await fixture.manager.run();
        await fixture.manager.run();
        await fixture.manager.run();

        expect(fixture.calls.renderExamPartial).toEqual([
            {
                meta: {
                    active: true,
                    attemptId: 55,
                    failureCount: 3
                },
                reason: 'heartbeat-lost-state',
                regions: {
                    notice: true,
                    questionFooterSync: true
                }
            }
        ]);
        expect(fixture.calls.render).toEqual([]);
    });

    it('clears heartbeat lost warning after the next successful heartbeat', async function () {
        var shouldFail = true;
        var fixture = createHeartbeatFixture({
            apiRequest: async function () {
                if (shouldFail) {
                    var error = new Error('Heartbeat timeout.');
                    error.status = 0;
                    error.code = 'network_error';
                    error.isNetworkError = true;
                    throw error;
                }

                return {
                    attempt_timer: {
                        attempt_id: 55,
                        remaining_seconds: 180
                    },
                    question_count: 3,
                    question_order_signature: 'order-1'
                };
            }
        });

        await fixture.manager.run();
        await fixture.manager.run();
        await fixture.manager.run();
        shouldFail = false;

        await fixture.manager.run();

        expect(fixture.state.heartbeatLostActive).toBe(false);
        expect(fixture.state.heartbeatLostFailureCount).toBe(0);
        expect(fixture.state.heartbeatLostLastErrorCode).toBe('');
        expect(fixture.calls.render).toEqual([
            {
                meta: {
                    active: true,
                    attemptId: 55,
                    failureCount: 3
                },
                reason: 'heartbeat-lost-state'
            },
            {
                meta: {
                    active: false,
                    attemptId: 55,
                    failureCount: 0
                },
                reason: 'heartbeat-lost-state'
            }
        ]);
    });

    it('does not activate heartbeat lost while the browser is offline', async function () {
        var fixture = createHeartbeatFixture({
            apiRequest: async function () {
                var error = new Error('Koneksi terputus.');
                error.status = 0;
                error.code = 'network_error';
                error.isNetworkError = true;
                throw error;
            },
            windowRef: {
                clearInterval: globalThis.clearInterval,
                navigator: {
                    onLine: false
                },
                setInterval: globalThis.setInterval,
                setTimeout: globalThis.setTimeout
            }
        });

        await fixture.manager.run();
        await fixture.manager.run();
        await fixture.manager.run();

        expect(fixture.state.heartbeatLostActive).toBe(false);
        expect(fixture.state.heartbeatLostFailureCount).toBe(0);
        expect(fixture.calls.sendSecurityEventSilently).toEqual([]);
        expect(fixture.calls.render).toEqual([]);
    });

    it('ignores non-network heartbeat failures for heartbeat lost tracking', async function () {
        var fixture = createHeartbeatFixture({
            apiRequest: async function () {
                var error = new Error('Server validation error.');
                error.status = 500;
                error.code = 'server_error';
                throw error;
            }
        });

        await fixture.manager.run();
        await fixture.manager.run();
        await fixture.manager.run();

        expect(fixture.state.heartbeatLostActive).toBe(false);
        expect(fixture.state.heartbeatLostFailureCount).toBe(0);
        expect(fixture.calls.sendSecurityEventSilently).toEqual([]);
    });

    it('does not track heartbeat lost when detection is disabled', async function () {
        var fixture = createHeartbeatFixture({
            heartbeatLostDetectionEnabled: false,
            apiRequest: async function () {
                var error = new Error('Heartbeat timeout.');
                error.status = 0;
                error.code = 'network_error';
                error.isNetworkError = true;
                throw error;
            }
        });

        await fixture.manager.run();
        await fixture.manager.run();
        await fixture.manager.run();

        expect(fixture.state.heartbeatLostActive).toBe(false);
        expect(fixture.state.heartbeatLostFailureCount).toBe(0);
        expect(fixture.calls.sendSecurityEventSilently).toEqual([]);
        expect(fixture.calls.render).toEqual([]);
    });

    it('consumes diagnostics fail-next once and recovers on the following heartbeat', async function () {
        var failNext = true;
        var diagnosticsManager = {
            enabled: true,
            getHeartbeatScenario: vi.fn().mockReturnValue('fail-next'),
            consumeHeartbeatFailureOnce: vi.fn(function () {
                if (failNext) {
                    failNext = false;
                    return true;
                }
                return false;
            })
        };
        var fixture = createHeartbeatFixture({
            diagnosticsManager: diagnosticsManager
        });

        var failed = await fixture.manager.run();
        var recovered = await fixture.manager.run();

        expect(failed).toBeNull();
        expect(recovered).toEqual(expect.objectContaining({
            enable_calculator: 1
        }));
        expect(diagnosticsManager.consumeHeartbeatFailureOnce).toHaveBeenCalledTimes(2);
        expect(fixture.calls.apiRequest).toHaveLength(1);
        expect(fixture.state.heartbeatLostActive).toBe(false);
        expect(fixture.state.heartbeatLostFailureCount).toBe(0);
    });
});
