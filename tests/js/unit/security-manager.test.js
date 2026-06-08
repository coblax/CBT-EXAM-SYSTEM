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
    windowRef.navigator = overrides.navigator || {
        platform: 'Win32'
    };
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
        isBrowserInspectionShortcutBlockingEnabled: function () {
            return overrides.browserInspectionShortcutBlocking !== false;
        },
        isExamCopyPasteBlocked: function () {
            return overrides.copyPasteBlocked !== false;
        },
        isExamFullscreenRequired: function () {
            return overrides.fullscreenRequired !== false;
        },
        isScreenshotKeyDetectionEnabled: function () {
            return overrides.screenshotKeyDetection === true;
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

    it('blocks context menu interactions and logs the browser signal', function () {
        var setup = createManager();
        var event = {
            type: 'contextmenu',
            defaultPrevented: false,
            propagationStopped: false,
            preventDefault: function () {
                this.defaultPrevented = true;
            },
            stopPropagation: function () {
                this.propagationStopped = true;
            }
        };

        setup.manager.mountSecurityListeners();
        setup.documentRef.dispatchEvent('contextmenu', event);

        expect(event.defaultPrevented).toBe(true);
        expect(event.propagationStopped).toBe(true);
        expect(setup.calls).toHaveLength(1);
        expect(setup.calls[0]).toMatchObject({
            eventType: 'context_menu_blocked',
            context: {
                source: 'contextmenu',
                blocked: 1
            },
            options: {
                attemptId: 77,
                keepalive: true,
                debounceMs: 1000
            }
        });
    });

    it('suppresses right-click from pointerdown before the browser context menu is opened', function () {
        var setup = createManager();
        var event = {
            type: 'pointerdown',
            button: 2,
            defaultPrevented: false,
            propagationStopped: false,
            preventDefault: function () {
                this.defaultPrevented = true;
            },
            stopPropagation: function () {
                this.propagationStopped = true;
            }
        };

        setup.manager.mountSecurityListeners();
        setup.documentRef.dispatchEvent('pointerdown', event);

        expect(event.defaultPrevented).toBe(true);
        expect(event.propagationStopped).toBe(true);
        expect(setup.calls).toHaveLength(0);
    });

    it('logs beforeprint as a best-effort print attempt without blocking the browser lifecycle event', function () {
        var setup = createManager();
        var event = {
            defaultPrevented: false,
            preventDefault: function () {
                this.defaultPrevented = true;
            }
        };

        setup.manager.mountSecurityListeners();
        setup.windowRef.dispatchEvent('beforeprint', event);

        expect(event.defaultPrevented).toBe(false);
        expect(setup.calls).toHaveLength(1);
        expect(setup.calls[0]).toMatchObject({
            eventType: 'print_attempt',
            context: {
                source: 'beforeprint',
                blocked: 0
            },
            options: {
                attemptId: 77,
                keepalive: true,
                debounceMs: 1500
            }
        });
    });

    it('logs PrintScreen keydown as a screenshot signal without blocking input', function () {
        var setup = createManager({
            screenshotKeyDetection: true
        });
        var event = {
            key: 'PrintScreen',
            code: 'PrintScreen',
            altKey: false,
            ctrlKey: false,
            metaKey: false,
            shiftKey: false,
            defaultPrevented: false,
            propagationStopped: false,
            preventDefault: function () {
                this.defaultPrevented = true;
            },
            stopPropagation: function () {
                this.propagationStopped = true;
            }
        };

        setup.manager.mountSecurityListeners();
        setup.documentRef.dispatchEvent('keydown', event);

        expect(event.defaultPrevented).toBe(false);
        expect(event.propagationStopped).toBe(false);
        expect(setup.calls).toHaveLength(1);
        expect(setup.calls[0]).toMatchObject({
            eventType: 'screenshot_key_detected',
            context: {
                source: 'printscreen_key',
                key: 'PrintScreen',
                code: 'PrintScreen',
                platform_hint: 'Win32',
                blocked: 0
            },
            options: {
                attemptId: 77,
                keepalive: true,
                debounceMs: 1200
            }
        });
    });

    it('logs macOS screenshot shortcuts when the browser exposes the keydown event', function () {
        var setup = createManager({
            screenshotKeyDetection: true,
            navigator: {
                platform: 'MacIntel'
            }
        });

        setup.manager.mountSecurityListeners();
        setup.documentRef.dispatchEvent('keydown', {
            key: '4',
            code: 'Digit4',
            metaKey: true,
            shiftKey: true,
            ctrlKey: false,
            altKey: false
        });

        expect(setup.calls).toHaveLength(1);
        expect(setup.calls[0]).toMatchObject({
            eventType: 'screenshot_key_detected',
            context: {
                source: 'macos_screenshot_shortcut',
                key: '4',
                code: 'Digit4',
                meta_key: 1,
                shift_key: 1,
                platform_hint: 'MacIntel',
                blocked: 0
            }
        });
    });

    it('logs ChromeOS screenshot shortcuts without blocking input', function () {
        var setup = createManager({
            screenshotKeyDetection: true,
            navigator: {
                platform: 'ChromeOS'
            }
        });
        var event = {
            key: 'F5',
            code: 'F5',
            altKey: false,
            ctrlKey: true,
            metaKey: false,
            shiftKey: true,
            defaultPrevented: false,
            propagationStopped: false,
            preventDefault: function () {
                this.defaultPrevented = true;
            },
            stopPropagation: function () {
                this.propagationStopped = true;
            }
        };

        setup.manager.mountSecurityListeners();
        setup.documentRef.dispatchEvent('keydown', event);

        expect(event.defaultPrevented).toBe(false);
        expect(event.propagationStopped).toBe(false);
        expect(setup.calls).toHaveLength(1);
        expect(setup.calls[0]).toMatchObject({
            eventType: 'screenshot_key_detected',
            context: {
                source: 'chromeos_partial_screenshot_shortcut',
                key: 'F5',
                code: 'F5',
                ctrl_key: 1,
                shift_key: 1,
                platform_hint: 'ChromeOS',
                blocked: 0
            }
        });
    });

    it('does not treat Ctrl+F5 as screenshot on non-ChromeOS platforms', function () {
        var setup = createManager({
            screenshotKeyDetection: true,
            navigator: {
                platform: 'Win32'
            }
        });

        setup.manager.mountSecurityListeners();
        setup.documentRef.dispatchEvent('keydown', {
            key: 'F5',
            code: 'F5',
            altKey: false,
            ctrlKey: true,
            metaKey: false,
            shiftKey: false
        });

        expect(setup.calls).toHaveLength(0);
    });

    it('does not log screenshot keys outside exam, without logging, without attempt, or when disabled', function () {
        [
            createManager({ screenshotKeyDetection: false }),
            createManager({ screenshotKeyDetection: true, loggingActive: false }),
            createManager({ screenshotKeyDetection: true, state: { stage: 'confirm' } }),
            createManager({ screenshotKeyDetection: true, state: { attemptId: 0 } })
        ].forEach(function (setup) {
            setup.manager.mountSecurityListeners();
            setup.documentRef.dispatchEvent('keydown', {
                key: 'PrintScreen',
                code: 'PrintScreen'
            });

            expect(setup.calls).toHaveLength(0);
        });
    });

    it('blocks browser inspection shortcuts even when security logging is off', function () {
        var setup = createManager({
            loggingActive: false
        });
        var event = {
            defaultPrevented: false,
            propagationStopped: false,
            preventDefault: function () {
                this.defaultPrevented = true;
            },
            stopPropagation: function () {
                this.propagationStopped = true;
            }
        };

        var handled = setup.manager.handleBlockedBrowserInspectionShortcutAction(
            'view_source_blocked',
            'view_source_shortcut',
            event
        );

        expect(handled).toBe(true);
        expect(event.defaultPrevented).toBe(true);
        expect(event.propagationStopped).toBe(true);
        expect(setup.calls).toHaveLength(0);
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
            isBrowserInspectionShortcutBlockingEnabled: function () {
                return true;
            },
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
