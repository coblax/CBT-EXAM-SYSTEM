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

export function getFrontendConfig(win) {
    var raw = win && win.CBTExamFrontendConfig ? win.CBTExamFrontendConfig : {};
    if (!raw || typeof raw !== 'object') {
        raw = {};
    }

    return Object.assign({}, raw, {
        securityForceFullscreen: normalizeBooleanFlag(raw.securityForceFullscreen),
        securityBlockCopyPaste: normalizeBooleanFlag(raw.securityBlockCopyPaste),
        securityLogEvents: normalizeBooleanFlag(raw.securityLogEvents),
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
        token: '',
        user: null,
        exams: [],
        examPickerMobileOpen: false,
        selectedExamId: 0,
        examToken: '',
        attemptId: 0,
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
        result: null,
        finishConfirmOpen: false,
        finishConfirmSummary: null,
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
        pendingSyncCount: 0,
        syncBlockingReason: '',
        examLockedForPendingFinish: false,
        lastSyncError: '',
        pendingFinishAutoSubmit: false
    };
}
