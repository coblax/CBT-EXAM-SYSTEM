export const AUTH_SESSION_STORAGE_KEY = 'cbt_exam_frontend_auth_v1';
export const UI_PREF_STORAGE_KEY = 'cbt_exam_frontend_ui_pref_v1';
export const ATTEMPT_UI_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_attempt_ui_v1_';
export const QUESTION_CACHE_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_v2_';
export const QUESTION_CACHE_META_LOCAL_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_meta_v2_';
export const QUESTION_CACHE_ITEM_LOCAL_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_item_v2_';
export const QUESTION_CACHE_INDEXED_DB_NAME = 'cbt_exam_frontend_cache_v2';
export const QUESTION_CACHE_INDEXED_DB_STORE = 'attempt_questions';
export const DOUBTFUL_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_doubtful_v1_';

export const FONT_SCALE_MIN = 0.85;
export const FONT_SCALE_MAX = 1.35;
export const FONT_SCALE_STEP = 0.1;
export const FONT_SCALE_DEFAULT = 1;

export const EXAM_TOKEN_LENGTH = 6;
export const EXAM_TOKEN_ALLOWED_PATTERN = /^[A-HJKMNPQRSTUVWXYZ1-9]$/;

export const NAV_SIDE_LAYOUT_BREAKPOINT = 1000;
export const PANEL_STACK_BREAKPOINT = 1100;

export const QUESTION_WINDOW_SIZE = 10;
export const QUESTION_PREFETCH_BATCH_SIZE = 5;
export const QUESTION_PREFETCH_IDLE_DELAY_MS = 30000;

export const NAV_QUESTION_FILTER_ALL = 'all';
export const NAV_QUESTION_FILTER_ANSWERED = 'answered';
export const NAV_QUESTION_FILTER_UNANSWERED = 'unanswered';
export const NAV_QUESTION_FILTER_DOUBTFUL = 'doubtful';

function normalizeBooleanFlag(value) {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value !== 0;
    }

    if (typeof value === 'string') {
        var normalized = value.trim().toLowerCase();
        if (normalized === '' || normalized === '0' || normalized === 'false' || normalized === 'no' || normalized === 'off') {
            return false;
        }
        if (normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on') {
            return true;
        }
    }

    return Boolean(value);
}

function normalizeIntegerFlag(value, fallback) {
    var parsed = parseInt(String(value), 10);
    return Number.isFinite(parsed) ? parsed : fallback;
}

function normalizeClampedNumber(value, fallback, min, max) {
    var normalizedValue = typeof value === 'string' ? value.trim().replace(',', '.') : value;
    var parsed = Number(normalizedValue);
    if (!Number.isFinite(parsed)) {
        parsed = fallback;
    }

    return Math.max(min, Math.min(max, parsed));
}

