import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createIdleDetectionManager } from '../../../src/frontend/app/core/idle-detection.js';

function createEmitter() {
    var listeners = {};

    return {
        visibilityState: 'visible',
        focusActive: true,
        addEventListener: function (eventName, callback) {
            if (!listeners[eventName]) {
                listeners[eventName] = [];
            }
            listeners[eventName].push(callback);
        },
        dispatchEvent: function (eventName, event) {
            (listeners[eventName] || []).forEach(function (callback) {
                callback(event || {});
            });
        },
        hasFocus: function () {
            return this.focusActive;
        }
    };
}

function createManager(overrides = {}) {
    var documentRef = createEmitter();
    var windowRef = createEmitter();
    windowRef.setTimeout = globalThis.setTimeout.bind(globalThis);
    windowRef.clearTimeout = globalThis.clearTimeout.bind(globalThis);
    var calls = [];
    var state = Object.assign({
        attemptId: 77,
        currentIndex: 1,
        examLockedForPendingFinish: false,
        finishConfirmOpen: false,
        isFinishing: false,
        pendingSyncCount: 2,
        stage: 'exam'
    }, overrides.state || {});

    var manager = createIdleDetectionManager({
        documentRef: documentRef,
        getIdleThresholdSeconds: function () {
            return overrides.idleThresholdSeconds !== undefined ? overrides.idleThresholdSeconds : 300;
        },
        getQuestionDisplayNumber: function (index) {
            if (typeof overrides.getQuestionDisplayNumber === 'function') {
                return overrides.getQuestionDisplayNumber(index);
            }
            return Number(index) + 1;
        },
        isExamFullscreenBlockingActive: function () {
            return overrides.fullscreenBlocking === true;
        },
        isIdleDetectionEnabled: function () {
            return overrides.idleEnabled !== false;
        },
        isSecurityLoggingEnabled: function () {
            return overrides.loggingEnabled !== false;
        },
        sendSecurityEventSilently: function (eventType, context, options) {
            calls.push({
                context,
                eventType,
                options
            });
            return true;
        },
        state: state,
        windowRef: windowRef
    });

    manager.mountIdleListeners();

    return {
        calls,
        documentRef,
        manager,
        state,
        windowRef
    };
}

beforeEach(function () {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-03-24T12:00:00Z'));
});

afterEach(function () {
    vi.useRealTimers();
});

describe('createIdleDetectionManager', function () {
    it('tracks idle only while the exam stage is active with a valid attempt', function () {
        var fixture = createManager({
            state: {
                stage: 'login'
            }
        });

        fixture.manager.syncState();
        vi.advanceTimersByTime(300000);
        expect(fixture.calls).toHaveLength(0);

        fixture.state.stage = 'exam';
        fixture.manager.syncState();
        vi.advanceTimersByTime(300000);
        expect(fixture.calls).toHaveLength(1);
        expect(fixture.calls[0].eventType).toBe('idle_detected');
    });

    it('resets the countdown after activity before the idle threshold is reached', function () {
        var fixture = createManager();

        fixture.manager.syncState();
        vi.advanceTimersByTime(240000);
        fixture.documentRef.dispatchEvent('keydown', {
            key: 'A'
        });
        vi.advanceTimersByTime(240000);
        expect(fixture.calls).toHaveLength(0);

        vi.advanceTimersByTime(60000);
        expect(fixture.calls).toHaveLength(1);
    });

    it('pauses idle detection while the tab is hidden and restarts when visible again', function () {
        var fixture = createManager();

        fixture.manager.syncState();
        vi.advanceTimersByTime(240000);
        fixture.documentRef.visibilityState = 'hidden';
        fixture.documentRef.dispatchEvent('visibilitychange', {});
        vi.advanceTimersByTime(300000);
        expect(fixture.calls).toHaveLength(0);

        fixture.documentRef.visibilityState = 'visible';
        fixture.documentRef.dispatchEvent('visibilitychange', {});
        vi.advanceTimersByTime(299000);
        expect(fixture.calls).toHaveLength(0);
        vi.advanceTimersByTime(1000);
        expect(fixture.calls).toHaveLength(1);
    });

    it('pauses idle detection while the window loses focus and restarts on focus', function () {
        var fixture = createManager();

        fixture.manager.syncState();
        vi.advanceTimersByTime(240000);
        fixture.documentRef.focusActive = false;
        fixture.windowRef.dispatchEvent('blur', {});
        vi.advanceTimersByTime(300000);
        expect(fixture.calls).toHaveLength(0);

        fixture.documentRef.focusActive = true;
        fixture.windowRef.dispatchEvent('focus', {});
        vi.advanceTimersByTime(300000);
        expect(fixture.calls).toHaveLength(1);
    });

    it('keeps emitting idle events per threshold interval until new activity happens', function () {
        var fixture = createManager();

        fixture.manager.syncState();
        vi.advanceTimersByTime(300000);
        vi.advanceTimersByTime(300000);
        expect(fixture.calls).toHaveLength(2);
        expect(fixture.calls[0].context.idle_seconds).toBe(300);
        expect(fixture.calls[1].context.idle_seconds).toBe(600);

        fixture.documentRef.dispatchEvent('pointerdown', {});
        vi.advanceTimersByTime(300000);
        expect(fixture.calls).toHaveLength(3);
        expect(fixture.calls[2].context.idle_seconds).toBe(300);
    });

    it('builds the idle payload context with threshold, question number, and focus state', function () {
        var fixture = createManager({
            getQuestionDisplayNumber: function () {
                return 12;
            },
            state: {
                currentIndex: 2,
                pendingSyncCount: 4
            }
        });

        fixture.manager.syncState();
        vi.advanceTimersByTime(300000);

        expect(fixture.calls).toHaveLength(1);
        expect(fixture.calls[0]).toMatchObject({
            context: {
                has_focus: 1,
                idle_seconds: 300,
                idle_threshold_seconds: 300,
                pending_sync_count: 4,
                question_index: 2,
                question_number: 12,
                source: 'idle_timer',
                visibility_state: 'visible'
            },
            options: {
                attemptId: 77,
                debounceMs: 300000,
                keepalive: true
            }
        });
    });

    it('clears the pending idle timer when runtime state is reset', function () {
        var fixture = createManager();

        fixture.manager.syncState();
        vi.advanceTimersByTime(120000);
        fixture.manager.clearRuntimeState();
        vi.advanceTimersByTime(300000);

        expect(fixture.calls).toHaveLength(0);
    });
});
