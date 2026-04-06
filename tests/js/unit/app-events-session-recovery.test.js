import { describe, expect, it, vi } from 'vitest';
import { createAppEventManager } from '../../../src/frontend/app/core/app-events.js';

function createFixture(overrides = {}) {
    var root = document.createElement('div');
    document.body.appendChild(root);
    var retrySessionRecovery = overrides.retrySessionRecovery || vi.fn(function () {
        return Promise.resolve(true);
    });
    var state = Object.assign({
        stage: 'confirm',
        examPickerMobileOpen: false,
        userPhotoModalOpen: false,
        finishConfirmOpen: false,
        isFinishing: false,
        calculatorVisible: false,
        isOpeningAttempt: false,
        sessionRecoveryVisible: true
    }, overrides.state || {});

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
        handleBlockedBrowserInspectionShortcutAction: function () {
            return false;
        },
        handleBlockedClipboardAction: function () {
            return false;
        },
        handleBlockedPrintAction: function () {
            return false;
        },
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
            return false;
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
        requestExamFullscreen: function () {
            return Promise.resolve(false);
        },
        resetExamSession: function () {},
        retrySessionRecovery: retrySessionRecovery,
        root: root,
        stageRuntimeManager: {
            handleCalculatorEnterKey: function () {
                return true;
            }
        },
        state: state,
        toggleTheme: function () {},
        updateFontScale: function () {
            return false;
        },
        updateNavPanelPosition: function () {
            return false;
        },
        updateSelectedExam: function () {}
    });

    return {
        manager: manager,
        retrySessionRecovery: retrySessionRecovery,
        root: root
    };
}

describe('createAppEventManager session recovery actions', function () {
    it('delegates retry-session-recovery actions without forcing logout or reset', function () {
        var fixture = createFixture();
        fixture.root.innerHTML = '<button type="button" data-action="retry-session-recovery">Retry</button>';
        var button = fixture.root.querySelector('[data-action="retry-session-recovery"]');
        var event = {
            preventDefault: vi.fn(),
            target: button
        };

        var handled = fixture.manager.handleRootClick(event);

        expect(handled).toBe(true);
        expect(event.preventDefault).toHaveBeenCalledTimes(1);
        expect(fixture.retrySessionRecovery).toHaveBeenCalledTimes(1);
    });
});