export function getFrontendConfig(win) {
    var raw = win && win.CBTExamFrontendConfig ? win.CBTExamFrontendConfig : {};
    if (!raw || typeof raw !== 'object') {
        raw = {};
    }

    return Object.assign({}, raw, {
        frontendMode: String(raw.frontendMode || '').trim().toLowerCase() === 'supervisor' ? 'supervisor' : 'student',
        examProgramName: String(raw.examProgramName || '').trim(),
        studentFrontendUrl: String(raw.studentFrontendUrl || raw.homeUrl || '').trim(),
        supervisorFrontendUrl: String(raw.supervisorFrontendUrl || raw.homeUrl || '').trim(),
        serviceWorkerEnabled: normalizeBooleanFlag(raw.serviceWorkerEnabled),
        serviceWorkerUrl: String(raw.serviceWorkerUrl || '').trim(),
        serviceWorkerScope: String(raw.serviceWorkerScope || '').trim(),
        serviceWorkerBuildId: String(raw.serviceWorkerBuildId || '').trim(),
        answerSyncBackgroundEnabled: normalizeBooleanFlag(raw.answerSyncBackgroundEnabled),
        securityForceFullscreen: normalizeBooleanFlag(raw.securityForceFullscreen),
        securityBlockCopyPaste: normalizeBooleanFlag(raw.securityBlockCopyPaste),
        securityBlockBrowserInspectionShortcuts: normalizeBooleanFlag(raw.securityBlockBrowserInspectionShortcuts),
        securityLogEvents: normalizeBooleanFlag(raw.securityLogEvents),
        securityDetectIdle: normalizeBooleanFlag(raw.securityDetectIdle !== undefined ? raw.securityDetectIdle : 1),
        securityDetectHeartbeatLost: normalizeBooleanFlag(raw.securityDetectHeartbeatLost),
        securityDetectScreenshotKeys: normalizeBooleanFlag(raw.securityDetectScreenshotKeys),
        securityShowExamWatermark: normalizeBooleanFlag(raw.securityShowExamWatermark),
        securityExamWatermarkOpacity: normalizeClampedNumber(raw.securityExamWatermarkOpacity, 0.07, 0.03, 0.12),
        securityIdleThresholdMinutes: normalizeIntegerFlag(raw.securityIdleThresholdMinutes, 5),
        securityIdleThresholdSeconds: normalizeIntegerFlag(
            raw.securityIdleThresholdSeconds,
            normalizeIntegerFlag(raw.securityIdleThresholdMinutes, 5) * 60
        ),
        frontendDebugUi: normalizeBooleanFlag(raw.frontendDebugUi),
        frontendDiagnosticsEnabled: normalizeBooleanFlag(raw.frontendDiagnosticsEnabled),
        frontendDiagnosticsScenarioEnabled: normalizeBooleanFlag(raw.frontendDiagnosticsScenarioEnabled),
        frontendDiagnosticsRenderStatsEnabled: normalizeBooleanFlag(raw.frontendDiagnosticsRenderStatsEnabled),
        frontendDiagnosticsStorageExplorerEnabled: normalizeBooleanFlag(raw.frontendDiagnosticsStorageExplorerEnabled),
        tokenMinLength: normalizeIntegerFlag(raw.tokenMinLength, 6),
        tokenLength: normalizeIntegerFlag(raw.tokenLength, 6),
        frontendDiagnosticsMaxEntries: normalizeIntegerFlag(raw.frontendDiagnosticsMaxEntries, 50),
        frontendDiagnosticsTimelineMaxEntries: normalizeIntegerFlag(raw.frontendDiagnosticsTimelineMaxEntries, 150),
    });
}

