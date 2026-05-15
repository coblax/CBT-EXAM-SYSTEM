import { describe, expect, it, vi } from 'vitest';
import { createSessionHeartbeatManager } from '../../../src/frontend/app/core/session-heartbeat.js';

function createState(overrides = {}) {
    return {
        stage: 'exam',
        attemptId: 42,
        selectedExamId: 10,
        token: 'test-token',
        heartbeatLostActive: false,
        heartbeatLostFailureCount: 0,
        heartbeatLostLastErrorCode: '',
        connectionStatus: 'online',
        adaptiveLoadLevel: 'normal',
        adaptiveLoadSource: 'auto',
        adaptiveLoadReasons: [],
        adaptiveLoadHeartbeatIntervalMs: 20000,
        adaptiveLoadAdminSnapshotRefreshSeconds: 10,
        adaptiveLoadLastEvaluatedAt: '',
        adaptiveLoadOverrideExpiresAt: '',
        exams: [{ id: 10, title: 'Test', enable_calculator: 1 }],
        questionRevision: null,
        questionOrderSignature: '',
        currentIndex: 0,
        notice: '',
        ...overrides
    };
}

function createDeps(state, overrides = {}) {
    return {
        apiRequest: overrides.apiRequest || vi.fn().mockResolvedValue({ ok: true }),
        applyAttemptTimerPayload: vi.fn(),
        clearCalculatorRuntimeState: vi.fn(),
        diagnosticsManager: null,
        documentRef: { hasFocus: function () { return true; }, visibilityState: 'visible' },
        getQuestionCount: vi.fn().mockReturnValue(10),
        isHeartbeatLostDetectionEnabled: overrides.isHeartbeatLostDetectionEnabled || function () { return true; },
        normalizeQuestionRevision: vi.fn().mockReturnValue(null),
        questionOrderSignatureEquals: vi.fn().mockReturnValue(true),
        questionRevisionEquals: vi.fn().mockReturnValue(true),
        refreshAttemptQuestionRevision: vi.fn().mockResolvedValue(undefined),
        sessionHeartbeatIntervalMs: 20000,
        sendSecurityEventSilently: overrides.sendSecurityEventSilently || vi.fn().mockReturnValue(true),
        setQuestionRevision: vi.fn(),
        state: state,
        windowRef: {
            setTimeout: function (fn, ms) { return 1; },
            clearTimeout: vi.fn(),
            setInterval: function (fn, ms) { return 1; },
            clearInterval: vi.fn(),
            navigator: { onLine: true }
        },
        recordTimeline: vi.fn(),
        recordActionTrail: vi.fn(),
        render: vi.fn(),
        renderExamPartial: null,
        ...overrides
    };
}

function buildNetworkError() {
    var error = new Error('Network error');
    error.status = 0;
    error.code = 'network_error';
    error.isNetworkError = true;
    return error;
}

