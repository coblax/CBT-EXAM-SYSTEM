export function createSessionLifecycleManager(deps) {
    var recordTimeline = deps.recordTimeline;
    var state = deps.state;
    var root = deps.root;
    var windowRef = deps.windowRef;
    var formatSeconds = deps.formatSeconds;
    var clearMessages = deps.clearMessages;
    var render = deps.render;
    var stopSessionHeartbeat = deps.stopSessionHeartbeat;
    var exitFullscreenSilently = deps.exitFullscreenSilently;
    var clearSecurityLoggingRuntimeState = deps.clearSecurityLoggingRuntimeState;
    var clearAutoSaveRuntimeState = deps.clearAutoSaveRuntimeState;
    var clearQuestionPrefetchRuntimeState = deps.clearQuestionPrefetchRuntimeState;
    var clearAttemptUiStateSyncTimer = deps.clearAttemptUiStateSyncTimer;
    var clearQuestionCachePersistTimer = deps.clearQuestionCachePersistTimer;
    var clearPendingRevisionSafeAnswerRestoreState = deps.clearPendingRevisionSafeAnswerRestoreState;
    var bumpQuestionDataGeneration = deps.bumpQuestionDataGeneration;
    var clearAttemptUiSyncRuntimeState = deps.clearAttemptUiSyncRuntimeState;
    var clearQuestionRevisionRefreshState = deps.clearQuestionRevisionRefreshState;
    var resetQuestionDataState = deps.resetQuestionDataState;
    var clearPersistedAttemptUiState = deps.clearPersistedAttemptUiState;
    var clearPersistedQuestionCache = deps.clearPersistedQuestionCache;
    var clearPersistedAuthSession = deps.clearPersistedAuthSession;
    var sendLogoutRequestSilently = deps.sendLogoutRequestSilently;
    var queueLoadedQuestionAnswersForFlush = deps.queueLoadedQuestionAnswersForFlush;
    var flushPendingAnswerBatch = deps.flushPendingAnswerBatch;
    var flushAttemptUiState = deps.flushAttemptUiState;
    var handleFinish = deps.handleFinish;
    var logoutSyncTimeoutMs = Math.max(3000, Number(deps.logoutSyncTimeoutMs) || 8000);

    function recordTimelineEntry(kind, summary, meta) {
        if (typeof recordTimeline === 'function') {
            recordTimeline(kind, summary, meta || {});
        }
    }

    function withTimeout(promise, timeoutMs, timeoutMessage) {
        var settled = false;

        return new Promise(function (resolve, reject) {
            var timeoutId = windowRef.setTimeout(function () {
                if (settled) {
                    return;
                }
                settled = true;
                reject(new Error(timeoutMessage));
            }, Math.max(0, Number(timeoutMs) || 0));

            Promise.resolve(promise)
                .then(function (value) {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    windowRef.clearTimeout(timeoutId);
                    resolve(value);
                })
                .catch(function (error) {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    windowRef.clearTimeout(timeoutId);
                    reject(error);
                });
        });
    }

    function startTimer() {
        stopTimer();

        if (state.remainingSeconds <= 0) {
            state.remainingSeconds = 0;
            updateTimerLabel();
            if (!state.isFinishing && !state.examLockedForPendingFinish && state.stage === 'exam') {
                handleFinish(true);
            }
            return;
        }

        state.timerId = windowRef.setInterval(function () {
            if (state.stage !== 'exam') {
                stopTimer();
                return;
            }

            if (state.remainingSeconds <= 0) {
                state.remainingSeconds = 0;
                updateTimerLabel();
                stopTimer();
                if (!state.isFinishing && !state.examLockedForPendingFinish) {
                    handleFinish(true);
                }
                return;
            }

            state.remainingSeconds -= 1;
            updateTimerLabel();
        }, 1000);
    }

    function stopTimer() {
        if (state.timerId) {
            windowRef.clearInterval(state.timerId);
            state.timerId = 0;
        }
    }

    function updateTimerLabel() {
        var timerEl = root.querySelector('[data-cbt-timer]');
        if (!timerEl) {
            return;
        }

        var formattedTime = formatSeconds(state.remainingSeconds);
        timerEl.textContent = formattedTime;
        var timerContainer = timerEl.closest('.cbt-timer-chip');
        if (timerContainer) {
            timerContainer.setAttribute('aria-label', 'Sisa waktu ujian: ' + formattedTime);
        }

        var timerVisualTarget = timerContainer || timerEl;
        if (state.remainingSeconds <= 300) {
            timerVisualTarget.style.background = 'rgba(197, 48, 48, 0.28)';
            timerVisualTarget.style.borderColor = 'rgba(255, 255, 255, 0.42)';
        } else {
            timerVisualTarget.style.background = '';
            timerVisualTarget.style.borderColor = '';
        }
    }

    function resetExamSession() {
        var previousAttemptId = Number(state.attemptId) || 0;
        stopTimer();
        exitFullscreenSilently();
        clearSecurityLoggingRuntimeState();
        clearAutoSaveRuntimeState();
        clearQuestionPrefetchRuntimeState();
        clearAttemptUiStateSyncTimer();
        clearQuestionCachePersistTimer();
        clearPendingRevisionSafeAnswerRestoreState();
        clearQuestionRevisionRefreshState();
        bumpQuestionDataGeneration();
        clearAttemptUiSyncRuntimeState();
        state.examPickerMobileOpen = false;
        state.examToken = '';
        state.attemptId = 0;
        resetQuestionDataState();
        state.remainingSeconds = 0;
        state.isFinishing = false;
        state.result = null;
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        state.userPhotoModalOpen = false;
        state.calculatorVisible = false;
        state.calculatorExpression = '';
        state.calculatorResult = '';
        state.calculatorError = '';
        state.isFullscreenActive = false;
        state.pendingFinishAutoSubmit = false;
        if (previousAttemptId > 0 && state.stage !== 'exam') {
            clearPersistedAttemptUiState(previousAttemptId);
            clearPersistedQuestionCache(previousAttemptId);
        }
    }

    function clearAuthenticatedFrontendState(options) {
        options = options || {};

        var previousAttemptId = Number(state.attemptId) || 0;
        stopTimer();
        stopSessionHeartbeat();
        exitFullscreenSilently();
        clearSecurityLoggingRuntimeState();
        clearAutoSaveRuntimeState();
        clearQuestionPrefetchRuntimeState();
        clearAttemptUiStateSyncTimer();
        clearQuestionCachePersistTimer();
        clearAttemptUiSyncRuntimeState();
        clearPendingRevisionSafeAnswerRestoreState();
        clearQuestionRevisionRefreshState();
        bumpQuestionDataGeneration();
        state.stage = typeof options.stage === 'string' && options.stage !== '' ? options.stage : 'login';
        state.busy = false;
        state.error = typeof options.error === 'string' ? options.error : '';
        state.notice = '';
        state.success = typeof options.success === 'string' ? options.success : '';
        state.loginIdentifier = '';
        state.loginPassword = '';
        state.loginPasswordVisible = false;
        state.token = '';
        state.user = null;
        state.exams = [];
        state.examPickerMobileOpen = false;
        state.selectedExamId = 0;
        state.examToken = '';
        state.attemptId = 0;
        resetQuestionDataState();
        state.remainingSeconds = 0;
        state.timerId = 0;
        state.isFinishing = false;
        state.result = null;
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        state.userPhotoModalOpen = false;
        state.calculatorVisible = false;
        state.calculatorExpression = '';
        state.calculatorResult = '';
        state.calculatorError = '';
        state.isFullscreenActive = false;
        state.pendingFinishAutoSubmit = false;
        if (previousAttemptId > 0) {
            clearPersistedAttemptUiState(previousAttemptId);
            clearPersistedQuestionCache(previousAttemptId);
        }
        clearPersistedAuthSession();
    }

    function expireAuthSession(message) {
        var normalizedMessage = String(message || '').trim();
        if (normalizedMessage === '') {
            normalizedMessage = 'Sesi login berakhir. Silakan login lagi.';
        }

        clearAuthenticatedFrontendState({
            stage: 'login',
            error: normalizedMessage
        });
        render();
    }

    async function fullLogout() {
        var activeToken = String(state.token || '');
        var hasLoadedQuestionOrder = Array.isArray(state.questionOrderIds) && state.questionOrderIds.length > 0;
        var hasLoadedQuestions = Array.isArray(state.questions) && state.questions.length > 0;
        var isOpeningExamShell = state.stage === 'exam' && (
            state.isOpeningAttempt
            || (!hasLoadedQuestionOrder && !hasLoadedQuestions && (Number(state.totalQuestions) || 0) <= 0)
        );
        recordTimelineEntry('logout:start', 'Logout dimulai.', {
            attemptId: Number(state.attemptId) || 0,
            stage: String(state.stage || '')
        });

        if (isOpeningExamShell) {
            sendLogoutRequestSilently(activeToken);
            clearAuthenticatedFrontendState({
                stage: 'login'
            });
            recordTimelineEntry('logout:done', 'Logout selesai dari loading shell.', {
                attemptId: 0,
                stage: 'login'
            });
            render();
            return;
        }

        if (state.stage === 'exam' && state.examLockedForPendingFinish) {
            state.error = 'Logout diblokir sementara jawaban terakhir masih menunggu sinkronisasi/finalisasi.';
            render();
            return;
        }

        if (state.stage === 'exam' && state.attemptId > 0 && !state.isFinishing) {
            state.busy = true;
            clearMessages();
            state.notice = 'Menyimpan jawaban terakhir sebelum logout...';
            render();

            try {
                queueLoadedQuestionAnswersForFlush();
                await withTimeout(
                    flushPendingAnswerBatch({
                        flushAll: true,
                        keepalive: true
                    }),
                    logoutSyncTimeoutMs,
                    'Sinkronisasi jawaban terlalu lama. Coba lagi sebentar.'
                );
                await withTimeout(
                    flushAttemptUiState({
                        force: true,
                        keepalive: true,
                        token: activeToken
                    }),
                    logoutSyncTimeoutMs,
                    'Sinkronisasi status ujian terlalu lama. Coba lagi sebentar.'
                );
            } catch (error) {
                state.busy = false;
                state.notice = '';
                state.error = error instanceof Error
                    ? ('Logout dibatalkan karena jawaban terakhir belum tersimpan: ' + error.message)
                    : 'Logout dibatalkan karena jawaban terakhir belum tersimpan.';
                render();
                return;
            }
        }

        sendLogoutRequestSilently(activeToken);
        clearAuthenticatedFrontendState({
            stage: 'login'
        });
        recordTimelineEntry('logout:done', 'Logout selesai.', {
            attemptId: 0,
            stage: 'login'
        });
        render();
    }

    return {
        applyAttemptTimerPayload: function (timerPayload) {
            if (!timerPayload || typeof timerPayload !== 'object' || state.stage !== 'exam') {
                return;
            }

            var payloadAttemptId = Number(timerPayload.attempt_id) || 0;
            var currentAttemptId = Number(state.attemptId) || 0;
            if (payloadAttemptId <= 0 || currentAttemptId <= 0 || payloadAttemptId !== currentAttemptId) {
                return;
            }

            var nextRemainingSeconds = Math.max(0, Math.floor(Number(timerPayload.remaining_seconds) || 0));
            var currentRemainingSeconds = Math.max(0, Math.floor(Number(state.remainingSeconds) || 0));

            if (nextRemainingSeconds <= 0) {
                if (currentRemainingSeconds !== 0) {
                    state.remainingSeconds = 0;
                    updateTimerLabel();
                }
                if (!state.isFinishing) {
                    stopTimer();
                    handleFinish(true);
                }
                return;
            }

            if (Math.abs(nextRemainingSeconds - currentRemainingSeconds) < 2) {
                return;
            }

            state.remainingSeconds = nextRemainingSeconds;
            updateTimerLabel();
        },
        clearAuthenticatedFrontendState: clearAuthenticatedFrontendState,
        expireAuthSession: expireAuthSession,
        fullLogout: fullLogout,
        resetExamSession: resetExamSession,
        startTimer: startTimer,
        stopTimer: stopTimer,
        updateTimerLabel: updateTimerLabel
    };
}
