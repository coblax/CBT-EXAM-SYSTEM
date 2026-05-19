export function createSyncLifecycleBridge(deps) {
    var flushAttemptUiState = deps.flushAttemptUiState;
    var flushPendingAnswerBatch = deps.flushPendingAnswerBatch;
    var getNavigatorConnectionStatus = deps.getNavigatorConnectionStatus;
    var maybeFinalizeLockedExam = deps.maybeFinalizeLockedExam;
    var queueLoadedQuestionAnswersForFlush = deps.queueLoadedQuestionAnswersForFlush;
    var schedulePendingAnswerRetry = deps.schedulePendingAnswerRetry;
    var setConnectionStatus = deps.setConnectionStatus;
    var state = deps.state;

    function resolveSilentOperation(promise, options) {
        var resolvedPromise = Promise.resolve(promise);
        if (options && options.swallowErrors === false) {
            return resolvedPromise;
        }

        return resolvedPromise.catch(function () {
            return null;
        });
    }

    function flushPendingAnswerBatchSilently(options) {
        options = options || {};
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
            return Promise.resolve(null);
        }

        try {
            queueLoadedQuestionAnswersForFlush();
            return resolveSilentOperation(flushPendingAnswerBatch(options), options);
        } catch (error) {
            return resolveSilentOperation(Promise.reject(error), options);
        }
    }

    function flushAttemptUiStateSilently(options) {
        options = options || {};
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
            return Promise.resolve(null);
        }

        try {
            return resolveSilentOperation(flushAttemptUiState(options), options);
        } catch (error) {
            return resolveSilentOperation(Promise.reject(error), options);
        }
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
