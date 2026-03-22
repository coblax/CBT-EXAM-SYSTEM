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
    var fullscreenExitLogSuppressedUntil = 0;

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
            }
        } catch (error) {
            // Ignore fullscreen exit errors.
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

                if (
                    wasFullscreenActive
                    && !state.isFullscreenActive
                    && isExamFullscreenRequired()
                    && isSecurityLoggingActiveForAttempt()
                    && Date.now() > fullscreenExitLogSuppressedUntil
                ) {
                    sendSecurityEventSilently('fullscreen_exit', {
                        source: 'fullscreenchange'
                    }, {
                        attemptId: Number(state.attemptId) || 0,
                        keepalive: true,
                        debounceMs: 1500,
                        requireFullscreen: true
                    });
                }
            });
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