describe('createSessionHeartbeatManager', function () {
    describe('heartbeat lost detection', function () {
        it('does not trigger heartbeat lost on first failure', async function () {
            var state = createState();
            var apiRequest = vi.fn().mockRejectedValue(buildNetworkError());
            var manager = createSessionHeartbeatManager(createDeps(state, { apiRequest }));

            await manager.run();

            expect(state.heartbeatLostActive).toBe(false);
            expect(state.heartbeatLostFailureCount).toBe(1);
        });

        it('triggers heartbeat lost after 3 consecutive failures', async function () {
            var state = createState();
            var sendSpy = vi.fn().mockReturnValue(true);
            var apiRequest = vi.fn().mockRejectedValue(buildNetworkError());
            var manager = createSessionHeartbeatManager(createDeps(state, { apiRequest, sendSecurityEventSilently: sendSpy }));

            await manager.run();
            await manager.run();
            await manager.run();

            expect(state.heartbeatLostActive).toBe(true);
            expect(state.heartbeatLostFailureCount).toBe(3);
            expect(sendSpy).toHaveBeenCalledWith('heartbeat_lost', expect.any(Object), expect.any(Object));
        });

        it('resets heartbeat lost on successful heartbeat', async function () {
            var state = createState({ heartbeatLostActive: true, heartbeatLostFailureCount: 3 });
            var apiRequest = vi.fn().mockResolvedValue({ ok: true });
            var manager = createSessionHeartbeatManager(createDeps(state, { apiRequest }));

            await manager.run();

            expect(state.heartbeatLostActive).toBe(false);
            expect(state.heartbeatLostFailureCount).toBe(0);
        });

        it('does not count failures when browser is offline', async function () {
            var state = createState();
            var apiRequest = vi.fn().mockRejectedValue(buildNetworkError());
            var windowRef = {
                setTimeout: function (fn, ms) { return 1; },
                clearTimeout: vi.fn(),
                setInterval: function (fn, ms) { return 1; },
                clearInterval: vi.fn(),
                navigator: { onLine: false }
            };
            var manager = createSessionHeartbeatManager(createDeps(state, { apiRequest, windowRef }));

            await manager.run();
            await manager.run();
            await manager.run();

            expect(state.heartbeatLostActive).toBe(false);
            expect(state.heartbeatLostFailureCount).toBe(0);
        });

        it('does not send security event when detection is disabled', async function () {
            var state = createState();
            var sendSpy = vi.fn();
            var apiRequest = vi.fn().mockRejectedValue(buildNetworkError());
            var manager = createSessionHeartbeatManager(createDeps(state, {
                apiRequest,
                sendSecurityEventSilently: sendSpy,
                isHeartbeatLostDetectionEnabled: function () { return false; }
            }));

            await manager.run();
            await manager.run();
            await manager.run();

            expect(sendSpy).not.toHaveBeenCalled();
        });

        it('resets failure count when attempt changes', async function () {
            var state = createState({ attemptId: 42 });
            var apiRequest = vi.fn().mockRejectedValue(buildNetworkError());
            var manager = createSessionHeartbeatManager(createDeps(state, { apiRequest }));

            await manager.run();
            await manager.run();
            expect(state.heartbeatLostFailureCount).toBe(2);

            // Change attempt
            state.attemptId = 99;
            await manager.run();

            expect(state.heartbeatLostFailureCount).toBe(1);
        });
    });

    describe('adaptive load', function () {
        it('applies adaptive load payload from session response', async function () {
            var state = createState();
            var apiRequest = vi.fn().mockResolvedValue({
                ok: true,
                adaptive_load: {
                    level: 'busy',
                    source: 'auto',
                    heartbeat_interval_ms: 30000,
                    admin_snapshot_refresh_seconds: 15,
                    reasons: ['high_load'],
                    last_evaluated_at: '2026-03-24 12:00:00',
                    override_expires_at: ''
                }
            });
            var manager = createSessionHeartbeatManager(createDeps(state, { apiRequest }));

            await manager.run();

            expect(state.adaptiveLoadLevel).toBe('busy');
            expect(state.adaptiveLoadHeartbeatIntervalMs).toBe(30000);
            expect(state.adaptiveLoadReasons).toEqual(['high_load']);
        });

        it('ignores adaptive load when payload is missing', async function () {
            var state = createState({ adaptiveLoadLevel: 'normal' });
            var apiRequest = vi.fn().mockResolvedValue({ ok: true });
            var manager = createSessionHeartbeatManager(createDeps(state, { apiRequest }));

            await manager.run();

            expect(state.adaptiveLoadLevel).toBe('normal');
        });
    });

    describe('calculator availability sync', function () {
        it('disables calculator when session reports it disabled', async function () {
            var state = createState();
            var clearCalc = vi.fn();
            var apiRequest = vi.fn().mockResolvedValue({ ok: true, enable_calculator: 0 });
            var manager = createSessionHeartbeatManager(createDeps(state, {
                apiRequest,
                clearCalculatorRuntimeState: clearCalc
            }));

            await manager.run();

            expect(state.exams[0].enable_calculator).toBe(0);
            expect(clearCalc).toHaveBeenCalled();
            expect(state.notice).toContain('Kalkulator dinonaktifkan');
        });
    });

    describe('run behavior', function () {
        it('does not run when no token', async function () {
            var state = createState({ token: '' });
            var apiRequest = vi.fn();
            var manager = createSessionHeartbeatManager(createDeps(state, { apiRequest }));

            await manager.run();

            expect(apiRequest).not.toHaveBeenCalled();
        });

        it('does not run when stage is login', async function () {
            var state = createState({ stage: 'login' });
            var apiRequest = vi.fn();
            var manager = createSessionHeartbeatManager(createDeps(state, { apiRequest }));

            await manager.run();

            expect(apiRequest).not.toHaveBeenCalled();
        });

        it('does not duplicate in-flight requests', async function () {
            var state = createState();
            var callCount = 0;
            var apiRequest = vi.fn().mockImplementation(function () {
                callCount++;
                return new Promise(function (resolve) {
                    setTimeout(function () { resolve({ ok: true }); }, 10);
                });
            });
            var manager = createSessionHeartbeatManager(createDeps(state, { apiRequest }));

            var p1 = manager.run();
            var p2 = manager.run();

            await Promise.all([p1, p2]);

            expect(callCount).toBe(1);
        });
    });

    describe('stop', function () {
        it('clears heartbeat state on stop', function () {
            var state = createState({ heartbeatLostActive: true, heartbeatLostFailureCount: 5 });
            var manager = createSessionHeartbeatManager(createDeps(state));

            manager.stop();

            expect(state.heartbeatLostActive).toBe(false);
            expect(state.heartbeatLostFailureCount).toBe(0);
        });
    });
});
