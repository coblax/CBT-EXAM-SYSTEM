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
        success: '',
        loginIdentifier: '',
        loginPassword: '',
        loginPasswordVisible: false,
        token: '',
        user: null,
        exams: [],
        selectedExamId: 0,
        examToken: '',
        attemptId: 0,
        questions: [],
        questionOrderIds: [],
        questionManifest: [],
        questionManifestById: {},
        questionPayloadById: {},
        answeredQuestionLookup: {},
        loadedQuestionWindowOffsets: {},
        windowOffset: 0,
        windowLimit: 0,
        totalQuestions: 0,
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
        calculatorError: ''
    };
    var AUTO_SAVE_CHOICE_DELAY_MS = 2000;
    var AUTO_SAVE_TEXT_DELAY_MS = 3500;
    var AUTO_SAVE_CHOICE_DELAY_CONGESTED_MS = 2600;
    var AUTO_SAVE_TEXT_DELAY_CONGESTED_MS = 4600;
    var AUTO_SAVE_CONGESTED_WINDOW_MS = 15000;
    var AUTO_SAVE_BATCH_MAX_ITEMS = 20;
    var ATTEMPT_UI_STATE_SYNC_DELAY_MS = 500;
    var SESSION_HEARTBEAT_INTERVAL_MS = 20000;
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

    function normalizeQuestionManifestItem(question) {
        var item = question && typeof question === 'object' ? question : {};
        var questionId = Number(item.id) || 0;
        if (questionId <= 0) {
            return null;
        }

        var normalized = {
            id: questionId,
            question_type: String(item.question_type || '')
        };

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

    function resetQuestionPrefetchIdleTimer() {
        clearQuestionPrefetchIdleTimer();

        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
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
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
            return;
        }

        resetQuestionPrefetchIdleTimer();
    }

    function prefetchNextQuestionBatch() {
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
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
        });

        questionPrefetchInFlightByOffset[offset] = request;
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

        var questionType = String(question.question_type || '');
        if (questionType === 'multiple_choice' || questionType === 'true_false') {
            var single = Number(existing) || 0;
            return {
                hasValue: single > 0,
                value: single > 0 ? single : null
            };
        }

        if (questionType === 'multiple_answer') {
            if (!Array.isArray(existing)) {
                return {
                    hasValue: false,
                    value: null
                };
            }

            var selected = existing
                .map(function (item) { return Number(item) || 0; })
                .filter(function (item) { return item > 0; });

            return {
                hasValue: selected.length > 0,
                value: selected
            };
        }

        if (questionType === 'true_false_matrix') {
            var matrixValue = normalizeTrueFalseMatrixAnswer(existing);
            return {
                hasValue: Object.keys(matrixValue).length > 0,
                value: matrixValue
            };
        }

        if (questionType === 'short_answer') {
            if (!existing || typeof existing !== 'object' || Array.isArray(existing)) {
                return {
                    hasValue: false,
                    value: null
                };
            }

            var normalizedShort = Object.keys(existing).reduce(function (accumulator, key) {
                var safeKey = String(key || '').trim().toUpperCase();
                if (safeKey === '') {
                    return accumulator;
                }
                accumulator[safeKey] = String(existing[key] || '');
                return accumulator;
            }, {});

            return {
                hasValue: Object.keys(normalizedShort).length > 0,
                value: normalizedShort
            };
        }

        var textValue = String(existing || '');
        return {
            hasValue: textValue !== '',
            value: textValue !== '' ? textValue : null
        };
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

            var normalized = normalizeExistingAnswerForQuestion(question);
            if (!normalized.hasValue) {
                return;
            }

            if (!overwriteExisting && Object.prototype.hasOwnProperty.call(state.answers, questionId)) {
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

            if (!overwriteExisting && Object.prototype.hasOwnProperty.call(state.answers, questionId)) {
                return;
            }

            state.answers[questionId] = existingAnswersMap[key];
            state.answeredQuestionLookup[questionId] = true;
        });
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

        if (normalizedOrderIds.length) {
            state.questionOrderIds = normalizedOrderIds;
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

    function runSessionHeartbeat() {
        if (!state.token || state.stage === 'login') {
            return Promise.resolve(null);
        }

        if (sessionHeartbeatInFlight) {
            return sessionHeartbeatInFlight;
        }

        sessionHeartbeatInFlight = api('session')
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

        var canApplyQuestionPayload = (
            Number(state.attemptId) === attemptId &&
            Number(state.selectedExamId) === examId &&
            (state.stage === 'exam' || state.stage === 'confirm')
        );

        if (!canApplyQuestionPayload) {
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

        if (isIndexInCurrentWindow(safeIndex) && isQuestionPayloadLoaded(questionId)) {
            return getQuestionPayloadById(questionId);
        }

        if (isQuestionWindowLoaded(targetOffset) && isQuestionPayloadLoaded(questionId)) {
            state.windowOffset = targetOffset;
            state.windowLimit = Math.max(1, Number(options.limit) || QUESTION_WINDOW_SIZE);
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

        return getQuestionPayloadById(questionId);
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
                scheduleAttemptUiStateSync(150);
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
        if (state.success) {
            return '<div class="cbt-alert cbt-alert-success">' + escapeHtml(state.success) + '</div>';
        }
        return '';
    }

    function clearMessages() {
        state.error = '';
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
        var answer = state.answers[questionId];
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
        var hasLocalAnswer = Object.prototype.hasOwnProperty.call(state.answers, questionId);
        var answer = hasLocalAnswer ? state.answers[questionId] : undefined;

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

        if (type === 'multiple_choice') {
            var selectedOptionId = Number(state.answers[question.id]) || 0;
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
            var selectedTrueFalseOptionId = Number(state.answers[question.id]) || 0;
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
            var selectedOptionIds = Array.isArray(state.answers[question.id])
                ? state.answers[question.id].map(function (item) { return Number(item) || 0; }).filter(function (item) { return item > 0; })
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
            var matrixAnswer = normalizeTrueFalseMatrixAnswer(state.answers[question.id]);
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

        var answer = state.answers[question.id];

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

    function applySubmittedBatchItems(items, responseItems) {
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

            applySubmittedBatchItems(items, batchResponse && Array.isArray(batchResponse.items) ? batchResponse.items : []);
            if (typeof state.error === 'string' && state.error.indexOf('Autosave') === 0) {
                state.error = '';
            }
            return batchResponse;
        } catch (batchError) {
            markAutoSaveCongested();

            try {
                var legacyResponse = await submitLegacyAnswerBatch(items, options);
                applySubmittedBatchItems(items, legacyResponse.items || []);
                state.error = 'Autosave batch melambat. Sistem memakai mode aman sementara.';
                return legacyResponse;
            } catch (legacyError) {
                requeuePendingAnswerBatchItems(items);
                throw (legacyError instanceof Error) ? legacyError : batchError;
            }
        }
    }

    async function flushPendingAnswerBatch(options) {
        options = options || {};
        var keepalive = !!options.keepalive;
        var flushAll = !!options.flushAll;

        clearAnswerBatchFlushTimer();

        while (true) {
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
                keepalive: keepalive
            });

            var result;
            try {
                result = await answerBatchFlushInFlight;
            } catch (error) {
                state.error = error instanceof Error ? ('Autosave gagal: ' + error.message) : 'Autosave gagal. Coba cek jaringan.';
                render();
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
        if (qid <= 0 || state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
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
        if (qid <= 0 || state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
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
                    keepalive: !!options.keepalive
                });
            } else if (queued) {
                scheduleAnswerBatchFlush(150);
            }

            if (typeof state.error === 'string' && state.error.indexOf('Autosave gagal') === 0) {
                state.error = '';
            }
        } catch (error) {
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
        clearAutoSaveRuntimeState();
        clearQuestionPrefetchRuntimeState();
        clearAttemptUiStateSyncTimer();
        lastAttemptUiStateSyncSignature = '';
        state.examToken = '';
        state.attemptId = 0;
        state.questions = [];
        state.questionOrderIds = [];
        state.questionManifest = [];
        state.questionManifestById = {};
        state.questionPayloadById = {};
        state.answeredQuestionLookup = {};
        state.loadedQuestionWindowOffsets = {};
        state.windowOffset = 0;
        state.windowLimit = 0;
        state.totalQuestions = 0;
        state.answers = {};
        state.doubtful = {};
        state.currentIndex = 0;
        state.navQuestionFilter = NAV_QUESTION_FILTER_ALL;
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
        if (previousAttemptId > 0 && state.stage !== 'exam') {
            clearPersistedAttemptUiState(previousAttemptId);
        }
    }

    function clearAuthenticatedFrontendState(options) {
        options = options || {};

        var previousAttemptId = Number(state.attemptId) || 0;
        stopTimer();
        stopSessionHeartbeat();
        clearAutoSaveRuntimeState();
        clearQuestionPrefetchRuntimeState();
        clearAttemptUiStateSyncTimer();
        attemptUiStateSyncInFlight = null;
        lastAttemptUiStateSyncSignature = '';
        state.stage = typeof options.stage === 'string' && options.stage !== '' ? options.stage : 'login';
        state.busy = false;
        state.error = typeof options.error === 'string' ? options.error : '';
        state.success = typeof options.success === 'string' ? options.success : '';
        state.loginIdentifier = '';
        state.loginPassword = '';
        state.loginPasswordVisible = false;
        state.token = '';
        state.user = null;
        state.exams = [];
        state.selectedExamId = 0;
        state.examToken = '';
        state.attemptId = 0;
        state.questions = [];
        state.questionOrderIds = [];
        state.questionManifest = [];
        state.questionManifestById = {};
        state.questionPayloadById = {};
        state.answeredQuestionLookup = {};
        state.loadedQuestionWindowOffsets = {};
        state.windowOffset = 0;
        state.windowLimit = 0;
        state.totalQuestions = 0;
        state.answers = {};
        state.doubtful = {};
        state.currentIndex = 0;
        state.navQuestionFilter = NAV_QUESTION_FILTER_ALL;
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
        if (previousAttemptId > 0) {
            clearPersistedAttemptUiState(previousAttemptId);
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
        clearQuestionPrefetchRuntimeState();
        clearAttemptUiStateSyncTimer();
        lastAttemptUiStateSyncSignature = '';

        var durationMinutes = Number(
            (startPayload && startPayload.duration_minutes) ||
            (selectedExam && selectedExam.duration_minutes) ||
            60
        );
        var remainingSeconds = Math.max(0, durationMinutes * 60);
        var startedAt = parseDateTime(startPayload && startPayload.started_at);
        if (startedAt) {
            var elapsed = Math.floor((Date.now() - startedAt.getTime()) / 1000);
            if (elapsed > 0) {
                remainingSeconds = Math.max(0, remainingSeconds - elapsed);
            }
        }
        state.remainingSeconds = remainingSeconds;

        var examId = Number(selectedExam && selectedExam.id) || 0;
        var includeExisting = 1;
        if (examId > 0) {
            state.selectedExamId = examId;
        }

        state.questions = [];
        state.questionOrderIds = [];
        state.questionManifest = [];
        state.questionManifestById = {};
        state.questionPayloadById = {};
        state.answeredQuestionLookup = {};
        state.loadedQuestionWindowOffsets = {};
        state.windowOffset = 0;
        state.windowLimit = 0;
        state.totalQuestions = 0;
        state.answers = {};
        state.doubtful = {};
        state.currentIndex = 0;
        state.navQuestionFilter = NAV_QUESTION_FILTER_ALL;

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

        var fallbackAttemptUiState = attemptUiStateRequestFailed
            ? readPersistedAttemptUiState(state.attemptId)
            : attemptUiStatePayload;
        var requestedResumeIndex = Math.max(
            0,
            Math.floor(Number(fallbackAttemptUiState && fallbackAttemptUiState.current_index !== undefined
                ? fallbackAttemptUiState.current_index
                : 0) || 0)
        );

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

        if (attemptUiStateRequestFailed) {
            applyAttemptUiState(readPersistedAttemptUiState(state.attemptId), state.attemptId);
        } else {
            applyAttemptUiState(attemptUiStatePayload, state.attemptId);
        }

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

        state.stage = 'exam';
        state.error = '';
        state.success = '';
        persistAuthSession();
        persistCurrentAttemptUiStateLocally();
        scheduleAttemptUiStateSync(ATTEMPT_UI_STATE_SYNC_DELAY_MS);
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
        try {
            await flushAttemptUiState({ force: true });
        } catch (error) {
            // Keep local fallback; next lifecycle event or interaction will retry.
        }
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
            '<span class="cbt-brand-badge">CBT</span>',
            '<div><h2>' + escapeHtml(config.siteName || 'CBT Exam') + '</h2><small>' + escapeHtml(stageLabel) + '</small></div>',
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
        var siteName = escapeHtml(config.siteName || 'SMK Negeri 1 Tanjungpandan CBT');
        var passwordType = state.loginPasswordVisible ? 'text' : 'password';
        var loginButtonClass = state.busy ? 'cbt-button cbt-button-primary cbt-button-login is-loading' : 'cbt-button cbt-button-primary cbt-button-login';
        var loginButtonLabel = state.busy ? 'Memverifikasi...' : 'Masuk Sekarang';
        var togglePasswordLabel = state.loginPasswordVisible ? 'Sembunyikan' : 'Tampilkan';

        return [
            '<section class="cbt-login-shell">',
            '<div class="cbt-login-hero">',
            '<p class="cbt-login-kicker">Portal Ujian Berbasis Komputer</p>',
            '<h1>' + siteName + '</h1>',
            '<p class="cbt-login-description">Platform ujian online untuk siswa dan guru. Masuk dengan akun terdaftar untuk melanjutkan ke daftar exam.</p>',
            '<div class="cbt-login-steps">',
            '<article class="cbt-login-step"><span>1</span><div><strong>Masuk</strong><small>Email/Username/NISN dan password.</small></div></article>',
            '<article class="cbt-login-step"><span>2</span><div><strong>Konfirmasi</strong><small>Pilih exam lalu konfirmasi token (jika diminta).</small></div></article>',
            '<article class="cbt-login-step"><span>3</span><div><strong>Kerjakan Ujian</strong><small>Jawab semua soal lalu kumpulkan sebelum waktu habis.</small></div></article>',
            '</div>',
            '</div>',
            '<div class="cbt-login-panel">',
            '<h3>Masuk ke CBT</h3>',
            '<p class="cbt-subtitle">Identifier bisa berupa email, username, atau NISN.</p>',
            '<form id="cbt-login-form" class="cbt-form-grid">',
            '<div class="cbt-field"><label for="cbt-identifier">Email / Username / NISN</label><input id="cbt-identifier" class="cbt-input" name="identifier" autocomplete="username" value="' + escapeHtml(state.loginIdentifier) + '" placeholder="Contoh: 231045 atau siswa@smkn1tpd.sch.id" required /></div>',
            '<div class="cbt-field"><label for="cbt-password">Password</label><div class="cbt-password-field"><input id="cbt-password" class="cbt-input" name="password" type="' + passwordType + '" autocomplete="current-password" value="' + escapeHtml(state.loginPassword) + '" placeholder="Masukkan password akun" required /><button class="cbt-password-toggle' + (state.loginPasswordVisible ? ' is-visible' : '') + '" data-action="toggle-password" type="button" aria-label="' + togglePasswordLabel + '" title="' + togglePasswordLabel + '"' + (state.busy ? ' disabled' : '') + '><span class="cbt-password-toggle-icon" aria-hidden="true"><span class="cbt-password-toggle-icon-eye"><svg viewBox="0 0 24 24" focusable="false"><path d="M1.5 12S5.5 5.5 12 5.5 22.5 12 22.5 12 18.5 18.5 12 18.5 1.5 12 1.5 12Z"></path><circle cx="12" cy="12" r="3.2"></circle></svg></span><span class="cbt-password-toggle-icon-eye-off"><svg viewBox="0 0 24 24" focusable="false"><path d="M3.2 3.2 20.8 20.8"></path><path d="M9.9 5.9A12.2 12.2 0 0 1 12 5.5c6.5 0 10.5 6.5 10.5 6.5a18.9 18.9 0 0 1-3.4 4.2"></path><path d="M6.4 8A18.3 18.3 0 0 0 1.5 12s4 6.5 10.5 6.5a11.6 11.6 0 0 0 4-.7"></path><path d="M14.3 14.3A3.2 3.2 0 0 1 9.7 9.7"></path></svg></span></span><span class="cbt-password-toggle-label">' + escapeHtml(togglePasswordLabel) + '</span></button></div></div>',
            '<div class="cbt-actions"><button class="' + loginButtonClass + '" type="submit"' + (state.busy ? ' disabled' : '') + '><span class="cbt-button-spinner" aria-hidden="true"></span><span>' + loginButtonLabel + '</span></button></div>',
            '</form>',
            renderAlert(),
            '<p class="cbt-login-help">Jika gagal login, hubungi admin sekolah atau pengawas ujian.</p>',
            '</div>',
            '</section>'
        ].join('');
    }

    function renderConfirmStage() {
        if (!state.exams.length) {
            return [
                '<section class="cbt-card">',
                '<h3>Belum Ada Exam Aktif</h3>',
                '<p class="cbt-subtitle">Akun ini belum memiliki exam yang tersedia saat ini.</p>',
                '<div class="cbt-actions">',
                '<button class="cbt-button cbt-button-secondary" data-action="reload-exams" type="button"' + (state.busy ? ' disabled' : '') + '>Refresh</button>',
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
                ? 'Ujian untuk exam ini sudah selesai. Anda bisa melihat hasil nilai dari attempt terakhir.'
                : (selectedExamRequiresToken
                    ? (
                        tokenInputRequired
                            ? ('Exam ini membutuhkan token global.' + (tokenRefreshMinutes > 0 ? (' Refresh setiap ' + tokenRefreshMinutes + ' menit.') : ''))
                            : ('Token exam diisi otomatis oleh sistem.' + (tokenRefreshMinutes > 0 ? (' Refresh setiap ' + tokenRefreshMinutes + ' menit.') : ''))
                    )
                    : 'Exam ini tidak membutuhkan token.'))
            : 'Pilih exam terlebih dahulu dari daftar di kiri.';
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
            var summaryCompactStatus = 'siap';

            if (isActive) {
                itemClasses.push('is-active');
            }
            if (latestAttemptStatus === 'completed') {
                itemClasses.push('is-completed');
                examAttemptLabel = 'Sudah selesai';
                examAttemptCompact = 'Selesai';
                summaryCompactStatus = 'Selesai';
                if (Number.isFinite(latestAttemptPercentage)) {
                    examAttemptExtra = ' | Nilai: ' + formatScoreValue(latestAttemptPercentage) + '%';
                    examAttemptCompact += ' | ' + formatScoreValue(latestAttemptPercentage) + '%';
                    summaryCompactStatus += ' ' + formatScoreValue(latestAttemptPercentage) + '%';
                }
            } else if (latestAttemptStatus === 'in_progress') {
                itemClasses.push('is-in-progress');
                examAttemptLabel = 'Sedang dikerjakan';
                examAttemptCompact = 'Dikerjakan';
                summaryCompactStatus = 'Dikerjakan';
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
            var scheduleCompactLabel = formatDateTimeCompact(exam.starts_at) + ' | ' + String(duration) + 'm';
            if (latestAttemptStatus !== 'completed' && latestAttemptStatus !== 'in_progress') {
                summaryCompactStatus = accessCompactLabel;
            }
            scheduleCompactLabel += ' | ' + summaryCompactStatus;

            return [
                '<button type="button" class="' + itemClasses.join(' ') + '" data-action="select-exam" data-id="' + escapeHtml(exam.id) + '" aria-pressed="' + (isActive ? 'true' : 'false') + '">',
                '<p class="cbt-exam-title">' + escapeHtml(exam.title || '-') + '</p>',
                '<p class="cbt-exam-meta cbt-exam-meta-subject"><span class="cbt-exam-meta-full">' + escapeHtml(exam.subject_name || '-') + ' | ' + escapeHtml(status) + '</span><span class="cbt-exam-meta-compact">' + escapeHtml(exam.subject_name || '-') + '</span></p>',
                '<p class="cbt-exam-meta cbt-exam-meta-schedule"><span class="cbt-exam-meta-full">Mulai: ' + escapeHtml(startsAt) + ' | Durasi: ' + escapeHtml(duration) + ' menit</span><span class="cbt-exam-meta-compact">' + escapeHtml(scheduleCompactLabel) + '</span></p>',
                '<p class="cbt-exam-meta cbt-exam-meta-access"><span class="cbt-exam-meta-full">' + escapeHtml(accessLabel) + '</span><span class="cbt-exam-meta-compact">' + escapeHtml(accessCompactLabel) + '</span></p>',
                '<p class="cbt-exam-meta cbt-exam-meta-attempt"><span class="cbt-exam-meta-full">Status Ujian: ' + escapeHtml(examAttemptLabel + examAttemptExtra) + '</span><span class="cbt-exam-meta-compact">' + escapeHtml(examAttemptCompact) + '</span></p>',
                '</button>'
            ].join('');
        }).join('');

        return [
            '<div class="cbt-grid-2">',
            '<section class="cbt-card">',
            '<h3>Pilih Exam</h3>',
            '<p class="cbt-subtitle">Daftar exam sesuai hak akses akun yang login.</p>',
            '<div class="cbt-exam-list">' + examItems + '</div>',
            renderAlert(),
            '</section>',
            '<section class="cbt-card cbt-confirm-card">',
            '<h3>Konfirmasi Ujian</h3>',
            '<p class="cbt-subtitle">' + tokenInfoText + '</p>',
            '<div class="cbt-confirm-profile">',
            (
                userPhoto !== ''
                    ? '<button class="cbt-confirm-profile-avatar-button" data-action="open-user-photo" type="button" aria-label="Lihat foto peserta ukuran besar"><img class="cbt-confirm-profile-avatar" src="' + escapeHtml(userPhoto) + '" alt="' + escapeHtml(userName) + '" loading="lazy" decoding="async" /></button>'
                    : '<div class="cbt-confirm-profile-avatar cbt-confirm-profile-avatar-fallback" aria-hidden="true">' + escapeHtml(userInitial) + '</div>'
            ),
            '</div>',
            '<div class="cbt-form-grid cbt-confirm-form-grid">',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Username</label><input class="cbt-input" value="' + escapeHtml(state.user && state.user.username ? state.user.username : '-') + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field"><label>Nama</label><input class="cbt-input" value="' + escapeHtml(userName || '-') + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Kelas</label><input class="cbt-input" value="' + escapeHtml(state.user && state.user.kode_kelas ? state.user.kode_kelas : '-') + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Ruangan</label><input class="cbt-input" value="' + escapeHtml(state.user && state.user.kode_ruang ? state.user.kode_ruang : '-') + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field"><label>Exam</label><input class="cbt-input" value="' + escapeHtml(selectedExam ? (selectedExam.title || '-') : '-') + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field"><label>Mata Pelajaran</label><input class="cbt-input" value="' + escapeHtml(selectedExam ? (selectedExam.subject_name || '-') : '-') + '" readonly /></div>',
            (
                tokenInputRequired
                    ? '<div class="cbt-field cbt-confirm-field cbt-confirm-field-token"><label for="cbt-exam-token">Token</label><input id="cbt-exam-token" class="cbt-input cbt-input-token" name="exam_token" maxlength="6" value="' + escapeHtml(state.examToken) + '" placeholder="6 karakter (tanpa 0 O I L)"' + (hasSelectedExam && !selectedExamCompleted ? '' : ' disabled') + ' /></div>'
                    : '<div class="cbt-field cbt-confirm-field cbt-confirm-field-token"><label>Token</label><input class="cbt-input" value="' + escapeHtml(selectedExamAutoTokenValue !== '' ? selectedExamAutoTokenValue : ((selectedExamRequiresToken || selectedExamAutoToken) ? 'Otomatis oleh sistem' : 'Tidak diperlukan')) + '" readonly /></div>'
            ),
            '</div>',
            '<div class="cbt-actions cbt-confirm-actions">',
            (
                selectedExamCompleted
                    ? '<button class="cbt-button cbt-button-primary" data-action="view-result" type="button"' + (state.busy || !hasSelectedExam || selectedExamAttemptId <= 0 ? ' disabled' : '') + '>' + (state.busy ? 'Memuat...' : 'Lihat Nilai') + '</button>'
                    : '<button class="cbt-button cbt-button-primary" data-action="start-exam" type="button"' + (state.busy || !hasSelectedExam ? ' disabled' : '') + '>' + (state.busy ? 'Memulai...' : 'Mulai Ujian') + '</button>'
            ),
            '<button class="cbt-button cbt-button-secondary" data-action="reload-exams" type="button"' + (state.busy ? ' disabled' : '') + '>Refresh Exam</button>',
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function renderQuestionInput(question) {
        var answer = state.answers[question.id];

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
        var answeredCount = progressSummary.answeredQuestions;
        var answeredPercentage = totalQuestions > 0 ? (answeredCount / totalQuestions) * 100 : 0;
        var answeredPercentageText = formatScoreValue(answeredPercentage);
        var answeredPercentageWidth = Math.max(0, Math.min(100, answeredPercentage)).toFixed(2);
        var doubtfulCount = progressSummary.doubtfulQuestions;
        var unansweredCount = Math.max(0, totalQuestions - answeredCount);
        var currentQuestionIsDoubtful = isQuestionDoubtful(currentQuestion);
        var isLastQuestion = state.currentIndex >= (totalQuestions - 1);
        var currentQuestionTypeLabel = formatQuestionType(currentQuestion.question_type);
        var currentQuestionTypeCode = navigationQuestionTypeBadgeConfig(currentQuestion.question_type).code;
        var currentQuestionPointsRaw = currentQuestion && currentQuestion.points !== undefined ? currentQuestion.points : '-';
        var currentQuestionPointsNumber = Number(currentQuestionPointsRaw);
        var currentQuestionPoints = Number.isFinite(currentQuestionPointsNumber)
            ? formatScoreValue(currentQuestionPointsNumber)
            : String(currentQuestionPointsRaw);
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
            if (entry.index === state.currentIndex) {
                classes.push('is-current');
            }
            var answerBadge = renderNavigationAnswerBadges(question);
            var questionTypeBadge = renderNavigationQuestionTypeBadge(question);
            return '<button type="button" class="' + classes.join(' ') + '" data-action="jump" data-index="' + escapeHtml(entry.index) + '"><span class="cbt-nav-number">' + escapeHtml(entry.index + 1) + '</span>' + questionTypeBadge + answerBadge + '</button>';
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
            '<div class="cbt-legend"><span class="cbt-legend-item cbt-legend-item-current"><i class="cbt-dot cbt-dot-current"></i> Aktif</span><span class="cbt-legend-item cbt-legend-item-answered"><i class="cbt-dot cbt-dot-answered"></i> Terjawab</span><span class="cbt-legend-item cbt-legend-item-doubtful"><i class="cbt-dot cbt-dot-doubtful"></i> Ragu-ragu</span></div>',
            (isLastQuestion
                ? ('<div class="cbt-actions cbt-side-actions-compact"><button class="cbt-button cbt-button-primary" data-action="collect" type="button"' + (state.busy || state.isFinishing ? ' disabled' : '') + '>Kumpulkan Jawaban</button></div>')
                : ''),
            '</aside>'
        ].join('');

        var questionCardMarkup = [
            '<section class="cbt-question-card">',
            '<div class="cbt-question-head">',
            '<div class="cbt-question-head-main">',
            '<div class="cbt-chip cbt-chip-question-index" aria-label="Soal ' + escapeHtml(state.currentIndex + 1) + ' dari ' + escapeHtml(totalQuestions) + '"><span class="cbt-chip-mobile-icon" aria-hidden="true">#</span><span class="cbt-chip-label">Soal</span><span class="cbt-chip-value">' + escapeHtml(state.currentIndex + 1) + '/' + escapeHtml(totalQuestions) + '</span></div>',
            '<div class="cbt-chip cbt-chip-question-meta" title="' + escapeHtml(currentQuestionMetaLabel) + '" aria-label="' + escapeHtml(currentQuestionMetaLabel) + '"><span class="cbt-chip-mobile-meta" aria-hidden="true">' + escapeHtml(currentQuestionMetaCompact) + '</span><span class="cbt-chip-type">' + escapeHtml(currentQuestionTypeLabel) + '</span><span class="cbt-chip-separator" aria-hidden="true"></span><span class="cbt-chip-points">Poin ' + escapeHtml(currentQuestionPoints) + '</span></div>',
            (currentQuestionIsDoubtful ? '<div class="cbt-chip cbt-chip-warning">Ragu-ragu</div>' : ''),
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

        return '<div class="' + stageShellClass + '">' + stageContent + '</div>';
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

        var userName = getCurrentUserName();
        var userKelas = state.user && state.user.kode_kelas ? String(state.user.kode_kelas) : '-';
        var userRuang = state.user && state.user.kode_ruang ? String(state.user.kode_ruang) : '-';
        var userAgama = state.user && state.user.agama ? String(state.user.agama) : '-';

        return [
            '<div class="cbt-user-photo-modal-overlay" data-action="close-user-photo">',
            '<section class="cbt-user-photo-modal" data-action="user-photo-modal-panel" role="dialog" aria-modal="true" aria-labelledby="cbt-user-photo-title">',
            '<button class="cbt-user-photo-modal-close" data-action="close-user-photo" type="button" aria-label="Tutup foto">&times;</button>',
            '<h3 id="cbt-user-photo-title">Foto Peserta</h3>',
            '<img class="cbt-user-photo-modal-image" src="' + escapeHtml(userPhoto) + '" alt="' + escapeHtml(userName) + '" loading="eager" decoding="sync" />',
            '<dl class="cbt-user-photo-modal-info">',
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
        scheduleNavigationGridLayout();
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
            if (examId > 0) {
                state.selectedExamId = examId;
                state.examToken = '';
                clearMessages();
                persistAuthSession();
                render();
            }
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

        if (state.stage === 'exam') {
            noteQuestionPrefetchActivity();
        }

        if (target.getAttribute('data-action') === 'answer-single') {
            var singleQid = Number(target.getAttribute('data-qid')) || 0;
            var singleOptionId = Number(target.getAttribute('data-option-id')) || 0;
            if (singleQid > 0 && singleOptionId > 0) {
                state.answers[singleQid] = singleOptionId;
                clearMessages();
                scheduleAutoSave(singleQid, AUTO_SAVE_CHOICE_DELAY_MS);
                render();
            }
            return;
        }

        if (target.getAttribute('data-action') === 'answer-multi') {
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
            clearMessages();
            scheduleAutoSave(multiQid, AUTO_SAVE_CHOICE_DELAY_MS);
            render();
            return;
        }

        if (target.getAttribute('data-action') === 'answer-tf-matrix') {
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
            clearMessages();
            scheduleAutoSave(matrixQid, AUTO_SAVE_CHOICE_DELAY_MS);
            render();
        }
    });

    root.addEventListener('input', function (event) {
        var target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (state.stage === 'exam') {
            noteQuestionPrefetchActivity();
        }

        var action = target.getAttribute('data-action');

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
            }
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
        scheduleNavigationGridLayout();
    });

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
        if (document.visibilityState === 'hidden') {
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

    window.addEventListener('pagehide', function () {
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

    bootstrapFromPersistedSession();
})();
