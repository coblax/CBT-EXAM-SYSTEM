export function createExamSessionManager(deps) {
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
    var questionWindowSize = Math.max(1, Number(deps.questionWindowSize) || 1);

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

    async function requestStartAttempt(body) {
        return withTimeout(
            apiRequest('start_attempt', {
                method: 'POST',
                body: body
            }),
            startAttemptTimeoutMs,
            'Gagal menyiapkan sesi ujian. Server terlalu lama merespons.'
        );
    }

    function resetOpeningAttemptState() {
        state.attemptId = 0;
        state.remainingSeconds = 0;
        state.isOpeningAttempt = false;
        state.isFinishing = false;
        state.result = null;
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
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

            await loadExams();
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
        } catch (error) {
            state.error = error instanceof Error ? error.message : 'Login gagal.';
            recordTimelineEntry('login:error', state.error, {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || '')
            });
        } finally {
            state.busy = false;
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
        render();

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
        if (restoredQuestionCache) {
            state.questions = [];
            state.questionPayloadById = {};
            state.loadedQuestionWindowOffsets = {};
            state.windowOffset = 0;
            state.windowLimit = 0;
        }

        recordTimelineEntry('question-window:load:start', 'Memuat question window awal.', {
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: examId,
            stage: 'exam',
            currentIndex: Number(requestedResumeIndex) || 0
        });
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

    async function handleStartExam() {
        if (state.busy) {
            return;
        }

        clearMessages();
        clearAutoSaveRuntimeState();
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        recordActionTrailEntry('attempt:start', 'Mulai ujian dipicu.', {});

        var selectedExam = getSelectedExam();
        if (!selectedExam) {
            state.error = 'Pilih exam terlebih dahulu.';
            render('start-exam-error', {
                reason: 'missing-selected-exam'
            });
            return;
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
        recordTimelineEntry('attempt:start:request', 'Memulai attempt baru.', {
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: Number(selectedExam.id) || 0,
            stage: 'confirm'
        });
        render('attempt-start-request', {
            selectedExamId: Number(selectedExam.id) || 0
        });

        try {
            var startPayload = await requestStartAttempt({
                exam_id: Number(selectedExam.id) || 0,
                exam_token: submittedToken
            });

            await openAttemptSession(selectedExam, startPayload);
            recordTimelineEntry('attempt:start:success', 'Attempt baru berhasil dimulai.', {
                attemptId: Number(startPayload && startPayload.attempt_id) || 0,
                selectedExamId: Number(selectedExam.id) || 0,
                stage: 'exam'
            });
            recordActionTrailEntry('attempt:start:success', 'Attempt baru berhasil dimulai.', {
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
            render('attempt-start-finalize', {
                selectedExamId: Number(selectedExam && selectedExam.id) || 0
            });
        }
    }

    async function handleViewResult() {
        if (state.busy) {
            return;
        }

        clearMessages();
        recordActionTrailEntry('result:view', 'Lihat hasil ujian dipicu.', {});

        var selectedExam = getSelectedExam();
        if (!selectedExam) {
            state.error = 'Pilih exam terlebih dahulu.';
            render('view-result-error', {
                reason: 'missing-selected-exam'
            });
            return;
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
