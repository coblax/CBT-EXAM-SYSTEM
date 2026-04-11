export function createQuestionRuntimeManager(deps) {
    var diagnosticsManager = deps.diagnosticsManager;
    var applyAttemptUiState = deps.applyAttemptUiState;
    var applyPendingRevisionSafeAnswersForLoadedQuestions = deps.applyPendingRevisionSafeAnswersForLoadedQuestions;
    var attemptUiStateSyncDelayMs = deps.attemptUiStateSyncDelayMs;
    var buildAttemptUiStateSnapshot = deps.buildAttemptUiStateSnapshot;
    var buildAutoSaveStateSnapshot = deps.buildAutoSaveStateSnapshot;
    var buildChangedQuestionLookup = deps.buildChangedQuestionLookup;
    var buildQuestionManifestById = deps.buildQuestionManifestById;
    var buildQuestionManifestFromQuestions = deps.buildQuestionManifestFromQuestions;
    var buildQuestionOrderSignature = deps.buildQuestionOrderSignature;
    var clearAttemptUiStateSyncTimer = deps.clearAttemptUiStateSyncTimer;
    var clearAutoSaveRuntimeState = deps.clearAutoSaveRuntimeState;
    var clearPendingRevisionSafeAnswerRestoreState = deps.clearPendingRevisionSafeAnswerRestoreState;
    var clearPersistedQuestionCache = deps.clearPersistedQuestionCache;
    var clearQuestionPrefetchRuntimeState = deps.clearQuestionPrefetchRuntimeState;
    var clampQuestionIndex = deps.clampQuestionIndex;
    var captureRevisionSafeLocalAnswers = deps.captureRevisionSafeLocalAnswers;
    var getQuestionCount = deps.getQuestionCount;
    var getQuestionIdAtIndex = deps.getQuestionIdAtIndex;
    var getQuestionManifestById = deps.getQuestionManifestById;
    var getQuestionPayloadById = deps.getQuestionPayloadById;
    var hasPendingQueuedAnswerBatchItems = deps.hasPendingQueuedAnswerBatchItems;
    var hasUsableLocalAnswerForQuestion = deps.hasUsableLocalAnswerForQuestion;
    var initializeSubmittedPayloadCache = deps.initializeSubmittedPayloadCache;
    var isIndexInCurrentWindow = deps.isIndexInCurrentWindow;
    var isQuestionPayloadLoaded = deps.isQuestionPayloadLoaded;
    var markQuestionWindowLoaded = deps.markQuestionWindowLoaded;
    var mergeExistingAnswersFromQuestionItems = deps.mergeExistingAnswersFromQuestionItems;
    var mergeExistingAnswersMap = deps.mergeExistingAnswersMap;
    var normalizeNavigationQuestionFilter = deps.normalizeNavigationQuestionFilter;
    var normalizeOrUseQuestionCacheSnapshot = deps.normalizeOrUseQuestionCacheSnapshot;
    var normalizeQuestionCacheSnapshot = deps.normalizeQuestionCacheSnapshot;
    var normalizeQuestionIdList = deps.normalizeQuestionIdList;
    var normalizeQuestionRevision = deps.normalizeQuestionRevision;
    var persistCurrentAttemptUiStateLocally = deps.persistCurrentAttemptUiStateLocally;
    var persistCurrentQuestionCacheLocally = deps.persistCurrentQuestionCacheLocally;
    var primeSubmittedPayloadCacheFromQuestionItems = deps.primeSubmittedPayloadCacheFromQuestionItems;
    var pruneAnswerSyncState = deps.pruneAnswerSyncState;
    var prunePendingRevisionSafeAnswerRestoreState = deps.prunePendingRevisionSafeAnswerRestoreState;
    var questionOrderSignatureEquals = deps.questionOrderSignatureEquals;
    var questionRevisionEquals = deps.questionRevisionEquals;
    var questionWindowSize = deps.questionWindowSize;
    var questionWindowOffsetForIndex = deps.questionWindowOffsetForIndex;
    var queueLoadedQuestionAnswersForFlush = deps.queueLoadedQuestionAnswersForFlush;
    var queueQuestionAnswersByIds = deps.queueQuestionAnswersByIds;
    var render = deps.render;
    var renderExamPartial = deps.renderExamPartial;
    var recordActionTrail = deps.recordActionTrail;
    var recordTimeline = deps.recordTimeline;
    var restoreLocalAnswerFromQuestion = deps.restoreLocalAnswerFromQuestion;
    var restoreQuestionAutoSaveState = deps.restoreQuestionAutoSaveState;
    var restoreRevisionSafeLocalAnswers = deps.restoreRevisionSafeLocalAnswers;
    var scheduleAttemptUiStateSync = deps.scheduleAttemptUiStateSync;
    var schedulePendingAnswerRetry = deps.schedulePendingAnswerRetry;
    var setQuestionWindowFromLoadedPayloads = deps.setQuestionWindowFromLoadedPayloads;
    var state = deps.state;
    var syncAttemptUiStateSignatureToCurrentState = deps.syncAttemptUiStateSignatureToCurrentState;
    var updateQuestionPrefetchIndicator = deps.updateQuestionPrefetchIndicator;
    var validAttemptQuestionIds = deps.validAttemptQuestionIds;
    var apiRequest = deps.apiRequest;
    var windowRef = deps.windowRef;

    var questionCachePersistTimer = 0;
    var questionDataGeneration = 0;
    var questionRevisionRefreshInFlight = null;
    var blockedQuestionOrderSignature = '';

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
            windowRef.setTimeout(resolve, waitMs);
        });
    }

    function renderRevisionPatch(regions, reason, meta) {
        if (typeof renderExamPartial === 'function') {
            var didPatch = renderExamPartial(regions, reason, meta);
            if (didPatch) {
                return true;
            }
        }

        render(reason, meta);
        return false;
    }

    function buildQuestionWindowScenarioError(target) {
        var scenarioTarget = String(target || 'any');
        var error = new Error('Scenario aktif: fail next question window (' + scenarioTarget + ').');
        error.code = 'scenario_fail_next_question_window';
        error.isScenarioError = true;
        return error;
    }

    function bumpQuestionDataGeneration() {
        questionDataGeneration = (questionDataGeneration + 1) % 2147483647;
        if (questionDataGeneration <= 0) {
            questionDataGeneration = 1;
        }
        return questionDataGeneration;
    }

    function getQuestionDataGeneration() {
        return questionDataGeneration;
    }

    function isQuestionRevisionRefreshActive() {
        return questionRevisionRefreshInFlight !== null;
    }

    function clearQuestionRevisionRefreshState() {
        questionRevisionRefreshInFlight = null;
        clearBlockedQuestionOrderSignature();
    }

    function setQuestionRevision(revision, fallbackExamId) {
        state.questionRevision = normalizeQuestionRevision(revision, fallbackExamId || state.selectedExamId || 0);
        return state.questionRevision;
    }

    function getChangedQuestionCount() {
        return Object.keys(state.changedQuestionLookup || {}).reduce(function (count, key) {
            var questionId = Number(key) || 0;
            return count + (questionId > 0 && state.changedQuestionLookup[key] ? 1 : 0);
        }, 0);
    }

    function getQuestionRevisionMarkerCount() {
        return Object.keys(state.questionRevisionMarkerLookup || {}).reduce(function (count, key) {
            var questionId = Number(key) || 0;
            return count + (questionId > 0 && state.questionRevisionMarkerLookup[key] ? 1 : 0);
        }, 0);
    }

    function clearQuestionRevisionToastTimer() {
        if (state.questionRevisionToastTimerId) {
            windowRef.clearTimeout(state.questionRevisionToastTimerId);
        }
        state.questionRevisionToastTimerId = 0;
    }

    function clearQuestionRevisionNotice(options) {
        options = options || {};

        clearQuestionRevisionToastTimer();
        if (!state.questionRevisionNotice) {
            return false;
        }

        state.questionRevisionNotice = null;

        if (options.render) {
            renderRevisionPatch({
                notice: true
            }, options.reason || 'question-revision:notice-clear', options.meta || {});
        }

        return true;
    }

    function setQuestionRevisionNotice(notice, options) {
        options = options || {};

        clearQuestionRevisionToastTimer();

        if (!notice || !String(notice.message || '').trim()) {
            clearQuestionRevisionNotice({
                render: !!options.render,
                reason: options.reason,
                meta: options.meta
            });
            return null;
        }

        var noticeId = String(Date.now()) + ':' + Math.random().toString(36).slice(2, 8);
        state.questionRevisionNotice = {
            id: noticeId,
            kind: String(notice.kind || 'toast'),
            tone: String(notice.tone || 'info'),
            sticky: !!notice.sticky,
            message: String(notice.message || '').trim()
        };

        if (!state.questionRevisionNotice.sticky) {
            var durationMs = Math.max(0, Number(options.durationMs) || 5000);
            state.questionRevisionToastTimerId = windowRef.setTimeout(function () {
                if (!state.questionRevisionNotice || state.questionRevisionNotice.id !== noticeId) {
                    return;
                }

                state.questionRevisionNotice = null;
                state.questionRevisionToastTimerId = 0;
                if (state.stage === 'exam') {
                    renderRevisionPatch({
                        notice: true
                    }, 'question-revision:toast-dismiss', {
                        noticeKind: 'toast'
                    });
                }
            }, durationMs);
        }

        if (options.render) {
            renderRevisionPatch({
                notice: true
            }, options.reason || 'question-revision:notice-set', options.meta || {});
        }

        return state.questionRevisionNotice;
    }

    function buildQuestionRevisionMarkerLookup(existingMarkerLookup, latestChangedLookup, acknowledgedLookup) {
        var mergedLookup = {};
        var safeExistingMarkerLookup = existingMarkerLookup && typeof existingMarkerLookup === 'object'
            ? existingMarkerLookup
            : {};
        var safeLatestChangedLookup = latestChangedLookup && typeof latestChangedLookup === 'object'
            ? latestChangedLookup
            : {};
        var safeAcknowledgedLookup = acknowledgedLookup && typeof acknowledgedLookup === 'object'
            ? acknowledgedLookup
            : {};

        Object.keys(safeExistingMarkerLookup).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && safeExistingMarkerLookup[key]) {
                mergedLookup[questionId] = true;
            }
        });

        Object.keys(safeLatestChangedLookup).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId <= 0 || !safeLatestChangedLookup[key]) {
                return;
            }

            mergedLookup[questionId] = true;
            delete safeAcknowledgedLookup[questionId];
        });

        Object.keys(safeAcknowledgedLookup).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && safeAcknowledgedLookup[key]) {
                delete mergedLookup[questionId];
            }
        });

        return mergedLookup;
    }

    function clearBlockedQuestionOrderSignature() {
        blockedQuestionOrderSignature = '';
    }

    function setBlockedQuestionOrderSignature(signature) {
        blockedQuestionOrderSignature = String(signature || '').trim();
    }

    function buildQuestionPayloadById(items) {
        if (!Array.isArray(items)) {
            return {};
        }

        return items.reduce(function (accumulator, item) {
            if (!item || typeof item !== 'object') {
                return accumulator;
            }

            var questionId = Number(item.id) || 0;
            if (questionId > 0) {
                accumulator[questionId] = item;
            }
            return accumulator;
        }, {});
    }

    function getQuestionNumberFromContract(questionId, manifestById, payloadById) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0) {
            return 0;
        }

        var manifestItem = manifestById && typeof manifestById === 'object'
            ? manifestById[safeQuestionId] || null
            : null;
        var payloadItem = payloadById && typeof payloadById === 'object'
            ? payloadById[safeQuestionId] || null
            : null;

        return Number(
            manifestItem && manifestItem.question_number !== undefined
                ? manifestItem.question_number
                : (payloadItem && payloadItem.question_number !== undefined ? payloadItem.question_number : 0)
        ) || 0;
    }

    function buildQuestionOrderConflictError(message, code, signature, detail) {
        var error = new Error(String(message || 'Urutan soal terbaru tidak valid.'));
        error.code = String(code || 'question_order_contract_invalid');
        error.detail = String(detail || '');
        error.isQuestionOrderConflict = true;
        error.questionOrderSignature = String(signature || '').trim();
        return error;
    }

    function buildQuestionOrderContract(questionPayload) {
        var responseItems = questionPayload && Array.isArray(questionPayload.items) ? questionPayload.items : [];
        var hasExplicitQuestionOrderIds = !!(questionPayload && Array.isArray(questionPayload.question_order_ids));
        var hasExplicitQuestionManifest = !!(questionPayload && Array.isArray(questionPayload.question_manifest));
        var responseOrderIds = questionPayload && Array.isArray(questionPayload.question_order_ids)
            ? questionPayload.question_order_ids
            : responseItems.map(function (question) { return Number(question && question.id) || 0; });
        var responseManifest = questionPayload && Array.isArray(questionPayload.question_manifest)
            ? questionPayload.question_manifest
            : buildQuestionManifestFromQuestions(responseItems);
        var normalizedOrderIds = normalizeQuestionIdList(responseOrderIds);
        var normalizedResponseOrderIds = Array.isArray(responseOrderIds)
            ? responseOrderIds.reduce(function (accumulator, item) {
                var questionId = Number(item) || 0;
                if (questionId > 0) {
                    accumulator.push(questionId);
                }
                return accumulator;
            }, [])
            : [];
        var responseManifestById = buildQuestionManifestById(responseManifest);
        var responsePayloadById = buildQuestionPayloadById(responseItems);
        var computedQuestionOrderSignature = buildQuestionOrderSignature(
            normalizedOrderIds,
            responseManifest,
            responseItems
        );
        var payloadQuestionOrderSignature = String(questionPayload && questionPayload.question_order_signature || '').trim();
        var questionOrderLookup = normalizedOrderIds.reduce(function (accumulator, questionId) {
            accumulator[questionId] = true;
            return accumulator;
        }, {});
        var normalizedResponseItemIds = responseItems.reduce(function (accumulator, question) {
            var questionId = Number(question && question.id) || 0;
            if (questionId > 0) {
                accumulator.push(questionId);
            }
            return accumulator;
        }, []);
        var usedQuestionNumbers = {};
        var previousQuestionNumber = 0;
        var invalidDetail = '';

        if (!normalizedOrderIds.length) {
            invalidDetail = 'empty-order';
        }
        if (
            invalidDetail === ''
            && hasExplicitQuestionOrderIds
            && normalizedOrderIds.length !== normalizedResponseOrderIds.length
        ) {
            invalidDetail = 'duplicate-or-invalid-question-id';
        }
        if (
            invalidDetail === ''
            && Object.keys(responsePayloadById).length !== normalizedResponseItemIds.length
        ) {
            invalidDetail = 'duplicate-response-question-id';
        }
        if (invalidDetail === '') {
            normalizedResponseItemIds.forEach(function (questionId) {
                if (invalidDetail !== '' || questionOrderLookup[questionId]) {
                    return;
                }

                invalidDetail = 'response-outside-order:' + String(questionId);
            });
        }
        if (invalidDetail === '' && hasExplicitQuestionManifest) {
            if (Object.keys(responseManifestById).length !== responseManifest.length) {
                invalidDetail = 'duplicate-manifest-question-id';
            } else if (Object.keys(responseManifestById).length !== normalizedOrderIds.length) {
                invalidDetail = 'missing-manifest-entry';
            } else {
                Object.keys(responseManifestById).forEach(function (key) {
                    var questionId = Number(key) || 0;
                    if (invalidDetail !== '' || questionId <= 0 || questionOrderLookup[questionId]) {
                        return;
                    }

                    invalidDetail = 'manifest-outside-order:' + String(questionId);
                });
            }
        }

        normalizedOrderIds.forEach(function (questionId) {
            if (invalidDetail !== '') {
                return;
            }

            if (!responseManifestById[questionId] && !responsePayloadById[questionId]) {
                invalidDetail = 'missing-question:' + String(questionId);
                return;
            }

            var questionNumber = getQuestionNumberFromContract(
                questionId,
                responseManifestById,
                responsePayloadById
            );
            if (questionNumber <= 0) {
                invalidDetail = 'missing-number:' + String(questionId);
                return;
            }
            if (usedQuestionNumbers[questionNumber]) {
                invalidDetail = 'duplicate-number:' + String(questionNumber);
                return;
            }
            if (questionNumber <= previousQuestionNumber) {
                invalidDetail = 'unordered-number:' + String(questionId);
                return;
            }

            usedQuestionNumbers[questionNumber] = true;
            previousQuestionNumber = questionNumber;
        });

        if (
            invalidDetail === ''
            && payloadQuestionOrderSignature !== ''
            && computedQuestionOrderSignature !== ''
            && !questionOrderSignatureEquals(payloadQuestionOrderSignature, computedQuestionOrderSignature)
        ) {
            invalidDetail = 'signature-mismatch';
        }

        return {
            isValid: invalidDetail === '',
            invalidDetail: invalidDetail,
            items: responseItems,
            questionManifest: responseManifest,
            questionManifestById: responseManifestById,
            questionOrderIds: normalizedOrderIds,
            questionOrderSignature: payloadQuestionOrderSignature || computedQuestionOrderSignature,
            questionPayloadById: responsePayloadById
        };
    }

    function buildActiveQuestionPayloadMap(questionOrderIds, existingPayloadById, responsePayloadById, manifestById) {
        var normalizedOrderIds = normalizeQuestionIdList(questionOrderIds);
        var safeExistingPayloadById = existingPayloadById && typeof existingPayloadById === 'object'
            ? existingPayloadById
            : {};
        var safeResponsePayloadById = responsePayloadById && typeof responsePayloadById === 'object'
            ? responsePayloadById
            : {};
        var safeManifestById = manifestById && typeof manifestById === 'object'
            ? manifestById
            : {};

        return normalizedOrderIds.reduce(function (accumulator, questionId) {
            var payloadQuestion = safeResponsePayloadById[questionId] || safeExistingPayloadById[questionId] || null;
            if (!payloadQuestion) {
                return accumulator;
            }

            var manifestQuestion = safeManifestById[questionId] || null;
            if (manifestQuestion && Number(manifestQuestion.question_number) > 0) {
                payloadQuestion = Object.assign({}, payloadQuestion, {
                    question_number: Number(manifestQuestion.question_number) || 0
                });
            }

            accumulator[questionId] = payloadQuestion;
            return accumulator;
        }, {});
    }

    function restorePersistedQuestionCacheAnswerState(snapshot, options) {
        options = options || {};

        var normalizedSnapshot = normalizeOrUseQuestionCacheSnapshot(snapshot, options.attemptId || state.attemptId);
        if (!normalizedSnapshot) {
            return false;
        }

        var expectedExamId = Number(options.examId) || 0;
        if (expectedExamId > 0 && normalizedSnapshot.examId > 0 && normalizedSnapshot.examId !== expectedExamId) {
            return false;
        }

        state.answers = Object.assign({}, normalizedSnapshot.answers || {}, state.answers || {});
        state.existingAnswerRawByQuestionId = Object.assign(
            {},
            normalizedSnapshot.existingAnswerRawByQuestionId || {},
            state.existingAnswerRawByQuestionId || {}
        );
        state.answeredQuestionLookup = Object.assign(
            {},
            normalizedSnapshot.answeredQuestionLookup || {},
            state.answeredQuestionLookup || {}
        );
        restoreQuestionAutoSaveState(normalizedSnapshot);
        return true;
    }

    function acknowledgeQuestionRevisionMarker(questionId, options) {
        options = options || {};

        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0) {
            return false;
        }

        var didChange = false;
        var hadMarker = !!(state.questionRevisionMarkerLookup && state.questionRevisionMarkerLookup[safeQuestionId]);
        if (hadMarker) {
            delete state.questionRevisionMarkerLookup[safeQuestionId];
            didChange = true;
        }

        if ((hadMarker || options.forceAcknowledge) && !state.acknowledgedRevisionQuestionIds[safeQuestionId]) {
            state.acknowledgedRevisionQuestionIds[safeQuestionId] = true;
            didChange = true;
        }

        if (didChange && options.persist !== false) {
            scheduleQuestionCachePersist(0);
        }

        if (didChange && options.render) {
            renderRevisionPatch({
                navigation: true
            }, options.reason || 'question-revision:marker-acknowledged', options.meta || {
                questionId: safeQuestionId
            });
        }

        return didChange;
    }

    function clearStickyQuestionRevisionNotice(options) {
        if (!state.questionRevisionNotice || !state.questionRevisionNotice.sticky) {
            return false;
        }

        return clearQuestionRevisionNotice(options);
    }

    function buildAddedQuestionIds(previousManifestById, nextManifestById) {
        var safePreviousManifestById = previousManifestById && typeof previousManifestById === 'object'
            ? previousManifestById
            : {};
        var safeNextManifestById = nextManifestById && typeof nextManifestById === 'object'
            ? nextManifestById
            : {};

        return Object.keys(safeNextManifestById).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId <= 0 || safePreviousManifestById[questionId]) {
                return accumulator;
            }

            accumulator.push(questionId);
            return accumulator;
        }, []);
    }

    function buildQuestionRevisionNotice(changedQuestionCount, addedQuestionCount) {
        var safeChangedCount = Math.max(0, Number(changedQuestionCount) || 0);
        var safeAddedCount = Math.max(0, Number(addedQuestionCount) || 0);

        if (safeAddedCount > 0) {
            if (safeChangedCount > 0) {
                return String(safeAddedCount) + ' soal baru ditambahkan, ' + String(safeChangedCount) + ' soal berubah.';
            }

            return String(safeAddedCount) + ' soal baru ditambahkan.';
        }

        if (safeChangedCount > 0) {
            return String(safeChangedCount) + ' soal berubah.';
        }

        return '';
    }

    function clearQuestionCachePersistTimer() {
        if (questionCachePersistTimer) {
            windowRef.clearTimeout(questionCachePersistTimer);
        }
        questionCachePersistTimer = 0;
    }

    function scheduleQuestionCachePersist(delayMs) {
        if (state.attemptId <= 0) {
            return;
        }

        clearQuestionCachePersistTimer();
        questionCachePersistTimer = windowRef.setTimeout(function () {
            clearQuestionCachePersistTimer();
            persistCurrentQuestionCacheLocally();
        }, Math.max(0, Number(delayMs) || 0));
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

        if (options.restoreAnswersOnly) {
            return restorePersistedQuestionCacheAnswerState(normalizedSnapshot, options);
        }

        var expectedQuestionOrderSignature = String(options.expectedQuestionOrderSignature || '').trim();
        if (
            expectedQuestionOrderSignature !== ''
            && !questionOrderSignatureEquals(normalizedSnapshot.questionOrderSignature, expectedQuestionOrderSignature)
        ) {
            if (options.restoreAnswersOnlyOnSignatureMismatch !== false) {
                restorePersistedQuestionCacheAnswerState(normalizedSnapshot, options);
            }
            return false;
        }

        state.questionOrderIds = normalizedSnapshot.questionOrderIds;
        state.totalQuestions = normalizedSnapshot.totalQuestions;
        state.questionOrderSignature = String(normalizedSnapshot.questionOrderSignature || '').trim();
        clearBlockedQuestionOrderSignature();
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
        state.questionRevisionMarkerLookup = normalizedSnapshot.questionRevisionMarkerLookup || {};
        state.acknowledgedRevisionQuestionIds = normalizedSnapshot.acknowledgedRevisionQuestionIds || {};
        state.answers = normalizedSnapshot.answers;
        state.existingAnswerRawByQuestionId = normalizedSnapshot.existingAnswerRawByQuestionId;
        state.loadedQuestionWindowOffsets = normalizedSnapshot.loadedQuestionWindowOffsets;
        restoreQuestionAutoSaveState(normalizedSnapshot);
        if (normalizedSnapshot.questionRevision) {
            setQuestionRevision(normalizedSnapshot.questionRevision, expectedExamId || normalizedSnapshot.examId || 0);
        }

        var targetWindowSize = Math.max(1, Number(options.windowSize) || normalizedSnapshot.windowLimit || questionWindowSize);
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
        state.questionRevisionMarkerLookup = {};
        state.acknowledgedRevisionQuestionIds = {};
        state.loadedQuestionWindowOffsets = {};
        state.windowOffset = 0;
        state.windowLimit = 0;
        state.totalQuestions = 0;
        state.questionOrderSignature = '';
        state.answers = {};
        clearQuestionRevisionToastTimer();
        state.questionRevisionNotice = null;

        if (!options.preserveDoubtful) {
            state.doubtful = {};
        }
        if (!options.preserveCurrentIndex) {
            state.currentIndex = 0;
        }
        if (!options.preserveNavFilter) {
            state.navQuestionFilter = deps.navQuestionFilterAll;
        }
        if (!options.preserveQuestionRevision) {
            state.questionRevision = null;
        }
    }

    function applyQuestionsResponse(questionPayload, options) {
        options = options || {};
        var questionOrderContract = buildQuestionOrderContract(questionPayload);
        if (!questionOrderContract.isValid) {
            throw buildQuestionOrderConflictError(
                'Urutan soal terbaru tidak valid. Muat ulang halaman untuk melanjutkan dengan aman.',
                'question_order_contract_invalid',
                questionOrderContract.questionOrderSignature,
                questionOrderContract.invalidDetail
            );
        }

        var responseItems = questionOrderContract.items;
        var normalizedOrderIds = questionOrderContract.questionOrderIds;
        var responseManifest = questionOrderContract.questionManifest;
        var responseManifestById = questionOrderContract.questionManifestById;
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
        var previousQuestionOrderSignature = String(state.questionOrderSignature || '').trim();
        var didQuestionOrderChange = (
            previousQuestionOrderSignature !== ''
            && !questionOrderSignatureEquals(previousQuestionOrderSignature, questionOrderContract.questionOrderSignature)
        );
        var responseOffset = Math.max(0, Number(questionPayload && questionPayload.offset) || 0);
        var responseLimit = Math.max(0, Number(questionPayload && questionPayload.limit) || 0);

        if (didQuestionOrderChange) {
            state.loadedQuestionWindowOffsets = {};
        }

        state.questionOrderIds = normalizedOrderIds;
        state.totalQuestions = Math.max(
            normalizedOrderIds.length,
            Number(questionPayload && questionPayload.total_questions) || 0
        );
        state.questionOrderSignature = questionOrderContract.questionOrderSignature;
        state.questionManifestById = responseManifestById;
        state.questionManifest = normalizedOrderIds.reduce(function (accumulator, questionId) {
            var manifestItem = responseManifestById[questionId] || null;
            if (manifestItem) {
                accumulator.push(manifestItem);
            }
            return accumulator;
        }, []);
        state.questionPayloadById = buildActiveQuestionPayloadMap(
            normalizedOrderIds,
            state.questionPayloadById,
            questionOrderContract.questionPayloadById,
            responseManifestById
        );

        if (responseRevision) {
            setQuestionRevision(responseRevision, Number(state.selectedExamId) || 0);
        }
        if (responseArchivedReviewItems !== null) {
            state.archivedReviewItems = responseArchivedReviewItems;
        }

        if (options.replaceAnsweredState) {
            state.answeredQuestionLookup = {};
        }

        if (responseAnsweredQuestionIds.length) {
            responseAnsweredQuestionIds.forEach(function (questionId) {
                state.answeredQuestionLookup[questionId] = true;
            });
        }

        if (!options.preserveActiveWindow) {
            state.windowOffset = responseOffset;
            state.windowLimit = responseLimit;
            state.questions = responseItems;
        } else if (state.windowLimit > 0) {
            setQuestionWindowFromLoadedPayloads(state.windowOffset, state.windowLimit);
        }
        markQuestionWindowLoaded(responseOffset);
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
                schedulePendingAnswerRetry('restore-deferred-answers', {
                    delayMs: 300
                });
            }
        }
        pruneQuestionScopedState(validAttemptQuestionIds());
        clearBlockedQuestionOrderSignature();
        scheduleQuestionCachePersist(0);
        updateQuestionPrefetchIndicator();
        return true;
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
        pruneLookup(state.questionRevisionMarkerLookup);
        pruneLookup(state.acknowledgedRevisionQuestionIds);
        pruneLookup(state.doubtful);
        prunePendingRevisionSafeAnswerRestoreState(validLookup);
        pruneAnswerSyncState(validLookup);
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

    async function loadQuestionWindow(offset, options) {
        options = options || {};

        var examId = Number(options.examId !== undefined ? options.examId : state.selectedExamId) || 0;
        var attemptId = Number(options.attemptId !== undefined ? options.attemptId : state.attemptId) || 0;
        var includeExisting = options.includeExisting !== undefined ? options.includeExisting : 1;
        var includeAnswerManifest = options.includeAnswerManifest !== undefined ? options.includeAnswerManifest : 0;
        var windowLimit = Math.max(1, Number(options.limit) || questionWindowSize);
        var requestGeneration = questionDataGeneration;
        var scenarioTarget = String(options.scenarioTarget || 'current').trim().toLowerCase() === 'prefetch'
            ? 'prefetch'
            : 'current';

        if (examId <= 0 || attemptId <= 0) {
            throw new Error('Sesi ujian tidak valid.');
        }

        if (
            diagnosticsManager
            && diagnosticsManager.enabled
            && typeof diagnosticsManager.getQuestionWindowLatencyMs === 'function'
        ) {
            var scenarioLatencyMs = Number(diagnosticsManager.getQuestionWindowLatencyMs()) || 0;
            if (scenarioLatencyMs > 0) {
                recordTimelineEntry('question-window:delayed', 'Scenario melambatkan load question window.', {
                    attemptId: attemptId,
                    selectedExamId: examId,
                    stage: String(state.stage || ''),
                    offset: Math.max(0, Number(offset) || 0),
                    limit: windowLimit,
                    target: scenarioTarget,
                    latencyMs: scenarioLatencyMs
                });
                recordActionTrailEntry('question-window:delayed', 'Load question window diperlambat oleh scenario.', {
                    target: scenarioTarget,
                    offset: Math.max(0, Number(offset) || 0),
                    limit: windowLimit,
                    latencyMs: scenarioLatencyMs
                });
                await delay(scenarioLatencyMs);
            }
        }

        if (
            diagnosticsManager
            && diagnosticsManager.enabled
            && typeof diagnosticsManager.consumeFailNextQuestionWindow === 'function'
            && diagnosticsManager.consumeFailNextQuestionWindow(scenarioTarget)
        ) {
            var scenarioError = buildQuestionWindowScenarioError(scenarioTarget);
            recordTimelineEntry('question-window:failed', scenarioError.message, {
                attemptId: attemptId,
                selectedExamId: examId,
                stage: String(state.stage || ''),
                offset: Math.max(0, Number(offset) || 0),
                limit: windowLimit,
                target: scenarioTarget,
                code: scenarioError.code
            });
            recordActionTrailEntry('question-window:failed', 'Load question window digagalkan oleh scenario.', {
                target: scenarioTarget,
                offset: Math.max(0, Number(offset) || 0),
                limit: windowLimit,
                code: scenarioError.code
            });
            throw scenarioError;
        }

        var questionQuery = {
            exam_id: examId,
            attempt_id: attemptId,
            include_existing: Number(includeExisting) ? 1 : 0,
            include_answer_manifest: Number(includeAnswerManifest) ? 1 : 0,
            offset: Math.max(0, Number(offset) || 0),
            limit: windowLimit
        };
        if (options.bootstrapLight === true) {
            questionQuery.bootstrap_light = 1;
        }

        var questionPayload = await apiRequest('questions', {
            query: questionQuery
        });
        var responseRevision = normalizeQuestionRevision(
            questionPayload && questionPayload.question_revision,
            examId
        );
        var responseQuestionOrderSignature = String(questionPayload && questionPayload.question_order_signature || '').trim();
        if (questionPayload && typeof questionPayload === 'object') {
            questionPayload.question_revision = deps.serializeQuestionRevision(responseRevision, examId);
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
            && (
                (
                    responseRevision
                    && state.questionRevision
                    && !questionRevisionEquals(responseRevision, state.questionRevision, examId)
                )
                || (
                    responseQuestionOrderSignature !== ''
                    && String(state.questionOrderSignature || '').trim() !== ''
                    && !questionOrderSignatureEquals(responseQuestionOrderSignature, state.questionOrderSignature)
                )
            )
        ) {
            await refreshAttemptQuestionRevision(responseRevision, {
                attemptId: attemptId,
                examId: examId,
                expectedQuestionOrderSignature: responseQuestionOrderSignature,
                preferredIndex: state.currentIndex,
                source: (
                    responseQuestionOrderSignature !== ''
                    && String(state.questionOrderSignature || '').trim() !== ''
                    && !questionOrderSignatureEquals(responseQuestionOrderSignature, state.questionOrderSignature)
                )
                    ? 'questions-order'
                    : 'questions'
            });
            return questionPayload;
        }

        try {
            applyQuestionsResponse(questionPayload, {
                overwriteExisting: !!options.overwriteExisting,
                preserveActiveWindow: !!options.preserveActiveWindow,
                replaceAnsweredState: !!options.replaceAnsweredState,
                useExistingStableQuestionNumbers: options.useExistingStableQuestionNumbers
            });
        } catch (error) {
            if (error && error.isQuestionOrderConflict) {
                setBlockedQuestionOrderSignature(
                    String(error.questionOrderSignature || responseQuestionOrderSignature || '').trim()
                );
                setQuestionRevisionNotice({
                    kind: 'warning',
                    tone: 'warning',
                    sticky: true,
                    message: 'Perubahan soal terdeteksi tetapi urutan terbaru belum bisa disinkron otomatis. Muat ulang halaman untuk melanjutkan dengan aman.'
                }, {
                    render: true,
                    reason: 'question-window:manual-reload-notice',
                    meta: {
                        code: error && error.code ? String(error.code) : '',
                        detail: error && error.detail ? String(error.detail) : ''
                    }
                });
                return questionPayload;
            }
            throw error;
        }

        return questionPayload;
    }

    async function ensureQuestionWindowForIndex(index, options) {
        options = options || {};

        var safeIndex = clampQuestionIndex(index);
        var targetOffset = questionWindowOffsetForIndex(safeIndex, options.limit || questionWindowSize);
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

        if (isQuestionPayloadLoaded(questionId) && (!shouldRestoreAnsweredState || hasUsableAnsweredState) && setQuestionWindowFromLoadedPayloads(targetOffset, options.limit || questionWindowSize)) {
            return getQuestionPayloadById(questionId);
        }

        await loadQuestionWindow(
            targetOffset,
            {
                examId: options.examId,
                attemptId: options.attemptId,
                includeExisting: options.includeExisting !== undefined ? options.includeExisting : 1,
                limit: options.limit || questionWindowSize
            }
        );

        questionId = getQuestionIdAtIndex(clampQuestionIndex(index));
        return getQuestionPayloadById(questionId);
    }

    async function refreshAttemptQuestionRevision(nextRevision, options) {
        options = options || {};

        var attemptId = Number(options.attemptId !== undefined ? options.attemptId : state.attemptId) || 0;
        var examId = Number(options.examId !== undefined ? options.examId : state.selectedExamId) || 0;
        var normalizedNextRevision = normalizeQuestionRevision(nextRevision, examId);
        var expectedQuestionOrderSignature = String(options.expectedQuestionOrderSignature || '').trim();
        var currentQuestionOrderSignature = String(state.questionOrderSignature || '').trim();
        var hasRevisionTransition = (
            normalizedNextRevision
            && state.questionRevision
            && !questionRevisionEquals(normalizedNextRevision, state.questionRevision, examId)
        );
        var hasQuestionOrderTransition = (
            expectedQuestionOrderSignature !== ''
            && !questionOrderSignatureEquals(expectedQuestionOrderSignature, currentQuestionOrderSignature)
        );

        if (state.stage !== 'exam' || attemptId <= 0 || examId <= 0) {
            return Promise.resolve(null);
        }

        if (!options.force && !hasRevisionTransition && !hasQuestionOrderTransition) {
            return Promise.resolve(null);
        }

        if (
            !options.force
            && expectedQuestionOrderSignature !== ''
            && blockedQuestionOrderSignature !== ''
            && questionOrderSignatureEquals(expectedQuestionOrderSignature, blockedQuestionOrderSignature)
        ) {
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
            current_question_id: 0,
            doubtful_question_ids: []
        };
        attemptUiSnapshot.current_index = Math.max(0, Math.floor(requestedIndex));
        var previousCurrentQuestionId = Number(getQuestionIdAtIndex(attemptUiSnapshot.current_index)) || 0;
        if (previousCurrentQuestionId > 0) {
            attemptUiSnapshot.current_question_id = previousCurrentQuestionId;
        }

        var previousQuestionManifestById = Object.keys(state.questionManifestById || {}).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && state.questionManifestById[key]) {
                accumulator[questionId] = state.questionManifestById[key];
            }
            return accumulator;
        }, {});
        var preservedNavQuestionFilter = normalizeNavigationQuestionFilter(state.navQuestionFilter);
        var preservedAnswers = captureRevisionSafeLocalAnswers();
        var preservedAutoSaveState = buildAutoSaveStateSnapshot();
        var preservedRevisionMarkerLookup = Object.assign({}, state.questionRevisionMarkerLookup || {});
        var preservedAcknowledgedRevisionQuestionIds = Object.assign({}, state.acknowledgedRevisionQuestionIds || {});

        questionRevisionRefreshInFlight = (async function () {
            var refreshGeneration = bumpQuestionDataGeneration();
            clearPersistedQuestionCache(attemptId);
            clearQuestionCachePersistTimer();
            clearQuestionPrefetchRuntimeState();
            clearAttemptUiStateSyncTimer();
            clearPendingRevisionSafeAnswerRestoreState();
            clearAutoSaveRuntimeState();
            state.questionRevisionRefreshing = true;
            state.navigationRefreshing = true;
            state.questionRegionRefreshing = false;

            try {
                var refreshOffset = questionWindowOffsetForIndex(attemptUiSnapshot.current_index, questionWindowSize);
                var questionPayload = await loadQuestionWindow(
                    refreshOffset,
                    {
                        examId: examId,
                        attemptId: attemptId,
                        includeExisting: 1,
                        includeAnswerManifest: 1,
                        limit: questionWindowSize,
                        scenarioTarget: 'current',
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
                    questionPayload.question_revision = deps.serializeQuestionRevision(appliedRevision, examId);
                }

                state.navQuestionFilter = preservedNavQuestionFilter;
                applyQuestionsResponse(questionPayload, {
                    overwriteExisting: true,
                    preserveActiveWindow: false,
                    replaceAnsweredState: true
                });
                state.changedQuestionLookup = buildChangedQuestionLookup(
                    previousQuestionManifestById,
                    state.questionManifestById,
                    null
                );
                var addedQuestionIds = buildAddedQuestionIds(
                    previousQuestionManifestById,
                    state.questionManifestById
                );
                var rawChangedQuestionCount = getChangedQuestionCount();
                var changedOnlyQuestionCount = Math.max(0, rawChangedQuestionCount - addedQuestionIds.length);
                state.questionRevisionMarkerLookup = buildQuestionRevisionMarkerLookup(
                    preservedRevisionMarkerLookup,
                    state.changedQuestionLookup,
                    preservedAcknowledgedRevisionQuestionIds
                );
                state.acknowledgedRevisionQuestionIds = preservedAcknowledgedRevisionQuestionIds;

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
                state.currentIndex = clampQuestionIndex(state.currentIndex);
                var nextCurrentQuestionId = Number(getQuestionIdAtIndex(state.currentIndex)) || 0;
                if (getQuestionCount() > 0 && nextCurrentQuestionId <= 0) {
                    throw buildQuestionOrderConflictError(
                        'Posisi soal aktif tidak bisa dipulihkan setelah revisi.',
                        'question_order_anchor_invalid',
                        String(state.questionOrderSignature || '').trim(),
                        'current-question-anchor-missing'
                    );
                }
                var previousCurrentManifest = previousCurrentQuestionId > 0 ? previousQuestionManifestById[previousCurrentQuestionId] || null : null;
                var nextCurrentManifest = nextCurrentQuestionId > 0 ? state.questionManifestById[nextCurrentQuestionId] || null : null;
                var previousCurrentQuestionNumber = Number(previousCurrentManifest && previousCurrentManifest.question_number !== undefined ? previousCurrentManifest.question_number : 0) || 0;
                var nextCurrentQuestionNumber = Number(nextCurrentManifest && nextCurrentManifest.question_number !== undefined ? nextCurrentManifest.question_number : 0) || 0;
                var currentQuestionAffected = previousCurrentQuestionId <= 0
                    || nextCurrentQuestionId <= 0
                    || nextCurrentQuestionId !== previousCurrentQuestionId
                    || Boolean(state.changedQuestionLookup[previousCurrentQuestionId] || state.changedQuestionLookup[nextCurrentQuestionId])
                    || (
                        previousCurrentQuestionId > 0
                        && previousCurrentQuestionId === nextCurrentQuestionId
                        && previousCurrentQuestionNumber > 0
                        && nextCurrentQuestionNumber > 0
                        && previousCurrentQuestionNumber !== nextCurrentQuestionNumber
                    );
                var currentQuestionDisplaced = previousCurrentQuestionId > 0
                    && nextCurrentQuestionId > 0
                    && nextCurrentQuestionId !== previousCurrentQuestionId;
                if (currentQuestionAffected && nextCurrentQuestionId > 0) {
                    acknowledgeQuestionRevisionMarker(nextCurrentQuestionId, {
                        persist: false
                    });
                }
                var currentQuestionPayload = nextCurrentQuestionId > 0 ? getQuestionPayloadById(nextCurrentQuestionId) : null;
                if (currentQuestionAffected && !currentQuestionPayload) {
                    state.questionRegionRefreshing = true;
                    renderRevisionPatch({
                        notice: true,
                        question: true
                    }, 'question-revision:question-loading', {
                        addedQuestionCount: addedQuestionIds.length,
                        changedQuestionCount: changedOnlyQuestionCount,
                        currentIndex: Number(state.currentIndex) || 0
                    });
                }

                await ensureQuestionWindowForIndex(state.currentIndex, {
                    examId: examId,
                    attemptId: attemptId,
                    includeExisting: 1,
                    limit: questionWindowSize
                });

                pruneQuestionScopedState(validAttemptQuestionIds());
                initializeSubmittedPayloadCache();
                var queuedRestoredAnswerCount = queueLoadedQuestionAnswersForFlush();

                persistCurrentAttemptUiStateLocally();
                persistCurrentQuestionCacheLocally();
                syncAttemptUiStateSignatureToCurrentState();
                scheduleAttemptUiStateSync(attemptUiStateSyncDelayMs);
                if (queuedRestoredAnswerCount > 0) {
                    schedulePendingAnswerRetry('revision-restore', {
                        delayMs: 700
                    });
                }

                state.questionRevisionRefreshing = false;
                state.navigationRefreshing = false;
                state.questionRegionRefreshing = false;
                if (currentQuestionDisplaced) {
                    setQuestionRevisionNotice({
                        kind: 'current-question-warning',
                        tone: 'warning',
                        sticky: true,
                        message: 'Soal aktif berubah karena revisi exam. Anda dipindahkan ke soal yang masih valid.'
                    });
                } else {
                    setQuestionRevisionNotice({
                        kind: 'toast',
                        tone: 'info',
                        sticky: false,
                        message: buildQuestionRevisionNotice(changedOnlyQuestionCount, addedQuestionIds.length)
                    });
                }
                if (
                    typeof state.error === 'string' &&
                    state.error.indexOf('Autosave') !== 0 &&
                    state.error.indexOf('Sinkronisasi jawaban gagal') !== 0
                ) {
                    state.error = '';
                }
                if (addedQuestionIds.length > 0) {
                    recordTimelineEntry('question-revision:added', 'Penambahan soal disinkronkan ke tab ujian.', {
                        addedQuestionCount: addedQuestionIds.length,
                        totalQuestions: getQuestionCount(),
                        attemptId: attemptId,
                        selectedExamId: examId
                    });
                    recordActionTrailEntry('question-revision:added', 'Penambahan soal disinkronkan ke tab ujian.', {
                        addedQuestionCount: addedQuestionIds.length,
                        totalQuestions: getQuestionCount()
                    });
                } else if (changedOnlyQuestionCount > 0) {
                    recordTimelineEntry('question-revision:changed', 'Perubahan soal disinkronkan ke tab ujian.', {
                        changedQuestionCount: changedOnlyQuestionCount,
                        totalQuestions: getQuestionCount(),
                        attemptId: attemptId,
                        selectedExamId: examId
                    });
                }
                renderRevisionPatch({
                    navigation: true,
                    notice: true,
                    question: currentQuestionAffected
                }, 'question-revision:patched', {
                    addedQuestionCount: addedQuestionIds.length,
                    changedQuestionCount: changedOnlyQuestionCount,
                    currentIndex: Number(state.currentIndex) || 0
                });
                deps.resetQuestionPrefetchIdleTimer();
                return questionPayload;
            } catch (error) {
                if (
                    refreshGeneration === questionDataGeneration
                    && state.stage === 'exam'
                    && Number(state.attemptId) === attemptId
                    && Number(state.selectedExamId) === examId
                ) {
                    restoreQuestionAutoSaveState(preservedAutoSaveState);
                    state.questionRevisionRefreshing = false;
                    state.navigationRefreshing = false;
                    state.questionRegionRefreshing = false;
                    var isQuestionOrderConflict = !!(error && error.isQuestionOrderConflict);
                    if (isQuestionOrderConflict) {
                        setBlockedQuestionOrderSignature(
                            String(error.questionOrderSignature || expectedQuestionOrderSignature || '').trim()
                        );
                        setQuestionRevisionNotice({
                            kind: 'warning',
                            tone: 'warning',
                            sticky: true,
                            message: 'Perubahan soal terdeteksi tetapi urutan terbaru belum bisa disinkron otomatis. Muat ulang halaman untuk melanjutkan dengan aman.'
                        });
                        recordTimelineEntry('question-revision:manual-reload', error instanceof Error ? error.message : 'Refresh manual diperlukan untuk sinkronisasi soal.', {
                            attemptId: attemptId,
                            selectedExamId: examId,
                            detail: error && error.detail ? String(error.detail) : '',
                            code: error && error.code ? String(error.code) : ''
                        });
                        recordActionTrailEntry('question-revision:manual-reload', error instanceof Error ? error.message : 'Refresh manual diperlukan untuk sinkronisasi soal.', {
                            detail: error && error.detail ? String(error.detail) : '',
                            code: error && error.code ? String(error.code) : ''
                        });
                    } else {
                        setQuestionRevisionNotice({
                            kind: 'warning',
                            tone: 'warning',
                            sticky: true,
                            message: 'Perubahan soal terdeteksi. Sinkronisasi akan dicoba lagi.'
                        });
                    }
                    renderRevisionPatch({
                        notice: true,
                        navigation: true,
                        question: true
                    }, isQuestionOrderConflict ? 'question-revision:manual-reload-notice' : 'question-revision:retry-notice', {
                        currentIndex: Number(state.currentIndex) || 0
                    });
                    if (!isQuestionOrderConflict && hasPendingQueuedAnswerBatchItems()) {
                        schedulePendingAnswerRetry('revision-refresh-retry', {
                            delayMs: 300
                        });
                    }
                    deps.resetQuestionPrefetchIdleTimer();
                }
                return null;
            } finally {
                questionRevisionRefreshInFlight = null;
            }
        })();

        return questionRevisionRefreshInFlight;
    }

    return {
        applyPersistedQuestionCache: applyPersistedQuestionCache,
        applyQuestionsResponse: applyQuestionsResponse,
        bumpQuestionDataGeneration: bumpQuestionDataGeneration,
        clearQuestionRevisionRefreshState: clearQuestionRevisionRefreshState,
        clearQuestionCachePersistTimer: clearQuestionCachePersistTimer,
        clearStickyQuestionRevisionNotice: clearStickyQuestionRevisionNotice,
        getChangedQuestionCount: getChangedQuestionCount,
        getQuestionRevisionMarkerCount: getQuestionRevisionMarkerCount,
        getQuestionDataGeneration: getQuestionDataGeneration,
        isQuestionRevisionRefreshActive: isQuestionRevisionRefreshActive,
        loadQuestionWindow: loadQuestionWindow,
        mergeAttemptUiStateDoubtfulIds: mergeAttemptUiStateDoubtfulIds,
        pruneQuestionScopedState: pruneQuestionScopedState,
        questionCacheHasPayloadForIndex: questionCacheHasPayloadForIndex,
        refreshAttemptQuestionRevision: refreshAttemptQuestionRevision,
        resetQuestionDataState: resetQuestionDataState,
        acknowledgeQuestionRevisionMarker: acknowledgeQuestionRevisionMarker,
        scheduleQuestionCachePersist: scheduleQuestionCachePersist,
        setQuestionRevision: setQuestionRevision,
        ensureQuestionWindowForIndex: ensureQuestionWindowForIndex
    };
}
