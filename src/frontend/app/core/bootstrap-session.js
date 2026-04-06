export function createBootstrapSessionManager(deps) {
    var CONFIRM_RECOVERY_STEP_TOTAL = 4;
    var EXAM_RECOVERY_STEP_TOTAL = 7;
    var RECOVERY_SLOW_STAGE_BUSY_DELAY_MS = 5000;
    var RECOVERY_SLOW_STAGE_HOLD_DELAY_MS = 15000;
    var clearMessages = deps.clearMessages;
    var fullLogout = deps.fullLogout;
    var loadExams = deps.loadExams;
    var persistAuthSession = deps.persistAuthSession;
    var readPersistedAuthSession = deps.readPersistedAuthSession;
    var reconcilePendingPageRefreshSecurityEvent = deps.reconcilePendingPageRefreshSecurityEvent;
    var render = deps.render;
    var startSessionHeartbeat = deps.startSessionHeartbeat;
    var state = deps.state;
    var triggerPendingSyncLifecycleRetry = deps.triggerPendingSyncLifecycleRetry;
    var tryResumeActiveAttemptFromExamList = deps.tryResumeActiveAttemptFromExamList;
    var windowRef = deps.windowRef || (typeof window !== 'undefined' ? window : globalThis);
    var activeRecoveryRunId = 0;
    var recoverySlowStageTimerIds = [];

    function clearSessionRecoveryTimers() {
        if (!windowRef || typeof windowRef.clearTimeout !== 'function') {
            recoverySlowStageTimerIds = [];
            return;
        }

        recoverySlowStageTimerIds.forEach(function (timerId) {
            windowRef.clearTimeout(timerId);
        });
        recoverySlowStageTimerIds = [];
    }

    function getSessionRecoveryStepTotal(mode) {
        return mode === 'exam_restore'
            ? EXAM_RECOVERY_STEP_TOTAL
            : CONFIRM_RECOVERY_STEP_TOTAL;
    }

    function isActiveRecoveryRun(runId) {
        return Number(runId) > 0 && Number(runId) === activeRecoveryRunId;
    }

    function resetSessionRecoveryState() {
        state.sessionRecoveryVisible = false;
        state.sessionRecoveryMode = '';
        state.sessionRecoveryStepIndex = 0;
        state.sessionRecoveryStepTotal = 0;
        state.sessionRecoveryPercent = 0;
        state.sessionRecoveryStatus = '';
        state.sessionRecoveryDetail = '';
        state.sessionRecoveryCanRetry = false;
        state.sessionRecoveryRetryCount = 0;
        state.sessionRecoveryStartedAt = 0;
        state.sessionRecoverySlowStage = '';
    }

    function closeSessionRecovery(runId) {
        if (runId && !isActiveRecoveryRun(runId)) {
            return;
        }

        clearSessionRecoveryTimers();
        resetSessionRecoveryState();
    }

    function updateSessionRecoveryProgress(mode, stepIndex, status, detail, options) {
        options = options || {};
        if (options.runId && !isActiveRecoveryRun(options.runId)) {
            return false;
        }

        var safeMode = String(mode || 'confirm_restore').toLowerCase() === 'exam_restore'
            ? 'exam_restore'
            : 'confirm_restore';
        var stepTotal = getSessionRecoveryStepTotal(safeMode);
        var safeStepIndex = Number(stepIndex);
        var percent = Number(options.percent);
        var shouldRender = options.render !== false;

        if (!Number.isFinite(safeStepIndex)) {
            safeStepIndex = 0;
        }
        if (!Number.isFinite(percent)) {
            percent = stepTotal > 0 ? (safeStepIndex / stepTotal) * 100 : 0;
        }

        state.sessionRecoveryVisible = true;
        state.sessionRecoveryMode = safeMode;
        state.sessionRecoveryStepIndex = Math.max(0, Math.min(stepTotal, safeStepIndex));
        state.sessionRecoveryStepTotal = stepTotal;
        state.sessionRecoveryPercent = Math.max(0, Math.min(100, percent));
        state.sessionRecoveryStatus = String(status || '');
        state.sessionRecoveryDetail = String(detail || '');

        if (shouldRender && typeof render === 'function') {
            render('session-recovery-progress', {
                mode: safeMode,
                percent: state.sessionRecoveryPercent,
                retryCount: Number(state.sessionRecoveryRetryCount) || 0,
                selectedExamId: Number(state.selectedExamId) || 0,
                stepIndex: state.sessionRecoveryStepIndex
            });
        }

        return true;
    }

    function scheduleSessionRecoverySlowStageTimers(runId) {
        clearSessionRecoveryTimers();
        if (!windowRef || typeof windowRef.setTimeout !== 'function') {
            return;
        }

        recoverySlowStageTimerIds.push(windowRef.setTimeout(function () {
            if (!isActiveRecoveryRun(runId) || !state.sessionRecoveryVisible) {
                return;
            }

            state.sessionRecoverySlowStage = 'busy';
            state.sessionRecoveryCanRetry = false;
            if (typeof render === 'function') {
                render('session-recovery-slow-busy', {
                    retryCount: Number(state.sessionRecoveryRetryCount) || 0,
                    selectedExamId: Number(state.selectedExamId) || 0
                });
            }
        }, RECOVERY_SLOW_STAGE_BUSY_DELAY_MS));

        recoverySlowStageTimerIds.push(windowRef.setTimeout(function () {
            if (!isActiveRecoveryRun(runId) || !state.sessionRecoveryVisible) {
                return;
            }

            state.sessionRecoverySlowStage = 'hold';
            state.sessionRecoveryCanRetry = true;
            if (typeof render === 'function') {
                render('session-recovery-slow-hold', {
                    retryCount: Number(state.sessionRecoveryRetryCount) || 0,
                    selectedExamId: Number(state.selectedExamId) || 0
                });
            }
        }, RECOVERY_SLOW_STAGE_HOLD_DELAY_MS));
    }

    function startSessionRecovery(mode, options) {
        options = options || {};
        activeRecoveryRunId += 1;
        clearSessionRecoveryTimers();

        var retryCount = Math.max(0, Number(options.retryCount) || 0);
        state.sessionRecoveryVisible = true;
        state.sessionRecoveryMode = String(mode || 'confirm_restore').toLowerCase() === 'exam_restore'
            ? 'exam_restore'
            : 'confirm_restore';
        state.sessionRecoveryStepIndex = 0;
        state.sessionRecoveryStepTotal = getSessionRecoveryStepTotal(state.sessionRecoveryMode);
        state.sessionRecoveryPercent = 0;
        state.sessionRecoveryStatus = '';
        state.sessionRecoveryDetail = '';
        state.sessionRecoveryCanRetry = false;
        state.sessionRecoveryRetryCount = retryCount;
        state.sessionRecoveryStartedAt = Date.now();
        state.sessionRecoverySlowStage = 'normal';

        scheduleSessionRecoverySlowStageTimers(activeRecoveryRunId);
        return activeRecoveryRunId;
    }

    function hasActiveResumeCandidate(selectedOnly) {
        if (!Array.isArray(state.exams) || !state.exams.length) {
            return false;
        }

        var selectedExamId = Number(state.selectedExamId) || 0;
        var candidates = selectedOnly && selectedExamId > 0
            ? state.exams.filter(function (exam) {
                return Number(exam && exam.id) === selectedExamId;
            })
            : state.exams;

        return candidates.some(function (exam) {
            return Number(exam && exam.latest_attempt_id) > 0
                && String(exam && exam.latest_attempt_status ? exam.latest_attempt_status : '').toLowerCase() === 'in_progress';
        });
    }

    function shouldAutoResumePersistedAttempt(persisted) {
        return String(persisted && persisted.lastStage ? persisted.lastStage : '').toLowerCase() === 'exam';
    }

    async function bootstrapFromPersistedSession(options) {
        options = options || {};
        var persisted = readPersistedAuthSession();
        if (!persisted) {
            closeSessionRecovery(activeRecoveryRunId);
            render();
            return;
        }

        var retryCount = options.incrementRetry
            ? (Math.max(0, Number(state.sessionRecoveryRetryCount) || 0) + 1)
            : 0;
        var recoveryRunId = startSessionRecovery('confirm_restore', {
            retryCount: retryCount
        });

        state.token = persisted.token;
        state.user = persisted.user;
        state.selectedExamId = persisted.selectedExamId;
        state.stage = 'confirm';
        state.busy = true;
        clearMessages();
        updateSessionRecoveryProgress(
            'confirm_restore',
            1,
            'Memulihkan sesi login',
            'Menyiapkan token login tersimpan dan identitas peserta.',
            {
                percent: 16,
                render: false,
                runId: recoveryRunId
            }
        );
        render();

        try {
            updateSessionRecoveryProgress(
                'confirm_restore',
                2,
                'Memuat daftar ujian',
                'Kami sedang mengambil daftar ujian terbaru untuk akun ini.',
                {
                    percent: 38,
                    runId: recoveryRunId
                }
            );
            await loadExams();
            if (!isActiveRecoveryRun(recoveryRunId)) {
                return;
            }

            updateSessionRecoveryProgress(
                'confirm_restore',
                3,
                'Mengecek attempt aktif',
                'Sistem sedang mencari apakah ada sesi ujian yang perlu disambungkan lagi.',
                {
                    percent: 56,
                    runId: recoveryRunId
                }
            );
            var shouldAutoResume = shouldAutoResumePersistedAttempt(persisted);
            if (shouldAutoResume && hasActiveResumeCandidate(Number(persisted.selectedExamId) > 0)) {
                updateSessionRecoveryProgress(
                    'exam_restore',
                    4,
                    'Menyambung attempt ujian',
                    'Attempt aktif ditemukan. Sistem sedang menyambungkan Anda kembali ke sesi ujian terakhir.',
                    {
                        percent: 54,
                        runId: recoveryRunId
                    }
                );
            }
            var resumed = false;
            if (shouldAutoResume) {
                resumed = await tryResumeActiveAttemptFromExamList({
                    selectedOnly: Number(persisted.selectedExamId) > 0
                });
            }
            if (!isActiveRecoveryRun(recoveryRunId)) {
                return;
            }
            if (resumed) {
                if (typeof reconcilePendingPageRefreshSecurityEvent === 'function') {
                    reconcilePendingPageRefreshSecurityEvent();
                }
                persistAuthSession();
                startSessionHeartbeat();
                state.busy = false;
                triggerPendingSyncLifecycleRetry('bootstrap-resume', {
                    delayMs: 220
                });
                closeSessionRecovery(recoveryRunId);
                render();
                return;
            }

            updateSessionRecoveryProgress(
                'confirm_restore',
                4,
                'Menyiapkan halaman konfirmasi',
                'Sesi berhasil dipulihkan dan halaman konfirmasi siap dipakai kembali.',
                {
                    percent: 100,
                    runId: recoveryRunId
                }
            );
            state.stage = 'confirm';
            state.error = '';
            state.success = '';
            persistAuthSession();
            startSessionHeartbeat();
            state.busy = false;
            triggerPendingSyncLifecycleRetry('bootstrap-session', {
                delayMs: 220
            });
            closeSessionRecovery(recoveryRunId);
            render();
        } catch (error) {
            if (!isActiveRecoveryRun(recoveryRunId)) {
                return;
            }

            closeSessionRecovery(recoveryRunId);
            if (!state.token) {
                state.busy = false;
                render();
                return;
            }

            fullLogout();
            state.error = error instanceof Error && error.message ? error.message : 'Sesi login berakhir. Silakan login lagi.';
            render();
        }
    }

    function retrySessionRecovery() {
        if (!state.sessionRecoveryVisible) {
            return Promise.resolve(false);
        }

        return bootstrapFromPersistedSession({
            incrementRetry: true
        }).then(function () {
            return true;
        });
    }

    return {
        bootstrapFromPersistedSession: bootstrapFromPersistedSession,
        retrySessionRecovery: retrySessionRecovery
    };
}
