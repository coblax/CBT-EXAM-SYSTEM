import { describe, expect, it, vi } from 'vitest';
import { createAppEventManager } from '../../../src/frontend/app/core/app-events.js';

function createManager(overrides = {}) {
    var state = Object.assign({
        stage: 'exam',
        examPickerMobileOpen: false,
        userPhotoModalOpen: false,
        finishConfirmOpen: false,
        isFinishing: false,
        calculatorVisible: false,
        isOpeningAttempt: false
    }, overrides.state || {});

    var handleBlockedPrintAction = overrides.handleBlockedPrintAction || vi.fn(function () {
        return true;
    });
    var handleBlockedBrowserInspectionShortcutAction = overrides.handleBlockedBrowserInspectionShortcutAction || vi.fn(function () {
        return true;
    });
    var handleBlockedClipboardAction = overrides.handleBlockedClipboardAction || vi.fn(function () {
        return false;
    });

    var manager = createAppEventManager({
        clearMessages: function () {},
        closeFinishConfirmModal: function () {},
        debugManager: null,
        documentRef: document,
        flushAttemptUiStateSilently: function () {},
        flushPendingAnswerBatchSilently: function () {},
        fontScaleDefault: 100,
        fontScaleStep: 10,
        fullLogout: function () {},
        getCurrentUserPhoto: function () {
            return '';
        },
        recordActionTrail: function () {},
        handleAnswerChangeTarget: function () {
            return false;
        },
        handleAnswerInputTarget: function () {
            return false;
        },
        handleArrowNavigationKey: function () {
            return false;
        },
        handleBlockedBrowserInspectionShortcutAction: handleBlockedBrowserInspectionShortcutAction,
        handleBlockedClipboardAction: handleBlockedClipboardAction,
        handleBlockedPrintAction: handleBlockedPrintAction,
        handleFinish: function () {},
        handleLogin: function () {},
        handleNavigationAction: function () {
            return false;
        },
        handleStartExam: function () {},
        handleViewResult: function () {},
        isCompactNavViewport: function () {
            return false;
        },
        isExamAnswerEditingLocked: function () {
            return false;
        },
        isExamClipboardBlockingActive: function () {
            return overrides.clipboardBlockingActive === true;
        },
        isExamFullscreenBlockingActive: function () {
            return false;
        },
        isQuestionRevisionRefreshActive: function () {
            return false;
        },
        loadExams: function () {},
        noteQuestionPrefetchActivity: function () {},
        render: function () {},
        requestExamFullscreen: function () {},
        resetExamSession: function () {},
        root: document.body,
        stageRuntimeManager: {
            handleCalculatorEnterKey: function () {
                return true;
            }
        },
        state: state,
        toggleTheme: function () {},
        updateFontScale: function () {},
        updateNavPanelPosition: function () {},
        updateSelectedExam: function () {}
    });

    return {
        handleBlockedBrowserInspectionShortcutAction: handleBlockedBrowserInspectionShortcutAction,
        handleBlockedClipboardAction: handleBlockedClipboardAction,
        handleBlockedPrintAction: handleBlockedPrintAction,
        manager: manager,
        state: state
    };
}

describe('createAppEventManager browser security shortcuts', function () {
    it('intercepts Ctrl+P during exam stage and delegates to the print security handler', function () {
        var setup = createManager();
        var event = {
            ctrlKey: true,
            metaKey: false,
            key: 'p'
        };

        var handled = setup.manager.handleKeydown(event);

        expect(handled).toBe(true);
        expect(setup.handleBlockedPrintAction).toHaveBeenCalledTimes(1);
        expect(setup.handleBlockedPrintAction).toHaveBeenCalledWith('print_shortcut', event, true);
        expect(setup.handleBlockedClipboardAction).not.toHaveBeenCalled();
    });

    it('keeps clipboard shortcut handling unchanged for other shortcuts', function () {
        var setup = createManager({
            clipboardBlockingActive: true,
            handleBlockedClipboardAction: vi.fn(function () {
                return true;
            })
        });
        var event = {
            ctrlKey: true,
            metaKey: false,
            shiftKey: false,
            key: 'c'
        };

        var handled = setup.manager.handleKeydown(event);

        expect(handled).toBe(true);
        expect(setup.handleBlockedPrintAction).not.toHaveBeenCalled();
        expect(setup.handleBlockedClipboardAction).toHaveBeenCalledTimes(1);
        expect(setup.handleBlockedClipboardAction).toHaveBeenCalledWith('copy', event);
    });

    it('intercepts F12 and delegates it to the browser inspection shortcut guard', function () {
        var setup = createManager();
        var event = {
            altKey: false,
            ctrlKey: false,
            key: 'F12',
            metaKey: false,
            shiftKey: false
        };

        var handled = setup.manager.handleKeydown(event);

        expect(handled).toBe(true);
        expect(setup.handleBlockedBrowserInspectionShortcutAction).toHaveBeenCalledWith(
            'devtools_shortcut_blocked',
            'devtools_toggle_shortcut',
            event
        );
        expect(setup.handleBlockedPrintAction).not.toHaveBeenCalled();
    });

    it('intercepts Ctrl+U and Ctrl+S with dedicated browser security event types', function () {
        var setup = createManager();
        var viewSourceEvent = {
            altKey: false,
            ctrlKey: true,
            key: 'u',
            metaKey: false,
            shiftKey: false
        };
        var savePageEvent = {
            altKey: false,
            ctrlKey: true,
            key: 's',
            metaKey: false,
            shiftKey: false
        };

        expect(setup.manager.handleKeydown(viewSourceEvent)).toBe(true);
        expect(setup.manager.handleKeydown(savePageEvent)).toBe(true);
        expect(setup.handleBlockedBrowserInspectionShortcutAction).toHaveBeenNthCalledWith(
            1,
            'view_source_blocked',
            'view_source_shortcut',
            viewSourceEvent
        );
        expect(setup.handleBlockedBrowserInspectionShortcutAction).toHaveBeenNthCalledWith(
            2,
            'save_page_blocked',
            'save_page_shortcut',
            savePageEvent
        );
    });

    it('does not intercept print shortcut outside exam stage', function () {
        var setup = createManager({
            state: {
                stage: 'login'
            }
        });
        var event = {
            ctrlKey: true,
            metaKey: false,
            key: 'p'
        };

        var handled = setup.manager.handleKeydown(event);

        expect(handled).toBe(false);
        expect(setup.handleBlockedPrintAction).not.toHaveBeenCalled();
        expect(setup.handleBlockedBrowserInspectionShortcutAction).not.toHaveBeenCalled();
    });
});
