import {
    FONT_SCALE_DEFAULT,
    FONT_SCALE_MAX,
    FONT_SCALE_MIN,
    FONT_SCALE_STEP,
    UI_PREF_STORAGE_KEY
} from '../core/config.js';
import { createApiClient } from '../core/api.js';
import { createAppMetaManager } from '../core/app-meta.js';
import { createAppShellManager } from '../core/app-shell.js';
import { createAuthStageManager } from '../core/auth-stages.js';
import { createUiPreferencesManager } from '../core/ui-preferences.js';
import { createReadonlyApiCache } from '../storage/readonly-api-cache.js';
import { escapeHtml } from '../core/html.js';
import {
    formatDateTime,
    formatDateTimeCompact,
    formatQuestionType,
    formatScoreValue,
    formatSeconds,
    normalizeExamToken
} from '../core/format.js';

var RESULT_PROGRESS_STEP_TOTAL = 4;

export function createAuthenticatedStageRuntime(context, options) {
    options = options || {};

    var root = context.root;
    var state = context.state;
    var config = context.config;
    var browserStorage = context.browserStorage;
    var authSession = context.authSession;
    var windowRef = typeof window !== 'undefined' ? window : globalThis;
    var documentRef = windowRef.document || document;
    var fetchImpl = typeof windowRef.fetch === 'function'
        ? windowRef.fetch.bind(windowRef)
        : (typeof globalThis.fetch === 'function'
            ? globalThis.fetch.bind(globalThis)
            : function () {
                return Promise.reject(new Error('Fetch API tidak tersedia.'));
            });
    var stage = String(options.stage || state.stage || 'confirm');
    var mounted = true;
    var appShellManager;

    state.stage = stage;

    var uiPreferencesManager = createUiPreferencesManager({
        getLocalStorage: browserStorage.getLocalStorage,
        root: root,
        state: state,
        storageKey: UI_PREF_STORAGE_KEY,
        windowRef: windowRef
    });
    var persistedUiPreferences = uiPreferencesManager.readPersistedUiPreferences();
    if (persistedUiPreferences) {
        state.uiTheme = persistedUiPreferences.theme;
        state.fontScale = persistedUiPreferences.fontScale;
        state.navPanelPosition = persistedUiPreferences.navPanelPosition;
        state.calculatorPosition = persistedUiPreferences.calculatorPosition;
    }

    var appMetaManager = createAppMetaManager({
        config: config,
        escapeHtml: escapeHtml,
        state: state,
        windowRef: windowRef
    });

    var apiClient = createApiClient({
        config: config,
        diagnosticsManager: context.diagnosticsManager,
        expireAuthSession: expireAuthSession,
        fetchImpl: fetchImpl,
        getNavigatorConnectionStatus: appMetaManager.getNavigatorConnectionStatus,
        isAnswerSubmitPath: function () {
            return false;
        },
        readOnlyApiCache: createReadonlyApiCache({
            getIndexedDb: browserStorage.getIndexedDb,
            getLocalStorage: browserStorage.getLocalStorage,
            state: state
        }),
        schedulePendingAnswerRetry: function () {},
        setConnectionStatus: setConnectionStatus,
        state: state,
        windowRef: windowRef
    });

    context.api = apiClient.api;

    var authStageManager = createAuthStageManager({
        clearMessages: clearMessages,
        escapeHtml: escapeHtml,
        formatDateTime: formatDateTime,
        formatDateTimeCompact: formatDateTimeCompact,
        formatScoreValue: formatScoreValue,
        getConfiguredPluginAuthor: appMetaManager.getConfiguredPluginAuthor,
        getConfiguredPluginVersion: appMetaManager.getConfiguredPluginVersion,
        getConfiguredSchoolLogoUrl: appMetaManager.getConfiguredSchoolLogoUrl,
        getConfiguredSchoolMotto: appMetaManager.getConfiguredSchoolMotto,
        getConfiguredSchoolName: appMetaManager.getConfiguredSchoolName,
        getCurrentUserName: appMetaManager.getCurrentUserName,
        getCurrentUserPhoto: appMetaManager.getCurrentUserPhoto,
        getLoginHeroSchoolBranding: appMetaManager.getLoginHeroSchoolBranding,
        getSelectedExam: appMetaManager.getSelectedExam,
        getUserInitial: appMetaManager.getUserInitial,
        persistAuthSession: persistAuthSession,
        recordTimeline: recordTimeline,
        render: render,
        renderAlert: appMetaManager.renderAlert,
        state: state
    });

    appShellManager = createAppShellManager({
        escapeHtml: escapeHtml,
        fontScaleMax: FONT_SCALE_MAX,
        fontScaleMin: FONT_SCALE_MIN,
        formatFontScaleLabel: uiPreferencesManager.formatFontScaleLabel,
        formatScoreValue: formatScoreValue,
        formatSeconds: formatSeconds,
        getConfiguredSchoolLogoUrl: appMetaManager.getConfiguredSchoolLogoUrl,
        getConfiguredSchoolName: appMetaManager.getConfiguredSchoolName,
        getCurrentUserName: appMetaManager.getCurrentUserName,
        getCurrentUserPhoto: appMetaManager.getCurrentUserPhoto,
        getExamProgressSummary: function () {
            return {};
        },
        getSelectedExam: appMetaManager.getSelectedExam,
        getUserInitial: appMetaManager.getUserInitial,
        renderAlert: appMetaManager.renderAlert,
        renderConfirmStage: function () {
            return options.renderConfirmStage ? options.renderConfirmStage(runtime) : '';
        },
        renderExamStageShell: function () {
            return '';
        },
        renderLoginStage: function () {
            return '';
        },
        renderResultStageShell: function () {
            return options.renderResultStageShell ? options.renderResultStageShell(runtime) : '';
        },
        state: state
    });

    var runtime = {
        api: apiClient.api,
        appMetaManager: appMetaManager,
        authStageManager: authStageManager,
        buildResultPayload: buildResultPayload,
        clearMessages: clearMessages,
        expireAuthSession: expireAuthSession,
        findExamById: appMetaManager.findExamById,
        formatQuestionType: formatQuestionType,
        formatScoreValue: formatScoreValue,
        getSelectedExam: appMetaManager.getSelectedExam,
        handoffToLegacyStartExam: handoffToLegacyStartExam,
        loadExams: loadExams,
        normalizeExamToken: normalizeExamToken,
        persistAuthSession: persistAuthSession,
        questionOptionKey: questionOptionKey,
        render: render,
        renderAlert: appMetaManager.renderAlert,
        resetAuthProgressState: resetAuthProgressState,
        resetResultProgressState: resetResultProgressState,
        resolvePrimaryActionSelection: resolvePrimaryActionSelection,
        root: root,
        safeRichHtml: appMetaManager.safeRichHtml,
        setConnectionStatus: setConnectionStatus,
        state: state,
        transitionTo: context.transitionTo,
        uiPreferencesManager: uiPreferencesManager,
        unmount: unmount,
        updateResultProgress: updateResultProgress
    };

    if (typeof context.registerStageController === 'function') {
        context.registerStageController(runtime);
    }
    mountListeners();
    uiPreferencesManager.applyUiPreferences();
    syncBodyStageClass();

    return runtime;

    function mountListeners() {
        root.addEventListener('click', handleRootClick);
        root.addEventListener('input', handleRootInput);
        root.addEventListener('change', handleRootChange);
        root.addEventListener('error', handleRootError, true);
        documentRef.addEventListener('keydown', handleDocumentKeydown);
        windowRef.addEventListener('online', handleOnline);
        windowRef.addEventListener('offline', handleOffline);
        windowRef.addEventListener('resize', handleResize);
    }

    function unmount() {
        mounted = false;
        root.removeEventListener('click', handleRootClick);
        root.removeEventListener('input', handleRootInput);
        root.removeEventListener('change', handleRootChange);
        root.removeEventListener('error', handleRootError, true);
        documentRef.removeEventListener('keydown', handleDocumentKeydown);
        windowRef.removeEventListener('online', handleOnline);
        windowRef.removeEventListener('offline', handleOffline);
        windowRef.removeEventListener('resize', handleResize);
    }

    function render(reason, meta) {
        if (!mounted) {
            return;
        }
        uiPreferencesManager.applyUiPreferences();
        syncBodyStageClass();
        root.innerHTML = [
            '<div class="cbt-app">',
            appShellManager.renderTopbar(),
            '<main class="cbt-container">',
            appShellManager.renderBody(),
            '</main>',
            appShellManager.renderAuthProgressOverlay(),
            appShellManager.renderResultProgressOverlay(),
            appShellManager.renderSessionRecoveryOverlay(),
            appShellManager.renderUserPhotoModal(),
            '</div>'
        ].join('');
        recordTimeline('stage:render', String(reason || 'render'), meta || {});
    }

    function handleRootClick(event) {
        var target = event && event.target && event.target.closest
            ? event.target.closest('[data-action]')
            : null;
        if (!target || !root.contains(target)) {
            return;
        }

        var action = String(target.getAttribute('data-action') || '');
        if (handleCommonAction(action, target, event)) {
            return;
        }
        if (typeof options.onAction === 'function') {
            options.onAction(action, target, runtime, event);
        }
    }

    function handleRootInput(event) {
        if (typeof options.onInput === 'function') {
            options.onInput(event, runtime);
        }
    }

    function handleRootChange(event) {
        if (typeof options.onChange === 'function') {
            options.onChange(event, runtime);
        }
    }

    function handleRootError(event) {
        var target = event && event.target ? event.target : null;
        if (!target || !target.getAttribute || target.getAttribute('data-cbt-profile-photo') === null) {
            return;
        }
        target.hidden = true;
        var parent = target.parentElement;
        if (!parent) {
            return;
        }
        var fallback = parent.querySelector('[data-cbt-profile-photo-fallback]');
        if (fallback) {
            fallback.hidden = false;
        }
    }

    function handleDocumentKeydown(event) {
        if (!event || event.key !== 'Escape') {
            return;
        }
        var changed = false;
        if (state.userPhotoModalOpen) {
            state.userPhotoModalOpen = false;
            changed = true;
        }
        if (state.examPickerMobileOpen) {
            state.examPickerMobileOpen = false;
            changed = true;
        }
        if (changed) {
            render('escape-close');
        }
    }

    function handleOnline() {
        setConnectionStatus('online', { render: true });
    }

    function handleOffline() {
        setConnectionStatus('offline', { render: true });
    }

    function handleResize() {
        uiPreferencesManager.applyUiPreferences();
    }

    function handleCommonAction(action, target, event) {
        if (action === 'dismiss-alert') {
            clearMessages();
            render('dismiss-alert');
            return true;
        }
        if (action === 'toggle-theme') {
            uiPreferencesManager.toggleTheme();
            render('toggle-theme');
            return true;
        }
        if (action === 'font-dec') {
            uiPreferencesManager.updateFontScale((Number(state.fontScale) || FONT_SCALE_DEFAULT) - FONT_SCALE_STEP);
            render('font-dec');
            return true;
        }
        if (action === 'font-inc') {
            uiPreferencesManager.updateFontScale((Number(state.fontScale) || FONT_SCALE_DEFAULT) + FONT_SCALE_STEP);
            render('font-inc');
            return true;
        }
        if (action === 'font-reset') {
            uiPreferencesManager.updateFontScale(FONT_SCALE_DEFAULT);
            render('font-reset');
            return true;
        }
        if (action === 'logout') {
            event.preventDefault();
            fullLogout();
            return true;
        }
        if (action === 'open-user-photo') {
            state.userPhotoModalOpen = true;
            render('open-user-photo');
            return true;
        }
        if (action === 'close-user-photo') {
            state.userPhotoModalOpen = false;
            render('close-user-photo');
            return true;
        }
        if (action === 'user-photo-modal-panel') {
            event.stopPropagation();
            return true;
        }
        return false;
    }

    async function fullLogout() {
        if (state.busy) {
            return;
        }

        state.busy = true;
        state.authProgressVisible = true;
        state.authProgressMode = 'logout';
        state.authProgressPercent = 30;
        state.authProgressStepIndex = 1;
        state.authProgressStepTotal = 4;
        state.authProgressStatus = 'Menutup sesi';
        state.authProgressDetail = 'Mengirim permintaan logout ke server.';
        clearMessages();
        render('logout-start');

        try {
            await apiClient.api('logout', {
                method: 'POST',
                suppressAuthExpiry: true
            });
            authSession.clearPersistedAuthSession();
            state.token = '';
            state.user = null;
            state.exams = [];
            state.selectedExamId = 0;
            state.examToken = '';
            state.result = null;
            state.busy = false;
            resetAuthProgressState();
            await context.transitionTo('login', { reason: 'logout' });
        } catch (error) {
            state.busy = false;
            resetAuthProgressState();
            state.error = error instanceof Error ? error.message : 'Logout gagal.';
            render('logout-error');
        }
    }

    function persistAuthSession() {
        return authSession.persistAuthSession();
    }

    function clearMessages() {
        state.error = '';
        state.notice = '';
        state.success = '';
    }

    function expireAuthSession(message) {
        authSession.clearPersistedAuthSession();
        state.token = '';
        state.user = null;
        state.exams = [];
        state.selectedExamId = 0;
        state.examToken = '';
        state.result = null;
        state.error = message || 'Sesi login berakhir. Silakan login kembali.';
        context.transitionTo('login', { reason: 'auth-expired' });
    }

    function loadExams(options) {
        options = options || {};
        return apiClient.api('exams', {
            suppressAuthExpiry: options.suppressAuthExpiry === true
        }).then(function (payload) {
            applyAdaptiveLoadPayload(payload);
            state.exams = Array.isArray(payload && payload.items) ? payload.items : [];
            state.examPickerMobileOpen = false;
            refreshCurrentUser(payload);

            if (!state.exams.length) {
                state.selectedExamId = 0;
                state.examToken = '';
                persistAuthSession();
                return payload;
            }

            var selectedStillExists = state.exams.some(function (exam) {
                return Number(exam && exam.id) === Number(state.selectedExamId);
            });
            if (!selectedStillExists) {
                if (state.exams.length === 1) {
                    state.selectedExamId = Number(state.exams[0].id) || 0;
                } else {
                    state.selectedExamId = 0;
                    state.examToken = '';
                }
            }

            persistAuthSession();
            return payload;
        });
    }

    async function resolvePrimaryActionSelection(requestedAction) {
        var activeSelectedExam = appMetaManager.getSelectedExam();
        var selectedExamId = Number(state.selectedExamId) || Number(activeSelectedExam && activeSelectedExam.id) || 0;
        if (selectedExamId <= 0) {
            return {
                action: String(requestedAction || ''),
                selectedExam: activeSelectedExam,
                refreshed: false
            };
        }

        await loadExams();

        var selectedExam = appMetaManager.getSelectedExam();
        var resolvedAction = String(requestedAction || '');
        var latestAttemptStatus = getExamLatestAttemptStatus(selectedExam);
        var latestAttemptFinalizing = Number(selectedExam && selectedExam.latest_attempt_finalize_pending) === 1;
        if (latestAttemptFinalizing) {
            resolvedAction = 'finalizing';
        } else if (resolvedAction === 'view-result' && latestAttemptStatus !== 'completed') {
            resolvedAction = 'start-exam';
        } else if (resolvedAction === 'start-exam' && latestAttemptStatus === 'completed') {
            resolvedAction = 'view-result';
        }

        return {
            action: resolvedAction,
            selectedExam: selectedExam,
            refreshed: true
        };
    }

    function handoffToLegacyStartExam(handoffOptions) {
        handoffOptions = handoffOptions || {};
        var selectedExam = handoffOptions.selectedExam || appMetaManager.getSelectedExam();
        if (!selectedExam) {
            state.error = 'Pilih exam terlebih dahulu.';
            render('start-exam-missing-selection');
            return Promise.resolve(null);
        }

        state.selectedExamId = Number(selectedExam.id) || Number(state.selectedExamId) || 0;
        state.examToken = normalizeExamToken(
            handoffOptions.examToken !== undefined ? handoffOptions.examToken : state.examToken
        );
        persistAuthSession();

        return context.loadLegacyRuntime({
            handoffIntent: {
                action: 'start-exam',
                exam_token: state.examToken,
                selected_exam_id: state.selectedExamId,
                skip_exam_refresh: handoffOptions.skipExamRefresh === true
            },
            reason: handoffOptions.reason || 'start-exam'
        });
    }

    function buildResultPayload(reviewPayload, selectedExam, attemptId) {
        var payload = reviewPayload && typeof reviewPayload === 'object' ? reviewPayload : {};
        var attemptData = payload.attempt || null;
        var showStudentResult = Number(
            payload.show_student_result !== undefined
                ? payload.show_student_result
                : (selectedExam && selectedExam.show_student_result !== undefined ? selectedExam.show_student_result : 1)
        ) === 1;
        var resultViewMode = payload.result_view_mode
            ? String(payload.result_view_mode)
            : (showStudentResult ? 'full' : 'restricted');
        var isRestrictedResult = !showStudentResult || resultViewMode.toLowerCase() === 'restricted';
        var score = isRestrictedResult
            ? 0
            : Number(attemptData && attemptData.score !== undefined ? attemptData.score : selectedExam && selectedExam.latest_attempt_score);
        var maxScore = isRestrictedResult
            ? 0
            : Number(attemptData && attemptData.max_score !== undefined ? attemptData.max_score : selectedExam && selectedExam.latest_attempt_max_score);
        var percentage = isRestrictedResult
            ? 0
            : (maxScore > 0
                ? ((score / maxScore) * 100)
                : Number(selectedExam && selectedExam.latest_attempt_percentage || 0));
        var passMeta = buildResultPassMeta(
            score,
            maxScore,
            payload.kkm_percentage !== undefined ? payload.kkm_percentage : selectedExam && selectedExam.kkm_percentage,
            payload.is_passed !== undefined ? payload.is_passed : selectedExam && selectedExam.latest_attempt_is_passed,
            payload.pass_label !== undefined ? payload.pass_label : selectedExam && selectedExam.latest_attempt_pass_label,
            payload.result_tone !== undefined ? payload.result_tone : selectedExam && selectedExam.latest_attempt_result_tone
        );

        return {
            answers: !isRestrictedResult && Array.isArray(payload.answers) ? payload.answers : [],
            attempt: attemptData,
            attempt_id: Number(attemptId) || 0,
            exam: payload.exam || selectedExam,
            is_passed: isRestrictedResult ? 0 : passMeta.is_passed,
            kkm_percentage: isRestrictedResult ? 0 : passMeta.kkm_percentage,
            max_score: Number.isFinite(maxScore) ? maxScore : 0,
            pass_label: isRestrictedResult ? '' : passMeta.pass_label,
            passing_score: isRestrictedResult ? 0 : passMeta.passing_score,
            percentage: Number.isFinite(percentage) ? percentage : 0,
            result_tone: isRestrictedResult ? '' : passMeta.result_tone,
            result_view_mode: resultViewMode,
            review_items: !isRestrictedResult && Array.isArray(payload.review_items) ? payload.review_items : [],
            review_summary: !isRestrictedResult && payload.review_summary && typeof payload.review_summary === 'object'
                ? payload.review_summary
                : null,
            score: Number.isFinite(score) ? score : 0,
            show_student_result: showStudentResult ? 1 : 0,
            status: String(attemptData && attemptData.status ? attemptData.status : 'completed'),
            submission_summary: payload.submission_summary && typeof payload.submission_summary === 'object'
                ? payload.submission_summary
                : null
        };
    }

    function updateResultProgress(percent, stepIndex, status, detail, progressOptions) {
        var shouldRender = !(progressOptions && progressOptions.render === false);
        state.resultProgressVisible = true;
        state.resultProgressPercent = Math.max(0, Math.min(100, Number(percent) || 0));
        state.resultProgressStepIndex = Math.max(0, Math.min(RESULT_PROGRESS_STEP_TOTAL, Number(stepIndex) || 0));
        state.resultProgressStepTotal = RESULT_PROGRESS_STEP_TOTAL;
        state.resultProgressStatus = String(status || '');
        state.resultProgressDetail = String(detail || '');
        if (shouldRender) {
            render('result-progress');
        }
    }

    function resetResultProgressState() {
        state.resultProgressVisible = false;
        state.resultProgressPercent = 0;
        state.resultProgressStepIndex = 0;
        state.resultProgressStepTotal = 0;
        state.resultProgressStatus = '';
        state.resultProgressDetail = '';
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

    function refreshCurrentUser(payload) {
        var currentUserPayload = payload && typeof payload === 'object' && payload.current_user && typeof payload.current_user === 'object'
            ? payload.current_user
            : null;
        if (!currentUserPayload || !state.user || Number(currentUserPayload.user_id || 0) !== Number(state.user.user_id || 0)) {
            return;
        }
        state.user = {
            agama: Object.prototype.hasOwnProperty.call(currentUserPayload, 'agama') ? String(currentUserPayload.agama || '') : String(state.user.agama || ''),
            display_name: Object.prototype.hasOwnProperty.call(currentUserPayload, 'display_name') ? String(currentUserPayload.display_name || '') : String(state.user.display_name || ''),
            email: Object.prototype.hasOwnProperty.call(currentUserPayload, 'email') ? String(currentUserPayload.email || '') : String(state.user.email || ''),
            foto: Object.prototype.hasOwnProperty.call(currentUserPayload, 'foto') ? String(currentUserPayload.foto || '') : String(state.user.foto || ''),
            kode_kelas: Object.prototype.hasOwnProperty.call(currentUserPayload, 'kode_kelas') ? String(currentUserPayload.kode_kelas || '') : String(state.user.kode_kelas || ''),
            kode_ruang: Object.prototype.hasOwnProperty.call(currentUserPayload, 'kode_ruang') ? String(currentUserPayload.kode_ruang || '') : String(state.user.kode_ruang || ''),
            role: Object.prototype.hasOwnProperty.call(currentUserPayload, 'role') ? String(currentUserPayload.role || '') : String(state.user.role || ''),
            user_id: Number(state.user.user_id) || 0,
            username: Object.prototype.hasOwnProperty.call(currentUserPayload, 'username') ? String(currentUserPayload.username || '') : String(state.user.username || '')
        };
    }

    function applyAdaptiveLoadPayload(payload) {
        var adaptiveLoad = payload && typeof payload === 'object' && payload.adaptive_load && typeof payload.adaptive_load === 'object'
            ? payload.adaptive_load
            : null;
        if (!adaptiveLoad) {
            return;
        }

        state.adaptiveLoadLevel = String(adaptiveLoad.level || 'normal');
        state.adaptiveLoadSource = String(adaptiveLoad.source || 'auto');
        state.adaptiveLoadReasons = Array.isArray(adaptiveLoad.reasons) ? adaptiveLoad.reasons.slice() : [];
        state.adaptiveLoadHeartbeatIntervalMs = Math.max(1000, Number(adaptiveLoad.heartbeat_interval_ms) || Number(state.adaptiveLoadHeartbeatIntervalMs) || 20000);
        state.adaptiveLoadAdminSnapshotRefreshSeconds = Math.max(1, Number(adaptiveLoad.admin_snapshot_refresh_seconds) || 10);
        state.adaptiveLoadLastEvaluatedAt = String(adaptiveLoad.last_evaluated_at || '');
        state.adaptiveLoadOverrideExpiresAt = String(adaptiveLoad.override_expires_at || '');
    }

    function setConnectionStatus(nextStatus, statusOptions) {
        var normalized = String(nextStatus || '').toLowerCase() === 'offline' ? 'offline' : 'online';
        if (state.connectionStatus === normalized) {
            return;
        }
        state.connectionStatus = normalized;
        if (statusOptions && statusOptions.render === true) {
            render('connection-status');
        }
    }

    function syncBodyStageClass() {
        if (!documentRef.body) {
            return;
        }
        ['login', 'confirm', 'exam', 'result'].forEach(function (name) {
            documentRef.body.classList.remove('cbt-stage-' + name);
        });
        documentRef.body.classList.add('cbt-stage-' + String(state.stage || stage));
    }

    function recordTimeline(eventName, message, meta) {
        if (typeof context.recordTimeline === 'function') {
            context.recordTimeline(eventName, message, meta || {});
        }
    }
}

function getExamLatestAttemptStatus(exam) {
    return String(exam && exam.latest_attempt_status ? exam.latest_attempt_status : '').toLowerCase();
}

function questionOptionKey(option, index) {
    var key = String(option && option.option_key ? option.option_key : '').trim();
    if (key !== '') {
        return key;
    }

    var code = 65 + Number(index || 0);
    if (code >= 65 && code <= 90) {
        return String.fromCharCode(code);
    }

    return String((Number(index) || 0) + 1);
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
        is_passed: isPassed ? 1 : 0,
        kkm_percentage: kkmPercentage,
        pass_label: rawPassLabel ? String(rawPassLabel) : (isPassed ? 'LULUS' : 'TIDAK LULUS'),
        passing_score: passingScore,
        result_tone: rawResultTone ? String(rawResultTone) : (isPassed ? 'pass' : 'fail')
    };
}
