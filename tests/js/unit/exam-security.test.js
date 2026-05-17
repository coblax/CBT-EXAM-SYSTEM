import { describe, expect, it, vi } from 'vitest';
import { createExamSecurityManager } from '../../../src/frontend/app/exam/security.js';

function createState(overrides = {}) {
    return {
        stage: 'exam',
        attemptId: 42,
        selectedExamId: 10,
        isFullscreenActive: false,
        examLockedForPendingFinish: false,
        isFinishing: false,
        calculatorVisible: false,
        examWatermarkTick: 0,
        error: '',
        ...overrides
    };
}

function createDeps(state, overrides = {}) {
    var documentRef = {
        addEventListener: vi.fn(),
        hasFocus: function () { return true; },
        fullscreenElement: null,
        webkitFullscreenElement: null,
        documentElement: { requestFullscreen: vi.fn().mockResolvedValue(undefined) },
        body: {},
        visibilityState: 'visible'
    };
    var windowRef = {
        addEventListener: vi.fn(),
        setInterval: vi.fn().mockReturnValue(1),
        navigator: { onLine: true, userAgentData: { platform: 'Windows' } }
    };

    return {
        state: state,
        root: {},
        documentRef: documentRef,
        windowRef: windowRef,
        escapeHtml: function (s) { return String(s); },
        clearMessages: vi.fn(),
        isBrowserInspectionShortcutBlockingEnabled: overrides.isBrowserInspectionShortcutBlockingEnabled || function () { return true; },
        isScreenshotKeyDetectionEnabled: overrides.isScreenshotKeyDetectionEnabled || function () { return true; },
        isExamWatermarkEnabled: overrides.isExamWatermarkEnabled || function () { return false; },
        isExamCopyPasteBlocked: overrides.isExamCopyPasteBlocked || function () { return true; },
        isExamFullscreenRequired: overrides.isExamFullscreenRequired || function () { return true; },
        isSecurityLoggingActiveForAttempt: overrides.isSecurityLoggingActiveForAttempt || function () { return true; },
        sendSecurityEventSilently: overrides.sendSecurityEventSilently || vi.fn().mockReturnValue(true),
        syncFullscreenState: overrides.syncFullscreenState || vi.fn(),
        requestNativeFullscreen: vi.fn().mockResolvedValue(false),
        setNativeFullscreenActive: vi.fn(),
        exitNativeFullscreen: vi.fn().mockResolvedValue(false),
        requestRender: vi.fn(),
        ...overrides
    };
}

