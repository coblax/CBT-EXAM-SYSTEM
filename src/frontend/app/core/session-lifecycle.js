export function createSessionLifecycleManager(deps) {
    var AUTH_PROGRESS_STEP_TOTAL = 4;
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

    function resetAuthProgressState() {
        state.authProgressVisible = false;
        state.authProgressMode = '';
        state.authProgressPercent = 0;
        state.authProgressStepIndex = 0;
        state.authProgressStepTotal = 0;
        state.authProgressStatus = '';
        state.authProgressDetail = '';
    }

    function updateAuthProgress(percent, stepIndex, status, detail, options) {
        var safePercent = Number(percent);
        var safeStepIndex = Number(stepIndex);
        var shouldRender = !(options && options.render === false);

        if (!Number.isFinite(safePercent)) {
            safePercent = 0;
        }
        if (!Number.isFinite(safeStepIndex)) {
            safeStepIndex = 0;
        }

        state.authProgressVisible = true;
        state.authProgressMode = 'logout';
        state.authProgressPercent = Math.max(0, Math.min(100, safePercent));
        state.authProgressStepIndex = Math.max(0, Math.min(AUTH_PROGRESS_STEP_TOTAL, safeStepIndex));
        state.authProgressStepTotal = AUTH_PROGRESS_STEP_TOTAL;
        state.authProgressStatus = String(status || '');
        state.authProgressDetail = String(detail || '');

        if (shouldRender && typeof render === 'function') {
            render('auth-progress-logout', {
                attemptId: Number(state.attemptId) || 0,
                percent: state.authProgressPercent,
                stepIndex: state.authProgressStepIndex
            });
        }
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
        state.finishProgressPercent = 0;
        state.finishProgressStepIndex = 0;
        state.finishProgressStepTotal = 0;
        state.finishProgressStatus = '';
        state.finishProgressDetail = '';
        resetAuthProgressState();
        state.result = null;
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        state.richZoomModalOpen = false;
        state.richZoomModalType = '';
        state.richZoomModalTitle = '';
        state.richZoomModalMarkup = '';
        state.richZoomModalGalleryId = '';
        state.richZoomModalGalleryIndex = 0;
        state.richZoomModalGalleryItems = [];
        state.richZoomModalGalleryCount = 0;
        state.richZoomModalScaleMode = 'fit';
        state.richZoomModalScalePercent = 100;
        state.userPhotoModalOpen = false;
        state.calculatorVisible = false;
        state.calculatorExpression = '';
        state.calculatorResult = '';
        state.calculatorError = '';
        state.isFullscreenActive = false;
        state.pendingFinishAutoSubmit = false;
        state.heartbeatLostActive = false;
        state.heartbeatLostFailureCount = 0;
        state.heartbeatLostLastErrorCode = '';
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
        state.finishProgressPercent = 0;
        state.finishProgressStepIndex = 0;
        state.finishProgressStepTotal = 0;
        state.finishProgressStatus = '';
        state.finishProgressDetail = '';
        resetAuthProgressState();
        state.result = null;
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        state.richZoomModalOpen = false;
        state.richZoomModalType = '';
        state.richZoomModalTitle = '';
        state.richZoomModalMarkup = '';
        state.richZoomModalGalleryId = '';
        state.richZoomModalGalleryIndex = 0;
        state.richZoomModalGalleryItems = [];
        state.richZoomModalGalleryCount = 0;
        state.richZoomModalScaleMode = 'fit';
        state.richZoomModalScalePercent = 100;
        state.userPhotoModalOpen = false;
        state.calculatorVisible = false;
        state.calculatorExpression = '';
        state.calculatorResult = '';
        state.calculatorError = '';
        state.isFullscreenActive = false;
        state.pendingFinishAutoSubmit = false;
        state.heartbeatLostActive = false;
        state.heartbeatLostFailureCount = 0;
        state.heartbeatLostLastErrorCode = '';
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
        state.busy = true;
        clearMessages();
        updateAuthProgress(
            14,
            1,
            'Menutup sesi',
            'Kami sedang menutup sesi Anda dengan aman.'
        );

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
            state.busy = false;
            resetAuthProgressState();
            state.error = 'Logout diblokir sementara jawaban terakhir masih menunggu sinkronisasi/finalisasi.';
            render();
            return;
        }

        if (state.stage === 'exam' && state.attemptId > 0 && !state.isFinishing) {
            state.busy = true;
            clearMessages();
            state.notice = 'Menyimpan jawaban terakhir sebelum logout...';
            updateAuthProgress(
                34,
                2,
                'Menyimpan jawaban terakhir',
                'Jawaban yang belum tersinkron sedang dikirim ke server.'
            );
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
                updateAuthProgress(
                    58,
                    3,
                    'Menyinkronkan status ujian',
                    'Status attempt terakhir sedang disimpan agar sesi tetap konsisten.'
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
                resetAuthProgressState();
                state.error = error instanceof Error
                    ? ('Logout dibatalkan karena jawaban terakhir belum tersimpan: ' + error.message)
                    : 'Logout dibatalkan karena jawaban terakhir belum tersimpan.';
                render();
                return;
            }
        }

        updateAuthProgress(
            82,
            4,
            'Menutup sesi server',
            'Token login sedang dinonaktifkan dan sesi lokal akan dibersihkan.'
        );
        try {
            await withTimeout(
                Promise.resolve(sendLogoutRequestSilently(activeToken)).catch(function () {
                    return null;
                }),
                logoutSyncTimeoutMs,
                'Logout server terlalu lama. Sesi lokal dibersihkan, tetapi sesi server mungkin belum tertutup sempurna.'
            );
        } catch (error) {
            // Tetap lanjutkan clear state lokal agar UI tidak tersangkut.
        }
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
