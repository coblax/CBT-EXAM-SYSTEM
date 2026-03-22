export function createAppEventManager(deps) {
    var debugManager = deps.debugManager;
    var documentRef = deps.documentRef;
    var fontScaleDefault = deps.fontScaleDefault;
    var fontScaleStep = deps.fontScaleStep;
    var getCurrentUserPhoto = deps.getCurrentUserPhoto;
    var handleAnswerChangeTarget = deps.handleAnswerChangeTarget;
    var handleAnswerInputTarget = deps.handleAnswerInputTarget;
    var handleArrowNavigationKey = deps.handleArrowNavigationKey;
    var handleBlockedClipboardAction = deps.handleBlockedClipboardAction;
    var handleFinish = deps.handleFinish;
    var handleLogin = deps.handleLogin;
    var handleNavigationAction = deps.handleNavigationAction;
    var handleStartExam = deps.handleStartExam;
    var handleViewResult = deps.handleViewResult;
    var isCompactNavViewport = deps.isCompactNavViewport;
    var isExamAnswerEditingLocked = deps.isExamAnswerEditingLocked;
    var isExamClipboardBlockingActive = deps.isExamClipboardBlockingActive;
    var isExamFullscreenBlockingActive = deps.isExamFullscreenBlockingActive;
    var isQuestionRevisionRefreshActive = deps.isQuestionRevisionRefreshActive;
    var loadExams = deps.loadExams;
    var noteQuestionPrefetchActivity = deps.noteQuestionPrefetchActivity;
    var recordActionTrail = deps.recordActionTrail;
    var render = deps.render;
    var requestExamFullscreen = deps.requestExamFullscreen;
    var resetExamSession = deps.resetExamSession;
    var root = deps.root;
    var stageRuntimeManager = deps.stageRuntimeManager;
    var state = deps.state;
    var toggleTheme = deps.toggleTheme;
    var updateFontScale = deps.updateFontScale;
    var updateNavPanelPosition = deps.updateNavPanelPosition;
    var updateSelectedExam = deps.updateSelectedExam;
    var closeFinishConfirmModal = deps.closeFinishConfirmModal;
    var clearMessages = deps.clearMessages;
    var fullLogout = deps.fullLogout;
    var flushAttemptUiStateSilently = deps.flushAttemptUiStateSilently;
    var flushPendingAnswerBatchSilently = deps.flushPendingAnswerBatchSilently;
    var suppressedClickAction = '';
    var suppressedClickUntil = 0;

    function resolveEventElement(target) {
        if (target instanceof Element) {
            return target;
        }
        if (target && target.parentElement instanceof Element) {
            return target.parentElement;
        }
        return null;
    }

    function describeActionNode(element) {
        if (!(element instanceof Element)) {
            return '-';
        }

        var text = element.tagName ? String(element.tagName).toLowerCase() : 'node';
        if (element.id) {
            text += '#' + String(element.id);
        }
        if (typeof element.className === 'string' && element.className.trim() !== '') {
            var classTokens = element.className.trim().split(/\s+/).slice(0, 3);
            if (classTokens.length) {
                text += '.' + classTokens.join('.');
            }
        }
        var action = element.getAttribute('data-action');
        if (action) {
            text += '[data-action="' + action + '"]';
        }

        return text;
    }

    function resolveActionNode(target) {
        var element = resolveEventElement(target);
        if (!(element instanceof Element)) {
            return null;
        }

        var actionNode = element.closest('[data-action]');
        return actionNode instanceof Element ? actionNode : null;
    }

    function resolveActionNodeFromPoint(event) {
        if (
            !event
            || typeof event.clientX !== 'number'
            || typeof event.clientY !== 'number'
            || !documentRef
            || typeof documentRef.elementsFromPoint !== 'function'
        ) {
            return null;
        }

        var elements = documentRef.elementsFromPoint(event.clientX, event.clientY);
        if (!Array.isArray(elements) || !elements.length) {
            return null;
        }

        for (var i = 0; i < elements.length; i++) {
            var element = elements[i];
            if (!(element instanceof Element) || !root.contains(element)) {
                continue;
            }

            var actionNode = element.closest('[data-action]');
            if (actionNode instanceof Element && root.contains(actionNode)) {
                return actionNode;
            }
        }

        return null;
    }

    function resolveAction(target, event) {
        var actionNode = resolveActionNode(target);
        if (!(actionNode instanceof Element)) {
            actionNode = resolveActionNodeFromPoint(event);
        }
        if (!(actionNode instanceof Element)) {
            return null;
        }

        var action = actionNode.getAttribute('data-action');
        if (!action) {
            return null;
        }

        return {
            action: action,
            actionNode: actionNode
        };
    }

    function shouldHandleActionOnPointerDown(action) {
        return action === 'logout'
            || action === 'toggle-theme'
            || action === 'open-user-photo'
            || action === 'close-user-photo'
            || action === 'back-confirm'
            || action === 'enter-fullscreen';
    }

    function markSuppressedClickAction(action) {
        suppressedClickAction = String(action || '');
        suppressedClickUntil = Date.now() + 1200;
    }

    function shouldSuppressClickAction(action) {
        if (suppressedClickAction === '' || suppressedClickAction !== String(action || '')) {
            return false;
        }

        if (Date.now() > suppressedClickUntil) {
            suppressedClickAction = '';
            suppressedClickUntil = 0;
            return false;
        }

        return true;
    }

    function safeNoteQuestionPrefetchActivity() {
        if (state.stage !== 'exam' || state.isOpeningAttempt) {
            return;
        }

        try {
            noteQuestionPrefetchActivity();
        } catch (error) {
            // Prefetch bookkeeping should never block UI controls.
        }
    }

    function recordUiAction(kind, summary, meta) {
        if (typeof recordActionTrail !== 'function') {
            return;
        }

        recordActionTrail(kind, summary, Object.assign({
            actionStage: String(state.stage || 'login')
        }, meta || {}));
    }

    function handleSubmit(target, event) {
        if (!(target instanceof HTMLFormElement)) {
            return false;
        }

        if (target.id === 'cbt-login-form') {
            event.preventDefault();
            recordUiAction('login:submit', 'Form login dikirim.', {});
            handleLogin(target);
            return true;
        }

        return false;
    }

    function handleRootClick(event) {
        var resolvedAction = resolveAction(event.target, event);
        if (!resolvedAction) {
            if (debugManager && debugManager.enabled) {
                debugManager.logEvent('handleRootClick:no-action', event);
            }
            return false;
        }

        var action = resolvedAction.action;
        var actionNode = resolvedAction.actionNode;

        if (debugManager && debugManager.enabled) {
            debugManager.logEvent('handleRootClick:action', event, {
                action: action,
                actionNode: describeActionNode(actionNode)
            });
        }

        if (shouldSuppressClickAction(action)) {
            suppressedClickAction = '';
            suppressedClickUntil = 0;
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            return true;
        }

        if (action !== 'user-photo-modal-panel') {
            recordUiAction('ui:action', 'Action UI dipicu: ' + action + '.', {
                action: action,
                target: describeActionNode(actionNode)
            });
        }

        if (action === 'enter-fullscreen') {
            requestExamFullscreen({
                silent: false
            }).then(function (entered) {
                if (entered) {
                    clearMessages();
                }
                render('enter-fullscreen', {
                    action: action
                });
            });
            return true;
        }

        if (isExamFullscreenBlockingActive() && action !== 'logout' && action !== 'toggle-theme') {
            event.preventDefault();
            return true;
        }

        if (action === 'font-dec') {
            safeNoteQuestionPrefetchActivity();
            if (updateFontScale(state.fontScale - fontScaleStep)) {
                render('font-scale', {
                    action: action
                });
            }
            return true;
        }

        if (action === 'font-inc') {
            safeNoteQuestionPrefetchActivity();
            if (updateFontScale(state.fontScale + fontScaleStep)) {
                render('font-scale', {
                    action: action
                });
            }
            return true;
        }

        if (action === 'font-reset') {
            safeNoteQuestionPrefetchActivity();
            if (updateFontScale(fontScaleDefault)) {
                render('font-scale', {
                    action: action
                });
            }
            return true;
        }

        if (action === 'toggle-theme') {
            toggleTheme();
            render('toggle-theme', {
                action: action
            });
            return true;
        }

        if (action === 'logout') {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            fullLogout();
            return true;
        }

        if (action === 'open-user-photo') {
            if (getCurrentUserPhoto() === '') {
                return true;
            }
            state.userPhotoModalOpen = true;
            render('open-user-photo', {
                action: action
            });
            return true;
        }

        if (action === 'close-user-photo') {
            state.userPhotoModalOpen = false;
            render('close-user-photo', {
                action: action
            });
            return true;
        }

        if (action === 'user-photo-modal-panel') {
            return true;
        }

        if (action === 'toggle-password') {
            state.loginPasswordVisible = !state.loginPasswordVisible;
            render('toggle-password', {
                action: action
            });
            var passwordInput = root.querySelector('#cbt-password');
            if (passwordInput instanceof HTMLInputElement) {
                passwordInput.focus();
                var caret = passwordInput.value.length;
                try {
                    passwordInput.setSelectionRange(caret, caret);
                } catch (error) {}
            }
            return true;
        }

        if (action === 'reload-exams') {
            if (state.busy) {
                return true;
            }
            state.busy = true;
            clearMessages();
            render('reload-exams', {
                action: action
            });
            loadExams()
                .then(function () {
                    state.error = '';
                })
                .catch(function (error) {
                    state.error = error instanceof Error ? error.message : 'Gagal memuat exam.';
                })
                .finally(function () {
                    state.busy = false;
                    render('reload-exams', {
                        action: action
                    });
                });
            return true;
        }

        if (action === 'select-exam' || action === 'select-exam-mobile') {
            updateSelectedExam(Number(actionNode.getAttribute('data-id')) || 0);
            return true;
        }

        if (action === 'toggle-exam-picker-mobile') {
            if (state.busy) {
                return true;
            }

            state.examPickerMobileOpen = !state.examPickerMobileOpen;
            render('toggle-exam-picker', {
                action: action
            });

            if (state.examPickerMobileOpen) {
                var activePickerOption = root.querySelector('.cbt-exam-picker-option.is-active, .cbt-exam-picker-option');
                if (activePickerOption instanceof HTMLButtonElement) {
                    activePickerOption.focus();
                }
            }
            return true;
        }

        if (action === 'start-exam') {
            handleStartExam();
            return true;
        }

        if (action === 'view-result') {
            handleViewResult();
            return true;
        }

        if (action === 'retry-load-exam-stage') {
            stageRuntimeManager.retryLoadExamStage();
            return true;
        }

        if (action === 'retry-load-result-stage') {
            stageRuntimeManager.retryLoadResultStage();
            return true;
        }

        if (action === 'toggle-nav') {
            safeNoteQuestionPrefetchActivity();
            state.navPanelVisible = !state.navPanelVisible;
            render('toggle-nav', {
                action: action
            });
            return true;
        }

        if (action === 'toggle-calculator') {
            safeNoteQuestionPrefetchActivity();
            stageRuntimeManager.toggleCalculator();
            return true;
        }

        if (
            action === 'calc-key'
            || action === 'calc-clear'
            || action === 'calc-backspace'
            || action === 'calc-eval'
            || action === 'set-calc-position'
        ) {
            safeNoteQuestionPrefetchActivity();
            stageRuntimeManager.handleCalculatorAction(action, actionNode);
            return true;
        }

        if (action === 'set-nav-position') {
            safeNoteQuestionPrefetchActivity();
            var requestedPosition = String(actionNode.getAttribute('data-position') || '');
            if (isCompactNavViewport() && (requestedPosition === 'left' || requestedPosition === 'right')) {
                return true;
            }
            if (updateNavPanelPosition(requestedPosition)) {
                render('set-nav-position', {
                    action: action,
                    position: requestedPosition
                });
            }
            return true;
        }

        if (handleNavigationAction(action, actionNode)) {
            safeNoteQuestionPrefetchActivity();
            return true;
        }

        if (action === 'finish' || action === 'collect') {
            safeNoteQuestionPrefetchActivity();
            handleFinish(false);
            return true;
        }

        if (action === 'finish-confirm-cancel') {
            closeFinishConfirmModal();
            return true;
        }

        if (action === 'finish-confirm-submit') {
            handleFinish(false, { skipConfirmation: true });
            return true;
        }

        if (action === 'back-confirm') {
            flushPendingAnswerBatchSilently({
                flushAll: true,
                keepalive: true
            });
            flushAttemptUiStateSilently({
                force: true,
                keepalive: true
            });
            resetExamSession();
            state.stage = 'confirm';
            state.busy = false;
            clearMessages();
            render('back-confirm', {
                action: action
            });
            return true;
        }

        return false;
    }

    function handlePointerDown(event) {
        var resolvedAction = resolveAction(event.target, event);
        if (!resolvedAction) {
            if (debugManager && debugManager.enabled) {
                debugManager.logEvent('handlePointerDown:no-action', event);
            }
            return false;
        }

        var action = resolvedAction.action;
        if (debugManager && debugManager.enabled) {
            debugManager.logEvent('handlePointerDown:action', event, {
                action: action,
                actionNode: describeActionNode(resolvedAction.actionNode)
            });
        }
        if (!shouldHandleActionOnPointerDown(action)) {
            return false;
        }

        markSuppressedClickAction(action);
        if (typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        return handleRootClick(event);
    }

    function handleChange(target) {
        target = resolveEventElement(target);
        if (!(target instanceof HTMLElement)) {
            return false;
        }

        var targetAction = String(target.getAttribute('data-action') || '');

        if (isExamFullscreenBlockingActive()) {
            return false;
        }

        if (isQuestionRevisionRefreshActive() && targetAction.indexOf('answer-') === 0) {
            return false;
        }

        if (isExamAnswerEditingLocked() && targetAction.indexOf('answer-') === 0) {
            return false;
        }

        safeNoteQuestionPrefetchActivity();

        return handleAnswerChangeTarget(target);
    }

    function handleInput(target) {
        target = resolveEventElement(target);
        if (!(target instanceof HTMLElement)) {
            return false;
        }

        var action = String(target.getAttribute('data-action') || '');

        if (isExamFullscreenBlockingActive()) {
            return false;
        }

        if (isQuestionRevisionRefreshActive() && action.indexOf('answer-') === 0) {
            return false;
        }

        if (isExamAnswerEditingLocked() && action.indexOf('answer-') === 0) {
            return false;
        }

        safeNoteQuestionPrefetchActivity();

        if (target.getAttribute('name') === 'calc_expression') {
            stageRuntimeManager.handleCalculatorInput(target);
            return true;
        }

        return handleAnswerInputTarget(target);
    }

    function handleDocumentClick(event) {
        if (!state.examPickerMobileOpen) {
            return false;
        }

        var target = resolveEventElement(event.target);
        if (!(target instanceof Element)) {
            return false;
        }

        if (target.closest('.cbt-exam-picker-dropdown')) {
            return false;
        }

        state.examPickerMobileOpen = false;
        render('close-exam-picker', {});
        return true;
    }

    function handleKeydown(event) {
        if (!event) {
            return false;
        }

        if ((event.ctrlKey || event.metaKey || event.shiftKey) && isExamClipboardBlockingActive()) {
            var key = String(event.key || '').toLowerCase();
            var shouldBlockClipboardShortcut = false;
            var clipboardAction = '';

            if ((event.ctrlKey || event.metaKey) && (key === 'c' || key === 'x' || key === 'v')) {
                shouldBlockClipboardShortcut = true;
                clipboardAction = key === 'c' ? 'copy' : (key === 'x' ? 'cut' : 'paste');
            } else if ((event.ctrlKey || event.metaKey) && key === 'insert') {
                shouldBlockClipboardShortcut = true;
                clipboardAction = 'copy';
            } else if (event.shiftKey && key === 'insert') {
                shouldBlockClipboardShortcut = true;
                clipboardAction = 'paste';
            } else if (event.shiftKey && key === 'delete') {
                shouldBlockClipboardShortcut = true;
                clipboardAction = 'cut';
            }

            if (shouldBlockClipboardShortcut && handleBlockedClipboardAction(clipboardAction, event)) {
                return true;
            }
        }

        if (isExamFullscreenBlockingActive()) {
            return false;
        }

        if (handleArrowNavigationKey(event)) {
            return true;
        }

        if (event.key === 'Escape') {
            if (state.examPickerMobileOpen) {
                event.preventDefault();
                state.examPickerMobileOpen = false;
                render('escape-close-picker', {});
                return true;
            }

            if (state.userPhotoModalOpen) {
                event.preventDefault();
                state.userPhotoModalOpen = false;
                render('escape-close-user-photo', {});
                return true;
            }

            if (state.finishConfirmOpen && !state.isFinishing) {
                event.preventDefault();
                closeFinishConfirmModal();
                return true;
            }

            if (state.stage === 'exam' && state.calculatorVisible) {
                event.preventDefault();
                state.calculatorVisible = false;
                state.calculatorError = '';
                render('escape-close-calculator', {});
                return true;
            }
            return false;
        }

        if (event.key === 'Enter' && state.stage === 'exam') {
            var activeElement = documentRef.activeElement;
            if (!(activeElement instanceof HTMLInputElement) || activeElement.getAttribute('name') !== 'calc_expression') {
                return false;
            }
            event.preventDefault();
            return stageRuntimeManager.handleCalculatorEnterKey();
        }

        return false;
    }

    return {
        handleChange: handleChange,
        handleDocumentClick: handleDocumentClick,
        handleInput: handleInput,
        handleKeydown: handleKeydown,
        handlePointerDown: handlePointerDown,
        handleRootClick: handleRootClick,
        handleSubmit: handleSubmit
    };
}
