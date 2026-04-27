import { createAttemptUiStateStorage } from '../storage/attempt-ui-state';
import { createQuestionCacheStorage } from '../storage/question-cache';
import { createAnswerInputManager } from './answer-inputs';
import { createAnswerSyncManager } from './answer-sync';
import { createFinishFlowManager } from './finish-flow';
import { createExamNavigationManager } from './navigation';
import { createQuestionFlags } from './question-flags';
import {
    getShortAnswerKeys,
    getTrueFalseMatrixItems,
    normalizeTrueFalseMatrixAnswer,
    questionOptionKey
} from './question-helpers';
import { createQuestionRuntimeManager } from './question-runtime';
import { createQuestionStateManager } from './question-state';
import { createQuestionWindowManager } from './question-window';

export function createExamRuntimeBundle(deps) {
    var state = deps.state;
    var windowRef = deps.windowRef;
    var questionRuntimeManager = null;
    var finishFlowManager = null;
    var navigationManager = null;
    var questionCacheStorage = null;

    var questionWindowManager = createQuestionWindowManager({
        escapeHtml: deps.escapeHtml,
        getLoadQuestionWindow: function () {
            return questionRuntimeManager ? questionRuntimeManager.loadQuestionWindow : null;
        },
        isQuestionRevisionRefreshActive: function () {
            return questionRuntimeManager ? questionRuntimeManager.isQuestionRevisionRefreshActive() : false;
        },
        questionPrefetchBatchSize: deps.questionPrefetchBatchSize,
        questionPrefetchIdleDelayMs: deps.questionPrefetchIdleDelayMs,
        questionWindowSize: deps.questionWindowSize,
        root: deps.root,
        state: state,
        windowRef: windowRef
    });

    var questionStateManager = createQuestionStateManager({
        getQuestionById: questionWindowManager.getQuestionById,
        state: state
    });

    var answerSyncManager = createAnswerSyncManager({
        answerSyncRetryBaseDelayMs: deps.answerSyncRetryBaseDelayMs,
        answerSyncRetryMaxDelayMs: deps.answerSyncRetryMaxDelayMs,
        apiRequest: deps.apiRequest,
        autoSaveBatchMaxItems: deps.autoSaveBatchMaxItems,
        autoSaveChoiceDelayCongestedMs: deps.autoSaveChoiceDelayCongestedMs,
        autoSaveChoiceDelayMs: deps.autoSaveChoiceDelayMs,
        autoSaveCongestedWindowMs: deps.autoSaveCongestedWindowMs,
        autoSaveTextDelayCongestedMs: deps.autoSaveTextDelayCongestedMs,
        autoSaveTextDelayMs: deps.autoSaveTextDelayMs,
        diagnosticsManager: deps.diagnosticsManager,
        getNavigatorConnectionStatus: deps.getNavigatorConnectionStatus,
        getQuestionById: questionWindowManager.getQuestionById,
        getQuestionDataGeneration: function () {
            return questionRuntimeManager ? questionRuntimeManager.getQuestionDataGeneration() : 0;
        },
        getQuestionPayloadById: questionWindowManager.getQuestionPayloadById,
        isQuestionRevisionRefreshActive: function () {
            return questionRuntimeManager ? questionRuntimeManager.isQuestionRevisionRefreshActive() : false;
        },
        maybeFinalizeLockedExam: function (reason) {
            if (finishFlowManager) {
                finishFlowManager.maybeFinalizeLockedExam(reason);
            }
        },
        normalizeExistingAnswerForQuestion: questionStateManager.normalizeExistingAnswerForQuestion,
        normalizeStoredAutoSaveState: function (snapshot) {
            return questionCacheStorage ? questionCacheStorage.normalizeStoredAutoSaveState(snapshot) : null;
        },
        payloadSignature: questionStateManager.payloadSignature,
        questionAnswerPayload: questionStateManager.questionAnswerPayload,
        recordActionTrail: deps.recordActionTrail,
        recordTimeline: deps.recordTimeline,
        render: deps.render,
        renderExamPartial: deps.renderExamPartial,
        scheduleQuestionCachePersist: function () {
            if (questionRuntimeManager) {
                return questionRuntimeManager.scheduleQuestionCachePersist.apply(questionRuntimeManager, arguments);
            }
            return undefined;
        },
        state: state,
        windowRef: windowRef
    });

    questionCacheStorage = createQuestionCacheStorage({
        getAutoSaveState: answerSyncManager.getAutoSaveState,
        getIndexedDb: deps.getIndexedDb,
        getLocalStorage: deps.getLocalStorage,
        getQuestionPayloadById: questionWindowManager.getQuestionPayloadById,
        getSessionStorage: deps.getSessionStorage,
        indexedDbName: deps.questionCacheIndexedDbName,
        indexedDbStore: deps.questionCacheIndexedDbStore,
        itemLocalStorageKeyPrefix: deps.questionCacheItemLocalStorageKeyPrefix,
        metaLocalStorageKeyPrefix: deps.questionCacheMetaLocalStorageKeyPrefix,
        normalizeExistingAnswerForQuestion: questionStateManager.normalizeExistingAnswerForQuestion,
        now: Date.now,
        payloadSignature: questionStateManager.payloadSignature,
        sessionStorageKeyPrefix: deps.questionCacheSessionStorageKeyPrefix,
        state: state,
        windowRef: windowRef
    });

    var attemptUiStateStorage = createAttemptUiStateStorage({
        buildDoubtfulSessionStorageKey: deps.buildDoubtfulSessionStorageKey,
        clearPersistedDoubtfulState: deps.clearPersistedDoubtfulState,
        getQuestionCount: questionWindowManager.getQuestionCount,
        getQuestionIdAtIndex: questionWindowManager.getQuestionIdAtIndex,
        getSessionStorage: deps.getSessionStorage,
        normalizeOrUseQuestionCacheSnapshot: questionCacheStorage.normalizeOrUseQuestionCacheSnapshot,
        normalizeQuestionCacheSnapshot: questionCacheStorage.normalizeQuestionCacheSnapshot,
        readPersistedDoubtfulState: deps.readPersistedDoubtfulState,
        state: state,
        storageKeyPrefix: deps.attemptUiSessionStorageKeyPrefix,
        validAttemptQuestionIds: questionWindowManager.validAttemptQuestionIds
    });

    questionRuntimeManager = createQuestionRuntimeManager({
        apiRequest: deps.apiRequest,
        applyAttemptUiState: attemptUiStateStorage.applyAttemptUiState,
        applyPendingRevisionSafeAnswersForLoadedQuestions: questionStateManager.applyPendingRevisionSafeAnswersForLoadedQuestions,
        attemptUiStateSyncDelayMs: deps.attemptUiStateSyncDelayMs,
        buildAttemptUiStateSnapshot: attemptUiStateStorage.buildAttemptUiStateSnapshot,
        buildAutoSaveStateSnapshot: questionCacheStorage.buildAutoSaveStateSnapshot,
        buildChangedQuestionLookup: questionCacheStorage.buildChangedQuestionLookup,
        buildQuestionManifestById: questionCacheStorage.buildQuestionManifestById,
        buildQuestionManifestFromQuestions: questionCacheStorage.buildQuestionManifestFromQuestions,
        buildQuestionOrderSignature: questionCacheStorage.buildQuestionOrderSignature,
        captureRevisionSafeLocalAnswers: questionStateManager.captureRevisionSafeLocalAnswers,
        clearAttemptUiStateSyncTimer: deps.clearAttemptUiStateSyncTimer,
        clearAutoSaveRuntimeState: answerSyncManager.clearAutoSaveRuntimeState,
        clearPendingRevisionSafeAnswerRestoreState: questionStateManager.clearPendingRevisionSafeAnswerRestoreState,
        clearPersistedAttemptUiState: attemptUiStateStorage.clearPersistedAttemptUiState,
        clearPersistedQuestionCache: questionCacheStorage.clearPersistedQuestionCache,
        clearQuestionPrefetchRuntimeState: questionWindowManager.clearQuestionPrefetchRuntimeState,
        clampQuestionIndex: questionWindowManager.clampQuestionIndex,
        diagnosticsManager: deps.diagnosticsManager,
        getQuestionCount: questionWindowManager.getQuestionCount,
        getQuestionIdAtIndex: questionWindowManager.getQuestionIdAtIndex,
        getQuestionManifestById: questionWindowManager.getQuestionManifestById,
        getQuestionPayloadById: questionWindowManager.getQuestionPayloadById,
        hasPendingQueuedAnswerBatchItems: answerSyncManager.hasPendingBatchItems,
        hasUsableLocalAnswerForQuestion: questionStateManager.hasUsableLocalAnswerForQuestion,
        initializeSubmittedPayloadCache: answerSyncManager.initializeSubmittedPayloadCache,
        isIndexInCurrentWindow: questionWindowManager.isIndexInCurrentWindow,
        isQuestionPayloadLoaded: questionWindowManager.isQuestionPayloadLoaded,
        markQuestionWindowLoaded: questionWindowManager.markQuestionWindowLoaded,
        mergeExistingAnswersFromQuestionItems: questionStateManager.mergeExistingAnswersFromQuestionItems,
        mergeExistingAnswersMap: questionStateManager.mergeExistingAnswersMap,
        navQuestionFilterAll: deps.navQuestionFilterAll,
        normalizeNavigationQuestionFilter: function (value) {
            return navigationManager ? navigationManager.normalizeNavigationQuestionFilter(value) : deps.navQuestionFilterAll;
        },
        normalizeOrUseQuestionCacheSnapshot: questionCacheStorage.normalizeOrUseQuestionCacheSnapshot,
        normalizeQuestionCacheSnapshot: questionCacheStorage.normalizeQuestionCacheSnapshot,
        normalizeQuestionIdList: questionCacheStorage.normalizeQuestionIdList,
        normalizeQuestionRevision: questionCacheStorage.normalizeQuestionRevision,
        persistCurrentAttemptUiStateLocally: attemptUiStateStorage.persistCurrentAttemptUiStateLocally,
        persistCurrentQuestionCacheLocally: questionCacheStorage.persistCurrentQuestionCacheLocally,
        primeSubmittedPayloadCacheFromQuestionItems: answerSyncManager.primeSubmittedPayloadCacheFromQuestionItems,
        pruneAnswerSyncState: answerSyncManager.pruneQuestionAnswerState,
        prunePendingRevisionSafeAnswerRestoreState: questionStateManager.prunePendingRevisionSafeAnswerRestoreState,
        questionOrderSignatureEquals: questionCacheStorage.questionOrderSignatureEquals,
        questionRevisionEquals: questionCacheStorage.questionRevisionEquals,
        questionWindowOffsetForIndex: questionWindowManager.questionWindowOffsetForIndex,
        questionWindowSize: deps.questionWindowSize,
        queueLoadedQuestionAnswersForFlush: answerSyncManager.queueLoadedQuestionAnswersForFlush,
        queueQuestionAnswersByIds: answerSyncManager.queueQuestionAnswersByIds,
        recordActionTrail: deps.recordActionTrail,
        recordTimeline: deps.recordTimeline,
        render: deps.render,
        renderExamPartial: deps.renderExamPartial,
        resetQuestionPrefetchIdleTimer: questionWindowManager.resetQuestionPrefetchIdleTimer,
        restoreLocalAnswerFromQuestion: questionStateManager.restoreLocalAnswerFromQuestion,
        restoreQuestionAutoSaveState: answerSyncManager.restoreQuestionAutoSaveState,
        restoreRevisionSafeLocalAnswers: questionStateManager.restoreRevisionSafeLocalAnswers,
        scheduleAttemptUiStateSync: deps.scheduleAttemptUiStateSync,
        schedulePendingAnswerRetry: answerSyncManager.schedulePendingAnswerRetry,
        serializeQuestionRevision: questionCacheStorage.serializeQuestionRevision,
        setQuestionWindowFromLoadedPayloads: questionWindowManager.setQuestionWindowFromLoadedPayloads,
        state: state,
        syncAttemptUiStateSignatureToCurrentState: deps.syncAttemptUiStateSignatureToCurrentState,
        updateQuestionPrefetchIndicator: questionWindowManager.updateQuestionPrefetchIndicator,
        validAttemptQuestionIds: questionWindowManager.validAttemptQuestionIds,
        windowRef: windowRef
    });

    var answerInputManager = createAnswerInputManager({
        autoSaveChoiceDelayMs: deps.autoSaveChoiceDelayMs,
        autoSaveTextDelayMs: deps.autoSaveTextDelayMs,
        clearMessages: deps.clearMessages,
        documentRef: deps.documentRef,
        normalizeExamToken: deps.normalizeExamToken,
        render: deps.render,
        renderExamPartial: deps.renderExamPartial,
        root: deps.root,
        scheduleAutoSave: answerSyncManager.scheduleAutoSave,
        scheduleQuestionCachePersist: function () {
            return questionRuntimeManager.scheduleQuestionCachePersist.apply(questionRuntimeManager, arguments);
        },
        state: state,
        updateSelectedExam: deps.updateSelectedExam,
        windowRef: deps.windowRef
    });

    navigationManager = createExamNavigationManager({
        attemptUiStateNavigationSyncDelayMs: deps.attemptUiStateNavigationSyncDelayMs,
        attemptUiStateSyncDelayMs: deps.attemptUiStateSyncDelayMs,
        acknowledgeQuestionRevisionMarker: questionRuntimeManager.acknowledgeQuestionRevisionMarker,
        clampQuestionIndex: questionWindowManager.clampQuestionIndex,
        clearStickyQuestionRevisionNotice: questionRuntimeManager.clearStickyQuestionRevisionNotice,
        clearMessages: deps.clearMessages,
        documentRef: deps.documentRef,
        ensureQuestionWindowForIndex: questionRuntimeManager.ensureQuestionWindowForIndex,
        escapeHtml: deps.escapeHtml,
        getNavigatorConnectionStatus: deps.getNavigatorConnectionStatus,
        getQuestionAtIndex: questionWindowManager.getQuestionAtIndex,
        getQuestionById: questionWindowManager.getQuestionById,
        getQuestionCount: questionWindowManager.getQuestionCount,
        getQuestionDisplayNumber: questionWindowManager.getQuestionDisplayNumber,
        getQuestionIdAtIndex: questionWindowManager.getQuestionIdAtIndex,
        getShortAnswerKeys: getShortAnswerKeys,
        getTrueFalseMatrixItems: getTrueFalseMatrixItems,
        hasUsableLocalAnswerForQuestion: questionStateManager.hasUsableLocalAnswerForQuestion,
        isExamAnswerEditingLocked: deps.isExamAnswerEditingLocked,
        isNetworkConnectivityError: answerSyncManager.isNetworkConnectivityError,
        isQuestionPayloadLoaded: questionWindowManager.isQuestionPayloadLoaded,
        navQuestionFilterAll: deps.navQuestionFilterAll,
        navQuestionFilterAnswered: deps.navQuestionFilterAnswered,
        navQuestionFilterDoubtful: deps.navQuestionFilterDoubtful,
        navQuestionFilterUnanswered: deps.navQuestionFilterUnanswered,
        navigationQuestionTypeBadgeConfig: deps.navigationQuestionTypeBadgeConfig,
        normalizeTrueFalseMatrixAnswer: normalizeTrueFalseMatrixAnswer,
        persistCurrentAttemptUiStateLocally: attemptUiStateStorage.persistCurrentAttemptUiStateLocally,
        prefetchNextQuestionBatch: questionWindowManager.prefetchNextQuestionBatch,
        questionOptionKey: questionOptionKey,
        questionWindowSize: deps.questionWindowSize,
        queueQuestionAnswer: answerSyncManager.queueQuestionAnswer,
        render: deps.render,
        renderExamPartial: deps.renderExamPartial,
        resetQuestionPrefetchIdleTimer: questionWindowManager.resetQuestionPrefetchIdleTimer,
        resolveStoredAnswerValueForQuestion: questionStateManager.resolveStoredAnswerValueForQuestion,
        scheduleAttemptUiStateSync: deps.scheduleAttemptUiStateSync,
        schedulePendingAnswerRetry: answerSyncManager.schedulePendingAnswerRetry,
        scheduleQuestionCachePersist: function () {
            return questionRuntimeManager.scheduleQuestionCachePersist.apply(questionRuntimeManager, arguments);
        },
        setActiveQuestionWindowForIndex: questionWindowManager.setActiveQuestionWindowForIndex,
        state: state
    });

    finishFlowManager = createFinishFlowManager({
        apiRequest: deps.apiRequest,
        clearAllAutoSaveTimers: answerSyncManager.clearAllAutoSaveTimers,
        clearAttemptUiStateSyncTimer: deps.clearAttemptUiStateSyncTimer,
        clearAutoSaveRuntimeState: answerSyncManager.clearAutoSaveRuntimeState,
        clearMessages: deps.clearMessages,
        clearPersistedAttemptUiState: attemptUiStateStorage.clearPersistedAttemptUiState,
        clearPersistedQuestionCache: questionCacheStorage.clearPersistedQuestionCache,
        clearQuestionCachePersistTimer: questionRuntimeManager.clearQuestionCachePersistTimer,
        clearQuestionPrefetchRuntimeState: questionWindowManager.clearQuestionPrefetchRuntimeState,
        diagnosticsManager: deps.diagnosticsManager,
        exitFullscreenSilently: deps.exitFullscreenSilently,
        flushAttemptUiState: deps.flushAttemptUiState,
        flushPendingAnswerBatch: answerSyncManager.flushPendingAnswerBatch,
        getExamProgressSummary: function () {
            return navigationManager.getExamProgressSummary();
        },
        getNavigatorConnectionStatus: deps.getNavigatorConnectionStatus,
        getQuestionAtIndex: questionWindowManager.getQuestionAtIndex,
        getQuestionCount: questionWindowManager.getQuestionCount,
        handleRecoverableAnswerSyncFailure: answerSyncManager.handleRecoverableAnswerSyncFailure,
        hasAnswerBatchFlushInFlight: answerSyncManager.hasFlushInFlight,
        isNetworkConnectivityError: answerSyncManager.isNetworkConnectivityError,
        isQuestionAnswered: function (question) {
            return navigationManager.isQuestionAnswered(question);
        },
        isRetryableAnswerSyncError: answerSyncManager.isRetryableAnswerSyncError,
        persistCurrentQuestionCacheLocally: questionCacheStorage.persistCurrentQuestionCacheLocally,
        ensureResultStageRenderer: deps.ensureResultStageRenderer,
        prefetchResultStageRenderer: deps.prefetchResultStageRenderer,
        queueQuestionAnswer: answerSyncManager.queueQuestionAnswer,
        recordActionTrail: deps.recordActionTrail,
        recordTimeline: deps.recordTimeline,
        render: deps.render,
        schedulePendingAnswerRetry: answerSyncManager.schedulePendingAnswerRetry,
        setConnectionStatus: answerSyncManager.setConnectionStatus,
        startTimer: deps.startTimer,
        state: state,
        stopTimer: deps.stopTimer,
        syncFullscreenState: deps.syncFullscreenState,
        syncPendingAnswerRuntimeState: answerSyncManager.syncPendingAnswerRuntimeState,
        windowRef: windowRef
    });

    var questionFlags = createQuestionFlags({
        state: state
    });

    return {
        answerInputManager: answerInputManager,
        answerSyncManager: answerSyncManager,
        attemptUiStateStorage: attemptUiStateStorage,
        finishFlowManager: finishFlowManager,
        navigationManager: navigationManager,
        questionCacheStorage: questionCacheStorage,
        questionFlags: questionFlags,
        questionRuntimeManager: questionRuntimeManager,
        questionStateManager: questionStateManager,
        questionWindowManager: questionWindowManager
    };
}
