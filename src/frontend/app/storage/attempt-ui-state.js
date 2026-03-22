export function createAttemptUiStateStorage(deps) {
    var state = deps.state;
    var getSessionStorage = deps.getSessionStorage;
    var storageKeyPrefix = String(deps.storageKeyPrefix || '');
    var buildDoubtfulSessionStorageKey = deps.buildDoubtfulSessionStorageKey;
    var clearPersistedDoubtfulState = deps.clearPersistedDoubtfulState;
    var readPersistedDoubtfulState = deps.readPersistedDoubtfulState;
    var getQuestionCount = deps.getQuestionCount;
    var normalizeOrUseQuestionCacheSnapshot = deps.normalizeOrUseQuestionCacheSnapshot;
    var normalizeQuestionCacheSnapshot = deps.normalizeQuestionCacheSnapshot;
    var validAttemptQuestionIds = deps.validAttemptQuestionIds;

    function buildAttemptUiSessionStorageKey(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || userId <= 0 || storageKeyPrefix === '') {
            return '';
        }
        return storageKeyPrefix + String(userId) + '_' + String(safeAttemptId);
    }

    function normalizeAttemptUiState(snapshot, attemptId) {
        var safeAttemptId = Number(attemptId || (snapshot && snapshot.attempt_id)) || 0;
        var questionCount = getQuestionCount();
        var questionIdSet = validAttemptQuestionIds();
        var rawDoubtful = [];

        if (snapshot && Array.isArray(snapshot.doubtful_question_ids)) {
            rawDoubtful = snapshot.doubtful_question_ids;
        } else if (snapshot && Array.isArray(snapshot.question_ids)) {
            rawDoubtful = snapshot.question_ids;
        }

        var doubtfulIds = [];
        var seenQuestionIds = {};
        rawDoubtful.forEach(function (item) {
            var questionId = Number(item) || 0;
            if (questionId <= 0 || seenQuestionIds[questionId]) {
                return;
            }
            if (questionCount > 0 && !questionIdSet[questionId]) {
                return;
            }
            seenQuestionIds[questionId] = true;
            doubtfulIds.push(questionId);
        });

        var currentIndex = Math.floor(Number(snapshot && snapshot.current_index !== undefined ? snapshot.current_index : 0));
        if (!Number.isFinite(currentIndex) || currentIndex < 0) {
            currentIndex = 0;
        }
        if (questionCount > 0 && currentIndex >= questionCount) {
            currentIndex = questionCount - 1;
        }

        return {
            attempt_id: safeAttemptId,
            current_index: currentIndex,
            doubtful_question_ids: doubtfulIds
        };
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

    function choosePreferredAttemptUiState(remoteSnapshot, localSnapshot, questionCacheSnapshot, attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var normalizedLocal = localSnapshot ? normalizeAttemptUiState(localSnapshot, safeAttemptId) : null;
        var normalizedRemote = remoteSnapshot ? normalizeAttemptUiState(remoteSnapshot, safeAttemptId) : null;
        var normalizedQuestionCache = normalizeOrUseQuestionCacheSnapshot(questionCacheSnapshot, safeAttemptId);
        var selectedSnapshot = normalizedLocal || normalizedRemote || {
            attempt_id: safeAttemptId,
            current_index: 0,
            doubtful_question_ids: []
        };

        if (normalizedQuestionCache) {
            if (normalizedLocal && questionCacheHasPayloadForIndex(normalizedQuestionCache, normalizedLocal.current_index)) {
                selectedSnapshot = normalizedLocal;
            } else if (normalizedRemote && questionCacheHasPayloadForIndex(normalizedQuestionCache, normalizedRemote.current_index)) {
                selectedSnapshot = normalizedRemote;
            }
        }

        return normalizeAttemptUiState({
            attempt_id: safeAttemptId,
            current_index: selectedSnapshot.current_index,
            doubtful_question_ids: mergeAttemptUiStateDoubtfulIds(normalizedLocal, normalizedRemote)
        }, safeAttemptId);
    }

    function buildAttemptUiStateSnapshot(attemptId) {
        var safeAttemptId = Number(attemptId || state.attemptId) || 0;
        if (safeAttemptId <= 0) {
            return null;
        }

        return normalizeAttemptUiState({
            attempt_id: safeAttemptId,
            current_index: state.currentIndex,
            doubtful_question_ids: Object.keys(state.doubtful).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.doubtful[key]) {
                    accumulator.push(questionId);
                }
                return accumulator;
            }, [])
        }, safeAttemptId);
    }

    function readPersistedAttemptUiState(attemptId) {
        var storage = getSessionStorage();
        if (!storage) {
            return null;
        }

        var safeAttemptId = Number(attemptId) || 0;
        var storageKey = buildAttemptUiSessionStorageKey(safeAttemptId);
        if (storageKey === '') {
            return null;
        }

        try {
            var raw = storage.getItem(storageKey);
            if (raw) {
                return normalizeAttemptUiState(JSON.parse(raw), safeAttemptId);
            }
        } catch (error) {
            // Ignore storage failures (quota or disabled storage).
        }

        var legacyDoubtful = readPersistedDoubtfulState(safeAttemptId);
        var legacyQuestionIds = Object.keys(legacyDoubtful).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && legacyDoubtful[key]) {
                accumulator.push(questionId);
            }
            return accumulator;
        }, []);
        if (!legacyQuestionIds.length) {
            return null;
        }

        return normalizeAttemptUiState({
            attempt_id: safeAttemptId,
            current_index: 0,
            doubtful_question_ids: legacyQuestionIds
        }, safeAttemptId);
    }

    function persistAttemptUiStateLocally(snapshot) {
        var storage = getSessionStorage();
        if (!storage) {
            return;
        }

        var normalizedSnapshot = normalizeAttemptUiState(snapshot, snapshot && snapshot.attempt_id);
        var storageKey = buildAttemptUiSessionStorageKey(normalizedSnapshot.attempt_id);
        if (storageKey === '') {
            return;
        }

        try {
            storage.setItem(storageKey, JSON.stringify(normalizedSnapshot));
        } catch (error) {
            // Ignore storage failures (quota or disabled storage).
        }
    }

    function persistCurrentAttemptUiStateLocally() {
        var snapshot = buildAttemptUiStateSnapshot();
        if (!snapshot) {
            return;
        }

        persistAttemptUiStateLocally(snapshot);
    }

    function clearPersistedAttemptUiState(attemptId) {
        var storage = getSessionStorage();
        if (!storage) {
            return;
        }

        var storageKey = buildAttemptUiSessionStorageKey(attemptId);
        if (storageKey === '') {
            clearPersistedDoubtfulState(attemptId);
            return;
        }

        try {
            storage.removeItem(storageKey);
        } catch (error) {
            // Ignore storage failures (private mode / blocked storage).
        }

        clearPersistedDoubtfulState(attemptId);
    }

    function applyAttemptUiState(snapshot, attemptId) {
        var normalizedSnapshot = normalizeAttemptUiState(snapshot, attemptId || state.attemptId);
        state.currentIndex = normalizedSnapshot.current_index;
        state.doubtful = normalizedSnapshot.doubtful_question_ids.reduce(function (accumulator, questionId) {
            accumulator[questionId] = true;
            return accumulator;
        }, {});
        persistAttemptUiStateLocally(normalizedSnapshot);
    }

    return {
        applyAttemptUiState: applyAttemptUiState,
        buildAttemptUiSessionStorageKey: buildAttemptUiSessionStorageKey,
        buildAttemptUiStateSnapshot: buildAttemptUiStateSnapshot,
        buildDoubtfulSessionStorageKey: buildDoubtfulSessionStorageKey,
        choosePreferredAttemptUiState: choosePreferredAttemptUiState,
        clearPersistedAttemptUiState: clearPersistedAttemptUiState,
        normalizeAttemptUiState: normalizeAttemptUiState,
        persistAttemptUiStateLocally: persistAttemptUiStateLocally,
        persistCurrentAttemptUiStateLocally: persistCurrentAttemptUiStateLocally,
        questionCacheHasPayloadForIndex: questionCacheHasPayloadForIndex,
        readPersistedAttemptUiState: readPersistedAttemptUiState
    };
}
