export function createAnswerSyncManager(deps) {
    var diagnosticsManager = deps.diagnosticsManager;
    var state = deps.state;
    var windowRef = deps.windowRef;
    var autoSaveChoiceDelayMs = Math.max(0, Number(deps.autoSaveChoiceDelayMs) || 0);
    var autoSaveTextDelayMs = Math.max(0, Number(deps.autoSaveTextDelayMs) || 0);
    var autoSaveChoiceDelayCongestedMs = Math.max(0, Number(deps.autoSaveChoiceDelayCongestedMs) || 0);
    var autoSaveTextDelayCongestedMs = Math.max(0, Number(deps.autoSaveTextDelayCongestedMs) || 0);
    var autoSaveCongestedWindowMs = Math.max(0, Number(deps.autoSaveCongestedWindowMs) || 0);
    var autoSaveBatchMaxItems = Math.max(1, Number(deps.autoSaveBatchMaxItems) || 1);
    var answerSyncRetryBaseDelayMs = Math.max(0, Number(deps.answerSyncRetryBaseDelayMs) || 0);
    var answerSyncRetryMaxDelayMs = Math.max(answerSyncRetryBaseDelayMs, Number(deps.answerSyncRetryMaxDelayMs) || 0);
    var apiRequest = deps.apiRequest;
    var getNavigatorConnectionStatus = deps.getNavigatorConnectionStatus;
    var getQuestionById = deps.getQuestionById;
    var getQuestionPayloadById = deps.getQuestionPayloadById;
    var getQuestionDataGeneration = deps.getQuestionDataGeneration;
    var isQuestionRevisionRefreshActive = deps.isQuestionRevisionRefreshActive;
    var maybeFinalizeLockedExam = deps.maybeFinalizeLockedExam;
    var normalizeExistingAnswerForQuestion = deps.normalizeExistingAnswerForQuestion;
    var normalizeStoredAutoSaveState = deps.normalizeStoredAutoSaveState;
    var payloadSignature = deps.payloadSignature;
    var questionAnswerPayload = deps.questionAnswerPayload;
    var recordActionTrail = deps.recordActionTrail;
    var recordTimeline = deps.recordTimeline;
    var render = deps.render;
    var renderExamPartial = typeof deps.renderExamPartial === 'function'
        ? deps.renderExamPartial
        : null;
    var scheduleQuestionCachePersist = deps.scheduleQuestionCachePersist;

    var autoSaveTimersByQuestion = {};
    var autoSaveCongestedUntil = 0;
    var lastSubmittedPayloadByQuestion = {};
    var pendingAnswerBatchByQuestion = {};
    var pendingAnswerBatchOrder = [];
    var answerBatchFlushTimer = 0;
    var answerBatchFlushDueAt = 0;
    var answerBatchFlushInFlight = null;
    var answerBatchInFlightItems = [];
    var answerSyncRetryCount = 0;

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

    function publishSyncSnapshot(reason) {
        if (!diagnosticsManager || !diagnosticsManager.enabled || typeof diagnosticsManager.recordSyncSnapshot !== 'function') {
            return;
        }

        diagnosticsManager.recordSyncSnapshot({
            attemptId: Number(state.attemptId) || 0,
            stage: String(state.stage || ''),
            connectionStatus: String(state.connectionStatus || getNavigatorConnectionStatus() || 'online'),
            pendingSyncCount: Number(state.pendingSyncCount) || 0,
            syncBlockingReason: String(state.syncBlockingReason || ''),
            lastSyncError: String(state.lastSyncError || ''),
            examLockedForPendingFinish: Boolean(state.examLockedForPendingFinish),
            isFinishing: Boolean(state.isFinishing),
            flushInFlight: Boolean(answerBatchFlushInFlight),
            hasPendingBatchItems: pendingAnswerBatchOrder.length > 0 || answerBatchInFlightItems.length > 0,
            retryCount: Number(answerSyncRetryCount) || 0,
            nextRetryDueAt: answerBatchFlushDueAt > 0 ? new Date(answerBatchFlushDueAt).toISOString() : '',
            autoSaveCongestedUntil: autoSaveCongestedUntil > 0 ? new Date(autoSaveCongestedUntil).toISOString() : '',
            reason: String(reason || ''),
            lastUpdatedAt: new Date().toISOString()
        });
    }

    function isAnswerSubmitPath(path) {
        var normalizedPath = String(path || '').replace(/^\/+/, '');
        return normalizedPath === 'submit_answer' || normalizedPath === 'submit_answers_batch';
    }

    function isNetworkConnectivityError(error) {
        if (!error) {
            return false;
        }

        if (error.isNetworkError === true) {
            return true;
        }

        var status = Number(error.status) || 0;
        if (status === 0) {
            return true;
        }

        var code = String(error.code || '').toLowerCase();
        if (code === 'network_error' || code === 'failed_to_fetch') {
            return true;
        }

        var message = String(error.message || '').toLowerCase();
        return (
            message.indexOf('failed to fetch') >= 0 ||
            message.indexOf('networkerror') >= 0 ||
            message.indexOf('load failed') >= 0 ||
            message.indexOf('network request failed') >= 0 ||
            message.indexOf('koneksi') >= 0
        );
    }

    function isRetryableAnswerSyncError(error) {
        return isNetworkConnectivityError(error);
    }

    function shouldFallbackToLegacyBatch(error) {
        if (!error) {
            return false;
        }

        var status = Number(error.status) || 0;
        var code = String(error.code || '');
        if (code === 'runtime_buffer_unavailable') {
            return true;
        }

        if (isRetryableAnswerSyncError(error)) {
            return false;
        }

        return (
            status >= 500 ||
            status === 503 ||
            status === 429 ||
            code === 'runtime_buffer_unavailable'
        );
    }

    function getPendingAnswerBatchCount() {
        var pendingLookup = Object.keys(pendingAnswerBatchByQuestion || {}).reduce(function (lookup, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0) {
                lookup[questionId] = true;
            }
            return lookup;
        }, {});

        (Array.isArray(answerBatchInFlightItems) ? answerBatchInFlightItems : []).forEach(function (item) {
            var questionId = Number(item && item.question_id) || 0;
            if (questionId > 0) {
                pendingLookup[questionId] = true;
            }
        });

        return Object.keys(pendingLookup).length;
    }

    function resolveSyncBlockingReason() {
        var pendingSyncForced = diagnosticsManager
            && diagnosticsManager.enabled
            && typeof diagnosticsManager.isPendingSyncForced === 'function'
            && diagnosticsManager.isPendingSyncForced();

        if (state.stage !== 'exam' || state.attemptId <= 0) {
            return '';
        }

        if (state.examLockedForPendingFinish) {
            if (state.isFinishing) {
                return 'finish_finalizing';
            }
            if (getNavigatorConnectionStatus() === 'offline' || state.connectionStatus === 'offline') {
                return 'finish_wait_online';
            }
            if (state.pendingSyncCount > 0) {
                return 'finish_pending_sync';
            }
            return 'finish_ready';
        }

        if (state.pendingSyncCount > 0) {
            if (pendingSyncForced) {
                return 'forced_pending_sync';
            }
            return (getNavigatorConnectionStatus() === 'offline' || state.connectionStatus === 'offline')
                ? 'offline_pending_sync'
                : 'pending_sync';
        }

        if (getNavigatorConnectionStatus() === 'offline' || state.connectionStatus === 'offline') {
            return 'offline';
        }

        return '';
    }

    function renderSyncUi(reason, meta) {
        if (state.stage === 'exam' && renderExamPartial) {
            if (renderExamPartial({
                notice: true,
                questionFooterSync: true
            }, reason, meta || {})) {
                return;
            }
        }

        render(reason, meta);
    }

    function syncPendingAnswerRuntimeState(options) {
        options = options || {};

        state.pendingSyncCount = getPendingAnswerBatchCount();
        state.syncBlockingReason = resolveSyncBlockingReason();

        if (
            state.pendingSyncCount <= 0 &&
            !state.examLockedForPendingFinish &&
            state.connectionStatus === 'online' &&
            options.clearLastSyncError !== false
        ) {
            state.lastSyncError = '';
        }

        if (options.persist !== false) {
            scheduleQuestionCachePersist(options.delayMs);
        }

        publishSyncSnapshot(options.reason || 'sync-runtime');

        if (options.render && state.stage === 'exam') {
            renderSyncUi(options.reason || 'sync-runtime', {
                pendingSyncCount: Number(state.pendingSyncCount) || 0,
                syncBlockingReason: String(state.syncBlockingReason || '')
            });
        }
    }

    function setConnectionStatus(nextStatus, options) {
        options = options || {};

        var normalizedStatus = String(nextStatus || '').toLowerCase() === 'offline' ? 'offline' : 'online';
        var hasChanged = state.connectionStatus !== normalizedStatus;
        state.connectionStatus = normalizedStatus;
        syncPendingAnswerRuntimeState({
            persist: options.persist,
            clearLastSyncError: normalizedStatus !== 'offline',
            render: false,
            reason: 'connection:' + normalizedStatus
        });

        if (normalizedStatus === 'online' && options.triggerRetry !== false) {
            schedulePendingAnswerRetry(options.reason || 'connection-online', {
                immediate: options.immediate !== false,
                resetBackoff: true
            });
        }

        if ((hasChanged || options.forceRender) && options.render !== false && state.stage === 'exam') {
            renderSyncUi(options.reason || ('connection:' + normalizedStatus), {
                connectionStatus: normalizedStatus,
                pendingSyncCount: Number(state.pendingSyncCount) || 0
            });
        }
    }

    function getAutoSaveState() {
        return {
            answerBatchInFlightItems: answerBatchInFlightItems,
            autoSaveCongestedUntil: autoSaveCongestedUntil,
            examLockedForPendingFinish: state.examLockedForPendingFinish,
            lastSubmittedPayloadByQuestion: lastSubmittedPayloadByQuestion,
            lastSyncError: state.lastSyncError,
            pendingAnswerBatchByQuestion: pendingAnswerBatchByQuestion,
            pendingAnswerBatchOrder: pendingAnswerBatchOrder,
            syncBlockingReason: state.syncBlockingReason
        };
    }

    function restoreQuestionAutoSaveState(snapshot) {
        var normalizedState = normalizeStoredAutoSaveState(snapshot);
        lastSubmittedPayloadByQuestion = Object.assign({}, normalizedState.lastSubmittedPayloadByQuestion);
        pendingAnswerBatchByQuestion = Object.assign({}, normalizedState.pendingAnswerBatchByQuestion);
        pendingAnswerBatchOrder = normalizedState.pendingAnswerBatchOrder.slice();
        autoSaveCongestedUntil = Math.max(0, Number(normalizedState.autoSaveCongestedUntil) || 0);
        state.lastSyncError = normalizedState.lastSyncError;
        state.examLockedForPendingFinish = normalizedState.examLockedForPendingFinish;
        state.syncBlockingReason = normalizedState.syncBlockingReason;
        answerSyncRetryCount = 0;
        syncPendingAnswerRuntimeState({
            persist: false,
            clearLastSyncError: false,
            reason: 'restore-autosave'
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

    function clearAllAutoSaveTimers() {
        Object.keys(autoSaveTimersByQuestion).forEach(function (key) {
            var timerId = autoSaveTimersByQuestion[key];
            if (timerId) {
                windowRef.clearTimeout(timerId);
            }
            delete autoSaveTimersByQuestion[key];
        });
    }

    function clearAnswerBatchFlushTimer() {
        if (answerBatchFlushTimer) {
            windowRef.clearTimeout(answerBatchFlushTimer);
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
        answerBatchInFlightItems = [];
        answerSyncRetryCount = 0;
        state.lastSyncError = '';
        state.examLockedForPendingFinish = false;
        state.pendingFinishAutoSubmit = false;
        syncPendingAnswerRuntimeState({
            persist: false,
            reason: 'clear-autosave-runtime'
        });
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
        syncPendingAnswerRuntimeState({
            persist: false,
            reason: 'initialize-submitted-payload'
        });
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

    function isAutoSaveCongested() {
        return autoSaveCongestedUntil > Date.now();
    }

    function markAutoSaveCongested() {
        autoSaveCongestedUntil = Date.now() + autoSaveCongestedWindowMs;
    }

    function resolveAutoSaveDelay(delayMs) {
        var waitMs = Math.max(0, Number(delayMs) || 0);
        if (!isAutoSaveCongested()) {
            return waitMs;
        }

        if (waitMs <= autoSaveChoiceDelayMs) {
            return Math.max(waitMs, autoSaveChoiceDelayCongestedMs);
        }

        return Math.max(waitMs, autoSaveTextDelayCongestedMs);
    }

    function getPendingAnswerRetryDelay() {
        var retryStep = Math.max(0, Number(answerSyncRetryCount) || 0);
        if (retryStep <= 0) {
            return answerSyncRetryBaseDelayMs;
        }
        return Math.min(
            answerSyncRetryMaxDelayMs,
            answerSyncRetryBaseDelayMs * Math.pow(2, retryStep - 1)
        );
    }

    function schedulePendingAnswerRetry(reason, options) {
        options = options || {};

        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing || isQuestionRevisionRefreshActive()) {
            return;
        }

        syncPendingAnswerRuntimeState({
            persist: options.persist,
            clearLastSyncError: false,
            reason: 'schedule-retry:' + String(reason || '')
        });

        if (state.pendingSyncCount <= 0) {
            maybeFinalizeLockedExam(reason || 'sync-empty');
            return;
        }

        if (getNavigatorConnectionStatus() === 'offline') {
            return;
        }

        if (
            diagnosticsManager
            && diagnosticsManager.enabled
            && typeof diagnosticsManager.isPendingSyncForced === 'function'
            && diagnosticsManager.isPendingSyncForced()
        ) {
            state.syncBlockingReason = 'forced_pending_sync';
            publishSyncSnapshot('force-pending-sync');
            recordTimelineEntry('sync:retry:skipped', 'Retry sync ditahan oleh Force Pending Sync.', {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || ''),
                reason: String(reason || '')
            });
            recordActionTrailEntry('sync:forced-pending', 'Pending sync dipertahankan oleh scenario.', {
                reason: String(reason || '')
            });
            if (options.render && state.stage === 'exam') {
                renderSyncUi('sync-forced-pending', {
                    reason: String(reason || '')
                });
            }
            return;
        }

        if (
            diagnosticsManager
            && diagnosticsManager.enabled
            && typeof diagnosticsManager.isAutoRetryDisabled === 'function'
            && diagnosticsManager.isAutoRetryDisabled()
        ) {
            state.syncBlockingReason = 'retry_disabled';
            publishSyncSnapshot('retry-disabled');
            recordTimelineEntry('sync:retry:skipped', 'Retry sync diblokir oleh scenario toggle.', {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || ''),
                reason: String(reason || '')
            });
            recordActionTrailEntry('sync:retry:skipped', 'Retry sync diblokir oleh scenario toggle.', {
                reason: String(reason || '')
            });
            if (options.render && state.stage === 'exam') {
                renderSyncUi('sync-retry-skipped', {
                    reason: String(reason || '')
                });
            }
            return;
        }

        if (options.resetBackoff) {
            answerSyncRetryCount = 0;
        } else if (!options.immediate) {
            answerSyncRetryCount = Math.min(8, answerSyncRetryCount + 1);
        }

        var delayMs = options.delayMs !== undefined
            ? Math.max(0, Number(options.delayMs) || 0)
            : (options.immediate ? 200 : getPendingAnswerRetryDelay());
        recordTimelineEntry('sync:retry:scheduled', 'Retry sync dijadwalkan.', {
            attemptId: Number(state.attemptId) || 0,
            delayMs: delayMs,
            reason: String(reason || ''),
            retryCount: Number(answerSyncRetryCount) || 0,
            stage: String(state.stage || '')
        });
        recordActionTrailEntry('sync:retry', 'Retry sync dijadwalkan.', {
            delayMs: delayMs,
            reason: String(reason || ''),
            retryCount: Number(answerSyncRetryCount) || 0
        });
        scheduleAnswerBatchFlush(delayMs);
    }

    function handleRecoverableAnswerSyncFailure(error, options) {
        options = options || {};

        state.lastSyncError = error instanceof Error && error.message
            ? error.message
            : 'Koneksi terputus. Jawaban disimpan lokal.';
        markAutoSaveCongested();
        setConnectionStatus('offline', {
            persist: false,
            render: false,
            triggerRetry: false
        });
        syncPendingAnswerRuntimeState({
            persist: options.persist !== false,
            clearLastSyncError: false,
            reason: 'sync-failure'
        });
        schedulePendingAnswerRetry(options.reason || 'sync-failure', {
            immediate: false
        });

        if (options.render !== false && state.stage === 'exam') {
            renderSyncUi(options.reason || 'sync-failure', {
                lastSyncError: String(state.lastSyncError || ''),
                pendingSyncCount: Number(state.pendingSyncCount) || 0
            });
        }
    }

    function noteSuccessfulAnswerSync(options) {
        options = options || {};
        answerSyncRetryCount = 0;
        state.lastSyncError = '';
        if (typeof state.error === 'string' && state.error.indexOf('Sinkronisasi jawaban gagal') === 0) {
            state.error = '';
        }
        setConnectionStatus('online', {
            persist: false,
            render: false,
            triggerRetry: false
        });
        syncPendingAnswerRuntimeState({
            persist: options.persist !== false,
            reason: 'sync-success'
        });
        maybeFinalizeLockedExam(options.reason || 'answer-sync-success');

        if (
            options.render !== false
            && state.stage === 'exam'
            && state.pendingSyncCount <= 0
            && !state.examLockedForPendingFinish
        ) {
            renderSyncUi(options.reason || 'sync-success', {
                pendingSyncCount: Number(state.pendingSyncCount) || 0
            });
        }
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
        publishSyncSnapshot('flush-scheduled');
        answerBatchFlushTimer = windowRef.setTimeout(function () {
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

        syncPendingAnswerRuntimeState({
            persist: true,
            clearLastSyncError: false,
            reason: 'answer-queued'
        });
        recordTimelineEntry('answer:queued', 'Jawaban masuk antrean sync.', {
            attemptId: Number(state.attemptId) || 0,
            questionId: questionId,
            stage: String(state.stage || '')
        });
        recordActionTrailEntry('answer:queued', 'Jawaban masuk antrean sync.', {
            questionId: questionId
        });
        return true;
    }

    function takePendingAnswerBatchItems(maxItems) {
        var limit = Math.max(1, Number(maxItems) || autoSaveBatchMaxItems);
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

        syncPendingAnswerRuntimeState({
            persist: true,
            clearLastSyncError: false,
            reason: 'batch-requeued'
        });
    }

    function applySubmittedBatchItems(items, responseItems, options) {
        options = options || {};
        var requestGeneration = Number(options.questionDataGeneration);
        if (Number.isFinite(requestGeneration) && requestGeneration !== getQuestionDataGeneration()) {
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

        noteSuccessfulAnswerSync({
            persist: true,
            reason: 'batch-submitted'
        });
    }

    async function submitLegacyAnswerBatch(items, options) {
        options = options || {};
        var responseItems = [];
        var submittedItems = [];

        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            try {
                var legacyPayload = await apiRequest('submit_answer', {
                    method: 'POST',
                    keepalive: !!options.keepalive,
                    body: {
                        attempt_id: state.attemptId,
                        question_id: item.question_id,
                        answer: item.answer
                    }
                });

                submittedItems.push(item);
                responseItems.push({
                    question_id: Number(item.question_id) || 0,
                    is_correct: legacyPayload && Object.prototype.hasOwnProperty.call(legacyPayload, 'is_correct')
                        ? legacyPayload.is_correct
                        : null,
                    score_awarded: Number(legacyPayload && legacyPayload.score_awarded !== undefined ? legacyPayload.score_awarded : 0),
                    deferred: Number(legacyPayload && legacyPayload.deferred !== undefined ? legacyPayload.deferred : 0),
                    cleared: Number(legacyPayload && legacyPayload.cleared !== undefined ? legacyPayload.cleared : 0)
                });
            } catch (error) {
                var partialError = error instanceof Error
                    ? error
                    : new Error('Legacy answer batch submission failed.');

                if (error && typeof error === 'object') {
                    Object.keys(error).forEach(function (key) {
                        partialError[key] = error[key];
                    });
                }

                partialError.partialSubmittedItems = submittedItems.slice();
                partialError.partialResponseItems = responseItems.slice();
                partialError.remainingItems = items.slice(i);
                throw partialError;
            }
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
            var batchResponse = await apiRequest('submit_answers_batch', {
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

            answerBatchInFlightItems = [];
            applySubmittedBatchItems(items, batchResponse && Array.isArray(batchResponse.items) ? batchResponse.items : [], {
                questionDataGeneration: requestGeneration
            });
            return batchResponse;
        } catch (batchError) {
            if (isRetryableAnswerSyncError(batchError) || !shouldFallbackToLegacyBatch(batchError)) {
                answerBatchInFlightItems = [];
                if (requestGeneration === getQuestionDataGeneration()) {
                    requeuePendingAnswerBatchItems(items);
                }
                throw batchError;
            }

            markAutoSaveCongested();

            try {
                var legacyResponse = await submitLegacyAnswerBatch(items, options);
                answerBatchInFlightItems = [];
                applySubmittedBatchItems(items, legacyResponse.items || [], {
                    questionDataGeneration: requestGeneration
                });
                if (requestGeneration === getQuestionDataGeneration()) {
                    state.lastSyncError = '';
                }
                return legacyResponse;
            } catch (legacyError) {
                answerBatchInFlightItems = [];
                if (requestGeneration === getQuestionDataGeneration()) {
                    var partialSubmittedItems = Array.isArray(legacyError && legacyError.partialSubmittedItems)
                        ? legacyError.partialSubmittedItems
                        : [];
                    var partialResponseItems = Array.isArray(legacyError && legacyError.partialResponseItems)
                        ? legacyError.partialResponseItems
                        : [];
                    var remainingItems = Array.isArray(legacyError && legacyError.remainingItems)
                        ? legacyError.remainingItems
                        : [];

                    if (partialSubmittedItems.length > 0) {
                        applySubmittedBatchItems(partialSubmittedItems, partialResponseItems, {
                            questionDataGeneration: requestGeneration
                        });
                    }

                    if (remainingItems.length > 0) {
                        requeuePendingAnswerBatchItems(remainingItems);
                    } else if (partialSubmittedItems.length <= 0) {
                        requeuePendingAnswerBatchItems(items);
                    }
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
            : getQuestionDataGeneration();

        clearAnswerBatchFlushTimer();

        if (
            diagnosticsManager
            && diagnosticsManager.enabled
            && typeof diagnosticsManager.isPendingSyncForced === 'function'
            && diagnosticsManager.isPendingSyncForced()
            && getPendingAnswerBatchCount() > 0
        ) {
            state.syncBlockingReason = 'forced_pending_sync';
            publishSyncSnapshot('force-pending-sync');
            recordTimelineEntry('sync:flush:blocked', 'Flush sync ditahan oleh Force Pending Sync.', {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || ''),
                pendingSyncCount: getPendingAnswerBatchCount()
            });
            recordActionTrailEntry('sync:forced-pending', 'Flush sync ditahan oleh scenario.', {
                pendingSyncCount: getPendingAnswerBatchCount()
            });
            return {
                attempt_id: state.attemptId,
                accepted_count: 0,
                buffered: getPendingAnswerBatchCount(),
                flushed: 0,
                pending_count: pendingAnswerBatchOrder.length,
                items: []
            };
        }

        while (true) {
            if (requestGeneration !== getQuestionDataGeneration()) {
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

            var items = takePendingAnswerBatchItems(autoSaveBatchMaxItems);
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
            answerBatchInFlightItems = items.slice();
            publishSyncSnapshot('flush-start');
            recordTimelineEntry('sync:flush:start', 'Sinkronisasi batch dimulai.', {
                attemptId: Number(state.attemptId) || 0,
                batchSize: items.length,
                stage: String(state.stage || '')
            });

            var result;
            try {
                result = await answerBatchFlushInFlight;
                publishSyncSnapshot('flush-success');
                recordTimelineEntry('sync:flush:success', 'Sinkronisasi batch berhasil.', {
                    attemptId: Number(state.attemptId) || 0,
                    batchSize: items.length,
                    stage: String(state.stage || '')
                });
            } catch (error) {
                recordTimelineEntry('sync:flush:error', error instanceof Error ? error.message : 'Sinkronisasi batch gagal.', {
                    attemptId: Number(state.attemptId) || 0,
                    batchSize: items.length,
                    stage: String(state.stage || ''),
                    error: error instanceof Error ? {
                        message: String(error.message || ''),
                        code: String(error.code || '')
                    } : null
                });
                if (requestGeneration === getQuestionDataGeneration()) {
                    if (isRetryableAnswerSyncError(error)) {
                        handleRecoverableAnswerSyncFailure(error, {
                            reason: 'flush-failed',
                            render: true
                        });
                    } else {
                        state.lastSyncError = error instanceof Error && error.message ? error.message : 'Sinkronisasi jawaban gagal.';
                        state.error = error instanceof Error ? ('Sinkronisasi jawaban gagal: ' + error.message) : 'Sinkronisasi jawaban gagal.';
                        syncPendingAnswerRuntimeState({
                            persist: true,
                            clearLastSyncError: false,
                            reason: 'flush-error'
                        });
                        renderSyncUi('flush-error', {
                            lastSyncError: String(state.lastSyncError || ''),
                            pendingSyncCount: Number(state.pendingSyncCount) || 0
                        });
                    }
                }
                throw error;
            } finally {
                answerBatchFlushInFlight = null;
                answerBatchInFlightItems = [];
            }

            if (!flushAll || pendingAnswerBatchOrder.length <= 0) {
                if (pendingAnswerBatchOrder.length > 0) {
                    schedulePendingAnswerRetry('flush-remaining', {
                        delayMs: 300
                    });
                }
                maybeFinalizeLockedExam('flush-complete');
                return result;
            }
        }
    }

    function scheduleAutoSave(questionId, delayMs) {
        var qid = Number(questionId) || 0;
        if (qid <= 0 || state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing || state.examLockedForPendingFinish || isQuestionRevisionRefreshActive()) {
            return;
        }

        if (autoSaveTimersByQuestion[qid]) {
            windowRef.clearTimeout(autoSaveTimersByQuestion[qid]);
            delete autoSaveTimersByQuestion[qid];
        }

        var waitMs = resolveAutoSaveDelay(delayMs);
        autoSaveTimersByQuestion[qid] = windowRef.setTimeout(function () {
            delete autoSaveTimersByQuestion[qid];
            runAutoSave(qid);
        }, waitMs);
    }

    async function runAutoSave(questionId, options) {
        options = options || {};
        var qid = Number(questionId) || 0;
        var requestGeneration = Number.isFinite(Number(options.questionDataGeneration))
            ? Number(options.questionDataGeneration)
            : getQuestionDataGeneration();
        if (
            qid <= 0
            || state.stage !== 'exam'
            || state.attemptId <= 0
            || state.isFinishing
            || state.examLockedForPendingFinish
            || isQuestionRevisionRefreshActive()
            || requestGeneration !== getQuestionDataGeneration()
        ) {
            return;
        }

        var question = getQuestionById(qid);
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
                schedulePendingAnswerRetry('autosave-queued', {
                    delayMs: 150
                });
            }
        } catch (error) {
            if (requestGeneration !== getQuestionDataGeneration()) {
                return;
            }

            if (isRetryableAnswerSyncError(error)) {
                handleRecoverableAnswerSyncFailure(error, {
                    reason: 'autosave',
                    render: true
                });
                if (!options.immediate) {
                    scheduleAutoSave(qid, 2600);
                }
                return;
            }

            state.lastSyncError = error instanceof Error && error.message ? error.message : 'Sinkronisasi jawaban gagal.';
            state.error = error instanceof Error ? ('Sinkronisasi jawaban gagal: ' + error.message) : 'Sinkronisasi jawaban gagal.';
            markAutoSaveCongested();
            syncPendingAnswerRuntimeState({
                persist: true,
                clearLastSyncError: false
            });
            if (options.immediate) {
                throw error;
            }
        }
    }

    function handleScenarioStateChange() {
        syncPendingAnswerRuntimeState({
            persist: false,
            clearLastSyncError: false,
            reason: 'scenario-state-change'
        });

        if (
            state.stage === 'exam'
            && state.attemptId > 0
            && state.pendingSyncCount > 0
            && getNavigatorConnectionStatus() !== 'offline'
            && !state.isFinishing
            && !state.examLockedForPendingFinish
            && !isQuestionRevisionRefreshActive()
            && !(
                diagnosticsManager
                && diagnosticsManager.enabled
                && typeof diagnosticsManager.isPendingSyncForced === 'function'
                && diagnosticsManager.isPendingSyncForced()
            )
        ) {
            schedulePendingAnswerRetry('scenario-state-change', {
                immediate: true,
                resetBackoff: true,
                render: true,
                persist: false
            });
        } else if (state.stage === 'exam') {
            renderSyncUi('scenario-state-change', {
                pendingSyncCount: Number(state.pendingSyncCount) || 0
            });
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

    function pruneQuestionAnswerState(validLookup) {
        function pruneLookup(source) {
            Object.keys(source || {}).forEach(function (key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && !validLookup[questionId]) {
                    delete source[key];
                }
            });
        }

        pruneLookup(lastSubmittedPayloadByQuestion);
        pruneLookup(pendingAnswerBatchByQuestion);

        pendingAnswerBatchOrder = pendingAnswerBatchOrder.filter(function (item) {
            var questionId = Number(item) || 0;
            return questionId > 0 && validLookup[questionId];
        });

        syncPendingAnswerRuntimeState({
            persist: false,
            clearLastSyncError: false,
            reason: 'prune-answer-state'
        });
    }

    function hasFlushInFlight() {
        return !!answerBatchFlushInFlight;
    }

    function hasPendingBatchItems() {
        return pendingAnswerBatchOrder.length > 0;
    }

    return {
        clearAllAutoSaveTimers: clearAllAutoSaveTimers,
        clearAutoSaveRuntimeState: clearAutoSaveRuntimeState,
        flushPendingAnswerBatch: flushPendingAnswerBatch,
        getAutoSaveState: getAutoSaveState,
        getPendingAnswerBatchCount: getPendingAnswerBatchCount,
        handleRecoverableAnswerSyncFailure: handleRecoverableAnswerSyncFailure,
        hasFlushInFlight: hasFlushInFlight,
        hasPendingBatchItems: hasPendingBatchItems,
        initializeSubmittedPayloadCache: initializeSubmittedPayloadCache,
        isAnswerSubmitPath: isAnswerSubmitPath,
        isNetworkConnectivityError: isNetworkConnectivityError,
        isRetryableAnswerSyncError: isRetryableAnswerSyncError,
        primeSubmittedPayloadCacheFromQuestionItems: primeSubmittedPayloadCacheFromQuestionItems,
        pruneQuestionAnswerState: pruneQuestionAnswerState,
        queueLoadedQuestionAnswersForFlush: queueLoadedQuestionAnswersForFlush,
        queueQuestionAnswer: queueQuestionAnswer,
        queueQuestionAnswersByIds: queueQuestionAnswersByIds,
        restoreQuestionAutoSaveState: restoreQuestionAutoSaveState,
        handleScenarioStateChange: handleScenarioStateChange,
        scheduleAnswerBatchFlush: scheduleAnswerBatchFlush,
        scheduleAutoSave: scheduleAutoSave,
        schedulePendingAnswerRetry: schedulePendingAnswerRetry,
        setConnectionStatus: setConnectionStatus,
        shouldFallbackToLegacyBatch: shouldFallbackToLegacyBatch,
        submitQuestionAnswer: submitQuestionAnswer,
        syncPendingAnswerRuntimeState: syncPendingAnswerRuntimeState
    };
}
