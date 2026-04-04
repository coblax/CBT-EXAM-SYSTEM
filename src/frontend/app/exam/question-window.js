export function createQuestionWindowManager(deps) {
    var state = deps.state;
    var root = deps.root;
    var windowRef = deps.windowRef;
    var questionWindowSize = Math.max(1, Number(deps.questionWindowSize) || 1);
    var questionPrefetchBatchSize = Math.max(1, Number(deps.questionPrefetchBatchSize) || 1);
    var questionPrefetchIdleDelayMs = Math.max(0, Number(deps.questionPrefetchIdleDelayMs) || 0);
    var escapeHtml = deps.escapeHtml;
    var getLoadQuestionWindow = deps.getLoadQuestionWindow;
    var isQuestionRevisionRefreshActive = deps.isQuestionRevisionRefreshActive;

    var questionPrefetchIdleTimer = 0;
    var questionPrefetchInFlightByOffset = {};

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
        var safeWindowSize = Math.max(1, Number(windowSize) || questionWindowSize);
        var safeIndex = Math.max(0, Math.floor(Number(index) || 0));
        return Math.floor(safeIndex / safeWindowSize) * safeWindowSize;
    }

    function setActiveQuestionWindowForIndex(index, windowSize) {
        var safeWindowSize = Math.max(1, Number(windowSize) || questionWindowSize);
        var safeIndex = clampQuestionIndex(index);
        state.windowOffset = questionWindowOffsetForIndex(safeIndex, safeWindowSize);
        state.windowLimit = safeWindowSize;
    }

    function buildQuestionWindowItems(offset, limit) {
        var totalQuestions = getQuestionCount();
        if (totalQuestions <= 0) {
            return [];
        }

        var safeOffset = Math.max(0, Number(offset) || 0);
        var safeLimit = Math.max(1, Number(limit) || questionWindowSize);
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
        var safeLimit = Math.max(1, Number(limit) || questionWindowSize);
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

    function clearQuestionPrefetchIdleTimer() {
        if (questionPrefetchIdleTimer) {
            windowRef.clearTimeout(questionPrefetchIdleTimer);
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

    function renderQuestionPrefetchIndicator(extraClass) {
        var meta = getQuestionPrefetchMeta();
        var classes = ['cbt-chip', 'cbt-chip-prefetch'];
        if (extraClass) {
            classes.push(String(extraClass));
        }
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

        var indicators = Array.from(root.querySelectorAll('[data-prefetch-indicator]'));
        if (!indicators.length) {
            return;
        }

        var meta = getQuestionPrefetchMeta();
        indicators.forEach(function (indicator) {
            if (!(indicator instanceof HTMLElement)) {
                return;
            }

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
        });
    }

    function resetQuestionPrefetchIdleTimer() {
        clearQuestionPrefetchIdleTimer();

        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing || isQuestionRevisionRefreshActive()) {
            return;
        }

        if (!hasPendingQuestionPrefetch()) {
            return;
        }

        questionPrefetchIdleTimer = windowRef.setTimeout(function () {
            questionPrefetchIdleTimer = 0;
            prefetchNextQuestionBatch().finally(function () {
                resetQuestionPrefetchIdleTimer();
            });
        }, questionPrefetchIdleDelayMs);
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
        var limit = Math.min(questionPrefetchBatchSize, Math.max(0, totalQuestions - offset));
        if (limit <= 0) {
            return Promise.resolve(null);
        }

        var request = getLoadQuestionWindow()(offset, {
            examId: state.selectedExamId,
            attemptId: state.attemptId,
            includeExisting: 1,
            limit: limit,
            scenarioTarget: 'prefetch',
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

    return {
        buildQuestionWindowItems: buildQuestionWindowItems,
        clampQuestionIndex: clampQuestionIndex,
        clearQuestionPrefetchRuntimeState: clearQuestionPrefetchRuntimeState,
        getQuestionAtIndex: getQuestionAtIndex,
        getQuestionById: getQuestionById,
        getQuestionCount: getQuestionCount,
        getQuestionDisplayNumber: getQuestionDisplayNumber,
        getQuestionDisplayNumberById: getQuestionDisplayNumberById,
        getQuestionIdAtIndex: getQuestionIdAtIndex,
        getQuestionManifestById: getQuestionManifestById,
        getQuestionPayloadById: getQuestionPayloadById,
        getQuestionPrefetchMeta: getQuestionPrefetchMeta,
        isIndexInCurrentWindow: isIndexInCurrentWindow,
        isQuestionPayloadLoaded: isQuestionPayloadLoaded,
        isQuestionWindowLoaded: isQuestionWindowLoaded,
        markQuestionWindowLoaded: markQuestionWindowLoaded,
        noteQuestionPrefetchActivity: noteQuestionPrefetchActivity,
        prefetchNextQuestionBatch: prefetchNextQuestionBatch,
        questionWindowOffsetForIndex: questionWindowOffsetForIndex,
        renderQuestionPrefetchIndicator: renderQuestionPrefetchIndicator,
        resetQuestionPrefetchIdleTimer: resetQuestionPrefetchIdleTimer,
        setActiveQuestionWindowForIndex: setActiveQuestionWindowForIndex,
        setQuestionWindowFromLoadedPayloads: setQuestionWindowFromLoadedPayloads,
        updateQuestionPrefetchIndicator: updateQuestionPrefetchIndicator,
        validAttemptQuestionIds: validAttemptQuestionIds
    };
}