describe('createExamSecurityManager', function () {
    describe('isExamFullscreenBlockingActive', function () {
        it('returns true when fullscreen required and not active', function () {
            var state = createState({ isFullscreenActive: false });
            var manager = createExamSecurityManager(createDeps(state));

            expect(manager.isExamFullscreenBlockingActive()).toBe(true);
        });

        it('returns false when fullscreen is active', function () {
            var state = createState({ isFullscreenActive: true });
            var manager = createExamSecurityManager(createDeps(state));

            expect(manager.isExamFullscreenBlockingActive()).toBe(false);
        });

        it('returns false when not in exam stage', function () {
            var state = createState({ stage: 'login' });
            var manager = createExamSecurityManager(createDeps(state));

            expect(manager.isExamFullscreenBlockingActive()).toBe(false);
        });
    });

    describe('isExamClipboardBlockingActive', function () {
        it('returns true during exam with copy/paste blocked', function () {
            var state = createState();
            var manager = createExamSecurityManager(createDeps(state));

            expect(manager.isExamClipboardBlockingActive()).toBe(true);
        });

        it('returns false when not in exam stage', function () {
            var state = createState({ stage: 'confirm' });
            var manager = createExamSecurityManager(createDeps(state));

            expect(manager.isExamClipboardBlockingActive()).toBe(false);
        });
    });

    describe('handleBlockedClipboardAction', function () {
        it('sends security event and prevents default', function () {
            var state = createState();
            var sendSpy = vi.fn().mockReturnValue(true);
            var manager = createExamSecurityManager(createDeps(state, {
                sendSecurityEventSilently: sendSpy
            }));

            var event = { preventDefault: vi.fn(), stopPropagation: vi.fn(), type: 'copy' };
            var result = manager.handleBlockedClipboardAction('copy', event);

            expect(result).toBe(true);
            expect(event.preventDefault).toHaveBeenCalled();
            expect(sendSpy).toHaveBeenCalledWith('clipboard_blocked', expect.any(Object), expect.any(Object));
        });

        it('does nothing when clipboard blocking is disabled', function () {
            var state = createState();
            var manager = createExamSecurityManager(createDeps(state, {
                isExamCopyPasteBlocked: function () { return false; }
            }));

            var event = { preventDefault: vi.fn(), stopPropagation: vi.fn(), type: 'copy' };
            var result = manager.handleBlockedClipboardAction('copy', event);

            expect(result).toBe(false);
            expect(event.preventDefault).not.toHaveBeenCalled();
        });
    });

    describe('mountSecurityListeners', function () {
        it('blocks context menu gestures and logs context_menu_blocked', function () {
            var state = createState();
            var sendSpy = vi.fn().mockReturnValue(true);
            var deps = createDeps(state, {
                sendSecurityEventSilently: sendSpy
            });
            var manager = createExamSecurityManager(deps);

            manager.mountSecurityListeners();
            var contextMenuCall = deps.documentRef.addEventListener.mock.calls.find(function (call) {
                return call[0] === 'contextmenu';
            });
            expect(contextMenuCall).toBeTruthy();

            var event = { preventDefault: vi.fn(), stopPropagation: vi.fn(), type: 'contextmenu' };
            contextMenuCall[1](event);

            expect(event.preventDefault).toHaveBeenCalled();
            expect(event.stopPropagation).toHaveBeenCalled();
            expect(sendSpy).toHaveBeenCalledWith('context_menu_blocked', expect.objectContaining({ blocked: 1 }), expect.any(Object));
        });

        it('suppresses right-click pointerdown before context menu opens without logging twice', function () {
            var state = createState();
            var sendSpy = vi.fn().mockReturnValue(true);
            var deps = createDeps(state, {
                sendSecurityEventSilently: sendSpy
            });
            var manager = createExamSecurityManager(deps);

            manager.mountSecurityListeners();
            var pointerDownCall = deps.documentRef.addEventListener.mock.calls.find(function (call) {
                return call[0] === 'pointerdown';
            });
            expect(pointerDownCall).toBeTruthy();

            var event = { button: 2, preventDefault: vi.fn(), stopPropagation: vi.fn(), type: 'pointerdown' };
            pointerDownCall[1](event);

            expect(event.preventDefault).toHaveBeenCalled();
            expect(event.stopPropagation).toHaveBeenCalled();
            expect(sendSpy).not.toHaveBeenCalledWith('context_menu_blocked', expect.any(Object), expect.any(Object));
        });

        it('blocks beforeinput paste events through the clipboard guard', function () {
            var state = createState();
            var sendSpy = vi.fn().mockReturnValue(true);
            var deps = createDeps(state, {
                sendSecurityEventSilently: sendSpy
            });
            var manager = createExamSecurityManager(deps);

            manager.mountSecurityListeners();
            var beforeInputCall = deps.documentRef.addEventListener.mock.calls.find(function (call) {
                return call[0] === 'beforeinput';
            });
            expect(beforeInputCall).toBeTruthy();

            var event = { inputType: 'insertFromPaste', preventDefault: vi.fn(), stopPropagation: vi.fn(), type: 'beforeinput' };
            beforeInputCall[1](event);

            expect(event.preventDefault).toHaveBeenCalled();
            expect(sendSpy).toHaveBeenCalledWith('clipboard_blocked', expect.objectContaining({ action: 'paste' }), expect.any(Object));
        });

        it('logs native fullscreen exit when native bridge reports inactive', function () {
            var state = createState({ isFullscreenActive: true });
            var sendSpy = vi.fn().mockReturnValue(true);
            var setNativeFullscreenActive = vi.fn(function (active) {
                state.isFullscreenActive = active;
            });
            var deps = createDeps(state, {
                sendSecurityEventSilently: sendSpy,
                setNativeFullscreenActive: setNativeFullscreenActive
            });
            var manager = createExamSecurityManager(deps);

            manager.mountSecurityListeners();
            var nativeCall = deps.windowRef.addEventListener.mock.calls.find(function (call) {
                return call[0] === 'cbt-native-fullscreen-change';
            });
            expect(nativeCall).toBeTruthy();

            nativeCall[1]({ detail: { active: false } });

            expect(setNativeFullscreenActive).toHaveBeenCalledWith(false, true);
            expect(sendSpy).toHaveBeenCalledWith('fullscreen_exit', expect.objectContaining({ source: 'native-fullscreen-change' }), expect.any(Object));
        });

        it('refreshes watermark tick on the security interval when enabled', function () {
            var state = createState();
            var requestRender = vi.fn();
            var setIntervalSpy = vi.fn(function (fn, ms) {
                expect(ms).toBe(60000);
                fn();
                return 7;
            });
            var deps = createDeps(state, {
                isExamWatermarkEnabled: function () { return true; },
                requestRender: requestRender,
                windowRef: {
                    addEventListener: vi.fn(),
                    setInterval: setIntervalSpy,
                    navigator: { onLine: true, userAgentData: { platform: 'Windows' } }
                }
            });
            var manager = createExamSecurityManager(deps);

            manager.mountSecurityListeners();

            expect(setIntervalSpy).toHaveBeenCalled();
            expect(state.examWatermarkTick).toBeGreaterThan(0);
            expect(requestRender).toHaveBeenCalledWith('exam-watermark-tick', { attemptId: 42 });
        });

        it('does not log fullscreen exit immediately after silent exit', function () {
            var state = createState({ isFullscreenActive: true });
            var sendSpy = vi.fn().mockReturnValue(true);
            var exitFullscreen = vi.fn().mockResolvedValue(undefined);
            var deps = createDeps(state, {
                documentRef: {
                    addEventListener: vi.fn(),
                    body: {},
                    documentElement: {},
                    exitFullscreen: exitFullscreen,
                    fullscreenElement: {},
                    hasFocus: function () { return true; },
                    visibilityState: 'visible',
                    webkitFullscreenElement: null
                },
                sendSecurityEventSilently: sendSpy,
                syncFullscreenState: function () {
                    state.isFullscreenActive = false;
                }
            });
            var manager = createExamSecurityManager(deps);

            manager.mountSecurityListeners();
            manager.exitFullscreenSilently();
            var fullscreenChangeCall = deps.documentRef.addEventListener.mock.calls.find(function (call) {
                return call[0] === 'fullscreenchange';
            });
            expect(fullscreenChangeCall).toBeTruthy();
            fullscreenChangeCall[1]();

            expect(exitFullscreen).toHaveBeenCalled();
            expect(sendSpy).not.toHaveBeenCalledWith('fullscreen_exit', expect.any(Object), expect.any(Object));
        });

        it('falls back to native fullscreen exit when the DOM fullscreen APIs are inactive', function () {
            var state = createState({ isFullscreenActive: true });
            var exitNativeFullscreen = vi.fn().mockResolvedValue(true);
            var deps = createDeps(state, {
                exitNativeFullscreen: exitNativeFullscreen,
                documentRef: {
                    addEventListener: vi.fn(),
                    body: {},
                    documentElement: {},
                    fullscreenElement: null,
                    hasFocus: function () { return true; },
                    visibilityState: 'visible',
                    webkitFullscreenElement: null
                }
            });
            var manager = createExamSecurityManager(deps);

            manager.exitFullscreenSilently();

            expect(exitNativeFullscreen).toHaveBeenCalledTimes(1);
        });

        it('does not refresh watermark ticks outside the exam stage', function () {
            var state = createState({ stage: 'confirm' });
            var requestRender = vi.fn();
            var setIntervalSpy = vi.fn(function (fn) {
                fn();
                return 8;
            });
            var deps = createDeps(state, {
                isExamWatermarkEnabled: function () { return true; },
                requestRender: requestRender,
                windowRef: {
                    addEventListener: vi.fn(),
                    setInterval: setIntervalSpy,
                    navigator: { onLine: true, userAgentData: { platform: 'Windows' } }
                }
            });
            var manager = createExamSecurityManager(deps);

            manager.mountSecurityListeners();

            expect(setIntervalSpy).toHaveBeenCalled();
            expect(requestRender).not.toHaveBeenCalled();
        });
    });

    describe('handleScreenshotKeySignal', function () {
        it('detects PrintScreen key', function () {
            var state = createState();
            var sendSpy = vi.fn().mockReturnValue(true);
            var manager = createExamSecurityManager(createDeps(state, {
                sendSecurityEventSilently: sendSpy
            }));

            var event = { key: 'PrintScreen', code: 'PrintScreen', metaKey: false, shiftKey: false, ctrlKey: false, altKey: false };
            var result = manager.handleScreenshotKeySignal(event);

            expect(result).toBe(true);
            expect(sendSpy).toHaveBeenCalledWith('screenshot_key_detected', expect.objectContaining({ source: 'printscreen_key' }), expect.any(Object));
        });

        it('detects macOS screenshot shortcut (Cmd+Shift+3)', function () {
            var state = createState();
            var sendSpy = vi.fn().mockReturnValue(true);
            var manager = createExamSecurityManager(createDeps(state, {
                sendSecurityEventSilently: sendSpy
            }));

            var event = { key: '3', code: 'Digit3', metaKey: true, shiftKey: true, ctrlKey: false, altKey: false };
            var result = manager.handleScreenshotKeySignal(event);

            expect(result).toBe(true);
            expect(sendSpy).toHaveBeenCalledWith('screenshot_key_detected', expect.objectContaining({ source: 'macos_screenshot_shortcut' }), expect.any(Object));
        });

        it('ignores regular keys', function () {
            var state = createState();
            var sendSpy = vi.fn();
            var manager = createExamSecurityManager(createDeps(state, {
                sendSecurityEventSilently: sendSpy
            }));

            var event = { key: 'a', code: 'KeyA', metaKey: false, shiftKey: false, ctrlKey: false, altKey: false };
            var result = manager.handleScreenshotKeySignal(event);

            expect(result).toBe(false);
            expect(sendSpy).not.toHaveBeenCalled();
        });

        it('does nothing when detection is disabled', function () {
            var state = createState();
            var sendSpy = vi.fn();
            var manager = createExamSecurityManager(createDeps(state, {
                isScreenshotKeyDetectionEnabled: function () { return false; },
                sendSecurityEventSilently: sendSpy
            }));

            var event = { key: 'PrintScreen', code: 'PrintScreen', metaKey: false, shiftKey: false, ctrlKey: false, altKey: false };
            var result = manager.handleScreenshotKeySignal(event);

            expect(result).toBe(false);
        });
    });

    describe('handleBlockedBrowserInspectionShortcutAction', function () {
        it('blocks devtools shortcut and sends event', function () {
            var state = createState();
            var sendSpy = vi.fn().mockReturnValue(true);
            var manager = createExamSecurityManager(createDeps(state, {
                sendSecurityEventSilently: sendSpy
            }));

            var event = { preventDefault: vi.fn(), stopPropagation: vi.fn() };
            var result = manager.handleBlockedBrowserInspectionShortcutAction('devtools_shortcut_blocked', 'devtools_toggle_shortcut', event);

            expect(result).toBe(true);
            expect(event.preventDefault).toHaveBeenCalled();
            expect(sendSpy).toHaveBeenCalledWith('devtools_shortcut_blocked', expect.any(Object), expect.any(Object));
        });

        it('blocks view source shortcut', function () {
            var state = createState();
            var sendSpy = vi.fn().mockReturnValue(true);
            var manager = createExamSecurityManager(createDeps(state, {
                sendSecurityEventSilently: sendSpy
            }));

            var event = { preventDefault: vi.fn(), stopPropagation: vi.fn() };
            var result = manager.handleBlockedBrowserInspectionShortcutAction('view_source_blocked', 'view_source_shortcut', event);

            expect(result).toBe(true);
            expect(sendSpy).toHaveBeenCalledWith('view_source_blocked', expect.any(Object), expect.any(Object));
        });

        it('does nothing when blocking is disabled', function () {
            var state = createState();
            var manager = createExamSecurityManager(createDeps(state, {
                isBrowserInspectionShortcutBlockingEnabled: function () { return false; }
            }));

            var event = { preventDefault: vi.fn(), stopPropagation: vi.fn() };
            var result = manager.handleBlockedBrowserInspectionShortcutAction('devtools_shortcut_blocked', 'devtools_toggle_shortcut', event);

            expect(result).toBe(false);
            expect(event.preventDefault).not.toHaveBeenCalled();
        });
    });

    describe('isExamAnswerEditingLocked', function () {
        it('returns true when exam locked for pending finish', function () {
            var state = createState({ examLockedForPendingFinish: true });
            var manager = createExamSecurityManager(createDeps(state));

            expect(manager.isExamAnswerEditingLocked()).toBe(true);
        });

        it('returns true when finishing', function () {
            var state = createState({ isFinishing: true });
            var manager = createExamSecurityManager(createDeps(state));

            expect(manager.isExamAnswerEditingLocked()).toBe(true);
        });

        it('returns false during normal exam', function () {
            var state = createState();
            var manager = createExamSecurityManager(createDeps(state));

            expect(manager.isExamAnswerEditingLocked()).toBe(false);
        });
    });

    describe('renderExamFullscreenPrompt', function () {
        it('returns prompt HTML when fullscreen blocking is active', function () {
            var state = createState({ isFullscreenActive: false });
            var manager = createExamSecurityManager(createDeps(state));

            var html = manager.renderExamFullscreenPrompt();

            expect(html).toContain('Mode Fullscreen Wajib Aktif');
            expect(html).toContain('data-action="enter-fullscreen"');
        });

        it('returns empty string when fullscreen is active', function () {
            var state = createState({ isFullscreenActive: true });
            var manager = createExamSecurityManager(createDeps(state));

            var html = manager.renderExamFullscreenPrompt();

            expect(html).toBe('');
        });
    });

    describe('requestExamFullscreen', function () {
        it('uses browser fullscreen and clears messages when granted', async function () {
            var state = createState({ isFullscreenActive: false });
            var clearMessages = vi.fn();
            var deps = createDeps(state, {
                clearMessages: clearMessages
            });
            var manager = createExamSecurityManager(deps);

            var result = await manager.requestExamFullscreen();

            expect(result).toBe(true);
            expect(state.isFullscreenActive).toBe(true);
            expect(clearMessages).toHaveBeenCalled();
            expect(deps.documentRef.documentElement.requestFullscreen).toHaveBeenCalled();
        });

        it('falls back to native fullscreen when browser fullscreen is unavailable', async function () {
            var state = createState({ isFullscreenActive: false });
            var nativeFullscreen = vi.fn().mockResolvedValue(true);
            var clearMessages = vi.fn();
            var deps = createDeps(state, {
                clearMessages: clearMessages,
                requestNativeFullscreen: nativeFullscreen
            });
            deps.documentRef.documentElement = {};
            var manager = createExamSecurityManager(deps);

            var result = await manager.requestExamFullscreen();

            expect(result).toBe(true);
            expect(nativeFullscreen).toHaveBeenCalled();
            expect(clearMessages).toHaveBeenCalled();
            expect(state.error).toBe('');
        });

        it('sets a user-facing error when fullscreen cannot be entered', async function () {
            var state = createState({ isFullscreenActive: false });
            var deps = createDeps(state, {
                requestNativeFullscreen: vi.fn().mockResolvedValue(false)
            });
            deps.documentRef.documentElement = {};
            var manager = createExamSecurityManager(deps);

            var result = await manager.requestExamFullscreen();

            expect(result).toBe(false);
            expect(state.error).toContain('Mode fullscreen wajib aktif');
        });
    });

    describe('handleBlockedPrintAction', function () {
        it('sends print_attempt event', function () {
            var state = createState();
            var sendSpy = vi.fn().mockReturnValue(true);
            var manager = createExamSecurityManager(createDeps(state, {
                sendSecurityEventSilently: sendSpy
            }));

            var event = { preventDefault: vi.fn(), stopPropagation: vi.fn() };
            var result = manager.handleBlockedPrintAction('print_shortcut', event, true);

            expect(result).toBe(true);
            expect(sendSpy).toHaveBeenCalledWith('print_attempt', expect.objectContaining({ source: 'print_shortcut', blocked: 1 }), expect.any(Object));
        });

        it('logs beforeprint without blocking the browser event when blocked=false', function () {
            var state = createState();
            var sendSpy = vi.fn().mockReturnValue(true);
            var manager = createExamSecurityManager(createDeps(state, {
                sendSecurityEventSilently: sendSpy
            }));

            var event = { preventDefault: vi.fn(), stopPropagation: vi.fn() };
            var result = manager.handleBlockedPrintAction('beforeprint', event, false);

            expect(result).toBe(false);
            expect(event.preventDefault).not.toHaveBeenCalled();
            expect(sendSpy).toHaveBeenCalledWith('print_attempt', expect.objectContaining({ source: 'beforeprint', blocked: 0 }), expect.any(Object));
        });
    });
});
