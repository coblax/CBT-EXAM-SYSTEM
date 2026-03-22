export function createAttemptUiSyncManager(deps) {
    var attemptUiStateSyncDelayMs = deps.attemptUiStateSyncDelayMs;
    var apiRequest = deps.apiRequest;
    var buildAttemptUiStateSnapshot = deps.buildAttemptUiStateSnapshot;
    var payloadSignature = deps.payloadSignature;
    var persistAttemptUiStateLocally = deps.persistAttemptUiStateLocally;
    var state = deps.state;
    var windowRef = deps.windowRef;

    var syncTimer = 0;
    var syncInFlight = null;
    var lastSignature = '';

    function clearTimer() {
        if (syncTimer) {
            windowRef.clearTimeout(syncTimer);
        }
        syncTimer = 0;
    }

    function signature(snapshot) {
        return payloadSignature(snapshot);
    }

    function syncSignatureToCurrentState() {
        var snapshot = buildAttemptUiStateSnapshot();
        lastSignature = snapshot ? signature(snapshot) : '';
    }

    function clearRuntimeState() {
        syncInFlight = null;
        lastSignature = '';
        clearTimer();
    }

    function scheduleSync(delayMs) {
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
            return;
        }

        var snapshot = buildAttemptUiStateSnapshot();
        if (!snapshot) {
            return;
        }

        persistAttemptUiStateLocally(snapshot);
        var nextSignature = signature(snapshot);
        if (!syncInFlight && nextSignature === lastSignature) {
            clearTimer();
            return;
        }

        clearTimer();
        syncTimer = windowRef.setTimeout(function () {
            clearTimer();
            flush().catch(function () {});
        }, Math.max(0, Number(delayMs) || 0));
    }

    async function flush(options) {
        options = options || {};

        if (state.stage !== 'exam' || state.attemptId <= 0) {
            return null;
        }
        if (state.isFinishing && !options.allowWhileFinishing) {
            return null;
        }

        clearTimer();

        var snapshot = buildAttemptUiStateSnapshot();
        if (!snapshot) {
            return null;
        }

        persistAttemptUiStateLocally(snapshot);
        var snapshotSignature = signature(snapshot);

        if (syncInFlight) {
            try {
                await syncInFlight;
            } catch (error) {}
        }

        if (!options.force && snapshotSignature === lastSignature) {
            return null;
        }

        syncInFlight = apiRequest('ui_state', {
            method: 'POST',
            keepalive: !!options.keepalive,
            token: options.token,
            body: {
                attempt_state: snapshot
            }
        }).then(function (responsePayload) {
            lastSignature = snapshotSignature;

            if (responsePayload && responsePayload.attempt_state && typeof responsePayload.attempt_state === 'object') {
                persistAttemptUiStateLocally(responsePayload.attempt_state);
            }

            return responsePayload;
        }).finally(function () {
            syncInFlight = null;

            if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing) {
                return;
            }

            var latestSnapshot = buildAttemptUiStateSnapshot();
            if (!latestSnapshot) {
                return;
            }

            if (signature(latestSnapshot) !== lastSignature) {
                scheduleSync(attemptUiStateSyncDelayMs);
            }
        });

        return syncInFlight;
    }

    return {
        clearRuntimeState: clearRuntimeState,
        clearTimer: clearTimer,
        flush: flush,
        scheduleSync: scheduleSync,
        signature: signature,
        syncSignatureToCurrentState: syncSignatureToCurrentState
    };
}