export function createInitialState(win) {
    return {
        stage: 'login',
        busy: false,
        error: '',
        notice: '',
        success: '',
        loginIdentifier: '',
        loginPassword: '',
        loginPasswordVisible: false,
        authProgressVisible: false,
        authProgressMode: '',
        authProgressPercent: 0,
        authProgressStepIndex: 0,
        authProgressStepTotal: 0,
        authProgressStatus: '',
        authProgressDetail: '',
        resultProgressVisible: false,
        resultProgressPercent: 0,
        resultProgressStepIndex: 0,
        resultProgressStepTotal: 0,
        resultProgressStatus: '',
        resultProgressDetail: '',
        sessionRecoveryVisible: false,
        sessionRecoveryMode: '',
        sessionRecoveryStepIndex: 0,
        sessionRecoveryStepTotal: 0,
        sessionRecoveryPercent: 0,
        sessionRecoveryStatus: '',
        sessionRecoveryDetail: '',
        sessionRecoveryCanRetry: false,
        sessionRecoveryRetryCount: 0,
        sessionRecoveryStartedAt: 0,
        sessionRecoverySlowStage: '',
        token: '',
        user: null,
        exams: [],
        examListFilter: 'all',
        examPickerMobileOpen: false,
        selectedExamId: 0,
        examToken: '',
        attemptId: 0,
        openingAttemptProgressPercent: 0,
        openingAttemptProgressStepIndex: 0,
        openingAttemptProgressStepTotal: 0,
        openingAttemptProgressStatus: '',
        openingAttemptProgressDetail: '',
        openingAttemptPhase: '',
        openingAttemptCanRetry: false,
        openingAttemptCanRefreshStatus: false,
        openingAttemptCanBack: false,
        openingAttemptQueuePosition: 0,
        openingAttemptQueueEstimatedWaitSeconds: 0,
        openingAttemptQueueLastPolledAt: 0,
        openingAttemptServerState: '',
        openingAttemptServerReason: '',
        openingAttemptServerResumeSource: '',
        openingAttemptWaitAgeSeconds: 0,
        openingAttemptLastStageAt: 0,
        openingRetryAttemptCount: 0,
        openingRetryNextAt: 0,
        openingRetryDelayMs: 0,
        openingRetryReason: '',
        openingRetryCountdownSeconds: 0,
        openingRetryInFlight: false,
        openingRetryLastTrigger: '',
        pendingExamId: 0,
        pendingExamToken: '',
        pendingStartIntentKey: '',
        pendingQueueTicket: '',
        pendingResumeIntent: false,
        pendingOpeningPhase: '',
        pendingLastErrorCode: '',
        pendingLastErrorMessage: '',
        questions: [],
        questionOrderIds: [],
        questionManifest: [],
        questionManifestById: {},
        questionPayloadById: {},
        archivedReviewItems: [],
        existingAnswerRawByQuestionId: {},
        answeredQuestionLookup: {},
        changedQuestionLookup: {},
        questionRevisionMarkerLookup: {},
        acknowledgedRevisionQuestionIds: {},
        loadedQuestionWindowOffsets: {},
        windowOffset: 0,
        windowLimit: 0,
        totalQuestions: 0,
        questionOrderSignature: '',
        questionRevision: null,
        questionResponseEtags: {},
        questionRevisionRefreshing: false,
        questionRegionRefreshing: false,
        navigationRefreshing: false,
        questionRevisionNotice: null,
        questionRevisionToastTimerId: 0,
        answers: {},
        doubtful: {},
        currentIndex: 0,
        navQuestionFilter: NAV_QUESTION_FILTER_ALL,
        remainingSeconds: 0,
        timerId: 0,
        isOpeningAttempt: false,
        isFinishing: false,
        finishProgressPercent: 0,
        finishProgressStepIndex: 0,
        finishProgressStepTotal: 0,
        finishProgressStatus: '',
        finishProgressDetail: '',
        result: null,
        finishConfirmOpen: false,
        finishConfirmSummary: null,
        richZoomModalOpen: false,
        richZoomModalType: '',
        richZoomModalTitle: '',
        richZoomModalMarkup: '',
        richZoomModalGalleryId: '',
        richZoomModalGalleryIndex: 0,
        richZoomModalGalleryItems: [],
        richZoomModalGalleryCount: 0,
        richZoomModalScaleMode: 'fit',
        richZoomModalScalePercent: 100,
        userPhotoModalOpen: false,
        fontScale: FONT_SCALE_DEFAULT,
        uiTheme: 'light',
        navPanelVisible: true,
        navPanelPosition: 'right',
        calculatorPosition: 'bottom',
        calculatorVisible: false,
        calculatorExpression: '',
        calculatorResult: '',
        calculatorError: '',
        isFullscreenActive: false,
        connectionStatus: (win && win.navigator && win.navigator.onLine === false) ? 'offline' : 'online',
        storageHealth: {
            cacheApiAvailable: false,
            indexedDbAvailable: false,
            localAnswerStorageAvailable: false,
            localStorageAvailable: false,
            mode: 'unknown',
            serviceWorkerControlled: false,
            sessionStorageAvailable: false,
            warningLevel: 'unknown'
        },
        adaptiveLoadLevel: 'normal',
        adaptiveLoadSource: 'auto',
        adaptiveLoadReasons: [],
        adaptiveLoadHeartbeatIntervalMs: 20000,
        adaptiveLoadAdminSnapshotRefreshSeconds: 10,
        adaptiveLoadLastEvaluatedAt: '',
        adaptiveLoadOverrideExpiresAt: '',
        pendingSyncCount: 0,
        syncBlockingReason: '',
        examLockedForPendingFinish: false,
        finishLockStartedAt: 0,
        finishRecoveryCanExit: false,
        lastSyncError: '',
        heartbeatLostActive: false,
        heartbeatLostFailureCount: 0,
        heartbeatLostLastErrorCode: '',
        pendingFinishAutoSubmit: false,
        finishReceipt: null,
        finishResultPending: false,
        finishRecoveryLastError: '',
        examWatermarkTick: 0
    };
}
