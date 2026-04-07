export function createExamSessionManager(deps) {
    var LOGIN_PROGRESS_STEP_TOTAL = 4;
    var OPEN_ATTEMPT_PROGRESS_STEP_TOTAL = 5;
    var RESULT_PROGRESS_STEP_TOTAL = 4;
    var START_ATTEMPT_TIMEOUT_MESSAGE = 'Gagal menyiapkan sesi ujian. Server terlalu lama merespons.';
    var recordTimeline = deps.recordTimeline;
    var state = deps.state;
    var apiRequest = deps.apiRequest;
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
    var startAttemptRecoveryTimeoutMs = Math.max(5000, Number(deps.startAttemptRecoveryTimeoutMs) || 30000);
    var startAttemptRecoveryPollDelayMs = Math.max(0, Number(deps.startAttemptRecoveryPollDelayMs) || 1200);
    var questionWindowSize = Math.max(1, Number(deps.questionWindowSize) || 1);
    var SESSION_RECOVERY_EXAM_STEP_TOTAL = 7;

    function resetOpeningAttemptProgressState() {
        state.openingAttemptProgressPercent = 0;
        state.openingAttemptProgressStepIndex = 0;
        state.openingAttemptProgressStepTotal = 0;
        state.openingAttemptProgressStatus = '';
        state.openingAttemptProgressDetail = '';
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
            });
        }
    }

    function updateOpeningAttemptProgress(percent, stepIndex, status, detail, options) {
        var safePercent = Number(percent);
        var safeStepIndex = Number(stepIndex);
        var shouldRender = !(options && options.render === false);

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
            });
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

    function delay(ms) {
        var waitMs = Math.max(0, Number(ms) || 0);
        if (waitMs <= 0) {
            return Promise.resolve();
        }

        return new Promise(function (resolve) {
            setTimeout(resolve, waitMs);
        });
    }

    function withTimeout(promise, timeoutMs, timeoutMessage) {
        var settled = false;

        return new Promise(function (resolve, reject) {
            var timeoutId = setTimeout(function () {
                if (settled) {
                    return;
                }
                settled = true;
                reject(new Error(timeoutMessage));
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
        return error instanceof Error && String(error.message || '') === START_ATTEMPT_TIMEOUT_MESSAGE;
    }

    function isStartAttemptLockError(error) {
        var status = Number(error && error.status) || 0;
        var code = String(error && error.code ? error.code : '').trim().toLowerCase();
        return status === 429 || code === 'attempt_lock_active';
    }

    function isStartAttemptNotFoundError(error) {
        var status = Number(error && error.status) || 0;
        var code = String(error && error.code ? error.code : '').trim().toLowerCase();
        return status === 404 || code === 'attempt_not_found';
    }

    function shouldRecoverSlowStartAttempt(error) {
        return isStartAttemptTimeoutError(error) || isStartAttemptLockError(error);
    }

    function isRetryableStartAttemptRecoveryError(error) {
        return shouldRecoverSlowStartAttempt(error) || isStartAttemptNotFoundError(error);
    }

    function isQueuedStartAttemptPayload(payload) {
        return !!payload
            && typeof payload === 'object'
            && String(payload.status || '').toLowerCase() === 'queued'
            && String(payload.queue_ticket || '').trim() !== '';
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
        return withTimeout(
            apiRequest('start_attempt', {
                method: 'POST',
                body: body
            }),
            Math.max(5000, Number(options.timeoutMs) || startAttemptTimeoutMs),
            String(options.timeoutMessage || START_ATTEMPT_TIMEOUT_MESSAGE)
        );
    }

    async function recoverSlowStartAttempt(selectedExam, submittedToken, triggerError) {
        var examId = Number(selectedExam && selectedExam.id) || 0;
        var recoveryDeadlineAt = Date.now() + startAttemptRecoveryTimeoutMs;
        var lastError = triggerError;
        var hasRetriedFreshStart = false;
        var resumeTimeoutMs = Math.max(5000, Math.min(startAttemptTimeoutMs, 8000));

        while (Date.now() <= recoveryDeadlineAt) {
            updateOpeningAttemptProgress(
                18,
                1,
                'Server masih menyiapkan sesi ujian',
                'Request awal sedang kami pantau. Anda tidak perlu menekan tombol lagi.'
            );
            await delay(startAttemptRecoveryPollDelayMs);

            try {
                updateOpeningAttemptProgress(
                    24,
                    1,
                    'Mengecek attempt aktif',
                    'Kami mencoba melanjutkan ke sesi yang mungkin sudah berhasil dibuat.'
                );
                return await requestStartAttempt({
                    exam_id: examId,
                    resume_only: 1
                }, {
                    timeoutMs: resumeTimeoutMs
                });
            } catch (resumeError) {
                lastError = resumeError;
                if (!isRetryableStartAttemptRecoveryError(resumeError)) {
                    throw resumeError;
                }

                if (!isStartAttemptNotFoundError(resumeError) || hasRetriedFreshStart) {
                    continue;
                }

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
                    if (!isRetryableStartAttemptRecoveryError(retryError)) {
                        throw retryError;
                    }
                }
            }
        }

        if (lastError && !isRetryableStartAttemptRecoveryError(lastError)) {
            throw lastError;
        }

        throw new Error('Server masih sibuk menyiapkan sesi ujian. Coba lagi beberapa saat.');
    }

    async function waitForQueuedStartAttempt(selectedExam, submittedToken, queuedPayload) {
        var examId = Number(selectedExam && selectedExam.id) || 0;
        var activePayload = queuedPayload;
        var hasRetriedFreshStart = false;
        var hasLoggedQueueState = false;
        var pollTimeoutMs = Math.max(5000, Math.min(startAttemptTimeoutMs, 8000));
        var queueDeadlineAt = Date.now() + Math.max(startAttemptRecoveryTimeoutMs, 30000);

        while (isQueuedStartAttemptPayload(activePayload)) {
            if (Date.now() > queueDeadlineAt) {
                return await recoverSlowStartAttempt(
                    selectedExam,
                    submittedToken,
                    new Error('Server masih menahan antrean start attempt.')
                );
            }

            var queueTicket = String(activePayload && activePayload.queue_ticket ? activePayload.queue_ticket : '').trim();
            var queuePosition = Math.max(0, Number(activePayload && activePayload.queue_position) || 0);
            var pollAfterMs = Math.max(0, Number(activePayload && activePayload.poll_after_ms) || 1000);
            var estimatedWaitSeconds = Math.max(0, Number(activePayload && activePayload.estimated_wait_seconds) || 0);

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

            await delay(pollAfterMs);

            try {
                activePayload = await requestStartAttempt({
                    exam_id: examId,
                    exam_token: submittedToken,
                    queue_ticket: queueTicket
                }, {
                    timeoutMs: pollTimeoutMs,
                    timeoutMessage: 'Masih menunggu giliran sesi ujian.'
                });
            } catch (pollError) {
                if (shouldRecoverSlowStartAttempt(pollError)) {
                    return await recoverSlowStartAttempt(selectedExam, submittedToken, pollError);
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
        resetQuestionDataState();
    }

    async function loadExams() {
        var payload = await apiRequest('exams');
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
        render();

        try {
            var loginPayload = await apiRequest('login', {
                method: 'POST',
                auth: false,
                body: {
                    identifier: identifier,
                    password: password
                }
            });

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
            await loadExams();
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
        } catch (error) {
            state.error = error instanceof Error ? error.message : 'Login gagal.';
            resetAuthProgressState();
            recordTimelineEntry('login:error', state.error, {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || '')
            });
        } finally {
            state.busy = false;
            resetAuthProgressState();
            render();
        }
    }

    async function openAttemptSession(selectedExam, startPayload) {
        state.attemptId = Number(startPayload && startPayload.attempt_id) || 0;
        if (state.attemptId <= 0) {
            throw new Error('Attempt ID tidak valid.');
        }

        state.stage = 'exam';
        state.isOpeningAttempt = true;
        state.error = '';
        state.notice = '';
        state.success = '';
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
        await ensureExamRuntimeBundle();
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

        var attemptUiStatePayload = null;
        var attemptUiStateRequestFailed = false;
        try {
            var uiStatePayload = await apiRequest('ui_state', {
                query: {
                    attempt_id: state.attemptId
                }
            });
            if (uiStatePayload && uiStatePayload.attempt_state && typeof uiStatePayload.attempt_state === 'object') {
                attemptUiStatePayload = uiStatePayload.attempt_state;
            }
        } catch (error) {
            attemptUiStateRequestFailed = true;
        }

        var localAttemptUiState = readPersistedAttemptUiState(state.attemptId);
        var restoredQuestionCacheSnapshot = await readPersistedQuestionCache(state.attemptId);
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

        var preferredAttemptUiState = choosePreferredAttemptUiState(
            attemptUiStateRequestFailed ? null : attemptUiStatePayload,
            localAttemptUiState,
            restoredQuestionCacheSnapshot,
            state.attemptId
        );
        var requestedResumeIndex = Math.max(
            0,
            Math.floor(Number(preferredAttemptUiState && preferredAttemptUiState.current_index !== undefined
                ? preferredAttemptUiState.current_index
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
        updateOpeningAttemptProgress(
            76,
            4,
            'Memuat soal awal',
            'Mengambil jendela soal pertama dan jawaban yang sudah pernah tersimpan.'
        );
        try {
            await loadQuestionWindow(
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
                }
            );
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

        applyAttemptUiState(preferredAttemptUiState, state.attemptId);
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
        await ensureQuestionWindowForIndex(state.currentIndex, {
            examId: examId,
            attemptId: state.attemptId,
            includeExisting: includeExisting,
            limit: questionWindowSize
        });
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
        if (state.busy) {
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

        if (!options.skipExamRefresh) {
            state.busy = true;
            render('start-exam-refresh-selection', {
                selectedExamId: Number(selectedExam.id) || 0
            });

            try {
                var refreshedStartSelection = await resolvePrimaryActionSelection('start-exam');
                selectedExam = refreshedStartSelection && refreshedStartSelection.selectedExam
                    ? refreshedStartSelection.selectedExam
                    : null;
                state.busy = false;

                if (!selectedExam) {
                    state.error = 'Exam yang dipilih sudah tidak tersedia.';
                    render('start-exam-error', {
                        reason: 'selected-exam-missing-after-refresh'
                    });
                    return;
                }

                if (String(refreshedStartSelection && refreshedStartSelection.action ? refreshedStartSelection.action : '') === 'view-result') {
                    recordActionTrailEntry('attempt:start:rerouted', 'Status exam berubah menjadi selesai, mengalihkan ke hasil ujian.', {
                        selectedExamId: Number(selectedExam.id) || 0
                    });
                    return handleViewResult({
                        skipExamRefresh: true,
                        selectedExam: selectedExam
                    });
                }
            } catch (error) {
                state.busy = false;
                state.error = error instanceof Error ? error.message : 'Gagal memperbarui status exam.';
                render('start-exam-error', {
                    reason: 'refresh-selection-failed'
                });
                return;
            }
        }

        if (selectedExam.is_class_allowed !== undefined && Number(selectedExam.is_class_allowed) !== 1) {
            state.error = 'Exam ini tidak tersedia untuk kelas akun Anda.';
            render('start-exam-error', {
                reason: 'class-not-allowed'
            });
            return;
        }

        var submittedToken = normalizeExamToken(state.examToken);
        var selectedExamRequiresToken = Number(selectedExam && selectedExam.requires_token ? selectedExam.requires_token : 0) === 1;
        var tokenInputRequired = Number(
            selectedExam && selectedExam.token_input_required !== undefined
                ? selectedExam.token_input_required
                : (selectedExamRequiresToken ? 1 : 0)
        ) === 1;
        if (tokenInputRequired && submittedToken === '') {
            state.error = 'Token ujian wajib diisi.';
            render('start-exam-error', {
                reason: 'missing-token'
            });
            return;
        }
        if (tokenInputRequired && submittedToken.length !== examTokenLength) {
            state.error = 'Token ujian harus 6 karakter (tanpa 0, O, I, L).';
            render('start-exam-error', {
                reason: 'invalid-token-length'
            });
            return;
        }
        if (!tokenInputRequired) {
            submittedToken = '';
        }

        var shouldExitFullscreenOnFailure = false;
        if (isExamFullscreenRequired()) {
            syncFullscreenState(false);
            shouldExitFullscreenOnFailure = false;
            requestExamFullscreen({
                silent: true
            }).catch(function () {
                // Non-blocking; exam stage has its own fullscreen guard UI.
            });
        }

        state.stage = 'exam';
        state.isOpeningAttempt = true;
        state.busy = true;
        updateOpeningAttemptProgress(
            12,
            1,
            'Meminta sesi ujian dari server',
            'Membuat attempt dan memeriksa token ujian.'
        );
        recordTimelineEntry('attempt:start:request', 'Memulai attempt baru.', {
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: Number(selectedExam.id) || 0,
            stage: 'confirm'
        });

        try {
            var startPayload;
            var recoveredSlowStart = false;

            try {
                startPayload = await requestStartAttempt({
                    exam_id: Number(selectedExam.id) || 0,
                    exam_token: submittedToken
                });
                if (isQueuedStartAttemptPayload(startPayload)) {
                    startPayload = await waitForQueuedStartAttempt(selectedExam, submittedToken, startPayload);
                }
            } catch (startError) {
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
                startPayload = await recoverSlowStartAttempt(selectedExam, submittedToken, startError);
                recoveredSlowStart = true;
            }

            await openAttemptSession(selectedExam, startPayload);
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
            resetOpeningAttemptState();
            state.stage = 'confirm';
            if (shouldExitFullscreenOnFailure) {
                exitFullscreenSilently();
                syncFullscreenState(false);
            }
            state.error = error instanceof Error ? error.message : 'Gagal memulai ujian.';
            recordTimelineEntry('attempt:start:error', state.error, {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: Number(selectedExam.id) || 0,
                stage: 'confirm'
            });
            recordActionTrailEntry('attempt:start:error', state.error, {
                selectedExamId: Number(selectedExam.id) || 0
            });
        } finally {
            state.isOpeningAttempt = false;
            state.busy = false;
            resetOpeningAttemptProgressState();
            render('attempt-start-finalize', {
                selectedExamId: Number(selectedExam && selectedExam.id) || 0
            });
        }
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
        tryResumeActiveAttemptFromExamList: tryResumeActiveAttemptFromExamList
    };
}
