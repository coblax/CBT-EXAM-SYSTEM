export function createNativeBridgeManager(deps) {
    var buildUrl = deps.buildUrl;
    var isSecurityLoggingEnabled = deps.isSecurityLoggingEnabled;
    var readPersistedAuthSession = deps.readPersistedAuthSession;
    var state = deps.state;
    var windowRef = deps.windowRef;
    var SNAPSHOT_CHANGED_EVENT = 'cbt-native-security-snapshot-changed';
    var lastSnapshotSignature = null;
    var snapshotSubscribers = [];

    function cloneSnapshot(snapshot) {
        return {
            ok: Number(snapshot && snapshot.ok ? snapshot.ok : 0) || 0,
            token: String(snapshot && snapshot.token ? snapshot.token : ''),
            attemptId: Number(snapshot && snapshot.attemptId ? snapshot.attemptId : 0) || 0,
            stage: String(snapshot && snapshot.stage ? snapshot.stage : ''),
            studentId: Number(snapshot && snapshot.studentId ? snapshot.studentId : 0) || 0,
            selectedExamId: Number(snapshot && snapshot.selectedExamId ? snapshot.selectedExamId : 0) || 0,
            securityLoggingEnabled: !!(snapshot && snapshot.securityLoggingEnabled),
            endpoints: snapshot && snapshot.endpoints && typeof snapshot.endpoints === 'object'
                ? {
                    nativeSecurityEvent: String(snapshot.endpoints.nativeSecurityEvent || '')
                }
                : {}
        };
    }

    function buildSnapshotSignature(snapshot) {
        return JSON.stringify(cloneSnapshot(snapshot));
    }

    function normalizeSnapshotToken(rawToken) {
        if (typeof rawToken !== 'string') {
            return '';
        }

        var token = rawToken.trim();
        if (token === '' || token.length > 4096) {
            return '';
        }

        return token;
    }

    function buildSnapshot() {
        var persisted = typeof readPersistedAuthSession === 'function'
            ? readPersistedAuthSession()
            : null;
        var activeUser = state && state.user ? state.user : (persisted && persisted.user ? persisted.user : null);
        var token = normalizeSnapshotToken(state && state.token ? state.token : (persisted && persisted.token ? persisted.token : ''));
        var attemptId = Number(state && state.attemptId ? state.attemptId : 0) || 0;
        var stage = String(state && state.stage ? state.stage : '');
        var selectedExamId = Number(state && state.selectedExamId ? state.selectedExamId : (persisted && persisted.selectedExamId ? persisted.selectedExamId : 0)) || 0;
        var studentId = Number(activeUser && activeUser.user_id ? activeUser.user_id : 0) || 0;
        var ok = token !== '' && attemptId > 0 && stage === 'exam';

        return {
            ok: ok ? 1 : 0,
            token: ok ? token : '',
            attemptId: ok ? attemptId : 0,
            stage: stage,
            studentId: studentId,
            selectedExamId: selectedExamId,
            securityLoggingEnabled: !!isSecurityLoggingEnabled(),
            endpoints: ok
                ? {
                    nativeSecurityEvent: buildUrl('native_security_event')
                }
                : {}
        };
    }

    function dispatchSnapshotChangedEvent(snapshot, reason) {
        var eventPayload = {
            detail: {
                reason: String(reason || 'sync'),
                snapshot: cloneSnapshot(snapshot)
            },
            type: SNAPSHOT_CHANGED_EVENT
        };

        if (!windowRef || typeof windowRef.dispatchEvent !== 'function') {
            return;
        }

        try {
            if (typeof windowRef.CustomEvent === 'function') {
                windowRef.dispatchEvent(new windowRef.CustomEvent(SNAPSHOT_CHANGED_EVENT, eventPayload));
                return;
            }

            if (typeof CustomEvent === 'function') {
                windowRef.dispatchEvent(new CustomEvent(SNAPSHOT_CHANGED_EVENT, eventPayload));
                return;
            }
        } catch (error) {
            // Fall back to the lightweight event payload below.
        }

        try {
            windowRef.dispatchEvent(eventPayload);
        } catch (error) {
            windowRef.dispatchEvent(SNAPSHOT_CHANGED_EVENT, eventPayload);
        }
    }

    function notifySnapshotSubscribers(snapshot, reason) {
        var snapshotClone = cloneSnapshot(snapshot);
        var normalizedReason = String(reason || 'sync');
        var bridge = windowRef && windowRef.CBTNativeBridge && typeof windowRef.CBTNativeBridge === 'object'
            ? windowRef.CBTNativeBridge
            : null;

        snapshotSubscribers.slice().forEach(function (subscriber) {
            try {
                subscriber(snapshotClone, normalizedReason);
            } catch (error) {
                // Ignore subscriber failures so the bridge stays stable.
            }
        });

        if (bridge && typeof bridge.onSecuritySnapshotChanged === 'function') {
            try {
                bridge.onSecuritySnapshotChanged(snapshotClone, normalizedReason);
            } catch (error) {
                // Native callback failures should not break the app runtime.
            }
        }

        dispatchSnapshotChangedEvent(snapshotClone, normalizedReason);
    }

    function unsubscribeSecuritySnapshot(subscriber) {
        var index = snapshotSubscribers.indexOf(subscriber);
        if (index === -1) {
            return false;
        }

        snapshotSubscribers.splice(index, 1);
        return true;
    }

    function subscribeSecuritySnapshot(subscriber) {
        if (typeof subscriber !== 'function') {
            return function () {};
        }

        if (snapshotSubscribers.indexOf(subscriber) === -1) {
            snapshotSubscribers.push(subscriber);
        }

        return function () {
            unsubscribeSecuritySnapshot(subscriber);
        };
    }

    function syncSnapshot(reason) {
        var snapshot = buildSnapshot();
        var nextSignature = buildSnapshotSignature(snapshot);

        if (lastSnapshotSignature === nextSignature) {
            return cloneSnapshot(snapshot);
        }

        lastSnapshotSignature = nextSignature;
        notifySnapshotSubscribers(snapshot, reason);

        return cloneSnapshot(snapshot);
    }

    function mount() {
        if (!windowRef || typeof windowRef !== 'object') {
            return null;
        }

        var existingBridge = (windowRef.CBTNativeBridge && typeof windowRef.CBTNativeBridge === 'object')
            ? windowRef.CBTNativeBridge
            : {};

        existingBridge.getSecuritySnapshot = buildSnapshot;
        existingBridge.getSecuritySnapshotChangedEventName = function () {
            return SNAPSHOT_CHANGED_EVENT;
        };
        existingBridge.onSecuritySnapshotChanged = existingBridge.onSecuritySnapshotChanged || null;
        existingBridge.subscribeSecuritySnapshot = subscribeSecuritySnapshot;
        existingBridge.syncSecuritySnapshot = syncSnapshot;
        existingBridge.unsubscribeSecuritySnapshot = unsubscribeSecuritySnapshot;
        windowRef.CBTNativeBridge = existingBridge;

        return existingBridge;
    }

    return {
        getSecuritySnapshot: buildSnapshot,
        mount: mount,
        sync: syncSnapshot
    };
}
