import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createExamSecurityManager } from '../../../src/frontend/app/exam/security.js';

function createEmitter() {
    var listeners = {};

    return {
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

function createManager(overrides = {}) {
    var documentRef = createEmitter();
    documentRef.documentElement = {};
    documentRef.body = {};
    documentRef.fullscreenElement = null;
    documentRef.exitFullscreen = function () {
        return Promise.resolve();
    };

    var windowRef = createEmitter();
    var calls = [];
    var state = Object.assign({
        stage: 'exam',
        attemptId: 77,
        isFullscreenActive: true,
        examLockedForPendingFinish: false,
        isFinishing: false,
        error: ''
    }, overrides.state || {});

    var manager = createExamSecurityManager({
        state,
        root: {},
        documentRef,
        windowRef,
        escapeHtml: function (value) {
            return String(value || '');
        },
        clearMessages: function () {},
        isExamCopyPasteBlocked: function () {
            return overrides.copyPasteBlocked !== false;
        },
        isExamFullscreenRequired: function () {
            return overrides.fullscreenRequired !== false;
        },
        isSecurityLoggingActiveForAttempt: function () {
            return overrides.loggingActive !== false;
        },
        sendSecurityEventSilently: function (eventType, context, options) {
            calls.push({
                eventType,
                context,
                options
            });
        },
        syncFullscreenState: function () {
            state.isFullscreenActive = false;
        },
        requestNativeFullscreen: function () {
            return Promise.resolve(false);
        },
        setNativeFullscreenActive: function (active) {
            state.isFullscreenActive = !!active;
        },
        exitNativeFullscreen: function () {
            return Promise.resolve();
        }
    });

    return {
        manager: manager,
        state: state,
        documentRef: documentRef,
        windowRef: windowRef,
        calls: calls
    };
}

describe('createExamSecurityManager', function () {
    beforeEach(function () {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-03-24T12:00:00Z'));
    });

    it('logs fullscreen exit once and suppresses repeated logs after silent exit', async function () {
        var setup = createManager();

        setup.manager.mountSecurityListeners();
        setup.documentRef.dispatchEvent('fullscreenchange', {});

        expect(setup.calls).toHaveLength(1);
        expect(setup.calls[0]).toMatchObject({
            eventType: 'fullscreen_exit',
            context: {
                source: 'fullscreenchange'
            }
        });

        setup.state.isFullscreenActive = true;
        await setup.manager.exitFullscreenSilently();
        setup.documentRef.dispatchEvent('fullscreenchange', {});

        expect(setup.calls).toHaveLength(1);

        vi.advanceTimersByTime(2100);
        setup.state.isFullscreenActive = true;
        setup.documentRef.dispatchEvent('fullscreenchange', {});

        expect(setup.calls).toHaveLength(2);
    });

    it('blocks clipboard actions and passes debounce metadata to the security logger', function () {
        var setup = createManager();
        var event = {
            type: 'copy',
            defaultPrevented: false,
            propagationStopped: false,
            preventDefault: function () {
                this.defaultPrevented = true;
            },
            stopPropagation: function () {
                this.propagationStopped = true;
            }
        };

        var handled = setup.manager.handleBlockedClipboardAction('copy', event);

        expect(handled).toBe(true);
        expect(event.defaultPrevented).toBe(true);
        expect(event.propagationStopped).toBe(true);
        expect(setup.calls).toHaveLength(1);
        expect(setup.calls[0]).toMatchObject({
            eventType: 'clipboard_blocked',
            context: {
                action: 'copy',
                source: 'copy'
            },
            options: {
                attemptId: 77,
                keepalive: true,
                debounceMs: 1500
            }
        });
    });

    it('does not throw when the security logger callback is missing', function () {
        var setup = createManager();
        var event = {
            type: 'copy',
            preventDefault: function () {},
            stopPropagation: function () {}
        };
        var manager = createExamSecurityManager({
            state: setup.state,
            root: {},
            documentRef: setup.documentRef,
            windowRef: setup.windowRef,
            escapeHtml: function (value) {
                return String(value || '');
            },
            clearMessages: function () {},
            isExamCopyPasteBlocked: function () {
                return true;
            },
            isExamFullscreenRequired: function () {
                return true;
            },
            isSecurityLoggingActiveForAttempt: function () {
                return true;
            },
            syncFullscreenState: function () {},
            requestNativeFullscreen: function () {
                return Promise.resolve(false);
            },
            setNativeFullscreenActive: function () {},
            exitNativeFullscreen: function () {
                return Promise.resolve();
            }
        });

        expect(function () {
            manager.handleBlockedClipboardAction('copy', event);
        }).not.toThrow();
    });
});
