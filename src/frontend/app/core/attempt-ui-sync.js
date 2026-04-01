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

    function signaturePayload(snapshot) {
        if (!snapshot || typeof snapshot !== 'object') {
            return snapshot;
        }

        if (Array.isArray(snapshot)) {
            return snapshot.slice();
        }

        var normalized = Object.assign({}, snapshot);
        delete normalized.updated_at;
        delete normalized.updatedAt;
        return normalized;
    }

    function signature(snapshot) {
        return payloadSignature(signaturePayload(snapshot));
    }

    function numericUpdatedAt(snapshot) {
        if (!snapshot || typeof snapshot !== 'object') {
            return 0;
        }

        return Math.max(0, Number(snapshot.updated_at) || 0);
    }

    function shouldPersistResponseAttemptState(sentSnapshot, latestLocalSnapshot, remoteSnapshot) {
        if (!remoteSnapshot || typeof remoteSnapshot !== 'object') {
            return false;
        }

        if (!latestLocalSnapshot || typeof latestLocalSnapshot !== 'object') {
            return true;
        }

        var remoteUpdatedAt = numericUpdatedAt(remoteSnapshot);
        var localUpdatedAt = numericUpdatedAt(latestLocalSnapshot);
        if (remoteUpdatedAt > 0 && localUpdatedAt > 0) {
            return remoteUpdatedAt >= localUpdatedAt;
        }

        var latestLocalSignature = signature(latestLocalSnapshot);
        if (latestLocalSignature === '') {
            return true;
        }

        var sentSignature = signature(sentSnapshot);
        var remoteSignature = signature(remoteSnapshot);
        return latestLocalSignature === sentSignature || latestLocalSignature === remoteSignature;
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

    function hasAuthToken(options) {
        var token = options && options.token !== undefined
            ? String(options.token || '')
            : String(state.token || '');

        return token.trim() !== '';
    }

    function isTerminalAuthError(error) {
        if (!error || typeof error !== 'object') {
            return false;
        }

        var status = Number(error.status) || 0;
        var code = String(error.code || '').trim().toLowerCase();
        return status === 401 || status === 403 || code === 'unauthorized' || code === 'forbidden';
    }

    function scheduleSync(delayMs) {
        if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing || !hasAuthToken()) {
            clearTimer();
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

        if (state.stage !== 'exam' || state.attemptId <= 0 || !hasAuthToken(options)) {
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

        var terminalAuthFailure = false;
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
                var latestLocalSnapshot = buildAttemptUiStateSnapshot();
                if (shouldPersistResponseAttemptState(snapshot, latestLocalSnapshot, responsePayload.attempt_state)) {
                    persistAttemptUiStateLocally(responsePayload.attempt_state);
                }
            }

            return responsePayload;
        }).catch(function (error) {
            if (isTerminalAuthError(error)) {
                terminalAuthFailure = true;
            }

            throw error;
        }).finally(function () {
            syncInFlight = null;

            if (terminalAuthFailure) {
                return;
            }

            if (state.stage !== 'exam' || state.attemptId <= 0 || state.isFinishing || !hasAuthToken()) {
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
