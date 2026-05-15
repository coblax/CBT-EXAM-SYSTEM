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
    });
});
