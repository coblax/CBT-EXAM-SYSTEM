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

    function buildStableQuestionNumberMapFromSources(manifestById, payloadById, preferredMap) {
        var stableMap = {};

        [manifestById, payloadById, preferredMap].forEach(function (source) {
            if (!source || typeof source !== 'object') {
                return;
            }

            Object.keys(source).forEach(function (key) {
                var questionId = Number(key) || 0;
                if (questionId <= 0) {
                    return;
                }

                var item = source[key];
                var questionNumber = Number(item && item.question_number !== undefined ? item.question_number : item) || 0;
                if (questionNumber > 0) {
                    stableMap[questionId] = questionNumber;
                }
            });
        });

        return stableMap;
    }

    function buildResponseQuestionNumberMap(manifestItems, payloadItems) {
        var responseMap = {};

        [manifestItems, payloadItems].forEach(function (items) {
            if (!Array.isArray(items)) {
                return;
            }

            items.forEach(function (item) {
                if (!item || typeof item !== 'object') {
                    return;
                }

                var questionId = Number(item.id) || 0;
                var questionNumber = Number(item.question_number) || 0;
                if (questionId <= 0 || questionNumber <= 0) {
                    return;
                }

                if (!Object.prototype.hasOwnProperty.call(responseMap, questionId)) {
                    responseMap[questionId] = questionNumber;
                }
            });
        });

        return responseMap;
    }

    function buildResolvedQuestionNumberMap(orderIds, manifestItems, payloadItems, stableQuestionNumberMap) {
        var resolvedMap = {};
        var usedNumberLookup = {};
        var safeStableQuestionNumberMap = stableQuestionNumberMap && typeof stableQuestionNumberMap === 'object'
            ? stableQuestionNumberMap
            : {};
        var responseQuestionNumberMap = buildResponseQuestionNumberMap(manifestItems, payloadItems);
        var candidateQuestionIds = normalizeQuestionIdList([].concat(
            Array.isArray(orderIds) ? orderIds : [],
            Object.keys(responseQuestionNumberMap || {}).map(function (key) {
                return Number(key) || 0;
            })
        ));
        var nextQuestionNumber = 0;

        candidateQuestionIds.forEach(function (questionId) {
            var responseQuestionNumber = Number(responseQuestionNumberMap[questionId]) || 0;
            if (responseQuestionNumber <= 0 || usedNumberLookup[responseQuestionNumber]) {
                return;
            }

            resolvedMap[questionId] = responseQuestionNumber;
            usedNumberLookup[responseQuestionNumber] = true;
            if (responseQuestionNumber > nextQuestionNumber) {
                nextQuestionNumber = responseQuestionNumber;
            }
        });

        candidateQuestionIds.forEach(function (questionId) {
            if (resolvedMap[questionId]) {
                return;
            }

            var stableQuestionNumber = Number(safeStableQuestionNumberMap[questionId]) || 0;
            if (stableQuestionNumber > 0 && !usedNumberLookup[stableQuestionNumber]) {
                resolvedMap[questionId] = stableQuestionNumber;
                usedNumberLookup[stableQuestionNumber] = true;
                if (stableQuestionNumber > nextQuestionNumber) {
                    nextQuestionNumber = stableQuestionNumber;
                }
                return;
            }
        });

        function allocateNextQuestionNumber() {
            do {
                nextQuestionNumber += 1;
            } while (usedNumberLookup[nextQuestionNumber]);

            usedNumberLookup[nextQuestionNumber] = true;
            return nextQuestionNumber;
        }

        candidateQuestionIds.forEach(function (questionId) {
            if (resolvedMap[questionId]) {
                return;
            }

            resolvedMap[questionId] = allocateNextQuestionNumber();
        });

        return resolvedMap;
    }

    function applyStableQuestionNumbersToItems(items, stableQuestionNumberMap) {
        if (!Array.isArray(items) || !items.length || !stableQuestionNumberMap || typeof stableQuestionNumberMap !== 'object') {
            return items;
        }

        return items.map(function (item) {
            if (!item || typeof item !== 'object') {
                return item;
            }

            var questionId = Number(item.id) || 0;
            var stableQuestionNumber = questionId > 0
                ? (Number(stableQuestionNumberMap[questionId]) || 0)
                : 0;

            if (stableQuestionNumber <= 0) {
                return item;
            }

            return Object.assign({}, item, {
                question_number: stableQuestionNumber
            });
        });
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

    function applyQuestionsResponse(questionPayload, options) {
        options = options || {};
        var useExistingStableQuestionNumbers = options.useExistingStableQuestionNumbers !== false;
        var stableQuestionNumberMap = buildStableQuestionNumberMapFromSources(
            useExistingStableQuestionNumbers ? state.questionManifestById : null,
            useExistingStableQuestionNumbers ? state.questionPayloadById : null,
            options.stableQuestionNumberMap
        );
        var responseItems = questionPayload && Array.isArray(questionPayload.items) ? questionPayload.items : [];
        var responseOrderIds = questionPayload && Array.isArray(questionPayload.question_order_ids)
            ? questionPayload.question_order_ids
            : responseItems.map(function (question) { return Number(question && question.id) || 0; });
        var normalizedOrderIds = normalizeQuestionIdList(responseOrderIds);
        var responseManifest = questionPayload && Array.isArray(questionPayload.question_manifest)
            ? questionPayload.question_manifest
            : buildQuestionManifestFromQuestions(responseItems);
        var resolvedQuestionNumberMap = buildResolvedQuestionNumberMap(
            normalizedOrderIds,
            responseManifest,
            responseItems,
            stableQuestionNumberMap
        );
        responseItems = applyStableQuestionNumbersToItems(responseItems, resolvedQuestionNumberMap);
        responseManifest = applyStableQuestionNumbersToItems(responseManifest, resolvedQuestionNumberMap);
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

        if (options.replaceAnsweredState) {
            state.answeredQuestionLookup = {};
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
                schedulePendingAnswerRetry('restore-deferred-answers', {
                    delayMs: 300
                });
            }
        }
        scheduleQuestionCachePersist(0);
        updateQuestionPrefetchIndicator();
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

        var questionPayload = await apiRequest('questions', {
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
            preserveActiveWindow: !!options.preserveActiveWindow,
            replaceAnsweredState: !!options.replaceAnsweredState,
            useExistingStableQuestionNumbers: options.useExistingStableQuestionNumbers
        });

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

        if (state.stage !== 'exam' || attemptId <= 0 || examId <= 0) {
            return Promise.resolve(null);
        }

        if (!options.force && normalizedNextRevision && state.questionRevision && questionRevisionEquals(normalizedNextRevision, state.questionRevision, examId)) {
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
        var previousCurrentQuestionId = Number(getQuestionIdAtIndex(attemptUiSnapshot.current_index)) || 0;

        var previousQuestionManifestById = Object.keys(state.questionManifestById || {}).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && state.questionManifestById[key]) {
                accumulator[questionId] = state.questionManifestById[key];
            }
            return accumulator;
        }, {});
        var previousStableQuestionNumberMap = buildStableQuestionNumberMapFromSources(
            previousQuestionManifestById,
            state.questionPayloadById,
            null
        );
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

                resetQuestionDataState();
                clearPendingRevisionSafeAnswerRestoreState();
                setQuestionRevision(appliedRevision, examId);
                state.navQuestionFilter = preservedNavQuestionFilter;
                applyQuestionsResponse(questionPayload, {
                    overwriteExisting: true,
                    preserveActiveWindow: false,
                    replaceAnsweredState: true,
                    stableQuestionNumberMap: previousStableQuestionNumberMap
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
                state.currentIndex = clampQuestionIndex(attemptUiSnapshot.current_index);
                var nextCurrentQuestionId = Number(getQuestionIdAtIndex(state.currentIndex)) || 0;
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
                    setQuestionRevisionNotice({
                        kind: 'warning',
                        tone: 'warning',
                        sticky: true,
                        message: 'Perubahan soal terdeteksi. Sinkronisasi akan dicoba lagi.'
                    });
                    renderRevisionPatch({
                        notice: true,
                        question: true
                    }, 'question-revision:retry-notice', {
                        currentIndex: Number(state.currentIndex) || 0
                    });
                    if (hasPendingQueuedAnswerBatchItems()) {
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
