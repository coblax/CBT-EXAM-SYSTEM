export function createFinishFlowManager(deps) {
    var FINISH_PROGRESS_STEP_TOTAL = 4;
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
    var windowRef = deps.windowRef || (typeof window !== 'undefined' ? window : globalThis);
    var ensureResultStageRenderer = typeof deps.ensureResultStageRenderer === 'function'
        ? deps.ensureResultStageRenderer
        : function () {
            return Promise.resolve(null);
        };
    var prefetchResultStageRenderer = deps.prefetchResultStageRenderer;
    var queueQuestionAnswer = deps.queueQuestionAnswer;
    var render = deps.render;
    var schedulePendingAnswerRetry = deps.schedulePendingAnswerRetry;
    var setConnectionStatus = deps.setConnectionStatus;
    var startTimer = deps.startTimer;
    var stopTimer = deps.stopTimer;
    var syncFullscreenState = deps.syncFullscreenState;
    var syncPendingAnswerRuntimeState = deps.syncPendingAnswerRuntimeState;
    var finishRecoveryRetryTimerId = 0;
    var finishRecoveryRetryAttempt = 0;
    var FINISH_RECOVERY_RETRY_DELAYS_MS = [5000, 15000, 30000];

    function resetFinishProgressState() {
        state.finishProgressPercent = 0;
        state.finishProgressStepIndex = 0;
        state.finishProgressStepTotal = 0;
        state.finishProgressStatus = '';
        state.finishProgressDetail = '';
    }

    function updateFinishProgress(percent, stepIndex, status, detail, options) {
        var safePercent = Number(percent);
        var safeStepIndex = Number(stepIndex);
        var shouldRender = !(options && options.render === false);
        var renderOptions = options && options.renderOptions && typeof options.renderOptions === 'object'
            ? options.renderOptions
            : null;

        if (!Number.isFinite(safePercent)) {
            safePercent = 0;
        }
        if (!Number.isFinite(safeStepIndex)) {
            safeStepIndex = 0;
        }

        state.finishProgressPercent = Math.max(0, Math.min(100, safePercent));
        state.finishProgressStepIndex = Math.max(0, Math.min(FINISH_PROGRESS_STEP_TOTAL, safeStepIndex));
        state.finishProgressStepTotal = FINISH_PROGRESS_STEP_TOTAL;
        state.finishProgressStatus = String(status || '');
        state.finishProgressDetail = String(detail || '');

        if (shouldRender && typeof render === 'function') {
            render('finish-progress', {
                attemptId: Number(state.attemptId) || 0,
                percent: state.finishProgressPercent,
                stepIndex: state.finishProgressStepIndex
            }, renderOptions);
        }
    }

    function waitForInteractiveFinishPaint() {
        if (!windowRef) {
            return Promise.resolve();
        }

        if (windowRef.document && windowRef.document.visibilityState === 'hidden') {
            return new Promise(function (resolve) {
                if (typeof windowRef.setTimeout === 'function') {
                    windowRef.setTimeout(resolve, 0);
                    return;
                }
                resolve();
            });
        }

        if (typeof windowRef.requestAnimationFrame !== 'function') {
            return new Promise(function (resolve) {
                if (typeof windowRef.setTimeout === 'function') {
                    windowRef.setTimeout(resolve, 0);
                    return;
                }
                resolve();
            });
        }

        return new Promise(function (resolve) {
            windowRef.requestAnimationFrame(function () {
                windowRef.requestAnimationFrame(function () {
                    resolve();
                });
            });
        });
    }

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

    function clearFinishRecoveryRetryTimer() {
        if (finishRecoveryRetryTimerId && windowRef && typeof windowRef.clearTimeout === 'function') {
            windowRef.clearTimeout(finishRecoveryRetryTimerId);
        }
        finishRecoveryRetryTimerId = 0;
    }

    function resetFinishRecoveryReceiptState() {
        clearFinishRecoveryRetryTimer();
        finishRecoveryRetryAttempt = 0;
        state.finishReceipt = null;
        state.finishResultPending = false;
        state.finishRecoveryLastError = '';
    }

    function getNormalizedFinishReceipt(receipt) {
        var safeReceipt = receipt && typeof receipt === 'object' ? receipt : {};
        var attemptId = Number(
            safeReceipt.attempt_id !== undefined
                ? safeReceipt.attempt_id
                : safeReceipt.attemptId
        ) || 0;
        var examId = Number(
            safeReceipt.exam_id !== undefined
                ? safeReceipt.exam_id
                : safeReceipt.examId
        ) || 0;
        var status = String(safeReceipt.status || '').trim().toLowerCase();
        if (attemptId <= 0 || examId <= 0 || status !== 'completed') {
            return null;
        }

        return {
            attempt_id: attemptId,
            exam_id: examId,
            finished_at: String(
                safeReceipt.finished_at !== undefined
                    ? safeReceipt.finished_at
                    : (safeReceipt.finishedAt || '')
            ),
            status: 'completed',
            result_view_mode_hint: String(
                safeReceipt.result_view_mode_hint !== undefined
                    ? safeReceipt.result_view_mode_hint
                    : (safeReceipt.resultViewModeHint || '')
            ),
            show_student_result_hint: Number(
                safeReceipt.show_student_result_hint !== undefined
                    ? safeReceipt.show_student_result_hint
                    : safeReceipt.showStudentResultHint
            ) === 1 ? 1 : 0,
            ack_source: String(
                safeReceipt.ack_source !== undefined
                    ? safeReceipt.ack_source
                    : (safeReceipt.ackSource || '')
            ),
            pending_result_fetch: Number(
                safeReceipt.pending_result_fetch !== undefined
                    ? safeReceipt.pending_result_fetch
                    : safeReceipt.pendingResultFetch
            ) === 1 ? 1 : 0,
            updated_at: Math.max(
                0,
                Number(
                    safeReceipt.updated_at !== undefined
                        ? safeReceipt.updated_at
                        : safeReceipt.updatedAt
                ) || 0
            )
        };
    }

    function buildFinishReceiptFromPayload(finishPayload, ackSource) {
        var attemptId = Number(finishPayload && finishPayload.attempt_id) || Number(state.attemptId) || 0;
        var examId = Number(
            finishPayload && finishPayload.exam && finishPayload.exam.id !== undefined
                ? finishPayload.exam.id
                : state.selectedExamId
        ) || 0;
        if (attemptId <= 0 || examId <= 0) {
            return null;
        }

        return getNormalizedFinishReceipt({
            attempt_id: attemptId,
            exam_id: examId,
            finished_at: String(finishPayload && finishPayload.finished_at ? finishPayload.finished_at : ''),
            status: 'completed',
            result_view_mode_hint: String(finishPayload && finishPayload.result_view_mode ? finishPayload.result_view_mode : ''),
            show_student_result_hint: Number(finishPayload && finishPayload.show_student_result !== undefined ? finishPayload.show_student_result : 1) === 1 ? 1 : 0,
            ack_source: String(ackSource || 'finish_exam'),
            pending_result_fetch: 1,
            updated_at: Date.now()
        });
    }

    function persistFinishReceipt(receipt) {
        var normalizedReceipt = getNormalizedFinishReceipt(receipt);
        if (!normalizedReceipt) {
            return null;
        }

        clearFinishRecoveryRetryTimer();
        finishRecoveryRetryAttempt = 0;
        state.finishReceipt = normalizedReceipt;
        state.finishResultPending = true;
        state.finishRecoveryLastError = '';
        persistCurrentQuestionCacheLocally();
        return normalizedReceipt;
    }

    function updateFinishRecoveryWaitingStatus(status, detail, options) {
        updateFinishProgress(
            90,
            4,
            String(status || 'Memuat hasil ujian'),
            String(detail || ''),
            options || null
        );
    }

    function shouldAllowFallbackWithoutReview(finishPayload, finishReceipt) {
        var payload = finishPayload && typeof finishPayload === 'object' ? finishPayload : {};
        var receipt = finishReceipt && typeof finishReceipt === 'object' ? finishReceipt : {};
        var showStudentResult = Number(
            payload.show_student_result !== undefined
                ? payload.show_student_result
                : receipt.show_student_result_hint
        ) === 1;
        var resultViewMode = String(
            payload.result_view_mode !== undefined
                ? payload.result_view_mode
                : receipt.result_view_mode_hint
        ).toLowerCase();

        return !showStudentResult || resultViewMode === 'restricted';
    }

    function scheduleFinishResultRecoveryRetry(reason) {
        if (
            !state.finishResultPending
            || !getNormalizedFinishReceipt(state.finishReceipt)
            || getNavigatorConnectionStatus() === 'offline'
            || finishRecoveryRetryTimerId
            || !windowRef
            || typeof windowRef.setTimeout !== 'function'
        ) {
            return;
        }

        var delay = FINISH_RECOVERY_RETRY_DELAYS_MS[Math.min(
            finishRecoveryRetryAttempt,
            FINISH_RECOVERY_RETRY_DELAYS_MS.length - 1
        )];
        finishRecoveryRetryAttempt += 1;
        finishRecoveryRetryTimerId = windowRef.setTimeout(function () {
            finishRecoveryRetryTimerId = 0;
            recordTimelineEntry('finish_recovery_retry', 'Retry pemulihan hasil dijalankan otomatis.', {
                attemptId: Number(state.attemptId) || 0,
                delayMs: delay,
                reason: String(reason || '')
            });
            maybeFinalizeLockedExam(reason || 'finish-result-recovery-retry');
        }, delay);
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

    function syncCompletedExamIntoList(resultPayload) {
        var exams = Array.isArray(state.exams) ? state.exams : [];
        if (!exams.length || !resultPayload || typeof resultPayload !== 'object') {
            return;
        }

        var attempt = resultPayload.attempt && typeof resultPayload.attempt === 'object'
            ? resultPayload.attempt
            : null;
        var examPayload = resultPayload.exam && typeof resultPayload.exam === 'object'
            ? resultPayload.exam
            : null;
        var examId = Number(
            examPayload && examPayload.id !== undefined
                ? examPayload.id
                : (attempt && attempt.exam_id !== undefined ? attempt.exam_id : state.selectedExamId)
        ) || 0;
        if (examId <= 0) {
            return;
        }

        var examIndex = -1;
        for (var index = 0; index < exams.length; index++) {
            if (Number(exams[index] && exams[index].id) === examId) {
                examIndex = index;
                break;
            }
        }
        if (examIndex < 0) {
            return;
        }

        var currentExam = exams[examIndex] && typeof exams[examIndex] === 'object' ? exams[examIndex] : {};
        var showStudentResult = Number(
            resultPayload.show_student_result !== undefined
                ? resultPayload.show_student_result
                : (currentExam.show_student_result !== undefined ? currentExam.show_student_result : 1)
        ) === 1;
        var safeScore = showStudentResult && Number.isFinite(Number(resultPayload.score))
            ? Number(resultPayload.score)
            : 0;
        var safeMaxScore = showStudentResult && Number.isFinite(Number(resultPayload.max_score))
            ? Number(resultPayload.max_score)
            : 0;
        var percentage = showStudentResult && safeMaxScore > 0
            ? ((safeScore / safeMaxScore) * 100)
            : 0;
        var passMeta = showStudentResult
            ? buildResultPassMeta(
                safeScore,
                safeMaxScore,
                resultPayload.kkm_percentage !== undefined
                    ? resultPayload.kkm_percentage
                    : currentExam.kkm_percentage,
                resultPayload.is_passed,
                resultPayload.pass_label,
                resultPayload.result_tone
            )
            : {
                passing_score: 0,
                is_passed: 0,
                pass_label: '',
                result_tone: ''
            };

        var updatedExam = Object.assign({}, currentExam, examPayload || {}, {
            show_student_result: showStudentResult ? 1 : 0,
            latest_attempt_id: Number(resultPayload.attempt_id || (attempt && attempt.id) || currentExam.latest_attempt_id || 0),
            latest_attempt_status: String((attempt && attempt.status) || resultPayload.status || 'completed'),
            latest_attempt_score: safeScore,
            latest_attempt_max_score: safeMaxScore,
            latest_attempt_percentage: Number.isFinite(percentage) ? percentage : 0,
            latest_attempt_passing_score: showStudentResult ? passMeta.passing_score : 0,
            latest_attempt_is_passed: showStudentResult ? passMeta.is_passed : 0,
            latest_attempt_pass_label: showStudentResult ? passMeta.pass_label : '',
            latest_attempt_result_tone: showStudentResult ? passMeta.result_tone : '',
            latest_attempt_started_at: String((attempt && attempt.started_at) || currentExam.latest_attempt_started_at || ''),
            latest_attempt_finished_at: String(resultPayload.finished_at || (attempt && attempt.finished_at) || currentExam.latest_attempt_finished_at || '')
        });

        state.exams = exams.slice();
        state.exams[examIndex] = updatedExam;
    }

    function openFinishConfirmModal() {
        if (state.stage !== 'exam' || state.isFinishing || state.examLockedForPendingFinish) {
            return;
        }
        prefetchResultStageRenderer();
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

    async function buildFinishedResultPayload(finishPayload, options) {
        options = options || {};
        var resolvedAttemptId = Number(finishPayload && finishPayload.attempt_id) || Number(state.attemptId) || 0;
        var resultPayload = {
            attempt_id: resolvedAttemptId,
            status: String(finishPayload && finishPayload.status ? finishPayload.status : 'completed'),
            show_student_result: Number(finishPayload && finishPayload.show_student_result !== undefined ? finishPayload.show_student_result : 1),
            result_view_mode: String(finishPayload && finishPayload.result_view_mode ? finishPayload.result_view_mode : 'full'),
            submission_summary: finishPayload && finishPayload.submission_summary && typeof finishPayload.submission_summary === 'object'
                ? finishPayload.submission_summary
                : null,
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
            try {
                var reviewPayload = await apiRequest('result', {
                    query: {
                        attempt_id: resolvedAttemptId
                    }
                });

                if (reviewPayload && typeof reviewPayload === 'object') {
                    resultPayload.attempt = reviewPayload.attempt || null;
                    resultPayload.exam = reviewPayload.exam || null;
                    resultPayload.show_student_result = Number(reviewPayload.show_student_result !== undefined ? reviewPayload.show_student_result : resultPayload.show_student_result);
                    resultPayload.result_view_mode = String(reviewPayload.result_view_mode ? reviewPayload.result_view_mode : resultPayload.result_view_mode);
                    resultPayload.submission_summary = reviewPayload.submission_summary && typeof reviewPayload.submission_summary === 'object'
                        ? reviewPayload.submission_summary
                        : resultPayload.submission_summary;
                    resultPayload.answers = Array.isArray(reviewPayload.answers) ? reviewPayload.answers : [];
                    resultPayload.review_items = Array.isArray(reviewPayload.review_items) ? reviewPayload.review_items : [];
                    resultPayload.review_summary = reviewPayload.review_summary && typeof reviewPayload.review_summary === 'object'
                        ? reviewPayload.review_summary
                        : null;
                    resultPayload.percentage = Number(reviewPayload.percentage !== undefined ? reviewPayload.percentage : resultPayload.percentage);
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
                if (!options.allowFallbackWithoutReview) {
                    throw reviewError;
                }
                resultPayload.review_items = [];
                resultPayload.review_summary = null;
            }
        }

        var isRestrictedResult = Number(resultPayload.show_student_result !== undefined ? resultPayload.show_student_result : 1) !== 1
            || String(resultPayload.result_view_mode || '').toLowerCase() === 'restricted';
        if (isRestrictedResult) {
            resultPayload.score = 0;
            resultPayload.max_score = 0;
            resultPayload.percentage = 0;
            resultPayload.kkm_percentage = 0;
            resultPayload.passing_score = 0;
            resultPayload.is_passed = 0;
            resultPayload.pass_label = '';
            resultPayload.result_tone = '';
            resultPayload.answers = [];
            resultPayload.review_items = [];
            resultPayload.review_summary = null;
            return resultPayload;
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
        var completedAttemptId = Number(resultPayload && resultPayload.attempt_id) || Number(state.attemptId) || 0;
        syncCompletedExamIntoList(resultPayload);
        state.result = resultPayload;
        state.stage = 'result';
        prefetchResultStageRenderer();
        state.isFinishing = false;
        resetFinishProgressState();
        clearAutoSaveRuntimeState();
        state.pendingFinishAutoSubmit = false;
        resetFinishRecoveryReceiptState();
        exitFullscreenSilently();
        syncFullscreenState(false);
        if (completedAttemptId > 0) {
            clearPersistedAttemptUiState(completedAttemptId);
            clearPersistedQuestionCache(completedAttemptId);
        }
        state.success = autoSubmit ? 'Waktu habis. Ujian otomatis diselesaikan.' : 'Ujian selesai.';
        state.error = '';
        recordTimelineEntry('result:view:ready', 'Result final siap ditampilkan.', {
            attemptId: Number(resultPayload && resultPayload.attempt_id) || 0,
            stage: 'result'
        });
    }

    async function prepareResultStageForFinishTransition() {
        prefetchResultStageRenderer();

        try {
            await ensureResultStageRenderer({
                renderOnResolve: false
            });
        } catch (error) {
            // Keep the finish path successful; result fallback shell can render if the chunk still fails.
        }
    }

    function shouldUnlockExamAfterFinishFailure() {
        return !state.pendingFinishAutoSubmit && state.remainingSeconds > 0;
    }

    function unlockExamAfterFinishFailure() {
        state.examLockedForPendingFinish = false;
        state.pendingFinishAutoSubmit = false;
        resetFinishProgressState();
        resetFinishRecoveryReceiptState();
        syncPendingAnswerRuntimeState({
            persist: false,
            clearLastSyncError: false
        });
        persistCurrentQuestionCacheLocally();
        if (state.stage === 'exam') {
            startTimer();
        }
    }

    async function recoverFinishedResultFromReceipt(reason, options) {
        options = options || {};
        var finishReceipt = getNormalizedFinishReceipt(options.finishReceipt || state.finishReceipt);
        if (!finishReceipt) {
            return null;
        }

        state.finishReceipt = finishReceipt;
        state.finishResultPending = true;
        state.examLockedForPendingFinish = true;

        if (getNavigatorConnectionStatus() === 'offline') {
            state.isFinishing = false;
            updateFinishRecoveryWaitingStatus(
                'Menunggu koneksi untuk memulihkan hasil',
                'Ujian sudah selesai di server. Nilai/review akan dipulihkan setelah koneksi kembali online.'
            );
            render();
            return null;
        }

        clearFinishRecoveryRetryTimer();
        state.isFinishing = true;
        state.finishRecoveryLastError = '';
        updateFinishRecoveryWaitingStatus(
            'Memuat hasil ujian',
            'Finalisasi diterima server. Kami sedang memulihkan hasil terbaru ujian Anda.'
        );
        clearMessages();
        recordTimelineEntry('finish_recovery_started', 'Pemulihan hasil ujian dimulai.', {
            attemptId: finishReceipt.attempt_id,
            reason: String(reason || ''),
            ackSource: String(finishReceipt.ack_source || '')
        });
        render();

        try {
            var finishPayload = options.finishPayload && typeof options.finishPayload === 'object'
                ? options.finishPayload
                : {
                    attempt_id: finishReceipt.attempt_id,
                    finished_at: finishReceipt.finished_at,
                    status: 'completed',
                    result_view_mode: finishReceipt.result_view_mode_hint,
                    show_student_result: finishReceipt.show_student_result_hint
                };
            var resultPayload = await buildFinishedResultPayload(finishPayload, {
                allowFallbackWithoutReview: shouldAllowFallbackWithoutReview(finishPayload, finishReceipt)
            });
            await prepareResultStageForFinishTransition();
            completeExamWithResult(resultPayload);
            render();
            recordTimelineEntry('finish_result_ready', 'Hasil ujian berhasil dipulihkan.', {
                attemptId: finishReceipt.attempt_id,
                stage: 'result'
            });
            return resultPayload;
        } catch (error) {
            state.isFinishing = false;
            state.finishResultPending = true;
            state.finishRecoveryLastError = error instanceof Error && error.message
                ? error.message
                : 'Hasil ujian belum bisa dipulihkan.';
            state.error = '';
            state.lastSyncError = state.finishRecoveryLastError;

            if (isNetworkConnectivityError(error)) {
                updateFinishRecoveryWaitingStatus(
                    'Menunggu koneksi untuk memulihkan hasil',
                    'Finalisasi diterima server, tetapi jaringan terputus saat memuat hasil. Sistem akan mencoba lagi otomatis.'
                );
                setConnectionStatus('offline', {
                    persist: false,
                    render: false,
                    triggerRetry: false
                });
            } else {
                updateFinishRecoveryWaitingStatus(
                    'Finalisasi diterima server',
                    'Ujian sudah selesai di server. Nilai/review sedang dipulihkan otomatis.'
                );
                scheduleFinishResultRecoveryRetry(reason || 'finish-result-recovery');
            }

            recordTimelineEntry('finish_result_recovery_failed', state.finishRecoveryLastError, {
                attemptId: finishReceipt.attempt_id,
                stage: String(state.stage || ''),
                code: error && error.code ? String(error.code) : ''
            });
            render();
            return null;
        }
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
        updateFinishProgress(
            72,
            3,
            'Mengirim finalisasi ujian',
            'Request final sedang dikirim ke server dan waktu ujian sudah dihentikan.'
        );
        clearQuestionPrefetchRuntimeState();
        clearAttemptUiStateSyncTimer();
        clearQuestionCachePersistTimer();
        clearMessages();
        prefetchResultStageRenderer();
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
            var finishReceipt = persistFinishReceipt(buildFinishReceiptFromPayload(finishPayload, 'finish_exam'));
            recordTimelineEntry('finish_acknowledged', 'Finalisasi ujian diakui server.', {
                attemptId: Number(finishPayload && finishPayload.attempt_id) || Number(state.attemptId) || 0,
                ackSource: 'finish_exam'
            });
            var recoveredResult = await recoverFinishedResultFromReceipt('finish-acknowledged', {
                finishPayload: finishPayload,
                finishReceipt: finishReceipt
            });
            if (!recoveredResult || state.stage !== 'result') {
                return;
            }
            state.success = autoSubmit ? 'Waktu habis. Ujian otomatis diselesaikan.' : 'Ujian selesai.';
            recordTimelineEntry('finish:submit:success', 'Finalisasi ujian berhasil.', {
                attemptId: Number(state.attemptId) || 0,
                stage: 'result'
            });
        } catch (error) {
            state.isFinishing = false;

            if (isNetworkConnectivityError(error)) {
                updateFinishProgress(
                    72,
                    3,
                    'Koneksi terputus saat finalisasi',
                    'Kami akan mencoba lagi otomatis ketika jaringan kembali stabil.'
                );
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
                    var recoveredReceipt = persistFinishReceipt(buildFinishReceiptFromPayload({
                        attempt_id: state.attemptId,
                        status: 'completed'
                    }, 'idempotent_finish_exam'));
                    var recoveredClosedResult = await recoverFinishedResultFromReceipt('attempt-closed', {
                        finishReceipt: recoveredReceipt,
                        finishPayload: {
                            attempt_id: state.attemptId,
                            status: 'completed'
                        }
                    });
                    if (!recoveredClosedResult || state.stage !== 'result') {
                        return;
                    }
                    state.success = autoSubmit ? 'Waktu habis. Ujian otomatis diselesaikan.' : 'Ujian selesai.';
                    recordTimelineEntry('finish:submit:success', 'Attempt sudah closed; hasil berhasil dipulihkan.', {
                        attemptId: Number(state.attemptId) || 0,
                        stage: 'result'
                    });
                } catch (resultError) {
                    state.error = '';
                    state.finishRecoveryLastError = resultError instanceof Error ? resultError.message : 'Ujian sudah selesai, tetapi hasil tidak bisa dimuat.';
                    recordTimelineEntry('finish_result_recovery_failed', state.finishRecoveryLastError, {
                        attemptId: Number(state.attemptId) || 0,
                        stage: String(state.stage || '')
                    });
                }
            } else {
                state.lastSyncError = error instanceof Error && error.message ? error.message : 'Gagal menyelesaikan ujian.';
                state.error = error instanceof Error ? error.message : 'Gagal menyelesaikan ujian.';
                if (shouldUnlockExamAfterFinishFailure()) {
                    unlockExamAfterFinishFailure();
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
        var hasFinishReceipt = !!getNormalizedFinishReceipt(state.finishReceipt);
        if (
            state.stage !== 'exam'
            || (state.attemptId <= 0 && !hasFinishReceipt)
            || !state.examLockedForPendingFinish
            || state.isFinishing
            || state.pendingSyncCount > 0
            || hasAnswerBatchFlushInFlight()
        ) {
            return Promise.resolve(null);
        }

        if (hasFinishReceipt && state.finishResultPending) {
            return recoverFinishedResultFromReceipt(reason || 'finish-result-recovery').catch(function () {
                // Error ditangani di recoverFinishedResultFromReceipt.
            });
        }

        if (getNavigatorConnectionStatus() === 'offline') {
            return Promise.resolve(null);
        }

        return finalizeExamAfterSync().catch(function () {
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
        resetFinishRecoveryReceiptState();
        updateFinishProgress(
            12,
            1,
            'Cek jawaban',
            'Simpan',
            {
                renderOptions: {
                    immediate: true,
                    skipPostRenderEffects: true
                }
            }
        );
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
        await waitForInteractiveFinishPaint();

        try {
            try {
                await flushAttemptUiState({
                    force: true,
                    allowWhileFinishing: true
                });
            } catch (error) {
                // Local fallback tetap tersedia.
            }
            updateFinishProgress(
                34,
                2,
                'Sinkron',
                'Kirim.'
            );

            if (getNavigatorConnectionStatus() === 'offline') {
                updateFinishProgress(
                    34,
                    2,
                    'Menunggu koneksi',
                    'Menunggu koneksi.'
                );
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
                            unlockExamAfterFinishFailure();
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
                unlockExamAfterFinishFailure();
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
