export function createExamSecurityManager(deps) {
    var state = deps.state;
    var root = deps.root;
    var documentRef = deps.documentRef;
    var windowRef = deps.windowRef;
    var escapeHtml = deps.escapeHtml;
    var clearMessages = deps.clearMessages;
    var isExamCopyPasteBlocked = deps.isExamCopyPasteBlocked;
    var isExamFullscreenRequired = deps.isExamFullscreenRequired;
    var isSecurityLoggingActiveForAttempt = deps.isSecurityLoggingActiveForAttempt;
    var sendSecurityEventSilently = deps.sendSecurityEventSilently;
    var syncFullscreenState = deps.syncFullscreenState;
    var requestNativeFullscreen = deps.requestNativeFullscreen;
    var setNativeFullscreenActive = deps.setNativeFullscreenActive;
    var exitNativeFullscreen = deps.exitNativeFullscreen;
    var fullscreenExitLogSuppressedUntil = 0;

    function normalizeNativeFullscreenActive(value) {
        if (value === true || value === false) {
            return value;
        }

        if (typeof value === 'number') {
            return value !== 0;
        }

        if (typeof value === 'string') {
            var normalized = value.trim().toLowerCase();
            if (normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on' || normalized === 'active') {
                return true;
            }
            if (normalized === '0' || normalized === 'false' || normalized === 'no' || normalized === 'off' || normalized === 'inactive') {
                return false;
            }
        }

        return null;
    }

    function getNativeFullscreenEventActive(event) {
        var detail = event && event.detail && typeof event.detail === 'object'
            ? event.detail
            : null;

        if (!detail) {
            return null;
        }

        return normalizeNativeFullscreenActive(detail.active);
    }

    function logFullscreenExitIfNeeded(source, wasFullscreenActive) {
        if (
            !wasFullscreenActive
            || state.isFullscreenActive
            || !isExamFullscreenRequired()
            || !isSecurityLoggingActiveForAttempt()
            || Date.now() <= fullscreenExitLogSuppressedUntil
        ) {
            return;
        }

        sendSecurityEventSilently('fullscreen_exit', {
            source: String(source || 'fullscreenchange')
        }, {
            attemptId: Number(state.attemptId) || 0,
            keepalive: true,
            debounceMs: 1500,
            requireFullscreen: true
        });
    }

    function isExamFullscreenBlockingActive() {
        return state.stage === 'exam' && isExamFullscreenRequired() && !state.isFullscreenActive;
    }

    function isExamClipboardBlockingActive() {
        return state.stage === 'exam' && isExamCopyPasteBlocked();
    }

    function isExamAnswerEditingLocked() {
        return state.stage === 'exam' && (state.examLockedForPendingFinish || state.isFinishing);
    }

    function handleBlockedClipboardAction(action, sourceEvent) {
        var safeAction = String(action || '').trim().toLowerCase();
        var safeSource = sourceEvent && sourceEvent.type ? String(sourceEvent.type) : '';

        if (!isExamClipboardBlockingActive()) {
            return false;
        }

        if (sourceEvent && typeof sourceEvent.preventDefault === 'function') {
            sourceEvent.preventDefault();
        }
        if (sourceEvent && typeof sourceEvent.stopPropagation === 'function') {
            sourceEvent.stopPropagation();
        }

        sendSecurityEventSilently('clipboard_blocked', {
            action: safeAction || 'clipboard',
            source: safeSource || safeAction || 'clipboard'
        }, {
            attemptId: Number(state.attemptId) || 0,
            keepalive: true,
            debounceMs: 1500
        });

        return true;
    }

    function requestFullscreenForElement(targetElement) {
        if (!targetElement) {
            return Promise.resolve(false);
        }

        try {
            if (typeof targetElement.requestFullscreen === 'function') {
                return Promise.resolve(targetElement.requestFullscreen()).then(function () {
                    return true;
                });
            }
            if (typeof targetElement.webkitRequestFullscreen === 'function') {
                targetElement.webkitRequestFullscreen();
                return Promise.resolve(true);
            }
            if (typeof targetElement.mozRequestFullScreen === 'function') {
                targetElement.mozRequestFullScreen();
                return Promise.resolve(true);
            }
            if (typeof targetElement.msRequestFullscreen === 'function') {
                targetElement.msRequestFullscreen();
                return Promise.resolve(true);
            }
        } catch (error) {
            return Promise.reject(error);
        }

        return Promise.resolve(false);
    }

    function exitFullscreenSilently() {
        fullscreenExitLogSuppressedUntil = Date.now() + 2000;

        try {
            if (documentRef.fullscreenElement && typeof documentRef.exitFullscreen === 'function') {
                documentRef.exitFullscreen().catch(function () {
                    // Ignore fullscreen exit errors.
                });
                return;
            }
            if (documentRef.webkitFullscreenElement && typeof documentRef.webkitExitFullscreen === 'function') {
                documentRef.webkitExitFullscreen();
                return;
            }
            if (documentRef.mozFullScreenElement && typeof documentRef.mozCancelFullScreen === 'function') {
                documentRef.mozCancelFullScreen();
                return;
            }
            if (documentRef.msFullscreenElement && typeof documentRef.msExitFullscreen === 'function') {
                documentRef.msExitFullscreen();
                return;
            }
        } catch (error) {
            // Ignore fullscreen exit errors.
        }

        if (typeof exitNativeFullscreen === 'function') {
            exitNativeFullscreen().catch(function () {
                // Ignore native fullscreen exit errors.
            });
        }
    }

    async function requestExamFullscreen(options) {
        options = options || {};

        if (!isExamFullscreenRequired()) {
            return true;
        }

        syncFullscreenState(false);
        if (state.isFullscreenActive) {
            return true;
        }

        var fullscreenTarget = documentRef.documentElement || documentRef.body || root;
        try {
            var entered = await requestFullscreenForElement(fullscreenTarget);
            syncFullscreenState(false);

            if (entered) {
                state.isFullscreenActive = true;
                clearMessages();
                return true;
            }
        } catch (error) {
            syncFullscreenState(false);
        }

        if (typeof requestNativeFullscreen === 'function') {
            try {
                var enteredNatively = await requestNativeFullscreen();
                syncFullscreenState(false);

                if (enteredNatively || state.isFullscreenActive) {
                    clearMessages();
                    return true;
                }
            } catch (error) {
                syncFullscreenState(false);
            }
        }

        if (!options.silent) {
            state.error = 'Mode fullscreen wajib aktif untuk ujian ini. Izinkan fullscreen lalu coba lagi.';
        }
        return false;
    }

    function renderExamFullscreenPrompt() {
        if (!isExamFullscreenBlockingActive()) {
            return '';
        }

        return [
            '<div class="cbt-exam-fullscreen-guard" role="alert" aria-live="assertive">',
            '<div class="cbt-exam-fullscreen-guard-card">',
            '<span class="cbt-exam-fullscreen-guard-chip">Security</span>',
            '<h3>Mode Fullscreen Wajib Aktif</h3>',
            '<p>Ujian ini menggunakan pengamanan fullscreen. Aktifkan fullscreen terlebih dahulu untuk melanjutkan pengerjaan soal.</p>',
            '<div class="cbt-actions cbt-exam-fullscreen-guard-actions">',
            '<button class="cbt-button cbt-button-primary" data-action="enter-fullscreen" type="button">Aktifkan Fullscreen</button>',
            '<button class="cbt-button cbt-button-secondary" data-action="logout" type="button">Logout</button>',
            '</div>',
            '</div>',
            '</div>'
        ].join('');
    }

    function mountSecurityListeners() {
        ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'].forEach(function (eventName) {
            documentRef.addEventListener(eventName, function () {
                var wasFullscreenActive = state.isFullscreenActive;
                syncFullscreenState(true);
                logFullscreenExitIfNeeded('fullscreenchange', wasFullscreenActive);
            });
        });

        function handleNativeFullscreenChange(event) {
            var wasFullscreenActive = state.isFullscreenActive;
            var nextActive = getNativeFullscreenEventActive(event);

            if (nextActive === null || typeof setNativeFullscreenActive !== 'function') {
                return;
            }

            setNativeFullscreenActive(nextActive, true);
            logFullscreenExitIfNeeded('native-fullscreen-change', wasFullscreenActive);
        }

        ['cbt-native-fullscreen-change', 'cbt:native-fullscreen-change'].forEach(function (eventName) {
            windowRef.addEventListener(eventName, handleNativeFullscreenChange);
            documentRef.addEventListener(eventName, handleNativeFullscreenChange);
        });

        ['copy', 'cut', 'paste'].forEach(function (eventName) {
            documentRef.addEventListener(eventName, function (event) {
                handleBlockedClipboardAction(eventName, event);
            }, true);
        });

        documentRef.addEventListener('beforeinput', function (event) {
            var inputType = event && event.inputType ? String(event.inputType) : '';

            if (!isExamClipboardBlockingActive()) {
                return;
            }

            if (inputType === 'insertFromPaste' || inputType === 'insertFromPasteAsQuotation' || inputType === 'deleteByCut') {
                handleBlockedClipboardAction(inputType.indexOf('deleteByCut') === 0 ? 'cut' : 'paste', event);
            }
        });
    }

    return {
        exitFullscreenSilently: exitFullscreenSilently,
        handleBlockedClipboardAction: handleBlockedClipboardAction,
        isExamAnswerEditingLocked: isExamAnswerEditingLocked,
        isExamClipboardBlockingActive: isExamClipboardBlockingActive,
        isExamFullscreenBlockingActive: isExamFullscreenBlockingActive,
        mountSecurityListeners: mountSecurityListeners,
        renderExamFullscreenPrompt: renderExamFullscreenPrompt,
        requestExamFullscreen: requestExamFullscreen
    };
}
