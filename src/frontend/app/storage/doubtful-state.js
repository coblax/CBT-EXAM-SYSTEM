export function createDoubtfulStateStorage(deps) {
    var state = deps.state;
    var getSessionStorage = deps.getSessionStorage;
    var storageKeyPrefix = String(deps.storageKeyPrefix || '');

    function buildDoubtfulSessionStorageKey(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || userId <= 0 || storageKeyPrefix === '') {
            return '';
        }
        return storageKeyPrefix + String(userId) + '_' + String(safeAttemptId);
    }

    function readPersistedDoubtfulState(attemptId) {
        var storage = getSessionStorage();
        if (!storage) {
            return {};
        }

        var storageKey = buildDoubtfulSessionStorageKey(attemptId);
        if (storageKey === '') {
            return {};
        }

        try {
            var raw = storage.getItem(storageKey);
            if (!raw) {
                return {};
            }

            var parsed = JSON.parse(raw);
            var questionIds = parsed && Array.isArray(parsed.question_ids) ? parsed.question_ids : [];
            return questionIds.reduce(function (accumulator, item) {
                var qid = Number(item) || 0;
                if (qid > 0) {
                    accumulator[qid] = true;
                }
                return accumulator;
            }, {});
        } catch (error) {
            return {};
        }
    }

    function clearPersistedDoubtfulState(attemptId) {
        var storage = getSessionStorage();
        if (!storage) {
            return;
        }

        var storageKey = buildDoubtfulSessionStorageKey(attemptId);
        if (storageKey === '') {
            return;
        }

        try {
            storage.removeItem(storageKey);
        } catch (error) {
            // Ignore storage failures (private mode / blocked storage).
        }
    }

    return {
        buildDoubtfulSessionStorageKey: buildDoubtfulSessionStorageKey,
        clearPersistedDoubtfulState: clearPersistedDoubtfulState,
        readPersistedDoubtfulState: readPersistedDoubtfulState
    };
}
