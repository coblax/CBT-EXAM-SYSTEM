(function () {
    'use strict';

    var root = document.getElementById('cbt-exam-app');
    if (!root) {
        return;
    }

    var config = window.CBTExamFrontendConfig || {};
    var AUTH_SESSION_STORAGE_KEY = 'cbt_exam_frontend_auth_v1';
    var UI_PREF_STORAGE_KEY = 'cbt_exam_frontend_ui_pref_v1';
    var ATTEMPT_UI_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_attempt_ui_v1_';
    var QUESTION_CACHE_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_v2_';
    var QUESTION_CACHE_META_LOCAL_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_meta_v2_';
    var QUESTION_CACHE_ITEM_LOCAL_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_item_v2_';
    var QUESTION_CACHE_INDEXED_DB_NAME = 'cbt_exam_frontend_cache_v2';
    var QUESTION_CACHE_INDEXED_DB_STORE = 'attempt_questions';
    var DOUBTFUL_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_doubtful_v1_';
    var FONT_SCALE_MIN = 0.85;
    var FONT_SCALE_MAX = 1.35;
    var FONT_SCALE_STEP = 0.1;
    var FONT_SCALE_DEFAULT = 1;
    var EXAM_TOKEN_LENGTH = 6;
    var EXAM_TOKEN_ALLOWED_PATTERN = /^[A-HJKMNPQRSTUVWXYZ1-9]$/;
    var NAV_SIDE_LAYOUT_BREAKPOINT = 1000;
    var PANEL_STACK_BREAKPOINT = 1100;
    var QUESTION_WINDOW_SIZE = 10;
    var QUESTION_PREFETCH_BATCH_SIZE = 5;
    var QUESTION_PREFETCH_IDLE_DELAY_MS = 30000;
    var NAV_QUESTION_FILTER_ALL = 'all';
    var NAV_QUESTION_FILTER_ANSWERED = 'answered';
    var NAV_QUESTION_FILTER_UNANSWERED = 'unanswered';
    var NAV_QUESTION_FILTER_DOUBTFUL = 'doubtful';
    var cachedSessionStorage;
    var cachedLocalStorage;
    var state = {
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
        loadedQuestionWindowOffsets: {},
        windowOffset: 0,
        windowLimit: 0,
        totalQuestions: 0,
        questionRevision: null,
        answers: {},
        doubtful: {},
        currentIndex: 0,
        navQuestionFilter: NAV_QUESTION_FILTER_ALL,
        remainingSeconds: 0,
        timerId: 0,
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
        isFullscreenActive: false
    };
    var AUTO_SAVE_CHOICE_DELAY_MS = 2000;
    var AUTO_SAVE_TEXT_DELAY_MS = 3500;
    var AUTO_SAVE_CHOICE_DELAY_CONGESTED_MS = 2600;
    var AUTO_SAVE_TEXT_DELAY_CONGESTED_MS = 4600;
    var AUTO_SAVE_CONGESTED_WINDOW_MS = 15000;
    var AUTO_SAVE_BATCH_MAX_ITEMS = 20;
    var ATTEMPT_UI_STATE_SYNC_DELAY_MS = 1200;
    var ATTEMPT_UI_STATE_NAVIGATION_SYNC_DELAY_MS = 1600;
    var SESSION_HEARTBEAT_INTERVAL_MS = 20000;
    var WINDOW_BLUR_LOG_DELAY_MS = 800;
    var autoSaveTimersByQuestion = {};
    var autoSaveCongestedUntil = 0;
    var lastSubmittedPayloadByQuestion = {};
    var pendingAnswerBatchByQuestion = {};
    var pendingAnswerBatchOrder = [];
    var answerBatchFlushTimer = 0;
    var answerBatchFlushDueAt = 0;
    var answerBatchFlushInFlight = null;
    var navGridLayoutFrameId = 0;
    var compactViewportState = false;
    var lastRenderedStage = '';
    var uiPreferencesSyncTimer = 0;
    var attemptUiStateSyncTimer = 0;
    var attemptUiStateSyncInFlight = null;
    var lastAttemptUiStateSyncSignature = '';
    var sessionHeartbeatTimer = 0;
    var sessionHeartbeatInFlight = null;
    var questionPrefetchIdleTimer = 0;
    var questionPrefetchInFlightByOffset = {};
    var questionCachePersistTimer = 0;
    var questionCacheIndexedDbPromise = null;
    var lastQuestionCacheRestoreDebug = null;
    var questionRevisionRefreshInFlight = null;
    var questionDataGeneration = 0;
    var pendingRevisionSafeAnswerRestoreByQuestion = {};
    var securityEventLastSentAtByKey = {};
    var pageLeaveLoggedAttemptId = 0;
    var tabHiddenLogTimer = 0;
    var tabHiddenLogScheduledAttemptId = 0;
    var windowBlurLogTimer = 0;
    var windowBlurLogScheduledAttemptId = 0;
    var fullscreenExitLogSuppressedUntil = 0;

    function getSessionStorage() {
        if (cachedSessionStorage !== undefined) {
            return cachedSessionStorage;
        }

        try {
            if (!window || !window.sessionStorage) {
                cachedSessionStorage = null;
                return cachedSessionStorage;
            }

            var probeKey = '__cbt_session_probe__';
            window.sessionStorage.setItem(probeKey, '1');
            window.sessionStorage.removeItem(probeKey);
            cachedSessionStorage = window.sessionStorage;
        } catch (error) {
            cachedSessionStorage = null;
        }

        return cachedSessionStorage;
    }

    function getLocalStorage() {
        if (cachedLocalStorage !== undefined) {
            return cachedLocalStorage;
        }

        try {
            if (!window || !window.localStorage) {
                cachedLocalStorage = null;
                return cachedLocalStorage;
            }

            var probeKey = '__cbt_local_probe__';
            window.localStorage.setItem(probeKey, '1');
            window.localStorage.removeItem(probeKey);
            cachedLocalStorage = window.localStorage;
        } catch (error) {
            cachedLocalStorage = null;
        }

        return cachedLocalStorage;
    }

    function getIndexedDb() {
        try {
            return window && window.indexedDB ? window.indexedDB : null;
        } catch (error) {
            return null;
        }
    }

    function openQuestionCacheIndexedDb() {
        if (questionCacheIndexedDbPromise !== null) {
            return questionCacheIndexedDbPromise;
        }

        var indexedDb = getIndexedDb();
        if (!indexedDb) {
            questionCacheIndexedDbPromise = Promise.resolve(null);
            return questionCacheIndexedDbPromise;
        }

        questionCacheIndexedDbPromise = new Promise(function (resolve) {
            var request;
            try {
                request = indexedDb.open(QUESTION_CACHE_INDEXED_DB_NAME, 1);
            } catch (error) {
                resolve(null);
                return;
            }

            request.onupgradeneeded = function () {
                var database = request.result;
                if (!database.objectStoreNames.contains(QUESTION_CACHE_INDEXED_DB_STORE)) {
                    database.createObjectStore(QUESTION_CACHE_INDEXED_DB_STORE, {
                        keyPath: 'cache_key'
                    });
                }
            };

            request.onsuccess = function () {
                resolve(request.result || null);
            };

            request.onerror = function () {
                resolve(null);
            };

            request.onblocked = function () {
                resolve(null);
            };
        });

        return questionCacheIndexedDbPromise;
    }

    function setQuestionCacheRestoreDebug(summary) {
        lastQuestionCacheRestoreDebug = summary && typeof summary === 'object' ? summary : null;
        try {
            if (window) {
                window.__CBTQuestionCacheDebug = lastQuestionCacheRestoreDebug;
            }
        } catch (error) {
            // Ignore debug export failures.
        }
    }

    function clamp(value, min, max) {
        if (!Number.isFinite(value)) {
            return min;
        }
        return Math.min(max, Math.max(min, value));
    }

    function normalizeExamToken(value) {
        var rawValue = String(value || '').toUpperCase();
        var normalized = '';
        for (var i = 0; i < rawValue.length && normalized.length < EXAM_TOKEN_LENGTH; i++) {
            var current = rawValue.charAt(i);
            if (!EXAM_TOKEN_ALLOWED_PATTERN.test(current)) {
                continue;
            }
            normalized += current;
        }
        return normalized;
    }

    function normalizeFontScale(value) {
        var numericValue = Number(value);
        if (!Number.isFinite(numericValue)) {
            return FONT_SCALE_DEFAULT;
        }

        var clampedValue = clamp(numericValue, FONT_SCALE_MIN, FONT_SCALE_MAX);
        return Math.round(clampedValue * 100) / 100;
    }

    function normalizeTheme(value) {
        return String(value || '').toLowerCase() === 'dark' ? 'dark' : 'light';
    }

    function normalizeNavPanelPosition(value) {
        var normalized = String(value || '').toLowerCase();
        if (normalized === 'left' || normalized === 'right' || normalized === 'bottom') {
            return normalized;
        }
        return 'top';
    }

    function normalizeCalculatorPanelPosition(value) {
        var normalized = String(value || '').toLowerCase();
        if (normalized === 'top' || normalized === 'left' || normalized === 'right') {
            return normalized;
        }
        return 'bottom';
    }

    function isCompactViewport() {
        return Boolean(window && window.innerWidth <= PANEL_STACK_BREAKPOINT);
    }

    function isCompactNavViewport() {
        return Boolean(window && window.innerWidth <= NAV_SIDE_LAYOUT_BREAKPOINT);
    }

    function getEffectiveNavPanelPosition() {
        var normalized = normalizeNavPanelPosition(state.navPanelPosition);
        if (isCompactNavViewport() && (normalized === 'left' || normalized === 'right')) {
            return 'top';
        }
        return normalized;
    }

    function getEffectiveCalculatorPanelPosition() {
        var normalized = normalizeCalculatorPanelPosition(state.calculatorPosition);
        if (isCompactViewport() && (normalized === 'left' || normalized === 'right')) {
            return 'bottom';
        }
        return normalized;
    }

    function formatFontScaleLabel(scale) {
        var normalized = normalizeFontScale(scale);
        return String(Math.round(normalized * 100)) + '%';
    }

    function normalizeCalculatorExpression(value) {
        return String(value || '')
            .replace(/,/g, '.')
            .replace(/[xX]/g, '*')
            .replace(/\u00d7/g, '*')
            .replace(/\u00f7/g, '/')
            .replace(/\s+/g, '')
            .replace(/[^0-9+\-*/%.()]/g, '');
    }

    function formatCalculatorNumber(value) {
        var numericValue = Number(value);
        if (!Number.isFinite(numericValue)) {
            return '0';
        }

        if (Math.abs(numericValue) < 0.000000000001) {
            numericValue = 0;
        }

        if (Math.abs(numericValue - Math.round(numericValue)) < 0.000000000001) {
            return String(Math.round(numericValue));
        }

        var formatted = numericValue.toPrecision(12).replace(/\.?0+$/, '');
        if (formatted === '-0') {
            return '0';
        }

        return formatted;
    }

    function evaluateCalculatorExpression(expression) {
        var normalizedExpression = normalizeCalculatorExpression(expression);
        if (normalizedExpression === '') {
            return {
                expression: '',
                result: '',
                error: 'Isi ekspresi terlebih dahulu.'
            };
        }

        if (/^[*/%.]/.test(normalizedExpression) || /[+\-*/%.(]$/.test(normalizedExpression)) {
            return {
                expression: normalizedExpression,
                result: '',
                error: 'Ekspresi tidak valid.'
            };
        }

        var openParenCount = 0;
        for (var index = 0; index < normalizedExpression.length; index++) {
            var character = normalizedExpression.charAt(index);
            if (character === '(') {
                openParenCount += 1;
            } else if (character === ')') {
                openParenCount -= 1;
                if (openParenCount < 0) {
                    return {
                        expression: normalizedExpression,
                        result: '',
                        error: 'Kurung tidak seimbang.'
                    };
                }
            }
        }

        if (openParenCount !== 0) {
            return {
                expression: normalizedExpression,
                result: '',
                error: 'Kurung tidak seimbang.'
            };
        }

        try {
            var result = Function('return (' + normalizedExpression + ');')();
            if (typeof result !== 'number' || !Number.isFinite(result)) {
                return {
                    expression: normalizedExpression,
                    result: '',
                    error: 'Hasil tidak valid.'
                };
            }

            return {
                expression: normalizedExpression,
                result: formatCalculatorNumber(result),
                error: ''
            };
        } catch (error) {
            return {
                expression: normalizedExpression,
                result: '',
                error: 'Ekspresi tidak valid.'
            };
        }
    }

    function applyCalculatorEvaluation() {
        var evaluation = evaluateCalculatorExpression(state.calculatorExpression);
        if (evaluation.error) {
            state.calculatorError = evaluation.error;
            state.calculatorResult = '';
            return false;
        }

        state.calculatorExpression = evaluation.expression;
        state.calculatorResult = evaluation.result;
        state.calculatorError = '';
        return true;
    }

    function focusCalculatorInput() {
        var calculatorInput = root.querySelector('[name="calc_expression"]');
        if (!(calculatorInput instanceof HTMLInputElement)) {
            return;
        }

        calculatorInput.focus();
        var cursorPosition = calculatorInput.value.length;
        try {
            calculatorInput.setSelectionRange(cursorPosition, cursorPosition);
        } catch (error) {
            // Ignore browsers that block selection updates on input fields.
        }
    }

    function applyUiPreferences() {
        if (!root) {
            return;
        }

        var fontScale = normalizeFontScale(state.fontScale);
        var theme = normalizeTheme(state.uiTheme);
        var navPanelPosition = normalizeNavPanelPosition(state.navPanelPosition);
        var calculatorPosition = normalizeCalculatorPanelPosition(state.calculatorPosition);

        state.fontScale = fontScale;
        state.uiTheme = theme;
        state.navPanelPosition = navPanelPosition;
        state.calculatorPosition = calculatorPosition;

        root.style.setProperty('--cbt-font-scale', String(fontScale));
        root.classList.toggle('cbt-theme-dark', theme === 'dark');
        root.classList.toggle('cbt-theme-light', theme !== 'dark');
    }

    function persistUiPreferences() {
        var storage = getLocalStorage();
        if (!storage) {
            return;
        }

        var payload = {
            theme: normalizeTheme(state.uiTheme),
            font_scale: normalizeFontScale(state.fontScale),
            nav_position: normalizeNavPanelPosition(state.navPanelPosition),
            calc_position: normalizeCalculatorPanelPosition(state.calculatorPosition)
        };

        try {
            storage.setItem(UI_PREF_STORAGE_KEY, JSON.stringify(payload));
        } catch (error) {
            // Ignore storage failures (quota or disabled storage).
        }
    }

    function readPersistedUiPreferences() {
        var storage = getLocalStorage();
        if (!storage) {
            return null;
        }

        try {
            var raw = storage.getItem(UI_PREF_STORAGE_KEY);
            if (!raw) {
                return null;
            }

            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return null;
            }
            return {
                theme: normalizeTheme(parsed.theme),
                fontScale: normalizeFontScale(parsed.font_scale),
                navPanelPosition: normalizeNavPanelPosition(parsed.nav_position),
                calculatorPosition: normalizeCalculatorPanelPosition(parsed.calc_position)
            };
        } catch (error) {
            return null;
        }
    }

    function updateFontScale(nextScale) {
        var normalized = normalizeFontScale(nextScale);
        if (Math.abs(normalized - state.fontScale) < 0.001) {
            return false;
        }

        state.fontScale = normalized;
        persistUiPreferences();
        return true;
    }

    function toggleTheme() {
        state.uiTheme = state.uiTheme === 'dark' ? 'light' : 'dark';
        persistUiPreferences();
    }

    function updateNavPanelPosition(nextPosition) {
        var normalized = normalizeNavPanelPosition(nextPosition);
        if (normalized === state.navPanelPosition) {
            return false;
        }

        state.navPanelPosition = normalized;
        persistUiPreferences();
        return true;
    }

    function updateCalculatorPanelPosition(nextPosition) {
        var normalized = normalizeCalculatorPanelPosition(nextPosition);
        if (normalized === state.calculatorPosition) {
            return false;
        }

        state.calculatorPosition = normalized;
        persistUiPreferences();
        return true;
    }

    function normalizePersistedUser(rawUser) {
        if (!rawUser || typeof rawUser !== 'object') {
            return null;
        }

        var safeUser = {
            user_id: Number(rawUser.user_id) || 0,
            role: String(rawUser.role || ''),
            display_name: String(rawUser.display_name || ''),
            username: String(rawUser.username || ''),
            email: String(rawUser.email || ''),
            kode_kelas: String(rawUser.kode_kelas || ''),
            kode_ruang: String(rawUser.kode_ruang || ''),
            agama: String(rawUser.agama || ''),
            foto: String(rawUser.foto || '')
        };

        if (safeUser.user_id <= 0 || safeUser.role === '') {
            return null;
        }

        return safeUser;
    }

    function clearPersistedAuthSession() {
        var storage = getSessionStorage();
        if (!storage) {
            return;
        }

        try {
            storage.removeItem(AUTH_SESSION_STORAGE_KEY);
        } catch (error) {
            // Ignore storage failures (private mode / blocked storage).
        }
    }

    function persistAuthSession() {
        var storage = getSessionStorage();
        if (!storage) {
            return;
        }

        if (!state.token || !state.user) {
            clearPersistedAuthSession();
            return;
        }

        var payload = {
            token: String(state.token || ''),
            user: normalizePersistedUser(state.user),
            selected_exam_id: Number(state.selectedExamId) || 0
        };

        if (!payload.user || payload.token === '') {
            clearPersistedAuthSession();
            return;
        }

        try {
            storage.setItem(AUTH_SESSION_STORAGE_KEY, JSON.stringify(payload));
        } catch (error) {
            // Ignore storage failures (quota or disabled storage).
        }
    }

    function readPersistedAuthSession() {
        var storage = getSessionStorage();
        if (!storage) {
            return null;
        }

        try {
            var raw = storage.getItem(AUTH_SESSION_STORAGE_KEY);
            if (!raw) {
                return null;
            }

            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return null;
            }

            var token = String(parsed.token || '');
            var user = normalizePersistedUser(parsed.user || null);
            var selectedExamId = Number(parsed.selected_exam_id) || 0;

            if (token === '' || !user) {
                return null;
            }

            return {
                token: token,
                user: user,
                selectedExamId: selectedExamId
            };
        } catch (error) {
            return null;
        }
    }

    function buildDoubtfulSessionStorageKey(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || userId <= 0) {
            return '';
        }
        return DOUBTFUL_SESSION_STORAGE_KEY_PREFIX + String(userId) + '_' + String(safeAttemptId);
    }

    function readPersistedDoubtfulState(attemptId) {
        var storage = getSessionStorage();
        if (!storage) {
            return {};
        }

        var storageKey = buildDoubtfulSessionStorageKey(attemptId);
        if (storageKey === '') {
            return {};
        }

        try {
            var raw = storage.getItem(storageKey);
            if (!raw) {
                return {};
            }

            var parsed = JSON.parse(raw);
            var questionIds = parsed && Array.isArray(parsed.question_ids) ? parsed.question_ids : [];
            return questionIds.reduce(function (accumulator, item) {
                var qid = Number(item) || 0;
                if (qid > 0) {
                    accumulator[qid] = true;
                }
                return accumulator;
            }, {});
        } catch (error) {
            return {};
        }
    }

    function buildAttemptUiSessionStorageKey(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || userId <= 0) {
            return '';
        }
        return ATTEMPT_UI_SESSION_STORAGE_KEY_PREFIX + String(userId) + '_' + String(safeAttemptId);
    }

    function buildQuestionCacheSessionStorageKey(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || userId <= 0) {
            return '';
        }
        return QUESTION_CACHE_SESSION_STORAGE_KEY_PREFIX + String(userId) + '_' + String(safeAttemptId);
    }

    function buildQuestionCacheMetaLocalStorageKey(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || userId <= 0) {
            return '';
        }
        return QUESTION_CACHE_META_LOCAL_STORAGE_KEY_PREFIX + String(userId) + '_' + String(safeAttemptId);
    }

    function buildQuestionCacheItemLocalStorageKey(attemptId, questionId) {
        var safeAttemptId = Number(attemptId) || 0;
        var safeQuestionId = Number(questionId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || safeQuestionId <= 0 || userId <= 0) {
            return '';
        }
        return QUESTION_CACHE_ITEM_LOCAL_STORAGE_KEY_PREFIX + String(userId) + '_' + String(safeAttemptId) + '_' + String(safeQuestionId);
    }

    function buildQuestionCacheIndexedDbMetaKey(attemptId) {
        var storageKey = buildQuestionCacheSessionStorageKey(attemptId);
        return storageKey === '' ? '' : (storageKey + '__meta');
    }

    function buildQuestionCacheIndexedDbItemKey(attemptId, questionId) {
        var storageKey = buildQuestionCacheSessionStorageKey(attemptId);
        var safeQuestionId = Number(questionId) || 0;
        if (storageKey === '' || safeQuestionId <= 0) {
            return '';
        }
        return storageKey + '__item_' + String(safeQuestionId);
    }

    function buildQuestionCacheSessionStorageMetaKey(attemptId) {
        var storageKey = buildQuestionCacheSessionStorageKey(attemptId);
        return storageKey === '' ? '' : (storageKey + '__meta');
    }

    function buildQuestionCacheSessionStorageItemKey(attemptId, questionId) {
        var storageKey = buildQuestionCacheSessionStorageKey(attemptId);
        var safeQuestionId = Number(questionId) || 0;
        if (storageKey === '' || safeQuestionId <= 0) {
            return '';
        }
        return storageKey + '__item_' + String(safeQuestionId);
    }

    function buildQuestionCacheLocalStorageItemKeyPrefix(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || userId <= 0) {
            return '';
        }
        return QUESTION_CACHE_ITEM_LOCAL_STORAGE_KEY_PREFIX + String(userId) + '_' + String(safeAttemptId) + '_';
    }

    function buildQuestionCacheItemKeyPrefix(storageKey) {
        return storageKey === '' ? '' : (storageKey + '__item_');
    }

    function parseQuestionIdFromCacheItemKey(cacheKey, keyPrefix) {
        var safeKey = String(cacheKey || '');
        var safePrefix = String(keyPrefix || '');
        if (safeKey === '' || safePrefix === '' || safeKey.indexOf(safePrefix) !== 0) {
            return 0;
        }

        return Number(safeKey.slice(safePrefix.length)) || 0;
    }

    function mergeQuestionCacheStoredIds(primaryIds, secondaryIds) {
        return normalizeQuestionIdList([].concat(
            Array.isArray(primaryIds) ? primaryIds : [],
            Array.isArray(secondaryIds) ? secondaryIds : []
        ));
    }

    function collectStorageQuestionCacheIds(storage, itemKeyPrefix) {
        if (!storage || itemKeyPrefix === '') {
            return [];
        }

        var discoveredIds = [];
        try {
            var storageLength = Number(storage.length) || 0;
            for (var index = 0; index < storageLength; index++) {
                var currentKey = typeof storage.key === 'function' ? storage.key(index) : '';
                var questionId = parseQuestionIdFromCacheItemKey(currentKey, itemKeyPrefix);
                if (questionId > 0) {
                    discoveredIds.push(questionId);
                }
            }
        } catch (error) {
            return normalizeQuestionIdList(discoveredIds);
        }

        return normalizeQuestionIdList(discoveredIds);
    }

    function normalizeQuestionIdList(rawQuestionIds) {
        if (!Array.isArray(rawQuestionIds)) {
            return [];
        }

        var seen = {};
        return rawQuestionIds.reduce(function (accumulator, item) {
            var questionId = Number(item) || 0;
            if (questionId <= 0 || seen[questionId]) {
                return accumulator;
            }
            seen[questionId] = true;
            accumulator.push(questionId);
            return accumulator;
        }, []);
    }

    function normalizeQuestionRevision(rawRevision, fallbackExamId) {
        var revision = rawRevision && typeof rawRevision === 'object' ? rawRevision : {};
        var examId = Number(revision.exam_id !== undefined ? revision.exam_id : fallbackExamId) || 0;
        var namespace = String(revision.namespace || '');
        var version = Math.max(0, Number(revision.version) || 0);
        var invalidatedAt = Math.max(0, Number(revision.invalidated_at) || 0);
        var signature = String(revision.signature || '');

        if (namespace === '' && examId > 0) {
            namespace = 'exam:' + String(examId);
        }
        if (signature === '' && namespace !== '' && version > 0) {
            signature = namespace + '|v:' + String(version) + '|t:' + String(invalidatedAt);
        }

        if (examId <= 0 || namespace === '' || version <= 0 || signature === '') {
            return null;
        }

        return {
            examId: examId,
            namespace: namespace,
            version: version,
            invalidatedAt: invalidatedAt,
            signature: signature
        };
    }

    function serializeQuestionRevision(revision, fallbackExamId) {
        var normalized = normalizeQuestionRevision(revision, fallbackExamId);
        if (!normalized) {
            return null;
        }

        return {
            exam_id: normalized.examId,
            namespace: normalized.namespace,
            version: normalized.version,
            invalidated_at: normalized.invalidatedAt,
            signature: normalized.signature
        };
    }

    function questionRevisionSignature(revision, fallbackExamId) {
        var normalized = normalizeQuestionRevision(revision, fallbackExamId);
        return normalized ? String(normalized.signature || '') : '';
    }

    function questionRevisionEquals(leftRevision, rightRevision, fallbackExamId) {
        var leftSignature = questionRevisionSignature(leftRevision, fallbackExamId);
        var rightSignature = questionRevisionSignature(rightRevision, fallbackExamId);
        if (leftSignature === '' || rightSignature === '') {
            return leftSignature === rightSignature;
        }
        return leftSignature === rightSignature;
    }

    function compareQuestionRevisionFreshness(leftRevision, rightRevision, fallbackExamId) {
        var left = normalizeQuestionRevision(leftRevision, fallbackExamId);
        var right = normalizeQuestionRevision(rightRevision, fallbackExamId);

        if (!left && !right) {
            return 0;
        }
        if (left && !right) {
            return 1;
        }
        if (!left && right) {
            return -1;
        }
        if (left.version !== right.version) {
            return left.version > right.version ? 1 : -1;
        }
        if (left.invalidatedAt !== right.invalidatedAt) {
            return left.invalidatedAt > right.invalidatedAt ? 1 : -1;
        }
        return 0;
    }

    function bumpQuestionDataGeneration() {
        questionDataGeneration = (questionDataGeneration + 1) % 2147483647;
        if (questionDataGeneration <= 0) {
            questionDataGeneration = 1;
        }
        return questionDataGeneration;
    }

    function isQuestionRevisionRefreshActive() {
        return questionRevisionRefreshInFlight !== null;
    }

    function clearPendingRevisionSafeAnswerRestoreState() {
        pendingRevisionSafeAnswerRestoreByQuestion = {};
    }

    function setQuestionRevision(revision, fallbackExamId) {
        state.questionRevision = normalizeQuestionRevision(revision, fallbackExamId || state.selectedExamId || 0);
        return state.questionRevision;
    }

    function normalizeQuestionManifestItem(question) {
        var item = question && typeof question === 'object' ? question : {};
        var questionId = Number(item.id) || 0;
        if (questionId <= 0) {
            return null;
        }

        var normalized = {
            id: questionId,
            question_type: String(item.question_type || ''),
            updated_at: String(item.updated_at || '')
        };

        var questionNumber = Number(item.question_number) || 0;
        if (questionNumber > 0) {
            normalized.question_number = questionNumber;
        }

        if (Array.isArray(item.options)) {
            normalized.options = item.options.map(function (option) {
                var optionItem = option && typeof option === 'object' ? option : {};
                return {
                    id: Number(optionItem.id) || 0,
                    option_key: String(optionItem.option_key || ''),
                    option_text: String(optionItem.option_text || '')
                };
            }).filter(function (option) {
                return Number(option.id) > 0;
            });
        }

        if (item.true_false_matrix_meta && typeof item.true_false_matrix_meta === 'object') {
            normalized.true_false_matrix_meta = item.true_false_matrix_meta;
        }

        if (item.short_answer_meta && typeof item.short_answer_meta === 'object') {
            normalized.short_answer_meta = item.short_answer_meta;
        }

        return normalized;
    }

    function buildQuestionManifestFromQuestions(questions) {
        if (!Array.isArray(questions)) {
            return [];
        }

        return questions.reduce(function (accumulator, question) {
            var normalized = normalizeQuestionManifestItem(question);
            if (normalized) {
                accumulator.push(normalized);
            }
            return accumulator;
        }, []);
    }

    function buildQuestionManifestById(manifestItems) {
        if (!Array.isArray(manifestItems)) {
            return {};
        }

        return manifestItems.reduce(function (accumulator, item) {
            var normalized = normalizeQuestionManifestItem(item);
            if (!normalized) {
                return accumulator;
            }
            accumulator[normalized.id] = normalized;
            return accumulator;
        }, {});
    }

    function questionManifestUpdatedAt(question) {
        var normalized = normalizeQuestionManifestItem(question);
        if (!normalized) {
            return '';
        }

        return String(normalized.updated_at || '').trim();
    }

    function questionManifestContentSignature(question) {
        var normalized = normalizeQuestionManifestItem(question);
        if (!normalized) {
            return '';
        }

        delete normalized.updated_at;
        delete normalized.question_number;
        return payloadSignature(normalized);
    }

    function buildChangedQuestionLookup(previousManifestById, nextManifestById, preservedLookup) {
        var changedLookup = normalizeStoredBooleanLookup(preservedLookup);
        var safePreviousManifestById = previousManifestById && typeof previousManifestById === 'object'
            ? previousManifestById
            : {};
        var safeNextManifestById = nextManifestById && typeof nextManifestById === 'object'
            ? nextManifestById
            : {};

        Object.keys(safeNextManifestById).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId <= 0) {
                return;
            }

            var previousManifest = safePreviousManifestById[questionId] || null;
            var nextManifest = safeNextManifestById[questionId] || null;
            if (!nextManifest) {
                return;
            }

            if (!previousManifest) {
                changedLookup[questionId] = true;
                return;
            }

            var previousUpdatedAt = questionManifestUpdatedAt(previousManifest);
            var nextUpdatedAt = questionManifestUpdatedAt(nextManifest);
            if (previousUpdatedAt !== '' && nextUpdatedAt !== '') {
                if (previousUpdatedAt !== nextUpdatedAt) {
                    if (questionManifestContentSignature(previousManifest) !== questionManifestContentSignature(nextManifest)) {
                        changedLookup[questionId] = true;
                    }
                }
                return;
            }

            if (questionManifestContentSignature(previousManifest) !== questionManifestContentSignature(nextManifest)) {
                changedLookup[questionId] = true;
            }
        });

        return changedLookup;
    }

    function getChangedQuestionCount() {
        return Object.keys(state.changedQuestionLookup || {}).reduce(function (count, key) {
            var questionId = Number(key) || 0;
            return count + (questionId > 0 && state.changedQuestionLookup[key] ? 1 : 0);
        }, 0);
    }

    function buildQuestionWindowItems(offset, limit) {
        var totalQuestions = getQuestionCount();
        if (totalQuestions <= 0) {
            return [];
        }

        var safeOffset = Math.max(0, Number(offset) || 0);
        var safeLimit = Math.max(1, Number(limit) || QUESTION_WINDOW_SIZE);
        var endIndex = Math.min(totalQuestions, safeOffset + safeLimit);
        var items = [];

        for (var index = safeOffset; index < endIndex; index++) {
            var questionId = getQuestionIdAtIndex(index);
            if (questionId <= 0) {
                return [];
            }

            var question = getQuestionPayloadById(questionId);
            if (!question) {
                return [];
            }

            items.push(question);
        }

        return items;
    }

    function setQuestionWindowFromLoadedPayloads(offset, limit) {
        var safeOffset = Math.max(0, Number(offset) || 0);
        var safeLimit = Math.max(1, Number(limit) || QUESTION_WINDOW_SIZE);
        var items = buildQuestionWindowItems(safeOffset, safeLimit);
        if (!items.length) {
            return false;
        }

        state.windowOffset = safeOffset;
        state.windowLimit = safeLimit;
        state.questions = items;
        markQuestionWindowLoaded(safeOffset);
        return true;
    }

    function normalizeStoredQuestionPayloadById(rawQuestionPayloadById) {
        if (!rawQuestionPayloadById || typeof rawQuestionPayloadById !== 'object') {
            return {};
        }

        return Object.keys(rawQuestionPayloadById).reduce(function (accumulator, key) {
            var question = rawQuestionPayloadById[key];
            var questionId = Number(question && question.id !== undefined ? question.id : key) || 0;
            if (questionId <= 0 || !question || typeof question !== 'object') {
                return accumulator;
            }

            accumulator[questionId] = question;
            return accumulator;
        }, {});
    }

    function normalizeStoredBooleanLookup(rawLookup) {
        if (!rawLookup || typeof rawLookup !== 'object') {
            return {};
        }

        return Object.keys(rawLookup).reduce(function (accumulator, key) {
            var numericKey = Number(key) || 0;
            if (numericKey > 0 && rawLookup[key]) {
                accumulator[numericKey] = true;
            }
            return accumulator;
        }, {});
    }

    function normalizeStoredAnswers(rawAnswers) {
        if (!rawAnswers || typeof rawAnswers !== 'object') {
            return {};
        }

        return Object.keys(rawAnswers).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0) {
                accumulator[questionId] = rawAnswers[key];
            }
            return accumulator;
        }, {});
    }

    function normalizeStoredExistingAnswerRawMap(rawMap) {
        if (!rawMap || typeof rawMap !== 'object') {
            return {};
        }

        return Object.keys(rawMap).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && rawMap[key] !== undefined) {
                accumulator[questionId] = rawMap[key];
            }
            return accumulator;
        }, {});
    }

    function normalizeOrUseQuestionCacheSnapshot(snapshot, attemptId) {
        if (
            snapshot &&
            typeof snapshot === 'object' &&
            Object.prototype.hasOwnProperty.call(snapshot, 'questionPayloadById') &&
            Object.prototype.hasOwnProperty.call(snapshot, 'questionOrderIds')
        ) {
            return snapshot;
        }

        return normalizeQuestionCacheSnapshot(snapshot, attemptId);
    }

    function questionCacheSnapshotsShareRevision(primarySnapshot, secondarySnapshot, attemptId) {
        var normalizedPrimary = normalizeOrUseQuestionCacheSnapshot(primarySnapshot, attemptId);
        var normalizedSecondary = normalizeOrUseQuestionCacheSnapshot(secondarySnapshot, attemptId);
        if (!normalizedPrimary || !normalizedSecondary) {
            return true;
        }

        var primarySignature = questionRevisionSignature(
            normalizedPrimary.questionRevision,
            normalizedPrimary.examId || attemptId || 0
        );
        var secondarySignature = questionRevisionSignature(
            normalizedSecondary.questionRevision,
            normalizedSecondary.examId || attemptId || 0
        );

        if (primarySignature === '' && secondarySignature === '') {
            return true;
        }

        return primarySignature !== '' && primarySignature === secondarySignature;
    }

    function mergeQuestionCachePayloadMaps(primaryPayloadById, secondaryPayloadById) {
        var mergedPayloadById = {};

        [primaryPayloadById, secondaryPayloadById].forEach(function (payloadMap) {
            var normalizedPayloadMap = normalizeStoredQuestionPayloadById(payloadMap);
            Object.keys(normalizedPayloadMap).forEach(function (key) {
                var questionId = Number(key) || 0;
                if (questionId > 0) {
                    mergedPayloadById[questionId] = normalizedPayloadMap[questionId];
                }
            });
        });

        return mergedPayloadById;
    }

    function buildQuestionCacheSnapshotFromBaseAndPayloads(baseSnapshot, extraPayloadById, attemptId) {
        var normalizedBaseSnapshot = normalizeOrUseQuestionCacheSnapshot(baseSnapshot, attemptId);
        var mergedPayloadById = mergeQuestionCachePayloadMaps(
            normalizedBaseSnapshot ? normalizedBaseSnapshot.questionPayloadById : null,
            extraPayloadById
        );

        if (!normalizedBaseSnapshot && !Object.keys(mergedPayloadById).length) {
            return null;
        }

        return normalizeQuestionCacheSnapshot({
            attempt_id: Number(attemptId) || 0,
            exam_id: normalizedBaseSnapshot ? normalizedBaseSnapshot.examId : Number(baseSnapshot && baseSnapshot.exam_id) || 0,
            question_revision: normalizedBaseSnapshot
                ? serializeQuestionRevision(normalizedBaseSnapshot.questionRevision, normalizedBaseSnapshot.examId)
                : (baseSnapshot && baseSnapshot.question_revision),
            total_questions: normalizedBaseSnapshot ? normalizedBaseSnapshot.totalQuestions : Number(baseSnapshot && baseSnapshot.total_questions) || 0,
            question_order_ids: normalizedBaseSnapshot ? normalizedBaseSnapshot.questionOrderIds : (baseSnapshot && baseSnapshot.question_order_ids),
            question_manifest: Object.keys(mergedPayloadById).length
                ? []
                : (normalizedBaseSnapshot ? normalizedBaseSnapshot.questionManifest : (baseSnapshot && baseSnapshot.question_manifest)),
            question_payload_by_id: mergedPayloadById,
            answered_question_lookup: normalizedBaseSnapshot ? normalizedBaseSnapshot.answeredQuestionLookup : (baseSnapshot && baseSnapshot.answered_question_lookup),
            changed_question_lookup: normalizedBaseSnapshot ? normalizedBaseSnapshot.changedQuestionLookup : (baseSnapshot && baseSnapshot.changed_question_lookup),
            answers: normalizedBaseSnapshot ? normalizedBaseSnapshot.answers : (baseSnapshot && baseSnapshot.answers),
            existing_answer_raw_by_question_id: normalizedBaseSnapshot ? normalizedBaseSnapshot.existingAnswerRawByQuestionId : (baseSnapshot && baseSnapshot.existing_answer_raw_by_question_id),
            loaded_question_window_offsets: normalizedBaseSnapshot ? normalizedBaseSnapshot.loadedQuestionWindowOffsets : (baseSnapshot && baseSnapshot.loaded_question_window_offsets),
            window_offset: normalizedBaseSnapshot ? normalizedBaseSnapshot.windowOffset : (baseSnapshot && baseSnapshot.window_offset),
            window_limit: normalizedBaseSnapshot ? normalizedBaseSnapshot.windowLimit : (baseSnapshot && baseSnapshot.window_limit),
            cached_at: Math.max(
                Number(normalizedBaseSnapshot && normalizedBaseSnapshot.cachedAt) || 0,
                Number(baseSnapshot && baseSnapshot.cached_at) || 0
            )
        }, attemptId);
    }

    function normalizeQuestionCacheSnapshot(snapshot, attemptId) {
        var safeAttemptId = Number(attemptId || (snapshot && snapshot.attempt_id)) || 0;
        if (safeAttemptId <= 0 || !snapshot || typeof snapshot !== 'object') {
            return null;
        }

        var questionPayloadById = normalizeStoredQuestionPayloadById(snapshot.question_payload_by_id);
        var payloadQuestions = Object.keys(questionPayloadById).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            var question = questionId > 0 ? questionPayloadById[questionId] : null;
            if (question) {
                accumulator.push(question);
            }
            return accumulator;
        }, []);

        var questionOrderIds = normalizeQuestionIdList(snapshot.question_order_ids);
        if (!questionOrderIds.length && payloadQuestions.length) {
            questionOrderIds = normalizeQuestionIdList(payloadQuestions.map(function (question) {
                return Number(question && question.id) || 0;
            }));
        }

        var questionManifest = Array.isArray(snapshot.question_manifest)
            ? snapshot.question_manifest.map(normalizeQuestionManifestItem).filter(function (item) { return !!item; })
            : [];
        if (!questionManifest.length && payloadQuestions.length) {
            questionManifest = buildQuestionManifestFromQuestions(payloadQuestions);
        }

        var answeredQuestionLookup = normalizeStoredBooleanLookup(snapshot.answered_question_lookup);
        var changedQuestionLookup = normalizeStoredBooleanLookup(snapshot.changed_question_lookup);
        var answers = normalizeStoredAnswers(snapshot.answers);
        var existingAnswerRawByQuestionId = normalizeStoredExistingAnswerRawMap(snapshot.existing_answer_raw_by_question_id);
        if (payloadQuestions.length && (!Object.keys(answeredQuestionLookup).length || !Object.keys(answers).length)) {
            payloadQuestions.forEach(function (question) {
                var questionId = Number(question && question.id) || 0;
                var normalizedExistingAnswer = normalizeExistingAnswerForQuestion(question);
                if (questionId <= 0 || !normalizedExistingAnswer.hasValue) {
                    return;
                }

                answeredQuestionLookup[questionId] = true;
                if (!Object.prototype.hasOwnProperty.call(answers, questionId)) {
                    answers[questionId] = normalizedExistingAnswer.value;
                }
                if (Object.prototype.hasOwnProperty.call(question, 'existing_answer')) {
                    existingAnswerRawByQuestionId[questionId] = question.existing_answer;
                }
            });
        }

        if (!questionOrderIds.length && !payloadQuestions.length) {
            return null;
        }

        return {
            attemptId: safeAttemptId,
            examId: Number(snapshot.exam_id) || 0,
            questionRevision: normalizeQuestionRevision(snapshot.question_revision, Number(snapshot.exam_id) || 0),
            totalQuestions: Math.max(
                Number(snapshot.total_questions) || 0,
                questionOrderIds.length,
                payloadQuestions.length
            ),
            questionOrderIds: questionOrderIds,
            questionManifest: questionManifest,
            questionPayloadById: questionPayloadById,
            answeredQuestionLookup: answeredQuestionLookup,
            changedQuestionLookup: changedQuestionLookup,
            answers: answers,
            existingAnswerRawByQuestionId: existingAnswerRawByQuestionId,
            loadedQuestionWindowOffsets: normalizeStoredBooleanLookup(snapshot.loaded_question_window_offsets),
            windowOffset: Math.max(0, Number(snapshot.window_offset) || 0),
            windowLimit: Math.max(0, Number(snapshot.window_limit) || 0),
            cachedAt: Math.max(0, Number(snapshot.cached_at) || 0)
        };
    }

    function buildQuestionCacheSnapshot(attemptId) {
        var safeAttemptId = Number(attemptId || state.attemptId) || 0;
        if (safeAttemptId <= 0) {
            return null;
        }

        var questionPayloadById = Object.keys(state.questionPayloadById || {}).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            var question = questionId > 0 ? getQuestionPayloadById(questionId) : null;
            if (question) {
                accumulator[questionId] = question;
            }
            return accumulator;
        }, {});

        var questionPayloadCount = Object.keys(questionPayloadById).length;
        if (questionPayloadCount <= 0) {
            return null;
        }

        var questionOrderIds = Array.isArray(state.questionOrderIds) ? state.questionOrderIds.slice() : [];
        var manifestItems = Array.isArray(state.questionManifest) && state.questionManifest.length
            ? state.questionManifest.slice()
            : buildQuestionManifestFromQuestions(Object.keys(questionPayloadById).map(function (key) {
                return questionPayloadById[key];
            }));

        return {
            attempt_id: safeAttemptId,
            exam_id: Number(state.selectedExamId) || 0,
            question_revision: serializeQuestionRevision(state.questionRevision, Number(state.selectedExamId) || 0),
            total_questions: Math.max(Number(state.totalQuestions) || 0, questionOrderIds.length, questionPayloadCount),
            question_order_ids: questionOrderIds,
            question_manifest: manifestItems,
            question_payload_by_id: questionPayloadById,
            answered_question_lookup: Object.keys(state.answeredQuestionLookup || {}).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.answeredQuestionLookup[key]) {
                    accumulator[questionId] = true;
                }
                return accumulator;
            }, {}),
            changed_question_lookup: Object.keys(state.changedQuestionLookup || {}).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.changedQuestionLookup[key]) {
                    accumulator[questionId] = true;
                }
                return accumulator;
            }, {}),
            answers: Object.keys(state.answers || {}).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.answers[key] !== undefined) {
                    accumulator[questionId] = state.answers[key];
                }
                return accumulator;
            }, {}),
            existing_answer_raw_by_question_id: Object.keys(state.existingAnswerRawByQuestionId || {}).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.existingAnswerRawByQuestionId[key] !== undefined) {
                    accumulator[questionId] = state.existingAnswerRawByQuestionId[key];
                }
                return accumulator;
            }, {}),
            loaded_question_window_offsets: Object.keys(state.loadedQuestionWindowOffsets || {}).reduce(function (accumulator, key) {
                var offset = Number(key);
                if (Number.isFinite(offset) && offset >= 0 && state.loadedQuestionWindowOffsets[key]) {
                    accumulator[offset] = true;
                }
                return accumulator;
            }, {}),
            window_offset: Math.max(0, Number(state.windowOffset) || 0),
            window_limit: Math.max(0, Number(state.windowLimit) || 0),
            cached_at: Date.now()
        };
    }

    function serializeQuestionCacheSnapshot(normalizedSnapshot) {
        if (!normalizedSnapshot) {
            return null;
        }

        return {
            attempt_id: normalizedSnapshot.attemptId,
            exam_id: normalizedSnapshot.examId,
            question_revision: serializeQuestionRevision(normalizedSnapshot.questionRevision, normalizedSnapshot.examId),
            total_questions: normalizedSnapshot.totalQuestions,
            question_order_ids: normalizedSnapshot.questionOrderIds,
            question_manifest: normalizedSnapshot.questionManifest,
            question_payload_by_id: normalizedSnapshot.questionPayloadById,
            answered_question_lookup: normalizedSnapshot.answeredQuestionLookup,
            changed_question_lookup: normalizedSnapshot.changedQuestionLookup,
            answers: normalizedSnapshot.answers,
            existing_answer_raw_by_question_id: normalizedSnapshot.existingAnswerRawByQuestionId,
            loaded_question_window_offsets: normalizedSnapshot.loadedQuestionWindowOffsets,
            window_offset: normalizedSnapshot.windowOffset,
            window_limit: normalizedSnapshot.windowLimit,
            cached_at: Date.now()
        };
    }

    function buildQuestionCacheStoredQuestionIds(questionPayloadById) {
        if (!questionPayloadById || typeof questionPayloadById !== 'object') {
            return [];
        }

        return Object.keys(questionPayloadById).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && questionPayloadById[questionId]) {
                accumulator.push(questionId);
            }
            return accumulator;
        }, []);
    }

    function serializeQuestionCacheMetaSnapshot(normalizedSnapshot, storedQuestionIds) {
        if (!normalizedSnapshot) {
            return null;
        }

        return {
            attempt_id: normalizedSnapshot.attemptId,
            exam_id: normalizedSnapshot.examId,
            question_revision: serializeQuestionRevision(normalizedSnapshot.questionRevision, normalizedSnapshot.examId),
            total_questions: normalizedSnapshot.totalQuestions,
            question_order_ids: normalizedSnapshot.questionOrderIds,
            answered_question_lookup: normalizedSnapshot.answeredQuestionLookup,
            changed_question_lookup: normalizedSnapshot.changedQuestionLookup,
            answers: normalizedSnapshot.answers,
            existing_answer_raw_by_question_id: normalizedSnapshot.existingAnswerRawByQuestionId,
            loaded_question_window_offsets: normalizedSnapshot.loadedQuestionWindowOffsets,
            window_offset: normalizedSnapshot.windowOffset,
            window_limit: normalizedSnapshot.windowLimit,
            stored_question_ids: normalizeQuestionIdList(storedQuestionIds),
            cached_at: Date.now()
        };
    }

    function normalizeQuestionCacheSnapshotFromMeta(metaSnapshot, questionPayloadById, attemptId) {
        return normalizeQuestionCacheSnapshot({
            attempt_id: attemptId,
            exam_id: metaSnapshot && metaSnapshot.exam_id,
            question_revision: metaSnapshot && metaSnapshot.question_revision,
            total_questions: metaSnapshot && metaSnapshot.total_questions,
            question_order_ids: metaSnapshot && metaSnapshot.question_order_ids,
            question_manifest: metaSnapshot && metaSnapshot.question_manifest,
            question_payload_by_id: questionPayloadById || {},
            answered_question_lookup: metaSnapshot && metaSnapshot.answered_question_lookup,
            changed_question_lookup: metaSnapshot && metaSnapshot.changed_question_lookup,
            answers: metaSnapshot && metaSnapshot.answers,
            existing_answer_raw_by_question_id: metaSnapshot && metaSnapshot.existing_answer_raw_by_question_id,
            loaded_question_window_offsets: metaSnapshot && metaSnapshot.loaded_question_window_offsets,
            window_offset: metaSnapshot && metaSnapshot.window_offset,
            window_limit: metaSnapshot && metaSnapshot.window_limit,
            cached_at: metaSnapshot && metaSnapshot.cached_at
        }, attemptId);
    }

    function questionCachePayloadCount(snapshot) {
        if (!snapshot || !snapshot.questionPayloadById || typeof snapshot.questionPayloadById !== 'object') {
            return 0;
        }

        return Object.keys(snapshot.questionPayloadById).reduce(function (count, key) {
            var questionId = Number(key) || 0;
            return count + (questionId > 0 ? 1 : 0);
        }, 0);
    }

    function choosePreferredQuestionCacheSnapshot(primarySnapshot, secondarySnapshot) {
        if (!primarySnapshot) {
            return secondarySnapshot || null;
        }
        if (!secondarySnapshot) {
            return primarySnapshot;
        }

        var revisionComparison = compareQuestionRevisionFreshness(
            primarySnapshot.questionRevision,
            secondarySnapshot.questionRevision,
            primarySnapshot.examId || secondarySnapshot.examId || 0
        );
        if (revisionComparison !== 0) {
            return revisionComparison > 0 ? primarySnapshot : secondarySnapshot;
        }

        var primaryCount = questionCachePayloadCount(primarySnapshot);
        var secondaryCount = questionCachePayloadCount(secondarySnapshot);
        if (primaryCount !== secondaryCount) {
            return primaryCount > secondaryCount ? primarySnapshot : secondarySnapshot;
        }

        return (Number(primarySnapshot.cachedAt) || 0) >= (Number(secondarySnapshot.cachedAt) || 0)
            ? primarySnapshot
            : secondarySnapshot;
    }

    function mergeStoredBooleanLookups(primaryLookup, secondaryLookup) {
        return normalizeStoredBooleanLookup(Object.assign(
            {},
            primaryLookup && typeof primaryLookup === 'object' ? primaryLookup : {},
            secondaryLookup && typeof secondaryLookup === 'object' ? secondaryLookup : {}
        ));
    }

    function mergeStoredAnswers(primaryAnswers, secondaryAnswers) {
        return normalizeStoredAnswers(Object.assign(
            {},
            primaryAnswers && typeof primaryAnswers === 'object' ? primaryAnswers : {},
            secondaryAnswers && typeof secondaryAnswers === 'object' ? secondaryAnswers : {}
        ));
    }

    function mergeStoredExistingAnswerRawMaps(primaryMap, secondaryMap) {
        return normalizeStoredExistingAnswerRawMap(Object.assign(
            {},
            primaryMap && typeof primaryMap === 'object' ? primaryMap : {},
            secondaryMap && typeof secondaryMap === 'object' ? secondaryMap : {}
        ));
    }

    function choosePreferredQuestionOrderSnapshot(preferredBaseSnapshot, primarySnapshot, secondarySnapshot) {
        if (preferredBaseSnapshot && Array.isArray(preferredBaseSnapshot.questionOrderIds) && preferredBaseSnapshot.questionOrderIds.length) {
            return preferredBaseSnapshot;
        }

        if (primarySnapshot.questionOrderIds.length !== secondarySnapshot.questionOrderIds.length) {
            return primarySnapshot.questionOrderIds.length > secondarySnapshot.questionOrderIds.length
                ? primarySnapshot
                : secondarySnapshot;
        }

        return (Number(primarySnapshot.cachedAt) || 0) >= (Number(secondarySnapshot.cachedAt) || 0)
            ? primarySnapshot
            : secondarySnapshot;
    }

    function mergeQuestionCacheSnapshots(primarySnapshot, secondarySnapshot, attemptId) {
        var normalizedPrimary = normalizeOrUseQuestionCacheSnapshot(primarySnapshot, attemptId);
        var normalizedSecondary = normalizeOrUseQuestionCacheSnapshot(secondarySnapshot, attemptId);
        if (!normalizedPrimary) {
            return normalizedSecondary;
        }
        if (!normalizedSecondary) {
            return normalizedPrimary;
        }
        if (!questionCacheSnapshotsShareRevision(normalizedPrimary, normalizedSecondary, attemptId)) {
            return choosePreferredQuestionCacheSnapshot(normalizedPrimary, normalizedSecondary);
        }

        var preferredBaseSnapshot = choosePreferredQuestionCacheSnapshot(normalizedPrimary, normalizedSecondary);
        var preferredOrderSnapshot = choosePreferredQuestionOrderSnapshot(
            preferredBaseSnapshot,
            normalizedPrimary,
            normalizedSecondary
        );
        var mergedPayloadById = mergeQuestionCachePayloadMaps(
            normalizedPrimary.questionPayloadById,
            normalizedSecondary.questionPayloadById
        );

        return buildQuestionCacheSnapshotFromBaseAndPayloads({
            attempt_id: Number(attemptId) || normalizedPrimary.attemptId || normalizedSecondary.attemptId || 0,
            exam_id: preferredBaseSnapshot.examId || preferredOrderSnapshot.examId || 0,
            question_revision: serializeQuestionRevision(
                preferredBaseSnapshot.questionRevision || preferredOrderSnapshot.questionRevision,
                preferredBaseSnapshot.examId || preferredOrderSnapshot.examId || 0
            ),
            total_questions: Math.max(
                Number(normalizedPrimary.totalQuestions) || 0,
                Number(normalizedSecondary.totalQuestions) || 0,
                Object.keys(mergedPayloadById).length
            ),
            question_order_ids: preferredOrderSnapshot.questionOrderIds,
            question_manifest: preferredOrderSnapshot.questionManifest,
            answered_question_lookup: mergeStoredBooleanLookups(
                normalizedPrimary.answeredQuestionLookup,
                normalizedSecondary.answeredQuestionLookup
            ),
            changed_question_lookup: mergeStoredBooleanLookups(
                normalizedPrimary.changedQuestionLookup,
                normalizedSecondary.changedQuestionLookup
            ),
            answers: mergeStoredAnswers(
                normalizedPrimary.answers,
                normalizedSecondary.answers
            ),
            existing_answer_raw_by_question_id: mergeStoredExistingAnswerRawMaps(
                normalizedPrimary.existingAnswerRawByQuestionId,
                normalizedSecondary.existingAnswerRawByQuestionId
            ),
            loaded_question_window_offsets: mergeStoredBooleanLookups(
                normalizedPrimary.loadedQuestionWindowOffsets,
                normalizedSecondary.loadedQuestionWindowOffsets
            ),
            window_offset: Number(preferredBaseSnapshot.windowOffset) || 0,
            window_limit: Number(preferredBaseSnapshot.windowLimit) || 0,
            cached_at: Math.max(
                Number(normalizedPrimary.cachedAt) || 0,
                Number(normalizedSecondary.cachedAt) || 0
            )
        }, mergedPayloadById, attemptId);
    }

    function persistQuestionCacheToSessionStorage(storageKey, normalizedSnapshot, storedSnapshot) {
        var storage = getSessionStorage();
        if (!storage || storageKey === '' || !normalizedSnapshot) {
            return;
        }

        var metaKey = buildQuestionCacheSessionStorageMetaKey(normalizedSnapshot.attemptId);
        var storedQuestionIds = [];
        buildQuestionCacheStoredQuestionIds(normalizedSnapshot.questionPayloadById).forEach(function (questionId) {
            var itemKey = buildQuestionCacheSessionStorageItemKey(normalizedSnapshot.attemptId, questionId);
            var questionPayload = normalizedSnapshot.questionPayloadById[questionId];
            if (itemKey === '' || !questionPayload) {
                return;
            }

            try {
                storage.setItem(itemKey, JSON.stringify(questionPayload));
                storedQuestionIds.push(questionId);
            } catch (error) {
                // Stop growing the per-question session cache when quota is reached.
            }
        });

        if (metaKey !== '') {
            try {
                storage.setItem(metaKey, JSON.stringify(serializeQuestionCacheMetaSnapshot(normalizedSnapshot, storedQuestionIds)));
            } catch (error) {
                // Ignore storage failures (quota or disabled storage).
            }
        }

        if (!storedSnapshot) {
            return;
        }

        try {
            storage.setItem(storageKey, JSON.stringify(storedSnapshot));
        } catch (error) {
            // Ignore storage failures (quota or disabled storage).
        }
    }

    function persistQuestionCacheToLocalStorage(normalizedSnapshot) {
        var storage = getLocalStorage();
        if (!storage || !normalizedSnapshot) {
            return false;
        }

        var metaKey = buildQuestionCacheMetaLocalStorageKey(normalizedSnapshot.attemptId);
        if (metaKey === '') {
            return false;
        }

        var storedQuestionIds = [];
        buildQuestionCacheStoredQuestionIds(normalizedSnapshot.questionPayloadById).forEach(function (questionId) {
            var questionPayload = normalizedSnapshot.questionPayloadById[questionId];
            if (!questionPayload) {
                return;
            }

            var itemKey = buildQuestionCacheItemLocalStorageKey(normalizedSnapshot.attemptId, questionId);
            if (itemKey === '') {
                return;
            }

            try {
                storage.setItem(itemKey, JSON.stringify(questionPayload));
                storedQuestionIds.push(questionId);
            } catch (error) {
                // Stop growing the cache when browser quota is reached.
            }
        });

        try {
            storage.setItem(metaKey, JSON.stringify(serializeQuestionCacheMetaSnapshot(normalizedSnapshot, storedQuestionIds)));
            return true;
        } catch (error) {
            return false;
        }
    }

    function persistQuestionCacheToIndexedDb(storageKey, normalizedSnapshot) {
        if (storageKey === '' || !normalizedSnapshot) {
            return Promise.resolve(false);
        }

        var metaKey = buildQuestionCacheIndexedDbMetaKey(normalizedSnapshot.attemptId);
        if (metaKey === '') {
            return Promise.resolve(false);
        }

        var storedQuestionIds = buildQuestionCacheStoredQuestionIds(normalizedSnapshot.questionPayloadById);
        var metaSnapshot = serializeQuestionCacheMetaSnapshot(normalizedSnapshot, storedQuestionIds);
        if (!metaSnapshot) {
            return Promise.resolve(false);
        }

        return openQuestionCacheIndexedDb().then(function (database) {
            if (!database) {
                return false;
            }

            return new Promise(function (resolve) {
                try {
                    var transaction = database.transaction(QUESTION_CACHE_INDEXED_DB_STORE, 'readwrite');
                    var store = transaction.objectStore(QUESTION_CACHE_INDEXED_DB_STORE);
                    store.put({
                        cache_key: metaKey,
                        snapshot: metaSnapshot,
                        updated_at: Date.now()
                    });
                    storedQuestionIds.forEach(function (questionId) {
                        var itemKey = buildQuestionCacheIndexedDbItemKey(normalizedSnapshot.attemptId, questionId);
                        var questionPayload = normalizedSnapshot.questionPayloadById[questionId];
                        if (itemKey === '' || !questionPayload) {
                            return;
                        }

                        store.put({
                            cache_key: itemKey,
                            payload: questionPayload,
                            updated_at: Date.now()
                        });
                    });
                    // Clear the older monolithic snapshot so future restores use the per-question cache.
                    store.delete(storageKey);
                    transaction.oncomplete = function () {
                        resolve(true);
                    };
                    transaction.onerror = function () {
                        resolve(false);
                    };
                    transaction.onabort = function () {
                        resolve(false);
                    };
                } catch (error) {
                    resolve(false);
                }
            });
        }).catch(function () {
            return false;
        });
    }

    function persistQuestionCacheLocally(snapshot) {
        var normalizedSnapshot = normalizeQuestionCacheSnapshot(snapshot, snapshot && snapshot.attempt_id);
        if (!normalizedSnapshot) {
            return;
        }

        var storageKey = buildQuestionCacheSessionStorageKey(normalizedSnapshot.attemptId);
        if (storageKey === '') {
            return;
        }

        var storedSnapshot = serializeQuestionCacheSnapshot(normalizedSnapshot);
        if (storedSnapshot) {
            persistQuestionCacheToSessionStorage(storageKey, normalizedSnapshot, storedSnapshot);
        }
        persistQuestionCacheToLocalStorage(normalizedSnapshot);
        persistQuestionCacheToIndexedDb(storageKey, normalizedSnapshot);
    }

    function persistCurrentQuestionCacheLocally() {
        var snapshot = buildQuestionCacheSnapshot();
        if (!snapshot) {
            return;
        }

        persistQuestionCacheLocally(snapshot);
    }

    function clearQuestionCachePersistTimer() {
        if (questionCachePersistTimer) {
            window.clearTimeout(questionCachePersistTimer);
        }
        questionCachePersistTimer = 0;
    }

    function scheduleQuestionCachePersist(delayMs) {
        if (state.attemptId <= 0) {
            return;
        }

        clearQuestionCachePersistTimer();
        questionCachePersistTimer = window.setTimeout(function () {
            clearQuestionCachePersistTimer();
            persistCurrentQuestionCacheLocally();
        }, Math.max(0, Number(delayMs) || 0));
    }

    function readPersistedQuestionCacheFromSessionStorage(attemptId) {
        var storage = getSessionStorage();
        if (!storage) {
            return null;
        }

        var safeAttemptId = Number(attemptId) || 0;
        var storageKey = buildQuestionCacheSessionStorageKey(safeAttemptId);
        var metaKey = buildQuestionCacheSessionStorageMetaKey(safeAttemptId);
        if (storageKey === '') {
            return null;
        }

        if (metaKey !== '') {
            try {
                var rawMeta = storage.getItem(metaKey);
                var discoveredQuestionIds = collectStorageQuestionCacheIds(storage, buildQuestionCacheItemKeyPrefix(storageKey));
                var questionPayloadById = {};
                discoveredQuestionIds.forEach(function (questionId) {
                    var itemKey = buildQuestionCacheSessionStorageItemKey(safeAttemptId, questionId);
                    if (itemKey === '') {
                        return;
                    }

                    try {
                        var rawItem = storage.getItem(itemKey);
                        if (!rawItem) {
                            return;
                        }

                        var parsedQuestion = JSON.parse(rawItem);
                        if (parsedQuestion && typeof parsedQuestion === 'object') {
                            questionPayloadById[questionId] = parsedQuestion;
                        }
                    } catch (error) {
                        // Ignore broken session question cache items.
                    }
                });

                var parsedMeta = rawMeta ? JSON.parse(rawMeta) : null;
                var rawLegacy = storage.getItem(storageKey);
                var parsedLegacy = rawLegacy ? JSON.parse(rawLegacy) : null;
                var mergedBaseSnapshot = mergeQuestionCacheSnapshots(parsedMeta, parsedLegacy, safeAttemptId);

                var mergedSnapshot = buildQuestionCacheSnapshotFromBaseAndPayloads(
                    mergedBaseSnapshot || parsedMeta || parsedLegacy,
                    questionPayloadById,
                    safeAttemptId
                );
                if (mergedSnapshot) {
                    return mergedSnapshot;
                }
            } catch (error) {
                // Fall through to legacy monolithic snapshot.
            }
        }

        try {
            var raw = storage.getItem(storageKey);
            if (!raw) {
                return null;
            }

            return normalizeQuestionCacheSnapshot(JSON.parse(raw), safeAttemptId);
        } catch (error) {
            return null;
        }
    }

    function readPersistedQuestionCacheFromLocalStorage(attemptId) {
        var storage = getLocalStorage();
        if (!storage) {
            return null;
        }

        var safeAttemptId = Number(attemptId) || 0;
        var metaKey = buildQuestionCacheMetaLocalStorageKey(safeAttemptId);
        if (metaKey === '') {
            return null;
        }

        try {
            var rawMeta = storage.getItem(metaKey);
            var baseSnapshot = null;
            if (rawMeta) {
                baseSnapshot = JSON.parse(rawMeta);
            }

            var storedQuestionIds = collectStorageQuestionCacheIds(storage, buildQuestionCacheLocalStorageItemKeyPrefix(safeAttemptId));
            if (!storedQuestionIds.length && !baseSnapshot) {
                return null;
            }
            var questionPayloadById = {};

            storedQuestionIds.forEach(function (questionId) {
                var itemKey = buildQuestionCacheItemLocalStorageKey(safeAttemptId, questionId);
                if (itemKey === '') {
                    return;
                }

                try {
                    var rawItem = storage.getItem(itemKey);
                    if (!rawItem) {
                        return;
                    }

                    var parsedQuestion = JSON.parse(rawItem);
                    if (parsedQuestion && typeof parsedQuestion === 'object') {
                        questionPayloadById[questionId] = parsedQuestion;
                    }
                } catch (error) {
                    // Ignore broken question payload cache items.
                }
            });

            return buildQuestionCacheSnapshotFromBaseAndPayloads(
                baseSnapshot || readPersistedQuestionCacheFromSessionStorage(safeAttemptId),
                questionPayloadById,
                safeAttemptId
            );
        } catch (error) {
            return null;
        }
    }

    function readPersistedQuestionCacheFromIndexedDb(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var storageKey = buildQuestionCacheSessionStorageKey(safeAttemptId);
        var metaKey = buildQuestionCacheIndexedDbMetaKey(safeAttemptId);
        if (storageKey === '' || metaKey === '') {
            return Promise.resolve(null);
        }

        return openQuestionCacheIndexedDb().then(function (database) {
            if (!database) {
                return null;
            }

            return new Promise(function (resolve) {
                try {
                    var transaction = database.transaction(QUESTION_CACHE_INDEXED_DB_STORE, 'readonly');
                    var store = transaction.objectStore(QUESTION_CACHE_INDEXED_DB_STORE);
                    var resolved = false;
                    var itemKeyPrefix = buildQuestionCacheItemKeyPrefix(storageKey);
                    function resolveOnce(snapshot) {
                        if (resolved) {
                            return;
                        }
                        resolved = true;
                        resolve(snapshot);
                    }

                    var metaSnapshot = null;
                    var metaResolved = false;
                    var cursorResolved = false;
                    var questionPayloadById = {};
                    var discoveredQuestionIds = [];

                    function finalizeIndexedDbSnapshot() {
                        if (!metaResolved || !cursorResolved) {
                            return;
                        }

                        var mergedMetaSnapshot = metaSnapshot ? Object.keys(metaSnapshot).reduce(function (accumulator, key) {
                            accumulator[key] = metaSnapshot[key];
                            return accumulator;
                        }, {}) : null;
                        if (mergedMetaSnapshot) {
                            mergedMetaSnapshot.stored_question_ids = mergeQuestionCacheStoredIds(
                                metaSnapshot && metaSnapshot.stored_question_ids,
                                discoveredQuestionIds
                            );
                        }
                        try {
                            var legacyRequest = store.get(storageKey);
                            legacyRequest.onsuccess = function () {
                                var record = legacyRequest.result;
                                var legacySnapshot = record && record.snapshot ? record.snapshot : null;
                                var mergedBaseSnapshot = mergeQuestionCacheSnapshots(
                                    mergedMetaSnapshot,
                                    legacySnapshot,
                                    safeAttemptId
                                );
                                var mergedSnapshot = buildQuestionCacheSnapshotFromBaseAndPayloads(
                                    mergedBaseSnapshot || mergedMetaSnapshot || legacySnapshot,
                                    questionPayloadById,
                                    safeAttemptId
                                );
                                resolveOnce(mergedSnapshot);
                            };
                            legacyRequest.onerror = function () {
                                var mergedSnapshot = buildQuestionCacheSnapshotFromBaseAndPayloads(
                                    mergedMetaSnapshot,
                                    questionPayloadById,
                                    safeAttemptId
                                );
                                resolveOnce(mergedSnapshot);
                            };
                        } catch (error) {
                            var mergedSnapshot = buildQuestionCacheSnapshotFromBaseAndPayloads(
                                mergedMetaSnapshot,
                                questionPayloadById,
                                safeAttemptId
                            );
                            resolveOnce(mergedSnapshot);
                        }
                    }

                    var metaRequest = store.get(metaKey);
                    metaRequest.onsuccess = function () {
                        var metaRecord = metaRequest.result;
                        metaSnapshot = metaRecord && metaRecord.snapshot ? metaRecord.snapshot : null;
                        metaResolved = true;
                        finalizeIndexedDbSnapshot();
                    };

                    metaRequest.onerror = function () {
                        metaResolved = true;
                        metaSnapshot = null;
                        finalizeIndexedDbSnapshot();
                    };

                    var cursorRequest = store.openCursor();
                    cursorRequest.onsuccess = function (event) {
                        var cursor = event && event.target ? event.target.result : null;
                        if (!cursor) {
                            cursorResolved = true;
                            finalizeIndexedDbSnapshot();
                            return;
                        }

                        var cacheKey = String(cursor.key || '');
                        var questionId = parseQuestionIdFromCacheItemKey(cacheKey, itemKeyPrefix);
                        var itemRecord = cursor.value;
                        if (questionId > 0 && itemRecord && itemRecord.payload && typeof itemRecord.payload === 'object') {
                            discoveredQuestionIds.push(questionId);
                            questionPayloadById[questionId] = itemRecord.payload;
                        }

                        cursor.continue();
                    };

                    cursorRequest.onerror = function () {
                        cursorResolved = true;
                        finalizeIndexedDbSnapshot();
                    };

                    transaction.onerror = function () {
                        resolveOnce(null);
                    };
                } catch (error) {
                    resolve(null);
                }
            });
        }).catch(function () {
            return null;
        });
    }

    async function readPersistedQuestionCache(attemptId) {
        var indexedDbSnapshot = await readPersistedQuestionCacheFromIndexedDb(attemptId);
        var localStorageSnapshot = readPersistedQuestionCacheFromLocalStorage(attemptId);
        var sessionSnapshot = readPersistedQuestionCacheFromSessionStorage(attemptId);
        var mergedSnapshot = mergeQuestionCacheSnapshots(
            mergeQuestionCacheSnapshots(indexedDbSnapshot, localStorageSnapshot, attemptId),
            sessionSnapshot,
            attemptId
        );
        setQuestionCacheRestoreDebug({
            attemptId: Number(attemptId) || 0,
            indexedDbPayloadCount: questionCachePayloadCount(indexedDbSnapshot),
            indexedDbRevision: questionRevisionSignature(indexedDbSnapshot && indexedDbSnapshot.questionRevision, indexedDbSnapshot && indexedDbSnapshot.examId),
            localStoragePayloadCount: questionCachePayloadCount(localStorageSnapshot),
            localStorageRevision: questionRevisionSignature(localStorageSnapshot && localStorageSnapshot.questionRevision, localStorageSnapshot && localStorageSnapshot.examId),
            sessionPayloadCount: questionCachePayloadCount(sessionSnapshot),
            sessionRevision: questionRevisionSignature(sessionSnapshot && sessionSnapshot.questionRevision, sessionSnapshot && sessionSnapshot.examId),
            mergedPayloadCount: questionCachePayloadCount(mergedSnapshot),
            mergedOrderCount: mergedSnapshot && Array.isArray(mergedSnapshot.questionOrderIds)
                ? mergedSnapshot.questionOrderIds.length
                : 0,
            mergedWindowOffset: mergedSnapshot ? Number(mergedSnapshot.windowOffset) || 0 : 0,
            mergedWindowLimit: mergedSnapshot ? Number(mergedSnapshot.windowLimit) || 0 : 0,
            mergedCachedAt: mergedSnapshot ? Number(mergedSnapshot.cachedAt) || 0 : 0,
            mergedRevision: questionRevisionSignature(mergedSnapshot && mergedSnapshot.questionRevision, mergedSnapshot && mergedSnapshot.examId),
            timestamp: Date.now()
        });
        return mergedSnapshot;
    }

    function clearPersistedQuestionCache(attemptId) {
        var storageKey = buildQuestionCacheSessionStorageKey(attemptId);
        if (storageKey === '') {
            return;
        }

        var storage = getSessionStorage();
        try {
            if (storage) {
                var metaKey = buildQuestionCacheSessionStorageMetaKey(attemptId);
                var itemKeyPrefix = buildQuestionCacheItemKeyPrefix(storageKey);
                if (metaKey !== '') {
                    try {
                        collectStorageQuestionCacheIds(storage, itemKeyPrefix).forEach(function (questionId) {
                            var itemKey = buildQuestionCacheSessionStorageItemKey(attemptId, questionId);
                            if (itemKey !== '') {
                                storage.removeItem(itemKey);
                            }
                        });
                    } catch (error) {
                        // Ignore sessionStorage cache cleanup failures.
                    }

                    try {
                        var storageLength = Number(storage.length) || 0;
                        for (var index = storageLength - 1; index >= 0; index--) {
                            var extraStorageKey = typeof storage.key === 'function' ? storage.key(index) : '';
                            if (parseQuestionIdFromCacheItemKey(extraStorageKey, itemKeyPrefix) > 0) {
                                storage.removeItem(extraStorageKey);
                            }
                        }
                    } catch (error) {
                        // Ignore sessionStorage cache cleanup failures.
                    }

                    try {
                        storage.removeItem(metaKey);
                    } catch (error) {
                        // Ignore sessionStorage cache cleanup failures.
                    }
                }

                storage.removeItem(storageKey);
            }
        } catch (error) {
            // Ignore storage failures (private mode / blocked storage).
        }

        var localStorage = getLocalStorage();
        var metaKey = buildQuestionCacheMetaLocalStorageKey(attemptId);
        if (localStorage && metaKey !== '') {
            var localItemKeyPrefix = buildQuestionCacheLocalStorageItemKeyPrefix(attemptId);
            try {
                collectStorageQuestionCacheIds(localStorage, localItemKeyPrefix).forEach(function (questionId) {
                    var itemKey = buildQuestionCacheItemLocalStorageKey(attemptId, questionId);
                    if (itemKey !== '') {
                        localStorage.removeItem(itemKey);
                    }
                });
            } catch (error) {
                // Ignore localStorage cache cleanup failures.
            }

            try {
                var localStorageLength = Number(localStorage.length) || 0;
                for (var localIndex = localStorageLength - 1; localIndex >= 0; localIndex--) {
                    var extraLocalKey = typeof localStorage.key === 'function' ? localStorage.key(localIndex) : '';
                    if (parseQuestionIdFromCacheItemKey(extraLocalKey, localItemKeyPrefix) > 0) {
                        localStorage.removeItem(extraLocalKey);
                    }
                }
            } catch (error) {
                // Ignore localStorage cache cleanup failures.
            }

            try {
                localStorage.removeItem(metaKey);
            } catch (error) {
                // Ignore localStorage cache cleanup failures.
            }
        }

        openQuestionCacheIndexedDb().then(function (database) {
            if (!database) {
                return;
            }

            try {
                var transaction = database.transaction(QUESTION_CACHE_INDEXED_DB_STORE, 'readwrite');
                var store = transaction.objectStore(QUESTION_CACHE_INDEXED_DB_STORE);
                var metaKey = buildQuestionCacheIndexedDbMetaKey(attemptId);
                var itemKeyPrefix = buildQuestionCacheItemKeyPrefix(storageKey);

                store.delete(storageKey);
                if (metaKey !== '') {
                    var cursorRequest = store.openCursor();
                    cursorRequest.onsuccess = function (event) {
                        var cursor = event && event.target ? event.target.result : null;
                        if (!cursor) {
                            store.delete(metaKey);
                            return;
                        }

                        var cacheKey = String(cursor.key || '');
                        if (parseQuestionIdFromCacheItemKey(cacheKey, itemKeyPrefix) > 0) {
                            store.delete(cacheKey);
                        }
                        cursor.continue();
                    };
                    cursorRequest.onerror = function () {
                        store.delete(metaKey);
                    };
                }
            } catch (error) {
                // Ignore IndexedDB deletion failures.
            }
        }).catch(function () {
            // Ignore IndexedDB deletion failures.
        });
    }

    function applyPersistedQuestionCache(snapshot, options) {
        options = options || {};

        var normalizedSnapshot = normalizeOrUseQuestionCacheSnapshot(snapshot, options.attemptId || state.attemptId);
        if (!normalizedSnapshot) {
            return false;
        }

        var expectedExamId = Number(options.examId) || 0;
        if (expectedExamId > 0 && normalizedSnapshot.examId > 0 && normalizedSnapshot.examId !== expectedExamId) {
            return false;
        }

        if (options.expectedQuestionRevision && !questionRevisionEquals(
            normalizedSnapshot.questionRevision,
            options.expectedQuestionRevision,
            expectedExamId || normalizedSnapshot.examId || 0
        )) {
            return false;
        }

        state.questionOrderIds = normalizedSnapshot.questionOrderIds;
        state.totalQuestions = normalizedSnapshot.totalQuestions;
        state.questionManifestById = buildQuestionManifestById(normalizedSnapshot.questionManifest);
        state.questionManifest = (state.questionOrderIds.length ? state.questionOrderIds : Object.keys(state.questionManifestById)).reduce(function (accumulator, item) {
            var questionId = Number(item) || 0;
            var manifestItem = getQuestionManifestById(questionId);
            if (manifestItem) {
                accumulator.push(manifestItem);
            }
            return accumulator;
        }, []);
        state.questionPayloadById = normalizedSnapshot.questionPayloadById;
        state.answeredQuestionLookup = normalizedSnapshot.answeredQuestionLookup;
        state.changedQuestionLookup = normalizedSnapshot.changedQuestionLookup;
        state.answers = normalizedSnapshot.answers;
        state.existingAnswerRawByQuestionId = normalizedSnapshot.existingAnswerRawByQuestionId;
        state.loadedQuestionWindowOffsets = normalizedSnapshot.loadedQuestionWindowOffsets;
        if (normalizedSnapshot.questionRevision) {
            setQuestionRevision(normalizedSnapshot.questionRevision, expectedExamId || normalizedSnapshot.examId || 0);
        }

        var targetWindowSize = Math.max(1, Number(options.windowSize) || normalizedSnapshot.windowLimit || QUESTION_WINDOW_SIZE);
        var preferredIndex = Number(options.preferredIndex);
        if (!Number.isFinite(preferredIndex) || preferredIndex < 0) {
            preferredIndex = 0;
        }

        if (
            !setQuestionWindowFromLoadedPayloads(
                questionWindowOffsetForIndex(preferredIndex, targetWindowSize),
                targetWindowSize
            )
        ) {
            setQuestionWindowFromLoadedPayloads(normalizedSnapshot.windowOffset, normalizedSnapshot.windowLimit || targetWindowSize);
        }

        return true;
    }

    function resetQuestionDataState(options) {
        options = options || {};

        state.questions = [];
        state.questionOrderIds = [];
        state.questionManifest = [];
        state.questionManifestById = {};
        state.questionPayloadById = {};
        state.archivedReviewItems = [];
        state.existingAnswerRawByQuestionId = {};
        state.answeredQuestionLookup = {};
        state.changedQuestionLookup = {};
        state.loadedQuestionWindowOffsets = {};
        state.windowOffset = 0;
        state.windowLimit = 0;
        state.totalQuestions = 0;
        state.answers = {};

        if (!options.preserveDoubtful) {
            state.doubtful = {};
        }
        if (!options.preserveCurrentIndex) {
            state.currentIndex = 0;
        }
        if (!options.preserveNavFilter) {
            state.navQuestionFilter = NAV_QUESTION_FILTER_ALL;
        }
        if (!options.preserveQuestionRevision) {
            state.questionRevision = null;
        }
    }

    function getQuestionCount() {
        var orderCount = Array.isArray(state.questionOrderIds) ? state.questionOrderIds.length : 0;
        if (orderCount > 0) {
            return orderCount;
        }

        var totalQuestions = Number(state.totalQuestions) || 0;
        if (totalQuestions > 0) {
            return totalQuestions;
        }

        return Array.isArray(state.questions) ? state.questions.length : 0;
    }

    function getQuestionIdAtIndex(index) {
        var safeIndex = Math.floor(Number(index));
        if (!Number.isFinite(safeIndex) || safeIndex < 0) {
            return 0;
        }

        if (Array.isArray(state.questionOrderIds) && safeIndex < state.questionOrderIds.length) {
            return Number(state.questionOrderIds[safeIndex]) || 0;
        }

        if (Array.isArray(state.questions) && safeIndex < state.questions.length) {
            return Number(state.questions[safeIndex] && state.questions[safeIndex].id) || 0;
        }

        return 0;
    }

    function clampQuestionIndex(index) {
        var totalQuestions = getQuestionCount();
        if (totalQuestions <= 0) {
            return 0;
        }

        var safeIndex = Math.floor(Number(index));
        if (!Number.isFinite(safeIndex)) {
            safeIndex = 0;
        }

        return Math.min(totalQuestions - 1, Math.max(0, safeIndex));
    }

    function getQuestionManifestById(questionId) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || !state.questionManifestById || typeof state.questionManifestById !== 'object') {
            return null;
        }

        return Object.prototype.hasOwnProperty.call(state.questionManifestById, safeQuestionId)
            ? state.questionManifestById[safeQuestionId]
            : null;
    }

    function getQuestionPayloadById(questionId) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || !state.questionPayloadById || typeof state.questionPayloadById !== 'object') {
            return null;
        }

        return Object.prototype.hasOwnProperty.call(state.questionPayloadById, safeQuestionId)
            ? state.questionPayloadById[safeQuestionId]
            : null;
    }

    function getQuestionById(questionId) {
        return getQuestionPayloadById(questionId) || getQuestionManifestById(questionId);
    }

    function getQuestionDisplayNumber(question, fallbackIndex) {
        var questionNumber = Number(question && question.question_number !== undefined ? question.question_number : 0) || 0;
        var questionId = Number(question && question.id !== undefined ? question.id : 0) || 0;
        if (questionNumber <= 0 && questionId > 0) {
            var manifestQuestion = getQuestionManifestById(questionId);
            questionNumber = Number(manifestQuestion && manifestQuestion.question_number !== undefined ? manifestQuestion.question_number : 0) || 0;
        }
        if (questionNumber > 0) {
            return questionNumber;
        }

        return Math.max(1, Math.floor(Number(fallbackIndex) || 0) + 1);
    }

    function getQuestionDisplayNumberById(questionId, fallbackIndex) {
        return getQuestionDisplayNumber(getQuestionById(questionId), fallbackIndex);
    }

    function getQuestionAtIndex(index) {
        return getQuestionById(getQuestionIdAtIndex(index));
    }

    function isQuestionPayloadLoaded(questionId) {
        return !!getQuestionPayloadById(questionId);
    }

    function isIndexInCurrentWindow(index) {
        var safeIndex = Math.floor(Number(index));
        var windowLimit = Number(state.windowLimit) || 0;
        if (!Number.isFinite(safeIndex) || safeIndex < 0 || windowLimit <= 0) {
            return false;
        }

        var windowOffset = Number(state.windowOffset) || 0;
        return safeIndex >= windowOffset && safeIndex < (windowOffset + windowLimit);
    }

    function isQuestionWindowLoaded(offset) {
        var safeOffset = Math.max(0, Number(offset) || 0);
        return !!(state.loadedQuestionWindowOffsets && state.loadedQuestionWindowOffsets[safeOffset]);
    }

    function markQuestionWindowLoaded(offset) {
        var safeOffset = Math.max(0, Number(offset) || 0);
        if (!state.loadedQuestionWindowOffsets || typeof state.loadedQuestionWindowOffsets !== 'object') {
            state.loadedQuestionWindowOffsets = {};
        }
        state.loadedQuestionWindowOffsets[safeOffset] = true;
    }

    function questionWindowOffsetForIndex(index, windowSize) {
        var safeWindowSize = Math.max(1, Number(windowSize) || QUESTION_WINDOW_SIZE);
        var safeIndex = Math.max(0, Math.floor(Number(index) || 0));
        return Math.floor(safeIndex / safeWindowSize) * safeWindowSize;
    }

    function setActiveQuestionWindowForIndex(index, windowSize) {
        var safeWindowSize = Math.max(1, Number(windowSize) || QUESTION_WINDOW_SIZE);
        var safeIndex = clampQuestionIndex(index);
        state.windowOffset = questionWindowOffsetForIndex(safeIndex, safeWindowSize);
        state.windowLimit = safeWindowSize;
    }

    function clearQuestionPrefetchIdleTimer() {
        if (questionPrefetchIdleTimer) {
            window.clearTimeout(questionPrefetchIdleTimer);
            questionPrefetchIdleTimer = 0;
        }
    }

    function clearQuestionPrefetchRuntimeState() {
        clearQuestionPrefetchIdleTimer();
        questionPrefetchInFlightByOffset = {};
    }

    function getNextQuestionPrefetchOffset() {
        var totalQuestions = getQuestionCount();
        if (totalQuestions <= 0) {
            return -1;
        }

        var startIndex = Math.max(0, Math.min(totalQuestions - 1, (Number(state.currentIndex) || 0) + 1));
        for (var scanned = 0; scanned < totalQuestions; scanned++) {
            var index = (startIndex + scanned) % totalQuestions;
            var questionId = getQuestionIdAtIndex(index);
            if (questionId > 0 && !isQuestionPayloadLoaded(questionId)) {
                return index;
            }
        }

        return -1;
    }

    function hasPendingQuestionPrefetch() {
        return getNextQuestionPrefetchOffset() >= 0;
    }

    function getLoadedQuestionCount() {
        var totalQuestions = getQuestionCount();
        if (totalQuestions <= 0) {
            return 0;
        }

        var loadedCount = 0;
        for (var index = 0; index < totalQuestions; index++) {
            if (isQuestionPayloadLoaded(getQuestionIdAtIndex(index))) {
                loadedCount++;
            }
        }

        return loadedCount;
    }

    function getQuestionPrefetchMeta() {
        var totalQuestions = getQuestionCount();
        var loadedCount = getLoadedQuestionCount();
        var inFlightCount = Object.keys(questionPrefetchInFlightByOffset).reduce(function (count, key) {
            var offset = Number(key);
            if (Number.isFinite(offset) && offset >= 0) {
                return count + 1;
            }
            return count;
        }, 0);
        var pendingCount = Math.max(0, totalQuestions - loadedCount);
        var isLoading = inFlightCount > 0;
        var isComplete = totalQuestions > 0 && pendingCount === 0 && !isLoading;
        var statusText = isComplete ? 'Lengkap' : (isLoading ? 'Memuat' : 'Siaga');
        var summaryText = loadedCount + '/' + totalQuestions;
        var title = isComplete
            ? 'Semua soal sudah dimuat di perangkat ini.'
            : (isLoading
                ? ('Sedang memuat soal tambahan di latar belakang. Tersisa ' + pendingCount + ' soal lagi.')
                : 'Prefetch siap mengambil soal tambahan saat pindah soal atau saat diam 30 detik.');

        return {
            loadedCount: loadedCount,
            totalQuestions: totalQuestions,
            pendingCount: pendingCount,
            isLoading: isLoading,
            isComplete: isComplete,
            summaryText: summaryText,
            statusText: statusText,
            title: title,
            ariaLabel: 'Status prefetch soal: ' + summaryText + ' soal sudah dimuat, ' + statusText + '.'
        };
    }

    function renderQuestionPrefetchIndicator() {
        var meta = getQuestionPrefetchMeta();
        var classes = ['cbt-chip', 'cbt-chip-prefetch'];
        if (meta.isLoading) {
            classes.push('is-loading');
        }
        if (meta.isComplete) {
            classes.push('is-complete');
        }

        return [
            '<div class="' + classes.join(' ') + '" data-prefetch-indicator title="' + escapeHtml(meta.title) + '" aria-label="' + escapeHtml(meta.ariaLabel) + '">',
            '<span class="cbt-chip-prefetch-dot" aria-hidden="true"></span>',
            '<span class="cbt-chip-label">Prefetch</span>',
            '<span class="cbt-chip-value" data-prefetch-count>' + escapeHtml(meta.summaryText) + '</span>',
            '<span class="cbt-chip-prefetch-status" data-prefetch-status>' + escapeHtml(meta.statusText) + '</span>',
            '</div>'
        ].join('');
    }

    function updateQuestionPrefetchIndicator() {
        if (state.stage !== 'exam') {
            return;
        }

        var indicator = root.querySelector('[data-prefetch-indicator]');
        if (!(indicator instanceof HTMLElement)) {
            return;
        }

        var meta = getQuestionPrefetchMeta();
        indicator.classList.toggle('is-loading', meta.isLoading);
        indicator.classList.toggle('is-complete', meta.isComplete);
        indicator.setAttribute('title', meta.title);
        indicator.setAttribute('aria-label', meta.ariaLabel);

        var countEl = indicator.querySelector('[data-prefetch-count]');
        if (countEl) {
            countEl.textContent = meta.summaryText;
        }

        var statusEl = indicator.querySelector('[data-prefetch-status]');
        if (statusEl) {
            statusEl.textContent = meta.statusText;
        }
    }

    function resetQuestionPrefetchIdleTimer() {
        clearQuestionPrefetchIdleTimer();

        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing || isQuestionRevisionRefreshActive()) {
            return;
        }

        if (!hasPendingQuestionPrefetch()) {
            return;
        }

        questionPrefetchIdleTimer = window.setTimeout(function () {
            questionPrefetchIdleTimer = 0;
            prefetchNextQuestionBatch().finally(function () {
                resetQuestionPrefetchIdleTimer();
            });
        }, QUESTION_PREFETCH_IDLE_DELAY_MS);
    }

    function noteQuestionPrefetchActivity() {
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing || isQuestionRevisionRefreshActive()) {
            return;
        }

        resetQuestionPrefetchIdleTimer();
    }

    function prefetchNextQuestionBatch() {
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing || isQuestionRevisionRefreshActive()) {
            return Promise.resolve(null);
        }

        var offset = getNextQuestionPrefetchOffset();
        if (offset < 0) {
            return Promise.resolve(null);
        }

        if (questionPrefetchInFlightByOffset[offset]) {
            return questionPrefetchInFlightByOffset[offset];
        }

        var totalQuestions = getQuestionCount();
        var limit = Math.min(QUESTION_PREFETCH_BATCH_SIZE, Math.max(0, totalQuestions - offset));
        if (limit <= 0) {
            return Promise.resolve(null);
        }

        var request = loadQuestionWindow(offset, {
            examId: state.selectedExamId,
            attemptId: state.attemptId,
            includeExisting: 1,
            limit: limit,
            preserveActiveWindow: true
        }).catch(function () {
            return null;
        }).finally(function () {
            delete questionPrefetchInFlightByOffset[offset];
            updateQuestionPrefetchIndicator();
        });

        questionPrefetchInFlightByOffset[offset] = request;
        updateQuestionPrefetchIndicator();
        return request;
    }

    function normalizeExistingAnswerForQuestion(question) {
        if (!question || !Object.prototype.hasOwnProperty.call(question, 'existing_answer')) {
            return {
                hasValue: false,
                value: null
            };
        }

        var existing = question.existing_answer;
        if (existing === null || existing === undefined || existing === '') {
            return {
                hasValue: false,
                value: null
            };
        }
        return normalizeAnswerValueForQuestion(question, existing);
    }

    function rememberExistingAnswerRaw(questionId, rawAnswer) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || rawAnswer === undefined) {
            return;
        }

        if (!state.existingAnswerRawByQuestionId || typeof state.existingAnswerRawByQuestionId !== 'object') {
            state.existingAnswerRawByQuestionId = {};
        }

        state.existingAnswerRawByQuestionId[safeQuestionId] = rawAnswer;
    }

    function getRememberedExistingAnswerRaw(questionId) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || !state.existingAnswerRawByQuestionId || typeof state.existingAnswerRawByQuestionId !== 'object') {
            return undefined;
        }

        return Object.prototype.hasOwnProperty.call(state.existingAnswerRawByQuestionId, safeQuestionId)
            ? state.existingAnswerRawByQuestionId[safeQuestionId]
            : undefined;
    }

    function hasUsableLocalAnswerForQuestion(questionId, question) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || !Object.prototype.hasOwnProperty.call(state.answers, safeQuestionId)) {
            return false;
        }

        var referenceQuestion = question || getQuestionById(safeQuestionId);
        if (!referenceQuestion) {
            return true;
        }

        return normalizeAnswerValueForQuestion(referenceQuestion, state.answers[safeQuestionId], {
            preserveText: true
        }).hasValue;
    }

    function resolveStoredAnswerValueForQuestion(question) {
        var questionId = Number(question && question.id) || 0;
        if (questionId <= 0 || !question) {
            return undefined;
        }

        if (hasUsableLocalAnswerForQuestion(questionId, question)) {
            return state.answers[questionId];
        }

        var rawExistingAnswer = Object.prototype.hasOwnProperty.call(question, 'existing_answer')
            ? question.existing_answer
            : getRememberedExistingAnswerRaw(questionId);
        if (rawExistingAnswer === undefined) {
            return undefined;
        }

        var normalized = normalizeAnswerValueForQuestion(question, rawExistingAnswer, {
            preserveText: true
        });
        if (!normalized.hasValue) {
            return undefined;
        }

        state.answers[questionId] = normalized.value;
        state.answeredQuestionLookup[questionId] = true;
        rememberExistingAnswerRaw(questionId, rawExistingAnswer);
        return normalized.value;
    }

    function mergeExistingAnswersFromQuestionItems(questions, options) {
        options = options || {};
        var overwriteExisting = !!options.overwriteExisting;

        if (!Array.isArray(questions)) {
            return;
        }

        questions.forEach(function (question) {
            var questionId = Number(question && question.id) || 0;
            if (questionId <= 0) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(question, 'existing_answer')) {
                rememberExistingAnswerRaw(questionId, question.existing_answer);
            }

            var normalized = normalizeExistingAnswerForQuestion(question);
            if (!normalized.hasValue) {
                return;
            }

            if (!overwriteExisting && hasUsableLocalAnswerForQuestion(questionId, question)) {
                return;
            }

            state.answers[questionId] = normalized.value;
            state.answeredQuestionLookup[questionId] = true;
        });
    }

    function primeSubmittedPayloadCacheFromQuestionItems(questions) {
        if (!Array.isArray(questions)) {
            return;
        }

        questions.forEach(function (question) {
            var questionId = Number(question && question.id) || 0;
            if (questionId <= 0) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(pendingAnswerBatchByQuestion, questionId)) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(lastSubmittedPayloadByQuestion, questionId)) {
                return;
            }

            var normalized = normalizeExistingAnswerForQuestion(question);
            if (!normalized.hasValue) {
                return;
            }

            var questionForPayload = getQuestionById(questionId) || question;
            if (!questionForPayload) {
                return;
            }

            var payload = questionAnswerPayload(questionForPayload);
            var signature = payloadSignature(payload);
            if (signature !== '') {
                lastSubmittedPayloadByQuestion[questionId] = signature;
            }
        });
    }

    function storeQuestionPayloads(questions) {
        if (!Array.isArray(questions)) {
            return;
        }

        questions.forEach(function (question) {
            var questionId = Number(question && question.id) || 0;
            if (questionId <= 0) {
                return;
            }
            state.questionPayloadById[questionId] = question;
        });
    }

    function mergeExistingAnswersMap(existingAnswersMap, options) {
        options = options || {};
        var overwriteExisting = !!options.overwriteExisting;

        if (!existingAnswersMap || typeof existingAnswersMap !== 'object') {
            return;
        }

        Object.keys(existingAnswersMap).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId <= 0) {
                return;
            }

            rememberExistingAnswerRaw(questionId, existingAnswersMap[key]);

            var question = getQuestionById(questionId);
            if (!overwriteExisting && hasUsableLocalAnswerForQuestion(questionId, question)) {
                return;
            }

            var normalized = normalizeAnswerValueForQuestion(question, existingAnswersMap[key]);
            if (!normalized.hasValue) {
                return;
            }

            state.answers[questionId] = normalized.value;
            state.answeredQuestionLookup[questionId] = true;
        });
    }

    function restoreLocalAnswerFromQuestion(question, options) {
        options = options || {};

        var questionId = Number(question && question.id) || 0;
        if (questionId <= 0) {
            return false;
        }

        if (!options.overwriteExisting && hasUsableLocalAnswerForQuestion(questionId, question)) {
            return true;
        }

        var normalized = normalizeExistingAnswerForQuestion(question);
        if (!normalized.hasValue) {
            return false;
        }

        state.answers[questionId] = normalized.value;
        state.answeredQuestionLookup[questionId] = true;
        return true;
    }

    function applyQuestionsResponse(questionPayload, options) {
        options = options || {};
        var responseItems = questionPayload && Array.isArray(questionPayload.items) ? questionPayload.items : [];
        var responseOrderIds = questionPayload && Array.isArray(questionPayload.question_order_ids)
            ? questionPayload.question_order_ids
            : responseItems.map(function (question) { return Number(question && question.id) || 0; });
        var normalizedOrderIds = normalizeQuestionIdList(responseOrderIds);
        var responseManifest = questionPayload && Array.isArray(questionPayload.question_manifest)
            ? questionPayload.question_manifest
            : buildQuestionManifestFromQuestions(responseItems);
        var responseAnsweredQuestionIds = questionPayload && Array.isArray(questionPayload.answered_question_ids)
            ? normalizeQuestionIdList(questionPayload.answered_question_ids)
            : [];
        var responseExistingAnswersMap = questionPayload && questionPayload.existing_answers_map && typeof questionPayload.existing_answers_map === 'object'
            ? questionPayload.existing_answers_map
            : null;
        var responseArchivedReviewItems = questionPayload && Array.isArray(questionPayload.archived_review_items)
            ? questionPayload.archived_review_items
            : null;
        var responseRevision = normalizeQuestionRevision(
            questionPayload && questionPayload.question_revision,
            Number(state.selectedExamId) || 0
        );

        if (normalizedOrderIds.length) {
            state.questionOrderIds = normalizedOrderIds;
        }
        if (responseRevision) {
            setQuestionRevision(responseRevision, Number(state.selectedExamId) || 0);
        }
        if (responseArchivedReviewItems !== null) {
            state.archivedReviewItems = responseArchivedReviewItems;
        }

        if (responseAnsweredQuestionIds.length) {
            responseAnsweredQuestionIds.forEach(function (questionId) {
                state.answeredQuestionLookup[questionId] = true;
            });
        }

        var responseOffset = Math.max(0, Number(questionPayload && questionPayload.offset) || 0);
        var responseLimit = Math.max(0, Number(questionPayload && questionPayload.limit) || 0);

        state.totalQuestions = Math.max(
            normalizedOrderIds.length,
            Number(questionPayload && questionPayload.total_questions) || 0,
            Array.isArray(state.questionOrderIds) ? state.questionOrderIds.length : 0
        );
        if (!options.preserveActiveWindow) {
            state.windowOffset = responseOffset;
            state.windowLimit = responseLimit;
            state.questions = responseItems;
        }
        markQuestionWindowLoaded(responseOffset);

        var manifestById = buildQuestionManifestById(responseManifest);
        Object.keys(manifestById).forEach(function (key) {
            state.questionManifestById[key] = manifestById[key];
        });

        state.questionManifest = (state.questionOrderIds.length ? state.questionOrderIds : Object.keys(state.questionManifestById)).reduce(function (accumulator, item) {
            var questionId = Number(item) || 0;
            var manifestItem = getQuestionManifestById(questionId);
            if (manifestItem) {
                accumulator.push(manifestItem);
            }
            return accumulator;
        }, []);

        storeQuestionPayloads(responseItems);
        mergeExistingAnswersMap(responseExistingAnswersMap, {
            overwriteExisting: !!options.overwriteExisting
        });
        mergeExistingAnswersFromQuestionItems(responseItems, {
            overwriteExisting: !!options.overwriteExisting
        });
        primeSubmittedPayloadCacheFromQuestionItems(responseItems);
        var restoredDeferredQuestionIds = applyPendingRevisionSafeAnswersForLoadedQuestions(responseItems);
        if (restoredDeferredQuestionIds.length && !isQuestionRevisionRefreshActive()) {
            if (queueQuestionAnswersByIds(restoredDeferredQuestionIds) > 0) {
                scheduleAnswerBatchFlush(300);
            }
        }
        scheduleQuestionCachePersist(0);
        updateQuestionPrefetchIndicator();
    }

    function validAttemptQuestionIds() {
        var sourceIds = Array.isArray(state.questionOrderIds) && state.questionOrderIds.length
            ? state.questionOrderIds
            : (Array.isArray(state.questionManifest) && state.questionManifest.length
                ? state.questionManifest.map(function (question) { return Number(question && question.id) || 0; })
                : state.questions.map(function (question) { return Number(question && question.id) || 0; }));

        return sourceIds.reduce(function (accumulator, item) {
            var questionId = Number(item) || 0;
            if (questionId > 0) {
                accumulator[questionId] = true;
            }
            return accumulator;
        }, {});
    }

    function pruneQuestionScopedState(validQuestionIdLookup) {
        var validLookup = validQuestionIdLookup && typeof validQuestionIdLookup === 'object'
            ? validQuestionIdLookup
            : validAttemptQuestionIds();

        function pruneLookup(source) {
            Object.keys(source || {}).forEach(function (key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && !validLookup[questionId]) {
                    delete source[key];
                }
            });
        }

        pruneLookup(state.answers);
        pruneLookup(state.answeredQuestionLookup);
        pruneLookup(state.changedQuestionLookup);
        pruneLookup(state.doubtful);
        pruneLookup(lastSubmittedPayloadByQuestion);
        pruneLookup(pendingAnswerBatchByQuestion);
        pruneLookup(pendingRevisionSafeAnswerRestoreByQuestion);

        pendingAnswerBatchOrder = pendingAnswerBatchOrder.filter(function (item) {
            var questionId = Number(item) || 0;
            return questionId > 0 && validLookup[questionId];
        });
    }

    function normalizeAttemptUiState(snapshot, attemptId) {
        var safeAttemptId = Number(attemptId || (snapshot && snapshot.attempt_id)) || 0;
        var questionCount = getQuestionCount();
        var questionIdSet = validAttemptQuestionIds();
        var rawDoubtful = [];

        if (snapshot && Array.isArray(snapshot.doubtful_question_ids)) {
            rawDoubtful = snapshot.doubtful_question_ids;
        } else if (snapshot && Array.isArray(snapshot.question_ids)) {
            rawDoubtful = snapshot.question_ids;
        }

        var doubtfulIds = [];
        var seenQuestionIds = {};
        rawDoubtful.forEach(function (item) {
            var questionId = Number(item) || 0;
            if (questionId <= 0 || seenQuestionIds[questionId]) {
                return;
            }
            if (questionCount > 0 && !questionIdSet[questionId]) {
                return;
            }
            seenQuestionIds[questionId] = true;
            doubtfulIds.push(questionId);
        });

        var currentIndex = Math.floor(Number(snapshot && snapshot.current_index !== undefined ? snapshot.current_index : 0));
        if (!Number.isFinite(currentIndex) || currentIndex < 0) {
            currentIndex = 0;
        }
        if (questionCount > 0 && currentIndex >= questionCount) {
            currentIndex = questionCount - 1;
        }

        return {
            attempt_id: safeAttemptId,
            current_index: currentIndex,
            doubtful_question_ids: doubtfulIds
        };
    }

    function questionCacheHasPayloadForIndex(snapshot, index) {
        var normalizedSnapshot = snapshot && snapshot.questionPayloadById
            ? snapshot
            : normalizeQuestionCacheSnapshot(snapshot, snapshot && snapshot.attempt_id);
        if (!normalizedSnapshot) {
            return false;
        }

        var safeIndex = Math.max(0, Math.floor(Number(index) || 0));
        if (!Array.isArray(normalizedSnapshot.questionOrderIds) || safeIndex >= normalizedSnapshot.questionOrderIds.length) {
            return false;
        }

        var questionId = Number(normalizedSnapshot.questionOrderIds[safeIndex]) || 0;
        return questionId > 0 && !!normalizedSnapshot.questionPayloadById[questionId];
    }

    function mergeAttemptUiStateDoubtfulIds(primarySnapshot, secondarySnapshot) {
        var mergedLookup = {};
        var mergedIds = [];

        [primarySnapshot, secondarySnapshot].forEach(function (snapshot) {
            if (!snapshot || !Array.isArray(snapshot.doubtful_question_ids)) {
                return;
            }

            snapshot.doubtful_question_ids.forEach(function (questionId) {
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

    function choosePreferredAttemptUiState(remoteSnapshot, localSnapshot, questionCacheSnapshot, attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var normalizedLocal = localSnapshot ? normalizeAttemptUiState(localSnapshot, safeAttemptId) : null;
        var normalizedRemote = remoteSnapshot ? normalizeAttemptUiState(remoteSnapshot, safeAttemptId) : null;
        var normalizedQuestionCache = normalizeOrUseQuestionCacheSnapshot(questionCacheSnapshot, safeAttemptId);
        var selectedSnapshot = normalizedLocal || normalizedRemote || {
            attempt_id: safeAttemptId,
            current_index: 0,
            doubtful_question_ids: []
        };

        if (normalizedQuestionCache) {
            if (normalizedLocal && questionCacheHasPayloadForIndex(normalizedQuestionCache, normalizedLocal.current_index)) {
                selectedSnapshot = normalizedLocal;
            } else if (normalizedRemote && questionCacheHasPayloadForIndex(normalizedQuestionCache, normalizedRemote.current_index)) {
                selectedSnapshot = normalizedRemote;
            }
        }

        return normalizeAttemptUiState({
            attempt_id: safeAttemptId,
            current_index: selectedSnapshot.current_index,
            doubtful_question_ids: mergeAttemptUiStateDoubtfulIds(normalizedLocal, normalizedRemote)
        }, safeAttemptId);
    }

    function buildAttemptUiStateSnapshot(attemptId) {
        var safeAttemptId = Number(attemptId || state.attemptId) || 0;
        if (safeAttemptId <= 0) {
            return null;
        }

        return normalizeAttemptUiState({
            attempt_id: safeAttemptId,
            current_index: state.currentIndex,
            doubtful_question_ids: Object.keys(state.doubtful).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.doubtful[key]) {
                    accumulator.push(questionId);
                }
                return accumulator;
            }, [])
        }, safeAttemptId);
    }

    function readPersistedAttemptUiState(attemptId) {
        var storage = getSessionStorage();
        if (!storage) {
            return null;
        }

        var safeAttemptId = Number(attemptId) || 0;
        var storageKey = buildAttemptUiSessionStorageKey(safeAttemptId);
        if (storageKey === '') {
            return null;
        }

        try {
            var raw = storage.getItem(storageKey);
            if (raw) {
                return normalizeAttemptUiState(JSON.parse(raw), safeAttemptId);
            }
        } catch (error) {
            // Ignore storage failures (quota or disabled storage).
        }

        var legacyDoubtful = readPersistedDoubtfulState(safeAttemptId);
        var legacyQuestionIds = Object.keys(legacyDoubtful).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && legacyDoubtful[key]) {
                accumulator.push(questionId);
            }
            return accumulator;
        }, []);
        if (!legacyQuestionIds.length) {
            return null;
        }

        return normalizeAttemptUiState({
            attempt_id: safeAttemptId,
            current_index: 0,
            doubtful_question_ids: legacyQuestionIds
        }, safeAttemptId);
    }

    function persistAttemptUiStateLocally(snapshot) {
        var storage = getSessionStorage();
        if (!storage) {
            return;
        }

        var normalizedSnapshot = normalizeAttemptUiState(snapshot, snapshot && snapshot.attempt_id);
        var storageKey = buildAttemptUiSessionStorageKey(normalizedSnapshot.attempt_id);
        if (storageKey === '') {
            return;
        }

        try {
            storage.setItem(storageKey, JSON.stringify(normalizedSnapshot));
        } catch (error) {
            // Ignore storage failures (quota or disabled storage).
        }
    }

    function persistCurrentAttemptUiStateLocally() {
        var snapshot = buildAttemptUiStateSnapshot();
        if (!snapshot) {
            return;
        }

        persistAttemptUiStateLocally(snapshot);
    }

    function clearPersistedDoubtfulState(attemptId) {
        var storage = getSessionStorage();
        if (!storage) {
            return;
        }

        var storageKey = buildDoubtfulSessionStorageKey(attemptId);
        if (storageKey === '') {
            return;
        }

        try {
            storage.removeItem(storageKey);
        } catch (error) {
            // Ignore storage failures (private mode / blocked storage).
        }
    }

    function clearPersistedAttemptUiState(attemptId) {
        var storage = getSessionStorage();
        if (!storage) {
            return;
        }

        var storageKey = buildAttemptUiSessionStorageKey(attemptId);
        if (storageKey === '') {
            clearPersistedDoubtfulState(attemptId);
            return;
        }

        try {
            storage.removeItem(storageKey);
        } catch (error) {
            // Ignore storage failures (private mode / blocked storage).
        }

        clearPersistedDoubtfulState(attemptId);
    }

    function applyAttemptUiState(snapshot, attemptId) {
        var normalizedSnapshot = normalizeAttemptUiState(snapshot, attemptId || state.attemptId);
        state.currentIndex = normalizedSnapshot.current_index;
        state.doubtful = normalizedSnapshot.doubtful_question_ids.reduce(function (accumulator, questionId) {
            accumulator[questionId] = true;
            return accumulator;
        }, {});
        persistAttemptUiStateLocally(normalizedSnapshot);
    }

    function apiErrorMessage(payload, fallback) {
        if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message;
        }
        return fallback;
    }

    function buildUrl(path, query) {
        var baseInput = String(config.restBasePath || config.restBase || '/wp-json/cbt/v1/');
        var base;
        try {
            base = new URL(baseInput, window.location.origin + '/').toString();
        } catch (error) {
            base = String(config.restBase || '/wp-json/cbt/v1/');
        }
        var normalizedPath = String(path || '').replace(/^\/+/, '');
        var url = new URL(normalizedPath, base);
        if (query && typeof query === 'object') {
            Object.keys(query).forEach(function (key) {
                var value = query[key];
                if (value === null || value === undefined || value === '') {
                    return;
                }
                url.searchParams.set(key, String(value));
            });
        }
        return url.toString();
    }

    function stopSessionHeartbeat() {
        if (sessionHeartbeatTimer) {
            window.clearInterval(sessionHeartbeatTimer);
            sessionHeartbeatTimer = 0;
        }
        sessionHeartbeatInFlight = null;
    }

    function applyAttemptTimerPayload(timerPayload) {
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
    }

    function runSessionHeartbeat() {
        if (!state.token || state.stage === 'login') {
            return Promise.resolve(null);
        }

        if (sessionHeartbeatInFlight) {
            return sessionHeartbeatInFlight;
        }

        var heartbeatAttemptId = state.stage === 'exam' && state.attemptId > 0
            ? Number(state.attemptId) || 0
            : 0;
        var heartbeatExamId = Number(state.selectedExamId) || 0;

        sessionHeartbeatInFlight = api('session', {
            query: {
                attempt_id: heartbeatAttemptId > 0 ? heartbeatAttemptId : null
            }
        }).then(function (sessionPayload) {
            if (
                heartbeatAttemptId > 0
                && state.stage === 'exam'
                && Number(state.attemptId) === heartbeatAttemptId
            ) {
                var sessionRevision = normalizeQuestionRevision(
                    sessionPayload && sessionPayload.question_revision,
                    heartbeatExamId
                );
                var sessionQuestionCount = Math.max(0, Number(sessionPayload && sessionPayload.question_count) || 0);
                var localQuestionCount = Math.max(0, getQuestionCount());
                var shouldRefreshForCount = (
                    sessionQuestionCount > 0
                    && localQuestionCount > 0
                    && sessionQuestionCount !== localQuestionCount
                );

                if (
                    shouldRefreshForCount ||
                    (sessionRevision && state.questionRevision && !questionRevisionEquals(sessionRevision, state.questionRevision, heartbeatExamId))
                ) {
                    return refreshAttemptQuestionRevision(sessionRevision, {
                        attemptId: heartbeatAttemptId,
                        examId: heartbeatExamId,
                        preferredIndex: state.currentIndex,
                        source: shouldRefreshForCount ? 'heartbeat-count' : 'heartbeat'
                    }).then(function () {
                        return sessionPayload;
                    });
                }

                if (sessionRevision && !state.questionRevision) {
                    setQuestionRevision(sessionRevision, heartbeatExamId);
                }

                applyAttemptTimerPayload(sessionPayload && sessionPayload.attempt_timer);
            }

            return sessionPayload;
        })
            .catch(function () {
                return null;
            })
            .finally(function () {
                sessionHeartbeatInFlight = null;
            });

        return sessionHeartbeatInFlight;
    }

    function startSessionHeartbeat() {
        if (!state.token || state.stage === 'login' || sessionHeartbeatTimer) {
            return;
        }

        sessionHeartbeatTimer = window.setInterval(function () {
            runSessionHeartbeat();
        }, SESSION_HEARTBEAT_INTERVAL_MS);
    }

    function sendLogoutRequestSilently(token) {
        var authToken = String(token || '');
        if (authToken === '') {
            return;
        }

        fetch(buildUrl('logout'), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + authToken
            },
            keepalive: true
        }).catch(function () {
            // Best-effort logout request.
        });
    }

    function sendSecurityEventSilently(eventType, context, options) {
        options = options || {};

        var safeEventType = String(eventType || '').trim();
        var attemptId = Number(options.attemptId !== undefined ? options.attemptId : state.attemptId) || 0;
        var stage = String(options.stage !== undefined ? options.stage : state.stage || '');
        var authToken = options.token !== undefined ? String(options.token || '') : String(state.token || '');
        var keepalive = !!options.keepalive;
        var debounceMs = Math.max(0, Number(options.debounceMs) || 0);
        var requireFullscreen = !!options.requireFullscreen;

        if (safeEventType === '' || attemptId <= 0 || stage !== 'exam' || authToken === '' || !isSecurityLoggingEnabled()) {
            return false;
        }

        if (requireFullscreen && !isExamFullscreenRequired()) {
            return false;
        }

        if (shouldThrottleSecurityEvent(safeEventType, attemptId, debounceMs)) {
            return false;
        }

        try {
            fetch(buildUrl('security_event'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + authToken
                },
                body: JSON.stringify({
                    attempt_id: attemptId,
                    event_type: safeEventType,
                    context: context && typeof context === 'object' ? context : {}
                }),
                keepalive: keepalive
            }).catch(function () {
                // Best-effort security event logging.
            });
            return true;
        } catch (error) {
            return false;
        }
    }

    function cancelScheduledTabHiddenSecurityLog() {
        if (tabHiddenLogTimer) {
            window.clearTimeout(tabHiddenLogTimer);
        }
        tabHiddenLogTimer = 0;
        tabHiddenLogScheduledAttemptId = 0;
    }

    function cancelScheduledWindowBlurSecurityLog() {
        if (windowBlurLogTimer) {
            window.clearTimeout(windowBlurLogTimer);
        }
        windowBlurLogTimer = 0;
        windowBlurLogScheduledAttemptId = 0;
    }

    function isWindowBlurLoggingActiveForAttempt() {
        return isSecurityLoggingActiveForAttempt() && !state.isFinishing;
    }

    function scheduleTabHiddenSecurityLog() {
        if (!isSecurityLoggingActiveForAttempt()) {
            return;
        }

        var attemptId = Number(state.attemptId) || 0;
        if (attemptId <= 0) {
            return;
        }

        cancelScheduledTabHiddenSecurityLog();
        tabHiddenLogScheduledAttemptId = attemptId;
        tabHiddenLogTimer = window.setTimeout(function () {
            tabHiddenLogTimer = 0;
            tabHiddenLogScheduledAttemptId = 0;

            if (!isSecurityLoggingActiveForAttempt()) {
                return;
            }
            if (pageLeaveLoggedAttemptId === attemptId) {
                return;
            }
            if (document.visibilityState !== 'hidden') {
                return;
            }

            sendSecurityEventSilently('tab_hidden', {
                source: 'visibilitychange',
                visibility_state: String(document.visibilityState || '')
            }, {
                attemptId: attemptId,
                keepalive: true,
                debounceMs: 1500
            });
        }, 500);
    }

    function scheduleWindowBlurSecurityLog(source) {
        if (!isWindowBlurLoggingActiveForAttempt()) {
            return;
        }

        var attemptId = Number(state.attemptId) || 0;
        if (attemptId <= 0) {
            return;
        }

        cancelScheduledWindowBlurSecurityLog();
        windowBlurLogScheduledAttemptId = attemptId;
        windowBlurLogTimer = window.setTimeout(function () {
            var hasFocus = typeof document.hasFocus === 'function' ? document.hasFocus() : true;
            windowBlurLogTimer = 0;
            windowBlurLogScheduledAttemptId = 0;

            if (!isWindowBlurLoggingActiveForAttempt()) {
                return;
            }
            if (pageLeaveLoggedAttemptId === attemptId) {
                return;
            }
            if (document.visibilityState === 'hidden') {
                return;
            }
            if (hasFocus) {
                return;
            }

            sendSecurityEventSilently('window_blur', {
                source: String(source || 'blur'),
                visibility_state: String(document.visibilityState || ''),
                has_focus: hasFocus ? 1 : 0
            }, {
                attemptId: attemptId,
                keepalive: true,
                debounceMs: 2500
            });
        }, WINDOW_BLUR_LOG_DELAY_MS);
    }

    function logPageLeaveSecurityEvent(source) {
        if (!isSecurityLoggingActiveForAttempt()) {
            return;
        }

        var attemptId = Number(state.attemptId) || 0;
        if (attemptId <= 0 || pageLeaveLoggedAttemptId === attemptId) {
            return;
        }

        pageLeaveLoggedAttemptId = attemptId;
        cancelScheduledTabHiddenSecurityLog();
        cancelScheduledWindowBlurSecurityLog();
        sendSecurityEventSilently('page_leave', {
            source: String(source || 'pagehide')
        }, {
            attemptId: attemptId,
            keepalive: true
        });
    }

    async function api(path, options) {
        options = options || {};
        var method = options.method || 'GET';
        var useAuth = options.auth !== false;
        var body = options.body || null;
        var query = options.query || null;
        var keepalive = !!options.keepalive;
        var authToken = options.token !== undefined ? String(options.token || '') : String(state.token || '');

        var headers = {
            'Accept': 'application/json'
        };

        if (body !== null) {
            headers['Content-Type'] = 'application/json';
        }

        if (useAuth && authToken) {
            headers.Authorization = 'Bearer ' + authToken;
        }

        var response = await fetch(buildUrl(path, query), {
            method: method,
            headers: headers,
            body: body !== null ? JSON.stringify(body) : null,
            keepalive: keepalive
        });

        var payload = {};
        try {
            payload = await response.json();
        } catch (error) {
            payload = {};
        }

        if (!response.ok) {
            var error = new Error(apiErrorMessage(payload, 'Request gagal.'));
            error.status = Number(response.status) || 0;
            error.code = payload && typeof payload.code === 'string' ? payload.code : '';

            if (response.status === 401 && useAuth) {
                expireAuthSession(error.message);
            }

            throw error;
        }

        return payload;
    }

    async function loadQuestionWindow(offset, options) {
        options = options || {};

        var examId = Number(options.examId !== undefined ? options.examId : state.selectedExamId) || 0;
        var attemptId = Number(options.attemptId !== undefined ? options.attemptId : state.attemptId) || 0;
        var includeExisting = options.includeExisting !== undefined ? options.includeExisting : 1;
        var includeAnswerManifest = options.includeAnswerManifest !== undefined ? options.includeAnswerManifest : 0;
        var windowLimit = Math.max(1, Number(options.limit) || QUESTION_WINDOW_SIZE);
        var requestGeneration = questionDataGeneration;

        if (examId <= 0 || attemptId <= 0) {
            throw new Error('Sesi ujian tidak valid.');
        }

        var questionPayload = await api('questions', {
            query: {
                exam_id: examId,
                attempt_id: attemptId,
                include_existing: Number(includeExisting) ? 1 : 0,
                include_answer_manifest: Number(includeAnswerManifest) ? 1 : 0,
                offset: Math.max(0, Number(offset) || 0),
                limit: windowLimit
            }
        });
        var responseRevision = normalizeQuestionRevision(
            questionPayload && questionPayload.question_revision,
            examId
        );
        if (questionPayload && typeof questionPayload === 'object') {
            questionPayload.question_revision = serializeQuestionRevision(responseRevision, examId);
        }

        if (requestGeneration !== questionDataGeneration) {
            return questionPayload;
        }

        var canApplyQuestionPayload = (
            Number(state.attemptId) === attemptId &&
            Number(state.selectedExamId) === examId &&
            (state.stage === 'exam' || state.stage === 'confirm')
        );

        if (!canApplyQuestionPayload || !!options.skipApply) {
            return questionPayload;
        }

        if (
            !options.allowRevisionTransition
            && responseRevision
            && state.questionRevision
            && !questionRevisionEquals(responseRevision, state.questionRevision, examId)
        ) {
            refreshAttemptQuestionRevision(responseRevision, {
                attemptId: attemptId,
                examId: examId,
                preferredIndex: state.currentIndex,
                source: 'questions'
            });
            return questionPayload;
        }

        applyQuestionsResponse(questionPayload, {
            overwriteExisting: !!options.overwriteExisting,
            preserveActiveWindow: !!options.preserveActiveWindow
        });

        return questionPayload;
    }

    async function ensureQuestionWindowForIndex(index, options) {
        options = options || {};

        var safeIndex = clampQuestionIndex(index);
        var targetOffset = questionWindowOffsetForIndex(safeIndex, options.limit || QUESTION_WINDOW_SIZE);
        var questionId = getQuestionIdAtIndex(safeIndex);
        if (questionId <= 0) {
            return null;
        }

        var cachedQuestion = getQuestionPayloadById(questionId);
        var shouldRestoreAnsweredState = !!(state.answeredQuestionLookup && state.answeredQuestionLookup[questionId]);
        var hasUsableAnsweredState = hasUsableLocalAnswerForQuestion(questionId, cachedQuestion || getQuestionManifestById(questionId));

        if (shouldRestoreAnsweredState && !hasUsableAnsweredState && cachedQuestion) {
            hasUsableAnsweredState = restoreLocalAnswerFromQuestion(cachedQuestion);
        }

        if (isIndexInCurrentWindow(safeIndex) && isQuestionPayloadLoaded(questionId) && (!shouldRestoreAnsweredState || hasUsableAnsweredState)) {
            if ((!Array.isArray(state.questions) || !state.questions.length) && state.windowLimit > 0) {
                setQuestionWindowFromLoadedPayloads(state.windowOffset, state.windowLimit);
            }
            return getQuestionPayloadById(questionId);
        }

        if (isQuestionPayloadLoaded(questionId) && (!shouldRestoreAnsweredState || hasUsableAnsweredState) && setQuestionWindowFromLoadedPayloads(targetOffset, options.limit || QUESTION_WINDOW_SIZE)) {
            return getQuestionPayloadById(questionId);
        }

        await loadQuestionWindow(
            targetOffset,
            {
                examId: options.examId,
                attemptId: options.attemptId,
                includeExisting: options.includeExisting !== undefined ? options.includeExisting : 1,
                limit: options.limit || QUESTION_WINDOW_SIZE
            }
        );

        questionId = getQuestionIdAtIndex(clampQuestionIndex(index));
        return getQuestionPayloadById(questionId);
    }

    function restoreQuestionAutoSaveState(snapshot) {
        var safeSnapshot = snapshot && typeof snapshot === 'object' ? snapshot : {};
        lastSubmittedPayloadByQuestion = safeSnapshot.lastSubmittedPayloadByQuestion && typeof safeSnapshot.lastSubmittedPayloadByQuestion === 'object'
            ? Object.assign({}, safeSnapshot.lastSubmittedPayloadByQuestion)
            : {};
        pendingAnswerBatchByQuestion = safeSnapshot.pendingAnswerBatchByQuestion && typeof safeSnapshot.pendingAnswerBatchByQuestion === 'object'
            ? Object.assign({}, safeSnapshot.pendingAnswerBatchByQuestion)
            : {};
        pendingAnswerBatchOrder = Array.isArray(safeSnapshot.pendingAnswerBatchOrder)
            ? safeSnapshot.pendingAnswerBatchOrder.slice()
            : [];
        autoSaveCongestedUntil = Math.max(0, Number(safeSnapshot.autoSaveCongestedUntil) || 0);
    }

    async function refreshAttemptQuestionRevision(nextRevision, options) {
        options = options || {};

        var attemptId = Number(options.attemptId !== undefined ? options.attemptId : state.attemptId) || 0;
        var examId = Number(options.examId !== undefined ? options.examId : state.selectedExamId) || 0;
        var normalizedNextRevision = normalizeQuestionRevision(nextRevision, examId);

        if (state.stage !== 'exam' || attemptId <= 0 || examId <= 0) {
            return Promise.resolve(null);
        }

        if (normalizedNextRevision && state.questionRevision && questionRevisionEquals(normalizedNextRevision, state.questionRevision, examId)) {
            return Promise.resolve(null);
        }

        if (questionRevisionRefreshInFlight) {
            return questionRevisionRefreshInFlight;
        }

        var requestedIndex = Number(options.preferredIndex);
        if (!Number.isFinite(requestedIndex) || requestedIndex < 0) {
            requestedIndex = Number(state.currentIndex) || 0;
        }

        var attemptUiSnapshot = buildAttemptUiStateSnapshot(attemptId) || {
            attempt_id: attemptId,
            current_index: Math.max(0, Math.floor(requestedIndex)),
            doubtful_question_ids: []
        };
        attemptUiSnapshot.current_index = Math.max(0, Math.floor(requestedIndex));

        var previousQuestionManifestById = Object.keys(state.questionManifestById || {}).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && state.questionManifestById[key]) {
                accumulator[questionId] = state.questionManifestById[key];
            }
            return accumulator;
        }, {});
        var preservedNavQuestionFilter = normalizeNavigationQuestionFilter(state.navQuestionFilter);
        var preservedChangedQuestionLookup = Object.assign({}, state.changedQuestionLookup || {});
        var preservedAnswers = captureRevisionSafeLocalAnswers();
        var preservedAutoSaveState = {
            autoSaveCongestedUntil: autoSaveCongestedUntil,
            lastSubmittedPayloadByQuestion: Object.assign({}, lastSubmittedPayloadByQuestion),
            pendingAnswerBatchByQuestion: Object.assign({}, pendingAnswerBatchByQuestion),
            pendingAnswerBatchOrder: pendingAnswerBatchOrder.slice()
        };

        questionRevisionRefreshInFlight = (async function () {
            var refreshGeneration = bumpQuestionDataGeneration();
            clearPersistedQuestionCache(attemptId);
            clearQuestionCachePersistTimer();
            clearQuestionPrefetchRuntimeState();
            clearAttemptUiStateSyncTimer();
            clearPendingRevisionSafeAnswerRestoreState();
            clearAutoSaveRuntimeState();
            state.busy = true;
            state.notice = '';
            render();

            try {
                var refreshOffset = questionWindowOffsetForIndex(attemptUiSnapshot.current_index, QUESTION_WINDOW_SIZE);
                var questionPayload = await loadQuestionWindow(
                    refreshOffset,
                    {
                        examId: examId,
                        attemptId: attemptId,
                        includeExisting: 1,
                        includeAnswerManifest: 1,
                        limit: QUESTION_WINDOW_SIZE,
                        skipApply: true,
                        allowRevisionTransition: true
                    }
                );

                if (
                    refreshGeneration !== questionDataGeneration
                    || state.stage !== 'exam'
                    || Number(state.attemptId) !== attemptId
                    || Number(state.selectedExamId) !== examId
                ) {
                    return null;
                }

                var appliedRevision = normalizeQuestionRevision(
                    questionPayload && questionPayload.question_revision,
                    examId
                ) || normalizedNextRevision;
                if (questionPayload && typeof questionPayload === 'object') {
                    questionPayload.question_revision = serializeQuestionRevision(appliedRevision, examId);
                }

                resetQuestionDataState();
                clearPendingRevisionSafeAnswerRestoreState();
                setQuestionRevision(appliedRevision, examId);
                state.navQuestionFilter = preservedNavQuestionFilter;
                applyQuestionsResponse(questionPayload, {
                    overwriteExisting: true,
                    preserveActiveWindow: false
                });
                state.changedQuestionLookup = buildChangedQuestionLookup(
                    previousQuestionManifestById,
                    state.questionManifestById,
                    preservedChangedQuestionLookup
                );

                if (!getQuestionCount()) {
                    throw new Error('Belum ada soal pada exam ini.');
                }

                initializeSubmittedPayloadCache();
                applyAttemptUiState(attemptUiSnapshot, attemptId);
                state.navQuestionFilter = preservedNavQuestionFilter;
                restoreRevisionSafeLocalAnswers(preservedAnswers, {
                    deferMissing: true
                });
                pruneQuestionScopedState(validAttemptQuestionIds());
                state.currentIndex = clampQuestionIndex(attemptUiSnapshot.current_index);

                await ensureQuestionWindowForIndex(state.currentIndex, {
                    examId: examId,
                    attemptId: attemptId,
                    includeExisting: 1,
                    limit: QUESTION_WINDOW_SIZE
                });

                pruneQuestionScopedState(validAttemptQuestionIds());
                initializeSubmittedPayloadCache();
                var queuedRestoredAnswerCount = queueLoadedQuestionAnswersForFlush();

                persistCurrentAttemptUiStateLocally();
                persistCurrentQuestionCacheLocally();
                syncAttemptUiStateSignatureToCurrentState();
                scheduleAttemptUiStateSync(ATTEMPT_UI_STATE_SYNC_DELAY_MS);
                if (queuedRestoredAnswerCount > 0) {
                    scheduleAnswerBatchFlush(700);
                }

                state.busy = false;
                state.notice = getChangedQuestionCount() > 0
                    ? 'Perubahan soal terdeteksi. Nomor merah menandakan soal yang berubah.'
                    : '';
                if (typeof state.error === 'string' && state.error.indexOf('Autosave') !== 0) {
                    state.error = '';
                }
                render();
                resetQuestionPrefetchIdleTimer();
                return questionPayload;
            } catch (error) {
                if (
                    refreshGeneration === questionDataGeneration
                    && state.stage === 'exam'
                    && Number(state.attemptId) === attemptId
                    && Number(state.selectedExamId) === examId
                ) {
                    restoreQuestionAutoSaveState(preservedAutoSaveState);
                    state.busy = false;
                    state.notice = 'Perubahan soal terdeteksi. Sinkronisasi akan dicoba lagi.';
                    render();
                    if (pendingAnswerBatchOrder.length > 0) {
                        scheduleAnswerBatchFlush(300);
                    }
                    resetQuestionPrefetchIdleTimer();
                }
                return null;
            } finally {
                questionRevisionRefreshInFlight = null;
            }
        })();

        return questionRevisionRefreshInFlight;
    }

    function clearAttemptUiStateSyncTimer() {
        if (attemptUiStateSyncTimer) {
            window.clearTimeout(attemptUiStateSyncTimer);
        }
        attemptUiStateSyncTimer = 0;
    }

    function attemptUiStateSignature(snapshot) {
        return payloadSignature(snapshot);
    }

    function syncAttemptUiStateSignatureToCurrentState() {
        var snapshot = buildAttemptUiStateSnapshot();
        lastAttemptUiStateSyncSignature = snapshot ? attemptUiStateSignature(snapshot) : '';
    }

    function scheduleAttemptUiStateSync(delayMs) {
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
            return;
        }

        var snapshot = buildAttemptUiStateSnapshot();
        if (!snapshot) {
            return;
        }

        persistAttemptUiStateLocally(snapshot);
        var nextSignature = attemptUiStateSignature(snapshot);
        if (!attemptUiStateSyncInFlight && nextSignature === lastAttemptUiStateSyncSignature) {
            clearAttemptUiStateSyncTimer();
            return;
        }

        clearAttemptUiStateSyncTimer();
        attemptUiStateSyncTimer = window.setTimeout(function () {
            clearAttemptUiStateSyncTimer();
            flushAttemptUiState().catch(function () {
                // Local fallback already persisted; retry on next interaction/lifecycle event.
            });
        }, Math.max(0, Number(delayMs) || 0));
    }

    async function flushAttemptUiState(options) {
        options = options || {};

        if (state.stage !== 'exam' || state.attemptId <= 0) {
            return null;
        }
        if (state.isFinishing && !options.allowWhileFinishing) {
            return null;
        }

        clearAttemptUiStateSyncTimer();

        var snapshot = buildAttemptUiStateSnapshot();
        if (!snapshot) {
            return null;
        }

        persistAttemptUiStateLocally(snapshot);
        var snapshotSignature = attemptUiStateSignature(snapshot);

        if (attemptUiStateSyncInFlight) {
            try {
                await attemptUiStateSyncInFlight;
            } catch (error) {
                // Use latest local snapshot below.
            }
        }

        if (!options.force && snapshotSignature === lastAttemptUiStateSyncSignature) {
            return null;
        }

        attemptUiStateSyncInFlight = api('ui_state', {
            method: 'POST',
            keepalive: !!options.keepalive,
            token: options.token,
            body: {
                attempt_state: snapshot
            }
        }).then(function (responsePayload) {
            lastAttemptUiStateSyncSignature = snapshotSignature;

            if (responsePayload && responsePayload.attempt_state && typeof responsePayload.attempt_state === 'object') {
                persistAttemptUiStateLocally(responsePayload.attempt_state);
            }

            return responsePayload;
        }).finally(function () {
            attemptUiStateSyncInFlight = null;

            if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
                return;
            }

            var latestSnapshot = buildAttemptUiStateSnapshot();
            if (!latestSnapshot) {
                return;
            }

            if (attemptUiStateSignature(latestSnapshot) !== lastAttemptUiStateSyncSignature) {
                scheduleAttemptUiStateSync(ATTEMPT_UI_STATE_SYNC_DELAY_MS);
            }
        });

        return attemptUiStateSyncInFlight;
    }

    function escapeHtml(value) {
        var normalized = value === null || value === undefined ? '' : value;
        return String(normalized)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizePhotoUrl(value) {
        var text = String(value || '').trim();
        if (!text) {
            return '';
        }

        if (!/^https?:\/\//i.test(text) && !/^\/\//.test(text) && text.charAt(0) !== '/') {
            return '';
        }

        try {
            var parsed = new URL(text, window.location.origin + '/');
            if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
                var current = new URL(window.location.origin + '/');
                var parsedHost = String(parsed.hostname || '').toLowerCase();
                var currentHost = String(current.hostname || '').toLowerCase();
                var parsedIsLocal = parsedHost === 'localhost' || parsedHost === '127.0.0.1' || parsedHost === '::1';

                // Jika URL tersimpan dari lingkungan lokal lain, fallback ke path agar ikut origin aktif.
                if (parsedIsLocal && parsedHost !== currentHost && parsed.pathname) {
                    return parsed.pathname + parsed.search + parsed.hash;
                }

                // Hindari mixed-content ketika host sama namun skema berbeda.
                if (current.protocol === 'https:' && parsed.protocol === 'http:' && parsedHost === currentHost) {
                    parsed.protocol = 'https:';
                }

                return parsed.toString();
            }
        } catch (error) {
            return '';
        }

        return '';
    }

    function getConfiguredSchoolName() {
        var value = String(config.schoolName || config.siteName || '').trim();
        return value || 'CBT Exam';
    }

    function getConfiguredSchoolMotto() {
        return String(config.schoolMotto || '').trim();
    }

    function getConfiguredSchoolLogoUrl() {
        return normalizePhotoUrl(config.schoolLogoUrl || '');
    }

    function getConfiguredPluginAuthor() {
        return String(config.pluginAuthor || 'COBLAX').trim();
    }

    function getConfiguredPluginVersion() {
        return String(config.pluginVersion || '').trim();
    }

    function isExamFullscreenRequired() {
        return Number(config.securityForceFullscreen || 0) === 1;
    }

    function isExamCopyPasteBlocked() {
        return Number(config.securityBlockCopyPaste || 0) === 1;
    }

    function isSecurityLoggingEnabled() {
        return Number(config.securityLogEvents || 0) === 1;
    }

    function isSecurityLoggingActiveForAttempt() {
        return isSecurityLoggingEnabled() && state.stage === 'exam' && (Number(state.attemptId) || 0) > 0;
    }

    function clearSecurityLoggingRuntimeState() {
        if (tabHiddenLogTimer) {
            window.clearTimeout(tabHiddenLogTimer);
        }
        tabHiddenLogTimer = 0;
        tabHiddenLogScheduledAttemptId = 0;
        pageLeaveLoggedAttemptId = 0;
        securityEventLastSentAtByKey = {};
    }

    function shouldThrottleSecurityEvent(eventType, attemptId, debounceMs) {
        var safeAttemptId = Number(attemptId) || 0;
        var safeDebounceMs = Math.max(0, Number(debounceMs) || 0);
        var throttleKey = String(eventType || '') + ':' + String(safeAttemptId);
        var now = Date.now();
        var lastSentAt = Number(securityEventLastSentAtByKey[throttleKey] || 0);

        if (safeDebounceMs > 0 && lastSentAt > 0 && (lastSentAt + safeDebounceMs) > now) {
            return true;
        }

        securityEventLastSentAtByKey[throttleKey] = now;
        return false;
    }

    function getFullscreenElement() {
        if (document.fullscreenElement) {
            return document.fullscreenElement;
        }
        if (document.webkitFullscreenElement) {
            return document.webkitFullscreenElement;
        }
        if (document.mozFullScreenElement) {
            return document.mozFullScreenElement;
        }
        if (document.msFullscreenElement) {
            return document.msFullscreenElement;
        }
        return null;
    }

    function syncFullscreenState(shouldRender) {
        var nextState = !!getFullscreenElement();
        if (state.isFullscreenActive === nextState) {
            return;
        }

        state.isFullscreenActive = nextState;

        if (shouldRender) {
            render();
        }
    }

    function isExamFullscreenBlockingActive() {
        return state.stage === 'exam' && isExamFullscreenRequired() && !state.isFullscreenActive;
    }

    function isExamClipboardBlockingActive() {
        return state.stage === 'exam' && isExamCopyPasteBlocked();
    }

    function handleBlockedClipboardAction(action, sourceEvent) {
        var safeAction = String(action || '').trim().toLowerCase();
        var safeSource = sourceEvent && sourceEvent.type ? String(sourceEvent.type) : '';

        if (!isExamClipboardBlockingActive()) {
            return false;
        }

        if (sourceEvent && typeof sourceEvent.preventDefault === 'function') {
            sourceEvent.preventDefault();
        }
        if (sourceEvent && typeof sourceEvent.stopPropagation === 'function') {
            sourceEvent.stopPropagation();
        }

        sendSecurityEventSilently('clipboard_blocked', {
            action: safeAction || 'clipboard',
            source: safeSource || safeAction || 'clipboard'
        }, {
            attemptId: Number(state.attemptId) || 0,
            keepalive: true,
            debounceMs: 1500
        });

        return true;
    }

    function requestFullscreenForElement(targetElement) {
        if (!targetElement) {
            return Promise.resolve(false);
        }

        try {
            if (typeof targetElement.requestFullscreen === 'function') {
                return Promise.resolve(targetElement.requestFullscreen()).then(function () {
                    return true;
                });
            }
            if (typeof targetElement.webkitRequestFullscreen === 'function') {
                targetElement.webkitRequestFullscreen();
                return Promise.resolve(true);
            }
            if (typeof targetElement.mozRequestFullScreen === 'function') {
                targetElement.mozRequestFullScreen();
                return Promise.resolve(true);
            }
            if (typeof targetElement.msRequestFullscreen === 'function') {
                targetElement.msRequestFullscreen();
                return Promise.resolve(true);
            }
        } catch (error) {
            return Promise.reject(error);
        }

        return Promise.resolve(false);
    }

    function exitFullscreenSilently() {
        fullscreenExitLogSuppressedUntil = Date.now() + 2000;

        try {
            if (document.fullscreenElement && typeof document.exitFullscreen === 'function') {
                document.exitFullscreen().catch(function () {
                    // Ignore fullscreen exit errors.
                });
                return;
            }
            if (document.webkitFullscreenElement && typeof document.webkitExitFullscreen === 'function') {
                document.webkitExitFullscreen();
                return;
            }
            if (document.mozFullScreenElement && typeof document.mozCancelFullScreen === 'function') {
                document.mozCancelFullScreen();
                return;
            }
            if (document.msFullscreenElement && typeof document.msExitFullscreen === 'function') {
                document.msExitFullscreen();
            }
        } catch (error) {
            // Ignore fullscreen exit errors.
        }
    }

    async function requestExamFullscreen(options) {
        options = options || {};

        if (!isExamFullscreenRequired()) {
            return true;
        }

        syncFullscreenState(false);
        if (state.isFullscreenActive) {
            return true;
        }

        var fullscreenTarget = document.documentElement || document.body || root;
        try {
            var entered = await requestFullscreenForElement(fullscreenTarget);
            syncFullscreenState(false);

            if (entered) {
                state.isFullscreenActive = true;
                clearMessages();
                return true;
            }
        } catch (error) {
            syncFullscreenState(false);
        }

        if (!options.silent) {
            state.error = 'Mode fullscreen wajib aktif untuk ujian ini. Izinkan fullscreen lalu coba lagi.';
        }
        return false;
    }

    function renderExamFullscreenPrompt() {
        if (!isExamFullscreenBlockingActive()) {
            return '';
        }

        return [
            '<div class="cbt-exam-fullscreen-guard" role="alert" aria-live="assertive">',
            '<div class="cbt-exam-fullscreen-guard-card">',
            '<span class="cbt-exam-fullscreen-guard-chip">Security</span>',
            '<h3>Mode Fullscreen Wajib Aktif</h3>',
            '<p>Ujian ini menggunakan pengamanan fullscreen. Aktifkan fullscreen terlebih dahulu untuk melanjutkan pengerjaan soal.</p>',
            '<div class="cbt-actions cbt-exam-fullscreen-guard-actions">',
            '<button class="cbt-button cbt-button-primary" data-action="enter-fullscreen" type="button">Aktifkan Fullscreen</button>',
            '<button class="cbt-button cbt-button-secondary" data-action="logout" type="button">Logout</button>',
            '</div>',
            '</div>',
            '</div>'
        ].join('');
    }

    function normalizeLoginHeroSchoolTag(prefix, number) {
        var normalizedPrefix = String(prefix || '').replace(/\s+/g, ' ').trim().toUpperCase();
        var compactPrefix = normalizedPrefix;

        if (/^SMK(?:\s+N(?:EGERI)?)?$/.test(normalizedPrefix) || normalizedPrefix === 'SMKN') {
            compactPrefix = 'SMKN';
        } else if (/^SMA(?:\s+N(?:EGERI)?)?$/.test(normalizedPrefix) || normalizedPrefix === 'SMAN') {
            compactPrefix = 'SMAN';
        } else if (/^SMP(?:\s+N(?:EGERI)?)?$/.test(normalizedPrefix) || normalizedPrefix === 'SMPN') {
            compactPrefix = 'SMPN';
        } else if (/^SD(?:\s+N(?:EGERI)?)?$/.test(normalizedPrefix) || normalizedPrefix === 'SDN') {
            compactPrefix = 'SDN';
        } else if (/^MTSN?$/.test(normalizedPrefix)) {
            compactPrefix = normalizedPrefix === 'MTS' ? 'MTS' : 'MTSN';
        } else if (/^MA(?:\s+NEGERI)?$/.test(normalizedPrefix) || normalizedPrefix === 'MAN') {
            compactPrefix = (normalizedPrefix.indexOf('NEGERI') >= 0 || normalizedPrefix === 'MAN') ? 'MAN' : 'MA';
        } else if (normalizedPrefix === 'MI') {
            compactPrefix = 'MI';
        }

        var normalizedNumber = String(number || '').trim();
        return normalizedNumber !== '' ? (compactPrefix + ' ' + normalizedNumber) : compactPrefix;
    }

    function getLoginHeroSchoolBranding(schoolName) {
        var normalized = String(schoolName || '').replace(/\s+/g, ' ').trim();
        var branding = {
            tag: 'Portal CBT',
            title: normalized || 'CBT Exam'
        };

        if (normalized === '') {
            return branding;
        }

        var match = normalized.match(/^(SMK(?:\s+N(?:EGERI)?)?|SMKN|SMA(?:\s+N(?:EGERI)?)?|SMAN|SMP(?:\s+N(?:EGERI)?)?|SMPN|SD(?:\s+N(?:EGERI)?)?|SDN|MI|MTSN?|MAN|MA(?:\s+NEGERI)?)(?:\s+(\d+))?\s+(.+)$/i);
        if (!match) {
            return branding;
        }

        branding.tag = normalizeLoginHeroSchoolTag(match[1], match[2]);
        branding.title = String(match[3] || normalized).trim() || normalized;

        return branding;
    }

    function getCurrentUserName() {
        return state.user && state.user.display_name
            ? String(state.user.display_name)
            : (state.user && state.user.username ? String(state.user.username) : '-');
    }

    function getCurrentUserRole() {
        return state.user && state.user.role ? String(state.user.role) : '-';
    }

    function getCurrentUserPhoto() {
        return normalizePhotoUrl(state.user && state.user.foto ? state.user.foto : '');
    }

    function getUserInitial(name) {
        var text = String(name || '').trim();
        if (!text) {
            return 'U';
        }

        for (var i = 0; i < text.length; i++) {
            var ch = text.charAt(i);
            if (/[A-Za-z0-9]/.test(ch)) {
                return ch.toUpperCase();
            }
        }

        return 'U';
    }

    function safeRichHtml(value) {
        var html = String(value || '');
        html = html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');
        html = html.replace(/\son\w+="[^"]*"/gi, '');
        html = html.replace(/\son\w+='[^']*'/gi, '');
        html = html.replace(/\r\n?/g, '\n');
        // TinyMCE/WordPress often stores blank lines as empty paragraphs.
        // Remove those wrappers so manual newlines don't look overly spaced.
        html = html.replace(/<p\b[^>]*>\s*(?:&nbsp;|&#160;|<br\s*\/?>)*\s*<\/p>/gi, '');

        var hasExplicitLineBreakMarkup = /<(?:br|p|div|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|blockquote|pre|figure|figcaption|h[1-6]|hr)\b/i.test(html);
        if (!hasExplicitLineBreakMarkup && html.indexOf('\n') >= 0) {
            // Import/textarea content may store each visual line as double newlines.
            // Collapse repeated blank lines so one "Enter" does not look too far apart.
            html = html.replace(/\n\s*\n+/g, '\n');
            html = html.replace(/\n/g, '<br />');
        }

        return html;
    }

    function parseDateTime(value) {
        var text = String(value || '').trim();
        if (!text) {
            return null;
        }
        var normalized = text.indexOf('T') >= 0 ? text : text.replace(' ', 'T');
        var parsed = new Date(normalized);
        if (Number.isNaN(parsed.getTime())) {
            return null;
        }
        return parsed;
    }

    function formatDateTime(value) {
        var date = parseDateTime(value);
        if (!date) {
            return '-';
        }
        try {
            return new Intl.DateTimeFormat('id-ID', {
                dateStyle: 'medium',
                timeStyle: 'short'
            }).format(date);
        } catch (error) {
            return date.toLocaleString();
        }
    }

    function formatDateTimeCompact(value) {
        var date = parseDateTime(value);
        if (!date) {
            return '-';
        }
        try {
            return new Intl.DateTimeFormat('id-ID', {
                day: 'numeric',
                month: 'short',
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        } catch (error) {
            return formatDateTime(value);
        }
    }

    function formatQuestionType(type) {
        var map = {
            multiple_choice: 'Multiple Choice',
            multiple_answer: 'Multiple Answer',
            true_false: 'True / False',
            true_false_matrix: 'True / False Matrix',
            short_answer: 'Short Answer',
            essay: 'Essay'
        };
        return map[type] || type || '-';
    }

    function navigationQuestionTypeBadgeConfig(questionType) {
        var map = {
            multiple_choice: { code: 'MC', className: 'is-mc' },
            multiple_answer: { code: 'MA', className: 'is-ma' },
            true_false: { code: 'TF', className: 'is-tf' },
            true_false_matrix: { code: 'TFM', className: 'is-tf' },
            short_answer: { code: 'SA', className: 'is-sa' },
            essay: { code: 'ES', className: 'is-es' }
        };
        var key = String(questionType || '');
        return map[key] || { code: '--', className: 'is-unknown' };
    }

    function formatSeconds(totalSeconds) {
        var safe = Math.max(0, Number(totalSeconds) || 0);
        var hours = Math.floor(safe / 3600);
        var minutes = Math.floor((safe % 3600) / 60);
        var seconds = safe % 60;
        return String(hours).padStart(2, '0') + ':' +
            String(minutes).padStart(2, '0') + ':' +
            String(seconds).padStart(2, '0');
    }

    function renderAlert() {
        if (state.error) {
            return '<div class="cbt-alert cbt-alert-error">' + escapeHtml(state.error) + '</div>';
        }
        if (state.notice) {
            return '<div class="cbt-alert cbt-alert-warning">' + escapeHtml(state.notice) + '</div>';
        }
        if (state.success) {
            return '<div class="cbt-alert cbt-alert-success">' + escapeHtml(state.success) + '</div>';
        }
        return '';
    }

    function clearMessages() {
        state.error = '';
        state.notice = '';
        state.success = '';
    }

    function getSelectedExam() {
        var examId = Number(state.selectedExamId) || 0;
        for (var i = 0; i < state.exams.length; i++) {
            var exam = state.exams[i];
            if (Number(exam.id) === examId) {
                return exam;
            }
        }
        return null;
    }

    function findExamById(examId) {
        var targetExamId = Number(examId) || 0;
        if (targetExamId <= 0) {
            return null;
        }
        for (var i = 0; i < state.exams.length; i++) {
            var exam = state.exams[i];
            if (Number(exam && exam.id) === targetExamId) {
                return exam;
            }
        }
        return null;
    }

    function questionOptionKey(option, index) {
        var key = String(option && option.option_key ? option.option_key : '').trim();
        if (key) {
            return key;
        }
        var code = 65 + index;
        if (code >= 65 && code <= 90) {
            return String.fromCharCode(code);
        }
        return String(index + 1);
    }

    function getShortAnswerKeys(question) {
        var meta = question && question.short_answer_meta ? question.short_answer_meta : null;
        var keys = meta && Array.isArray(meta.input_keys) ? meta.input_keys.slice(0, 8) : [];
        if (!keys.length) {
            keys = ['A'];
        }
        return keys.map(function (item) {
            return String(item || '').trim().toUpperCase();
        }).filter(function (item) {
            return item !== '';
        });
    }

    function getTrueFalseMatrixItems(question) {
        var meta = question && question.true_false_matrix_meta ? question.true_false_matrix_meta : null;
        var items = meta && Array.isArray(meta.items) ? meta.items : [];
        return items.map(function (item, index) {
            var key = String(item && item.key ? item.key : (index + 1)).trim();
            if (key === '') {
                key = String(index + 1);
            }
            return {
                key: key,
                text: String(item && item.text ? item.text : '')
            };
        });
    }

    function findQuestionOptionById(question, optionId) {
        var safeOptionId = Number(optionId) || 0;
        if (safeOptionId <= 0 || !question || !Array.isArray(question.options)) {
            return null;
        }

        for (var index = 0; index < question.options.length; index++) {
            var option = question.options[index];
            if (Number(option && option.id) === safeOptionId) {
                return option;
            }
        }

        return null;
    }

    function findQuestionOptionByKey(question, optionKey) {
        var normalizedKey = String(optionKey || '').trim().toUpperCase();
        if (normalizedKey === '' || !question || !Array.isArray(question.options)) {
            return null;
        }

        for (var index = 0; index < question.options.length; index++) {
            var option = question.options[index];
            if (String(questionOptionKey(option, index) || '').trim().toUpperCase() === normalizedKey) {
                return option;
            }
        }

        return null;
    }

    function findQuestionOptionKeyById(question, optionId) {
        var safeOptionId = Number(optionId) || 0;
        if (safeOptionId <= 0 || !question || !Array.isArray(question.options)) {
            return '';
        }

        for (var index = 0; index < question.options.length; index++) {
            var option = question.options[index];
            if (Number(option && option.id) === safeOptionId) {
                return String(questionOptionKey(option, index) || '').trim().toUpperCase();
            }
        }

        return '';
    }

    function normalizeAnswerValueForQuestion(question, rawAnswer, options) {
        options = options || {};

        if (!question) {
            return {
                hasValue: false,
                value: null
            };
        }

        var preserveText = !!options.preserveText;
        var questionType = String(question.question_type || '');

        if (questionType === 'multiple_choice' || questionType === 'true_false') {
            var selectedId = Number(rawAnswer) || 0;
            var selectedOption = findQuestionOptionById(question, selectedId);
            return {
                hasValue: !!selectedOption,
                value: selectedOption ? Number(selectedOption.id) || 0 : null
            };
        }

        if (questionType === 'multiple_answer') {
            if (!Array.isArray(rawAnswer)) {
                return {
                    hasValue: false,
                    value: null
                };
            }

            var selectedOptionIds = [];
            var seenOptionIds = {};
            rawAnswer.forEach(function (item) {
                var option = findQuestionOptionById(question, item);
                var optionId = Number(option && option.id) || 0;
                if (optionId <= 0 || seenOptionIds[optionId]) {
                    return;
                }
                seenOptionIds[optionId] = true;
                selectedOptionIds.push(optionId);
            });

            return {
                hasValue: selectedOptionIds.length > 0,
                value: selectedOptionIds.length ? selectedOptionIds : null
            };
        }

        if (questionType === 'true_false_matrix') {
            var matrixValue = normalizeTrueFalseMatrixAnswer(rawAnswer);
            var validMatrixKeys = getTrueFalseMatrixItems(question).reduce(function (accumulator, item) {
                accumulator[String(item.key || '').trim()] = true;
                return accumulator;
            }, {});
            var filteredMatrixValue = Object.keys(matrixValue).reduce(function (accumulator, key) {
                if (!Object.keys(validMatrixKeys).length || validMatrixKeys[key]) {
                    accumulator[key] = matrixValue[key];
                }
                return accumulator;
            }, {});

            return {
                hasValue: Object.keys(filteredMatrixValue).length > 0,
                value: Object.keys(filteredMatrixValue).length ? filteredMatrixValue : null
            };
        }

        if (questionType === 'short_answer') {
            if (!rawAnswer || typeof rawAnswer !== 'object' || Array.isArray(rawAnswer)) {
                return {
                    hasValue: false,
                    value: null
                };
            }

            var allowedKeys = getShortAnswerKeys(question).reduce(function (accumulator, key) {
                accumulator[String(key || '').trim().toUpperCase()] = true;
                return accumulator;
            }, {});
            var shortAnswerValue = Object.keys(rawAnswer).reduce(function (accumulator, key) {
                var normalizedKey = String(key || '').trim().toUpperCase();
                if (normalizedKey === '' || !allowedKeys[normalizedKey]) {
                    return accumulator;
                }

                var nextValue = rawAnswer[key] === undefined || rawAnswer[key] === null
                    ? ''
                    : String(rawAnswer[key]);
                if (nextValue.trim() === '') {
                    return accumulator;
                }

                accumulator[normalizedKey] = preserveText ? nextValue : nextValue.trim();
                return accumulator;
            }, {});

            return {
                hasValue: Object.keys(shortAnswerValue).length > 0,
                value: Object.keys(shortAnswerValue).length ? shortAnswerValue : null
            };
        }

        var textValue = rawAnswer === undefined || rawAnswer === null ? '' : String(rawAnswer);
        if (textValue.trim() === '') {
            return {
                hasValue: false,
                value: null
            };
        }

        return {
            hasValue: true,
            value: preserveText ? textValue : textValue.trim()
        };
    }

    function normalizeTrueFalseMatrixAnswer(answer) {
        if (!answer || typeof answer !== 'object' || Array.isArray(answer)) {
            return {};
        }

        var normalized = {};
        Object.keys(answer).sort(function (a, b) {
            return Number(a) - Number(b);
        }).forEach(function (key) {
            var keyText = String(key || '').trim();
            if (keyText === '') {
                return;
            }
            var valueText = String(answer[key] || '').trim().toLowerCase();
            if (valueText === 'true' || valueText === 'false') {
                normalized[keyText] = valueText;
            }
        });

        return normalized;
    }

    function shortAnswerKeyToIndex(key) {
        var normalized = String(key || '').trim().toUpperCase();
        if (/^[1-8]$/.test(normalized)) {
            return Number(normalized);
        }
        if (/^[A-H]$/.test(normalized)) {
            return normalized.charCodeAt(0) - 64;
        }
        return 0;
    }

    function hasShortAnswerPlaceholder(questionText) {
        return /\[\s*input(?:\s*[_-]?\s*)?([a-h1-8])\s*\]/i.test(String(questionText || ''));
    }

    function buildShortAnswerPlaceholderPattern(token) {
        var escapedToken = String(token || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return new RegExp('\\[\\s*input(?:\\s*[_-]?\\s*)?' + escapedToken + '\\s*\\]', 'ig');
    }

    function renderShortAnswerInlineField(questionId, key, value, instance, isFallback) {
        var safeQuestionId = Number(questionId) || 0;
        var safeKey = String(key || '').trim().toUpperCase();
        var safeInstance = Number(instance) || 1;
        var inputId = 'cbt_short_' + safeQuestionId + '_' + safeKey + '_' + safeInstance;
        var wrapperClass = 'cbt-short-inline-field' + (isFallback ? ' is-fallback' : '');
        var keyChip = isFallback ? ('<span class="cbt-short-inline-key">' + escapeHtml(safeKey) + '</span>') : '';

        return [
            '<span class="' + wrapperClass + '">',
            keyChip,
            '<input',
            ' id="' + escapeHtml(inputId) + '"',
            ' class="cbt-input cbt-short-inline-input"',
            ' data-action="answer-short"',
            ' data-qid="' + escapeHtml(safeQuestionId) + '"',
            ' data-short-key="' + escapeHtml(safeKey) + '"',
            ' value="' + escapeHtml(String(value || '')) + '"',
            ' aria-label="Input ' + escapeHtml(safeKey) + '"',
            ' placeholder="' + escapeHtml(safeKey) + '"',
            ' />',
            '</span>'
        ].join('');
    }

    function renderShortAnswerStem(question) {
        var rawStem = String(question && question.question_text ? question.question_text : '');
        var questionId = Number(question && question.id) || 0;
        var keys = getShortAnswerKeys(question);
        var answer = resolveStoredAnswerValueForQuestion(question);
        var values = answer && typeof answer === 'object' ? answer : {};
        var inlineFieldCountByKey = {};
        var usedKeyMap = {};
        var hasInlinePlaceholders = hasShortAnswerPlaceholder(rawStem);
        var stemWithFields = rawStem;

        function nextFieldInstance(key) {
            var safeKey = String(key || '').trim().toUpperCase();
            var nextValue = Number(inlineFieldCountByKey[safeKey] || 0) + 1;
            inlineFieldCountByKey[safeKey] = nextValue;
            return nextValue;
        }

        function replacePlaceholder(pattern, key) {
            stemWithFields = stemWithFields.replace(pattern, function () {
                usedKeyMap[key] = true;
                return renderShortAnswerInlineField(
                    questionId,
                    key,
                    values[key],
                    nextFieldInstance(key),
                    false
                );
            });
        }

        keys.forEach(function (key) {
            replacePlaceholder(buildShortAnswerPlaceholderPattern(key), key);

            var keyIndex = shortAnswerKeyToIndex(key);
            if (keyIndex > 0) {
                replacePlaceholder(buildShortAnswerPlaceholderPattern(String(keyIndex)), key);
            }
        });

        var stemMarkup = safeRichHtml(stemWithFields);
        var missingKeys = keys.filter(function (key) {
            return !usedKeyMap[key];
        });

        if (!hasInlinePlaceholders || missingKeys.length) {
            var fallbackKeys = hasInlinePlaceholders ? missingKeys : keys;
            var fallbackMarkup = fallbackKeys.map(function (key) {
                return renderShortAnswerInlineField(
                    questionId,
                    key,
                    values[key],
                    nextFieldInstance(key),
                    true
                );
            }).join('');

            if (fallbackMarkup !== '') {
                stemMarkup += '<div class="cbt-short-inline-fallback">' + fallbackMarkup + '</div>';
            }
        }

        return stemMarkup;
    }

    function renderQuestionStem(question) {
        if (question && question.question_type === 'short_answer') {
            return renderShortAnswerStem(question);
        }
        return safeRichHtml(question && question.question_text ? question.question_text : '');
    }

    function isQuestionAnswered(question) {
        if (!question) {
            return false;
        }
        var questionId = Number(question.id) || 0;
        var hasLocalAnswer = hasUsableLocalAnswerForQuestion(questionId, question);
        var answer = hasLocalAnswer ? state.answers[questionId] : resolveStoredAnswerValueForQuestion(question);

        if (!hasLocalAnswer) {
            return !!(state.answeredQuestionLookup && state.answeredQuestionLookup[questionId]);
        }

        if (question.question_type === 'multiple_choice' || question.question_type === 'true_false') {
            return Number(answer) > 0;
        }

        if (question.question_type === 'true_false_matrix') {
            var normalizedMatrixAnswer = normalizeTrueFalseMatrixAnswer(answer);
            return Object.keys(normalizedMatrixAnswer).some(function (key) {
                var value = String(normalizedMatrixAnswer[key] || '').trim().toLowerCase();
                return value === 'true' || value === 'false';
            });
        }

        if (question.question_type === 'multiple_answer') {
            return Array.isArray(answer) && answer.length > 0;
        }

        if (question.question_type === 'short_answer') {
            if (!answer || typeof answer !== 'object') {
                return false;
            }
            return Object.keys(answer).some(function (key) {
                return String(answer[key] || '').trim() !== '';
            });
        }

        if (question.question_type === 'essay') {
            return String(answer || '').trim() !== '';
        }

        return String(answer || '').trim() !== '';
    }

    function isQuestionDoubtful(question) {
        if (!question) {
            return false;
        }
        return !!state.doubtful[question.id];
    }

    function isQuestionChanged(question) {
        if (!question) {
            return false;
        }
        return !!(state.changedQuestionLookup && state.changedQuestionLookup[question.id]);
    }

    function captureRevisionSafeAnswerForQuestion(questionId) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || !Object.prototype.hasOwnProperty.call(state.answers, safeQuestionId)) {
            return null;
        }

        var question = getQuestionById(safeQuestionId);
        if (!question) {
            return null;
        }

        var answer = state.answers[safeQuestionId];
        var questionType = String(question.question_type || '');
        var questionUpdatedAt = String(question && question.updated_at ? question.updated_at : '').trim();

        if (questionType === 'multiple_choice' || questionType === 'true_false') {
            var singleOptionKey = findQuestionOptionKeyById(question, answer);
            if (singleOptionKey === '') {
                return null;
            }
            return {
                kind: 'option_single',
                option_key: singleOptionKey,
                question_updated_at: questionUpdatedAt
            };
        }

        if (questionType === 'multiple_answer') {
            if (!Array.isArray(answer)) {
                return null;
            }

            var selectedOptionKeys = [];
            var seenOptionKeys = {};
            answer.forEach(function (item) {
                var optionKey = findQuestionOptionKeyById(question, item);
                if (optionKey === '' || seenOptionKeys[optionKey]) {
                    return;
                }
                seenOptionKeys[optionKey] = true;
                selectedOptionKeys.push(optionKey);
            });

            if (!selectedOptionKeys.length) {
                return null;
            }

            return {
                kind: 'option_multi',
                option_keys: selectedOptionKeys,
                question_updated_at: questionUpdatedAt
            };
        }

        if (questionType === 'true_false_matrix') {
            var normalizedMatrixAnswer = normalizeAnswerValueForQuestion(question, answer, {
                preserveText: true
            });
            if (!normalizedMatrixAnswer.hasValue) {
                return null;
            }

            return {
                kind: 'true_false_matrix',
                value: normalizedMatrixAnswer.value,
                question_updated_at: questionUpdatedAt
            };
        }

        if (questionType === 'short_answer') {
            var normalizedShortAnswer = normalizeAnswerValueForQuestion(question, answer, {
                preserveText: true
            });
            if (!normalizedShortAnswer.hasValue) {
                return null;
            }

            return {
                kind: 'short_answer',
                value: normalizedShortAnswer.value,
                question_updated_at: questionUpdatedAt
            };
        }

        var normalizedTextAnswer = normalizeAnswerValueForQuestion(question, answer, {
            preserveText: true
        });
        if (!normalizedTextAnswer.hasValue) {
            return null;
        }

        return {
            kind: 'text',
            value: normalizedTextAnswer.value,
            question_updated_at: questionUpdatedAt
        };
    }

    function captureRevisionSafeLocalAnswers() {
        return Object.keys(state.answers || {}).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            var preservedAnswer = captureRevisionSafeAnswerForQuestion(questionId);
            if (questionId > 0 && preservedAnswer) {
                accumulator[questionId] = preservedAnswer;
            }
            return accumulator;
        }, {});
    }

    function restoreRevisionSafeAnswerForQuestion(question, preservedAnswer) {
        var questionId = Number(question && question.id) || 0;
        if (questionId <= 0 || !preservedAnswer || typeof preservedAnswer !== 'object') {
            return false;
        }

        var preservedUpdatedAt = String(preservedAnswer.question_updated_at || '').trim();
        var currentUpdatedAt = String(question && question.updated_at ? question.updated_at : '').trim();
        if (preservedUpdatedAt !== '' && currentUpdatedAt !== '' && preservedUpdatedAt !== currentUpdatedAt) {
            delete state.answers[questionId];
            delete state.answeredQuestionLookup[questionId];
            return false;
        }

        var nextValue = null;
        var hasValue = false;
        var kind = String(preservedAnswer.kind || '');

        if (kind === 'option_single') {
            var selectedOption = findQuestionOptionByKey(question, preservedAnswer.option_key);
            nextValue = Number(selectedOption && selectedOption.id) || 0;
            hasValue = nextValue > 0;
        } else if (kind === 'option_multi') {
            var selectedOptionIds = [];
            var seenOptionIds = {};
            var optionKeys = Array.isArray(preservedAnswer.option_keys) ? preservedAnswer.option_keys : [];
            optionKeys.forEach(function (item) {
                var option = findQuestionOptionByKey(question, item);
                var optionId = Number(option && option.id) || 0;
                if (optionId <= 0 || seenOptionIds[optionId]) {
                    return;
                }
                seenOptionIds[optionId] = true;
                selectedOptionIds.push(optionId);
            });
            nextValue = selectedOptionIds;
            hasValue = selectedOptionIds.length > 0;
        } else if (kind === 'true_false_matrix' || kind === 'short_answer' || kind === 'text') {
            var normalizedAnswer = normalizeAnswerValueForQuestion(
                question,
                preservedAnswer.value,
                {
                    preserveText: true
                }
            );
            nextValue = normalizedAnswer.value;
            hasValue = normalizedAnswer.hasValue;
        }

        if (!hasValue) {
            delete state.answers[questionId];
            delete state.answeredQuestionLookup[questionId];
            return false;
        }

        state.answers[questionId] = nextValue;
        state.answeredQuestionLookup[questionId] = true;
        return true;
    }

    function restoreRevisionSafeLocalAnswers(preservedAnswers, options) {
        options = options || {};
        var shouldDeferMissingQuestions = options.deferMissing !== false;
        var restoredQuestionIds = [];

        if (!preservedAnswers || typeof preservedAnswers !== 'object') {
            return restoredQuestionIds;
        }

        Object.keys(preservedAnswers).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId <= 0) {
                return;
            }

            var question = getQuestionById(questionId);
            if (!question) {
                if (shouldDeferMissingQuestions) {
                    pendingRevisionSafeAnswerRestoreByQuestion[questionId] = preservedAnswers[key];
                }
                return;
            }

            if (restoreRevisionSafeAnswerForQuestion(question, preservedAnswers[key])) {
                restoredQuestionIds.push(questionId);
            }

            delete pendingRevisionSafeAnswerRestoreByQuestion[questionId];
        });

        return restoredQuestionIds;
    }

    function applyPendingRevisionSafeAnswersForLoadedQuestions(questions) {
        if (!Array.isArray(questions) || !questions.length) {
            return [];
        }

        var restoredQuestionIds = [];
        questions.forEach(function (question) {
            var questionId = Number(question && question.id) || 0;
            if (questionId <= 0 || !Object.prototype.hasOwnProperty.call(pendingRevisionSafeAnswerRestoreByQuestion, questionId)) {
                return;
            }

            if (restoreRevisionSafeAnswerForQuestion(question, pendingRevisionSafeAnswerRestoreByQuestion[questionId])) {
                restoredQuestionIds.push(questionId);
            }

            delete pendingRevisionSafeAnswerRestoreByQuestion[questionId];
        });

        return restoredQuestionIds;
    }

    function normalizeNavigationQuestionFilter(filter) {
        var normalizedFilter = String(filter || NAV_QUESTION_FILTER_ALL).trim().toLowerCase();
        if (
            normalizedFilter === NAV_QUESTION_FILTER_ANSWERED
            || normalizedFilter === NAV_QUESTION_FILTER_UNANSWERED
            || normalizedFilter === NAV_QUESTION_FILTER_DOUBTFUL
        ) {
            return normalizedFilter;
        }
        return NAV_QUESTION_FILTER_ALL;
    }

    function navigationQuestionFilterEmptyMessage(filter) {
        var normalizedFilter = normalizeNavigationQuestionFilter(filter);
        if (normalizedFilter === NAV_QUESTION_FILTER_ANSWERED) {
            return 'Belum ada soal yang terjawab.';
        }
        if (normalizedFilter === NAV_QUESTION_FILTER_UNANSWERED) {
            return 'Semua soal sudah terjawab.';
        }
        if (normalizedFilter === NAV_QUESTION_FILTER_DOUBTFUL) {
            return 'Belum ada soal yang ditandai ragu-ragu.';
        }
        return 'Belum ada soal yang bisa ditampilkan.';
    }

    function questionMatchesNavigationFilter(question, filter) {
        var normalizedFilter = normalizeNavigationQuestionFilter(filter);
        if (normalizedFilter === NAV_QUESTION_FILTER_ALL) {
            return true;
        }
        if (!question) {
            return false;
        }
        if (normalizedFilter === NAV_QUESTION_FILTER_ANSWERED) {
            return isQuestionAnswered(question);
        }
        if (normalizedFilter === NAV_QUESTION_FILTER_UNANSWERED) {
            return !isQuestionAnswered(question);
        }
        if (normalizedFilter === NAV_QUESTION_FILTER_DOUBTFUL) {
            return isQuestionDoubtful(question);
        }
        return true;
    }

    function getNavigationQuestionEntries(filter) {
        var navigationQuestionIds = Array.isArray(state.questionOrderIds) && state.questionOrderIds.length
            ? state.questionOrderIds
            : state.questions.map(function (question) { return Number(question && question.id) || 0; });

        return navigationQuestionIds.reduce(function (accumulator, questionId, index) {
            var question = getQuestionById(questionId);
            if (!question || !questionMatchesNavigationFilter(question, filter)) {
                return accumulator;
            }
            accumulator.push({
                questionId: questionId,
                index: index,
                question: question
            });
            return accumulator;
        }, []);
    }

    function getExamProgressSummary() {
        var totalQuestions = getQuestionCount();
        var answeredQuestions = 0;
        var doubtfulQuestions = 0;

        var summaryQuestionIds = Array.isArray(state.questionOrderIds) && state.questionOrderIds.length
            ? state.questionOrderIds
            : (Array.isArray(state.questionManifest) && state.questionManifest.length
                ? state.questionManifest.map(function (question) { return Number(question && question.id) || 0; })
                : state.questions.map(function (question) { return Number(question && question.id) || 0; }));

        summaryQuestionIds.forEach(function (questionId) {
            var question = getQuestionById(questionId);
            if (!question) {
                return;
            }
            if (isQuestionAnswered(question)) {
                answeredQuestions += 1;
            }
            if (isQuestionDoubtful(question)) {
                doubtfulQuestions += 1;
            }
        });

        var unansweredQuestions = Math.max(0, totalQuestions - answeredQuestions);
        var answeredPercentage = totalQuestions > 0 ? (answeredQuestions / totalQuestions) * 100 : 0;

        return {
            totalQuestions: totalQuestions,
            answeredQuestions: answeredQuestions,
            unansweredQuestions: unansweredQuestions,
            doubtfulQuestions: doubtfulQuestions,
            answeredPercentage: answeredPercentage
        };
    }

    function openFinishConfirmModal() {
        if (state.stage !== 'exam' || state.isFinishing) {
            return;
        }
        state.finishConfirmSummary = getExamProgressSummary();
        state.finishConfirmOpen = true;
        clearMessages();
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

    function getNavigationAnswerKeys(question) {
        if (!question) {
            return [];
        }

        var type = String(question.question_type || '');
        var options = Array.isArray(question.options) ? question.options : [];
        var answer = resolveStoredAnswerValueForQuestion(question);

        if (type === 'multiple_choice') {
            var selectedOptionId = Number(answer) || 0;
            if (selectedOptionId <= 0) {
                return [];
            }

            for (var i = 0; i < options.length; i++) {
                var option = options[i];
                if (Number(option.id) === selectedOptionId) {
                    var singleKey = String(questionOptionKey(option, i) || '').toUpperCase();
                    return singleKey !== '' ? [singleKey] : [];
                }
            }

            return [];
        }

        if (type === 'true_false') {
            var selectedTrueFalseOptionId = Number(answer) || 0;
            if (selectedTrueFalseOptionId <= 0) {
                return [];
            }

            for (var tfIndex = 0; tfIndex < options.length; tfIndex++) {
                var trueFalseOption = options[tfIndex];
                if (Number(trueFalseOption && trueFalseOption.id) !== selectedTrueFalseOptionId) {
                    continue;
                }

                var rawText = String(trueFalseOption && trueFalseOption.option_text ? trueFalseOption.option_text : '')
                    .trim()
                    .toLowerCase();
                if (rawText === 'true' || rawText === '1' || rawText === 't' || rawText === 'benar' || rawText === 'ya' || rawText === 'yes') {
                    return ['TRUE'];
                }
                if (rawText === 'false' || rawText === '0' || rawText === 'f' || rawText === 'salah' || rawText === 'tidak' || rawText === 'no') {
                    return ['FALSE'];
                }

                var fallbackKey = String(questionOptionKey(trueFalseOption, tfIndex) || '').toUpperCase();
                return fallbackKey !== '' ? [fallbackKey] : [];
            }

            return [];
        }

        if (type === 'multiple_answer') {
            var selectedOptionIds = Array.isArray(answer)
                ? answer.map(function (item) { return Number(item) || 0; }).filter(function (item) { return item > 0; })
                : [];

            if (!selectedOptionIds.length) {
                return [];
            }

            var selectedLookup = {};
            selectedOptionIds.forEach(function (id) {
                selectedLookup[id] = true;
            });

            var keys = [];
            for (var j = 0; j < options.length; j++) {
                var multiOption = options[j];
                var multiOptionId = Number(multiOption && multiOption.id) || 0;
                if (multiOptionId <= 0 || !selectedLookup[multiOptionId]) {
                    continue;
                }
                var key = String(questionOptionKey(multiOption, j) || '').toUpperCase();
                if (key !== '' && keys.indexOf(key) < 0) {
                    keys.push(key);
                }
            }

            return keys;
        }

        if (type === 'true_false_matrix') {
            var matrixItems = getTrueFalseMatrixItems(question);
            var matrixAnswer = normalizeTrueFalseMatrixAnswer(answer);
            var answeredCount = matrixItems.reduce(function (acc, item) {
                var value = String(matrixAnswer[item.key] || '').trim().toLowerCase();
                return acc + ((value === 'true' || value === 'false') ? 1 : 0);
            }, 0);
            if (!matrixItems.length || answeredCount <= 0) {
                return [];
            }
            return [String(answeredCount) + '/' + String(matrixItems.length)];
        }

        return [];
    }

    function renderNavigationAnswerBadges(question) {
        var keys = getNavigationAnswerKeys(question);
        if (!keys.length) {
            return '';
        }

        var visibleKeys = [];
        if (keys.length <= 3) {
            visibleKeys = keys.slice(0);
        } else {
            visibleKeys = [keys[0], keys[1], ('+' + (keys.length - 2))];
        }

        return [
            '<span class="cbt-nav-answer-badges">',
            visibleKeys.map(function (key) {
                var keyText = String(key || '').trim();
                var badgeClass = 'cbt-nav-answer-badge' + (keyText.length > 2 ? ' is-long' : '');
                return '<span class="' + badgeClass + '">' + escapeHtml(keyText) + '</span>';
            }).join(''),
            '</span>'
        ].join('');
    }

    function renderNavigationQuestionTypeBadge(question) {
        var config = navigationQuestionTypeBadgeConfig(question && question.question_type);
        return '<span class="cbt-nav-type-badge ' + escapeHtml(config.className) + '">' + escapeHtml(config.code) + '</span>';
    }

    function questionAnswerPayload(question) {
        if (!question) {
            return null;
        }

        var answer = resolveStoredAnswerValueForQuestion(question);

        if (question.question_type === 'multiple_choice' || question.question_type === 'true_false') {
            var selected = Number(answer) || 0;
            return selected > 0 ? selected : null;
        }

        if (question.question_type === 'multiple_answer') {
            if (!Array.isArray(answer) || !answer.length) {
                return null;
            }
            var cleaned = answer
                .map(function (item) { return Number(item) || 0; })
                .filter(function (item) { return item > 0; });
            if (!cleaned.length) {
                return null;
            }
            var seen = {};
            return cleaned.filter(function (item) {
                if (seen[item]) {
                    return false;
                }
                seen[item] = true;
                return true;
            });
        }

        if (question.question_type === 'true_false_matrix') {
            var matrixAnswer = normalizeTrueFalseMatrixAnswer(answer);
            if (!Object.keys(matrixAnswer).length) {
                return null;
            }
            return matrixAnswer;
        }

        if (question.question_type === 'short_answer') {
            if (!answer || typeof answer !== 'object') {
                return null;
            }
            var payload = {};
            var keys = getShortAnswerKeys(question);
            keys.forEach(function (key) {
                var raw = String(answer[key] || '').trim();
                if (raw !== '') {
                    payload['input_' + key.toLowerCase()] = raw;
                }
            });
            if (!Object.keys(payload).length) {
                return null;
            }
            return payload;
        }

        var textValue = String(answer || '').trim();
        return textValue !== '' ? textValue : null;
    }

    function payloadSignature(payload) {
        if (payload === null || payload === undefined) {
            return '';
        }
        if (Array.isArray(payload) || typeof payload === 'object') {
            try {
                return JSON.stringify(payload);
            } catch (error) {
                return String(payload);
            }
        }
        return String(payload);
    }

    function clearAllAutoSaveTimers() {
        Object.keys(autoSaveTimersByQuestion).forEach(function (key) {
            var timerId = autoSaveTimersByQuestion[key];
            if (timerId) {
                window.clearTimeout(timerId);
            }
            delete autoSaveTimersByQuestion[key];
        });
    }

    function clearAnswerBatchFlushTimer() {
        if (answerBatchFlushTimer) {
            window.clearTimeout(answerBatchFlushTimer);
        }
        answerBatchFlushTimer = 0;
        answerBatchFlushDueAt = 0;
    }

    function clearAutoSaveRuntimeState() {
        clearAllAutoSaveTimers();
        clearAnswerBatchFlushTimer();
        autoSaveCongestedUntil = 0;
        lastSubmittedPayloadByQuestion = {};
        pendingAnswerBatchByQuestion = {};
        pendingAnswerBatchOrder = [];
        answerBatchFlushInFlight = null;
    }

    function initializeSubmittedPayloadCache() {
        lastSubmittedPayloadByQuestion = {};
        primeSubmittedPayloadCacheFromQuestionItems(Object.keys(state.questionPayloadById).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            var question = getQuestionPayloadById(questionId);
            if (question) {
                accumulator.push(question);
            }
            return accumulator;
        }, []));
    }

    function queueLoadedQuestionAnswersForFlush() {
        var queuedCount = 0;

        Object.keys(state.questionPayloadById).forEach(function (key) {
            var questionId = Number(key) || 0;
            var question = getQuestionPayloadById(questionId);
            if (!question) {
                return;
            }

            if (queueQuestionAnswer(question)) {
                queuedCount += 1;
            }
        });

        return queuedCount;
    }

    function queueQuestionAnswersByIds(questionIds) {
        if (!Array.isArray(questionIds) || !questionIds.length) {
            return 0;
        }

        var queuedCount = 0;
        questionIds.forEach(function (item) {
            var questionId = Number(item) || 0;
            var question = getQuestionById(questionId);
            if (question && queueQuestionAnswer(question)) {
                queuedCount += 1;
            }
        });

        return queuedCount;
    }

    function findQuestionById(questionId) {
        return getQuestionById(questionId);
    }

    function isAutoSaveCongested() {
        return autoSaveCongestedUntil > Date.now();
    }

    function markAutoSaveCongested() {
        autoSaveCongestedUntil = Date.now() + AUTO_SAVE_CONGESTED_WINDOW_MS;
    }

    function resolveAutoSaveDelay(delayMs) {
        var waitMs = Math.max(0, Number(delayMs) || 0);
        if (!isAutoSaveCongested()) {
            return waitMs;
        }

        if (waitMs <= AUTO_SAVE_CHOICE_DELAY_MS) {
            return Math.max(waitMs, AUTO_SAVE_CHOICE_DELAY_CONGESTED_MS);
        }

        return Math.max(waitMs, AUTO_SAVE_TEXT_DELAY_CONGESTED_MS);
    }

    function scheduleAnswerBatchFlush(delayMs) {
        if (isQuestionRevisionRefreshActive()) {
            return;
        }

        var waitMs = Math.max(0, Number(delayMs) || 0);
        var nextDueAt = Date.now() + waitMs;
        if (answerBatchFlushTimer && answerBatchFlushDueAt > 0 && answerBatchFlushDueAt <= nextDueAt) {
            return;
        }

        clearAnswerBatchFlushTimer();
        answerBatchFlushDueAt = nextDueAt;
        answerBatchFlushTimer = window.setTimeout(function () {
            clearAnswerBatchFlushTimer();
            flushPendingAnswerBatch().catch(function () {
                // Error sudah ditangani pada flushPendingAnswerBatch.
            });
        }, waitMs);
    }

    function queueQuestionAnswer(question, options) {
        options = options || {};
        var questionId = Number(question && question.id) || 0;
        if (questionId <= 0 || state.attemptId <= 0) {
            return false;
        }

        var payload = questionAnswerPayload(question);
        var signature = payloadSignature(payload);
        var hasPreviousSubmission = Object.prototype.hasOwnProperty.call(lastSubmittedPayloadByQuestion, questionId);
        var hasPendingItem = Object.prototype.hasOwnProperty.call(pendingAnswerBatchByQuestion, questionId);
        if (payload === null && !hasPreviousSubmission && !hasPendingItem) {
            return false;
        }
        if (payload !== null && signature !== '' && lastSubmittedPayloadByQuestion[questionId] === signature && !hasPendingItem) {
            return false;
        }

        pendingAnswerBatchByQuestion[questionId] = {
            question_id: questionId,
            answer: payload,
            signature: signature
        };

        if (pendingAnswerBatchOrder.indexOf(questionId) < 0) {
            pendingAnswerBatchOrder.push(questionId);
        }

        return true;
    }

    function takePendingAnswerBatchItems(maxItems) {
        var limit = Math.max(1, Number(maxItems) || AUTO_SAVE_BATCH_MAX_ITEMS);
        var items = [];

        while (pendingAnswerBatchOrder.length && items.length < limit) {
            var questionId = Number(pendingAnswerBatchOrder.shift()) || 0;
            if (questionId <= 0 || !Object.prototype.hasOwnProperty.call(pendingAnswerBatchByQuestion, questionId)) {
                continue;
            }

            items.push(pendingAnswerBatchByQuestion[questionId]);
            delete pendingAnswerBatchByQuestion[questionId];
        }

        return items;
    }

    function requeuePendingAnswerBatchItems(items) {
        if (!Array.isArray(items) || !items.length) {
            return;
        }

        for (var i = items.length - 1; i >= 0; i--) {
            var item = items[i];
            var questionId = Number(item && item.question_id) || 0;
            if (questionId <= 0) {
                continue;
            }

            if (!Object.prototype.hasOwnProperty.call(pendingAnswerBatchByQuestion, questionId)) {
                pendingAnswerBatchByQuestion[questionId] = item;
            }
            if (pendingAnswerBatchOrder.indexOf(questionId) < 0) {
                pendingAnswerBatchOrder.unshift(questionId);
            }
        }
    }

    function applySubmittedBatchItems(items, responseItems, options) {
        options = options || {};
        var requestGeneration = Number(options.questionDataGeneration);
        if (Number.isFinite(requestGeneration) && requestGeneration !== questionDataGeneration) {
            return;
        }

        var responseByQuestion = {};
        if (Array.isArray(responseItems)) {
            responseItems.forEach(function (responseItem) {
                var questionId = Number(responseItem && responseItem.question_id) || 0;
                if (questionId > 0) {
                    responseByQuestion[questionId] = responseItem;
                }
            });
        }

        items.forEach(function (item) {
            var questionId = Number(item && item.question_id) || 0;
            if (questionId <= 0) {
                return;
            }

            if (String(item.signature || '') === '') {
                delete lastSubmittedPayloadByQuestion[questionId];
            } else {
                lastSubmittedPayloadByQuestion[questionId] = String(item.signature || '');
            }

            if (responseByQuestion[questionId] && Number(responseByQuestion[questionId].deferred) === 1) {
                markAutoSaveCongested();
            }
        });
    }

    async function submitLegacyAnswerBatch(items, options) {
        options = options || {};
        var responseItems = [];

        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var legacyPayload = await api('submit_answer', {
                method: 'POST',
                keepalive: !!options.keepalive,
                body: {
                    attempt_id: state.attemptId,
                    question_id: item.question_id,
                    answer: item.answer
                }
            });

            responseItems.push({
                question_id: Number(item.question_id) || 0,
                is_correct: legacyPayload && Object.prototype.hasOwnProperty.call(legacyPayload, 'is_correct')
                    ? legacyPayload.is_correct
                    : null,
                score_awarded: Number(legacyPayload && legacyPayload.score_awarded !== undefined ? legacyPayload.score_awarded : 0),
                deferred: Number(legacyPayload && legacyPayload.deferred !== undefined ? legacyPayload.deferred : 0),
                cleared: Number(legacyPayload && legacyPayload.cleared !== undefined ? legacyPayload.cleared : 0)
            });
        }

        return {
            attempt_id: state.attemptId,
            accepted_count: items.length,
            buffered: 0,
            flushed: items.length,
            pending_count: pendingAnswerBatchOrder.length,
            items: responseItems
        };
    }

    async function sendAnswerBatch(items, options) {
        options = options || {};
        var requestGeneration = Number(options.questionDataGeneration);

        try {
            var batchResponse = await api('submit_answers_batch', {
                method: 'POST',
                keepalive: !!options.keepalive,
                body: {
                    attempt_id: state.attemptId,
                    answers: items.map(function (item) {
                        return {
                            question_id: item.question_id,
                            answer: item.answer
                        };
                    })
                }
            });

            applySubmittedBatchItems(items, batchResponse && Array.isArray(batchResponse.items) ? batchResponse.items : [], {
                questionDataGeneration: requestGeneration
            });
            if (
                requestGeneration === questionDataGeneration &&
                typeof state.error === 'string' &&
                state.error.indexOf('Autosave') === 0
            ) {
                state.error = '';
            }
            return batchResponse;
        } catch (batchError) {
            markAutoSaveCongested();

            try {
                var legacyResponse = await submitLegacyAnswerBatch(items, options);
                applySubmittedBatchItems(items, legacyResponse.items || [], {
                    questionDataGeneration: requestGeneration
                });
                if (requestGeneration === questionDataGeneration) {
                    state.error = 'Autosave batch melambat. Sistem memakai mode aman sementara.';
                }
                return legacyResponse;
            } catch (legacyError) {
                if (requestGeneration === questionDataGeneration) {
                    requeuePendingAnswerBatchItems(items);
                }
                throw (legacyError instanceof Error) ? legacyError : batchError;
            }
        }
    }

    async function flushPendingAnswerBatch(options) {
        options = options || {};
        var keepalive = !!options.keepalive;
        var flushAll = !!options.flushAll;
        var requestGeneration = Number.isFinite(Number(options.questionDataGeneration))
            ? Number(options.questionDataGeneration)
            : questionDataGeneration;

        clearAnswerBatchFlushTimer();

        while (true) {
            if (requestGeneration !== questionDataGeneration) {
                return {
                    attempt_id: state.attemptId,
                    accepted_count: 0,
                    buffered: 0,
                    flushed: 0,
                    pending_count: pendingAnswerBatchOrder.length,
                    items: []
                };
            }

            if (answerBatchFlushInFlight) {
                await answerBatchFlushInFlight;
                if (!flushAll || pendingAnswerBatchOrder.length <= 0) {
                    return {
                        attempt_id: state.attemptId,
                        accepted_count: 0,
                        buffered: 0,
                        flushed: 0,
                        pending_count: pendingAnswerBatchOrder.length,
                        items: []
                    };
                }
            }

            var items = takePendingAnswerBatchItems(AUTO_SAVE_BATCH_MAX_ITEMS);
            if (!items.length) {
                return {
                    attempt_id: state.attemptId,
                    accepted_count: 0,
                    buffered: 0,
                    flushed: 0,
                    pending_count: pendingAnswerBatchOrder.length,
                    items: []
                };
            }

            answerBatchFlushInFlight = sendAnswerBatch(items, {
                keepalive: keepalive,
                questionDataGeneration: requestGeneration
            });

            var result;
            try {
                result = await answerBatchFlushInFlight;
            } catch (error) {
                if (requestGeneration === questionDataGeneration) {
                    state.error = error instanceof Error ? ('Autosave gagal: ' + error.message) : 'Autosave gagal. Coba cek jaringan.';
                    render();
                }
                throw error;
            } finally {
                answerBatchFlushInFlight = null;
            }

            if (!flushAll || pendingAnswerBatchOrder.length <= 0) {
                if (pendingAnswerBatchOrder.length > 0) {
                    scheduleAnswerBatchFlush(300);
                }
                return result;
            }
        }
    }

    function scheduleAutoSave(questionId, delayMs) {
        var qid = Number(questionId) || 0;
        if (qid <= 0 || state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing || isQuestionRevisionRefreshActive()) {
            return;
        }

        if (autoSaveTimersByQuestion[qid]) {
            window.clearTimeout(autoSaveTimersByQuestion[qid]);
            delete autoSaveTimersByQuestion[qid];
        }

        var waitMs = resolveAutoSaveDelay(delayMs);
        autoSaveTimersByQuestion[qid] = window.setTimeout(function () {
            delete autoSaveTimersByQuestion[qid];
            runAutoSave(qid);
        }, waitMs);
    }

    async function runAutoSave(questionId, options) {
        options = options || {};
        var qid = Number(questionId) || 0;
        var requestGeneration = Number.isFinite(Number(options.questionDataGeneration))
            ? Number(options.questionDataGeneration)
            : questionDataGeneration;
        if (
            qid <= 0
            || state.stage !== 'exam'
            || state.attemptId <= 0
            || state.isFinishing
            || isQuestionRevisionRefreshActive()
            || requestGeneration !== questionDataGeneration
        ) {
            return;
        }

        var question = findQuestionById(qid);
        if (!question) {
            return;
        }

        try {
            var queued = queueQuestionAnswer(question, {
                force: !!options.force
            });
            if (!queued && !options.immediate) {
                return;
            }

            if (options.immediate) {
                await flushPendingAnswerBatch({
                    flushAll: true,
                    keepalive: !!options.keepalive,
                    questionDataGeneration: requestGeneration
                });
            } else if (queued) {
                scheduleAnswerBatchFlush(150);
            }

            if (
                requestGeneration === questionDataGeneration &&
                typeof state.error === 'string' &&
                state.error.indexOf('Autosave gagal') === 0
            ) {
                state.error = '';
            }
        } catch (error) {
            if (requestGeneration !== questionDataGeneration) {
                return;
            }
            state.error = error instanceof Error ? ('Autosave gagal: ' + error.message) : 'Autosave gagal. Coba cek jaringan.';
            markAutoSaveCongested();
            if (!options.immediate) {
                scheduleAutoSave(qid, 2600);
            } else {
                throw error;
            }
        }
    }

    async function submitQuestionAnswer(question, options) {
        options = options || {};
        await runAutoSave(Number(question && question.id) || 0, {
            force: !!options.force,
            immediate: true,
            keepalive: !!options.keepalive
        });
    }

    function startTimer() {
        stopTimer();

        if (state.remainingSeconds <= 0) {
            state.remainingSeconds = 0;
            updateTimerLabel();
            return;
        }

        state.timerId = window.setInterval(function () {
            if (state.stage !== 'exam') {
                stopTimer();
                return;
            }

            if (state.remainingSeconds <= 0) {
                state.remainingSeconds = 0;
                updateTimerLabel();
                stopTimer();
                if (!state.isFinishing) {
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
            window.clearInterval(state.timerId);
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
        questionRevisionRefreshInFlight = null;
        bumpQuestionDataGeneration();
        lastAttemptUiStateSyncSignature = '';
        state.examPickerMobileOpen = false;
        state.examToken = '';
        state.attemptId = 0;
        resetQuestionDataState();
        state.remainingSeconds = 0;
        state.isFinishing = false;
        state.result = null;
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        state.userPhotoModalOpen = false;
        state.calculatorVisible = false;
        state.calculatorExpression = '';
        state.calculatorResult = '';
        state.calculatorError = '';
        state.isFullscreenActive = false;
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
        attemptUiStateSyncInFlight = null;
        lastAttemptUiStateSyncSignature = '';
        clearPendingRevisionSafeAnswerRestoreState();
        questionRevisionRefreshInFlight = null;
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
        state.result = null;
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        state.userPhotoModalOpen = false;
        state.calculatorVisible = false;
        state.calculatorExpression = '';
        state.calculatorResult = '';
        state.calculatorError = '';
        state.isFullscreenActive = false;
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

        if (state.stage === 'exam' && state.attemptId > 0 && !state.isFinishing) {
            state.busy = true;
            clearMessages();
            render();

            try {
                queueLoadedQuestionAnswersForFlush();
                await flushPendingAnswerBatch({
                    flushAll: true,
                    keepalive: true
                });
                await flushAttemptUiState({
                    force: true,
                    keepalive: true,
                    token: activeToken
                });
            } catch (error) {
                state.busy = false;
                state.error = error instanceof Error
                    ? ('Logout dibatalkan karena jawaban terakhir belum tersimpan: ' + error.message)
                    : 'Logout dibatalkan karena jawaban terakhir belum tersimpan.';
                render();
                return;
            }
        }

        sendLogoutRequestSilently(activeToken);
        clearAuthenticatedFrontendState({
            stage: 'login'
        });
        render();
    }

    async function loadExams() {
        var payload = await api('exams');
        state.exams = Array.isArray(payload.items) ? payload.items : [];
        state.examPickerMobileOpen = false;
        var currentUserPayload = payload && typeof payload === 'object' && payload.current_user && typeof payload.current_user === 'object'
            ? payload.current_user
            : null;
        if (currentUserPayload && state.user && Number(currentUserPayload.user_id || 0) === Number(state.user.user_id || 0)) {
            var nextDisplayName = Object.prototype.hasOwnProperty.call(currentUserPayload, 'display_name')
                ? String(currentUserPayload.display_name || '')
                : String(state.user.display_name || '');
            var nextUsername = Object.prototype.hasOwnProperty.call(currentUserPayload, 'username')
                ? String(currentUserPayload.username || '')
                : String(state.user.username || '');
            var nextEmail = Object.prototype.hasOwnProperty.call(currentUserPayload, 'email')
                ? String(currentUserPayload.email || '')
                : String(state.user.email || '');
            var nextRole = Object.prototype.hasOwnProperty.call(currentUserPayload, 'role')
                ? String(currentUserPayload.role || '')
                : String(state.user.role || '');
            var nextKelas = Object.prototype.hasOwnProperty.call(currentUserPayload, 'kode_kelas')
                ? String(currentUserPayload.kode_kelas || '')
                : String(state.user.kode_kelas || '');
            var nextRuang = Object.prototype.hasOwnProperty.call(currentUserPayload, 'kode_ruang')
                ? String(currentUserPayload.kode_ruang || '')
                : String(state.user.kode_ruang || '');
            var nextAgama = Object.prototype.hasOwnProperty.call(currentUserPayload, 'agama')
                ? String(currentUserPayload.agama || '')
                : String(state.user.agama || '');
            var nextFoto = Object.prototype.hasOwnProperty.call(currentUserPayload, 'foto')
                ? String(currentUserPayload.foto || '')
                : String(state.user.foto || '');

            state.user = {
                user_id: Number(state.user.user_id) || 0,
                role: nextRole,
                display_name: nextDisplayName,
                username: nextUsername,
                email: nextEmail,
                kode_kelas: nextKelas,
                kode_ruang: nextRuang,
                agama: nextAgama,
                foto: nextFoto
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
        render();

        try {
            var loginPayload = await api('login', {
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
        } catch (error) {
            state.error = error instanceof Error ? error.message : 'Login gagal.';
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
        clearSecurityLoggingRuntimeState();
        clearQuestionPrefetchRuntimeState();
        clearAttemptUiStateSyncTimer();
        clearPendingRevisionSafeAnswerRestoreState();
        questionRevisionRefreshInFlight = null;
        bumpQuestionDataGeneration();
        lastAttemptUiStateSyncSignature = '';

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
        setQuestionRevision(startPayload && startPayload.question_revision, examId);
        resetQuestionDataState({
            preserveQuestionRevision: true
        });

        var attemptUiStatePayload = null;
        var attemptUiStateRequestFailed = false;
        try {
            var uiStatePayload = await api('ui_state', {
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
                preferredIndex: requestedResumeIndex,
                windowSize: QUESTION_WINDOW_SIZE,
                expectedQuestionRevision: state.questionRevision
            }
        );
        if (restoredQuestionCache) {
            // Resume can reuse order/manifest from cache, but the full question payload should come from server.
            state.questions = [];
            state.questionPayloadById = {};
            state.loadedQuestionWindowOffsets = {};
            state.windowOffset = 0;
            state.windowLimit = 0;
        }
        await loadQuestionWindow(
            questionWindowOffsetForIndex(requestedResumeIndex, QUESTION_WINDOW_SIZE),
            {
                examId: examId,
                attemptId: state.attemptId,
                includeExisting: includeExisting,
                includeAnswerManifest: 1,
                limit: QUESTION_WINDOW_SIZE
            }
        );

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
            limit: QUESTION_WINDOW_SIZE
        });
        initializeSubmittedPayloadCache();
        var queuedCachedAnswerCount = restoredQuestionCache ? queueLoadedQuestionAnswersForFlush() : 0;

        state.stage = 'exam';
        state.error = '';
        state.notice = '';
        state.success = '';
        persistAuthSession();
        persistCurrentAttemptUiStateLocally();
        persistCurrentQuestionCacheLocally();
        scheduleAttemptUiStateSync(ATTEMPT_UI_STATE_SYNC_DELAY_MS);
        if (queuedCachedAnswerCount > 0) {
            scheduleAnswerBatchFlush(700);
        }
        startSessionHeartbeat();
        startTimer();
        resetQuestionPrefetchIdleTimer();
    }

    async function tryResumeExamCandidate(exam) {
        var examId = Number(exam && exam.id) || 0;
        if (examId <= 0) {
            return false;
        }

        try {
            var resumePayload = await api('start_attempt', {
                method: 'POST',
                body: {
                    exam_id: examId,
                    resume_only: 1
                }
            });

            state.selectedExamId = examId;
            await openAttemptSession(exam, resumePayload);
            return true;
        } catch (error) {
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

        var selectedExam = getSelectedExam();
        if (!selectedExam) {
            state.error = 'Pilih exam terlebih dahulu.';
            render();
            return;
        }

        if (selectedExam.is_class_allowed !== undefined && Number(selectedExam.is_class_allowed) !== 1) {
            state.error = 'Exam ini tidak tersedia untuk kelas akun Anda.';
            render();
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
            render();
            return;
        }
        if (tokenInputRequired && submittedToken.length !== EXAM_TOKEN_LENGTH) {
            state.error = 'Token ujian harus 6 karakter (tanpa 0, O, I, L).';
            render();
            return;
        }
        if (!tokenInputRequired) {
            submittedToken = '';
        }

        var shouldExitFullscreenOnFailure = false;
        if (isExamFullscreenRequired()) {
            syncFullscreenState(false);
            shouldExitFullscreenOnFailure = !state.isFullscreenActive;
            var fullscreenReady = await requestExamFullscreen({
                silent: false
            });
            if (!fullscreenReady) {
                render();
                return;
            }
        }

        state.busy = true;
        render();

        try {
            var startPayload = await api('start_attempt', {
                method: 'POST',
                body: {
                    exam_id: Number(selectedExam.id) || 0,
                    exam_token: submittedToken
                }
            });

            await openAttemptSession(selectedExam, startPayload);
        } catch (error) {
            if (shouldExitFullscreenOnFailure) {
                exitFullscreenSilently();
                syncFullscreenState(false);
            }
            state.error = error instanceof Error ? error.message : 'Gagal memulai ujian.';
        } finally {
            state.busy = false;
            render();
        }
    }

    async function handleViewResult() {
        if (state.busy) {
            return;
        }

        clearMessages();

        var selectedExam = getSelectedExam();
        if (!selectedExam) {
            state.error = 'Pilih exam terlebih dahulu.';
            render();
            return;
        }

        var attemptId = Number(selectedExam.latest_attempt_id) || 0;
        if (attemptId <= 0) {
            state.error = 'Hasil ujian untuk exam ini belum tersedia.';
            render();
            return;
        }

        state.busy = true;
        render();

        try {
            var reviewPayload = await api('result', {
                query: {
                    attempt_id: attemptId
                }
            });

            var attemptData = reviewPayload && typeof reviewPayload === 'object'
                ? (reviewPayload.attempt || null)
                : null;
            var score = Number(attemptData && attemptData.score !== undefined ? attemptData.score : selectedExam.latest_attempt_score);
            var maxScore = Number(attemptData && attemptData.max_score !== undefined ? attemptData.max_score : selectedExam.latest_attempt_max_score);
            var percentage = maxScore > 0
                ? ((score / maxScore) * 100)
                : Number(selectedExam.latest_attempt_percentage || 0);

            state.result = {
                attempt_id: attemptId,
                status: String(attemptData && attemptData.status ? attemptData.status : 'completed'),
                score: Number.isFinite(score) ? score : 0,
                max_score: Number.isFinite(maxScore) ? maxScore : 0,
                percentage: Number.isFinite(percentage) ? percentage : 0,
                attempt: attemptData,
                exam: reviewPayload && typeof reviewPayload === 'object'
                    ? (reviewPayload.exam || selectedExam)
                    : selectedExam,
                answers: reviewPayload && typeof reviewPayload === 'object' && Array.isArray(reviewPayload.answers)
                    ? reviewPayload.answers
                    : [],
                review_items: reviewPayload && typeof reviewPayload === 'object' && Array.isArray(reviewPayload.review_items)
                    ? reviewPayload.review_items
                    : [],
                review_summary: reviewPayload && typeof reviewPayload === 'object' && reviewPayload.review_summary && typeof reviewPayload.review_summary === 'object'
                    ? reviewPayload.review_summary
                    : null
            };
            state.stage = 'result';
            state.success = 'Menampilkan hasil ujian.';
            state.error = '';
        } catch (error) {
            state.error = error instanceof Error ? error.message : 'Gagal memuat hasil ujian.';
        } finally {
            state.busy = false;
            render();
        }
    }

    async function goToQuestion(nextIndex) {
        if (state.busy || state.isFinishing) {
            return;
        }

        var questionCount = getQuestionCount();
        if (questionCount <= 0) {
            return;
        }

        var safeIndex = clampQuestionIndex(nextIndex);

        var currentQuestion = getQuestionAtIndex(state.currentIndex);
        if (currentQuestion) {
            var currentQuestionId = Number(currentQuestion.id) || 0;
            if (currentQuestionId > 0) {
                try {
                    await runAutoSave(currentQuestionId, {
                        force: true,
                        immediate: true
                    });
                } catch (error) {
                    render();
                    return;
                }
            }
        }

        var targetQuestionId = getQuestionIdAtIndex(safeIndex);
        var requiresWindowLoad = !isQuestionPayloadLoaded(targetQuestionId);
        if (requiresWindowLoad) {
            state.busy = true;
            render();

            try {
                await ensureQuestionWindowForIndex(safeIndex, {
                    includeExisting: 1,
                    limit: QUESTION_WINDOW_SIZE
                });
            } catch (error) {
                state.busy = false;
                state.error = error instanceof Error ? error.message : 'Gagal memuat soal.';
                render();
                return;
            }

            state.busy = false;
        }

        state.currentIndex = safeIndex;
        setActiveQuestionWindowForIndex(safeIndex, QUESTION_WINDOW_SIZE);
        state.error = '';
        persistCurrentAttemptUiStateLocally();
        scheduleAttemptUiStateSync(ATTEMPT_UI_STATE_NAVIGATION_SYNC_DELAY_MS);
        render();
        prefetchNextQuestionBatch();
        resetQuestionPrefetchIdleTimer();
    }

    async function handleFinish(autoSubmit, options) {
        options = options || {};
        var skipConfirmation = !!options.skipConfirmation;

        if (state.isFinishing) {
            return;
        }

        if (!autoSubmit && !skipConfirmation) {
            openFinishConfirmModal();
            return;
        }

        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        state.isFinishing = true;
        clearAllAutoSaveTimers();
        clearQuestionPrefetchRuntimeState();
        clearAttemptUiStateSyncTimer();
        clearQuestionCachePersistTimer();
        clearMessages();
        render();

        try {
            stopTimer();
            try {
                await flushAttemptUiState({
                    force: true,
                    allowWhileFinishing: true
                });
            } catch (error) {
                // Finishing the exam is higher priority; local fallback remains available.
            }

            for (var i = 0; i < getQuestionCount(); i++) {
                var question = getQuestionAtIndex(i);
                if (!isQuestionAnswered(question)) {
                    continue;
                }
                queueQuestionAnswer(question, { force: true });
            }
            await flushPendingAnswerBatch({ flushAll: true });

            var finishPayload = await api('finish_exam', {
                method: 'POST',
                body: {
                    attempt_id: state.attemptId
                }
            });

            var resolvedAttemptId = Number(finishPayload && finishPayload.attempt_id) || Number(state.attemptId) || 0;
            var resultPayload = {
                attempt_id: resolvedAttemptId,
                status: String(finishPayload && finishPayload.status ? finishPayload.status : 'completed'),
                score: Number(finishPayload && finishPayload.score !== undefined ? finishPayload.score : 0),
                max_score: Number(finishPayload && finishPayload.max_score !== undefined ? finishPayload.max_score : 0),
                percentage: Number(finishPayload && finishPayload.percentage !== undefined ? finishPayload.percentage : 0),
                finished_at: String(finishPayload && finishPayload.finished_at ? finishPayload.finished_at : ''),
                review_items: [],
                review_summary: null
            };

            if (resolvedAttemptId > 0) {
                clearPersistedAttemptUiState(resolvedAttemptId);
                clearPersistedQuestionCache(resolvedAttemptId);
                try {
                    var reviewPayload = await api('result', {
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

            state.result = resultPayload;
            state.stage = 'result';
            exitFullscreenSilently();
            syncFullscreenState(false);
            state.success = autoSubmit ? 'Waktu habis. Ujian otomatis diselesaikan.' : 'Ujian selesai.';
            state.error = '';
        } catch (error) {
            state.error = error instanceof Error ? error.message : 'Gagal menyelesaikan ujian.';
            state.isFinishing = false;
            if (state.stage === 'exam') {
                startTimer();
            }
        } finally {
            render();
        }
    }

    function renderThemeToggleControl() {
        var isDark = state.uiTheme === 'dark';
        var themeLabel = isDark ? 'Tema Terang' : 'Tema Gelap';
        var themeIcon = isDark ? '\u2600' : '\u263E';

        return [
            '<button class="cbt-icon-button cbt-theme-toggle" data-action="toggle-theme" type="button" aria-label="' + escapeHtml(themeLabel) + '" title="' + escapeHtml(themeLabel) + '">',
            '<span class="cbt-theme-toggle-icon" aria-hidden="true">' + escapeHtml(themeIcon) + '</span>',
            '</button>'
        ].join('');
    }

    function renderQuestionFontControls() {
        var canDecrease = state.fontScale > (FONT_SCALE_MIN + 0.001);
        var canIncrease = state.fontScale < (FONT_SCALE_MAX - 0.001);
        var scaleLabel = formatFontScaleLabel(state.fontScale);

        return [
            '<div class="cbt-access-group cbt-question-font-controls" role="group" aria-label="Ukuran Font Soal">',
            '<button class="cbt-icon-button cbt-font-btn cbt-font-btn-dec" data-action="font-dec" type="button" title="Perkecil font" aria-label="Perkecil font"' + (canDecrease ? '' : ' disabled') + '><span class="cbt-font-btn-icon" aria-hidden="true">\u2212</span><span class="cbt-font-btn-label">A-</span></button>',
            '<button class="cbt-icon-button cbt-icon-button-value cbt-font-btn cbt-font-btn-reset" data-action="font-reset" type="button" title="Reset ukuran font (' + escapeHtml(scaleLabel) + ')" aria-label="Reset ukuran font (' + escapeHtml(scaleLabel) + ')"><span class="cbt-font-btn-icon" aria-hidden="true">A</span><span class="cbt-font-btn-label">' + escapeHtml(scaleLabel) + '</span></button>',
            '<button class="cbt-icon-button cbt-font-btn cbt-font-btn-inc" data-action="font-inc" type="button" title="Perbesar font" aria-label="Perbesar font"' + (canIncrease ? '' : ' disabled') + '><span class="cbt-font-btn-icon" aria-hidden="true">+</span><span class="cbt-font-btn-label">A+</span></button>',
            '</div>',
        ].join('');
    }

    function renderNavToggleButton(isOpen, extraClass) {
        var classes = ['cbt-icon-button', 'cbt-nav-toggle'];
        if (isOpen) {
            classes.push('is-open');
        }
        if (extraClass) {
            classes.push(String(extraClass));
        }

        var label = isOpen ? 'Tutup navigasi soal' : 'Buka navigasi soal';

        return [
            '<button class="' + classes.join(' ') + '" data-action="toggle-nav" type="button" aria-label="' + escapeHtml(label) + '" title="' + escapeHtml(label) + '">',
            '<span class="cbt-nav-toggle-icon" aria-hidden="true"><span></span><span></span><span></span></span>',
            '</button>'
        ].join('');
    }

    function renderCalculatorToggleButton(isOpen) {
        var classes = ['cbt-icon-button', 'cbt-calculator-toggle'];
        if (isOpen) {
            classes.push('is-open');
        }

        var label = isOpen ? 'Sembunyikan kalkulator' : 'Tampilkan kalkulator';
        return [
            '<button class="' + classes.join(' ') + '" data-action="toggle-calculator" type="button" aria-label="' + escapeHtml(label) + '" title="' + escapeHtml(label) + '">',
            '<span class="cbt-calculator-toggle-icon" aria-hidden="true">',
            '<span class="cbt-calculator-icon-glyph">',
            '<svg viewBox="0 0 24 24" focusable="false">',
            '<rect x="3" y="2" width="18" height="20" rx="2.8"></rect>',
            '<rect x="6.5" y="5.5" width="11" height="4.3" rx="1.1"></rect>',
            '<rect x="6.5" y="12" width="3.2" height="3.2" rx="0.6"></rect>',
            '<rect x="10.4" y="12" width="3.2" height="3.2" rx="0.6"></rect>',
            '<rect x="14.3" y="12" width="3.2" height="3.2" rx="0.6"></rect>',
            '<rect x="6.5" y="15.9" width="3.2" height="3.2" rx="0.6"></rect>',
            '<rect x="10.4" y="15.9" width="3.2" height="3.2" rx="0.6"></rect>',
            '<rect x="14.3" y="15.9" width="3.2" height="3.2" rx="0.6"></rect>',
            '</svg>',
            '</span>',
            '<span class="cbt-calculator-icon-close"></span>',
            '</span>',
            '<span class="cbt-visually-hidden">' + escapeHtml(label) + '</span>',
            '</button>'
        ].join('');
    }

    function renderCalculatorPositionControl(extraClass) {
        var options = [
            { value: 'top', label: 'Atas', arrow: '\u2191' },
            { value: 'left', label: 'Kiri', arrow: '\u2190' },
            { value: 'right', label: 'Kanan', arrow: '\u2192' },
            { value: 'bottom', label: 'Bawah', arrow: '\u2193' }
        ];
        var activePosition = getEffectiveCalculatorPanelPosition();
        var compactMode = isCompactViewport();
        var groupClass = 'cbt-access-group cbt-calc-position-group';

        if (extraClass) {
            groupClass += ' ' + String(extraClass);
        }

        return [
            '<div class="' + groupClass + '" role="group" aria-label="Posisi Kalkulator">',
            options.map(function (option) {
                var isActive = option.value === activePosition;
                var isDisabled = compactMode && (option.value === 'left' || option.value === 'right');
                var classes = 'cbt-icon-button cbt-calc-position-btn' + (isActive ? ' is-active' : '');
                return '<button class="' + classes + '" data-action="set-calc-position" data-position="' + escapeHtml(option.value) + '" type="button" aria-label="' + escapeHtml(option.label) + '" title="' + escapeHtml(option.label) + '"' + (isActive ? ' aria-pressed="true"' : ' aria-pressed="false"') + (isDisabled ? ' disabled aria-disabled="true"' : '') + '><span aria-hidden="true">' + escapeHtml(option.arrow) + '</span></button>';
            }).join(''),
            '</div>'
        ].join('');
    }

    function renderCalculatorPanel() {
        var panelClass = 'cbt-calc-panel' + (state.calculatorVisible ? '' : ' is-hidden');
        var statusClass = 'cbt-calc-status';
        var statusText = 'Ketik ekspresi lalu =';

        if (state.calculatorError) {
            statusClass += ' is-error';
            statusText = state.calculatorError;
        } else if (state.calculatorResult !== '') {
            statusClass += ' is-result';
            statusText = '= ' + state.calculatorResult;
        }

        return [
            '<aside class="' + panelClass + '" aria-hidden="' + (state.calculatorVisible ? 'false' : 'true') + '">',
            '<div class="cbt-calc-head">',
            '<div class="cbt-calc-head-title-wrap">',
            '<strong class="cbt-calc-head-title">KALKULATOR</strong>',
            '<p class="cbt-calc-head-subtitle">Hitung cepat di soal</p>',
            '</div>',
            '<div class="cbt-calc-head-actions">',
            renderCalculatorPositionControl('cbt-calc-position-group-inline'),
            '<button class="cbt-icon-button cbt-calc-close" data-action="toggle-calculator" type="button" aria-label="Tutup kalkulator" title="Tutup kalkulator"><span class="cbt-calc-close-icon" aria-hidden="true">X</span><span class="cbt-visually-hidden">Tutup kalkulator</span></button>',
            '</div>',
            '</div>',
            '<div class="cbt-calc-display">',
            '<input class="cbt-input cbt-calc-expression" type="text" name="calc_expression" inputmode="decimal" autocomplete="off" spellcheck="false" placeholder="(7+8)*2" value="' + escapeHtml(state.calculatorExpression) + '" />',
            '<div class="' + statusClass + '">' + escapeHtml(statusText) + '</div>',
            '</div>',
            '<div class="cbt-calc-grid" role="group" aria-label="Tombol kalkulator">',
            '<button class="cbt-calc-key cbt-calc-key-util" data-action="calc-clear" type="button">C</button>',
            '<button class="cbt-calc-key cbt-calc-key-util" data-action="calc-backspace" type="button">DEL</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="(" type="button">(</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value=")" type="button">)</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="7" type="button">7</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="8" type="button">8</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="9" type="button">9</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="/" type="button">/</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="4" type="button">4</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="5" type="button">5</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="6" type="button">6</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="*" type="button">*</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="1" type="button">1</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="2" type="button">2</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="3" type="button">3</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="-" type="button">-</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="0" type="button">0</button>',
            '<button class="cbt-calc-key" data-action="calc-key" data-value="." type="button">.</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="%" type="button">%</button>',
            '<button class="cbt-calc-key cbt-calc-key-op" data-action="calc-key" data-value="+" type="button">+</button>',
            '<button class="cbt-calc-key cbt-calc-key-eval" data-action="calc-eval" type="button">=</button>',
            '</div>',
            '</aside>'
        ].join('');
    }

    function renderNavPositionControl(extraClass) {
        var compactMode = isCompactNavViewport();
        var options = compactMode
            ? [
                { value: 'top', label: 'Atas', arrow: '\u2191' },
                { value: 'bottom', label: 'Bawah', arrow: '\u2193' }
            ]
            : [
                { value: 'top', label: 'Atas', arrow: '\u2191' },
                { value: 'left', label: 'Kiri', arrow: '\u2190' },
                { value: 'right', label: 'Kanan', arrow: '\u2192' },
                { value: 'bottom', label: 'Bawah', arrow: '\u2193' }
            ];
        var activePosition = getEffectiveNavPanelPosition();
        var groupClass = 'cbt-access-group cbt-nav-position-group';

        if (extraClass) {
            groupClass += ' ' + String(extraClass);
        }

        return [
            '<div class="' + groupClass + '" role="group" aria-label="Posisi Navigasi Soal">',
            options.map(function (option) {
                var isActive = option.value === activePosition;
                var classes = 'cbt-icon-button cbt-nav-position-btn' + (isActive ? ' is-active' : '');
                return '<button class="' + classes + '" data-action="set-nav-position" data-position="' + escapeHtml(option.value) + '" type="button" aria-label="' + escapeHtml(option.label) + '" title="' + escapeHtml(option.label) + '"' + (isActive ? ' aria-pressed="true"' : ' aria-pressed="false"') + '><span aria-hidden="true">' + escapeHtml(option.arrow) + '</span></button>';
            }).join(''),
            '</div>'
        ].join('');
    }

    function renderTopbar() {
        var userName = getCurrentUserName();
        var userRole = getCurrentUserRole();
        var userPhoto = getCurrentUserPhoto();
        var userInitial = getUserInitial(userName);
        var schoolName = getConfiguredSchoolName();
        var schoolLogoUrl = getConfiguredSchoolLogoUrl();
        var brandBadge = schoolLogoUrl !== ''
            ? '<img class="cbt-brand-badge-logo" src="' + escapeHtml(schoolLogoUrl) + '" alt="' + escapeHtml(schoolName) + '" loading="lazy" decoding="async" />'
            : 'CBT';
        var userChip = [
            '<span class="cbt-chip cbt-user-chip">',
            userPhoto !== ''
                ? '<button class="cbt-user-chip-photo-button" data-action="open-user-photo" type="button" aria-label="Lihat foto profil ukuran besar"><img class="cbt-user-chip-photo" src="' + escapeHtml(userPhoto) + '" alt="' + escapeHtml(userName) + '" loading="lazy" decoding="async" /></button>'
                : '<span class="cbt-user-chip-fallback" aria-hidden="true">' + escapeHtml(userInitial) + '</span>',
            '<button class="cbt-user-chip-name-button" data-action="open-user-photo" type="button" aria-label="Lihat informasi peserta">' + escapeHtml(userName) + ' (' + escapeHtml(userRole) + ')</button>',
            '</span>'
        ].join('');

        var stageLabel = 'Login';
        if (state.stage === 'confirm') {
            stageLabel = 'Konfirmasi Ujian';
        } else if (state.stage === 'exam') {
            stageLabel = 'Sedang Ujian';
        } else if (state.stage === 'result') {
            stageLabel = 'Hasil Ujian';
        }

        var timerChip = '';
        if (state.stage === 'exam') {
            timerChip = [
                '<span class="cbt-chip cbt-timer-chip" aria-live="polite" aria-label="Sisa waktu ujian: ' + escapeHtml(formatSeconds(state.remainingSeconds)) + '">',
                '<span class="cbt-timer-chip-icon" aria-hidden="true">\u23f1</span>',
                '<span data-cbt-timer>' + formatSeconds(state.remainingSeconds) + '</span>',
                '</span>'
            ].join('');
        }

        return [
            '<header class="cbt-topbar">',
            '<div class="cbt-brand">',
            '<span class="cbt-brand-badge">' + brandBadge + '</span>',
            '<div><h2>' + escapeHtml(schoolName) + '</h2><small>' + escapeHtml(stageLabel) + '</small></div>',
            '</div>',
            '<div class="cbt-topbar-right">',
            renderThemeToggleControl(),
            userChip,
            timerChip,
            state.user
                ? '<button class="cbt-button cbt-button-secondary cbt-logout-button" data-action="logout" type="button" aria-label="Logout" title="Logout"><span class="cbt-logout-icon" aria-hidden="true">\u23fb</span><span class="cbt-logout-label">LOGOUT</span></button>'
                : '',
            '</div>',
            '</header>'
        ].join('');
    }

    function renderLoginStage() {
        var schoolNameRaw = getConfiguredSchoolName();
        var schoolName = escapeHtml(schoolNameRaw);
        var schoolBranding = getLoginHeroSchoolBranding(schoolNameRaw);
        var schoolBrandTag = escapeHtml(schoolBranding.tag || 'Portal CBT');
        var schoolBrandTitle = escapeHtml(schoolBranding.title || schoolNameRaw);
        var schoolMottoRaw = getConfiguredSchoolMotto();
        var schoolMotto = schoolMottoRaw !== '' ? escapeHtml(schoolMottoRaw) : '';
        var heroMottoBlock = schoolMotto !== ''
            ? '<div class="cbt-login-hero-motto-row"><span class="cbt-login-hero-motto-line" aria-hidden="true"></span><p class="cbt-login-hero-motto">' + schoolMotto + '</p></div>'
            : '';
        var heroDescription = schoolMotto === ''
            ? 'Masuk dengan akun resmi, pilih ujian yang aktif, lalu kerjakan dengan autosave dan timer yang sinkron dari server.'
            : '';
        var schoolLogoUrl = getConfiguredSchoolLogoUrl();
        var heroLogoBlock = schoolLogoUrl !== ''
            ? '<div class="cbt-login-hero-logo-wrap"><img class="cbt-login-hero-logo" src="' + escapeHtml(schoolLogoUrl) + '" alt="' + schoolName + '" loading="lazy" decoding="async" /></div>'
            : '<div class="cbt-login-hero-logo-wrap is-fallback" aria-hidden="true"><span class="cbt-login-hero-logo-fallback"><svg viewBox="0 0 64 64" focusable="false"><path d="M32 8 47 14v15c0 11-6.8 21.1-15 26-8.2-4.9-15-15-15-26V14L32 8Z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"></path><path d="M32 20v18" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path><path d="M23 29h18" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg></span></div>';
        var mobilePanelLogoBlock = schoolLogoUrl !== ''
            ? '<div class="cbt-login-panel-brand-mobile"><img class="cbt-login-panel-brand-mobile-logo" src="' + escapeHtml(schoolLogoUrl) + '" alt="' + schoolName + '" loading="lazy" decoding="async" /></div>'
            : '';
        var passwordType = state.loginPasswordVisible ? 'text' : 'password';
        var loginButtonClass = state.busy ? 'cbt-button cbt-button-primary cbt-button-login is-loading' : 'cbt-button cbt-button-primary cbt-button-login';
        var loginButtonLabel = state.busy ? 'Memverifikasi...' : 'LOGIN';
        var togglePasswordLabel = state.loginPasswordVisible ? 'Sembunyikan' : 'Tampilkan';
        var pluginAuthorRaw = getConfiguredPluginAuthor();
        var pluginVersionRaw = getConfiguredPluginVersion();
        var loginMetaItems = [];

        if (pluginAuthorRaw !== '') {
            loginMetaItems.push('<span class="cbt-login-meta-item cbt-login-meta-copy">&copy; ' + escapeHtml(pluginAuthorRaw) + '</span>');
        }
        if (pluginVersionRaw !== '') {
            loginMetaItems.push('<span class="cbt-login-meta-item cbt-login-meta-version">Versi ' + escapeHtml(pluginVersionRaw) + '</span>');
        }

        var loginMetaBlock = loginMetaItems.length
            ? '<div class="cbt-login-meta" aria-label="Informasi sistem">' + loginMetaItems.join('') + '</div>'
            : '';

        return [
            '<section class="cbt-login-shell">',
            '<div class="cbt-login-hero">',
            '<div class="cbt-login-hero-heading">',
            heroLogoBlock,
            '<div class="cbt-login-hero-title">',
            '<span class="cbt-login-hero-school-tag">' + schoolBrandTag + '</span>',
            '<h1>' + schoolBrandTitle + '</h1>',
            heroMottoBlock,
            '</div>',
            '</div>',
            heroDescription !== '' ? '<p class="cbt-login-description">' + heroDescription + '</p>' : '',
            '<p class="cbt-login-flow-label">Alur masuk ujian</p>',
            '<div class="cbt-login-steps" aria-label="Alur masuk ke ujian CBT">',
            '<article class="cbt-login-flow-card is-login"><div class="cbt-login-flow-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" focusable="false"><path d="M14 6h2a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M10 8l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg></div><div class="cbt-login-flow-content"><div class="cbt-login-flow-title-row"><span class="cbt-login-flow-number">01</span><strong class="cbt-login-flow-title">Masuk dengan akun resmi</strong></div><p class="cbt-login-flow-desc">Masuk memakai email, username, atau NISN yang terdaftar.</p><div class="cbt-login-flow-tags"><div class="cbt-login-flow-tag">Email / Username / NISN</div><div class="cbt-login-flow-tag">1 akun = 1 sesi</div></div></div></article>',
            '<article class="cbt-login-flow-card is-verify"><div class="cbt-login-flow-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" focusable="false"><rect x="6" y="4.5" width="12" height="15" rx="2.5" stroke="currentColor" stroke-width="1.8"></rect><path d="M9.2 4.5h5.6v2.2H9.2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path><path d="m9.5 12.3 1.7 1.7 3.4-3.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg></div><div class="cbt-login-flow-content"><div class="cbt-login-flow-title-row"><span class="cbt-login-flow-number">02</span><strong class="cbt-login-flow-title">Pilih ujian dan verifikasi token</strong></div><p class="cbt-login-flow-desc">Pilih ujian, lalu isi token hanya jika memang diwajibkan.</p><div class="cbt-login-flow-tags"><div class="cbt-login-flow-tag">Token global / per ujian</div><div class="cbt-login-flow-tag">Sesi aktif bisa dilanjutkan</div></div></div></article>',
            '<article class="cbt-login-flow-card is-submit"><div class="cbt-login-flow-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" focusable="false"><path d="M20 4 10 14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="m20 4-6 16-4-6-6-4 16-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg></div><div class="cbt-login-flow-content"><div class="cbt-login-flow-title-row"><span class="cbt-login-flow-number">03</span><strong class="cbt-login-flow-title">Kerjakan, review, lalu kumpulkan</strong></div><p class="cbt-login-flow-desc">Jawaban autosave aktif; review sebentar lalu kumpulkan.</p><div class="cbt-login-flow-tags"><div class="cbt-login-flow-tag">Autosave aktif</div><div class="cbt-login-flow-tag">Timer sinkron server</div></div></div></article>',
            '</div>',
            '</div>',
            '<div class="cbt-login-panel">',
            '<h3>Masuk ke CBT</h3>',
            mobilePanelLogoBlock,
            '<form id="cbt-login-form" class="cbt-form-grid">',
            '<div class="cbt-field"><label for="cbt-identifier">EMAIL / USERNAME / NISN</label><input id="cbt-identifier" class="cbt-input" name="identifier" autocomplete="username" value="' + escapeHtml(state.loginIdentifier) + '" placeholder="Contoh: 231045 atau siswa@smkn1tpd.sch.id" required /></div>',
            '<div class="cbt-field"><label for="cbt-password">PASSWORD</label><div class="cbt-password-field"><input id="cbt-password" class="cbt-input" name="password" type="' + passwordType + '" autocomplete="current-password" value="' + escapeHtml(state.loginPassword) + '" placeholder="Masukkan password akun" required /><button class="cbt-password-toggle' + (state.loginPasswordVisible ? ' is-visible' : '') + '" data-action="toggle-password" type="button" aria-label="' + togglePasswordLabel + '" title="' + togglePasswordLabel + '"' + (state.busy ? ' disabled' : '') + '><span class="cbt-password-toggle-icon" aria-hidden="true"><span class="cbt-password-toggle-icon-eye"><svg viewBox="0 0 24 24" focusable="false"><path d="M1.5 12S5.5 5.5 12 5.5 22.5 12 22.5 12 18.5 18.5 12 18.5 1.5 12 1.5 12Z"></path><circle cx="12" cy="12" r="3.2"></circle></svg></span><span class="cbt-password-toggle-icon-eye-off"><svg viewBox="0 0 24 24" focusable="false"><path d="M3.2 3.2 20.8 20.8"></path><path d="M9.9 5.9A12.2 12.2 0 0 1 12 5.5c6.5 0 10.5 6.5 10.5 6.5a18.9 18.9 0 0 1-3.4 4.2"></path><path d="M6.4 8A18.3 18.3 0 0 0 1.5 12s4 6.5 10.5 6.5a11.6 11.6 0 0 0 4-.7"></path><path d="M14.3 14.3A3.2 3.2 0 0 1 9.7 9.7"></path></svg></span></span><span class="cbt-password-toggle-label">' + escapeHtml(togglePasswordLabel) + '</span></button></div></div>',
            '<div class="cbt-actions"><button class="' + loginButtonClass + '" type="submit"' + (state.busy ? ' disabled' : '') + '><span class="cbt-button-spinner" aria-hidden="true"></span><span>' + loginButtonLabel + '</span></button></div>',
            '</form>',
            renderAlert(),
            '<p class="cbt-login-help">Jika gagal login, hubungi admin sekolah atau pengawas ujian.</p>',
            loginMetaBlock,
            '</div>',
            '</section>'
        ].join('');
    }

    function renderConfirmStatusPill(label, tone) {
        var classes = ['cbt-confirm-pill'];
        if (tone) {
            classes.push(tone);
        }

        return '<span class="' + classes.join(' ') + '">' + escapeHtml(label || '-') + '</span>';
    }

    function renderConfirmInfoCard(label, value, meta, extraClass) {
        var classes = ['cbt-confirm-fact'];
        var safeValue = value === undefined || value === null || String(value).trim() === '' ? '-' : String(value);
        var safeMeta = meta === undefined || meta === null || String(meta).trim() === '' ? '' : String(meta);

        if (extraClass) {
            classes.push(extraClass);
        }

        return [
            '<div class="' + classes.join(' ') + '">',
            '<span class="cbt-confirm-fact-label">' + escapeHtml(label || '-') + '</span>',
            '<strong class="cbt-confirm-fact-value">' + escapeHtml(safeValue) + '</strong>',
            safeMeta !== '' ? '<small class="cbt-confirm-fact-meta">' + escapeHtml(safeMeta) + '</small>' : '',
            '</div>'
        ].join('');
    }

    function renderRefreshButton(disabled, extraClass) {
        var classes = ['cbt-button', 'cbt-button-secondary', 'cbt-button-refresh'];

        if (extraClass) {
            classes.push(extraClass);
        }

        return [
            '<button class="' + classes.join(' ') + '" data-action="reload-exams" type="button"' + (disabled ? ' disabled' : '') + '>',
            '<span class="cbt-button-refresh-icon" aria-hidden="true">',
            '<svg viewBox="0 0 24 24" focusable="false">',
            '<path d="M20 11a8 8 0 1 0 2 5.3"></path>',
            '<path d="M20 4v7h-7"></path>',
            '</svg>',
            '</span>',
            '<span class="cbt-button-refresh-label">REFRESH</span>',
            '</button>'
        ].join('');
    }

    function formatExamStatusBadgeLabel(status) {
        var normalized = String(status || '').replace(/[_-]+/g, ' ').trim().toLowerCase();
        if (normalized === '') {
            return '-';
        }

        return normalized.replace(/\b[a-z]/g, function (character) {
            return character.toUpperCase();
        });
    }

    function renderExamCardChip(label, iconName, tone) {
        var classes = ['cbt-exam-card-chip'];
        if (tone) {
            classes.push(tone);
        }

        var iconMarkup = '';
        if (iconName === 'calendar') {
            iconMarkup = '<svg viewBox="0 0 24 24" focusable="false"><path d="M7 3v3"></path><path d="M17 3v3"></path><rect x="4" y="5.5" width="16" height="14" rx="2"></rect><path d="M4 9.5h16"></path></svg>';
        } else if (iconName === 'clock') {
            iconMarkup = '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4l2.5 2"></path></svg>';
        } else if (iconName === 'access') {
            iconMarkup = '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8"></circle><path d="M9 12.5l2 2 4-5"></path></svg>';
        } else {
            iconMarkup = '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>';
        }

        return [
            '<span class="' + classes.join(' ') + '">',
            '<span class="cbt-exam-card-chip-icon" aria-hidden="true">' + iconMarkup + '</span>',
            '<span class="cbt-exam-card-chip-label">' + escapeHtml(label || '-') + '</span>',
            '</span>'
        ].join('');
    }

    function formatExamPickerOptionLabel(exam) {
        var examId = Number(exam && exam.id) || 0;
        var title = String(exam && exam.title ? exam.title : '').trim();
        var subject = String(exam && exam.subject_name ? exam.subject_name : '').trim();
        var titleNormalized = title.toLowerCase();
        var subjectNormalized = subject.toLowerCase();
        var label = title;

        if (label === '') {
            label = subject !== '' ? subject : ('Ujian #' + String(examId || '-'));
        } else if (subject !== '' && subjectNormalized !== '' && titleNormalized.indexOf(subjectNormalized) === -1) {
            var combinedLabel = title + ' - ' + subject;
            label = combinedLabel.length <= 44 ? combinedLabel : title;
        }

        if (label.length > 44) {
            label = label.slice(0, 41).trim() + '...';
        }

        return label;
    }

    function renderExamPickerMobileOption(exam) {
        var optionId = Number(exam && exam.id) || 0;
        var isActive = optionId === Number(state.selectedExamId);
        var durationMinutes = Number(exam && exam.duration_minutes) || 0;
        var startsAtLabel = formatDateTimeCompact(exam && exam.starts_at ? exam.starts_at : '');
        var latestAttemptStatus = String(exam && exam.latest_attempt_status ? exam.latest_attempt_status : '').toLowerCase();
        var availableNow = Number(exam && exam.is_available_now ? exam.is_available_now : 0) === 1;
        var classAllowed = Number(exam && exam.is_class_allowed ? exam.is_class_allowed : 0) === 1;
        var withinSchedule = Number(exam && exam.is_within_schedule ? exam.is_within_schedule : 0) === 1;
        var availabilityReason = String(exam && exam.availability_reason ? exam.availability_reason : '');
        var availabilityLabel = 'Siap';
        var availabilityTone = 'is-ready';
        var classes = ['cbt-exam-picker-option'];

        if (isActive) {
            classes.push('is-active');
        }

        if (latestAttemptStatus === 'completed') {
            availabilityLabel = 'Selesai';
            availabilityTone = 'is-completed';
        } else if (latestAttemptStatus === 'in_progress') {
            availabilityLabel = 'Lanjutkan';
            availabilityTone = 'is-progress';
        } else if (!availableNow) {
            availabilityTone = 'is-warn';
            if (!classAllowed) {
                availabilityLabel = 'Kelas';
            } else if (!withinSchedule) {
                if (availabilityReason === 'not_started') {
                    availabilityLabel = 'Belum mulai';
                } else if (availabilityReason === 'ended') {
                    availabilityLabel = 'Berakhir';
                } else {
                    availabilityLabel = 'Jadwal';
                }
            } else {
                availabilityLabel = 'Tutup';
            }
        }

        return [
            '<button type="button" class="' + classes.join(' ') + '" data-action="select-exam-mobile" data-id="' + escapeHtml(optionId) + '" role="option" aria-selected="' + (isActive ? 'true' : 'false') + '"' + (state.busy ? ' disabled' : '') + '>',
            '<span class="cbt-exam-picker-option-main">',
            '<span class="cbt-exam-picker-option-title">' + escapeHtml(formatExamPickerOptionLabel(exam)) + '</span>',
            '<span class="cbt-exam-picker-option-meta">',
            '<span class="cbt-exam-picker-option-chip">' + escapeHtml(startsAtLabel) + '</span>',
            '<span class="cbt-exam-picker-option-chip">' + escapeHtml(String(durationMinutes) + ' menit') + '</span>',
            '<span class="cbt-exam-picker-option-chip ' + availabilityTone + '">' + escapeHtml(availabilityLabel) + '</span>',
            '</span>',
            '</span>',
            '<span class="cbt-exam-picker-option-indicator" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M20 7 10 17l-5-5"></path></svg></span>',
            '</button>'
        ].join('');
    }

    function updateSelectedExam(examId) {
        var normalizedExamId = Number(examId) || 0;
        if (normalizedExamId <= 0) {
            return;
        }

        state.examPickerMobileOpen = false;
        state.selectedExamId = normalizedExamId;
        state.examToken = '';
        clearMessages();
        persistAuthSession();
        render();
    }

    function renderConfirmStage() {
        if (!state.exams.length) {
            return [
                '<section class="cbt-card">',
                '<h3>Belum Ada Exam Aktif</h3>',
                '<p class="cbt-subtitle">Akun ini belum memiliki exam yang tersedia saat ini.</p>',
                '<div class="cbt-actions">',
                renderRefreshButton(state.busy),
                '<button class="cbt-button cbt-button-danger" data-action="logout" type="button">Logout</button>',
                '</div>',
                renderAlert(),
                '</section>'
            ].join('');
        }

        var selectedExam = getSelectedExam();
        var userName = getCurrentUserName();
        var userPhoto = getCurrentUserPhoto();
        var userInitial = getUserInitial(userName);
        var hasSelectedExam = !!selectedExam;
        var selectedAttemptStatus = String(selectedExam && selectedExam.latest_attempt_status ? selectedExam.latest_attempt_status : '').toLowerCase();
        var selectedExamCompleted = selectedAttemptStatus === 'completed';
        var selectedExamAttemptId = Number(selectedExam && selectedExam.latest_attempt_id ? selectedExam.latest_attempt_id : 0);
        var selectedExamRequiresToken = Number(selectedExam && selectedExam.requires_token ? selectedExam.requires_token : 0) === 1;
        var selectedExamAutoToken = Number(selectedExam && selectedExam.token_frontend_auto_apply ? selectedExam.token_frontend_auto_apply : 0) === 1;
        var selectedExamAutoTokenValue = String(selectedExam && selectedExam.token_auto_value ? selectedExam.token_auto_value : '').trim().toUpperCase();
        var tokenInputRequired = Number(
            selectedExam && selectedExam.token_input_required !== undefined
                ? selectedExam.token_input_required
                : (selectedExamRequiresToken ? 1 : 0)
        ) === 1;
        var tokenRefreshMinutes = Number(selectedExam && selectedExam.token_refresh_minutes ? selectedExam.token_refresh_minutes : 0);
        var tokenInfoText = hasSelectedExam
            ? (selectedExamCompleted
                ? 'Ujian ini sudah selesai. Anda bisa melihat hasil nilai dari attempt terakhir.'
                : (selectedExamRequiresToken
                    ? (
                        tokenInputRequired
                            ? ('Ujian ini membutuhkan token.' + (tokenRefreshMinutes > 0 ? (' Refresh setiap ' + tokenRefreshMinutes + ' menit.') : ''))
                            : ('Token ujian diisi otomatis oleh sistem.' + (tokenRefreshMinutes > 0 ? (' Refresh setiap ' + tokenRefreshMinutes + ' menit.') : ''))
                    )
                    : 'Ujian ini tidak membutuhkan token.'))
            : 'Pilih ujian terlebih dahulu dari daftar di kiri.';
        var userUsername = String(state.user && state.user.username ? state.user.username : '-');
        var userClassCode = String(state.user && state.user.kode_kelas ? state.user.kode_kelas : '-');
        var userRoomCode = String(state.user && state.user.kode_ruang ? state.user.kode_ruang : '-');
        var selectedExamTitle = hasSelectedExam ? String(selectedExam.title || '-') : 'Belum ada ujian dipilih';
        var selectedExamSubject = hasSelectedExam ? String(selectedExam.subject_name || '-') : 'Pilih ujian dari daftar kiri';
        var selectedExamStatusLabel = hasSelectedExam ? String(selectedExam.status || '-') : 'Menunggu pilihan';
        var selectedExamStartsAt = hasSelectedExam ? formatDateTime(selectedExam.starts_at) : '-';
        var selectedExamDurationMinutes = hasSelectedExam ? (Number(selectedExam.duration_minutes) || 0) : 0;
        var selectedExamDurationLabel = hasSelectedExam
            ? (selectedExamDurationMinutes > 0 ? (selectedExamDurationMinutes + ' menit') : 'Durasi belum diatur')
            : 'Menunggu pilihan';
        var selectedExamLatestPercentage = Number(selectedExam && selectedExam.latest_attempt_percentage);
        var selectedAccessLabel = 'Siap dikerjakan';
        var selectedAccessTone = 'is-ready';
        if (!hasSelectedExam) {
            selectedAccessLabel = 'Belum pilih ujian';
            selectedAccessTone = 'is-muted';
        } else if (selectedExamCompleted) {
            selectedAccessLabel = 'Hasil tersedia';
            selectedAccessTone = 'is-done';
        } else if (selectedAttemptStatus === 'in_progress') {
            selectedAccessLabel = 'Lanjutkan ujian';
            selectedAccessTone = 'is-ready';
        } else if (Number(selectedExam && selectedExam.is_available_now ? selectedExam.is_available_now : 0) !== 1) {
            selectedAccessTone = 'is-warn';
            if (Number(selectedExam && selectedExam.is_class_allowed ? selectedExam.is_class_allowed : 0) !== 1) {
                selectedAccessLabel = 'Kelas tidak sesuai';
            } else if (Number(selectedExam && selectedExam.is_within_schedule ? selectedExam.is_within_schedule : 0) !== 1) {
                var selectedAvailabilityReason = String(selectedExam && selectedExam.availability_reason ? selectedExam.availability_reason : '');
                if (selectedAvailabilityReason === 'not_started') {
                    selectedAccessLabel = 'Belum mulai';
                } else if (selectedAvailabilityReason === 'ended') {
                    selectedAccessLabel = 'Jadwal selesai';
                } else {
                    selectedAccessLabel = 'Di luar jadwal';
                }
            } else {
                selectedAccessLabel = 'Belum tersedia';
            }
        }

        var selectedAttemptLabel = 'Belum mulai';
        var selectedAttemptTone = 'is-neutral';
        var selectedAttemptMeta = 'Belum ada sesi ujian tersimpan.';
        if (!hasSelectedExam) {
            selectedAttemptLabel = 'Menunggu pilihan';
            selectedAttemptTone = 'is-muted';
            selectedAttemptMeta = 'Pilih salah satu ujian dari panel kiri.';
        } else if (selectedExamCompleted) {
            selectedAttemptLabel = 'Sudah selesai';
            selectedAttemptTone = 'is-done';
            selectedAttemptMeta = Number.isFinite(selectedExamLatestPercentage)
                ? ('Nilai terakhir ' + formatScoreValue(selectedExamLatestPercentage) + '%')
                : 'Hasil attempt terakhir tersedia.';
        } else if (selectedAttemptStatus === 'in_progress') {
            selectedAttemptLabel = 'Sesi aktif';
            selectedAttemptTone = 'is-ready';
            selectedAttemptMeta = 'Anda akan melanjutkan dari progres terakhir.';
        }

        var selectedTokenLabel = 'Menunggu pilihan';
        var selectedTokenTone = 'is-muted';
        if (hasSelectedExam) {
            if (tokenInputRequired) {
                selectedTokenLabel = 'Token manual';
                selectedTokenTone = 'is-warn';
            } else if (selectedExamRequiresToken || selectedExamAutoToken) {
                selectedTokenLabel = 'Token otomatis';
                selectedTokenTone = 'is-ready';
            } else {
                selectedTokenLabel = 'Tanpa token';
                selectedTokenTone = 'is-neutral';
            }
        }

        var tokenFieldValue = !hasSelectedExam
            ? 'Pilih ujian dahulu'
            : (selectedExamAutoTokenValue !== ''
                ? selectedExamAutoTokenValue
                : ((selectedExamRequiresToken || selectedExamAutoToken) ? 'Otomatis oleh sistem' : 'Tidak diperlukan'));
        var tokenFieldHelpText = !hasSelectedExam
            ? 'Pilih ujian dari daftar kiri untuk melihat kebutuhan token.'
            : (selectedExamCompleted
                ? 'Attempt terakhir untuk ujian ini sudah selesai. Anda masih bisa membuka hasil nilainya.'
                : (tokenInputRequired
                    ? 'Masukkan token 6 karakter sebelum memulai atau melanjutkan ujian.'
                    : ((selectedExamRequiresToken || selectedExamAutoToken)
                        ? 'Token tidak perlu diketik manual karena akan diisi oleh sistem.'
                        : 'Ujian ini dapat dimulai tanpa token.')));
        if (tokenRefreshMinutes > 0 && hasSelectedExam && !selectedExamCompleted) {
            tokenFieldHelpText += ' Gunakan token terbaru karena sistem dapat memperbaruinya setiap ' + tokenRefreshMinutes + ' menit.';
        }

        var confirmQuickText = !hasSelectedExam
            ? 'Pilih salah satu ujian dari daftar kiri untuk mengaktifkan detail dan tombol aksi.'
            : (
                selectedAttemptStatus === 'in_progress'
                    ? 'Sesi sebelumnya masih aktif. Anda akan melanjutkan dari progres terakhir.'
                    : (
                        selectedExamCompleted
                            ? 'Attempt terakhir sudah selesai. Gunakan tombol lihat nilai untuk membuka hasilnya.'
                            : 'Pastikan jadwal, token, dan data peserta sudah benar sebelum menekan mulai.'
                    )
            );
        var confirmSupportText = tokenFieldHelpText;
        if (confirmQuickText && tokenFieldHelpText.indexOf(confirmQuickText) === -1) {
            confirmSupportText += ' ' + confirmQuickText;
        }

        var primaryActionLabel = state.busy
            ? (selectedExamCompleted ? 'Memuat...' : (selectedAttemptStatus === 'in_progress' ? 'Membuka...' : 'Memulai...'))
            : (selectedExamCompleted ? 'Lihat Nilai' : (selectedAttemptStatus === 'in_progress' ? 'Lanjutkan Ujian' : 'Mulai Ujian'));
        var examItems = state.exams.map(function (exam) {
            var isActive = Number(exam.id) === Number(state.selectedExamId);
            var status = String(exam.status || '-');
            var startsAt = formatDateTime(exam.starts_at);
            var duration = Number(exam.duration_minutes) || 0;
            var withinSchedule = Number(exam.is_within_schedule) === 1;
            var classAllowed = Number(exam.is_class_allowed) === 1;
            var availableNow = Number(exam.is_available_now) === 1;
            var availabilityReason = String(exam.availability_reason || '');
            var latestAttemptStatus = String(exam.latest_attempt_status || '').toLowerCase();
            var latestAttemptPercentage = Number(exam.latest_attempt_percentage);
            var examAttemptLabel = 'Belum ujian';
            var examAttemptExtra = '';
            var examAttemptCompact = 'Belum';
            var accessLabel = 'Akses: siap';
            var itemClasses = ['cbt-exam-item'];

            if (isActive) {
                itemClasses.push('is-active');
            }
            if (latestAttemptStatus === 'completed') {
                itemClasses.push('is-completed');
                examAttemptLabel = 'Sudah selesai';
                examAttemptCompact = 'Selesai';
                if (Number.isFinite(latestAttemptPercentage)) {
                    examAttemptExtra = ' | Nilai: ' + formatScoreValue(latestAttemptPercentage) + '%';
                    examAttemptCompact += ' | ' + formatScoreValue(latestAttemptPercentage) + '%';
                }
            } else if (latestAttemptStatus === 'in_progress') {
                itemClasses.push('is-in-progress');
                examAttemptLabel = 'Sedang dikerjakan';
                examAttemptCompact = 'Dikerjakan';
            } else {
                itemClasses.push('is-not-started');
            }

            if (!availableNow) {
                if (!classAllowed) {
                    accessLabel = 'Akses: kelas tidak sesuai';
                } else if (!withinSchedule) {
                    if (availabilityReason === 'not_started') {
                        accessLabel = 'Akses: belum mulai';
                    } else if (availabilityReason === 'ended') {
                        accessLabel = 'Akses: jadwal sudah selesai';
                    } else {
                        accessLabel = 'Akses: di luar jadwal';
                    }
                } else {
                    accessLabel = 'Akses: belum tersedia';
                }
            }

            var accessCompactLabel = accessLabel.replace(/^Akses:\s*/i, '');
            var startsAtCompact = formatDateTimeCompact(exam.starts_at);
            var statusBadgeLabel = formatExamStatusBadgeLabel(status);
            var statusBadgeClass = 'is-neutral';
            var accessChipClass = availableNow ? 'is-ready' : 'is-warn';
            var attemptChipClass = 'is-warn';

            itemClasses.push('cbt-exam-item-modern');

            if (String(status || '').toLowerCase().indexOf('publish') >= 0 || String(status || '').toLowerCase().indexOf('aktif') >= 0) {
                statusBadgeClass = 'is-published';
            } else if (String(status || '').toLowerCase().indexOf('draft') >= 0 || String(status || '').toLowerCase().indexOf('arsip') >= 0) {
                statusBadgeClass = 'is-muted';
            }

            if (latestAttemptStatus === 'completed') {
                attemptChipClass = 'is-success';
            } else if (latestAttemptStatus === 'in_progress') {
                attemptChipClass = 'is-ready';
            }

            return [
                '<button type="button" class="' + itemClasses.join(' ') + '" data-action="select-exam" data-id="' + escapeHtml(exam.id) + '" aria-pressed="' + (isActive ? 'true' : 'false') + '">',
                '<span class="cbt-exam-card-head">',
                '<span class="cbt-exam-title cbt-exam-card-title" title="' + escapeHtml(exam.title || '-') + '">' + escapeHtml(exam.title || '-') + '</span>',
                '<span class="cbt-exam-card-status ' + statusBadgeClass + '">' + escapeHtml(statusBadgeLabel) + '</span>',
                '</span>',
                '<span class="cbt-exam-card-chips">',
                renderExamCardChip(startsAtCompact, 'calendar', 'is-accent'),
                renderExamCardChip(String(duration) + ' menit', 'clock', 'is-soft'),
                renderExamCardChip(accessCompactLabel, 'access', accessChipClass),
                renderExamCardChip(examAttemptCompact, 'attempt', attemptChipClass),
                '</span>',
                '</button>'
            ].join('');
        }).join('');
        var selectedExamPickerLabel = hasSelectedExam
            ? formatExamPickerOptionLabel(selectedExam)
            : 'Pilih salah satu ujian';
        var selectedExamPickerStartsAt = hasSelectedExam ? formatDateTimeCompact(selectedExam.starts_at) : '';
        var selectedExamPickerDuration = hasSelectedExam ? (Number(selectedExam.duration_minutes) || 0) : 0;
        var selectedExamPickerNote = hasSelectedExam
            ? (selectedExamPickerStartsAt + ' | ' + (selectedExamPickerDuration > 0 ? (String(selectedExamPickerDuration) + ' menit') : 'Durasi belum diatur'))
            : (String(state.exams.length) + ' ujian tersedia');
        var examPickerDropdownClass = 'cbt-exam-picker-dropdown' + (state.examPickerMobileOpen ? ' is-open' : '');
        var examPickerOptions = state.exams.map(function (exam) {
            return renderExamPickerMobileOption(exam);
        }).join('');

        return [
            '<div class="cbt-grid-2 cbt-confirm-stage-grid">',
            '<section class="cbt-card cbt-exam-picker-card">',
            '<h3 class="cbt-exam-picker-title">Pilih Ujian</h3>',
            '<p class="cbt-subtitle">Daftar ujian sesuai hak akses akun yang login.</p>',
            '<div class="cbt-exam-picker-mobile">',
            '<div class="cbt-exam-picker-mobile-head">',
            '<p class="cbt-exam-picker-mobile-kicker">Pilih Cepat</p>',
            '<span class="cbt-exam-picker-mobile-count">' + escapeHtml(String(state.exams.length)) + ' ujian</span>',
            '</div>',
            '<div class="cbt-field">',
            '<label id="cbt-exam-picker-mobile-label">Pilih Ujian</label>',
            '<div class="' + examPickerDropdownClass + '">',
            '<button id="cbt-exam-picker-trigger" class="cbt-exam-picker-trigger" data-action="toggle-exam-picker-mobile" type="button" aria-haspopup="listbox" aria-expanded="' + (state.examPickerMobileOpen ? 'true' : 'false') + '" aria-controls="cbt-exam-picker-menu" aria-labelledby="cbt-exam-picker-mobile-label cbt-exam-picker-trigger-value"' + (state.busy ? ' disabled' : '') + '>',
            '<span class="cbt-exam-picker-trigger-copy">',
            '<span class="cbt-exam-picker-trigger-label">Ujian Dipilih</span>',
            '<strong id="cbt-exam-picker-trigger-value" class="cbt-exam-picker-trigger-value">' + escapeHtml(selectedExamPickerLabel) + '</strong>',
            '<small class="cbt-exam-picker-trigger-note">' + escapeHtml(selectedExamPickerNote) + '</small>',
            '</span>',
            '<span class="cbt-exam-picker-trigger-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6 9l6 6 6-6"></path></svg></span>',
            '</button>',
            (state.examPickerMobileOpen
                ? ('<div id="cbt-exam-picker-menu" class="cbt-exam-picker-menu" role="listbox" aria-labelledby="cbt-exam-picker-mobile-label">' + examPickerOptions + '</div>')
                : ''),
            '</div>',
            '</div>',
            '<p class="cbt-exam-picker-mobile-help">Pilih ujian dari panel ini untuk melihat ringkasan dan tombol aksi yang sesuai.</p>',
            '</div>',
            '<div class="cbt-exam-list">' + examItems + '</div>',
            '</section>',
            '<section class="cbt-card cbt-confirm-card cbt-confirm-card-simple">',
            '<p class="cbt-confirm-kicker">Siap Ujian</p>',
            '<h3>Konfirmasi Ujian</h3>',
            '<p class="cbt-confirm-selected-title">' + escapeHtml(selectedExamTitle) + '</p>',
            '<p class="cbt-subtitle">' + escapeHtml(tokenInfoText) + '</p>',
            '<div class="cbt-confirm-status-list">',
            renderConfirmStatusPill(selectedAccessLabel, selectedAccessTone),
            renderConfirmStatusPill(selectedTokenLabel, selectedTokenTone),
            renderConfirmStatusPill(selectedAttemptLabel, selectedAttemptTone),
            '</div>',
            '<div class="cbt-confirm-profile">',
            (
                userPhoto !== ''
                    ? '<button class="cbt-confirm-profile-avatar-button" data-action="open-user-photo" type="button" aria-label="Lihat foto peserta ukuran besar"><img class="cbt-confirm-profile-avatar" src="' + escapeHtml(userPhoto) + '" alt="' + escapeHtml(userName) + '" loading="lazy" decoding="async" /></button>'
                    : '<div class="cbt-confirm-profile-avatar cbt-confirm-profile-avatar-fallback" aria-hidden="true">' + escapeHtml(userInitial) + '</div>'
            ),
            '</div>',
            '<div class="cbt-form-grid cbt-confirm-form-grid">',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Username</label><input class="cbt-input" value="' + escapeHtml(userUsername) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field"><label>Nama Peserta</label><input class="cbt-input" value="' + escapeHtml(userName || '-') + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Kelas</label><input class="cbt-input" value="' + escapeHtml(userClassCode) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Ruangan</label><input class="cbt-input" value="' + escapeHtml(userRoomCode) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field"><label>Ujian</label><input class="cbt-input" value="' + escapeHtml(selectedExamTitle) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field"><label>Mata Pelajaran</label><input class="cbt-input" value="' + escapeHtml(selectedExamSubject) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Mulai</label><input class="cbt-input" value="' + escapeHtml(selectedExamStartsAt) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Durasi</label><input class="cbt-input" value="' + escapeHtml(selectedExamDurationLabel) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Status Akses</label><input class="cbt-input" value="' + escapeHtml(selectedAccessLabel) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Status Attempt</label><input class="cbt-input" value="' + escapeHtml(selectedAttemptLabel) + '" readonly /></div>',
            (
                tokenInputRequired
                    ? '<div class="cbt-field cbt-confirm-field cbt-confirm-field-token"><label for="cbt-exam-token">Token Ujian</label><input id="cbt-exam-token" class="cbt-input cbt-input-token" name="exam_token" maxlength="6" value="' + escapeHtml(state.examToken) + '" placeholder="6 karakter (tanpa 0 O I L)"' + (hasSelectedExam && !selectedExamCompleted ? '' : ' disabled') + ' /></div>'
                    : '<div class="cbt-field cbt-confirm-field cbt-confirm-field-token"><label>Token Ujian</label><input class="cbt-input" value="' + escapeHtml(tokenFieldValue) + '" readonly /></div>'
            ),
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-note"><p class="cbt-confirm-token-note">' + escapeHtml(confirmSupportText) + '</p></div>',
            '</div>',
            renderAlert(),
            '<div class="cbt-actions cbt-confirm-actions">',
            (
                selectedExamCompleted
                    ? '<button class="cbt-button cbt-button-primary" data-action="view-result" type="button"' + (state.busy || !hasSelectedExam || selectedExamAttemptId <= 0 ? ' disabled' : '') + '>' + primaryActionLabel + '</button>'
                    : '<button class="cbt-button cbt-button-primary" data-action="start-exam" type="button"' + (state.busy || !hasSelectedExam ? ' disabled' : '') + '>' + primaryActionLabel + '</button>'
            ),
            renderRefreshButton(state.busy),
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function renderQuestionInput(question) {
        var answer = resolveStoredAnswerValueForQuestion(question);

        if (question.question_type === 'multiple_choice' || question.question_type === 'true_false') {
            var selectedId = Number(answer) || 0;
            return [
                '<div class="cbt-options">',
                (Array.isArray(question.options) ? question.options : []).map(function (option, index) {
                    var optionId = Number(option.id) || 0;
                    var checked = optionId === selectedId;
                    return [
                        '<label class="cbt-option' + (checked ? ' is-selected' : '') + '">',
                        '<span class="cbt-option-row">',
                        '<input type="radio" name="cbt_q_' + escapeHtml(question.id) + '" value="' + escapeHtml(optionId) + '" data-action="answer-single" data-qid="' + escapeHtml(question.id) + '" data-option-id="' + escapeHtml(optionId) + '"' + (checked ? ' checked' : '') + ' />',
                        '<span class="cbt-option-key">' + escapeHtml(questionOptionKey(option, index)) + '</span>',
                        '<span class="cbt-option-label">' + safeRichHtml(option.option_text || '') + '</span>',
                        '</span>',
                        '</label>'
                    ].join('');
                }).join(''),
                '</div>'
            ].join('');
        }

        if (question.question_type === 'multiple_answer') {
            var selected = Array.isArray(answer) ? answer.map(function (item) { return Number(item) || 0; }) : [];
            return [
                '<div class="cbt-options">',
                (Array.isArray(question.options) ? question.options : []).map(function (option, index) {
                    var optionId = Number(option.id) || 0;
                    var checked = selected.indexOf(optionId) >= 0;
                    return [
                        '<label class="cbt-option' + (checked ? ' is-selected' : '') + '">',
                        '<span class="cbt-option-row">',
                        '<input type="checkbox" value="' + escapeHtml(optionId) + '" data-action="answer-multi" data-qid="' + escapeHtml(question.id) + '" data-option-id="' + escapeHtml(optionId) + '"' + (checked ? ' checked' : '') + ' />',
                        '<span class="cbt-option-key">' + escapeHtml(questionOptionKey(option, index)) + '</span>',
                        '<span class="cbt-option-label">' + safeRichHtml(option.option_text || '') + '</span>',
                        '</span>',
                        '</label>'
                    ].join('');
                }).join(''),
                '</div>'
            ].join('');
        }

        if (question.question_type === 'true_false_matrix') {
            var matrixItems = getTrueFalseMatrixItems(question);
            var matrixAnswer = normalizeTrueFalseMatrixAnswer(answer);
            if (!matrixItems.length) {
                return '<p class="cbt-muted">Konfigurasi pernyataan belum tersedia.</p>';
            }

            return [
                '<div class="cbt-tf-matrix-wrap">',
                '<table class="cbt-tf-matrix-table">',
                '<thead><tr><th>Pernyataan</th><th>Benar</th><th>Salah</th></tr></thead>',
                '<tbody>',
                matrixItems.map(function (item, index) {
                    var selectedValue = String(matrixAnswer[item.key] || '').toLowerCase();
                    var trueChecked = selectedValue === 'true';
                    var falseChecked = selectedValue === 'false';
                    var rowName = 'cbt_tfm_' + String(question.id) + '_' + String(item.key);

                    return [
                        '<tr>',
                        '<td class="cbt-tf-matrix-statement"><span class="cbt-option-key">' + escapeHtml(index + 1) + '.</span> <span>' + safeRichHtml(item.text || '') + '</span></td>',
                        '<td class="cbt-tf-matrix-choice">',
                        '<label>',
                        '<input type="radio" name="' + escapeHtml(rowName) + '" data-action="answer-tf-matrix" data-qid="' + escapeHtml(question.id) + '" data-key="' + escapeHtml(item.key) + '" data-value="true"' + (trueChecked ? ' checked' : '') + ' />',
                        '<span>Benar</span>',
                        '</label>',
                        '</td>',
                        '<td class="cbt-tf-matrix-choice">',
                        '<label>',
                        '<input type="radio" name="' + escapeHtml(rowName) + '" data-action="answer-tf-matrix" data-qid="' + escapeHtml(question.id) + '" data-key="' + escapeHtml(item.key) + '" data-value="false"' + (falseChecked ? ' checked' : '') + ' />',
                        '<span>Salah</span>',
                        '</label>',
                        '</td>',
                        '</tr>'
                    ].join('');
                }).join(''),
                '</tbody>',
                '</table>',
                '</div>'
            ].join('');
        }

        if (question.question_type === 'short_answer') {
            return '';
        }

        var essayValue = String(answer || '');
        return '<textarea class="cbt-textarea" rows="8" data-action="answer-text" data-qid="' + escapeHtml(question.id) + '">' + escapeHtml(essayValue) + '</textarea>';
    }

    function renderExamStage() {
        var totalQuestions = getQuestionCount();
        if (!totalQuestions) {
            return [
                '<section class="cbt-card">',
                '<h3>Soal Tidak Tersedia</h3>',
                '<p class="cbt-subtitle">Belum ada soal yang bisa ditampilkan untuk exam ini.</p>',
                '<div class="cbt-actions"><button class="cbt-button cbt-button-secondary" data-action="back-confirm" type="button">Kembali</button></div>',
                renderAlert(),
                '</section>'
            ].join('');
        }

        var currentQuestionId = getQuestionIdAtIndex(state.currentIndex);
        var currentQuestion = getQuestionPayloadById(currentQuestionId);
        if (!currentQuestion) {
            return [
                '<section class="cbt-card">',
                '<h3>Memuat Soal</h3>',
                '<p class="cbt-subtitle">Window soal sedang dimuat. Coba lagi beberapa detik.</p>',
                renderAlert(),
                '</section>'
            ].join('');
        }

        var progressSummary = getExamProgressSummary();
        var selectedExam = getSelectedExam();
        var activeExamTitle = selectedExam && selectedExam.title ? String(selectedExam.title) : '-';
        var answeredCount = progressSummary.answeredQuestions;
        var answeredPercentage = totalQuestions > 0 ? (answeredCount / totalQuestions) * 100 : 0;
        var answeredPercentageText = formatScoreValue(answeredPercentage);
        var answeredPercentageWidth = Math.max(0, Math.min(100, answeredPercentage)).toFixed(2);
        var examFooterProgressValue = totalQuestions > 0 ? (answeredPercentageText + '%') : '-';
        var examFooterProgressNote = totalQuestions > 0
            ? (String(answeredCount) + '/' + String(totalQuestions) + ' soal')
            : 'Belum ada soal';
        var doubtfulCount = progressSummary.doubtfulQuestions;
        var unansweredCount = Math.max(0, totalQuestions - answeredCount);
        var changedQuestionCount = getChangedQuestionCount();
        var currentQuestionIsAnswered = isQuestionAnswered(currentQuestion);
        var currentQuestionIsDoubtful = isQuestionDoubtful(currentQuestion);
        var currentQuestionIsChanged = isQuestionChanged(currentQuestion);
        var isLastQuestion = state.currentIndex >= (totalQuestions - 1);
        var currentQuestionTypeLabel = formatQuestionType(currentQuestion.question_type);
        var currentQuestionTypeCode = navigationQuestionTypeBadgeConfig(currentQuestion.question_type).code;
        var currentQuestionPointsRaw = currentQuestion && currentQuestion.points !== undefined ? currentQuestion.points : '-';
        var currentQuestionPointsNumber = Number(currentQuestionPointsRaw);
        var currentQuestionPoints = Number.isFinite(currentQuestionPointsNumber)
            ? formatScoreValue(currentQuestionPointsNumber)
            : String(currentQuestionPointsRaw);
        var currentQuestionDisplayNumber = getQuestionDisplayNumber(currentQuestion, state.currentIndex);
        var currentQuestionMetaLabel = currentQuestionTypeLabel + ' | Poin ' + currentQuestionPoints;
        var currentQuestionMetaCompact = currentQuestionTypeCode + '\u00b7' + currentQuestionPoints;
        var doubtfulActionLabel = currentQuestionIsDoubtful ? 'Batalkan ragu-ragu' : 'Tandai ragu-ragu';
        var doubtfulActionClass = 'cbt-action-icon cbt-action-icon-doubtful' + (currentQuestionIsDoubtful ? ' is-active' : '');
        var quickNavigationMarkup = [
            '<button class="cbt-action-icon cbt-action-icon-prev" data-action="prev" type="button" aria-label="Sebelumnya" title="Sebelumnya"' + (state.currentIndex <= 0 || state.busy ? ' disabled' : '') + '><span class="cbt-visually-hidden">Sebelumnya</span></button>',
            '<button class="' + doubtfulActionClass + '" data-action="toggle-doubtful" data-qid="' + escapeHtml(currentQuestion.id) + '" type="button" aria-label="' + escapeHtml(doubtfulActionLabel) + '" title="' + escapeHtml(doubtfulActionLabel) + '"' + (state.busy ? ' disabled' : '') + '><span class="cbt-visually-hidden">' + escapeHtml(doubtfulActionLabel) + '</span></button>',
            '<button class="cbt-action-icon cbt-action-icon-next" data-action="next" type="button" aria-label="Selanjutnya" title="Selanjutnya"' + (state.currentIndex >= totalQuestions - 1 || state.busy ? ' disabled' : '') + '><span class="cbt-visually-hidden">Selanjutnya</span></button>'
        ].join('');
        var navPanelPosition = getEffectiveNavPanelPosition();
        var calculatorPanelPosition = getEffectiveCalculatorPanelPosition();
        var examLayoutClass = 'cbt-exam-layout cbt-nav-pos-' + navPanelPosition + (state.navPanelVisible ? '' : ' is-nav-hidden');
        var questionHeadClasses = ['cbt-question-head'];
        if (currentQuestionIsDoubtful) {
            questionHeadClasses.push('is-doubtful');
        } else if (currentQuestionIsAnswered) {
            questionHeadClasses.push('is-answered');
        }
        var navQuestionFilter = normalizeNavigationQuestionFilter(state.navQuestionFilter);
        var filteredNavigationEntries = getNavigationQuestionEntries(navQuestionFilter);
        var navItems = filteredNavigationEntries.map(function (entry) {
            var question = entry.question;
            var classes = ['cbt-nav-btn'];
            if (isQuestionAnswered(question)) {
                classes.push('is-answered');
            }
            if (isQuestionDoubtful(question)) {
                classes.push('is-doubtful');
            }
            if (isQuestionChanged(question)) {
                classes.push('is-changed');
            }
            if (entry.index === state.currentIndex) {
                classes.push('is-current');
            }
            var answerBadge = renderNavigationAnswerBadges(question);
            var questionTypeBadge = renderNavigationQuestionTypeBadge(question);
            var displayNumber = getQuestionDisplayNumber(question, entry.index);
            var buttonLabel = 'Soal ' + String(displayNumber);
            if (isQuestionChanged(question)) {
                buttonLabel += ', soal berubah';
            }
            return '<button type="button" class="' + classes.join(' ') + '" data-action="jump" data-index="' + escapeHtml(entry.index) + '" aria-label="' + escapeHtml(buttonLabel) + '" title="' + escapeHtml(buttonLabel) + '"><span class="cbt-nav-number">' + escapeHtml(displayNumber) + '</span>' + questionTypeBadge + answerBadge + '</button>';
        }).join('');
        var navGridClass = 'cbt-nav-grid' + (filteredNavigationEntries.length ? '' : ' is-empty');
        var navGridMarkup = filteredNavigationEntries.length
            ? navItems
            : ('<div class="cbt-nav-empty">' + escapeHtml(navigationQuestionFilterEmptyMessage(navQuestionFilter)) + '</div>');
        var answeredFilterActive = navQuestionFilter === NAV_QUESTION_FILTER_ANSWERED;
        var unansweredFilterActive = navQuestionFilter === NAV_QUESTION_FILTER_UNANSWERED;
        var doubtfulFilterActive = navQuestionFilter === NAV_QUESTION_FILTER_DOUBTFUL;
        var answeredFilterTitle = answeredFilterActive
            ? 'Tampilkan semua nomor soal'
            : 'Tampilkan hanya soal yang sudah terjawab';
        var unansweredFilterTitle = unansweredFilterActive
            ? 'Tampilkan semua nomor soal'
            : 'Tampilkan hanya soal yang belum dijawab';
        var doubtfulFilterTitle = doubtfulFilterActive
            ? 'Tampilkan semua nomor soal'
            : 'Tampilkan hanya soal yang ditandai ragu-ragu';

        var navPanelMarkup = [
            '<aside class="cbt-side-card' + (state.navPanelVisible ? '' : ' is-hidden') + '">',
            '<div class="cbt-side-card-head">',
            '<div class="cbt-side-card-title-wrap">',
            '<h4 class="cbt-side-card-title">NAVIGASI SOAL</h4>',
            '<p class="cbt-side-card-subtitle">Pilih nomor untuk pindah soal</p>',
            '</div>',
            '<div class="cbt-side-card-head-actions">',
            renderNavPositionControl('cbt-nav-position-group-inline'),
            renderNavToggleButton(true, 'cbt-nav-toggle-side'),
            '</div>',
            '</div>',
            '<div class="cbt-exam-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(answeredPercentageWidth) + '">',
            '<span class="cbt-exam-progress-fill" style="width: ' + escapeHtml(answeredPercentageWidth) + '%;"></span>',
            '</div>',
            '<section class="cbt-side-summary">',
            '<div class="cbt-side-summary-top">',
            '<p class="cbt-side-summary-kicker">Progress Jawaban</p>',
            '<strong class="cbt-side-summary-value">' + escapeHtml(answeredPercentageText) + '%</strong>',
            '</div>',
            '<div class="cbt-side-stat-grid">',
            '<button class="cbt-side-stat is-answered' + (answeredFilterActive ? ' is-active' : '') + '" data-action="filter-nav" data-filter="' + NAV_QUESTION_FILTER_ANSWERED + '" type="button" aria-pressed="' + (answeredFilterActive ? 'true' : 'false') + '" title="' + escapeHtml(answeredFilterTitle) + '" aria-label="Filter soal terjawab, ' + escapeHtml(answeredCount) + ' dari ' + escapeHtml(totalQuestions) + ' soal"><span>Terjawab</span><strong>' + escapeHtml(answeredCount) + '/' + escapeHtml(totalQuestions) + '</strong></button>',
            '<button class="cbt-side-stat is-unanswered' + (unansweredFilterActive ? ' is-active' : '') + '" data-action="filter-nav" data-filter="' + NAV_QUESTION_FILTER_UNANSWERED + '" type="button" aria-pressed="' + (unansweredFilterActive ? 'true' : 'false') + '" title="' + escapeHtml(unansweredFilterTitle) + '" aria-label="Filter soal belum dijawab, ' + escapeHtml(unansweredCount) + ' soal"><span>Belum</span><strong>' + escapeHtml(unansweredCount) + '</strong></button>',
            '<button class="cbt-side-stat is-doubtful' + (doubtfulFilterActive ? ' is-active' : '') + '" data-action="filter-nav" data-filter="' + NAV_QUESTION_FILTER_DOUBTFUL + '" type="button" aria-pressed="' + (doubtfulFilterActive ? 'true' : 'false') + '" title="' + escapeHtml(doubtfulFilterTitle) + '" aria-label="Filter soal ragu-ragu, ' + escapeHtml(doubtfulCount) + ' soal"><span>Ragu</span><strong>' + escapeHtml(doubtfulCount) + '</strong></button>',
            '</div>',
            '</section>',
            '<div class="' + navGridClass + '">' + navGridMarkup + '</div>',
            '<div class="cbt-legend"><span class="cbt-legend-item cbt-legend-item-current"><i class="cbt-dot cbt-dot-current"></i> Aktif</span><span class="cbt-legend-item cbt-legend-item-answered"><i class="cbt-dot cbt-dot-answered"></i> Terjawab</span><span class="cbt-legend-item cbt-legend-item-doubtful"><i class="cbt-dot cbt-dot-doubtful"></i> Ragu-ragu</span>' + (changedQuestionCount > 0 ? '<span class="cbt-legend-item cbt-legend-item-changed"><i class="cbt-dot cbt-dot-changed"></i> Berubah</span>' : '') + '</div>',
            renderArchivedReviewHistorySection(),
            (isLastQuestion
                ? ('<div class="cbt-actions cbt-side-actions-compact"><button class="cbt-button cbt-button-primary" data-action="collect" type="button"' + (state.busy || state.isFinishing ? ' disabled' : '') + '>Kumpulkan Jawaban</button></div>')
                : ''),
            '</aside>'
        ].join('');

        var questionCardMarkup = [
            '<section class="cbt-question-card">',
            '<div class="' + questionHeadClasses.join(' ') + '">',
            '<div class="cbt-question-head-main">',
            '<div class="cbt-chip cbt-chip-question-index" aria-label="Soal ' + escapeHtml(currentQuestionDisplayNumber) + '"><span class="cbt-chip-mobile-icon" aria-hidden="true">#</span><span class="cbt-chip-label">Soal</span><span class="cbt-chip-value">' + escapeHtml(currentQuestionDisplayNumber) + '</span></div>',
            '<div class="cbt-chip cbt-chip-question-meta" title="' + escapeHtml(currentQuestionMetaLabel) + '" aria-label="' + escapeHtml(currentQuestionMetaLabel) + '"><span class="cbt-chip-mobile-meta" aria-hidden="true">' + escapeHtml(currentQuestionMetaCompact) + '</span><span class="cbt-chip-type">' + escapeHtml(currentQuestionTypeLabel) + '</span><span class="cbt-chip-separator" aria-hidden="true"></span><span class="cbt-chip-points">Poin ' + escapeHtml(currentQuestionPoints) + '</span></div>',
            renderQuestionPrefetchIndicator(),
            (currentQuestionIsChanged ? '<div class="cbt-chip cbt-chip-danger">Soal berubah</div>' : ''),
            (currentQuestionIsDoubtful ? '<div class="cbt-chip cbt-chip-warning cbt-chip-warning-icon" aria-label="Ragu-ragu"><span class="cbt-chip-warning-symbol" aria-hidden="true">!</span><span class="cbt-visually-hidden">Ragu-ragu</span></div>' : ''),
            renderQuestionFontControls(),
            '</div>',
            '<div class="cbt-question-head-tools">',
            renderCalculatorToggleButton(state.calculatorVisible),
            renderNavToggleButton(state.navPanelVisible, 'cbt-nav-toggle-head'),
            '</div>',
            '</div>',
            '<div class="cbt-question-body">',
            '<div class="cbt-question-quick-nav cbt-question-quick-nav-top" role="group" aria-label="Navigasi Cepat Soal">',
            quickNavigationMarkup,
            '</div>',
            '<div class="cbt-question-stem' + (currentQuestion.question_type === 'short_answer' ? ' is-short-answer' : '') + '">' + renderQuestionStem(currentQuestion) + '</div>',
            renderQuestionInput(currentQuestion),
            (isLastQuestion
                ? ('<div class="cbt-question-actions cbt-question-actions-main"><button class="cbt-button cbt-button-primary" data-action="finish" type="button"' + (state.busy || state.isFinishing ? ' disabled' : '') + '>' + (state.isFinishing ? 'Mengirim...' : 'Kumpulkan Jawaban') + '</button></div>')
                : ''),
            renderAlert(),
            '<div class="cbt-question-quick-nav cbt-question-quick-nav-bottom" role="group" aria-label="Navigasi Cepat Soal">',
            quickNavigationMarkup,
            '</div>',
            '<div class="cbt-question-exam-footer" title="' + escapeHtml(activeExamTitle) + '">',
            '<span class="cbt-question-exam-footer-badge" aria-hidden="true"><span class="cbt-question-exam-footer-badge-core"></span></span>',
            '<div class="cbt-question-exam-footer-copy">',
            '<span class="cbt-question-exam-footer-label">Ujian Aktif</span>',
            '<strong class="cbt-question-exam-footer-value">' + escapeHtml(activeExamTitle) + '</strong>',
            '</div>',
            '<div class="cbt-question-exam-footer-meta" aria-label="Progress ' + escapeHtml(examFooterProgressValue) + ', ' + escapeHtml(examFooterProgressNote) + ' terjawab">',
            '<span class="cbt-question-exam-footer-meta-label">Progress</span>',
            '<strong class="cbt-question-exam-footer-meta-value">' + escapeHtml(examFooterProgressValue) + '</strong>',
            '<small class="cbt-question-exam-footer-meta-note">' + escapeHtml(examFooterProgressNote) + '</small>',
            '</div>',
            '</div>',
            '</div>',
            '</section>'
        ].join('');

        var layoutContent = navPanelPosition === 'right'
            ? (questionCardMarkup + navPanelMarkup)
            : (navPanelPosition === 'bottom'
                ? (questionCardMarkup + navPanelMarkup)
                : (navPanelMarkup + questionCardMarkup));

        var examLayoutMarkup = '<div class="' + examLayoutClass + '">' + layoutContent + '</div>';
        var calculatorMarkup = renderCalculatorPanel();
        var stageShellClass = 'cbt-exam-stage-shell cbt-calc-pos-' + calculatorPanelPosition + (state.calculatorVisible ? '' : ' is-calc-hidden');
        var fullscreenPromptMarkup = renderExamFullscreenPrompt();
        var stageContent;

        if (calculatorPanelPosition === 'left') {
            stageContent = calculatorMarkup + examLayoutMarkup;
        } else if (calculatorPanelPosition === 'right') {
            stageContent = examLayoutMarkup + calculatorMarkup;
        } else if (calculatorPanelPosition === 'top') {
            stageContent = calculatorMarkup + examLayoutMarkup;
        } else {
            stageContent = examLayoutMarkup + calculatorMarkup;
        }

        return '<div class="' + stageShellClass + '">' + fullscreenPromptMarkup + stageContent + '</div>';
    }

    function formatScoreValue(value) {
        var number = Number(value);
        if (!Number.isFinite(number)) {
            return '0';
        }
        if (Math.abs(number - Math.round(number)) < 0.000001) {
            return String(Math.round(number));
        }
        return number.toFixed(2).replace(/\.?0+$/, '');
    }

    function normalizeReviewValueList(value) {
        if (Array.isArray(value)) {
            return value.map(function (item) {
                return String(item || '').trim();
            }).filter(function (item) {
                return item !== '';
            });
        }

        var raw = String(value || '').trim();
        if (raw === '') {
            return [];
        }

        if (raw.charAt(0) === '[') {
            try {
                var decoded = JSON.parse(raw);
                if (Array.isArray(decoded)) {
                    return decoded.map(function (item) {
                        return String(item || '').trim();
                    }).filter(function (item) {
                        return item !== '';
                    });
                }
            } catch (error) {
                // Fall through and treat as plain text.
            }
        }

        return [raw];
    }

    function normalizeReviewValueListWithEmpty(value) {
        if (Array.isArray(value)) {
            return value.map(function (item) {
                if (item === null || item === undefined) {
                    return '';
                }
                return String(item).trim();
            });
        }

        var raw = String(value || '').trim();
        if (raw === '') {
            return [];
        }

        if (raw.charAt(0) === '[') {
            try {
                var decoded = JSON.parse(raw);
                if (Array.isArray(decoded)) {
                    return decoded.map(function (item) {
                        if (item === null || item === undefined) {
                            return '';
                        }
                        return String(item).trim();
                    });
                }
            } catch (error) {
                // Fall through and treat as plain text.
            }
        }

        return [raw];
    }

    function shortAnswerInputLabelFromToken(token) {
        var normalized = String(token || '').trim().toUpperCase();
        if (/^[1-8]$/.test(normalized)) {
            return 'INPUT_' + normalized;
        }
        if (/^[A-H]$/.test(normalized)) {
            return 'INPUT_' + String(normalized.charCodeAt(0) - 64);
        }
        return '';
    }

    function resolveShortAnswerReviewInputLabels(item, submittedValues, correctValues) {
        var labels = [];
        var seen = {};
        var explicitKeys = item && Array.isArray(item.short_answer_input_keys) ? item.short_answer_input_keys : [];

        if (explicitKeys.length) {
            explicitKeys.forEach(function (key) {
                var label = shortAnswerInputLabelFromToken(key);
                if (label === '' || seen[label]) {
                    return;
                }
                seen[label] = true;
                labels.push(label);
            });
        } else {
            var questionText = String(item && item.question_text ? item.question_text : '');
            var pattern = /\[\s*input(?:\s*[_-]?\s*)?([a-h1-8])\s*\]/ig;
            var match;
            while ((match = pattern.exec(questionText)) !== null) {
                var parsedLabel = shortAnswerInputLabelFromToken(match[1]);
                if (parsedLabel === '' || seen[parsedLabel]) {
                    continue;
                }
                seen[parsedLabel] = true;
                labels.push(parsedLabel);
            }
        }

        var slotCount = Math.max(labels.length, submittedValues.length, correctValues.length);
        if (slotCount <= 0) {
            slotCount = 1;
        }
        for (var i = labels.length + 1; i <= slotCount; i++) {
            labels.push('INPUT_' + i);
        }
        return labels;
    }

    function renderReviewLabeledChips(labels, values) {
        if (!Array.isArray(labels) || !labels.length) {
            return '<span class="cbt-review-empty">-</span>';
        }

        return labels.map(function (label, index) {
            var rawEntry = Array.isArray(values) ? values[index] : '';
            var rawValue = (rawEntry === null || rawEntry === undefined) ? '' : String(rawEntry).trim();
            var valueMarkup = rawValue !== ''
                ? ('<span class="cbt-review-chip-value">' + escapeHtml(rawValue) + '</span>')
                : '<span class="cbt-review-chip-value cbt-review-chip-value-empty">-</span>';

            return [
                '<span class="cbt-review-chip cbt-review-chip-labeled">',
                '<span class="cbt-review-chip-key">' + escapeHtml(label) + '</span>',
                valueMarkup,
                '</span>'
            ].join('');
        }).join('');
    }

    function renderReviewChips(values) {
        if (!Array.isArray(values) || !values.length) {
            return '<span class="cbt-review-empty">-</span>';
        }

        return values.map(function (value) {
            return '<span class="cbt-review-chip">' + escapeHtml(String(value || '')) + '</span>';
        }).join('');
    }

    function renderReviewText(value) {
        var text = String(value || '').trim();
        if (text === '') {
            return '<span class="cbt-review-empty">-</span>';
        }
        return escapeHtml(text).replace(/\r?\n/g, '<br />');
    }

    function reviewStatusLabel(status) {
        var map = {
            correct: 'Benar',
            wrong: 'Salah',
            unanswered: 'Belum dijawab',
            manual: 'Perlu penilaian guru'
        };
        return map[status] || 'Belum dijawab';
    }

    function reviewStatusClass(status) {
        var map = {
            correct: 'is-correct',
            wrong: 'is-wrong',
            unanswered: 'is-unanswered',
            manual: 'is-manual'
        };
        return map[status] || 'is-unanswered';
    }

    function renderReviewItem(item) {
        var status = String(item && item.status ? item.status : 'unanswered');
        var questionType = String(item && item.question_type ? item.question_type : '');
        var options = item && Array.isArray(item.options) ? item.options : [];
        var points = Number(item && item.points !== undefined ? item.points : 0);
        var scoreAwarded = Number(item && item.score_awarded !== undefined ? item.score_awarded : 0);
        var explanationText = String(item && item.explanation ? item.explanation : '').trim();

        var answerMarkup = '';
        if (options.length > 0) {
            answerMarkup = [
                '<div class="cbt-review-options">',
                options.map(function (option, index) {
                    var optionId = Number(option && option.id) || 0;
                    var isSelected = Number(option && option.is_selected) === 1;
                    var isCorrect = Number(option && option.is_correct) === 1;
                    var optionClasses = ['cbt-review-option'];
                    if (isSelected) {
                        optionClasses.push('is-selected');
                    }
                    if (isCorrect) {
                        optionClasses.push('is-correct');
                    }

                    var badges = [];
                    if (isSelected) {
                        badges.push('<span class="cbt-review-badge cbt-review-badge-selected">Jawaban Anda</span>');
                    }
                    if (isCorrect) {
                        badges.push('<span class="cbt-review-badge cbt-review-badge-correct">Kunci</span>');
                    }

                    return [
                        '<div class="' + optionClasses.join(' ') + '" data-option-id="' + escapeHtml(optionId) + '">',
                        '<div class="cbt-review-option-main">',
                        '<span class="cbt-option-key">' + escapeHtml(questionOptionKey(option, index)) + '</span>',
                        '<span class="cbt-option-label">' + safeRichHtml(option && option.option_text ? option.option_text : '') + '</span>',
                        '</div>',
                        badges.length ? '<div class="cbt-review-option-badges">' + badges.join('') + '</div>' : '',
                        '</div>'
                    ].join('');
                }).join(''),
                '</div>'
            ].join('');
        } else if (questionType === 'true_false_matrix') {
            var tfMatrixRows = item && Array.isArray(item.true_false_matrix_rows) ? item.true_false_matrix_rows : [];
            if (!tfMatrixRows.length) {
                answerMarkup = '<div class="cbt-review-text">Data jawaban tidak tersedia.</div>';
            } else {
                answerMarkup = [
                    '<div class="cbt-review-tf-matrix">',
                    '<table class="cbt-tf-matrix-table cbt-tf-matrix-review">',
                    '<thead><tr><th>Pernyataan</th><th>Jawaban Anda</th><th>Kunci</th></tr></thead>',
                    '<tbody>',
                    tfMatrixRows.map(function (row, index) {
                        var submitted = String(row && row.submitted ? row.submitted : '-');
                        var correct = String(row && row.correct ? row.correct : '-');
                        var isMatch = Number(row && row.is_match) === 1;
                        var matchClass = isMatch ? ' is-match' : ' is-mismatch';
                        return [
                            '<tr>',
                            '<td class="cbt-tf-matrix-statement"><span class="cbt-option-key">' + escapeHtml(index + 1) + '.</span> ' + escapeHtml(String(row && row.text ? row.text : '')) + '</td>',
                            '<td class="cbt-tf-matrix-choice' + matchClass + '">' + escapeHtml(submitted) + '</td>',
                            '<td class="cbt-tf-matrix-choice">' + escapeHtml(correct) + '</td>',
                            '</tr>'
                        ].join('');
                    }).join(''),
                    '</tbody>',
                    '</table>',
                    '</div>'
                ].join('');
            }
        } else if (questionType === 'short_answer') {
            var submittedShort = normalizeReviewValueListWithEmpty(item && item.submitted_short_answers ? item.submitted_short_answers : []);
            if (!submittedShort.length) {
                submittedShort = normalizeReviewValueListWithEmpty(item && item.answer_text ? item.answer_text : '');
            }
            var correctShort = normalizeReviewValueListWithEmpty(item && item.correct_short_answers ? item.correct_short_answers : []);
            var shortInputLabels = resolveShortAnswerReviewInputLabels(item, submittedShort, correctShort);
            answerMarkup = [
                '<div class="cbt-review-short-answer">',
                '<div class="cbt-review-pair"><strong>Jawaban Anda:</strong><div class="cbt-review-chip-list">' + renderReviewLabeledChips(shortInputLabels, submittedShort) + '</div></div>',
                '<div class="cbt-review-pair"><strong>Kunci Jawaban:</strong><div class="cbt-review-chip-list">' + renderReviewLabeledChips(shortInputLabels, correctShort) + '</div></div>',
                '</div>'
            ].join('');
        } else if (questionType === 'essay') {
            var rubricText = String(item && item.essay_rubric ? item.essay_rubric : '').trim();
            answerMarkup = [
                '<div class="cbt-review-essay-answer">',
                '<div class="cbt-review-pair"><strong>Jawaban Anda:</strong><div class="cbt-review-text">' + renderReviewText(item && item.answer_text ? item.answer_text : '') + '</div></div>',
                '<div class="cbt-review-pair"><strong>Acuan/Rubrik:</strong><div class="cbt-review-text">' + renderReviewText(rubricText) + '</div></div>',
                '</div>'
            ].join('');
        } else {
            answerMarkup = [
                '<div class="cbt-review-essay-answer">',
                '<div class="cbt-review-pair"><strong>Jawaban Anda:</strong><div class="cbt-review-text">' + renderReviewText(item && item.answer_text ? item.answer_text : '') + '</div></div>',
                '</div>'
            ].join('');
        }

        return [
            '<article class="cbt-review-item">',
            '<header class="cbt-review-item-head">',
            '<div>',
            '<h4>Soal ' + escapeHtml(item && item.question_number ? item.question_number : '-') + '</h4>',
            '<p class="cbt-muted">' + escapeHtml(formatQuestionType(questionType)) + '</p>',
            '</div>',
            '<div class="cbt-review-item-right">',
            '<span class="cbt-review-status ' + reviewStatusClass(status) + '">' + escapeHtml(reviewStatusLabel(status)) + '</span>',
            '<small class="cbt-muted">Skor ' + escapeHtml(formatScoreValue(scoreAwarded)) + ' / ' + escapeHtml(formatScoreValue(points)) + '</small>',
            '</div>',
            '</header>',
            '<div class="cbt-review-question">' + safeRichHtml(item && item.question_text ? item.question_text : '') + '</div>',
            answerMarkup,
            explanationText !== '' ? ('<div class="cbt-review-explanation"><strong>Pembahasan:</strong> ' + safeRichHtml(explanationText) + '</div>') : '',
            '</article>'
        ].join('');
    }

    function renderResultReviewSection() {
        var reviewItems = state.result && Array.isArray(state.result.review_items) ? state.result.review_items : [];
        if (!reviewItems.length) {
            return '';
        }

        var summary = state.result && state.result.review_summary && typeof state.result.review_summary === 'object'
            ? state.result.review_summary
            : null;

        var totalQuestions = summary ? Number(summary.total_questions) || reviewItems.length : reviewItems.length;
        var correctQuestions = summary ? Number(summary.correct_questions) || 0 : 0;
        var wrongQuestions = summary ? Number(summary.wrong_questions) || 0 : 0;
        var unansweredQuestions = summary ? Number(summary.unanswered_questions) || 0 : 0;
        var manualQuestions = summary ? Number(summary.manual_questions) || 0 : 0;
        var pendingEssayQuestions = 0;
        var pendingEssayMaxPoints = 0;

        reviewItems.forEach(function (item) {
            var status = String(item && item.status ? item.status : 'unanswered');
            var questionType = String(item && item.question_type ? item.question_type : '');
            var points = Number(item && item.points !== undefined ? item.points : 0);
            if (questionType === 'essay' && status === 'manual') {
                pendingEssayQuestions += 1;
                if (Number.isFinite(points) && points > 0) {
                    pendingEssayMaxPoints += points;
                }
            }

            if (!summary) {
                if (status === 'correct') {
                    correctQuestions += 1;
                } else if (status === 'wrong') {
                    wrongQuestions += 1;
                } else if (status === 'manual') {
                    manualQuestions += 1;
                } else {
                    unansweredQuestions += 1;
                }
            }
        });

        var summaryText = 'Total ' + totalQuestions + ' soal | Benar ' + correctQuestions + ' | Salah ' + wrongQuestions + ' | Belum dijawab ' + unansweredQuestions;
        if (manualQuestions > 0) {
            summaryText += ' | Perlu nilai guru ' + manualQuestions;
        }
        if (pendingEssayQuestions > 0) {
            summaryText += ' | Esai menunggu koreksi ' + pendingEssayQuestions + ' (maks ' + formatScoreValue(pendingEssayMaxPoints) + ' poin)';
        }

        return [
            '<section class="cbt-card cbt-review-card">',
            '<h3>Review Jawaban</h3>',
            '<p class="cbt-subtitle">' + escapeHtml(summaryText) + '</p>',
            '<div class="cbt-review-list">',
            reviewItems.map(renderReviewItem).join(''),
            '</div>',
            '</section>'
        ].join('');
    }

    function renderArchivedReviewHistorySection() {
        var archivedItems = Array.isArray(state.archivedReviewItems) ? state.archivedReviewItems : [];
        if (!archivedItems.length) {
            return '';
        }

        return [
            '<details class="cbt-archived-review-section">',
            '<summary class="cbt-archived-review-summary"><span class="cbt-archived-review-summary-row"><span class="cbt-archived-review-summary-label">History Soal Nonaktif (' + escapeHtml(archivedItems.length) + ')</span><span class="cbt-archived-review-close" aria-hidden="true">Tutup</span></span></summary>',
            '<p class="cbt-archived-review-note">Jawaban ini tetap tersimpan, tetapi soalnya sudah tidak aktif di exam saat ini.</p>',
            '<div class="cbt-review-list cbt-archived-review-list">',
            archivedItems.map(function (item) {
                return renderReviewItem(item);
            }).join(''),
            '</div>',
            '</details>'
        ].join('');
    }

    function renderResultStage() {
        var selectedExam = getSelectedExam();
        var resultExam = state.result && state.result.exam && typeof state.result.exam === 'object' ? state.result.exam : null;
        var resultAttempt = state.result && state.result.attempt && typeof state.result.attempt === 'object' ? state.result.attempt : null;
        var examTitle = resultExam && resultExam.title
            ? String(resultExam.title)
            : (selectedExam && selectedExam.title ? String(selectedExam.title) : '-');

        var score = state.result && state.result.score !== undefined
            ? Number(state.result.score)
            : Number(resultAttempt && resultAttempt.score !== undefined ? resultAttempt.score : 0);
        var maxScore = state.result && state.result.max_score !== undefined
            ? Number(state.result.max_score)
            : Number(resultAttempt && resultAttempt.max_score !== undefined ? resultAttempt.max_score : 0);
        var percentage = state.result && state.result.percentage !== undefined
            ? Number(state.result.percentage)
            : Number.NaN;

        if (!Number.isFinite(percentage)) {
            percentage = maxScore > 0 ? ((score / maxScore) * 100) : 0;
        }

        var safeScore = Number.isFinite(score) ? score : 0;
        var safeMaxScore = Number.isFinite(maxScore) ? maxScore : 0;
        var safePercentage = Number.isFinite(percentage) ? percentage : 0;
        var finalScoreText = safePercentage.toFixed(2);
        var reviewItems = state.result && Array.isArray(state.result.review_items) ? state.result.review_items : [];
        var pendingEssayCount = 0;
        var pendingEssayCurrentPoints = 0;
        var pendingEssayMaxPoints = 0;
        reviewItems.forEach(function (item) {
            var questionType = String(item && item.question_type ? item.question_type : '');
            var status = String(item && item.status ? item.status : 'unanswered');
            if (questionType !== 'essay' || status !== 'manual') {
                return;
            }

            pendingEssayCount += 1;
            var itemPoints = Number(item && item.points !== undefined ? item.points : 0);
            var itemAwarded = Number(item && item.score_awarded !== undefined ? item.score_awarded : 0);
            if (Number.isFinite(itemPoints) && itemPoints > 0) {
                pendingEssayMaxPoints += itemPoints;
            }
            if (Number.isFinite(itemAwarded) && itemAwarded > 0) {
                pendingEssayCurrentPoints += itemAwarded;
            }
        });
        pendingEssayCurrentPoints = Math.max(0, pendingEssayCurrentPoints);
        pendingEssayMaxPoints = Math.max(0, pendingEssayMaxPoints);
        var pendingEssayPotentialPoints = Math.max(0, pendingEssayMaxPoints - pendingEssayCurrentPoints);
        var pendingEssayInfoMarkup = '';
        if (pendingEssayCount > 0) {
            pendingEssayInfoMarkup = '<p class="cbt-subtitle">Esai menunggu koreksi: <strong>' + escapeHtml(pendingEssayCount) + ' soal</strong> | Poin esai sementara <strong>' + escapeHtml(formatScoreValue(pendingEssayCurrentPoints)) + '</strong> / <strong>' + escapeHtml(formatScoreValue(pendingEssayMaxPoints)) + '</strong> | Potensi tambah <strong>+' + escapeHtml(formatScoreValue(pendingEssayPotentialPoints)) + '</strong></p>';
        }

        return [
            '<div class="cbt-result-wrap">',
            '<section class="cbt-card cbt-result-card">',
            '<div class="cbt-result-hero">',
            '<h3>Ujian Selesai</h3>',
            '<p class="cbt-subtitle">' + escapeHtml(examTitle) + '</p>',
            '<div class="cbt-score">' + escapeHtml(safePercentage.toFixed(2)) + '%</div>',
            '<p class="cbt-subtitle"><strong>Nilai Akhir: ' + escapeHtml(finalScoreText) + '</strong></p>',
            '<p class="cbt-subtitle">Skor: <strong>' + escapeHtml(safeScore.toFixed(2)) + '</strong> dari <strong>' + escapeHtml(safeMaxScore.toFixed(2)) + '</strong></p>',
            '</div>',
            '<div class="cbt-result-body">',
            pendingEssayInfoMarkup,
            renderAlert(),
            '<div class="cbt-actions cbt-result-actions">',
            '<button class="cbt-button cbt-button-secondary" data-action="back-confirm" type="button">Kembali ke Daftar Exam</button>',
            '<button class="cbt-button cbt-button-danger" data-action="logout" type="button">Logout</button>',
            '</div>',
            '</div>',
            '</section>',
            renderResultReviewSection(),
            '</div>'
        ].join('');
    }

    function renderBody() {
        if (state.stage === 'login') {
            return renderLoginStage();
        }

        if (state.stage === 'confirm') {
            return renderConfirmStage();
        }

        if (state.stage === 'exam') {
            return renderExamStage();
        }

        if (state.stage === 'result') {
            return renderResultStage();
        }

        return '<section class="cbt-card"><p class="cbt-subtitle">Stage tidak dikenali.</p></section>';
    }

    function renderFinishConfirmModal() {
        if (!state.finishConfirmOpen || state.stage !== 'exam') {
            return '';
        }

        var summary = state.finishConfirmSummary || getExamProgressSummary();
        var totalQuestions = Number(summary.totalQuestions) || 0;
        var answeredQuestions = Number(summary.answeredQuestions) || 0;
        var unansweredQuestions = Number(summary.unansweredQuestions) || 0;
        var answeredPercentage = totalQuestions > 0 ? (answeredQuestions / totalQuestions) * 100 : 0;
        var progressWidth = Math.max(0, Math.min(100, answeredPercentage)).toFixed(2);
        var progressLabel = formatScoreValue(answeredPercentage);
        var warningMarkup = unansweredQuestions > 0
            ? '<div class="cbt-finish-modal-warning">Masih ada <strong>' + escapeHtml(unansweredQuestions) + '</strong> soal belum terjawab.</div>'
            : '<div class="cbt-finish-modal-ok">Semua soal sudah terjawab. Anda bisa lanjut kumpulkan ujian.</div>';

        return [
            '<div class="cbt-finish-modal-overlay">',
            '<section class="cbt-finish-modal" role="dialog" aria-modal="true" aria-labelledby="cbt-finish-modal-title">',
            '<h3 id="cbt-finish-modal-title">Konfirmasi Pengumpulan Ujian</h3>',
            '<p class="cbt-subtitle">Periksa jumlah jawaban sebelum ujian dikumpulkan.</p>',
            '<div class="cbt-finish-stat-grid">',
            '<div class="cbt-finish-stat"><span>Total Soal</span><strong>' + escapeHtml(totalQuestions) + '</strong></div>',
            '<div class="cbt-finish-stat is-answered"><span>Terjawab</span><strong>' + escapeHtml(answeredQuestions) + '</strong></div>',
            '<div class="cbt-finish-stat is-unanswered"><span>Belum Terjawab</span><strong>' + escapeHtml(unansweredQuestions) + '</strong></div>',
            '</div>',
            '<div class="cbt-finish-modal-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(progressWidth) + '">',
            '<span class="cbt-finish-modal-progress-fill" style="width: ' + escapeHtml(progressWidth) + '%;"></span>',
            '</div>',
            '<p class="cbt-muted">Progress jawaban: ' + escapeHtml(progressLabel) + '%</p>',
            warningMarkup,
            '<div class="cbt-actions cbt-finish-modal-actions">',
            '<button class="cbt-button cbt-button-secondary" data-action="finish-confirm-cancel" type="button"' + (state.isFinishing ? ' disabled' : '') + '>Kembali Kerjakan</button>',
            '<button class="cbt-button cbt-button-primary" data-action="finish-confirm-submit" type="button"' + (state.isFinishing ? ' disabled' : '') + '>' + (state.isFinishing ? 'Mengirim...' : 'Tetap Kumpulkan') + '</button>',
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function renderUserPhotoModal() {
        if (!state.userPhotoModalOpen) {
            return '';
        }

        var userPhoto = getCurrentUserPhoto();
        if (userPhoto === '') {
            return '';
        }

        var selectedExam = getSelectedExam();
        var userName = getCurrentUserName();
        var userKelas = state.user && state.user.kode_kelas ? String(state.user.kode_kelas) : '-';
        var userRuang = state.user && state.user.kode_ruang ? String(state.user.kode_ruang) : '-';
        var userAgama = state.user && state.user.agama ? String(state.user.agama) : '-';
        var activeExamTitle = selectedExam && selectedExam.title ? String(selectedExam.title) : '-';

        return [
            '<div class="cbt-user-photo-modal-overlay" data-action="close-user-photo">',
            '<section class="cbt-user-photo-modal" data-action="user-photo-modal-panel" role="dialog" aria-modal="true" aria-labelledby="cbt-user-photo-title">',
            '<button class="cbt-user-photo-modal-close" data-action="close-user-photo" type="button" aria-label="Tutup foto">&times;</button>',
            '<h3 id="cbt-user-photo-title">Foto Peserta</h3>',
            '<img class="cbt-user-photo-modal-image" src="' + escapeHtml(userPhoto) + '" alt="' + escapeHtml(userName) + '" loading="eager" decoding="sync" />',
            '<dl class="cbt-user-photo-modal-info">',
            '<div class="cbt-user-photo-modal-row cbt-user-photo-modal-row-wide"><dt>Ujian Aktif</dt><dd>' + escapeHtml(activeExamTitle) + '</dd></div>',
            '<div class="cbt-user-photo-modal-row"><dt>Nama</dt><dd>' + escapeHtml(userName) + '</dd></div>',
            '<div class="cbt-user-photo-modal-row"><dt>Kelas</dt><dd>' + escapeHtml(userKelas) + '</dd></div>',
            '<div class="cbt-user-photo-modal-row"><dt>Ruangan</dt><dd>' + escapeHtml(userRuang) + '</dd></div>',
            '<div class="cbt-user-photo-modal-row"><dt>Agama</dt><dd>' + escapeHtml(userAgama) + '</dd></div>',
            '</dl>',
            '</section>',
            '</div>'
        ].join('');
    }

    function ensureCurrentNavigationItemVisible() {
        if (state.stage !== 'exam' || !state.navPanelVisible) {
            return;
        }

        var navGrid = root.querySelector('.cbt-nav-grid');
        if (!(navGrid instanceof HTMLElement)) {
            return;
        }

        var examLayout = root.querySelector('.cbt-exam-layout');
        var navPosition = getEffectiveNavPanelPosition();
        if (examLayout instanceof HTMLElement) {
            if (examLayout.classList.contains('cbt-nav-pos-left')) {
                navPosition = 'left';
            } else if (examLayout.classList.contains('cbt-nav-pos-right')) {
                navPosition = 'right';
            } else {
                navPosition = 'top';
            }
        }
        var treatAsTopLayout = (navPosition === 'top') || window.innerWidth <= NAV_SIDE_LAYOUT_BREAKPOINT;

        var currentItem = navGrid.querySelector('.cbt-nav-btn.is-current');
        if (!(currentItem instanceof HTMLElement)) {
            return;
        }

        var gridRect = navGrid.getBoundingClientRect();
        var itemRect = currentItem.getBoundingClientRect();
        var gutter = 14;
        var outsideViewport;
        if (treatAsTopLayout) {
            outsideViewport = itemRect.left < (gridRect.left + gutter) || itemRect.right > (gridRect.right - gutter);
        } else {
            outsideViewport = itemRect.top < (gridRect.top + gutter) || itemRect.bottom > (gridRect.bottom - gutter);
        }

        if (!outsideViewport) {
            return;
        }

        currentItem.scrollIntoView({
            block: 'nearest',
            inline: treatAsTopLayout ? 'center' : 'nearest'
        });
    }

    function updateNavigationGridRows() {
        if (state.stage !== 'exam' || !state.navPanelVisible) {
            return;
        }

        var navGrid = root.querySelector('.cbt-nav-grid');
        if (!(navGrid instanceof HTMLElement)) {
            return;
        }

        var examLayout = root.querySelector('.cbt-exam-layout');
        var navPosition = getEffectiveNavPanelPosition();
        if (examLayout instanceof HTMLElement) {
            if (examLayout.classList.contains('cbt-nav-pos-left')) {
                navPosition = 'left';
            } else if (examLayout.classList.contains('cbt-nav-pos-right')) {
                navPosition = 'right';
            } else {
                navPosition = 'top';
            }
        }
        var treatAsTopLayout = (navPosition === 'top') || window.innerWidth <= NAV_SIDE_LAYOUT_BREAKPOINT;
        if (!treatAsTopLayout) {
            navGrid.style.setProperty('--cbt-nav-rows', '1');
            return;
        }

        var navItems = navGrid.querySelectorAll('.cbt-nav-btn');
        var itemCount = navItems ? navItems.length : 0;
        if (itemCount <= 0) {
            navGrid.style.setProperty('--cbt-nav-rows', '1');
            return;
        }

        var firstItem = navItems[0];
        if (!(firstItem instanceof HTMLElement)) {
            navGrid.style.setProperty('--cbt-nav-rows', '1');
            return;
        }

        var availableWidth = navGrid.clientWidth;
        if (availableWidth <= 0) {
            return;
        }

        var navGridStyle = window.getComputedStyle(navGrid);
        var columnGap = parseFloat(String(navGridStyle.columnGap || navGridStyle.gap || '0'));
        if (!Number.isFinite(columnGap) || columnGap < 0) {
            columnGap = 0;
        }

        var itemWidth = firstItem.offsetWidth;
        if (!Number.isFinite(itemWidth) || itemWidth <= 0) {
            itemWidth = parseFloat(String(navGridStyle.gridAutoColumns || '0'));
        }
        if (!Number.isFinite(itemWidth) || itemWidth <= 0) {
            itemWidth = 46;
        }

        var singleRowRequiredWidth = (itemWidth * itemCount) + (columnGap * Math.max(0, itemCount - 1));
        var targetRows = singleRowRequiredWidth <= (availableWidth + 1) ? '1' : '2';
        if (navGrid.style.getPropertyValue('--cbt-nav-rows') !== targetRows) {
            navGrid.style.setProperty('--cbt-nav-rows', targetRows);
        }
    }

    function scheduleNavigationGridLayout() {
        if (navGridLayoutFrameId) {
            return;
        }

        navGridLayoutFrameId = window.requestAnimationFrame(function () {
            navGridLayoutFrameId = 0;
            updateNavigationGridRows();
            ensureCurrentNavigationItemVisible();
        });
    }

    function fitLoginHeroSchoolName() {
        if (state.stage !== 'login') {
            return;
        }

        var titleNode = root.querySelector('.cbt-login-hero-heading h1');
        if (!(titleNode instanceof HTMLElement)) {
            return;
        }

        titleNode.style.removeProperty('font-size');

        var computed = window.getComputedStyle(titleNode);
        var baseFontSize = parseFloat(String(computed.fontSize || '0'));
        if (!Number.isFinite(baseFontSize) || baseFontSize <= 0) {
            return;
        }

        var lineHeight = parseFloat(String(computed.lineHeight || '0'));
        if (!Number.isFinite(lineHeight) || lineHeight <= 0) {
            lineHeight = baseFontSize * 1.08;
        }

        var fitsInTwoLines = function () {
            var currentComputed = window.getComputedStyle(titleNode);
            var currentLineHeight = parseFloat(String(currentComputed.lineHeight || '0'));
            if (!Number.isFinite(currentLineHeight) || currentLineHeight <= 0) {
                var currentFontSize = parseFloat(String(currentComputed.fontSize || '0'));
                currentLineHeight = (Number.isFinite(currentFontSize) && currentFontSize > 0) ? currentFontSize * 1.08 : lineHeight;
            }
            var allowedHeight = (currentLineHeight * 2) + 1;
            return titleNode.scrollHeight <= allowedHeight;
        };

        if (fitsInTwoLines()) {
            return;
        }

        var minFontSize = Math.max(20, Math.round(baseFontSize * 0.58));
        var currentFontSize = Math.round(baseFontSize);
        while (currentFontSize > minFontSize) {
            currentFontSize -= 1;
            titleNode.style.fontSize = String(currentFontSize) + 'px';
            if (fitsInTwoLines()) {
                return;
            }
        }
    }

    function render() {
        var loadingMarkup = state.busy && state.stage !== 'exam' && state.stage !== 'login'
            ? '<div class="cbt-loading" role="status" aria-live="polite"><span class="cbt-loading-dot" aria-hidden="true"></span><span>Memproses...</span></div>'
            : '';
        var showTopbar = state.stage !== 'login';
        var containerClass = showTopbar ? 'cbt-container' : 'cbt-container cbt-container-login';
        var currentStage = String(state.stage || 'login');
        var stageChanged = lastRenderedStage !== currentStage;
        var stagePanelClass = 'cbt-stage-panel cbt-stage-panel-' + currentStage + (stageChanged ? ' is-stage-enter' : '');

        root.innerHTML = [
            '<div class="cbt-app">',
            showTopbar ? renderTopbar() : '',
            '<main class="' + containerClass + '">',
            loadingMarkup,
            '<section class="' + stagePanelClass + '">',
            renderBody(),
            '</section>',
            '</main>',
            renderFinishConfirmModal(),
            renderUserPhotoModal(),
            '</div>'
        ].join('');
        lastRenderedStage = currentStage;

        applyUiPreferences();
        syncBodyStageClass();
        updateTimerLabel();
        fitLoginHeroSchoolName();
        scheduleNavigationGridLayout();
        updateQuestionPrefetchIndicator();
    }

    function syncBodyStageClass() {
        if (!document.body) {
            return;
        }

        var classes = ['cbt-stage-login', 'cbt-stage-confirm', 'cbt-stage-exam', 'cbt-stage-result'];
        for (var i = 0; i < classes.length; i++) {
            document.body.classList.remove(classes[i]);
        }
        document.body.classList.add('cbt-stage-' + String(state.stage || 'login'));
    }

    root.addEventListener('submit', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLFormElement)) {
            return;
        }

        if (target.id === 'cbt-login-form') {
            event.preventDefault();
            handleLogin(target);
        }
    });

    root.addEventListener('click', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        var actionNode = target.closest('[data-action]');
        if (!(actionNode instanceof HTMLElement)) {
            return;
        }

        var action = actionNode.getAttribute('data-action');
        if (!action) {
            return;
        }

        if (action === 'enter-fullscreen') {
            requestExamFullscreen({
                silent: false
            }).then(function (entered) {
                if (entered) {
                    clearMessages();
                }
                render();
            });
            return;
        }

        if (isExamFullscreenBlockingActive() && action !== 'logout' && action !== 'toggle-theme') {
            event.preventDefault();
            return;
        }

        if (state.stage === 'exam') {
            noteQuestionPrefetchActivity();
        }

        if (action === 'font-dec') {
            if (updateFontScale(state.fontScale - FONT_SCALE_STEP)) {
                render();
            }
            return;
        }

        if (action === 'font-inc') {
            if (updateFontScale(state.fontScale + FONT_SCALE_STEP)) {
                render();
            }
            return;
        }

        if (action === 'font-reset') {
            if (updateFontScale(FONT_SCALE_DEFAULT)) {
                render();
            }
            return;
        }

        if (action === 'toggle-theme') {
            toggleTheme();
            render();
            return;
        }

        if (action === 'logout') {
            fullLogout();
            return;
        }

        if (action === 'open-user-photo') {
            if (getCurrentUserPhoto() === '') {
                return;
            }
            state.userPhotoModalOpen = true;
            render();
            return;
        }

        if (action === 'close-user-photo') {
            state.userPhotoModalOpen = false;
            render();
            return;
        }

        if (action === 'user-photo-modal-panel') {
            return;
        }

        if (action === 'toggle-password') {
            state.loginPasswordVisible = !state.loginPasswordVisible;
            render();
            var passwordInput = root.querySelector('#cbt-password');
            if (passwordInput instanceof HTMLInputElement) {
                passwordInput.focus();
                var caret = passwordInput.value.length;
                try {
                    passwordInput.setSelectionRange(caret, caret);
                } catch (error) {
                    // Ignore browsers that block selection changes on certain input types.
                }
            }
            return;
        }

        if (action === 'reload-exams') {
            if (state.busy) {
                return;
            }
            state.busy = true;
            clearMessages();
            render();
            loadExams()
                .then(function () {
                    state.error = '';
                })
                .catch(function (error) {
                    state.error = error instanceof Error ? error.message : 'Gagal memuat exam.';
                })
                .finally(function () {
                    state.busy = false;
                    render();
                });
            return;
        }

        if (action === 'select-exam') {
            var examId = Number(actionNode.getAttribute('data-id')) || 0;
            updateSelectedExam(examId);
            return;
        }

        if (action === 'toggle-exam-picker-mobile') {
            if (state.busy) {
                return;
            }

            state.examPickerMobileOpen = !state.examPickerMobileOpen;
            render();

            if (state.examPickerMobileOpen) {
                var activePickerOption = root.querySelector('.cbt-exam-picker-option.is-active, .cbt-exam-picker-option');
                if (activePickerOption instanceof HTMLButtonElement) {
                    activePickerOption.focus();
                }
            }
            return;
        }

        if (action === 'select-exam-mobile') {
            var mobileExamId = Number(actionNode.getAttribute('data-id')) || 0;
            updateSelectedExam(mobileExamId);
            return;
        }

        if (action === 'start-exam') {
            handleStartExam();
            return;
        }

        if (action === 'view-result') {
            handleViewResult();
            return;
        }

        if (action === 'toggle-nav') {
            state.navPanelVisible = !state.navPanelVisible;
            render();
            return;
        }

        if (action === 'toggle-calculator') {
            if (state.stage !== 'exam') {
                return;
            }
            state.calculatorVisible = !state.calculatorVisible;
            if (!state.calculatorVisible) {
                state.calculatorError = '';
            }
            render();
            if (state.calculatorVisible) {
                focusCalculatorInput();
            }
            return;
        }

        if (action === 'calc-key') {
            if (state.stage !== 'exam') {
                return;
            }
            var calcKey = String(actionNode.getAttribute('data-value') || '');
            if (calcKey === '') {
                return;
            }
            var nextExpression = String(state.calculatorExpression || '') + calcKey;
            state.calculatorExpression = normalizeCalculatorExpression(nextExpression);
            state.calculatorResult = '';
            state.calculatorError = '';
            render();
            focusCalculatorInput();
            return;
        }

        if (action === 'calc-clear') {
            if (state.stage !== 'exam') {
                return;
            }
            state.calculatorExpression = '';
            state.calculatorResult = '';
            state.calculatorError = '';
            render();
            focusCalculatorInput();
            return;
        }

        if (action === 'calc-backspace') {
            if (state.stage !== 'exam') {
                return;
            }
            var expression = String(state.calculatorExpression || '');
            state.calculatorExpression = expression.length > 0 ? expression.slice(0, -1) : '';
            state.calculatorResult = '';
            state.calculatorError = '';
            render();
            focusCalculatorInput();
            return;
        }

        if (action === 'calc-eval') {
            if (state.stage !== 'exam') {
                return;
            }
            applyCalculatorEvaluation();
            render();
            focusCalculatorInput();
            return;
        }

        if (action === 'set-nav-position') {
            var requestedPosition = String(actionNode.getAttribute('data-position') || '');
            if (isCompactNavViewport() && (requestedPosition === 'left' || requestedPosition === 'right')) {
                return;
            }
            if (updateNavPanelPosition(requestedPosition)) {
                render();
            }
            return;
        }

        if (action === 'set-calc-position') {
            var requestedCalcPosition = String(actionNode.getAttribute('data-position') || '');
            if (isCompactViewport() && (requestedCalcPosition === 'left' || requestedCalcPosition === 'right')) {
                return;
            }
            if (updateCalculatorPanelPosition(requestedCalcPosition)) {
                render();
                if (state.calculatorVisible) {
                    focusCalculatorInput();
                }
            }
            return;
        }

        if (action === 'prev') {
            goToQuestion(state.currentIndex - 1);
            return;
        }

        if (action === 'next') {
            goToQuestion(state.currentIndex + 1);
            return;
        }

        if (action === 'jump') {
            var index = Number(actionNode.getAttribute('data-index'));
            goToQuestion(index);
            return;
        }

        if (action === 'filter-nav') {
            var requestedFilter = normalizeNavigationQuestionFilter(actionNode.getAttribute('data-filter'));
            var nextFilter = requestedFilter === normalizeNavigationQuestionFilter(state.navQuestionFilter)
                ? NAV_QUESTION_FILTER_ALL
                : requestedFilter;
            state.navQuestionFilter = nextFilter;

            if (nextFilter !== NAV_QUESTION_FILTER_ALL) {
                var filteredEntries = getNavigationQuestionEntries(nextFilter);
                var currentQuestionForFilter = getQuestionAtIndex(state.currentIndex);
                if (
                    filteredEntries.length
                    && !questionMatchesNavigationFilter(currentQuestionForFilter, nextFilter)
                ) {
                    goToQuestion(filteredEntries[0].index);
                    return;
                }
            }

            render();
            return;
        }

        if (action === 'finish') {
            handleFinish(false);
            return;
        }

        if (action === 'collect') {
            handleFinish(false);
            return;
        }

        if (action === 'finish-confirm-cancel') {
            closeFinishConfirmModal();
            return;
        }

        if (action === 'finish-confirm-submit') {
            handleFinish(false, { skipConfirmation: true });
            return;
        }

        if (action === 'toggle-doubtful') {
            var doubtfulQid = Number(actionNode.getAttribute('data-qid')) || 0;
            if (doubtfulQid > 0) {
                state.doubtful[doubtfulQid] = !state.doubtful[doubtfulQid];
                if (!state.doubtful[doubtfulQid]) {
                    delete state.doubtful[doubtfulQid];
                }
                persistCurrentAttemptUiStateLocally();
                scheduleAttemptUiStateSync(ATTEMPT_UI_STATE_SYNC_DELAY_MS);
                clearMessages();
                render();
            }
            return;
        }

        if (action === 'back-confirm') {
            flushPendingAnswerBatchSilently({
                flushAll: true,
                keepalive: true
            });
            flushAttemptUiStateSilently({
                force: true,
                keepalive: true
            });
            resetExamSession();
            state.stage = 'confirm';
            state.busy = false;
            clearMessages();
            render();
        }
    });

    root.addEventListener('change', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        var targetAction = String(target.getAttribute('data-action') || '');

        if (isExamFullscreenBlockingActive()) {
            return;
        }

        if (isQuestionRevisionRefreshActive() && targetAction.indexOf('answer-') === 0) {
            return;
        }

        if (state.stage === 'exam') {
            noteQuestionPrefetchActivity();
        }

        if (targetAction === 'select-exam-mobile') {
            if (target instanceof HTMLSelectElement) {
                updateSelectedExam(target.value);
            }
            return;
        }

        if (targetAction === 'answer-single') {
            var singleQid = Number(target.getAttribute('data-qid')) || 0;
            var singleOptionId = Number(target.getAttribute('data-option-id')) || 0;
            if (singleQid > 0 && singleOptionId > 0) {
                state.answers[singleQid] = singleOptionId;
                state.answeredQuestionLookup[singleQid] = true;
                scheduleQuestionCachePersist(200);
                clearMessages();
                scheduleAutoSave(singleQid, AUTO_SAVE_CHOICE_DELAY_MS);
                render();
            }
            return;
        }

        if (targetAction === 'answer-multi') {
            var multiQid = Number(target.getAttribute('data-qid')) || 0;
            var multiOptionId = Number(target.getAttribute('data-option-id')) || 0;
            if (multiQid <= 0 || multiOptionId <= 0) {
                return;
            }
            var selected = Array.isArray(state.answers[multiQid]) ? state.answers[multiQid].slice() : [];
            var checked = false;
            if (target instanceof HTMLInputElement) {
                checked = target.checked;
            }

            if (checked && selected.indexOf(multiOptionId) < 0) {
                selected.push(multiOptionId);
            }
            if (!checked) {
                selected = selected.filter(function (item) { return Number(item) !== multiOptionId; });
            }

            state.answers[multiQid] = selected;
            if (selected.length > 0) {
                state.answeredQuestionLookup[multiQid] = true;
            } else {
                delete state.answeredQuestionLookup[multiQid];
            }
            scheduleQuestionCachePersist(200);
            clearMessages();
            scheduleAutoSave(multiQid, AUTO_SAVE_CHOICE_DELAY_MS);
            render();
            return;
        }

        if (targetAction === 'answer-tf-matrix') {
            var matrixQid = Number(target.getAttribute('data-qid')) || 0;
            var matrixKey = String(target.getAttribute('data-key') || '').trim();
            var matrixValue = String(target.getAttribute('data-value') || '').trim().toLowerCase();
            if (matrixQid <= 0 || matrixKey === '' || (matrixValue !== 'true' && matrixValue !== 'false')) {
                return;
            }
            if (!state.answers[matrixQid] || typeof state.answers[matrixQid] !== 'object' || Array.isArray(state.answers[matrixQid])) {
                state.answers[matrixQid] = {};
            }
            state.answers[matrixQid][matrixKey] = matrixValue;
            state.answeredQuestionLookup[matrixQid] = true;
            scheduleQuestionCachePersist(240);
            clearMessages();
            scheduleAutoSave(matrixQid, AUTO_SAVE_CHOICE_DELAY_MS);
            render();
        }
    });

    document.addEventListener('click', function (event) {
        if (!state.examPickerMobileOpen) {
            return;
        }

        var target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.closest('.cbt-exam-picker-dropdown')) {
            return;
        }

        state.examPickerMobileOpen = false;
        render();
    });

    root.addEventListener('input', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        var action = String(target.getAttribute('data-action') || '');

        if (isExamFullscreenBlockingActive()) {
            return;
        }

        if (isQuestionRevisionRefreshActive() && action.indexOf('answer-') === 0) {
            return;
        }

        if (state.stage === 'exam') {
            noteQuestionPrefetchActivity();
        }

        if (target.getAttribute('name') === 'identifier') {
            if (target instanceof HTMLInputElement) {
                state.loginIdentifier = String(target.value || '');
            }
            return;
        }

        if (target.getAttribute('name') === 'password') {
            if (target instanceof HTMLInputElement) {
                state.loginPassword = String(target.value || '');
            }
            return;
        }

        if (target.getAttribute('name') === 'exam_token') {
            if (target instanceof HTMLInputElement) {
                state.examToken = normalizeExamToken(target.value || '');
                if (target.value !== state.examToken) {
                    target.value = state.examToken;
                }
            }
            return;
        }

        if (target.getAttribute('name') === 'calc_expression') {
            if (target instanceof HTMLInputElement) {
                state.calculatorExpression = normalizeCalculatorExpression(target.value || '');
                if (target.value !== state.calculatorExpression) {
                    target.value = state.calculatorExpression;
                }
                state.calculatorResult = '';
                state.calculatorError = '';
            }
            return;
        }

        if (action === 'answer-short') {
            var shortQid = Number(target.getAttribute('data-qid')) || 0;
            var shortKey = String(target.getAttribute('data-short-key') || '').trim().toUpperCase();
            if (shortQid <= 0 || shortKey === '') {
                return;
            }
            if (!state.answers[shortQid] || typeof state.answers[shortQid] !== 'object') {
                state.answers[shortQid] = {};
            }
            var shortValue = '';
            if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
                shortValue = String(target.value || '');
                state.answers[shortQid][shortKey] = shortValue;
            }
            var shortAnswerValues = state.answers[shortQid] && typeof state.answers[shortQid] === 'object'
                ? Object.keys(state.answers[shortQid]).map(function (key) {
                    return String(state.answers[shortQid][key] || '');
                })
                : [];
            if (shortAnswerValues.some(function (value) { return value !== ''; })) {
                state.answeredQuestionLookup[shortQid] = true;
            } else {
                delete state.answeredQuestionLookup[shortQid];
            }

            var mirrorSelector = '[data-action="answer-short"][data-qid="' + shortQid + '"][data-short-key="' + shortKey + '"]';
            var mirrorInputs = root.querySelectorAll(mirrorSelector);
            for (var mirrorIndex = 0; mirrorIndex < mirrorInputs.length; mirrorIndex++) {
                var mirrorNode = mirrorInputs[mirrorIndex];
                if (!(mirrorNode instanceof HTMLInputElement || mirrorNode instanceof HTMLTextAreaElement)) {
                    continue;
                }
                if (mirrorNode === target) {
                    continue;
                }
                if (mirrorNode.value !== shortValue) {
                    mirrorNode.value = shortValue;
                }
            }
            scheduleQuestionCachePersist(500);
            scheduleAutoSave(shortQid, AUTO_SAVE_TEXT_DELAY_MS);
            return;
        }

        if (action === 'answer-text') {
            var textQid = Number(target.getAttribute('data-qid')) || 0;
            if (textQid <= 0) {
                return;
            }
            if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
                state.answers[textQid] = target.value;
                if (String(target.value || '') !== '') {
                    state.answeredQuestionLookup[textQid] = true;
                } else {
                    delete state.answeredQuestionLookup[textQid];
                }
            }
            scheduleQuestionCachePersist(500);
            scheduleAutoSave(textQid, AUTO_SAVE_TEXT_DELAY_MS);
        }
    });

    function shouldIgnoreArrowQuestionNavigation() {
        var activeElement = document.activeElement;
        if (!(activeElement instanceof HTMLElement)) {
            return false;
        }

        if (activeElement.isContentEditable) {
            return true;
        }

        if (
            activeElement instanceof HTMLInputElement
            || activeElement instanceof HTMLTextAreaElement
            || activeElement instanceof HTMLSelectElement
        ) {
            return true;
        }

        return !!activeElement.closest('.cbt-calc-panel');
    }

    document.addEventListener('keydown', function (event) {
        if (!event) {
            return;
        }

        if ((event.ctrlKey || event.metaKey || event.shiftKey) && isExamClipboardBlockingActive()) {
            var key = String(event.key || '').toLowerCase();
            var shouldBlockClipboardShortcut = false;
            var clipboardAction = '';

            if ((event.ctrlKey || event.metaKey) && (key === 'c' || key === 'x' || key === 'v')) {
                shouldBlockClipboardShortcut = true;
                clipboardAction = key === 'c' ? 'copy' : (key === 'x' ? 'cut' : 'paste');
            } else if ((event.ctrlKey || event.metaKey) && key === 'insert') {
                shouldBlockClipboardShortcut = true;
                clipboardAction = 'copy';
            } else if (event.shiftKey && key === 'insert') {
                shouldBlockClipboardShortcut = true;
                clipboardAction = 'paste';
            } else if (event.shiftKey && key === 'delete') {
                shouldBlockClipboardShortcut = true;
                clipboardAction = 'cut';
            }

            if (shouldBlockClipboardShortcut && handleBlockedClipboardAction(clipboardAction, event)) {
                return;
            }
        }

        if (isExamFullscreenBlockingActive()) {
            return;
        }

        if (
            state.stage === 'exam'
            && !state.finishConfirmOpen
            && !state.userPhotoModalOpen
            && !event.altKey
            && !event.ctrlKey
            && !event.metaKey
            && !event.repeat
            && !shouldIgnoreArrowQuestionNavigation()
        ) {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                goToQuestion(state.currentIndex - 1);
                return;
            }

            if (event.key === 'ArrowRight') {
                event.preventDefault();
                goToQuestion(state.currentIndex + 1);
                return;
            }
        }

        if (event.key === 'Escape') {
            if (state.examPickerMobileOpen) {
                event.preventDefault();
                state.examPickerMobileOpen = false;
                render();
                return;
            }

            if (state.userPhotoModalOpen) {
                event.preventDefault();
                state.userPhotoModalOpen = false;
                render();
                return;
            }

            if (state.finishConfirmOpen && !state.isFinishing) {
                event.preventDefault();
                closeFinishConfirmModal();
                return;
            }

            if (state.stage === 'exam' && state.calculatorVisible) {
                event.preventDefault();
                state.calculatorVisible = false;
                state.calculatorError = '';
                render();
            }
            return;
        }

        if (event.key === 'Enter' && state.stage === 'exam') {
            var activeElement = document.activeElement;
            if (!(activeElement instanceof HTMLInputElement) || activeElement.getAttribute('name') !== 'calc_expression') {
                return;
            }
            event.preventDefault();
            applyCalculatorEvaluation();
            render();
            focusCalculatorInput();
        }
    });

    window.addEventListener('resize', function () {
        var nextCompactState = isCompactViewport();
        if (nextCompactState !== compactViewportState) {
            compactViewportState = nextCompactState;
            render();
            return;
        }
        fitLoginHeroSchoolName();
        scheduleNavigationGridLayout();
    });

    ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'].forEach(function (eventName) {
        document.addEventListener(eventName, function () {
            var wasFullscreenActive = state.isFullscreenActive;
            syncFullscreenState(true);

            if (
                wasFullscreenActive
                && !state.isFullscreenActive
                && isExamFullscreenRequired()
                && isSecurityLoggingActiveForAttempt()
                && Date.now() > fullscreenExitLogSuppressedUntil
            ) {
                sendSecurityEventSilently('fullscreen_exit', {
                    source: 'fullscreenchange'
                }, {
                    attemptId: Number(state.attemptId) || 0,
                    keepalive: true,
                    debounceMs: 1500,
                    requireFullscreen: true
                });
            }
        });
    });

    ['copy', 'cut', 'paste'].forEach(function (eventName) {
        document.addEventListener(eventName, function (event) {
            handleBlockedClipboardAction(eventName, event);
        }, true);
    });

    document.addEventListener('beforeinput', function (event) {
        var inputType = event && event.inputType ? String(event.inputType) : '';

        if (!isExamClipboardBlockingActive()) {
            return;
        }

        if (inputType === 'insertFromPaste' || inputType === 'insertFromPasteAsQuotation' || inputType === 'deleteByCut') {
            handleBlockedClipboardAction(inputType.indexOf('deleteByCut') === 0 ? 'cut' : 'paste', event);
        }
    });

    if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
        document.fonts.ready.then(function () {
            fitLoginHeroSchoolName();
        }).catch(function () {
            // Ignore font loading errors.
        });
    }

    function flushPendingAnswerBatchSilently(options) {
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
            return;
        }

        queueLoadedQuestionAnswersForFlush();
        flushPendingAnswerBatch(options || {}).catch(function () {
            // Best-effort flush for visibility/page lifecycle events.
        });
    }

    function flushAttemptUiStateSilently(options) {
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
            return;
        }

        flushAttemptUiState(options || {}).catch(function () {
            // Best-effort sync for navigation and page lifecycle events.
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            cancelScheduledTabHiddenSecurityLog();
            cancelScheduledWindowBlurSecurityLog();
        }

        if (document.visibilityState === 'hidden') {
            cancelScheduledWindowBlurSecurityLog();
            scheduleTabHiddenSecurityLog();
            persistCurrentQuestionCacheLocally();
            flushPendingAnswerBatchSilently({
                flushAll: true,
                keepalive: true
            });
            flushAttemptUiStateSilently({
                force: true,
                keepalive: true
            });
        }
    });

    window.addEventListener('blur', function () {
        if (!isWindowBlurLoggingActiveForAttempt()) {
            return;
        }
        if (document.visibilityState === 'hidden') {
            return;
        }

        scheduleWindowBlurSecurityLog('blur');
        persistCurrentQuestionCacheLocally();
        flushPendingAnswerBatchSilently({
            flushAll: true,
            keepalive: true
        });
        flushAttemptUiStateSilently({
            force: true,
            keepalive: true
        });
    });

    window.addEventListener('focus', function () {
        cancelScheduledWindowBlurSecurityLog();
    });

    window.addEventListener('pagehide', function () {
        logPageLeaveSecurityEvent('pagehide');
        persistCurrentQuestionCacheLocally();
        flushPendingAnswerBatchSilently({
            flushAll: true,
            keepalive: true
        });
        flushAttemptUiStateSilently({
            force: true,
            keepalive: true
        });
    });

    window.addEventListener('beforeunload', function () {
        logPageLeaveSecurityEvent('beforeunload');
        persistCurrentQuestionCacheLocally();
        flushPendingAnswerBatchSilently({
            flushAll: true,
            keepalive: true
        });
        flushAttemptUiStateSilently({
            force: true,
            keepalive: true
        });
    });

    async function bootstrapFromPersistedSession() {
        var persisted = readPersistedAuthSession();
        if (!persisted) {
            render();
            return;
        }

        state.token = persisted.token;
        state.user = persisted.user;
        state.selectedExamId = persisted.selectedExamId;
        state.stage = 'confirm';
        state.busy = true;
        clearMessages();
        render();

        try {
            await loadExams();
            var resumed = await tryResumeActiveAttemptFromExamList({
                selectedOnly: Number(persisted.selectedExamId) > 0
            });
            if (resumed) {
                persistAuthSession();
                startSessionHeartbeat();
                state.busy = false;
                render();
                return;
            }

            state.stage = 'confirm';
            state.error = '';
            state.success = '';
            persistAuthSession();
            startSessionHeartbeat();
            state.busy = false;
            render();
        } catch (error) {
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

    var persistedUiPreferences = readPersistedUiPreferences();
    if (persistedUiPreferences) {
        state.uiTheme = persistedUiPreferences.theme;
        state.fontScale = persistedUiPreferences.fontScale;
        state.navPanelPosition = persistedUiPreferences.navPanelPosition;
        state.calculatorPosition = persistedUiPreferences.calculatorPosition;
    }
    compactViewportState = isCompactViewport();
    applyUiPreferences();
    syncFullscreenState(false);

    bootstrapFromPersistedSession();
})();
