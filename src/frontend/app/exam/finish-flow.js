export function createFinishFlowManager(deps) {
    var diagnosticsManager = deps.diagnosticsManager;
    var recordActionTrail = deps.recordActionTrail;
    var recordTimeline = deps.recordTimeline;
    var state = deps.state;
    var apiRequest = deps.apiRequest;
    var clearAllAutoSaveTimers = deps.clearAllAutoSaveTimers;
    var clearAttemptUiStateSyncTimer = deps.clearAttemptUiStateSyncTimer;
    var clearAutoSaveRuntimeState = deps.clearAutoSaveRuntimeState;
    var clearMessages = deps.clearMessages;
    var clearPersistedAttemptUiState = deps.clearPersistedAttemptUiState;
    var clearPersistedQuestionCache = deps.clearPersistedQuestionCache;
    var clearQuestionCachePersistTimer = deps.clearQuestionCachePersistTimer;
    var clearQuestionPrefetchRuntimeState = deps.clearQuestionPrefetchRuntimeState;
    var exitFullscreenSilently = deps.exitFullscreenSilently;
    var flushAttemptUiState = deps.flushAttemptUiState;
    var flushPendingAnswerBatch = deps.flushPendingAnswerBatch;
    var getExamProgressSummary = deps.getExamProgressSummary;
    var getNavigatorConnectionStatus = deps.getNavigatorConnectionStatus;
    var getQuestionAtIndex = deps.getQuestionAtIndex;
    var getQuestionCount = deps.getQuestionCount;
    var handleRecoverableAnswerSyncFailure = deps.handleRecoverableAnswerSyncFailure;
    var hasAnswerBatchFlushInFlight = deps.hasAnswerBatchFlushInFlight;
    var isNetworkConnectivityError = deps.isNetworkConnectivityError;
    var isQuestionAnswered = deps.isQuestionAnswered;
    var isRetryableAnswerSyncError = deps.isRetryableAnswerSyncError;
    var persistCurrentQuestionCacheLocally = deps.persistCurrentQuestionCacheLocally;
    var prefetchResultStageRenderer = deps.prefetchResultStageRenderer;
    var queueQuestionAnswer = deps.queueQuestionAnswer;
    var render = deps.render;
    var schedulePendingAnswerRetry = deps.schedulePendingAnswerRetry;
    var setConnectionStatus = deps.setConnectionStatus;
    var startTimer = deps.startTimer;
    var stopTimer = deps.stopTimer;
    var syncFullscreenState = deps.syncFullscreenState;
    var syncPendingAnswerRuntimeState = deps.syncPendingAnswerRuntimeState;

    function recordTimelineEntry(kind, summary, meta) {
        if (typeof recordTimeline === 'function') {
            recordTimeline(kind, summary, meta || {});
        }
    }

    function recordActionTrailEntry(kind, summary, meta) {
        if (typeof recordActionTrail === 'function') {
            recordActionTrail(kind, summary, meta || {});
        }
    }

    function buildScenarioFinishError(mode) {
        var normalizedMode = String(mode || 'network');
        if (normalizedMode === 'server') {
            var serverError = new Error('Scenario aktif: finish attempt gagal dengan server error.');
            serverError.status = 500;
            serverError.code = 'scenario_finish_server';
            return serverError;
        }

        if (normalizedMode === 'validation') {
            var validationError = new Error('Scenario aktif: finish attempt ditolak validasi.');
            validationError.status = 400;
            validationError.code = 'scenario_finish_validation';
            return validationError;
        }

        var networkError = new Error('Scenario aktif: finish attempt gagal karena simulasi network.');
        networkError.status = 0;
        networkError.code = 'scenario_finish_network';
        networkError.isNetworkError = true;
        return networkError;
    }

    function normalizeKkmPercentage(value) {
        var number = Number(value);
        if (!Number.isFinite(number)) {
            return 75;
        }
        return Math.max(0, Math.min(100, number));
    }

    function buildResultPassMeta(score, maxScore, rawKkm, rawIsPassed, rawPassLabel, rawResultTone) {
        var safeScore = Number.isFinite(Number(score)) ? Number(score) : 0;
        var safeMaxScore = Number.isFinite(Number(maxScore)) ? Math.max(0, Number(maxScore)) : 0;
        var kkmPercentage = normalizeKkmPercentage(rawKkm);
        var passingScore = safeMaxScore > 0 ? ((safeMaxScore * kkmPercentage) / 100) : 0;
        var explicitPassed = Number(rawIsPassed);
        var isPassed = Number.isFinite(explicitPassed)
            ? explicitPassed === 1
            : (safeMaxScore > 0 ? (safeScore + 0.0001 >= passingScore) : kkmPercentage <= 0);

        return {
            kkm_percentage: kkmPercentage,
            passing_score: passingScore,
            is_passed: isPassed ? 1 : 0,
            pass_label: rawPassLabel ? String(rawPassLabel) : (isPassed ? 'LULUS' : 'TIDAK LULUS'),
            result_tone: rawResultTone ? String(rawResultTone) : (isPassed ? 'pass' : 'fail')
        };
    }

    function getSelectedExamFallback() {
        var exams = Array.isArray(state.exams) ? state.exams : [];
        var selectedExamId = Number(state.selectedExamId) || 0;

        for (var index = 0; index < exams.length; index++) {
            var exam = exams[index];
            if (!exam || Number(exam.id) !== selectedExamId) {
                continue;
            }
            return exam;
        }

        return null;
    }

    function openFinishConfirmModal() {
        if (state.stage !== 'exam' || state.isFinishing || state.examLockedForPendingFinish) {
            return;
        }
        state.finishConfirmSummary = getExamProgressSummary();
        state.finishConfirmOpen = true;
        clearMessages();
        recordTimelineEntry('finish:requested', 'Dialog finish dibuka.', {
            attemptId: Number(state.attemptId) || 0,
            stage: String(state.stage || '')
        });
        render();
    }

    function closeFinishConfirmModal() {
        if (!state.finishConfirmOpen) {
            return;
        }
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        render();
    }

    function queueAnsweredQuestionsForFinalSync() {
        for (var index = 0; index < getQuestionCount(); index++) {
            var question = getQuestionAtIndex(index);
            if (!isQuestionAnswered(question)) {
                continue;
            }
            queueQuestionAnswer(question, { force: true });
        }

        syncPendingAnswerRuntimeState({
            persist: true,
            clearLastSyncError: false
        });
    }

    async function buildFinishedResultPayload(finishPayload) {
        var resolvedAttemptId = Number(finishPayload && finishPayload.attempt_id) || Number(state.attemptId) || 0;
        var resultPayload = {
            attempt_id: resolvedAttemptId,
            status: String(finishPayload && finishPayload.status ? finishPayload.status : 'completed'),
            score: Number(finishPayload && finishPayload.score !== undefined ? finishPayload.score : 0),
            max_score: Number(finishPayload && finishPayload.max_score !== undefined ? finishPayload.max_score : 0),
            percentage: Number(finishPayload && finishPayload.percentage !== undefined ? finishPayload.percentage : 0),
            kkm_percentage: Number(finishPayload && finishPayload.kkm_percentage !== undefined ? finishPayload.kkm_percentage : 75),
            passing_score: Number(finishPayload && finishPayload.passing_score !== undefined ? finishPayload.passing_score : 0),
            is_passed: Number(finishPayload && finishPayload.is_passed !== undefined ? finishPayload.is_passed : 0),
            pass_label: String(finishPayload && finishPayload.pass_label ? finishPayload.pass_label : ''),
            result_tone: String(finishPayload && finishPayload.result_tone ? finishPayload.result_tone : ''),
            finished_at: String(finishPayload && finishPayload.finished_at ? finishPayload.finished_at : ''),
            review_items: [],
            review_summary: null
        };

        if (resolvedAttemptId > 0) {
            clearPersistedAttemptUiState(resolvedAttemptId);
            clearPersistedQuestionCache(resolvedAttemptId);
            try {
                var reviewPayload = await apiRequest('result', {
                    query: {
                        attempt_id: resolvedAttemptId
                    }
                });

                if (reviewPayload && typeof reviewPayload === 'object') {
                    resultPayload.attempt = reviewPayload.attempt || null;
                    resultPayload.exam = reviewPayload.exam || null;
                    resultPayload.answers = Array.isArray(reviewPayload.answers) ? reviewPayload.answers : [];
                    resultPayload.review_items = Array.isArray(reviewPayload.review_items) ? reviewPayload.review_items : [];
                    resultPayload.review_summary = reviewPayload.review_summary && typeof reviewPayload.review_summary === 'object'
                        ? reviewPayload.review_summary
                        : null;
                    resultPayload.kkm_percentage = Number(reviewPayload.kkm_percentage !== undefined ? reviewPayload.kkm_percentage : resultPayload.kkm_percentage);
                    resultPayload.passing_score = Number(reviewPayload.passing_score !== undefined ? reviewPayload.passing_score : resultPayload.passing_score);
                    resultPayload.is_passed = Number(reviewPayload.is_passed !== undefined ? reviewPayload.is_passed : resultPayload.is_passed);
                    resultPayload.pass_label = String(reviewPayload.pass_label ? reviewPayload.pass_label : resultPayload.pass_label);
                    resultPayload.result_tone = String(reviewPayload.result_tone ? reviewPayload.result_tone : resultPayload.result_tone);

                    if (
                        resultPayload.attempt &&
                        typeof resultPayload.attempt === 'object' &&
                        Number.isFinite(Number(resultPayload.attempt.score)) &&
                        Number.isFinite(Number(resultPayload.attempt.max_score))
                    ) {
                        resultPayload.score = Number(resultPayload.attempt.score);
                        resultPayload.max_score = Number(resultPayload.attempt.max_score);
                    }
                }
            } catch (reviewError) {
                resultPayload.review_items = [];
                resultPayload.review_summary = null;
            }
        }

        var selectedExam = getSelectedExamFallback();
        var passMeta = buildResultPassMeta(
            resultPayload.score,
            resultPayload.max_score,
            resultPayload.kkm_percentage !== undefined ? resultPayload.kkm_percentage : (selectedExam && selectedExam.kkm_percentage),
            resultPayload.is_passed,
            resultPayload.pass_label,
            resultPayload.result_tone
        );
        resultPayload.kkm_percentage = passMeta.kkm_percentage;
        resultPayload.passing_score = passMeta.passing_score;
        resultPayload.is_passed = passMeta.is_passed;
        resultPayload.pass_label = passMeta.pass_label;
        resultPayload.result_tone = passMeta.result_tone;

        return resultPayload;
    }

    function completeExamWithResult(resultPayload) {
        var autoSubmit = !!state.pendingFinishAutoSubmit;
        state.result = resultPayload;
        state.stage = 'result';
        prefetchResultStageRenderer();
        state.isFinishing = false;
        clearAutoSaveRuntimeState();
        state.pendingFinishAutoSubmit = false;
        exitFullscreenSilently();
        syncFullscreenState(false);
        state.success = autoSubmit ? 'Waktu habis. Ujian otomatis diselesaikan.' : 'Ujian selesai.';
        state.error = '';
        recordTimelineEntry('result:view:ready', 'Result final siap ditampilkan.', {
            attemptId: Number(resultPayload && resultPayload.attempt_id) || 0,
            stage: 'result'
        });
    }

    function shouldUnlockExamAfterFinishFailure() {
        return !state.pendingFinishAutoSubmit && state.remainingSeconds > 0;
    }

    async function finalizeExamAfterSync() {
        if (
            state.stage !== 'exam'
            || state.attemptId <= 0
            || state.isFinishing
            || !state.examLockedForPendingFinish
            || state.pendingSyncCount > 0
            || hasAnswerBatchFlushInFlight()
            || getNavigatorConnectionStatus() === 'offline'
        ) {
            return;
        }

        var autoSubmit = !!state.pendingFinishAutoSubmit;
        state.isFinishing = true;
        clearQuestionPrefetchRuntimeState();
        clearAttemptUiStateSyncTimer();
        clearQuestionCachePersistTimer();
        clearMessages();
        recordTimelineEntry('finish:submit:start', 'Finalisasi ujian dimulai.', {
            attemptId: Number(state.attemptId) || 0,
            stage: String(state.stage || '')
        });
        render();

        try {
            stopTimer();
            try {
                await flushAttemptUiState({
                    force: true,
                    allowWhileFinishing: true
                });
            } catch (error) {
                // Local fallback tetap tersedia.
            }

            var scenarioFinishMode = (
                diagnosticsManager
                && diagnosticsManager.enabled
                && typeof diagnosticsManager.consumeFailFinishOnce === 'function'
            )
                ? String(diagnosticsManager.consumeFailFinishOnce() || '')
                : '';
            if (scenarioFinishMode !== '') {
                var finishScenarioError = buildScenarioFinishError(scenarioFinishMode);
                recordTimelineEntry('finish:submit:error', finishScenarioError.message, {
                    attemptId: Number(state.attemptId) || 0,
                    stage: String(state.stage || ''),
                    code: finishScenarioError.code,
                    scenario: scenarioFinishMode
                });
                recordActionTrailEntry('finish:scenario-failed', 'Finish digagalkan oleh scenario.', {
                    mode: scenarioFinishMode,
                    code: finishScenarioError.code
                });
                throw finishScenarioError;
            }

            var finishPayload = await apiRequest('finish_exam', {
                method: 'POST',
                body: {
                    attempt_id: state.attemptId
                }
            });
            var resultPayload = await buildFinishedResultPayload(finishPayload);
            completeExamWithResult(resultPayload);
            state.success = autoSubmit ? 'Waktu habis. Ujian otomatis diselesaikan.' : 'Ujian selesai.';
            recordTimelineEntry('finish:submit:success', 'Finalisasi ujian berhasil.', {
                attemptId: Number(state.attemptId) || 0,
                stage: 'result'
            });
        } catch (error) {
            state.isFinishing = false;

            if (isNetworkConnectivityError(error)) {
                state.lastSyncError = error instanceof Error && error.message ? error.message : 'Koneksi terputus.';
                setConnectionStatus('offline', {
                    persist: false,
                    render: false,
                    triggerRetry: false
                });
                syncPendingAnswerRuntimeState({
                    persist: true,
                    clearLastSyncError: false
                });
                schedulePendingAnswerRetry('finish-request-retry', {
                    immediate: false
                });
                recordTimelineEntry('finish:submit:error', state.lastSyncError, {
                    attemptId: Number(state.attemptId) || 0,
                    stage: String(state.stage || ''),
                    code: error && error.code ? String(error.code) : ''
                });
            } else if (error && error.code === 'attempt_closed') {
                try {
                    var recoveredResultPayload = await buildFinishedResultPayload({
                        attempt_id: state.attemptId,
                        status: 'completed'
                    });
                    completeExamWithResult(recoveredResultPayload);
                    state.success = autoSubmit ? 'Waktu habis. Ujian otomatis diselesaikan.' : 'Ujian selesai.';
                    recordTimelineEntry('finish:submit:success', 'Attempt sudah closed; hasil berhasil dipulihkan.', {
                        attemptId: Number(state.attemptId) || 0,
                        stage: 'result'
                    });
                } catch (resultError) {
                    state.error = resultError instanceof Error ? resultError.message : 'Ujian sudah selesai, tetapi hasil tidak bisa dimuat.';
                    recordTimelineEntry('finish:submit:error', state.error, {
                        attemptId: Number(state.attemptId) || 0,
                        stage: String(state.stage || '')
                    });
                }
            } else {
                state.lastSyncError = error instanceof Error && error.message ? error.message : 'Gagal menyelesaikan ujian.';
                state.error = error instanceof Error ? error.message : 'Gagal menyelesaikan ujian.';
                if (shouldUnlockExamAfterFinishFailure()) {
                    state.examLockedForPendingFinish = false;
                    state.pendingFinishAutoSubmit = false;
                    if (state.stage === 'exam') {
                        startTimer();
                    }
                }
                syncPendingAnswerRuntimeState({
                    persist: true,
                    clearLastSyncError: false
                });
                recordTimelineEntry('finish:submit:error', state.error || state.lastSyncError || 'Gagal menyelesaikan ujian.', {
                    attemptId: Number(state.attemptId) || 0,
                    stage: String(state.stage || '')
                });
            }
        } finally {
            render();
        }
    }

    function maybeFinalizeLockedExam(reason) {
        if (
            state.stage !== 'exam'
            || state.attemptId <= 0
            || !state.examLockedForPendingFinish
            || state.isFinishing
            || state.pendingSyncCount > 0
            || hasAnswerBatchFlushInFlight()
            || getNavigatorConnectionStatus() === 'offline'
        ) {
            return;
        }

        finalizeExamAfterSync().catch(function () {
            // Error ditangani di finalizeExamAfterSync.
        });
    }

    async function handleFinish(autoSubmit, options) {
        options = options || {};
        var skipConfirmation = !!options.skipConfirmation;

        if (state.isFinishing) {
            return;
        }

        if (!autoSubmit && !skipConfirmation && !state.examLockedForPendingFinish) {
            openFinishConfirmModal();
            return;
        }

        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        state.examLockedForPendingFinish = true;
        state.pendingFinishAutoSubmit = !!autoSubmit;
        recordTimelineEntry('finish:requested', autoSubmit ? 'Auto-finish dipicu.' : 'User mengonfirmasi finish ujian.', {
            attemptId: Number(state.attemptId) || 0,
            stage: String(state.stage || ''),
            autoSubmit: !!autoSubmit
        });
        clearAllAutoSaveTimers();
        clearQuestionPrefetchRuntimeState();
        clearAttemptUiStateSyncTimer();
        clearQuestionCachePersistTimer();
        clearMessages();
        stopTimer();
        queueAnsweredQuestionsForFinalSync();
        persistCurrentQuestionCacheLocally();
        render();

        try {
            try {
                await flushAttemptUiState({
                    force: true,
                    allowWhileFinishing: true
                });
            } catch (error) {
                // Local fallback tetap tersedia.
            }

            if (getNavigatorConnectionStatus() === 'offline') {
                syncPendingAnswerRuntimeState({
                    persist: true,
                    clearLastSyncError: false
                });
                render();
                return;
            }

            if (state.pendingSyncCount > 0) {
                try {
                    await flushPendingAnswerBatch({ flushAll: true });
                } catch (error) {
                    if (isRetryableAnswerSyncError(error)) {
                        handleRecoverableAnswerSyncFailure(error, {
                            reason: 'finish-flush-retry',
                            render: true
                        });
                    } else {
                        state.lastSyncError = error instanceof Error && error.message ? error.message : 'Sinkronisasi jawaban gagal.';
                        state.error = error instanceof Error ? error.message : 'Sinkronisasi jawaban gagal.';
                        if (shouldUnlockExamAfterFinishFailure()) {
                            state.examLockedForPendingFinish = false;
                            state.pendingFinishAutoSubmit = false;
                            if (state.stage === 'exam') {
                                startTimer();
                            }
                        }
                        syncPendingAnswerRuntimeState({
                            persist: true,
                            clearLastSyncError: false
                        });
                        render();
                    }
                    return;
                }
            }

            maybeFinalizeLockedExam('finish-request');
        } catch (error) {
            state.error = error instanceof Error ? error.message : 'Gagal menyelesaikan ujian.';
            if (shouldUnlockExamAfterFinishFailure() && state.stage === 'exam') {
                state.examLockedForPendingFinish = false;
                state.pendingFinishAutoSubmit = false;
                startTimer();
            }
        } finally {
            render();
        }
    }

    return {
        closeFinishConfirmModal: closeFinishConfirmModal,
        handleFinish: handleFinish,
        maybeFinalizeLockedExam: maybeFinalizeLockedExam,
        openFinishConfirmModal: openFinishConfirmModal
    };
}
