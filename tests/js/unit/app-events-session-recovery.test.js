import { describe, expect, it, vi } from 'vitest';
import { createAppEventManager } from '../../../src/frontend/app/core/app-events.js';

async function flushAsyncWork() {
    await Promise.resolve();
    await Promise.resolve();
    await Promise.resolve();
}

function createFixture(overrides = {}) {
    var root = document.createElement('div');
    document.body.appendChild(root);
    var calls = {
        clearMessages: 0,
        render: []
    };
    var clearMessages = overrides.clearMessages || vi.fn(function () {
        calls.clearMessages += 1;
    });
    var retrySessionRecovery = overrides.retrySessionRecovery || vi.fn(function () {
        return Promise.resolve(true);
    });
    var loadExams = overrides.loadExams || vi.fn(function () {
        return Promise.resolve();
    });
    var handleFinish = overrides.handleFinish || vi.fn(function () {});
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
        clearMessages: clearMessages,
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
        handleFinish: handleFinish,
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
        loadExams: loadExams,
        noteQuestionPrefetchActivity: function () {},
        render: function (reason, meta, options) {
            calls.render.push({
                meta: meta || {},
                options: options || {},
                reason: reason
            });
        },
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
        calls: calls,
        clearMessages: clearMessages,
        loadExams: loadExams,
        handleFinish: handleFinish,
        manager: manager,
        retrySessionRecovery: retrySessionRecovery,
        root: root,
        state: state
    };
}

describe('createAppEventManager session recovery actions', function () {
    it('dismisses explicit alerts and rerenders the current stage', function () {
        var fixture = createFixture();
        fixture.root.innerHTML = '<button type="button" data-action="dismiss-alert">x</button>';
        var button = fixture.root.querySelector('[data-action="dismiss-alert"]');
        var event = {
            preventDefault: vi.fn(),
            target: button
        };

        var handled = fixture.manager.handleRootClick(event);

        expect(handled).toBe(true);
        expect(event.preventDefault).toHaveBeenCalledTimes(1);
        expect(fixture.clearMessages).toHaveBeenCalledTimes(1);
        expect(fixture.calls.render.at(-1)).toMatchObject({
            reason: 'dismiss-alert'
        });
    });

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

    it('keeps final submit routed through skipConfirmation from the review modal', function () {
        var fixture = createFixture({
            state: {
                stage: 'exam',
                finishConfirmOpen: true
            }
        });
        fixture.root.innerHTML = '<button type="button" data-action="finish-confirm-submit">Saya Yakin Kumpulkan</button>';
        var button = fixture.root.querySelector('[data-action="finish-confirm-submit"]');
        var event = {
            preventDefault: vi.fn(),
            target: button
        };

        var handled = fixture.manager.handleRootClick(event);

        expect(handled).toBe(true);
        expect(fixture.handleFinish).toHaveBeenCalledTimes(1);
        expect(fixture.handleFinish).toHaveBeenCalledWith(false, { skipConfirmation: true });
    });

    it('keeps the current screen and shows the nginx authorization hint when reload receives missing_token', async function () {
        var error = new Error('Authorization token not found');
        error.code = 'missing_token';
        var fixture = createFixture({
            loadExams: vi.fn(function () {
                return Promise.reject(error);
            })
        });
        fixture.root.innerHTML = '<button type="button" data-action="reload-exams">Refresh</button>';
        var button = fixture.root.querySelector('[data-action="reload-exams"]');
        var event = {
            preventDefault: vi.fn(),
            target: button
        };

        var handled = fixture.manager.handleRootClick(event);
        await flushAsyncWork();

        expect(handled).toBe(true);
        expect(fixture.loadExams).toHaveBeenCalledTimes(1);
        expect(fixture.state.stage).toBe('confirm');
        expect(fixture.state.busy).toBe(false);
        expect(fixture.state.error).toContain('fastcgi_param HTTP_AUTHORIZATION');
        expect(fixture.calls.render.at(-1)).toMatchObject({
            reason: 'reload-exams'
        });
    });
});
