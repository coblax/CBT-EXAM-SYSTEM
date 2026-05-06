import { describe, expect, it } from 'vitest';
import { createLifecycleManager } from '../../../src/frontend/app/core/lifecycle.js';

function createEmitter() {
    var listeners = {};

    return {
        fonts: null,
        innerWidth: 1280,
        visibilityState: 'visible',
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
        }
    };
}

function createFixture(overrides = {}) {
    var documentRef = createEmitter();
    var windowRef = createEmitter();
    var calls = {
        flushAttemptUiState: [],
        flushPendingAnswerBatch: [],
        persistCurrentQuestionCacheLocally: 0,
        recordActionTrail: [],
        runSessionHeartbeat: 0,
        setConnectionStatus: [],
        triggerPendingSyncLifecycleRetry: []
    };
    var state = Object.assign({
        attemptId: 55,
        connectionStatus: 'online',
        isFinishing: false,
        pendingSyncCount: 0,
        stage: 'exam'
    }, overrides.state || {});

    windowRef.setTimeout = globalThis.setTimeout.bind(globalThis);
    windowRef.clearTimeout = globalThis.clearTimeout.bind(globalThis);

    var manager = createLifecycleManager({
        documentRef: documentRef,
        windowRef: windowRef,
        state: state,
        fitLoginHeroSchoolName: function () {},
        flushAttemptUiStateSilently: function (options) {
            calls.flushAttemptUiState.push(options === undefined ? null : options);
        },
        flushPendingAnswerBatchSilently: function (options) {
            calls.flushPendingAnswerBatch.push(options === undefined ? null : options);
        },
        getCompactViewportState: function () {
            return false;
        },
        isCompactViewport: function () {
            return false;
        },
        logPageLeaveSecurityEvent: function () {},
        persistCurrentQuestionCacheLocally: function () {
            calls.persistCurrentQuestionCacheLocally += 1;
        },
        recordActionTrail: function (kind, summary, meta) {
            calls.recordActionTrail.push({
                kind: kind || '',
                meta: meta || null,
                summary: summary || ''
            });
        },
        render: function () {},
        runSessionHeartbeat: function () {
            calls.runSessionHeartbeat += 1;
        },
        scheduleNavigationGridLayout: function () {},
        setCompactViewportState: function () {},
        setConnectionStatus: function (status, options) {
            calls.setConnectionStatus.push({
                options: options || null,
                status: status || ''
            });
        },
        triggerPendingSyncLifecycleRetry: function (reason, options) {
            calls.triggerPendingSyncLifecycleRetry.push({
                options: options || null,
                reason: reason || ''
            });
        },
        cancelScheduledTabHiddenSecurityLog: function () {},
        cancelScheduledWindowBlurSecurityLog: function () {},
        scheduleTabHiddenSecurityLog: function () {},
        scheduleWindowBlurSecurityLog: function () {},
        isWindowBlurLoggingActiveForAttempt: function () {
            return overrides.windowBlurLogging !== false;
        }
    });

    manager.mountLifecycleListeners();

    return {
        calls: calls,
        documentRef: documentRef,
        state: state,
        windowRef: windowRef
    };
}

describe('createLifecycleManager', function () {
    it('uses non-force ui_state flushes for visible, blur, focus, and online transitions', function () {
        var fixture = createFixture();

        fixture.windowRef.dispatchEvent('blur', {});
        expect(fixture.calls.flushAttemptUiState.pop()).toEqual({
            keepalive: true
        });

        fixture.documentRef.visibilityState = 'visible';
        fixture.documentRef.dispatchEvent('visibilitychange', {});
        expect(fixture.calls.flushAttemptUiState.pop()).toBeNull();

        fixture.windowRef.dispatchEvent('focus', {});
        expect(fixture.calls.flushAttemptUiState.pop()).toBeNull();

        fixture.windowRef.dispatchEvent('online', {});
        expect(fixture.calls.flushAttemptUiState.pop()).toBeNull();
    });

    it('keeps force + keepalive flushes for hidden, pagehide, and beforeunload', function () {
        var fixture = createFixture();

        fixture.documentRef.visibilityState = 'hidden';
        fixture.documentRef.dispatchEvent('visibilitychange', {});
        expect(fixture.calls.flushAttemptUiState.pop()).toEqual({
            force: true,
            keepalive: true
        });

        fixture.windowRef.dispatchEvent('pagehide', {});
        expect(fixture.calls.flushAttemptUiState.pop()).toEqual({
            force: true,
            keepalive: true
        });

        fixture.windowRef.dispatchEvent('beforeunload', {});
        expect(fixture.calls.flushAttemptUiState.pop()).toEqual({
            force: true,
            keepalive: true
        });
    });

    it('deduplicates visible, focus, and online reconnect retries within one browser burst', function () {
        var fixture = createFixture();

        fixture.documentRef.visibilityState = 'visible';
        fixture.documentRef.dispatchEvent('visibilitychange', {});
        fixture.windowRef.dispatchEvent('focus', {});
        fixture.windowRef.dispatchEvent('online', {});

        expect(fixture.calls.triggerPendingSyncLifecycleRetry).toEqual([
            {
                options: {
                    delayMs: 180
                },
                reason: 'visible'
            }
        ]);
        expect(fixture.calls.runSessionHeartbeat).toBe(1);
        expect(fixture.calls.setConnectionStatus).toEqual([
            {
                options: {
                    immediate: true,
                    render: true,
                    resetBackoff: true,
                    triggerRetry: false
                },
                status: 'online'
            }
        ]);
        expect(fixture.calls.recordActionTrail.map(function (entry) {
            return entry.kind;
        })).toEqual([
            'reconnect:visible',
            'reconnect:online'
        ]);
        expect(fixture.calls.recordActionTrail[1].meta).toMatchObject({
            skippedRetry: true
        });
    });
});
