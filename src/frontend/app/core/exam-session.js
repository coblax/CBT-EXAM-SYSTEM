export function createExamSessionManager(deps) {
    var LOGIN_PROGRESS_STEP_TOTAL = 4;
    var OPEN_ATTEMPT_PROGRESS_STEP_TOTAL = 5;
    var RESULT_PROGRESS_STEP_TOTAL = 4;
    var START_ATTEMPT_TIMEOUT_MESSAGE = 'Gagal menyiapkan sesi ujian. Server terlalu lama merespons.';
    var START_ATTEMPT_STATUS_TIMEOUT_MESSAGE = 'Status sesi ujian belum dapat dipastikan. Server terlalu lama merespons.';
    var START_ATTEMPT_RECOVERY_BUSY_MESSAGE = 'Server masih sibuk menyiapkan sesi ujian. Coba lagi beberapa saat.';
    var recordTimeline = deps.recordTimeline;
    var state = deps.state;
    var apiRequest = deps.apiRequest;
    var applyAdaptiveLoadPayload = typeof deps.applyAdaptiveLoadPayload === 'function'
        ? deps.applyAdaptiveLoadPayload
        : function () {
            return false;
        };
    var clearAttemptUiStateSyncTimer = deps.clearAttemptUiStateSyncTimer;
    var clearAttemptUiSyncRuntimeState = deps.clearAttemptUiSyncRuntimeState;
    var clearAutoSaveRuntimeState = deps.clearAutoSaveRuntimeState;
    var clearMessages = deps.clearMessages;
    var clearPendingRevisionSafeAnswerRestoreState = deps.clearPendingRevisionSafeAnswerRestoreState;
    var clearQuestionPrefetchRuntimeState = deps.clearQuestionPrefetchRuntimeState;
    var clearQuestionRevisionRefreshState = deps.clearQuestionRevisionRefreshState;
    var choosePreferredAttemptUiState = deps.choosePreferredAttemptUiState;
    var clearPersistedAttemptUiState = deps.clearPersistedAttemptUiState;
    var clearPersistedQuestionCache = deps.clearPersistedQuestionCache;
    var ensureExamRuntimeBundle = typeof deps.ensureExamRuntimeBundle === 'function'
        ? deps.ensureExamRuntimeBundle
        : function () {
            return Promise.resolve(null);
        };
    var ensureExamStageRenderer = deps.ensureExamStageRenderer;
    var ensureQuestionWindowForIndex = deps.ensureQuestionWindowForIndex;
    var examTokenLength = Math.max(1, Number(deps.examTokenLength) || 1);
    var clearSecurityLoggingRuntimeState = deps.clearSecurityLoggingRuntimeState;
    var exitFullscreenSilently = deps.exitFullscreenSilently;
    var findExamById = deps.findExamById;
    var getNavigatorConnectionStatus = deps.getNavigatorConnectionStatus;
    var getChangedQuestionCount = typeof deps.getChangedQuestionCount === 'function'
        ? deps.getChangedQuestionCount
        : function () {
            return 0;
        };
    var getQuestionCount = deps.getQuestionCount;
    var getSelectedExam = deps.getSelectedExam;
    var initializeSubmittedPayloadCache = deps.initializeSubmittedPayloadCache;
    var isExamFullscreenRequired = deps.isExamFullscreenRequired;
    var loadQuestionWindow = deps.loadQuestionWindow;
    var maybeFinalizeLockedExam = deps.maybeFinalizeLockedExam;
    var normalizeExamToken = deps.normalizeExamToken;
    var parseDateTime = deps.parseDateTime;
    var persistAuthSession = deps.persistAuthSession;
    var persistCurrentAttemptUiStateLocally = deps.persistCurrentAttemptUiStateLocally;
    var persistCurrentQuestionCacheLocally = deps.persistCurrentQuestionCacheLocally;
    var prefetchCalculatorFeature = deps.prefetchCalculatorFeature;
    var ensureResultStageRenderer = typeof deps.ensureResultStageRenderer === 'function'
        ? deps.ensureResultStageRenderer
        : function () {
            return Promise.resolve(null);
        };
    var prefetchResultStageRenderer = deps.prefetchResultStageRenderer;
    var queueLoadedQuestionAnswersForFlush = deps.queueLoadedQuestionAnswersForFlush;
    var questionRevisionEquals = deps.questionRevisionEquals;
    var questionWindowOffsetForIndex = deps.questionWindowOffsetForIndex;
    var readPersistedAttemptUiState = deps.readPersistedAttemptUiState;
    var readPersistedQuestionCache = deps.readPersistedQuestionCache;
    var recordActionTrail = deps.recordActionTrail;
    var render = deps.render;
    var requestExamFullscreen = deps.requestExamFullscreen;
    var resetQuestionDataState = deps.resetQuestionDataState;
    var resetQuestionPrefetchIdleTimer = deps.resetQuestionPrefetchIdleTimer;
    var scheduleAttemptUiStateSync = deps.scheduleAttemptUiStateSync;
    var schedulePendingAnswerRetry = deps.schedulePendingAnswerRetry;
    var setConnectionStatus = deps.setConnectionStatus;
    var setQuestionRevision = deps.setQuestionRevision;
    var startSessionHeartbeat = deps.startSessionHeartbeat;
    var startTimer = deps.startTimer;
    var syncAttemptUiStateSignatureToCurrentState = deps.syncAttemptUiStateSignatureToCurrentState;
    var syncFullscreenState = deps.syncFullscreenState;
    var syncPendingAnswerRuntimeState = deps.syncPendingAnswerRuntimeState;
    var applyAttemptUiState = deps.applyAttemptUiState;
    var applyPersistedQuestionCache = deps.applyPersistedQuestionCache;
    var bumpQuestionDataGeneration = deps.bumpQuestionDataGeneration;
    var attemptUiStateSyncDelayMs = Math.max(0, Number(deps.attemptUiStateSyncDelayMs) || 0);
    var startAttemptTimeoutMs = Math.max(5000, Number(deps.startAttemptTimeoutMs) || 15000);
    var startAttemptStatusTimeoutMs = Math.max(
        5000,
        Number(deps.startAttemptStatusTimeoutMs) || Math.max(12000, Math.min(startAttemptTimeoutMs, 15000))
    );
    var startAttemptRecoveryTimeoutMs = Math.max(5000, Number(deps.startAttemptRecoveryTimeoutMs) || 30000);
    var startAttemptRecoveryPollDelayMs = Math.max(0, Number(deps.startAttemptRecoveryPollDelayMs) || 1200);
    var OPENING_RETRY_JITTER_RATIO = 0.25;
    var OPENING_RETRY_STATUS_MIN_MS = 1500;
    var OPENING_RETRY_STATUS_MAX_MS = 15000;
    var OPENING_RETRY_QUEUE_FALLBACK_MS = 3000;
    var OPENING_RETRY_BOOTSTRAP_MIN_MS = 1000;
    var OPENING_RETRY_BOOTSTRAP_MAX_MS = 8000;
    var OPENING_RETRY_DIAGNOSTIC_THRESHOLD = Math.max(3, Number(deps.openingRetryDiagnosticThreshold) || 5);
    var OPENING_RETRY_DIAGNOSTIC_INTERVAL = Math.max(1, Number(deps.openingRetryDiagnosticInterval) || 4);
    var questionWindowSize = Math.max(1, Number(deps.questionWindowSize) || 1);
    var SESSION_RECOVERY_EXAM_STEP_TOTAL = 7;
    var openingAttemptRequestSequence = 0;
    var activeOpeningAttemptRequestId = 0;
    var activeOpeningAttemptAbortRequest = null;
    var openingRetryCountdownTimerId = 0;
    var openingRetryCountdownIntervalId = 0;
    var openingRetryCountdownResolve = null;
    var openingRetryAutoActionTimerId = 0;
    var loginEntryFlowMetricContext = null;
    var openingEntryFlowMetricContext = null;

    function resetOpeningAttemptProgressState() {
        state.openingAttemptProgressPercent = 0;
        state.openingAttemptProgressStepIndex = 0;
        state.openingAttemptProgressStepTotal = 0;
        state.openingAttemptProgressStatus = '';
        state.openingAttemptProgressDetail = '';
    }

    function clearOpeningRetryCountdownTimers(result) {
        if (openingRetryCountdownTimerId) {
            clearTimeout(openingRetryCountdownTimerId);
            openingRetryCountdownTimerId = 0;
        }
        if (openingRetryCountdownIntervalId) {
            clearInterval(openingRetryCountdownIntervalId);
            openingRetryCountdownIntervalId = 0;
        }
        if (typeof openingRetryCountdownResolve === 'function') {
            var resolve = openingRetryCountdownResolve;
            openingRetryCountdownResolve = null;
            resolve(String(result || 'cancelled'));
        }
    }

    function clearOpeningRetryAutoActionTimer() {
        if (openingRetryAutoActionTimerId) {
            clearTimeout(openingRetryAutoActionTimerId);
            openingRetryAutoActionTimerId = 0;
        }
    }

    function clearOpeningAttemptLastResult() {
        state.pendingLastErrorCode = '';
        state.pendingLastErrorMessage = '';
    }

    function clearOpeningAttemptServerState() {
        state.openingAttemptServerState = '';
        state.openingAttemptServerReason = '';
        state.openingAttemptServerResumeSource = '';
        state.openingAttemptWaitAgeSeconds = 0;
        state.openingAttemptLastStageAt = 0;
    }

    function syncOpeningAttemptServerState(source, fallback) {
        fallback = fallback || {};
        state.openingAttemptServerState = String(
            source && source.opening_state
                ? source.opening_state
                : (fallback.openingState || '')
        ).trim().toLowerCase();
        state.openingAttemptServerReason = String(
            source && source.opening_reason
                ? source.opening_reason
                : (fallback.openingReason || '')
        ).trim().toLowerCase();
        state.openingAttemptServerResumeSource = String(
            source && source.resume_source
                ? source.resume_source
                : (fallback.resumeSource || '')
        ).trim().toLowerCase();
        state.openingAttemptWaitAgeSeconds = Math.max(
            0,
            Number(source && source.wait_age_seconds) || Number(fallback.waitAgeSeconds) || 0
        );
        state.openingAttemptLastStageAt = Math.max(
            0,
            Number(source && source.last_stage_at) || Number(fallback.lastStageAt) || 0
        );
    }

    function rememberOpeningAttemptLastResult(code, message) {
        var safeCode = String(code || '').trim().toLowerCase();
        var safeMessage = String(message || '').trim();

        if (safeCode === '' && safeMessage === '') {
            clearOpeningAttemptLastResult();
            return;
        }

        state.pendingLastErrorCode = safeCode;
        state.pendingLastErrorMessage = safeMessage;
    }

    function rememberOpeningAttemptLastResultFromPayload(payload, fallbackCode, fallbackMessage) {
        var status = getStartAttemptPayloadStatus(payload);
        var code = String(payload && payload.error_code ? payload.error_code : '').trim().toLowerCase();
        var message = '';

        if (status === 'queued') {
            code = code || 'queued';
            message = buildQueuedStartAttemptDetail(payload);
        } else if (status === 'admitted') {
            code = code || 'admitted';
            message = 'Giliran masuk sudah terbuka. Kami meminta server membuka sesi ujian.';
        } else if (status === 'resumed') {
            code = code || 'resumed';
            message = 'Attempt aktif ditemukan dan sedang dibuka.';
        } else if (status === 'started') {
            code = code || 'started';
            message = 'Attempt baru berhasil dibuat dan sedang dibuka.';
        } else if (status === 'completed') {
            code = code || 'attempt_already_completed';
            message = 'Ujian ini sudah selesai dan hasilnya siap dibuka.';
        } else if (status === 'terminal_error') {
            code = code || String(fallbackCode || 'terminal_error').trim().toLowerCase();
            message = String(payload && payload.error_message ? payload.error_message : (fallbackMessage || 'Sesi ujian tidak dapat dilanjutkan.')).trim();
        } else if (status === 'pending') {
            code = code || String(fallbackCode || 'start_attempt_status_pending').trim().toLowerCase();
            message = String(payload && payload.error_message ? payload.error_message : (fallbackMessage || 'Status sesi ujian belum dapat dipastikan.')).trim();
        } else {
            code = code || String(fallbackCode || '').trim().toLowerCase();
            message = String(
                payload && payload.error_message
                    ? payload.error_message
                    : (payload && payload.message ? payload.message : (fallbackMessage || ''))
            ).trim();
        }

        rememberOpeningAttemptLastResult(code, message);
    }

    function rememberOpeningAttemptLastResultFromError(error, fallbackCode, fallbackMessage) {
        if (isOpeningAttemptCancelledError(error)) {
            return;
        }

        rememberOpeningAttemptLastResult(
            getErrorCode(error) || String(fallbackCode || '').trim().toLowerCase(),
            error instanceof Error
                ? String(error.message || '').trim()
                : String(fallbackMessage || 'Status sesi ujian belum dapat dipastikan.').trim()
        );
    }

    function registerOpeningAttemptAbortRequest(abortRequest) {
        activeOpeningAttemptAbortRequest = typeof abortRequest === 'function' ? abortRequest : null;
    }

    function clearOpeningAttemptAbortRequest(abortRequest) {
        if (!abortRequest || abortRequest === activeOpeningAttemptAbortRequest) {
            activeOpeningAttemptAbortRequest = null;
        }
    }

    function abortActiveOpeningAttemptRequest(reason) {
        if (typeof activeOpeningAttemptAbortRequest !== 'function') {
            return;
        }

        var abortRequest = activeOpeningAttemptAbortRequest;
        activeOpeningAttemptAbortRequest = null;
        abortRequest(String(reason || 'opening_attempt_cancelled'));
    }

    function resetOpeningRetryState() {
        clearOpeningRetryCountdownTimers('cancelled');
        clearOpeningRetryAutoActionTimer();
        state.openingRetryAttemptCount = 0;
        state.openingRetryNextAt = 0;
        state.openingRetryDelayMs = 0;
        state.openingRetryReason = '';
        state.openingRetryCountdownSeconds = 0;
        state.openingRetryInFlight = false;
        state.openingRetryLastTrigger = '';
    }

    function resetOpeningAttemptControlState() {
        resetOpeningRetryState();
        state.openingAttemptPhase = '';
        state.openingAttemptCanRetry = false;
        state.openingAttemptCanRefreshStatus = false;
        state.openingAttemptCanBack = false;
        state.openingAttemptQueuePosition = 0;
        state.openingAttemptQueueEstimatedWaitSeconds = 0;
        state.openingAttemptQueueLastPolledAt = 0;
        state.openingAttemptLastActionKind = '';
        state.openingAttemptLastActionStatus = '';
        state.pendingExamId = 0;
        state.pendingExamToken = '';
        state.pendingStartIntentKey = '';
        state.pendingQueueTicket = '';
        state.pendingResumeIntent = false;
        state.pendingOpeningPhase = '';
        clearOpeningAttemptLastResult();
        clearOpeningAttemptServerState();
        clearOpeningEntryFlowMetricContext();
    }

    function beginOpeningAttemptUiAction(kind) {
        state.openingAttemptLastActionKind = String(kind || '');
        state.openingAttemptLastActionStatus = state.openingAttemptLastActionKind === '' ? '' : 'running';
    }

    function completeOpeningAttemptUiAction(status) {
        if (String(state.openingAttemptLastActionKind || '') === '') {
            return;
        }

        state.openingAttemptLastActionStatus = String(status || '');
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

    function buildEntryFlowMetricKey(prefix, stableSeed) {
        var seed = String(stableSeed || '').trim();
        if (seed !== '') {
            return String(prefix || 'entry') + '_' + seed;
        }

        return String(prefix || 'entry')
            + '_'
            + Date.now().toString(36)
            + '_'
            + Math.random().toString(36).slice(2, 10);
    }

    function emitEntryFlowMetric(payload) {
        if (!payload || typeof apiRequest !== 'function') {
            return Promise.resolve(false);
        }

        return apiRequest('entry_flow_metric', {
            method: 'POST',
            keepalive: true,
            body: payload
        }).then(function () {
            return true;
        }).catch(function () {
            return false;
        });
    }

    function beginLoginEntryFlowMetricContext() {
        loginEntryFlowMetricContext = {
            emitted: false,
            metricKey: buildEntryFlowMetricKey('login_entry_flow'),
            startedAt: Date.now(),
            loginRequestStartedAt: 0,
            loginRequestFinishedAt: 0,
            examListStartedAt: 0,
            examListFinishedAt: 0
        };

        return loginEntryFlowMetricContext;
    }

    function clearLoginEntryFlowMetricContext() {
        loginEntryFlowMetricContext = null;
    }

    function emitLoginEntryFlowMetricSuccess() {
        var context = loginEntryFlowMetricContext;
        if (!context || context.emitted) {
            return;
        }

        var finishedAt = context.examListFinishedAt > 0 ? context.examListFinishedAt : Date.now();
        var phaseDurations = {};
        if (context.loginRequestStartedAt > 0 && context.loginRequestFinishedAt >= context.loginRequestStartedAt) {
            phaseDurations.login_request_ms = Math.max(0, context.loginRequestFinishedAt - context.loginRequestStartedAt);
        }
        if (context.examListStartedAt > 0 && finishedAt >= context.examListStartedAt) {
            phaseDurations.login_exam_list_ms = Math.max(0, finishedAt - context.examListStartedAt);
        }

        context.emitted = true;
        emitEntryFlowMetric({
            flow: 'login_to_exam_list',
            metric_key: context.metricKey,
            duration_ms: Math.max(0, finishedAt - context.startedAt),
            phase_durations: phaseDurations
        });
    }

    function beginOpeningEntryFlowMetricContext(selectedExam, startIntentKey, resumeIntent) {
        var examId = Number(selectedExam && selectedExam.id) || 0;
        var metricKey = buildEntryFlowMetricKey('opening_entry_flow', startIntentKey || String(examId || '0'));

        if (
            openingEntryFlowMetricContext
            && openingEntryFlowMetricContext.emitted !== true
            && String(openingEntryFlowMetricContext.metricKey || '') === metricKey
            && Number(openingEntryFlowMetricContext.examId) === examId
        ) {
            return openingEntryFlowMetricContext;
        }

        openingEntryFlowMetricContext = {
            attemptAcquiredAt: 0,
            attemptId: 0,
            emitted: false,
            examId: examId,
            firstWindowStartedAt: 0,
            flow: '',
            metricKey: metricKey,
            phaseDurations: {},
            resumeIntentHint: resumeIntent === true,
            startedAt: Date.now(),
            uiStateReconcileMs: 0
        };

        return openingEntryFlowMetricContext;
    }

    function clearOpeningEntryFlowMetricContext() {
        openingEntryFlowMetricContext = null;
    }

    function recordOpeningEntryFlowAttemptAcquired(startPayload) {
        var context = openingEntryFlowMetricContext;
        if (!context || context.emitted) {
            return;
        }

        var acquiredAt = Date.now();
        var status = String(startPayload && startPayload.status ? startPayload.status : '').trim().toLowerCase();
        context.attemptId = Number(startPayload && startPayload.attempt_id) || context.attemptId || 0;
        context.examId = context.examId || Number(startPayload && startPayload.exam_id) || 0;
        if (!context.attemptAcquiredAt) {
            context.attemptAcquiredAt = acquiredAt;
            context.phaseDurations.attempt_acquire_ms = Math.max(0, acquiredAt - context.startedAt);
        }
        if (context.flow === '') {
            if (status === 'resumed' || status === 'resume') {
                context.flow = 'resume_to_first_question';
            } else if (status === 'started') {
                context.flow = 'start_to_first_question';
            } else {
                context.flow = context.resumeIntentHint
                    ? 'resume_to_first_question'
                    : 'start_to_first_question';
            }
        }
    }

    function markOpeningEntryFlowFirstWindowStart() {
        var context = openingEntryFlowMetricContext;
        if (!context || context.emitted || context.firstWindowStartedAt > 0) {
            return;
        }

        context.firstWindowStartedAt = Date.now();
        if (context.attemptAcquiredAt > 0) {
            context.phaseDurations.attempt_open_shell_ms = Math.max(0, context.firstWindowStartedAt - context.attemptAcquiredAt);
        }
    }

    function recordOpeningEntryFlowUiStateReconcile(durationMs) {
        var context = openingEntryFlowMetricContext;
        if (!context || context.emitted || !Number.isFinite(Number(durationMs))) {
            return;
        }

        context.uiStateReconcileMs = Math.max(0, Number(durationMs) || 0);
        if (context.uiStateReconcileMs > 0) {
            context.phaseDurations.ui_state_reconcile_ms = context.uiStateReconcileMs;
        }
    }

    function emitOpeningEntryFlowMetricSuccess() {
        var context = openingEntryFlowMetricContext;
        if (!context || context.emitted || context.flow === '') {
            return;
        }

        var finishedAt = Date.now();
        if (context.firstWindowStartedAt > 0) {
            context.phaseDurations.first_window_ready_ms = Math.max(0, finishedAt - context.firstWindowStartedAt);
        }

        var phaseDurations = {};
        ['attempt_acquire_ms', 'attempt_open_shell_ms', 'first_window_ready_ms', 'ui_state_reconcile_ms'].forEach(function (phase) {
            var durationMs = Number(context.phaseDurations && context.phaseDurations[phase]);
            if (Number.isFinite(durationMs) && durationMs >= 0) {
                phaseDurations[phase] = Math.floor(durationMs);
            }
        });

        context.emitted = true;
        emitEntryFlowMetric({
            flow: context.flow,
            metric_key: context.metricKey,
            duration_ms: Math.max(0, finishedAt - context.startedAt),
            phase_durations: phaseDurations,
            exam_id: Number(context.examId) || 0,
            attempt_id: Number(context.attemptId) || 0
        });
    }

    function resetResultProgressState() {
        state.resultProgressVisible = false;
        state.resultProgressPercent = 0;
        state.resultProgressStepIndex = 0;
        state.resultProgressStepTotal = 0;
        state.resultProgressStatus = '';
        state.resultProgressDetail = '';
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
        state.authProgressMode = 'login';
        state.authProgressPercent = Math.max(0, Math.min(100, safePercent));
        state.authProgressStepIndex = Math.max(0, Math.min(LOGIN_PROGRESS_STEP_TOTAL, safeStepIndex));
        state.authProgressStepTotal = LOGIN_PROGRESS_STEP_TOTAL;
        state.authProgressStatus = String(status || '');
        state.authProgressDetail = String(detail || '');

        if (shouldRender && typeof render === 'function') {
            render('auth-progress-login', {
                percent: state.authProgressPercent,
                selectedExamId: Number(state.selectedExamId) || 0,
                stepIndex: state.authProgressStepIndex
            });
        }
    }

    function updateResultProgress(percent, stepIndex, status, detail, options) {
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

        state.resultProgressVisible = true;
        state.resultProgressPercent = Math.max(0, Math.min(100, safePercent));
        state.resultProgressStepIndex = Math.max(0, Math.min(RESULT_PROGRESS_STEP_TOTAL, safeStepIndex));
        state.resultProgressStepTotal = RESULT_PROGRESS_STEP_TOTAL;
        state.resultProgressStatus = String(status || '');
        state.resultProgressDetail = String(detail || '');

        if (shouldRender && typeof render === 'function') {
            render('result-progress', {
                attemptId: Number(state.attemptId) || 0,
                percent: state.resultProgressPercent,
                selectedExamId: Number(state.selectedExamId) || 0,
                stepIndex: state.resultProgressStepIndex
            }, renderOptions);
        }
    }

    function updateOpeningAttemptProgress(percent, stepIndex, status, detail, options) {
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

        state.openingAttemptProgressPercent = Math.max(0, Math.min(100, safePercent));
        state.openingAttemptProgressStepIndex = Math.max(0, Math.min(OPEN_ATTEMPT_PROGRESS_STEP_TOTAL, safeStepIndex));
        state.openingAttemptProgressStepTotal = OPEN_ATTEMPT_PROGRESS_STEP_TOTAL;
        state.openingAttemptProgressStatus = String(status || '');
        state.openingAttemptProgressDetail = String(detail || '');

        if (shouldRender && typeof render === 'function') {
            render('attempt-opening-progress', {
                attemptId: Number(state.attemptId) || 0,
                percent: state.openingAttemptProgressPercent,
                selectedExamId: Number(state.selectedExamId) || 0,
                stepIndex: state.openingAttemptProgressStepIndex
            }, renderOptions);
        }
    }

    function updateSessionRecoveryExamProgress(stepIndex, status, detail, options) {
        options = options || {};
        if (!state.sessionRecoveryVisible || String(state.sessionRecoveryMode || '') !== 'exam_restore') {
            return false;
        }

        var safeStepIndex = Number(stepIndex);
        var safePercent = Number(options.percent);
        var shouldRender = options.render !== false;
        if (!Number.isFinite(safeStepIndex)) {
            safeStepIndex = 0;
        }
        if (!Number.isFinite(safePercent)) {
            safePercent = SESSION_RECOVERY_EXAM_STEP_TOTAL > 0
                ? (safeStepIndex / SESSION_RECOVERY_EXAM_STEP_TOTAL) * 100
                : 0;
        }

        state.sessionRecoveryVisible = true;
        state.sessionRecoveryMode = 'exam_restore';
        state.sessionRecoveryStepIndex = Math.max(0, Math.min(SESSION_RECOVERY_EXAM_STEP_TOTAL, safeStepIndex));
        state.sessionRecoveryStepTotal = SESSION_RECOVERY_EXAM_STEP_TOTAL;
        state.sessionRecoveryPercent = Math.max(0, Math.min(100, safePercent));
        state.sessionRecoveryStatus = String(status || '');
        state.sessionRecoveryDetail = String(detail || '');

        if (shouldRender && typeof render === 'function') {
            render('session-recovery-exam-progress', {
                attemptId: Number(state.attemptId) || 0,
                percent: state.sessionRecoveryPercent,
                selectedExamId: Number(state.selectedExamId) || 0,
                stepIndex: state.sessionRecoveryStepIndex
            });
        }

        return true;
    }

    function enterOpeningAttemptShell(percent, stepIndex, status, detail) {
        state.stage = 'exam';
        state.isOpeningAttempt = true;
        state.busy = true;
        state.openingAttemptPhase = 'opening_active';
        state.pendingOpeningPhase = 'opening_active';
        state.openingAttemptCanRetry = false;
        state.openingAttemptCanRefreshStatus = false;
        state.openingAttemptCanBack = true;
        clearOpeningAttemptLastResult();
        clearOpeningAttemptServerState();
        state.pendingQueueTicket = '';
        state.openingAttemptQueuePosition = 0;
        state.openingAttemptQueueEstimatedWaitSeconds = 0;
        state.openingAttemptQueueLastPolledAt = 0;
        updateOpeningAttemptProgress(
            percent,
            stepIndex,
            status,
            detail,
            {
                renderOptions: {
                    immediate: true,
                    skipPostRenderEffects: true
                }
            }
        );
    }

    function getErrorCode(error) {
        return String(error && error.code ? error.code : '').trim().toLowerCase();
    }

    function buildOpeningAttemptCancelledError() {
        var cancelledError = new Error('Opening attempt flow dibatalkan.');
        cancelledError.code = 'opening_attempt_cancelled';
        return cancelledError;
    }

    function isOpeningAttemptCancelledError(error) {
        return getErrorCode(error) === 'opening_attempt_cancelled';
    }

    function beginOpeningAttemptRequest() {
        openingAttemptRequestSequence += 1;
        activeOpeningAttemptRequestId = openingAttemptRequestSequence;
        return activeOpeningAttemptRequestId;
    }

    function cancelOpeningAttemptRequest() {
        abortActiveOpeningAttemptRequest('opening_attempt_cancelled');
        activeOpeningAttemptRequestId = 0;
    }

    function assertOpeningAttemptRequestActive(requestId) {
        if (requestId <= 0 || requestId !== activeOpeningAttemptRequestId) {
            throw buildOpeningAttemptCancelledError();
        }
    }

    function rememberOpeningAttemptContext(selectedExam, submittedToken, resumeIntent, startIntentKey) {
        state.pendingExamId = Number(selectedExam && selectedExam.id) || 0;
        state.pendingExamToken = String(submittedToken || '');
        state.pendingStartIntentKey = String(startIntentKey || state.pendingStartIntentKey || '');
        state.pendingResumeIntent = resumeIntent === true;
        state.pendingOpeningPhase = String(state.openingAttemptPhase || '');
    }

    function buildOpeningAttemptIntentKey(selectedExamId) {
        return 's_'
            + Math.max(0, Number(selectedExamId) || 0).toString(36)
            + '_'
            + Date.now().toString(36)
            + '_'
            + Math.random().toString(36).slice(2, 10);
    }

    function resolveOpeningAttemptIntentKey(selectedExamId, options) {
        var pendingKey = String(state.pendingStartIntentKey || '').trim();
        if (
            pendingKey !== ''
            && Math.max(0, Number(state.pendingExamId) || 0) === Math.max(0, Number(selectedExamId) || 0)
        ) {
            return pendingKey;
        }

        return buildOpeningAttemptIntentKey(selectedExamId);
    }

    function clearOpeningAttemptContext() {
        resetOpeningAttemptControlState();
    }

    function setOpeningAttemptPhase(phase, options) {
        options = options || {};
        state.openingAttemptPhase = String(phase || '');
        state.pendingOpeningPhase = state.openingAttemptPhase;
        state.openingAttemptCanRetry = options.canRetry === true;
        state.openingAttemptCanRefreshStatus = options.canRefreshStatus === true;
        state.openingAttemptCanBack = options.canBack !== false;
        if (Object.prototype.hasOwnProperty.call(options, 'queuePosition')) {
            state.openingAttemptQueuePosition = Math.max(0, Number(options.queuePosition) || 0);
        }
        if (Object.prototype.hasOwnProperty.call(options, 'estimatedWaitSeconds')) {
            state.openingAttemptQueueEstimatedWaitSeconds = Math.max(0, Number(options.estimatedWaitSeconds) || 0);
        }
        if (Object.prototype.hasOwnProperty.call(options, 'queueTicket')) {
            state.pendingQueueTicket = String(options.queueTicket || '');
        }
        if (Object.prototype.hasOwnProperty.call(options, 'errorCode')) {
            state.pendingLastErrorCode = String(options.errorCode || '');
        }
        if (Object.prototype.hasOwnProperty.call(options, 'errorMessage')) {
            state.pendingLastErrorMessage = String(options.errorMessage || '');
        }
        state.openingAttemptQueueLastPolledAt = Date.now();
    }

    function markOpeningAttemptTemporaryFailure(message, code, options) {
        options = options || {};
        state.stage = 'exam';
        state.isOpeningAttempt = true;
        state.busy = false;
        state.error = String(message || 'Server masih sibuk menyiapkan sesi ujian.');
        state.notice = '';
        state.success = '';
        rememberOpeningAttemptLastResult(code || 'opening_recovering', state.error);
        if (options.serverStateSource || options.serverState || options.serverReason) {
            syncOpeningAttemptServerState(options.serverStateSource || null, {
                openingState: String(options.serverState || ''),
                openingReason: String(options.serverReason || code || '').trim().toLowerCase()
            });
        }
        setOpeningAttemptPhase('opening_recovering', {
            canRetry: options.canRetry !== false,
            canRefreshStatus: options.canRefreshStatus !== false,
            canBack: true,
            errorCode: code || '',
            errorMessage: state.error
        });
        updateOpeningAttemptProgress(
            Number(options.percent) || Math.max(12, Number(state.openingAttemptProgressPercent) || 18),
            Number(options.stepIndex) || Math.max(1, Number(state.openingAttemptProgressStepIndex) || 1),
            String(options.status || 'Server masih sibuk menyiapkan sesi'),
            String(options.detail || 'Tetap di layar ini. Cek status lagi atau ulangi permintaan.'),
            {
                renderOptions: {
                    immediate: true,
                    skipPostRenderEffects: true
                }
            }
        );

        if (options.autoRetryAction) {
            scheduleOpeningRetryAutoAction(
                String(options.autoRetryAction || 'retry'),
                String(options.autoRetryReason || 'Server masih menyiapkan sesi.'),
                options.retrySource || null,
                Math.max(0, Number(options.retryDelayMs) || OPENING_RETRY_STATUS_MIN_MS),
                {
                    minDelayMs: Math.max(0, Number(options.minDelayMs) || OPENING_RETRY_STATUS_MIN_MS),
                    maxDelayMs: Math.max(
                        Math.max(0, Number(options.minDelayMs) || OPENING_RETRY_STATUS_MIN_MS),
                        Number(options.maxDelayMs) || OPENING_RETRY_STATUS_MAX_MS
                    )
                }
            );
        }
    }

    function markOpeningAttemptTerminalFailure(message, code, options) {
        options = options || {};
        state.stage = 'exam';
        state.isOpeningAttempt = true;
        state.busy = false;
        state.error = String(message || 'Sesi ujian tidak dapat dilanjutkan.');
        state.notice = '';
        state.success = '';
        rememberOpeningAttemptLastResult(code || 'opening_terminal_error', state.error);
        resetOpeningRetryState();
        syncOpeningAttemptServerState(options.serverStateSource || null, {
            openingState: 'terminal_error',
            openingReason: String(options.serverReason || code || '').trim().toLowerCase()
        });
        setOpeningAttemptPhase('opening_terminal_error', {
            canRetry: false,
            canRefreshStatus: false,
            canBack: true,
            errorCode: code || '',
            errorMessage: state.error
        });
        updateOpeningAttemptProgress(
            Number(options.percent) || Math.max(8, Number(state.openingAttemptProgressPercent) || 12),
            Number(options.stepIndex) || Math.max(1, Number(state.openingAttemptProgressStepIndex) || 1),
            String(options.status || 'Sesi ujian membutuhkan tindakan'),
            String(options.detail || 'Periksa pesan di bawah ini. Anda dapat kembali ke daftar exam setelah membaca penyebabnya.'),
            {
                renderOptions: {
                    immediate: true,
                    skipPostRenderEffects: true
                }
            }
        );
    }

    function updateOpeningAttemptQueueState(queuePayload) {
        var queueTicket = String(queuePayload && queuePayload.queue_ticket ? queuePayload.queue_ticket : '').trim();
        var queuePosition = Math.max(0, Number(queuePayload && queuePayload.queue_position) || 0);
        var estimatedWaitSeconds = Math.max(0, Number(queuePayload && queuePayload.estimated_wait_seconds) || 0);
        syncOpeningAttemptServerState(queuePayload, {
            openingState: 'queue_waiting',
            openingReason: 'queue_admission_wait'
        });

        state.stage = 'exam';
        state.isOpeningAttempt = true;
        state.busy = false;
        state.error = '';
        state.notice = '';
        state.success = '';
        setOpeningAttemptPhase('opening_waiting_queue', {
            canRetry: true,
            canRefreshStatus: true,
            canBack: true,
            queuePosition: queuePosition,
            estimatedWaitSeconds: estimatedWaitSeconds,
            queueTicket: queueTicket,
            errorCode: '',
            errorMessage: ''
        });
    }

    function resolvePendingOpeningExam() {
        var pendingExamId = Number(state.pendingExamId) || 0;
        if (pendingExamId <= 0) {
            return getSelectedExam();
        }

        if (typeof findExamById === 'function') {
            var matchedExam = findExamById(pendingExamId);
            if (matchedExam) {
                return matchedExam;
            }
        }

        var selectedExam = getSelectedExam();
        if (selectedExam && Number(selectedExam.id) === pendingExamId) {
            return selectedExam;
        }

        return {
            id: pendingExamId,
            duration_minutes: 60,
            is_class_allowed: 1,
            latest_attempt_status: state.pendingResumeIntent ? 'in_progress' : '',
            latest_attempt_id: 0
        };
    }

    function isTerminalStartAttemptError(error) {
        var code = getErrorCode(error);
        return code === 'attempt_already_completed'
            || code === 'token_invalid'
            || code === 'token_required'
            || code === 'forbidden'
            || code === 'not_found';
    }

    function isAttemptCompletedError(error) {
        return getErrorCode(error) === 'attempt_already_completed';
    }

    function routeToResultFromOpeningAttempt(selectedExam) {
        clearOpeningAttemptContext();
        resetOpeningAttemptProgressState();
        state.isOpeningAttempt = false;
        state.busy = false;
        state.error = '';
        state.notice = '';
        state.success = '';
        return handleViewResult({
            skipExamRefresh: true,
            selectedExam: selectedExam
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

    function extractRetryAfterMs(source, fallbackMs) {
        var candidates = [];
        if (source && typeof source === 'object') {
            candidates.push(source.retry_after_ms);
            candidates.push(source.retryAfterMs);
            candidates.push(source.poll_after_ms);
            if (source.data && typeof source.data === 'object') {
                candidates.push(source.data.retry_after_ms);
                candidates.push(source.data.poll_after_ms);
            }
            if (source.payload && typeof source.payload === 'object') {
                candidates.push(source.payload.retry_after_ms);
                candidates.push(source.payload.poll_after_ms);
            }
        }
        candidates.push(fallbackMs);

        for (var index = 0; index < candidates.length; index++) {
            var value = Number(candidates[index]);
            if (Number.isFinite(value) && value >= 0) {
                return Math.floor(value);
            }
        }

        return 0;
    }

    function computeOpeningRetryDelayMs(kind, source, fallbackMs, options) {
        options = options || {};
        var rawDelayMs = extractRetryAfterMs(source, fallbackMs);
        if (rawDelayMs <= 0) {
            return 0;
        }

        var minDelayMs = Math.max(0, Number(options.minDelayMs) || 0);
        var maxDelayMs = Math.max(minDelayMs, Number(options.maxDelayMs) || OPENING_RETRY_STATUS_MAX_MS);
        var jitterRatio = Number(options.jitterRatio);
        if (!Number.isFinite(jitterRatio)) {
            jitterRatio = OPENING_RETRY_JITTER_RATIO;
        }
        jitterRatio = Math.max(0, Math.min(0.9, jitterRatio));

        var boundedBaseMs = Math.max(minDelayMs, Math.min(maxDelayMs, rawDelayMs));
        var jitterFactor = 1;
        if (jitterRatio > 0) {
            jitterFactor = (1 - jitterRatio) + (Math.random() * jitterRatio * 2);
        }

        return Math.max(minDelayMs, Math.min(maxDelayMs, Math.floor(boundedBaseMs * jitterFactor)));
    }

    function updateOpeningRetryCountdownSeconds() {
        var nextAt = Number(state.openingRetryNextAt) || 0;
        if (nextAt <= 0) {
            state.openingRetryCountdownSeconds = 0;
            return 0;
        }

        state.openingRetryCountdownSeconds = Math.max(0, Math.ceil((nextAt - Date.now()) / 1000));
        return state.openingRetryCountdownSeconds;
    }

    function startOpeningRetryCountdown(kind, reason, source, fallbackMs, options) {
        options = options || {};
        clearOpeningRetryCountdownTimers('replaced');

        var delayMs = computeOpeningRetryDelayMs(kind, source, fallbackMs, options);
        if (delayMs <= 0) {
            state.openingRetryNextAt = 0;
            state.openingRetryDelayMs = 0;
            state.openingRetryCountdownSeconds = 0;
            state.openingRetryReason = '';
            return Promise.resolve('immediate');
        }

        var trigger = String(kind || 'retry');
        state.openingRetryAttemptCount = Math.max(0, Number(state.openingRetryAttemptCount) || 0) + 1;
        state.openingRetryNextAt = Date.now() + delayMs;
        state.openingRetryDelayMs = delayMs;
        state.openingRetryReason = String(reason || 'Server masih menyiapkan sesi.');
        state.openingRetryLastTrigger = trigger;
        state.openingRetryInFlight = false;
        updateOpeningRetryCountdownSeconds();
        if (typeof render === 'function') {
            render('opening-retry-countdown', {
                delayMs: delayMs,
                trigger: trigger
            });
        }

        return new Promise(function (resolve) {
            var settled = false;

            function finish(result) {
                if (settled) {
                    return;
                }
                settled = true;
                clearOpeningRetryCountdownTimers('');
                state.openingRetryNextAt = 0;
                state.openingRetryDelayMs = 0;
                state.openingRetryCountdownSeconds = 0;
                if (String(result || '') === 'manual') {
                    state.openingRetryReason = 'Mencoba sekarang...';
                }
                if (typeof render === 'function') {
                    render('opening-retry-countdown-complete', {
                        result: String(result || 'elapsed'),
                        trigger: trigger
                    });
                }
                resolve(String(result || 'elapsed'));
            }

            openingRetryCountdownResolve = finish;
            openingRetryCountdownTimerId = setTimeout(function () {
                finish('elapsed');
            }, delayMs);
            openingRetryCountdownIntervalId = setInterval(function () {
                updateOpeningRetryCountdownSeconds();
                if (typeof render === 'function') {
                    render('opening-retry-countdown-tick', {
                        seconds: state.openingRetryCountdownSeconds,
                        trigger: trigger
                    });
                }
            }, 1000);
        });
    }

    function forceOpeningRetryCountdown(kind) {
        if (typeof openingRetryCountdownResolve !== 'function') {
            return false;
        }

        state.openingRetryLastTrigger = String(kind || state.openingRetryLastTrigger || 'manual');
        openingRetryCountdownResolve('manual');
        return true;
    }

    function beginOpeningRetryRequestIndicator(kind, reason) {
        if (!state.isOpeningAttempt) {
            return;
        }

        clearOpeningRetryAutoActionTimer();
        state.openingRetryInFlight = true;
        state.openingRetryNextAt = 0;
        state.openingRetryDelayMs = 0;
        state.openingRetryCountdownSeconds = 0;
        state.openingRetryLastTrigger = String(kind || state.openingRetryLastTrigger || 'request');
        if (reason) {
            state.openingRetryReason = String(reason || '');
        }
    }

    function completeOpeningRetryRequestIndicator() {
        state.openingRetryInFlight = false;
    }

    function scheduleOpeningRetryAutoAction(action, reason, source, fallbackMs, options) {
        options = options || {};
        clearOpeningRetryAutoActionTimer();
        startOpeningRetryCountdown(
            action,
            reason,
            source,
            fallbackMs,
            options
        ).then(function (result) {
            if (String(result || '') === 'cancelled' || String(result || '') === 'replaced') {
                return;
            }
            if (!state.isOpeningAttempt || state.busy === true) {
                return;
            }
            openingRetryAutoActionTimerId = setTimeout(function () {
                openingRetryAutoActionTimerId = 0;
                if (!state.isOpeningAttempt || state.busy === true) {
                    return;
                }
                if (String(action || '') === 'refresh') {
                    refreshOpeningAttemptStatus();
                    return;
                }
                retryOpeningAttempt();
            }, 0);
        });
    }

    function withTimeout(promise, timeoutMs, timeoutMessage, options) {
        var settled = false;
        options = options || {};

        return new Promise(function (resolve, reject) {
            var timeoutId = setTimeout(function () {
                if (settled) {
                    return;
                }
                settled = true;
                if (typeof options.onTimeout === 'function') {
                    try {
                        options.onTimeout();
                    } catch (timeoutHookError) {
                        // Ignore timeout cleanup errors so the original timeout still surfaces.
                    }
                }
                var timeoutError = new Error(timeoutMessage);
                timeoutError.code = String(options.code || 'request_timeout');
                timeoutError.isTimeoutError = true;
                reject(timeoutError);
            }, timeoutMs);

            Promise.resolve(promise)
                .then(function (value) {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    clearTimeout(timeoutId);
                    resolve(value);
                })
                .catch(function (error) {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    clearTimeout(timeoutId);
                    reject(error);
                });
        });
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

    function isStartAttemptTimeoutError(error) {
        return getErrorCode(error) === 'start_attempt_timeout'
            || (error instanceof Error && String(error.message || '') === START_ATTEMPT_TIMEOUT_MESSAGE);
    }

    function isStartAttemptStatusTimeoutError(error) {
        return getErrorCode(error) === 'start_attempt_status_timeout'
            || (error instanceof Error && String(error.message || '') === START_ATTEMPT_STATUS_TIMEOUT_MESSAGE);
    }

    function isStartAttemptLockError(error) {
        var status = Number(error && error.status) || 0;
        var code = String(error && error.code ? error.code : '').trim().toLowerCase();
        return status === 429 || code === 'attempt_lock_active';
    }

    function isStartAttemptNotFoundError(error) {
        var status = Number(error && error.status) || 0;
        var code = String(error && error.code ? error.code : '').trim().toLowerCase();
        return code === 'attempt_not_found' || (status === 404 && code !== 'not_found');
    }

    function isStartAttemptRecoveryBusyError(error) {
        var code = String(error && error.code ? error.code : '').trim().toLowerCase();
        if (code === 'start_attempt_recovery_busy') {
            return true;
        }

        return error instanceof Error
            && String(error.message || '').trim() === START_ATTEMPT_RECOVERY_BUSY_MESSAGE;
    }

    function shouldRecoverSlowStartAttempt(error) {
        return isStartAttemptTimeoutError(error)
            || isStartAttemptStatusTimeoutError(error)
            || isStartAttemptLockError(error);
    }

    function isRetryableStartAttemptRecoveryError(error) {
        return shouldRecoverSlowStartAttempt(error) || isStartAttemptNotFoundError(error);
    }

    function isQuestionBootstrapRetryableError(error) {
        var code = getErrorCode(error);
        var status = Number(error && error.status) || 0;
        return code === 'attempt_bootstrap_busy'
            || code === 'question_bootstrap_busy'
            || code === 'runtime_bootstrap_busy'
            || (status === 429 && code.indexOf('bootstrap') !== -1);
    }

    async function loadOpeningQuestionWindowWithRetry(offset, options, requestId) {
        var retryCount = 0;
        var retryDelayMs = OPENING_RETRY_BOOTSTRAP_MIN_MS;
        var requestOptions = Object.assign({}, options || {}, {
            bootstrapLight: true
        });

        while (true) {
            try {
                var payload = await loadQuestionWindow(offset, requestOptions);
                recordTimelineEntry('question-window:ready', 'Question window awal siap dipakai.', {
                    attemptId: Number(state.attemptId) || 0,
                    selectedExamId: Number(requestOptions.examId) || 0,
                    stage: 'exam',
                    retryCount: retryCount
                });
                return payload;
            } catch (error) {
                if (!isQuestionBootstrapRetryableError(error)) {
                    throw error;
                }

                retryCount += 1;
                setOpeningAttemptPhase('opening_recovering', {
                    canRetry: true,
                    canRefreshStatus: true,
                    canBack: true,
                    errorCode: getErrorCode(error),
                    errorMessage: error instanceof Error ? error.message : 'Server masih menyiapkan data soal.'
                });
                updateOpeningAttemptProgress(
                    Math.max(78, Number(state.openingAttemptProgressPercent) || 78),
                    4,
                    'Memuat soal pertama',
                    'Server masih menyiapkan cache soal. Kami akan mencoba lagi otomatis dengan jeda aman.'
                );
                recordTimelineEntry('question-window:bootstrap:retry', error instanceof Error ? error.message : 'Bootstrap soal belum siap.', {
                    attemptId: Number(state.attemptId) || 0,
                    selectedExamId: Number(requestOptions.examId) || 0,
                    stage: 'exam',
                    retryCount: retryCount,
                    code: getErrorCode(error)
                });

                await startOpeningRetryCountdown(
                    'question_bootstrap',
                    'Server masih menyiapkan soal pertama.',
                    error,
                    retryDelayMs,
                    {
                        minDelayMs: OPENING_RETRY_BOOTSTRAP_MIN_MS,
                        maxDelayMs: OPENING_RETRY_BOOTSTRAP_MAX_MS
                    }
                );
                retryDelayMs = Math.min(OPENING_RETRY_BOOTSTRAP_MAX_MS, Math.floor(retryDelayMs * 1.6));
                if (requestId > 0) {
                    assertOpeningAttemptRequestActive(requestId);
                }
            }
        }
    }

    function mergeAttemptUiQuestionIds(primaryIds, secondaryIds) {
        var mergedLookup = {};
        var mergedIds = [];

        [primaryIds, secondaryIds].forEach(function (ids) {
            if (!Array.isArray(ids)) {
                return;
            }

            ids.forEach(function (questionId) {
                var safeQuestionId = Number(questionId) || 0;
                if (safeQuestionId <= 0 || mergedLookup[safeQuestionId]) {
                    return;
                }

                mergedLookup[safeQuestionId] = true;
                mergedIds.push(safeQuestionId);
            });
        });

        return mergedIds;
    }

    function buildOpeningAttemptUiStateSeed(attemptId, localAttemptUiState, questionCacheSnapshot) {
        var safeAttemptId = Number(attemptId) || 0;

        if (safeAttemptId <= 0) {
            return null;
        }

        if (localAttemptUiState && typeof localAttemptUiState === 'object') {
            return localAttemptUiState;
        }

        if (questionCacheSnapshot && typeof questionCacheSnapshot === 'object') {
            var cachedWindowOffset = Math.max(0, Math.floor(Number(questionCacheSnapshot.windowOffset) || 0));
            var cachedQuestionId = Array.isArray(questionCacheSnapshot.questionOrderIds)
                ? (Number(questionCacheSnapshot.questionOrderIds[cachedWindowOffset]) || 0)
                : 0;

            return {
                attempt_id: safeAttemptId,
                current_index: cachedWindowOffset,
                current_question_id: cachedQuestionId,
                doubtful_question_ids: []
            };
        }

        var currentIndex = Math.max(0, Math.floor(Number(state.currentIndex) || 0));
        var currentQuestionId = Array.isArray(state.questionOrderIds)
            ? (Number(state.questionOrderIds[currentIndex]) || 0)
            : 0;

        return {
            attempt_id: safeAttemptId,
            current_index: currentIndex,
            current_question_id: currentQuestionId,
            doubtful_question_ids: []
        };
    }

    function buildProvisionalOpeningAttemptUiState(attemptId, localAttemptUiState, questionCacheSnapshot) {
        return choosePreferredAttemptUiState(
            null,
            buildOpeningAttemptUiStateSeed(attemptId, localAttemptUiState, questionCacheSnapshot),
            questionCacheSnapshot,
            attemptId
        );
    }

    function requestDeferredOpeningUiState(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;

        if (safeAttemptId <= 0) {
            return Promise.resolve({
                attemptState: null,
                error: null
            });
        }

        recordTimelineEntry('attempt:ui-state:deferred:start', 'Meminta posisi resume server secara paralel.', {
            attemptId: safeAttemptId,
            stage: 'exam'
        });

        return apiRequest('ui_state', {
            query: {
                attempt_id: safeAttemptId
            }
        }).then(function (uiStatePayload) {
            var attemptState = uiStatePayload && uiStatePayload.attempt_state && typeof uiStatePayload.attempt_state === 'object'
                ? uiStatePayload.attempt_state
                : null;

            recordTimelineEntry('attempt:ui-state:deferred:ready', attemptState
                ? 'Posisi resume server siap direkonsiliasi.'
                : 'Server tidak mengembalikan posisi resume khusus.', {
                attemptId: safeAttemptId,
                stage: 'exam',
                hasAttemptState: attemptState ? 1 : 0
            });

            return {
                attemptState: attemptState,
                error: null
            };
        }).catch(function (error) {
            recordTimelineEntry('attempt:ui-state:deferred:error', error instanceof Error ? error.message : 'Gagal memuat posisi resume server.', {
                attemptId: safeAttemptId,
                stage: 'exam'
            });

            return {
                attemptState: null,
                error: error
            };
        });
    }

    function canAutoReconcileDeferredOpeningUiState(attemptId, provisionalIndex) {
        var safeAttemptId = Number(attemptId) || 0;
        if (safeAttemptId <= 0) {
            return false;
        }

        if (Number(state.attemptId) !== safeAttemptId || String(state.stage || '') !== 'exam') {
            return false;
        }

        if ((Number(getChangedQuestionCount()) || 0) > 0) {
            return false;
        }

        return Math.max(0, Math.floor(Number(state.currentIndex) || 0))
            === Math.max(0, Math.floor(Number(provisionalIndex) || 0));
    }

    function buildDeferredOpeningUiStateSnapshot(remoteAttemptUiState, localAttemptUiState, attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var safeRemoteSnapshot = remoteAttemptUiState && typeof remoteAttemptUiState === 'object'
            ? remoteAttemptUiState
            : {};

        return {
            attempt_id: safeAttemptId,
            current_index: Math.max(0, Math.floor(Number(
                safeRemoteSnapshot.current_index !== undefined
                    ? safeRemoteSnapshot.current_index
                    : 0
            ) || 0)),
            current_question_id: Number(safeRemoteSnapshot.current_question_id) || 0,
            doubtful_question_ids: mergeAttemptUiQuestionIds(
                localAttemptUiState && localAttemptUiState.doubtful_question_ids,
                safeRemoteSnapshot.doubtful_question_ids
            ),
            updated_at: Math.max(0, Number(safeRemoteSnapshot.updated_at) || 0)
        };
    }

    function scheduleDeferredOpeningUiStateReconciliation(context) {
        var deferredUiStatePromise = context && context.deferredUiStatePromise;
        var attemptId = Number(context && context.attemptId) || 0;
        var provisionalIndex = Math.max(0, Math.floor(Number(context && context.provisionalIndex) || 0));
        var examId = Number(context && context.examId) || 0;
        var localAttemptUiState = context && context.localAttemptUiState ? context.localAttemptUiState : null;

        if (attemptId <= 0 || !deferredUiStatePromise || typeof deferredUiStatePromise.then !== 'function') {
            return;
        }

        deferredUiStatePromise.then(async function (deferredResult) {
            if (!deferredResult || !deferredResult.attemptState) {
                return false;
            }

            if (!canAutoReconcileDeferredOpeningUiState(attemptId, provisionalIndex)) {
                recordTimelineEntry('attempt:ui-state:deferred:skip', 'Posisi resume server dilewati karena user sudah bergerak lebih dulu.', {
                    attemptId: attemptId,
                    stage: 'exam',
                    currentIndex: Number(state.currentIndex) || 0,
                    provisionalIndex: provisionalIndex
                });
                return false;
            }

            var deferredSnapshot = buildDeferredOpeningUiStateSnapshot(
                deferredResult.attemptState,
                localAttemptUiState,
                attemptId
            );
            var targetIndex = Math.max(0, Math.floor(Number(deferredSnapshot.current_index) || 0));
            var currentIndex = Math.max(0, Math.floor(Number(state.currentIndex) || 0));

            var reconcileStartedAt = 0;
            if (targetIndex !== currentIndex) {
                reconcileStartedAt = Date.now();
                await ensureQuestionWindowForIndex(targetIndex, {
                    examId: examId,
                    attemptId: attemptId,
                    includeExisting: 1,
                    limit: questionWindowSize
                });
            }

            if (!canAutoReconcileDeferredOpeningUiState(attemptId, provisionalIndex)) {
                recordTimelineEntry('attempt:ui-state:deferred:skip', 'Posisi resume server dibatalkan setelah user berinteraksi.', {
                    attemptId: attemptId,
                    stage: 'exam',
                    currentIndex: Number(state.currentIndex) || 0,
                    provisionalIndex: provisionalIndex
                });
                return false;
            }

            if (targetIndex === currentIndex) {
                return false;
            }

            applyAttemptUiState(deferredSnapshot, attemptId);
            syncAttemptUiStateSignatureToCurrentState();
            persistCurrentAttemptUiStateLocally();
            persistCurrentQuestionCacheLocally();
            render('attempt-ui-state-deferred-reconcile', {
                attemptId: attemptId,
                examId: examId,
                phase: 'deferred-ui-state-reconcile'
            });
            recordTimelineEntry('attempt:ui-state:deferred:reconciled', 'Posisi resume server diterapkan setelah shell ujian siap.', {
                attemptId: attemptId,
                stage: 'exam',
                previousIndex: currentIndex,
                targetIndex: targetIndex
            });
            if (reconcileStartedAt > 0) {
                recordOpeningEntryFlowUiStateReconcile(Date.now() - reconcileStartedAt);
            }
            return true;
        }).catch(function () {
            // Ignore deferred reconciliation errors; the user can continue from the current shell.
        });
    }

    function isQueuedStartAttemptPayload(payload) {
        return !!payload
            && typeof payload === 'object'
            && String(payload.status || '').toLowerCase() === 'queued'
            && String(payload.queue_ticket || '').trim() !== '';
    }

    function getStartAttemptPayloadStatus(payload) {
        return String(payload && payload.status ? payload.status : '').trim().toLowerCase();
    }

    function isAdmittedStartAttemptStatusPayload(payload) {
        return getStartAttemptPayloadStatus(payload) === 'admitted';
    }

    function isCompletedStartAttemptStatusPayload(payload) {
        return getStartAttemptPayloadStatus(payload) === 'completed';
    }

    function isPendingStartAttemptStatusPayload(payload) {
        return getStartAttemptPayloadStatus(payload) === 'pending';
    }

    function isTerminalStartAttemptStatusPayload(payload) {
        return getStartAttemptPayloadStatus(payload) === 'terminal_error';
    }

    function buildStartAttemptStatusError(payload, fallbackMessage) {
        var message = String(
            payload && payload.error_message
                ? payload.error_message
                : (fallbackMessage || 'Status sesi ujian belum dapat dipastikan.')
        ).trim();
        var error = new Error(message || 'Status sesi ujian belum dapat dipastikan.');
        error.code = String(payload && payload.error_code ? payload.error_code : 'start_attempt_status_pending').trim().toLowerCase();
        error.status = Math.max(0, Number(payload && payload.http_status) || 0);
        error.retry_after_ms = extractRetryAfterMs(payload, 0);
        error.opening_state = String(payload && payload.opening_state ? payload.opening_state : '').trim().toLowerCase();
        error.opening_reason = String(payload && payload.opening_reason ? payload.opening_reason : '').trim().toLowerCase();
        error.attempt_id = Math.max(0, Number(payload && payload.attempt_id) || 0);
        error.queue_ticket = String(payload && payload.queue_ticket ? payload.queue_ticket : '').trim();
        error.wait_age_seconds = Math.max(0, Number(payload && payload.wait_age_seconds) || 0);
        error.last_stage_at = Math.max(0, Number(payload && payload.last_stage_at) || 0);
        error.resume_source = String(payload && payload.resume_source ? payload.resume_source : '').trim().toLowerCase();
        return error;
    }

    function getOpeningStateRetryPolicy(openingState) {
        var normalizedState = String(openingState || '').trim().toLowerCase();
        if (normalizedState === 'queue_waiting') {
            return {
                minDelayMs: 800,
                maxDelayMs: OPENING_RETRY_STATUS_MAX_MS,
                fallbackMs: OPENING_RETRY_QUEUE_FALLBACK_MS,
                autoRetryAction: 'refresh'
            };
        }
        if (normalizedState === 'bootstrap_questions') {
            return {
                minDelayMs: OPENING_RETRY_BOOTSTRAP_MIN_MS,
                maxDelayMs: OPENING_RETRY_BOOTSTRAP_MAX_MS,
                fallbackMs: OPENING_RETRY_BOOTSTRAP_MIN_MS,
                autoRetryAction: Number(state.attemptId) > 0 ? 'retry' : 'refresh'
            };
        }

        return {
            minDelayMs: OPENING_RETRY_STATUS_MIN_MS,
            maxDelayMs: OPENING_RETRY_STATUS_MAX_MS,
            fallbackMs: OPENING_RETRY_STATUS_MIN_MS,
            autoRetryAction: Number(state.attemptId) > 0 ? 'retry' : 'refresh'
        };
    }

    function describeOpeningPendingState(source) {
        var openingState = String(source && source.opening_state ? source.opening_state : '').trim().toLowerCase();
        var openingReason = String(source && source.opening_reason ? source.opening_reason : '').trim().toLowerCase();
        var waitAgeSeconds = Math.max(0, Number(source && source.wait_age_seconds) || 0);
        var attemptId = Math.max(0, Number(source && source.attempt_id) || 0);
        var policy = getOpeningStateRetryPolicy(openingState);
        var detailParts = [];
        var status = 'Status sesi masih dipantau';
        var detail = 'Tetap di layar ini. Kami akan mencoba lagi dengan jeda aman.';
        var phase = 'opening_recovering';
        var percent = Math.max(16, Number(state.openingAttemptProgressPercent) || 16);
        var stepIndex = Math.max(1, Number(state.openingAttemptProgressStepIndex) || 1);

        if (openingState === 'resume_lookup') {
            status = 'Mengecek attempt aktif';
            detail = 'Server masih memeriksa apakah sesi aktif sudah terlihat untuk akun Anda.';
            percent = 18;
            stepIndex = 1;
        } else if (openingState === 'queue_waiting') {
            status = 'Menunggu giliran masuk ujian';
            detail = 'Tiket antrean yang sama tetap dipakai. Kami mengecek kapan giliran masuk dibuka.';
            percent = 20;
            stepIndex = 1;
            phase = 'opening_waiting_queue';
        } else if (openingState === 'attempt_creating') {
            status = 'Server sedang membuat sesi ujian';
            detail = 'Permintaan awal masih diproses. Kami menunggu server menyelesaikan pembuatan attempt.';
            percent = 24;
            stepIndex = 1;
        } else if (openingState === 'attempt_created' || openingState === 'bootstrap_session') {
            status = 'Sesi sudah dibuat, menyiapkan shell ujian';
            detail = 'Attempt sudah ada. Kami menunggu snapshot sesi dan runtime ringan siap dipakai.';
            percent = 46;
            stepIndex = 2;
        } else if (openingState === 'bootstrap_questions') {
            status = 'Sesi siap, memuat soal pertama';
            detail = 'Attempt sudah ada. Kami menunggu window soal pertama selesai disiapkan.';
            percent = 76;
            stepIndex = 4;
        }

        if (openingReason === 'lock_owner_active') {
            detailParts.push('Request sebelumnya masih memegang lock dan tetap menjadi intent yang sama.');
        } else if (openingReason === 'resume_db_miss' || openingReason === 'resume_index_miss') {
            detailParts.push('Attempt aktif belum terlihat, tetapi flow masih dipantau otomatis.');
        } else if (openingReason === 'question_window_pending') {
            detailParts.push('Cache soal awal belum siap penuh.');
        } else if (openingReason === 'session_snapshot_pending' || openingReason === 'entry_snapshot_pending') {
            detailParts.push('Snapshot sesi ringan masih disusun.');
        }

        if (attemptId > 0) {
            detailParts.push('Attempt #' + String(attemptId) + ' sudah dikenali server.');
        }
        if (waitAgeSeconds > 0) {
            detailParts.push('Umur tunggu saat ini sekitar ' + String(waitAgeSeconds) + ' detik.');
        }

        if (detailParts.length) {
            detail += ' ' + detailParts.join(' ');
        }

        return {
            openingState: openingState,
            openingReason: openingReason,
            phase: phase,
            status: status,
            detail: detail,
            percent: percent,
            stepIndex: stepIndex,
            minDelayMs: policy.minDelayMs,
            maxDelayMs: policy.maxDelayMs,
            fallbackMs: policy.fallbackMs,
            autoRetryAction: policy.autoRetryAction,
            autoRetryReason: status + '. Intent yang sama tetap dipakai.'
        };
    }

    function applyOpeningPendingStatusPayload(payload, options) {
        options = options || {};
        var description = describeOpeningPendingState(payload);

        syncOpeningAttemptServerState(payload, {
            openingState: description.openingState,
            openingReason: description.openingReason
        });
        state.stage = 'exam';
        state.isOpeningAttempt = true;
        state.busy = false;
        state.error = '';
        state.notice = '';
        state.success = '';
        setOpeningAttemptPhase(description.phase, {
            canRetry: true,
            canRefreshStatus: true,
            canBack: true,
            queuePosition: Math.max(0, Number(payload && payload.queue_position) || 0),
            estimatedWaitSeconds: Math.max(0, Number(payload && payload.estimated_wait_seconds) || 0),
            queueTicket: String(payload && payload.queue_ticket ? payload.queue_ticket : ''),
            errorCode: String(payload && payload.error_code ? payload.error_code : ''),
            errorMessage: String(payload && payload.error_message ? payload.error_message : '')
        });
        updateOpeningAttemptProgress(
            description.percent,
            description.stepIndex,
            description.status,
            description.detail,
            {
                renderOptions: {
                    immediate: true,
                    skipPostRenderEffects: true
                }
            }
        );

        if (options.scheduleRetry !== false) {
            scheduleOpeningRetryAutoAction(
                description.autoRetryAction,
                description.autoRetryReason,
                payload,
                Math.max(0, Number(payload && payload.retry_after_ms) || description.fallbackMs),
                {
                    minDelayMs: description.minDelayMs,
                    maxDelayMs: description.maxDelayMs
                }
            );
        }

        return description;
    }

    function buildQueuedStartAttemptDetail(payload) {
        var queuePosition = Math.max(0, Number(payload && payload.queue_position) || 0);
        var estimatedWaitSeconds = Math.max(0, Number(payload && payload.estimated_wait_seconds) || 0);
        var parts = [];

        if (queuePosition > 0) {
            parts.push('Posisi antrean ' + String(queuePosition) + '.');
        }
        if (estimatedWaitSeconds > 0) {
            parts.push('Perkiraan tunggu sekitar ' + String(estimatedWaitSeconds) + ' detik.');
        }
        parts.push('Server sedang melepas sesi masuk secara bertahap agar tetap stabil.');

        return parts.join(' ');
    }

    function getExamLatestAttemptStatus(exam) {
        return String(exam && exam.latest_attempt_status ? exam.latest_attempt_status : '').toLowerCase();
    }

    async function resolvePrimaryActionSelection(requestedAction) {
        var activeSelectedExam = getSelectedExam();
        var selectedExamId = Number(state.selectedExamId) || Number(activeSelectedExam && activeSelectedExam.id) || 0;
        if (selectedExamId <= 0) {
            return {
                action: String(requestedAction || ''),
                selectedExam: activeSelectedExam,
                refreshed: false
            };
        }

        recordTimelineEntry('exams:primary-action:refresh', 'Menyegarkan daftar exam sebelum menjalankan aksi utama.', {
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: selectedExamId,
            requestedAction: String(requestedAction || ''),
            stage: String(state.stage || '')
        });

        await loadExams();

        var selectedExam = getSelectedExam();
        var resolvedAction = String(requestedAction || '');
        var latestAttemptStatus = getExamLatestAttemptStatus(selectedExam);
        if (resolvedAction === 'view-result' && latestAttemptStatus !== 'completed') {
            resolvedAction = 'start-exam';
        } else if (resolvedAction === 'start-exam' && latestAttemptStatus === 'completed') {
            resolvedAction = 'view-result';
        }

        recordTimelineEntry('exams:primary-action:resolved', 'Status exam setelah refresh berhasil ditentukan.', {
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: Number(selectedExam && selectedExam.id) || selectedExamId,
            latestAttemptStatus: latestAttemptStatus,
            resolvedAction: resolvedAction,
            stage: String(state.stage || '')
        });

        return {
            action: resolvedAction,
            selectedExam: selectedExam,
            refreshed: true
        };
    }

    async function requestStartAttempt(body, options) {
        options = options || {};
        body = body || {};
        if (!body.idempotency_key && state.pendingStartIntentKey) {
            body.idempotency_key = state.pendingStartIntentKey;
        }
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var abortedByManager = false;
        var timedOut = false;
        var abortRequest = function () {
            abortedByManager = true;
            if (controller && typeof controller.abort === 'function') {
                controller.abort();
            }
        };
        beginOpeningRetryRequestIndicator('start_attempt', 'Menghubungi server untuk membuka sesi.');
        registerOpeningAttemptAbortRequest(abortRequest);
        try {
            var payload = await withTimeout(
                apiRequest('start_attempt', {
                    method: 'POST',
                    body: body,
                    signal: controller ? controller.signal : null
                }),
                Math.max(5000, Number(options.timeoutMs) || startAttemptTimeoutMs),
                String(options.timeoutMessage || START_ATTEMPT_TIMEOUT_MESSAGE),
                {
                    code: 'start_attempt_timeout',
                    onTimeout: function () {
                        timedOut = true;
                        if (controller && typeof controller.abort === 'function') {
                            controller.abort();
                        }
                    }
                }
            );
            rememberOpeningAttemptLastResultFromPayload(payload, 'start_attempt_pending', 'Permintaan start belum mendapat jawaban final.');
            return payload;
        } catch (error) {
            if (abortedByManager || (!timedOut && getErrorCode(error) === 'request_aborted')) {
                throw buildOpeningAttemptCancelledError();
            }
            rememberOpeningAttemptLastResultFromError(error, 'start_attempt_failed', 'Permintaan start belum mendapat jawaban final.');
            throw error;
        } finally {
            clearOpeningAttemptAbortRequest(abortRequest);
            completeOpeningRetryRequestIndicator();
        }
    }

    async function requestStartAttemptStatus(body, options) {
        options = options || {};
        body = body || {};
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        var abortedByManager = false;
        var timedOut = false;
        var abortRequest = function () {
            abortedByManager = true;
            if (controller && typeof controller.abort === 'function') {
                controller.abort();
            }
        };
        beginOpeningRetryRequestIndicator('start_attempt_status', 'Mengecek status sesi aktif.');
        registerOpeningAttemptAbortRequest(abortRequest);
        try {
            var payload = await withTimeout(
                apiRequest('start_attempt_status', {
                    method: 'POST',
                    body: body,
                    signal: controller ? controller.signal : null
                }),
                Math.max(5000, Number(options.timeoutMs) || startAttemptStatusTimeoutMs),
                String(options.timeoutMessage || START_ATTEMPT_STATUS_TIMEOUT_MESSAGE),
                {
                    code: 'start_attempt_status_timeout',
                    onTimeout: function () {
                        timedOut = true;
                        if (controller && typeof controller.abort === 'function') {
                            controller.abort();
                        }
                    }
                }
            );
            rememberOpeningAttemptLastResultFromPayload(payload, 'start_attempt_status_pending', 'Status sesi ujian belum dapat dipastikan.');
            return payload;
        } catch (error) {
            if (abortedByManager || (!timedOut && getErrorCode(error) === 'request_aborted')) {
                throw buildOpeningAttemptCancelledError();
            }
            rememberOpeningAttemptLastResultFromError(error, 'start_attempt_status_failed', 'Status sesi ujian belum dapat dipastikan.');
            throw error;
        } finally {
            clearOpeningAttemptAbortRequest(abortRequest);
            completeOpeningRetryRequestIndicator();
        }
    }

    async function maybeRunOpeningAttemptResumeDiagnostic(selectedExam, requestId, lastDiagnosticRetryCount) {
        var currentRetryCount = Math.max(0, Number(state.openingRetryAttemptCount) || 0);
        var examId = Number(selectedExam && selectedExam.id) || 0;

        if (examId <= 0) {
            return {
                handled: false,
                lastDiagnosticRetryCount: lastDiagnosticRetryCount
            };
        }

        if (
            currentRetryCount < OPENING_RETRY_DIAGNOSTIC_THRESHOLD
            || currentRetryCount < (Math.max(0, Number(lastDiagnosticRetryCount) || 0) + OPENING_RETRY_DIAGNOSTIC_INTERVAL)
        ) {
            return {
                handled: false,
                lastDiagnosticRetryCount: lastDiagnosticRetryCount
            };
        }

        updateOpeningAttemptProgress(
            26,
            1,
            'Memeriksa ulang sesi secara lebih tegas',
            'Percobaan berulang belum memberi jawaban final. Kami meminta server memeriksa ulang attempt aktif tanpa membuat intent baru.'
        );
        rememberOpeningAttemptLastResult(
            'resume_diagnostic_running',
            'Pemeriksaan lanjutan sedang berjalan. Intent yang sama tetap dipakai.'
        );

        try {
            var diagnosticPayload = await requestStartAttempt({
                exam_id: examId,
                resume_only: 1
            }, {
                timeoutMs: Math.max(startAttemptStatusTimeoutMs, 12000),
                timeoutMessage: START_ATTEMPT_STATUS_TIMEOUT_MESSAGE
            });
            assertOpeningAttemptRequestActive(requestId);

            if (getStartAttemptPayloadStatus(diagnosticPayload) === 'resumed') {
                rememberOpeningAttemptLastResult(
                    'resumed',
                    'Attempt aktif ditemukan lewat pemeriksaan lanjutan dan siap dibuka.'
                );
                return {
                    handled: true,
                    lastDiagnosticRetryCount: currentRetryCount,
                    payload: diagnosticPayload
                };
            }

            rememberOpeningAttemptLastResultFromPayload(
                diagnosticPayload,
                'resume_diagnostic_pending',
                'Pemeriksaan lanjutan belum menemukan attempt aktif.'
            );
            return {
                handled: false,
                lastDiagnosticRetryCount: currentRetryCount
            };
        } catch (error) {
            if (isOpeningAttemptCancelledError(error)) {
                throw error;
            }

            rememberOpeningAttemptLastResultFromError(
                error,
                'resume_diagnostic_failed',
                'Pemeriksaan lanjutan belum berhasil.'
            );

            if (!isRetryableStartAttemptRecoveryError(error)) {
                throw error;
            }

            return {
                handled: false,
                lastDiagnosticRetryCount: currentRetryCount
            };
        }
    }

    async function recoverSlowStartAttempt(selectedExam, submittedToken, triggerError, requestId) {
        var examId = Number(selectedExam && selectedExam.id) || 0;
        var recoveryDeadlineAt = Date.now() + startAttemptRecoveryTimeoutMs;
        var lastError = triggerError;
        var hasRetriedFreshStart = false;
        var lastDiagnosticRetryCount = 0;
        var statusTimeoutMs = startAttemptStatusTimeoutMs;

        while (Date.now() <= recoveryDeadlineAt) {
            assertOpeningAttemptRequestActive(requestId);
            updateOpeningAttemptProgress(
                18,
                1,
                'Server masih menyiapkan sesi ujian',
                'Request awal sedang kami pantau. Anda tidak perlu menekan tombol lagi.'
            );
            await startOpeningRetryCountdown(
                'resume_status',
                'Mengecek lagi attempt aktif. Intent yang sama tetap dipakai.',
                lastError,
                startAttemptRecoveryPollDelayMs,
                {
                    minDelayMs: OPENING_RETRY_STATUS_MIN_MS,
                    maxDelayMs: OPENING_RETRY_STATUS_MAX_MS
                }
            );
            assertOpeningAttemptRequestActive(requestId);

            try {
                updateOpeningAttemptProgress(
                    24,
                    1,
                    'Mengecek attempt aktif',
                    'Kami mencoba melanjutkan ke sesi yang mungkin sudah berhasil dibuat.'
                );
                var statusPayload = await requestStartAttemptStatus({
                    exam_id: examId,
                    resume_only: 1
                }, {
                    timeoutMs: statusTimeoutMs
                });
                assertOpeningAttemptRequestActive(requestId);

                if (getStartAttemptPayloadStatus(statusPayload) === 'resumed') {
                    syncOpeningAttemptServerState(statusPayload, {
                        openingState: 'ready',
                        openingReason: 'attempt_ready'
                    });
                    return statusPayload;
                }

                if (isCompletedStartAttemptStatusPayload(statusPayload)) {
                    throw buildStartAttemptStatusError({
                        error_code: 'attempt_already_completed',
                        error_message: 'Anda sudah menyelesaikan ujian ini.',
                        http_status: 403,
                    });
                }

                if (isTerminalStartAttemptStatusPayload(statusPayload)) {
                    syncOpeningAttemptServerState(statusPayload, {
                        openingState: 'terminal_error',
                        openingReason: String(statusPayload && statusPayload.error_code ? statusPayload.error_code : '').trim().toLowerCase()
                    });
                    throw buildStartAttemptStatusError(statusPayload);
                }

                if (!isPendingStartAttemptStatusPayload(statusPayload)) {
                    return statusPayload;
                }

                lastError = buildStartAttemptStatusError(statusPayload);
                rememberOpeningAttemptLastResultFromPayload(
                    statusPayload,
                    'attempt_pending',
                    'Attempt aktif belum ditemukan. Status sesi masih kami pantau.'
                );
                applyOpeningPendingStatusPayload(statusPayload, {
                    scheduleRetry: false
                });

                var pendingDiagnosticResult = await maybeRunOpeningAttemptResumeDiagnostic(
                    selectedExam,
                    requestId,
                    lastDiagnosticRetryCount
                );
                lastDiagnosticRetryCount = pendingDiagnosticResult.lastDiagnosticRetryCount;
                if (pendingDiagnosticResult.handled) {
                    return pendingDiagnosticResult.payload;
                }
            } catch (resumeError) {
                lastError = resumeError;
                rememberOpeningAttemptLastResultFromError(
                    resumeError,
                    'start_attempt_status_failed',
                    'Status sesi ujian belum dapat dipastikan.'
                );
                if (!isRetryableStartAttemptRecoveryError(resumeError)) {
                    throw resumeError;
                }

                if (isStartAttemptNotFoundError(resumeError) && !hasRetriedFreshStart) {
                    hasRetriedFreshStart = true;
                    try {
                        updateOpeningAttemptProgress(
                            30,
                            1,
                            'Mencoba lagi pembuatan sesi',
                            'Attempt aktif belum terlihat. Kami mencoba sekali lagi dengan aman.'
                        );
                        return await requestStartAttempt({
                            exam_id: examId,
                            exam_token: submittedToken
                        });
                    } catch (retryError) {
                        lastError = retryError;
                        rememberOpeningAttemptLastResultFromError(
                            retryError,
                            'start_attempt_retry_failed',
                            'Percobaan start ulang belum berhasil.'
                        );
                        if (!isRetryableStartAttemptRecoveryError(retryError)) {
                            throw retryError;
                        }
                    }
                }

                var diagnosticResult = await maybeRunOpeningAttemptResumeDiagnostic(
                    selectedExam,
                    requestId,
                    lastDiagnosticRetryCount
                );
                lastDiagnosticRetryCount = diagnosticResult.lastDiagnosticRetryCount;
                if (diagnosticResult.handled) {
                    return diagnosticResult.payload;
                }
                continue;
            }
        }

        if (lastError && !isRetryableStartAttemptRecoveryError(lastError)) {
            throw lastError;
        }

        var busyError = new Error(START_ATTEMPT_RECOVERY_BUSY_MESSAGE);
        busyError.code = 'start_attempt_recovery_busy';
        throw busyError;
    }

    async function tryFinalizePendingStartAttempt(selectedExam, requestId) {
        var examId = Number(selectedExam && selectedExam.id) || 0;
        if (examId <= 0) {
            return {
                status: 'not_found',
                exam: null
            };
        }

        try {
            assertOpeningAttemptRequestActive(requestId);
            updateOpeningAttemptProgress(
                36,
                2,
                'Memastikan attempt yang baru dibuat',
                'Kami mengecek status sesi terbaru tanpa memuat ulang daftar exam.'
            );
            var statusPayload = await requestStartAttemptStatus({
                exam_id: examId,
                resume_only: 1
            }, {
                timeoutMs: startAttemptStatusTimeoutMs
            });
            assertOpeningAttemptRequestActive(requestId);

            var refreshedExam = findExamById(examId) || getSelectedExam() || selectedExam;
            if (getStartAttemptPayloadStatus(statusPayload) === 'resumed') {
                syncOpeningAttemptServerState(statusPayload, {
                    openingState: 'ready',
                    openingReason: 'attempt_ready'
                });
                state.selectedExamId = examId;
                await openAttemptSession(refreshedExam, statusPayload, requestId);
                return {
                    status: 'resumed',
                    exam: refreshedExam
                };
            }

            if (isCompletedStartAttemptStatusPayload(statusPayload)) {
                return {
                    status: 'completed',
                    exam: refreshedExam
                };
            }

            if (isTerminalStartAttemptStatusPayload(statusPayload) || isPendingStartAttemptStatusPayload(statusPayload)) {
                if (isPendingStartAttemptStatusPayload(statusPayload)) {
                    applyOpeningPendingStatusPayload(statusPayload, {
                        scheduleRetry: false
                    });
                } else {
                    syncOpeningAttemptServerState(statusPayload, {
                        openingState: 'terminal_error',
                        openingReason: String(statusPayload && statusPayload.error_code ? statusPayload.error_code : '').trim().toLowerCase()
                    });
                }
                updateOpeningAttemptProgress(
                    40,
                    2,
                    'Menyegarkan status exam',
                    'Kami melakukan satu sinkronisasi akhir ke daftar exam untuk memastikan sesi aktif tidak tertinggal.'
                );
                await loadExams();
                assertOpeningAttemptRequestActive(requestId);

                refreshedExam = findExamById(examId) || getSelectedExam() || selectedExam;
                var latestAttemptId = Number(refreshedExam && refreshedExam.latest_attempt_id) || 0;
                var latestAttemptStatus = String(refreshedExam && refreshedExam.latest_attempt_status ? refreshedExam.latest_attempt_status : '').toLowerCase();
                if (latestAttemptStatus === 'completed' && latestAttemptId > 0) {
                    return {
                        status: 'completed',
                        exam: refreshedExam
                    };
                }
                if (latestAttemptId <= 0 || latestAttemptStatus !== 'in_progress') {
                    return {
                        status: 'not_found',
                        exam: refreshedExam
                    };
                }

                updateOpeningAttemptProgress(
                    54,
                    2,
                    'Attempt aktif ditemukan',
                    'Server sudah menandai sesi aktif. Kami mencoba menyambungkan Anda kembali.'
                );
                var resumePayload = await requestStartAttemptStatus({
                    exam_id: examId,
                    resume_only: 1
                }, {
                    timeoutMs: startAttemptStatusTimeoutMs
                });
                assertOpeningAttemptRequestActive(requestId);

                if (getStartAttemptPayloadStatus(resumePayload) === 'resumed') {
                    syncOpeningAttemptServerState(resumePayload, {
                        openingState: 'ready',
                        openingReason: 'attempt_ready'
                    });
                    state.selectedExamId = examId;
                    await openAttemptSession(refreshedExam, resumePayload, requestId);
                    return {
                        status: 'resumed',
                        exam: refreshedExam
                    };
                }

                return {
                    status: 'not_found',
                    exam: refreshedExam
                };
            }

            return {
                status: 'not_found',
                exam: refreshedExam
            };
        } catch (resumeError) {
            if (isOpeningAttemptCancelledError(resumeError)) {
                throw resumeError;
            }
            recordTimelineEntry(
                'attempt:start:final-resume-error',
                resumeError instanceof Error ? resumeError.message : 'Resume akhir attempt gagal.',
                {
                    attemptId: Number(state.attemptId) || 0,
                    selectedExamId: examId,
                    stage: String(state.stage || '')
                }
            );
            return {
                status: 'failed',
                exam: null
            };
        }
    }

    async function waitForQueuedStartAttempt(selectedExam, submittedToken, queuedPayload, requestId) {
        var examId = Number(selectedExam && selectedExam.id) || 0;
        var activePayload = queuedPayload;
        var hasRetriedFreshStart = false;
        var hasLoggedQueueState = false;
        var pollTimeoutMs = startAttemptStatusTimeoutMs;

        while (isQueuedStartAttemptPayload(activePayload)) {
            assertOpeningAttemptRequestActive(requestId);

            var queueTicket = String(activePayload && activePayload.queue_ticket ? activePayload.queue_ticket : '').trim();
            var queuePosition = Math.max(0, Number(activePayload && activePayload.queue_position) || 0);
            var pollAfterMs = Math.max(0, Number(activePayload && activePayload.poll_after_ms) || OPENING_RETRY_QUEUE_FALLBACK_MS);
            var estimatedWaitSeconds = Math.max(0, Number(activePayload && activePayload.estimated_wait_seconds) || 0);

            updateOpeningAttemptQueueState(activePayload);

            updateOpeningAttemptProgress(
                20,
                1,
                'Menunggu giliran masuk ujian',
                buildQueuedStartAttemptDetail(activePayload)
            );

            if (!hasLoggedQueueState) {
                recordTimelineEntry('attempt:start:queued', 'Start attempt masuk antrean gate exam.', {
                    attemptId: Number(state.attemptId) || 0,
                    selectedExamId: examId,
                    queuePosition: queuePosition,
                    estimatedWaitSeconds: estimatedWaitSeconds,
                    stage: 'exam'
                });
                recordActionTrailEntry('attempt:start:queued', 'Menunggu giliran masuk ujian.', {
                    selectedExamId: examId,
                    queuePosition: queuePosition,
                    estimatedWaitSeconds: estimatedWaitSeconds
                });
                hasLoggedQueueState = true;
            }

            await startOpeningRetryCountdown(
                'queue',
                'Menunggu giliran masuk ujian. Posisi/intent tetap sama.',
                activePayload,
                pollAfterMs,
                {
                    minDelayMs: 800,
                    maxDelayMs: OPENING_RETRY_STATUS_MAX_MS
                }
            );
            assertOpeningAttemptRequestActive(requestId);

            try {
                updateOpeningAttemptProgress(
                    22,
                    1,
                    'Mengecek status antrean',
                    'Giliran Anda dicek ulang. Tiket antrean yang sama tetap dipakai.'
                );
                var statusPayload = await requestStartAttemptStatus({
                    exam_id: examId,
                    exam_token: submittedToken,
                    queue_ticket: queueTicket
                }, {
                    timeoutMs: pollTimeoutMs,
                    timeoutMessage: 'Masih menunggu giliran sesi ujian.'
                });
                assertOpeningAttemptRequestActive(requestId);

                if (isQueuedStartAttemptPayload(statusPayload)) {
                    activePayload = statusPayload;
                    continue;
                }

                if (isAdmittedStartAttemptStatusPayload(statusPayload)) {
                    activePayload = await requestStartAttempt({
                        exam_id: examId,
                        exam_token: submittedToken,
                        queue_ticket: queueTicket
                    }, {
                        timeoutMs: pollTimeoutMs,
                        timeoutMessage: 'Masih menunggu giliran sesi ujian.'
                    });
                    assertOpeningAttemptRequestActive(requestId);
                    continue;
                }

                if (getStartAttemptPayloadStatus(statusPayload) === 'resumed') {
                    syncOpeningAttemptServerState(statusPayload, {
                        openingState: 'ready',
                        openingReason: 'attempt_ready'
                    });
                    return statusPayload;
                }

                if (isCompletedStartAttemptStatusPayload(statusPayload)) {
                    throw buildStartAttemptStatusError({
                        error_code: 'attempt_already_completed',
                        error_message: 'Anda sudah menyelesaikan ujian ini.',
                        http_status: 403,
                    });
                }

                if (isTerminalStartAttemptStatusPayload(statusPayload)) {
                    syncOpeningAttemptServerState(statusPayload, {
                        openingState: 'terminal_error',
                        openingReason: String(statusPayload && statusPayload.error_code ? statusPayload.error_code : '').trim().toLowerCase()
                    });
                    throw buildStartAttemptStatusError(statusPayload);
                }

                if (isPendingStartAttemptStatusPayload(statusPayload)) {
                    applyOpeningPendingStatusPayload(statusPayload, {
                        scheduleRetry: false
                    });
                    if (!hasRetriedFreshStart && String(statusPayload && statusPayload.error_code ? statusPayload.error_code : '').trim().toLowerCase() === 'queue_ticket_not_found') {
                        hasRetriedFreshStart = true;
                        activePayload = await requestStartAttempt({
                            exam_id: examId,
                            exam_token: submittedToken
                        });
                        continue;
                    }

                    throw buildStartAttemptStatusError(
                        statusPayload,
                        'Status antrean belum pasti. Gunakan Refresh Status atau Coba Lagi.'
                    );
                }

                activePayload = statusPayload;
            } catch (pollError) {
                if (isOpeningAttemptCancelledError(pollError)) {
                    throw pollError;
                }
                if (shouldRecoverSlowStartAttempt(pollError)) {
                    return await recoverSlowStartAttempt(selectedExam, submittedToken, pollError, requestId);
                }

                if (!hasRetriedFreshStart && isStartAttemptNotFoundError(pollError)) {
                    hasRetriedFreshStart = true;
                    activePayload = await requestStartAttempt({
                        exam_id: examId,
                        exam_token: submittedToken
                    });
                    continue;
                }

                throw pollError;
            }
        }

        return activePayload;
    }

    function resetOpeningAttemptState() {
        state.attemptId = 0;
        state.remainingSeconds = 0;
        state.isOpeningAttempt = false;
        state.isFinishing = false;
        state.result = null;
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        resetOpeningAttemptProgressState();
        clearOpeningAttemptContext();
        resetQuestionDataState();
    }

    async function loadExams() {
        var payload = await apiRequest('exams');
        applyAdaptiveLoadPayload(payload);
        state.exams = Array.isArray(payload.items) ? payload.items : [];
        state.examPickerMobileOpen = false;
        recordTimelineEntry('exams:loaded', 'Daftar exam berhasil dimuat.', {
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: Number(state.selectedExamId) || 0,
            count: state.exams.length,
            stage: String(state.stage || '')
        });

        var currentUserPayload = payload && typeof payload === 'object' && payload.current_user && typeof payload.current_user === 'object'
            ? payload.current_user
            : null;
        if (currentUserPayload && state.user && Number(currentUserPayload.user_id || 0) === Number(state.user.user_id || 0)) {
            state.user = {
                user_id: Number(state.user.user_id) || 0,
                role: Object.prototype.hasOwnProperty.call(currentUserPayload, 'role')
                    ? String(currentUserPayload.role || '')
                    : String(state.user.role || ''),
                display_name: Object.prototype.hasOwnProperty.call(currentUserPayload, 'display_name')
                    ? String(currentUserPayload.display_name || '')
                    : String(state.user.display_name || ''),
                username: Object.prototype.hasOwnProperty.call(currentUserPayload, 'username')
                    ? String(currentUserPayload.username || '')
                    : String(state.user.username || ''),
                email: Object.prototype.hasOwnProperty.call(currentUserPayload, 'email')
                    ? String(currentUserPayload.email || '')
                    : String(state.user.email || ''),
                kode_kelas: Object.prototype.hasOwnProperty.call(currentUserPayload, 'kode_kelas')
                    ? String(currentUserPayload.kode_kelas || '')
                    : String(state.user.kode_kelas || ''),
                kode_ruang: Object.prototype.hasOwnProperty.call(currentUserPayload, 'kode_ruang')
                    ? String(currentUserPayload.kode_ruang || '')
                    : String(state.user.kode_ruang || ''),
                agama: Object.prototype.hasOwnProperty.call(currentUserPayload, 'agama')
                    ? String(currentUserPayload.agama || '')
                    : String(state.user.agama || ''),
                foto: Object.prototype.hasOwnProperty.call(currentUserPayload, 'foto')
                    ? String(currentUserPayload.foto || '')
                    : String(state.user.foto || '')
            };
        }

        if (!state.exams.length) {
            state.selectedExamId = 0;
            state.examToken = '';
            persistAuthSession();
            return;
        }

        var selectedStillExists = state.exams.some(function (exam) {
            return Number(exam.id) === Number(state.selectedExamId);
        });

        if (selectedStillExists) {
            persistAuthSession();
            return;
        }

        if (state.exams.length === 1) {
            state.selectedExamId = Number(state.exams[0].id) || 0;
        } else {
            state.selectedExamId = 0;
            state.examToken = '';
        }

        persistAuthSession();
    }

    async function handleLogin(form) {
        if (state.busy) {
            return;
        }

        var identifierEl = form.querySelector('[name="identifier"]');
        var passwordEl = form.querySelector('[name="password"]');
        var identifier = String(state.loginIdentifier || (identifierEl ? identifierEl.value || '' : '')).trim();
        var password = String(state.loginPassword || (passwordEl ? passwordEl.value || '' : ''));
        state.loginIdentifier = identifier;
        state.loginPassword = password;

        clearMessages();

        if (!identifier || !password) {
            state.error = 'Identifier dan password wajib diisi.';
            render();
            return;
        }

        state.busy = true;
        var loginMetricContext = beginLoginEntryFlowMetricContext();
        loginMetricContext.loginRequestStartedAt = Date.now();
        updateAuthProgress(
            12,
            1,
            'Menghubungi server login',
            'Identifier dan password Anda sedang diverifikasi.',
            { render: false }
        );
        recordTimelineEntry('login:start', 'Login dimulai.', {
            attemptId: Number(state.attemptId) || 0,
            stage: String(state.stage || '')
        });
        render('login-submit', {
            phase: 'auth-progress-initial'
        }, {
            immediate: true,
            skipPostRenderEffects: true
        });

        try {
            var loginPayload = await apiRequest('login', {
                method: 'POST',
                auth: false,
                body: {
                    identifier: identifier,
                    password: password
                }
            });
            loginMetricContext.loginRequestFinishedAt = Date.now();
            applyAdaptiveLoadPayload(loginPayload);

            updateAuthProgress(
                42,
                2,
                'Menyusun sesi peserta',
                'Token login dan profil singkat sedang disiapkan.',
                { render: false }
            );
            state.token = String(loginPayload.token || '');
            state.user = {
                user_id: Number(loginPayload.user_id) || 0,
                role: String(loginPayload.role || ''),
                display_name: String(loginPayload.display_name || ''),
                username: String(loginPayload.username || ''),
                email: String(loginPayload.email || ''),
                kode_kelas: String(loginPayload.kode_kelas || ''),
                kode_ruang: String(loginPayload.kode_ruang || ''),
                agama: String(loginPayload.agama || ''),
                foto: String(loginPayload.foto || '')
            };

            updateAuthProgress(
                72,
                3,
                'Memuat daftar ujian',
                'Kami mengambil daftar exam yang tersedia untuk akun ini.'
            );
            loginMetricContext.examListStartedAt = Date.now();
            await loadExams();
            loginMetricContext.examListFinishedAt = Date.now();
            updateAuthProgress(
                92,
                4,
                'Menyiapkan dashboard ujian',
                'Sesi aktif sedang disinkronkan sebelum Anda masuk ke daftar exam.',
                { render: false }
            );
            state.stage = 'confirm';
            state.success = '';
            state.error = '';
            state.loginIdentifier = '';
            state.loginPassword = '';
            state.loginPasswordVisible = false;
            persistAuthSession();
            startSessionHeartbeat();
            recordTimelineEntry('login:success', 'Login berhasil.', {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: Number(state.selectedExamId) || 0,
                stage: 'confirm'
            });
            updateAuthProgress(
                100,
                4,
                'Login berhasil',
                'Daftar exam siap ditampilkan.',
                { render: false }
            );
            emitLoginEntryFlowMetricSuccess();
        } catch (error) {
            state.error = error instanceof Error ? error.message : 'Login gagal.';
            resetAuthProgressState();
            recordTimelineEntry('login:error', state.error, {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || '')
            });
        } finally {
            clearLoginEntryFlowMetricContext();
            state.busy = false;
            resetAuthProgressState();
            render();
        }
    }

    async function openAttemptSession(selectedExam, startPayload, requestId) {
        if (requestId > 0) {
            assertOpeningAttemptRequestActive(requestId);
        }
        recordOpeningEntryFlowAttemptAcquired(startPayload);
        state.attemptId = Number(startPayload && startPayload.attempt_id) || 0;
        if (state.attemptId <= 0) {
            throw new Error('Attempt ID tidak valid.');
        }

        state.stage = 'exam';
        state.isOpeningAttempt = true;
        state.error = '';
        state.notice = '';
        state.success = '';
        syncOpeningAttemptServerState(startPayload, {
            openingState: 'ready',
            openingReason: 'attempt_ready'
        });
        recordTimelineEntry('attempt:open:start', 'Membuka sesi ujian.', {
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: Number(selectedExam && selectedExam.id) || 0,
            stage: 'exam'
        });
        updateSessionRecoveryExamProgress(
            5,
            'Memuat window soal',
            'Kami sedang menyiapkan shell ujian dan jendela soal pertama.',
            {
                percent: 68
            }
        );
        updateOpeningAttemptProgress(
            28,
            2,
            'Menyiapkan runtime ujian',
            'Memuat modul interaktif, keamanan, dan shell soal.'
        );

        clearSecurityLoggingRuntimeState();
        clearQuestionPrefetchRuntimeState();
        clearAttemptUiStateSyncTimer();
        clearPendingRevisionSafeAnswerRestoreState();
        clearQuestionRevisionRefreshState();
        bumpQuestionDataGeneration();
        clearAttemptUiSyncRuntimeState();

        var durationMinutes = Number(
            (startPayload && startPayload.duration_minutes) ||
            (selectedExam && selectedExam.duration_minutes) ||
            60
        );
        applyAdaptiveLoadPayload(startPayload);
        var remainingSecondsFromServer = Math.floor(Number(startPayload && startPayload.remaining_seconds) || 0);
        var remainingSeconds = remainingSecondsFromServer > 0
            ? remainingSecondsFromServer
            : Math.max(0, durationMinutes * 60);
        if (remainingSecondsFromServer <= 0) {
            var startedAt = parseDateTime(startPayload && startPayload.started_at);
            if (startedAt) {
                var elapsed = Math.floor((Date.now() - startedAt.getTime()) / 1000);
                if (elapsed > 0) {
                    remainingSeconds = Math.max(0, remainingSeconds - elapsed);
                }
            }
        }
        state.remainingSeconds = remainingSeconds;

        var examId = Number(selectedExam && selectedExam.id) || 0;
        var includeExisting = 1;
        if (examId > 0) {
            state.selectedExamId = examId;
        }
        var deferredUiStatePromise = requestDeferredOpeningUiState(state.attemptId);
        var runtimeBundlePromise = ensureExamRuntimeBundle();

        await runtimeBundlePromise;
        if (requestId > 0) {
            assertOpeningAttemptRequestActive(requestId);
        }
        updateOpeningAttemptProgress(
            52,
            3,
            'Memulihkan data lokal',
            'Menyinkronkan posisi terakhir, cache soal, dan jawaban tersimpan.'
        );
        setQuestionRevision(startPayload && startPayload.question_revision, examId);
        resetQuestionDataState({
            preserveQuestionRevision: true
        });

        var isFreshStartedAttempt = String(startPayload && startPayload.status ? startPayload.status : '') === 'started';
        if (isFreshStartedAttempt) {
            if (typeof clearPersistedAttemptUiState === 'function') {
                clearPersistedAttemptUiState(state.attemptId);
            }
            clearPersistedQuestionCache(state.attemptId);
        }

        var restoredQuestionCachePromise = readPersistedQuestionCache(state.attemptId);

        var localAttemptUiState = readPersistedAttemptUiState(state.attemptId);
        var restoredQuestionCacheSnapshot = await restoredQuestionCachePromise;
        if (requestId > 0) {
            assertOpeningAttemptRequestActive(requestId);
        }
        var expectedQuestionOrderSignature = String(startPayload && startPayload.question_order_signature || '').trim();
        if (
            restoredQuestionCacheSnapshot &&
            !questionRevisionEquals(
                restoredQuestionCacheSnapshot.questionRevision,
                state.questionRevision,
                examId
            )
        ) {
            clearPersistedQuestionCache(state.attemptId);
            restoredQuestionCacheSnapshot = null;
        }
        if (
            restoredQuestionCacheSnapshot &&
            expectedQuestionOrderSignature !== '' &&
            String(restoredQuestionCacheSnapshot.questionOrderSignature || '').trim() !== expectedQuestionOrderSignature
        ) {
            applyPersistedQuestionCache(
                restoredQuestionCacheSnapshot,
                {
                    attemptId: state.attemptId,
                    examId: examId,
                    expectedQuestionRevision: state.questionRevision,
                    restoreAnswersOnly: true
                }
            );
            clearPersistedQuestionCache(state.attemptId);
            restoredQuestionCacheSnapshot = null;
        }

        var provisionalAttemptUiState = buildProvisionalOpeningAttemptUiState(
            state.attemptId,
            localAttemptUiState,
            restoredQuestionCacheSnapshot
        );
        var requestedResumeIndex = Math.max(
            0,
            Math.floor(Number(provisionalAttemptUiState && provisionalAttemptUiState.current_index !== undefined
                ? provisionalAttemptUiState.current_index
                : 0) || 0)
        );

        var restoredQuestionCache = applyPersistedQuestionCache(
            restoredQuestionCacheSnapshot,
            {
                attemptId: state.attemptId,
                examId: examId,
                expectedQuestionOrderSignature: expectedQuestionOrderSignature,
                preferredIndex: requestedResumeIndex,
                windowSize: questionWindowSize,
                expectedQuestionRevision: state.questionRevision
            }
        );
        updateSessionRecoveryExamProgress(
            6,
            'Memulihkan jawaban lokal',
            'Mengembalikan posisi terakhir, cache soal, dan jawaban lokal ke tampilan ujian.',
            {
                percent: 84
            }
        );

        recordTimelineEntry('question-window:load:start', 'Memuat question window awal.', {
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: examId,
            stage: 'exam',
            currentIndex: Number(requestedResumeIndex) || 0
        });
        markOpeningEntryFlowFirstWindowStart();
        updateOpeningAttemptProgress(
            76,
            4,
            'Memuat soal awal',
            'Mengambil jendela soal pertama dan jawaban yang sudah pernah tersimpan.'
        );
        try {
            await loadOpeningQuestionWindowWithRetry(
                questionWindowOffsetForIndex(requestedResumeIndex, questionWindowSize),
                {
                    examId: examId,
                    attemptId: state.attemptId,
                    includeExisting: includeExisting,
                    includeAnswerManifest: 1,
                    limit: questionWindowSize,
                    overwriteExisting: isFreshStartedAttempt,
                    replaceAnsweredState: true,
                    useExistingStableQuestionNumbers: false
                },
                requestId
            );
            if (requestId > 0) {
                assertOpeningAttemptRequestActive(requestId);
            }
        } catch (error) {
            recordTimelineEntry('question-window:load:error', error instanceof Error ? error.message : 'Question window awal gagal dimuat.', {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: examId,
                stage: 'exam',
                currentIndex: Number(requestedResumeIndex) || 0
            });
            throw error;
        }
        recordTimelineEntry('question-window:load:success', 'Question window awal berhasil dimuat.', {
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: examId,
            stage: 'exam',
            currentIndex: Number(requestedResumeIndex) || 0
        });

        applyAttemptUiState(provisionalAttemptUiState, state.attemptId);
        syncAttemptUiStateSignatureToCurrentState();

        state.isFinishing = false;
        state.navPanelVisible = true;
        state.calculatorVisible = false;
        state.calculatorExpression = '';
        state.calculatorResult = '';
        state.calculatorError = '';

        if (!getQuestionCount()) {
            throw new Error('Belum ada soal pada exam ini.');
        }

        updateOpeningAttemptProgress(
            92,
            5,
            'Finalisasi tampilan ujian',
            'Menyalakan timer, sinkronisasi jawaban, dan panel interaktif.'
        );
        ensureQuestionWindowForIndex(state.currentIndex, {
            examId: examId,
            attemptId: state.attemptId,
            includeExisting: includeExisting,
            limit: questionWindowSize
        }).catch(function (error) {
            recordTimelineEntry('question-window:prefetch:error', error instanceof Error ? error.message : 'Prefetch question window gagal.', {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: examId,
                stage: 'exam',
                currentIndex: Number(state.currentIndex) || 0
            });
        });
        if (requestId > 0) {
            assertOpeningAttemptRequestActive(requestId);
        }
        if (!restoredQuestionCache) {
            initializeSubmittedPayloadCache();
        }
        var queuedCachedAnswerCount = restoredQuestionCache ? queueLoadedQuestionAnswersForFlush() : 0;
        ensureExamStageRenderer({
            renderOnResolve: true
        }).catch(function () {
            // Keep attempt state intact; the exam stage shell will surface a retry UI.
        });
        prefetchCalculatorFeature();

        state.isOpeningAttempt = false;
        state.isFinishing = false;
        state.error = '';
        state.notice = '';
        state.success = '';
        setConnectionStatus(getNavigatorConnectionStatus(), {
            persist: false,
            render: false,
            triggerRetry: false
        });
        syncPendingAnswerRuntimeState({
            persist: false,
            clearLastSyncError: false
        });
        persistAuthSession();
        persistCurrentAttemptUiStateLocally();
        persistCurrentQuestionCacheLocally();
        scheduleAttemptUiStateSync(attemptUiStateSyncDelayMs);
        scheduleDeferredOpeningUiStateReconciliation({
            attemptId: state.attemptId,
            deferredUiStatePromise: deferredUiStatePromise,
            examId: examId,
            localAttemptUiState: localAttemptUiState,
            provisionalIndex: requestedResumeIndex
        });
        if (queuedCachedAnswerCount > 0 || state.pendingSyncCount > 0 || state.examLockedForPendingFinish) {
            schedulePendingAnswerRetry('open-attempt', {
                delayMs: 700,
                resetBackoff: true
            });
        }
        startSessionHeartbeat();
        startTimer();
        resetQuestionPrefetchIdleTimer();
        maybeFinalizeLockedExam('open-attempt');
        emitOpeningEntryFlowMetricSuccess();
        clearOpeningAttemptContext();
        recordTimelineEntry('attempt:open:success', 'Sesi ujian siap.', {
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: examId,
            pendingSyncCount: Number(state.pendingSyncCount) || 0,
            stage: 'exam'
        });
        updateSessionRecoveryExamProgress(
            7,
            'Menyinkronkan jawaban tertunda',
            'Jawaban lokal sedang diselaraskan agar sesi ujian kembali aman dipakai.',
            {
                percent: 100,
                render: false
            }
        );
        updateOpeningAttemptProgress(
            100,
            OPEN_ATTEMPT_PROGRESS_STEP_TOTAL,
            'Ujian siap dibuka',
            'Mengalihkan Anda ke tampilan soal.',
            {
                render: false
            }
        );
        resetOpeningAttemptProgressState();
    }

    async function tryResumeExamCandidate(exam) {
        var examId = Number(exam && exam.id) || 0;
        var latestAttemptId = Number(exam && exam.latest_attempt_id) || 0;
        var latestAttemptStatus = String(exam && exam.latest_attempt_status ? exam.latest_attempt_status : '').toLowerCase();
        if (examId <= 0) {
            return false;
        }
        if (latestAttemptId <= 0 || latestAttemptStatus !== 'in_progress') {
            return false;
        }

        try {
            updateSessionRecoveryExamProgress(
                4,
                'Menyambung attempt ujian',
                'Kami sedang mengecek attempt aktif dan menyiapkan sambungan ke sesi terakhir.',
                {
                    percent: 54
                }
            );
            recordActionTrailEntry('attempt:resume', 'Resume ujian dipicu.', {
                selectedExamId: examId
            });
            recordTimelineEntry('attempt:resume:request', 'Mencoba resume attempt aktif.', {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: examId,
                stage: String(state.stage || '')
            });
            var resumePayload = await requestStartAttempt({
                exam_id: examId,
                resume_only: 1
            });

            state.selectedExamId = examId;
            await openAttemptSession(exam, resumePayload);
            recordTimelineEntry('attempt:resume:success', 'Resume attempt berhasil.', {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: examId,
                stage: 'exam'
            });
            recordActionTrailEntry('attempt:resume:success', 'Resume attempt berhasil.', {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: examId
            });
            return true;
        } catch (error) {
            recordTimelineEntry('attempt:resume:error', error instanceof Error ? error.message : 'Resume attempt gagal.', {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: examId,
                stage: String(state.stage || '')
            });
            recordActionTrailEntry('attempt:resume:error', error instanceof Error ? error.message : 'Resume attempt gagal.', {
                selectedExamId: examId
            });
            return false;
        }
    }

    async function tryResumeActiveAttemptFromExamList(options) {
        options = options || {};
        var selectedOnly = !!options.selectedOnly;

        if (!Array.isArray(state.exams) || !state.exams.length) {
            return false;
        }

        if (selectedOnly) {
            var selectedExamOnly = findExamById(state.selectedExamId);
            if (!selectedExamOnly) {
                return false;
            }
            return tryResumeExamCandidate(selectedExamOnly);
        }

        var candidates = [];
        var seenExamIds = {};
        var selectedExam = getSelectedExam();
        if (selectedExam && Number(selectedExam.id) > 0) {
            candidates.push(selectedExam);
            seenExamIds[Number(selectedExam.id)] = true;
        }

        state.exams.forEach(function (exam) {
            var examId = Number(exam && exam.id) || 0;
            if (examId <= 0 || seenExamIds[examId]) {
                return;
            }
            candidates.push(exam);
            seenExamIds[examId] = true;
        });

        for (var i = 0; i < candidates.length; i++) {
            var exam = candidates[i];
            var resumed = await tryResumeExamCandidate(exam);
            if (resumed) {
                return true;
            }
        }

        return false;
    }

    async function handleStartExam(options) {
        options = options || {};
        if (state.busy && !state.isOpeningAttempt) {
            return;
        }

        clearMessages();
        clearAutoSaveRuntimeState();
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        recordActionTrailEntry('attempt:start', 'Mulai ujian dipicu.', {});

        var selectedExam = options.selectedExam || getSelectedExam();
        if (!selectedExam) {
            state.error = 'Pilih exam terlebih dahulu.';
            render('start-exam-error', {
                reason: 'missing-selected-exam'
            });
            return;
        }

        var selectedExamId = Number(selectedExam && selectedExam.id) || 0;
        var resumeIntent = options.resumeIntentOverride === true
            || (options.resumeIntentOverride !== false && getExamLatestAttemptStatus(selectedExam) === 'in_progress');
        var requestId = beginOpeningAttemptRequest();
        var submittedToken = options.submittedToken !== undefined
            ? String(options.submittedToken || '')
            : normalizeExamToken(state.examToken);
        var queueTicket = String(options.queueTicket || '');
        var forceResumeOnly = options.forceResumeOnly === true;
        var allowFreshStartFallback = options.allowFreshStartFallback !== false;
        var shouldSkipBlockingRefresh = options.skipExamRefresh === true || resumeIntent;
        var startIntentKey = '';

        enterOpeningAttemptShell(
            8,
            1,
            resumeIntent
                ? 'Menyambungkan sesi ujian'
                : 'Menyiapkan permintaan masuk ujian',
            resumeIntent
                ? 'Kami sedang mengecek attempt aktif dan menyiapkan resume dari progres terakhir.'
                : 'Kami sedang memverifikasi status exam sebelum sesi baru dibuat.'
        );

        try {
            if (!shouldSkipBlockingRefresh) {
                updateOpeningAttemptProgress(
                    10,
                    1,
                    'Menyegarkan status exam',
                    'Memastikan status terbaru exam sebelum sesi ujian dibuka.'
                );

                var refreshedStartSelection = await resolvePrimaryActionSelection('start-exam');
                assertOpeningAttemptRequestActive(requestId);
                selectedExam = refreshedStartSelection && refreshedStartSelection.selectedExam
                    ? refreshedStartSelection.selectedExam
                    : null;

                if (!selectedExam) {
                    markOpeningAttemptTerminalFailure(
                        'Exam yang dipilih sudah tidak tersedia.',
                        'selected_exam_missing',
                        {
                            status: 'Exam yang dipilih tidak tersedia',
                            detail: 'Silakan kembali ke daftar exam dan pilih exam yang masih aktif.'
                        }
                    );
                    return;
                }

                if (String(refreshedStartSelection && refreshedStartSelection.action ? refreshedStartSelection.action : '') === 'view-result') {
                    cancelOpeningAttemptRequest();
                    recordActionTrailEntry('attempt:start:rerouted', 'Status exam berubah menjadi selesai, mengalihkan ke hasil ujian.', {
                        selectedExamId: Number(selectedExam.id) || 0
                    });
                    await routeToResultFromOpeningAttempt(selectedExam);
                    return;
                }
            }

            if (selectedExam.is_class_allowed !== undefined && Number(selectedExam.is_class_allowed) !== 1) {
                markOpeningAttemptTerminalFailure(
                    'Exam ini tidak tersedia untuk kelas akun Anda.',
                    'class_not_allowed',
                    {
                        status: 'Exam tidak tersedia untuk kelas Anda',
                        detail: 'Kembali ke daftar exam untuk memilih exam lain yang sesuai.'
                    }
                );
                return;
            }

            var selectedExamRequiresToken = Number(selectedExam && selectedExam.requires_token ? selectedExam.requires_token : 0) === 1;
            var tokenInputRequired = Number(
                selectedExam && selectedExam.token_input_required !== undefined
                    ? selectedExam.token_input_required
                    : (selectedExamRequiresToken ? 1 : 0)
            ) === 1;
            if (tokenInputRequired && submittedToken === '') {
                markOpeningAttemptTerminalFailure(
                    'Token ujian wajib diisi.',
                    'token_required_local',
                    {
                        status: 'Token ujian dibutuhkan',
                        detail: 'Kembali ke daftar exam untuk mengisi token ujian yang benar.'
                    }
                );
                return;
            }
            if (tokenInputRequired && submittedToken.length !== examTokenLength) {
                markOpeningAttemptTerminalFailure(
                    'Token ujian harus 6 karakter (tanpa 0, O, I, L).',
                    'token_invalid_length',
                    {
                        status: 'Format token ujian belum valid',
                        detail: 'Kembali ke daftar exam untuk memperbaiki token yang diinput.'
                    }
                );
                return;
            }
            if (!tokenInputRequired) {
                submittedToken = '';
            }

            startIntentKey = resolveOpeningAttemptIntentKey(Number(selectedExam && selectedExam.id) || selectedExamId, options);
            rememberOpeningAttemptContext(selectedExam, submittedToken, resumeIntent, startIntentKey);
            beginOpeningEntryFlowMetricContext(selectedExam, startIntentKey, resumeIntent);

            if (isExamFullscreenRequired()) {
                syncFullscreenState(false);
                requestExamFullscreen({
                    silent: true
                }).catch(function () {
                    // Non-blocking; exam stage has its own fullscreen guard UI.
                });
            }

            updateOpeningAttemptProgress(
                12,
                1,
                resumeIntent || forceResumeOnly
                    ? 'Mengecek attempt aktif'
                    : 'Meminta sesi ujian dari server',
                resumeIntent || forceResumeOnly
                    ? 'Kami mencoba menyambungkan Anda ke attempt yang mungkin sudah aktif.'
                    : 'Membuat attempt dan memeriksa token ujian.'
            );
            recordTimelineEntry('attempt:start:request', 'Memulai attempt atau resume session.', {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: Number(selectedExam.id) || 0,
                stage: 'exam',
                resumeIntent: resumeIntent ? 1 : 0
            });

            var startPayload;
            var startStatusPayload = null;
            var recoveredSlowStart = false;

            try {
                if (resumeIntent || forceResumeOnly) {
                    startStatusPayload = await requestStartAttemptStatus({
                        exam_id: Number(selectedExam.id) || 0,
                        resume_only: 1
                    }, {
                        timeoutMs: startAttemptStatusTimeoutMs
                    });
                    assertOpeningAttemptRequestActive(requestId);

                    if (getStartAttemptPayloadStatus(startStatusPayload) === 'resumed') {
                        syncOpeningAttemptServerState(startStatusPayload, {
                            openingState: 'ready',
                            openingReason: 'attempt_ready'
                        });
                        await openAttemptSession(selectedExam, startStatusPayload, requestId);
                        recordTimelineEntry('attempt:start:resumed-status', 'Attempt aktif ditemukan lewat endpoint status ringan.', {
                            attemptId: Number(startStatusPayload && startStatusPayload.attempt_id) || 0,
                            selectedExamId: Number(selectedExam.id) || 0,
                            stage: 'exam'
                        });
                        return;
                    }

                    if (isCompletedStartAttemptStatusPayload(startStatusPayload)) {
                        syncOpeningAttemptServerState(startStatusPayload, {
                            openingState: 'completed',
                            openingReason: 'attempt_completed'
                        });
                        cancelOpeningAttemptRequest();
                        await routeToResultFromOpeningAttempt(selectedExam);
                        return;
                    }

                    if (isTerminalStartAttemptStatusPayload(startStatusPayload)) {
                        syncOpeningAttemptServerState(startStatusPayload, {
                            openingState: 'terminal_error',
                            openingReason: String(startStatusPayload && startStatusPayload.error_code ? startStatusPayload.error_code : '').trim().toLowerCase()
                        });
                        throw buildStartAttemptStatusError(startStatusPayload);
                    }

                    if (isPendingStartAttemptStatusPayload(startStatusPayload)) {
                        applyOpeningPendingStatusPayload(startStatusPayload, {
                            scheduleRetry: false
                        });
                    }
                }

                if (queueTicket !== '') {
                    startPayload = await requestStartAttempt({
                        exam_id: Number(selectedExam.id) || 0,
                        exam_token: submittedToken,
                        queue_ticket: queueTicket
                    });
                } else if (resumeIntent || forceResumeOnly) {
                    startPayload = await requestStartAttempt({
                        exam_id: Number(selectedExam.id) || 0,
                        resume_only: 1
                    }, {
                        timeoutMs: startAttemptStatusTimeoutMs
                    });
                } else {
                    startPayload = await requestStartAttempt({
                        exam_id: Number(selectedExam.id) || 0,
                        exam_token: submittedToken
                    });
                }
                assertOpeningAttemptRequestActive(requestId);

                if (isQueuedStartAttemptPayload(startPayload)) {
                    startPayload = await waitForQueuedStartAttempt(selectedExam, submittedToken, startPayload, requestId);
                    assertOpeningAttemptRequestActive(requestId);
                }
            } catch (startError) {
                if (isOpeningAttemptCancelledError(startError)) {
                    throw startError;
                }

                if ((resumeIntent || forceResumeOnly) && isStartAttemptNotFoundError(startError)) {
                    var finalizedAttemptState = await tryFinalizePendingStartAttempt(selectedExam, requestId);
                    if (finalizedAttemptState.status === 'resumed') {
                        state.error = '';
                        recordTimelineEntry('attempt:start:final-resume', 'Attempt aktif ditemukan setelah pengecekan ulang dan berhasil dibuka.', {
                            attemptId: Number(state.attemptId) || 0,
                            selectedExamId: Number(selectedExam && selectedExam.id) || 0,
                            stage: 'exam'
                        });
                        return;
                    }
                    if (finalizedAttemptState.status === 'completed' && finalizedAttemptState.exam) {
                        cancelOpeningAttemptRequest();
                        await routeToResultFromOpeningAttempt(finalizedAttemptState.exam);
                        return;
                    }
                    if (forceResumeOnly || !allowFreshStartFallback) {
                        throw startError;
                    }
                }

                if (!shouldRecoverSlowStartAttempt(startError)) {
                    throw startError;
                }

                recordTimelineEntry('attempt:start:recovering', startError instanceof Error ? startError.message : 'Request mulai ujian melambat.', {
                    attemptId: Number(state.attemptId) || 0,
                    selectedExamId: Number(selectedExam.id) || 0,
                    stage: 'exam'
                });
                recordActionTrailEntry('attempt:start:recovering', 'Server lambat, mencoba mengambil attempt aktif.', {
                    selectedExamId: Number(selectedExam.id) || 0
                });
                startPayload = await recoverSlowStartAttempt(selectedExam, submittedToken, startError, requestId);
                assertOpeningAttemptRequestActive(requestId);
                recoveredSlowStart = true;
            }

            await openAttemptSession(selectedExam, startPayload, requestId);
            recordTimelineEntry(recoveredSlowStart ? 'attempt:start:recovered' : 'attempt:start:success', recoveredSlowStart ? 'Attempt aktif berhasil dipulihkan setelah server sibuk.' : 'Attempt baru berhasil dimulai.', {
                attemptId: Number(startPayload && startPayload.attempt_id) || 0,
                selectedExamId: Number(selectedExam.id) || 0,
                stage: 'exam'
            });
            recordActionTrailEntry(recoveredSlowStart ? 'attempt:start:recovered' : 'attempt:start:success', recoveredSlowStart ? 'Attempt aktif berhasil dipulihkan.' : 'Attempt baru berhasil dimulai.', {
                attemptId: Number(startPayload && startPayload.attempt_id) || 0,
                selectedExamId: Number(selectedExam.id) || 0
            });
        } catch (error) {
            if (isOpeningAttemptCancelledError(error)) {
                return;
            }

            if (isStartAttemptRecoveryBusyError(error)) {
                var finalizedPendingAttempt = await tryFinalizePendingStartAttempt(selectedExam, requestId);
                if (finalizedPendingAttempt.status === 'resumed') {
                    state.error = '';
                    recordTimelineEntry('attempt:start:final-resume', 'Attempt aktif ditemukan setelah refresh akhir dan berhasil dibuka.', {
                        attemptId: Number(state.attemptId) || 0,
                        selectedExamId: Number(selectedExam && selectedExam.id) || 0,
                        stage: 'exam'
                    });
                    return;
                }
                if (finalizedPendingAttempt.status === 'completed' && finalizedPendingAttempt.exam) {
                    cancelOpeningAttemptRequest();
                    await routeToResultFromOpeningAttempt(finalizedPendingAttempt.exam);
                    return;
                }
            }

            if (isAttemptCompletedError(error)) {
                cancelOpeningAttemptRequest();
                await routeToResultFromOpeningAttempt(selectedExam);
                return;
            }

            var errorMessage = error instanceof Error ? error.message : 'Gagal memulai ujian.';
            var errorCode = getErrorCode(error);
            recordTimelineEntry('attempt:start:error', errorMessage, {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: Number(selectedExam && selectedExam.id) || 0,
                stage: 'exam'
            });
            recordActionTrailEntry('attempt:start:error', errorMessage, {
                selectedExamId: Number(selectedExam && selectedExam.id) || 0
            });

            if (isTerminalStartAttemptError(error)) {
                markOpeningAttemptTerminalFailure(errorMessage, errorCode, {
                    status: errorCode === 'token_invalid'
                        ? 'Token ujian tidak valid'
                        : (errorCode === 'token_required'
                            ? 'Token ujian dibutuhkan'
                            : (errorCode === 'forbidden'
                                ? 'Exam tidak dapat dilanjutkan'
                                : 'Exam tidak tersedia')),
                    detail: errorCode === 'token_invalid' || errorCode === 'token_required'
                        ? 'Kembali ke daftar exam untuk memperbaiki token lalu coba lagi.'
                        : 'Kembali ke daftar exam untuk memeriksa status exam terbaru.',
                    serverStateSource: error
                });
                return;
            }

            markOpeningAttemptTemporaryFailure(errorMessage, errorCode, {
                status: 'Server masih sibuk menyiapkan sesi',
                detail: 'Tetap di layar ini. Kami akan mencoba lagi otomatis dengan jeda aman.',
                canRetry: true,
                canRefreshStatus: true,
                autoRetryAction: Number(state.attemptId) > 0 || state.pendingQueueTicket !== '' ? 'refresh' : 'retry',
                autoRetryReason: 'Server masih menyiapkan sesi. Mencoba lagi otomatis.',
                retrySource: error,
                retryDelayMs: OPENING_RETRY_STATUS_MIN_MS,
                serverStateSource: error
            });
        } finally {
            if (requestId === activeOpeningAttemptRequestId) {
                activeOpeningAttemptRequestId = 0;
                state.busy = false;
                render('attempt-start-finalize', {
                    selectedExamId: selectedExamId
                });
            }
        }
    }

    async function retryOpeningAttempt() {
        var selectedExam = resolvePendingOpeningExam();
        if (!selectedExam) {
            return false;
        }

        if (forceOpeningRetryCountdown('manual_retry')) {
            updateOpeningAttemptProgress(
                Math.max(18, Number(state.openingAttemptProgressPercent) || 18),
                Math.max(1, Number(state.openingAttemptProgressStepIndex) || 1),
                'Mencoba lagi sekarang',
                'Countdown dipercepat. Request yang sama akan dipakai, tanpa membuat request paralel.'
            );
            return true;
        }

        if (activeOpeningAttemptRequestId > 0 || state.openingRetryInFlight === true) {
            beginOpeningAttemptUiAction('retry');
            state.openingRetryInFlight = true;
            updateOpeningAttemptProgress(
                Math.max(18, Number(state.openingAttemptProgressPercent) || 18),
                Math.max(1, Number(state.openingAttemptProgressStepIndex) || 1),
                'Coba Lagi sedang berjalan',
                'Permintaan sebelumnya masih diproses. Kami tidak membuat request paralel.'
            );
            return true;
        }

        if (Number(state.attemptId) > 0 && state.stage === 'exam' && state.isOpeningAttempt) {
            cancelOpeningAttemptRequest();
            beginOpeningAttemptUiAction('retry');
            var retryRequestId = beginOpeningAttemptRequest();
            var retryPayload = {
                attempt_id: Number(state.attemptId) || 0,
                status: 'resumed',
                duration_minutes: Number(selectedExam && selectedExam.duration_minutes) || 0,
                remaining_seconds: Math.max(0, Number(state.remainingSeconds) || 0),
                question_revision: state.questionRevision || null,
                question_order_signature: String(state.questionOrderSignature || '')
            };

            state.busy = true;
            updateOpeningAttemptProgress(
                Math.max(76, Number(state.openingAttemptProgressPercent) || 76),
                4,
                'Memuat ulang soal pertama',
                'Coba Lagi akan memakai attempt yang sudah dibuat, tanpa membuat start baru.'
            );

            try {
                await openAttemptSession(selectedExam, retryPayload, retryRequestId);
                completeOpeningAttemptUiAction('success');
                return true;
            } catch (error) {
                if (isOpeningAttemptCancelledError(error)) {
                    return false;
                }
                completeOpeningAttemptUiAction('pending');
                markOpeningAttemptTemporaryFailure(
                    error instanceof Error ? error.message : 'Soal pertama belum berhasil dimuat.',
                    getErrorCode(error),
                    {
                        status: 'Soal pertama belum siap',
                        detail: 'Tetap di layar ini. Kami akan mencoba memuat soal pertama lagi.',
                        canRetry: true,
                        canRefreshStatus: true,
                        autoRetryAction: 'retry',
                        autoRetryReason: 'Server masih menyiapkan soal pertama.',
                        retrySource: error,
                        retryDelayMs: OPENING_RETRY_BOOTSTRAP_MIN_MS,
                        minDelayMs: OPENING_RETRY_BOOTSTRAP_MIN_MS,
                        maxDelayMs: OPENING_RETRY_BOOTSTRAP_MAX_MS
                    }
                );
                return true;
            } finally {
                if (retryRequestId === activeOpeningAttemptRequestId) {
                    activeOpeningAttemptRequestId = 0;
                    state.busy = false;
                    render('attempt-question-bootstrap-retry', {
                        selectedExamId: Number(selectedExam && selectedExam.id) || 0
                    });
                }
            }
        }

        cancelOpeningAttemptRequest();
        updateOpeningAttemptProgress(
            Math.max(18, Number(state.openingAttemptProgressPercent) || 18),
            Math.max(1, Number(state.openingAttemptProgressStepIndex) || 1),
            'Mengulang permintaan sesi',
            'Permintaan Coba Lagi sedang diproses.'
        );
        return handleStartExam({
            skipExamRefresh: true,
            selectedExam: selectedExam,
            submittedToken: String(state.pendingExamToken || ''),
            resumeIntentOverride: state.pendingResumeIntent === true
        });
    }

    async function refreshOpeningAttemptStatus() {
        var selectedExam = resolvePendingOpeningExam();
        if (!selectedExam) {
            return false;
        }

        if (forceOpeningRetryCountdown('manual_refresh')) {
            updateOpeningAttemptProgress(
                Math.max(16, Number(state.openingAttemptProgressPercent) || 16),
                Math.max(1, Number(state.openingAttemptProgressStepIndex) || 1),
                'Mengecek status sekarang',
                'Countdown dipercepat. Kami tetap memakai intent/tiket yang sama.'
            );
            return true;
        }

        if (activeOpeningAttemptRequestId > 0 || state.openingRetryInFlight === true) {
            beginOpeningAttemptUiAction('refresh');
            state.openingRetryInFlight = true;
            updateOpeningAttemptProgress(
                Math.max(16, Number(state.openingAttemptProgressPercent) || 16),
                Math.max(1, Number(state.openingAttemptProgressStepIndex) || 1),
                'Refresh Status sedang berjalan',
                'Status masih dicek. Kami tidak membuat request paralel.'
            );
            return true;
        }

        cancelOpeningAttemptRequest();
        beginOpeningAttemptUiAction('refresh');
        var requestId = beginOpeningAttemptRequest();
        var selectedExamId = Number(selectedExam && selectedExam.id) || 0;
        var submittedToken = String(state.pendingExamToken || '');
        var queueTicket = String(state.pendingQueueTicket || '');
        var resumeIntent = state.pendingResumeIntent === true;

        state.busy = true;
        updateOpeningAttemptProgress(
            Math.max(16, Number(state.openingAttemptProgressPercent) || 16),
            Math.max(1, Number(state.openingAttemptProgressStepIndex) || 1),
            queueTicket !== '' ? 'Mengecek status antrean' : 'Mengecek status sesi',
            queueTicket !== ''
                ? 'Kami mengecek apakah tiket antrean sudah boleh masuk.'
                : 'Kami mengecek apakah sesi aktif sudah tersedia.'
        );

        try {
            var statusPayload = await requestStartAttemptStatus({
                exam_id: selectedExamId,
                exam_token: submittedToken,
                queue_ticket: queueTicket,
                resume_only: queueTicket === '' ? 1 : 0
            });
            assertOpeningAttemptRequestActive(requestId);

            if (isQueuedStartAttemptPayload(statusPayload)) {
                updateOpeningAttemptQueueState(statusPayload);
                updateOpeningAttemptProgress(
                    20,
                    1,
                    'Menunggu giliran masuk ujian',
                    buildQueuedStartAttemptDetail(statusPayload)
                );
                completeOpeningAttemptUiAction('success');
                return true;
            }

            if (isAdmittedStartAttemptStatusPayload(statusPayload)) {
                syncOpeningAttemptServerState(statusPayload, {
                    openingState: 'queue_waiting',
                    openingReason: 'queue_admission_wait'
                });
                completeOpeningAttemptUiAction('success');
                cancelOpeningAttemptRequest();
                return handleStartExam({
                    skipExamRefresh: true,
                    selectedExam: selectedExam,
                    submittedToken: submittedToken,
                    resumeIntentOverride: resumeIntent,
                    queueTicket: String(statusPayload.queue_ticket || queueTicket),
                    allowFreshStartFallback: false
                });
            }

            if (getStartAttemptPayloadStatus(statusPayload) === 'resumed') {
                syncOpeningAttemptServerState(statusPayload, {
                    openingState: 'ready',
                    openingReason: 'attempt_ready'
                });
                completeOpeningAttemptUiAction('success');
                await openAttemptSession(selectedExam, statusPayload, requestId);
                return true;
            }

            if (isCompletedStartAttemptStatusPayload(statusPayload)) {
                syncOpeningAttemptServerState(statusPayload, {
                    openingState: 'completed',
                    openingReason: 'attempt_completed'
                });
                completeOpeningAttemptUiAction('success');
                cancelOpeningAttemptRequest();
                await routeToResultFromOpeningAttempt(selectedExam);
                return true;
            }

            if (isTerminalStartAttemptStatusPayload(statusPayload)) {
                syncOpeningAttemptServerState(statusPayload, {
                    openingState: 'terminal_error',
                    openingReason: String(statusPayload && statusPayload.error_code ? statusPayload.error_code : '').trim().toLowerCase()
                });
                completeOpeningAttemptUiAction('error');
                throw buildStartAttemptStatusError(statusPayload);
            }

            if (isPendingStartAttemptStatusPayload(statusPayload)) {
                completeOpeningAttemptUiAction('pending');
                applyOpeningPendingStatusPayload(statusPayload);
                return true;
            }

            return true;
        } catch (error) {
            if (isOpeningAttemptCancelledError(error)) {
                return false;
            }

            if (isAttemptCompletedError(error)) {
                completeOpeningAttemptUiAction('success');
                cancelOpeningAttemptRequest();
                await routeToResultFromOpeningAttempt(selectedExam);
                return true;
            }

            if (isTerminalStartAttemptError(error)) {
                completeOpeningAttemptUiAction('error');
                markOpeningAttemptTerminalFailure(
                    error instanceof Error ? error.message : 'Sesi ujian tidak dapat dilanjutkan.',
                    getErrorCode(error),
                    {
                        status: 'Sesi ujian membutuhkan tindakan',
                        detail: 'Periksa pesan berikut lalu kembali ke daftar exam bila perlu.',
                        serverStateSource: error
                    }
                );
                return true;
            }

            completeOpeningAttemptUiAction('pending');
            markOpeningAttemptTemporaryFailure(
                error instanceof Error ? error.message : 'Status sesi belum dapat dipastikan.',
                getErrorCode(error),
                {
                    status: queueTicket !== '' ? 'Status antrean belum dapat dipastikan' : 'Status sesi belum dapat dipastikan',
                    detail: 'Tetap di layar ini. Kami akan mencoba mengecek lagi otomatis.',
                    canRetry: true,
                    canRefreshStatus: true,
                    autoRetryAction: 'refresh',
                    autoRetryReason: queueTicket !== ''
                        ? 'Status antrean belum pasti. Mengecek lagi otomatis.'
                        : 'Status sesi belum pasti. Mengecek lagi otomatis.',
                    retrySource: error,
                    retryDelayMs: queueTicket !== '' ? OPENING_RETRY_QUEUE_FALLBACK_MS : OPENING_RETRY_STATUS_MIN_MS,
                    serverStateSource: error
                }
            );
            return true;
        } finally {
            if (requestId === activeOpeningAttemptRequestId) {
                activeOpeningAttemptRequestId = 0;
                state.busy = false;
                render('attempt-status-refresh', {
                    selectedExamId: selectedExamId
                });
            }
        }
    }

    function cancelOpeningAttemptFlow() {
        cancelOpeningAttemptRequest();
        resetOpeningRetryState();
    }

    async function handleViewResult(options) {
        options = options || {};
        if (state.busy) {
            return;
        }

        clearMessages();
        recordActionTrailEntry('result:view', 'Lihat hasil ujian dipicu.', {});

        var selectedExam = options.selectedExam || getSelectedExam();
        if (!selectedExam) {
            state.error = 'Pilih exam terlebih dahulu.';
            render('view-result-error', {
                reason: 'missing-selected-exam'
            });
            return;
        }

        if (!options.skipExamRefresh) {
            state.busy = true;
            updateResultProgress(
                18,
                1,
                'Memperbarui status exam',
                'Kami sedang mengecek status attempt terbaru sebelum nilai ditampilkan.',
                {
                    render: false
                }
            );
            render('view-result-refresh-selection', {
                selectedExamId: Number(selectedExam.id) || 0
            }, {
                immediate: true,
                skipPostRenderEffects: true
            });

            try {
                var refreshedResultSelection = await resolvePrimaryActionSelection('view-result');
                selectedExam = refreshedResultSelection && refreshedResultSelection.selectedExam
                    ? refreshedResultSelection.selectedExam
                    : null;
                state.busy = false;

                if (!selectedExam) {
                    state.error = 'Exam yang dipilih sudah tidak tersedia.';
                    render('view-result-error', {
                        reason: 'selected-exam-missing-after-refresh'
                    });
                    return;
                }

                if (String(refreshedResultSelection && refreshedResultSelection.action ? refreshedResultSelection.action : '') === 'start-exam') {
                    recordActionTrailEntry('result:view:rerouted', 'Status exam berubah menjadi aktif, mengalihkan ke sesi ujian.', {
                        selectedExamId: Number(selectedExam.id) || 0
                    });
                    return handleStartExam({
                        skipExamRefresh: true,
                        selectedExam: selectedExam
                    });
                }
            } catch (error) {
                state.busy = false;
                state.error = error instanceof Error ? error.message : 'Gagal memperbarui status exam.';
                render('view-result-error', {
                    reason: 'refresh-selection-failed'
                });
                return;
            }
        }

        var attemptId = Number(selectedExam.latest_attempt_id) || 0;
        if (attemptId <= 0) {
            state.error = 'Hasil ujian untuk exam ini belum tersedia.';
            render('view-result-error', {
                reason: 'missing-attempt'
            });
            return;
        }

        state.busy = true;
        recordTimelineEntry('result:view:start', 'Memuat hasil attempt dari daftar exam.', {
            attemptId: attemptId,
            selectedExamId: Number(selectedExam.id) || 0,
            stage: String(state.stage || '')
        });
        updateResultProgress(
            46,
            2,
            'Mengambil hasil attempt',
            'Server sedang mengirim ringkasan nilai, status lulus, dan detail review jawaban.',
            {
                render: false
            }
        );
        render('result-view-request', {
            attemptId: attemptId,
            selectedExamId: Number(selectedExam.id) || 0
        }, {
            immediate: true,
            skipPostRenderEffects: true
        });

        try {
            var reviewPayload = await apiRequest('result', {
                query: {
                    attempt_id: attemptId
                }
            });
            updateResultProgress(
                74,
                3,
                'Menyusun ringkasan nilai',
                'Kami sedang menghitung ulang skor aman, status lulus, dan ringkasan review yang akan ditampilkan.',
                {
                    render: true
                }
            );

            var attemptData = reviewPayload && typeof reviewPayload === 'object'
                ? (reviewPayload.attempt || null)
                : null;
            var showStudentResult = Number(
                reviewPayload && typeof reviewPayload === 'object' && reviewPayload.show_student_result !== undefined
                    ? reviewPayload.show_student_result
                    : (selectedExam && selectedExam.show_student_result !== undefined ? selectedExam.show_student_result : 1)
            ) === 1;
            var resultViewMode = reviewPayload && typeof reviewPayload === 'object' && reviewPayload.result_view_mode
                ? String(reviewPayload.result_view_mode)
                : (showStudentResult ? 'full' : 'restricted');
            var isRestrictedResult = !showStudentResult || resultViewMode.toLowerCase() === 'restricted';
            var score = isRestrictedResult
                ? 0
                : Number(attemptData && attemptData.score !== undefined ? attemptData.score : selectedExam.latest_attempt_score);
            var maxScore = isRestrictedResult
                ? 0
                : Number(attemptData && attemptData.max_score !== undefined ? attemptData.max_score : selectedExam.latest_attempt_max_score);
            var percentage = isRestrictedResult
                ? 0
                : (maxScore > 0
                    ? ((score / maxScore) * 100)
                    : Number(selectedExam.latest_attempt_percentage || 0));
            var passMeta = buildResultPassMeta(
                score,
                maxScore,
                reviewPayload && typeof reviewPayload === 'object'
                    ? reviewPayload.kkm_percentage
                    : selectedExam.kkm_percentage,
                reviewPayload && typeof reviewPayload === 'object' ? reviewPayload.is_passed : selectedExam.latest_attempt_is_passed,
                reviewPayload && typeof reviewPayload === 'object' ? reviewPayload.pass_label : selectedExam.latest_attempt_pass_label,
                reviewPayload && typeof reviewPayload === 'object' ? reviewPayload.result_tone : selectedExam.latest_attempt_result_tone
            );

            state.result = {
                attempt_id: attemptId,
                status: String(attemptData && attemptData.status ? attemptData.status : 'completed'),
                show_student_result: showStudentResult ? 1 : 0,
                result_view_mode: resultViewMode,
                submission_summary: reviewPayload && typeof reviewPayload === 'object' && reviewPayload.submission_summary && typeof reviewPayload.submission_summary === 'object'
                    ? reviewPayload.submission_summary
                    : null,
                score: Number.isFinite(score) ? score : 0,
                max_score: Number.isFinite(maxScore) ? maxScore : 0,
                percentage: Number.isFinite(percentage) ? percentage : 0,
                kkm_percentage: isRestrictedResult ? 0 : passMeta.kkm_percentage,
                passing_score: isRestrictedResult ? 0 : passMeta.passing_score,
                is_passed: isRestrictedResult ? 0 : passMeta.is_passed,
                pass_label: isRestrictedResult ? '' : passMeta.pass_label,
                result_tone: isRestrictedResult ? '' : passMeta.result_tone,
                attempt: attemptData,
                exam: reviewPayload && typeof reviewPayload === 'object'
                    ? (reviewPayload.exam || selectedExam)
                    : selectedExam,
                answers: !isRestrictedResult && reviewPayload && typeof reviewPayload === 'object' && Array.isArray(reviewPayload.answers)
                    ? reviewPayload.answers
                    : [],
                review_items: !isRestrictedResult && reviewPayload && typeof reviewPayload === 'object' && Array.isArray(reviewPayload.review_items)
                    ? reviewPayload.review_items
                    : [],
                review_summary: !isRestrictedResult && reviewPayload && typeof reviewPayload === 'object' && reviewPayload.review_summary && typeof reviewPayload.review_summary === 'object'
                    ? reviewPayload.review_summary
                    : null
            };
            updateResultProgress(
                92,
                4,
                'Menyiapkan halaman hasil',
                'Menyusun tampilan nilai akhir, progress jawaban, dan review soal untuk Anda.',
                {
                    render: true
                }
            );
            await ensureResultStageRenderer({
                renderOnResolve: false
            }).catch(function () {
                // Keep the result payload intact; result stage fallback can still render score and retry affordances.
            });
            state.stage = 'result';
            state.success = 'Menampilkan hasil ujian.';
            state.error = '';
            prefetchResultStageRenderer();
            recordTimelineEntry('result:view:ready', 'Hasil attempt siap ditampilkan.', {
                attemptId: attemptId,
                selectedExamId: Number(selectedExam.id) || 0,
                stage: 'result'
            });
        } catch (error) {
            state.error = error instanceof Error ? error.message : 'Gagal memuat hasil ujian.';
            recordTimelineEntry('result:view:error', state.error, {
                attemptId: attemptId,
                selectedExamId: Number(selectedExam.id) || 0,
                stage: String(state.stage || '')
            });
        } finally {
            state.isOpeningAttempt = false;
            state.busy = false;
            resetResultProgressState();
            render();
        }
    }

    return {
        handleLogin: handleLogin,
        handleStartExam: handleStartExam,
        handleViewResult: handleViewResult,
        loadExams: loadExams,
        openAttemptSession: openAttemptSession,
        retryOpeningAttempt: retryOpeningAttempt,
        refreshOpeningAttemptStatus: refreshOpeningAttemptStatus,
        cancelOpeningAttemptFlow: cancelOpeningAttemptFlow,
        tryResumeActiveAttemptFromExamList: tryResumeActiveAttemptFromExamList
    };
}
