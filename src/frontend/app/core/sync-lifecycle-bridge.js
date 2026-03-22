export function createSyncLifecycleBridge(deps) {
    var flushAttemptUiState = deps.flushAttemptUiState;
    var flushPendingAnswerBatch = deps.flushPendingAnswerBatch;
    var getNavigatorConnectionStatus = deps.getNavigatorConnectionStatus;
    var maybeFinalizeLockedExam = deps.maybeFinalizeLockedExam;
    var queueLoadedQuestionAnswersForFlush = deps.queueLoadedQuestionAnswersForFlush;
    var schedulePendingAnswerRetry = deps.schedulePendingAnswerRetry;
    var setConnectionStatus = deps.setConnectionStatus;
    var state = deps.state;

    function flushPendingAnswerBatchSilently(options) {
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
            return;
        }

        queueLoadedQuestionAnswersForFlush();
        flushPendingAnswerBatch(options || {}).catch(function () {});
    }

    function flushAttemptUiStateSilently(options) {
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
            return;
        }

        flushAttemptUiState(options || {}).catch(function () {});
    }

    function triggerPendingSyncLifecycleRetry(reason, options) {
        options = options || {};

        setConnectionStatus(getNavigatorConnectionStatus(), {
            persist: false,
            render: false,
            triggerRetry: false
        });
        schedulePendingAnswerRetry(reason || 'lifecycle', {
            immediate: true,
            resetBackoff: true,
            delayMs: options.delayMs,
            persist: false
        });
        maybeFinalizeLockedExam(reason || 'lifecycle');
    }

    return {
        flushAttemptUiStateSilently: flushAttemptUiStateSilently,
        flushPendingAnswerBatchSilently: flushPendingAnswerBatchSilently,
        triggerPendingSyncLifecycleRetry: triggerPendingSyncLifecycleRetry
    };
}
