export const DURABLE_ANSWER_QUEUE_DB_NAME = 'cbt_exam_answer_queue_v1';
export const DURABLE_ANSWER_QUEUE_ANSWER_STORE = 'answers';
export const DURABLE_ANSWER_QUEUE_GRANT_STORE = 'auth_grants';
export const DURABLE_ANSWER_QUEUE_LOCAL_STORAGE_KEY = 'cbt_exam_answer_queue_v1';

var STATUS_ACKED = 'acked';
var STATUS_FAILED_RETRYABLE = 'failed_retryable';
var STATUS_PENDING = 'pending';
var STATUS_SYNCING = 'syncing';

function cloneJson(value) {
    if (value === null || value === undefined) {
        return value;
    }

    try {
        return JSON.parse(JSON.stringify(value));
    } catch (error) {
        return value;
    }
}

function normalizeStatus(value) {
    var normalized = String(value || '').trim();
    if (
        normalized === 'pending'
        || normalized === 'syncing'
        || normalized === 'failed_retryable'
        || normalized === 'failed_terminal'
        || normalized === 'acked'
    ) {
        return normalized;
    }
    return STATUS_PENDING;
}

function normalizeContext(context) {
    context = context || {};
    return {
        attemptId: Number(context.attemptId || context.attempt_id) || 0,
        examId: Number(context.examId || context.exam_id) || 0,
        userId: Number(context.userId || context.user_id) || 0
    };
}

function buildQueueKey(context, questionId) {
    var normalizedContext = normalizeContext(context);
    var safeQuestionId = Number(questionId) || 0;
    if (normalizedContext.userId <= 0 || normalizedContext.attemptId <= 0 || safeQuestionId <= 0) {
        return '';
    }
    return [
        normalizedContext.userId,
        normalizedContext.attemptId,
        safeQuestionId
    ].join(':');
}

function buildGrantKey(context) {
    var normalizedContext = normalizeContext(context);
    if (normalizedContext.userId <= 0 || normalizedContext.attemptId <= 0) {
        return '';
    }
    return [
        normalizedContext.userId,
        normalizedContext.attemptId
    ].join(':');
}

function normalizeAnswerItem(context, item, existing, now) {
    var normalizedContext = normalizeContext(context);
    var questionId = Number(item && item.question_id) || Number(item && item.questionId) || 0;
    var queueKey = buildQueueKey(normalizedContext, questionId);
    if (queueKey === '') {
        return null;
    }

    var existingItem = existing && typeof existing === 'object' ? existing : {};
    var createdAt = Math.max(0, Number(existingItem.created_at || existingItem.createdAt) || 0) || now;

    return {
        queue_key: queueKey,
        user_id: normalizedContext.userId,
        attempt_id: normalizedContext.attemptId,
        exam_id: normalizedContext.examId,
        question_id: questionId,
        answer: Object.prototype.hasOwnProperty.call(item || {}, 'answer') ? cloneJson(item.answer) : null,
        signature: String(item && item.signature ? item.signature : ''),
        status: STATUS_PENDING,
        attempt_count: Math.max(0, Number(existingItem.attempt_count || existingItem.attemptCount) || 0),
        last_error: '',
        created_at: createdAt,
        updated_at: now,
        attempted_at: Math.max(0, Number(existingItem.attempted_at || existingItem.attemptedAt) || 0),
        lease_owner: '',
        lease_until: 0
    };
}

function normalizeStoredAnswerItem(raw) {
    if (!raw || typeof raw !== 'object') {
        return null;
    }

    var context = {
        attemptId: Number(raw.attempt_id || raw.attemptId) || 0,
        examId: Number(raw.exam_id || raw.examId) || 0,
        userId: Number(raw.user_id || raw.userId) || 0
    };
    var questionId = Number(raw.question_id || raw.questionId) || 0;
    var queueKey = String(raw.queue_key || raw.queueKey || buildQueueKey(context, questionId));
    if (queueKey === '') {
        return null;
    }

    return {
        queue_key: queueKey,
        user_id: context.userId,
        attempt_id: context.attemptId,
        exam_id: context.examId,
        question_id: questionId,
        answer: Object.prototype.hasOwnProperty.call(raw, 'answer') ? cloneJson(raw.answer) : null,
        signature: String(raw.signature || ''),
        status: normalizeStatus(raw.status),
        attempt_count: Math.max(0, Number(raw.attempt_count || raw.attemptCount) || 0),
        last_error: String(raw.last_error || raw.lastError || ''),
        created_at: Math.max(0, Number(raw.created_at || raw.createdAt) || 0),
        updated_at: Math.max(0, Number(raw.updated_at || raw.updatedAt) || 0),
        attempted_at: Math.max(0, Number(raw.attempted_at || raw.attemptedAt) || 0),
        lease_owner: String(raw.lease_owner || raw.leaseOwner || ''),
        lease_until: Math.max(0, Number(raw.lease_until || raw.leaseUntil) || 0)
    };
}

function normalizeGrant(context, grant, now) {
    var normalizedContext = normalizeContext(context);
    var grantKey = buildGrantKey(normalizedContext);
    if (grantKey === '') {
        return null;
    }

    return {
        grant_key: grantKey,
        user_id: normalizedContext.userId,
        attempt_id: normalizedContext.attemptId,
        exam_id: normalizedContext.examId,
        token: String(grant && grant.token ? grant.token : ''),
        expires_at_ms: Math.max(0, Number(grant && (grant.expiresAtMs || grant.expires_at_ms)) || 0),
        created_at: now,
        updated_at: now
    };
}

function normalizeStoredGrant(raw) {
    if (!raw || typeof raw !== 'object') {
        return null;
    }

    var context = {
        attemptId: Number(raw.attempt_id || raw.attemptId) || 0,
        examId: Number(raw.exam_id || raw.examId) || 0,
        userId: Number(raw.user_id || raw.userId) || 0
    };
    var grantKey = String(raw.grant_key || raw.grantKey || buildGrantKey(context));
    if (grantKey === '' || String(raw.token || '') === '') {
        return null;
    }

    return {
        grant_key: grantKey,
        user_id: context.userId,
        attempt_id: context.attemptId,
        exam_id: context.examId,
        token: String(raw.token || ''),
        expires_at_ms: Math.max(0, Number(raw.expires_at_ms || raw.expiresAtMs) || 0),
        created_at: Math.max(0, Number(raw.created_at || raw.createdAt) || 0),
        updated_at: Math.max(0, Number(raw.updated_at || raw.updatedAt) || 0)
    };
}

export function createDurableAnswerQueueStorage(deps) {
    deps = deps || {};

    var getIndexedDb = typeof deps.getIndexedDb === 'function' ? deps.getIndexedDb : function () { return null; };
    var getLocalStorage = typeof deps.getLocalStorage === 'function' ? deps.getLocalStorage : function () { return null; };
    var now = typeof deps.now === 'function' ? deps.now : Date.now;
    var dbName = String(deps.indexedDbName || DURABLE_ANSWER_QUEUE_DB_NAME);
    var answerStore = String(deps.answerStore || DURABLE_ANSWER_QUEUE_ANSWER_STORE);
    var grantStore = String(deps.grantStore || DURABLE_ANSWER_QUEUE_GRANT_STORE);
    var localStorageKey = String(deps.localStorageKey || DURABLE_ANSWER_QUEUE_LOCAL_STORAGE_KEY);
    var dbPromise = null;

    function openDb() {
        if (dbPromise !== null) {
            return dbPromise;
        }

        var indexedDb = getIndexedDb();
        if (!indexedDb || dbName === '') {
            dbPromise = Promise.resolve(null);
            return dbPromise;
        }

        dbPromise = new Promise(function (resolve) {
            var request;
            try {
                request = indexedDb.open(dbName, 1);
            } catch (error) {
                resolve(null);
                return;
            }

            request.onupgradeneeded = function () {
                var database = request.result;
                if (!database.objectStoreNames.contains(answerStore)) {
                    database.createObjectStore(answerStore, { keyPath: 'queue_key' });
                }
                if (!database.objectStoreNames.contains(grantStore)) {
                    database.createObjectStore(grantStore, { keyPath: 'grant_key' });
                }
            };

            request.onsuccess = function () {
                resolve(request.result || null);
            };
            request.onerror = function () {
                resolve(null);
            };
            request.onblocked = function () {
                resolve(null);
            };
        });

        return dbPromise;
    }

    function readLocalState() {
        var storage = getLocalStorage();
        if (!storage || localStorageKey === '') {
            return {
                answers: {},
                grants: {}
            };
        }

        try {
            var parsed = JSON.parse(storage.getItem(localStorageKey) || '{}');
            return {
                answers: parsed && parsed.answers && typeof parsed.answers === 'object' ? parsed.answers : {},
                grants: parsed && parsed.grants && typeof parsed.grants === 'object' ? parsed.grants : {}
            };
        } catch (error) {
            return {
                answers: {},
                grants: {}
            };
        }
    }

    function writeLocalState(state) {
        var storage = getLocalStorage();
        if (!storage || localStorageKey === '') {
            return;
        }

        try {
            storage.setItem(localStorageKey, JSON.stringify({
                answers: state && state.answers && typeof state.answers === 'object' ? state.answers : {},
                grants: state && state.grants && typeof state.grants === 'object' ? state.grants : {}
            }));
        } catch (error) {
            // Ignore quota failures. The in-memory runtime queue remains active.
        }
    }

    function getLocalAnswer(queueKey) {
        var state = readLocalState();
        return normalizeStoredAnswerItem(state.answers[queueKey] || null);
    }

    function putLocalAnswer(item) {
        var normalized = normalizeStoredAnswerItem(item);
        if (!normalized) {
            return null;
        }
        var state = readLocalState();
        state.answers[normalized.queue_key] = normalized;
        writeLocalState(state);
        return normalized;
    }

    function deleteLocalAnswer(queueKey) {
        var state = readLocalState();
        delete state.answers[String(queueKey || '')];
        writeLocalState(state);
    }

    function listLocalAnswers(context) {
        var normalizedContext = normalizeContext(context);
        var state = readLocalState();
        return Object.keys(state.answers).reduce(function (items, key) {
            var item = normalizeStoredAnswerItem(state.answers[key]);
            if (
                item
                && item.user_id === normalizedContext.userId
                && item.attempt_id === normalizedContext.attemptId
            ) {
                items.push(item);
            }
            return items;
        }, []);
    }

    function putLocalGrant(grant) {
        var normalized = normalizeStoredGrant(grant);
        if (!normalized) {
            return null;
        }
        var state = readLocalState();
        state.grants[normalized.grant_key] = normalized;
        writeLocalState(state);
        return normalized;
    }

    function deleteLocalGrant(grantKey) {
        var state = readLocalState();
        delete state.grants[String(grantKey || '')];
        writeLocalState(state);
    }

    function getLocalGrant(grantKey) {
        var state = readLocalState();
        return normalizeStoredGrant(state.grants[grantKey] || null);
    }

    function runRequest(storeName, mode, operation, fallback) {
        return openDb().then(function (database) {
            if (!database) {
                return typeof fallback === 'function' ? fallback() : null;
            }

            return new Promise(function (resolve) {
                var settled = false;
                var requestResult = null;
                var tx;
                try {
                    tx = database.transaction(storeName, mode);
                    var store = tx.objectStore(storeName);
                    var request = operation(store);
                    if (request && typeof request === 'object') {
                        request.onsuccess = function () {
                            requestResult = request.result;
                        };
                        request.onerror = function () {
                            requestResult = null;
                        };
                    }
                } catch (error) {
                    resolve(typeof fallback === 'function' ? fallback() : null);
                    return;
                }

                tx.oncomplete = function () {
                    if (!settled) {
                        settled = true;
                        resolve(requestResult);
                    }
                };
                tx.onerror = function () {
                    if (!settled) {
                        settled = true;
                        resolve(typeof fallback === 'function' ? fallback() : null);
                    }
                };
                tx.onabort = tx.onerror;
            });
        });
    }

    function getAnswer(queueKey) {
        var safeKey = String(queueKey || '');
        if (safeKey === '') {
            return Promise.resolve(null);
        }

        return runRequest(answerStore, 'readonly', function (store) {
            return store.get(safeKey);
        }, function () {
            return getLocalAnswer(safeKey);
        }).then(function (item) {
            return normalizeStoredAnswerItem(item);
        });
    }

    function putAnswer(item) {
        var normalized = normalizeStoredAnswerItem(item);
        if (!normalized) {
            return Promise.resolve(null);
        }

        return runRequest(answerStore, 'readwrite', function (store) {
            return store.put(normalized);
        }, function () {
            return putLocalAnswer(normalized);
        }).then(function () {
            return normalized;
        });
    }

    function deleteAnswer(queueKey) {
        var safeKey = String(queueKey || '');
        if (safeKey === '') {
            return Promise.resolve(false);
        }

        return runRequest(answerStore, 'readwrite', function (store) {
            return store.delete(safeKey);
        }, function () {
            deleteLocalAnswer(safeKey);
            return true;
        }).then(function () {
            return true;
        });
    }

    function listAnswers(context) {
        return runRequest(answerStore, 'readonly', function (store) {
            return store.getAll();
        }, function () {
            return listLocalAnswers(context);
        }).then(function (items) {
            var normalizedContext = normalizeContext(context);
            return (Array.isArray(items) ? items : []).reduce(function (accumulator, raw) {
                var item = normalizeStoredAnswerItem(raw);
                if (
                    item
                    && item.user_id === normalizedContext.userId
                    && item.attempt_id === normalizedContext.attemptId
                    && item.question_id > 0
                ) {
                    accumulator.push(item);
                }
                return accumulator;
            }, []).sort(function (left, right) {
                return (left.created_at - right.created_at) || (left.question_id - right.question_id);
            });
        });
    }

    function upsertAnswer(context, item) {
        var questionId = Number(item && item.question_id) || 0;
        var queueKey = buildQueueKey(context, questionId);
        if (queueKey === '') {
            return Promise.resolve(null);
        }

        return getAnswer(queueKey).then(function (existing) {
            var normalized = normalizeAnswerItem(context, item, existing, now());
            return normalized ? putAnswer(normalized) : null;
        });
    }

    function listPendingAnswers(context, options) {
        options = options || {};
        var includeTerminal = options.includeTerminal !== false;
        return listAnswers(context).then(function (items) {
            return items.filter(function (item) {
                if (item.status === STATUS_ACKED) {
                    return false;
                }
                return includeTerminal || item.status !== 'failed_terminal';
            });
        });
    }

    function getPendingCount(context, options) {
        return listPendingAnswers(context, options).then(function (items) {
            return items.length;
        });
    }

    function acquireBatch(context, options) {
        options = options || {};
        var limit = Math.max(1, Number(options.limit) || 1);
        var owner = String(options.owner || 'main');
        var leaseMs = Math.max(1000, Number(options.leaseMs) || 30000);
        var currentTime = now();

        return listAnswers(context).then(function (items) {
            var available = items.filter(function (item) {
                if (item.status === STATUS_PENDING || item.status === STATUS_FAILED_RETRYABLE) {
                    return true;
                }
                return item.status === STATUS_SYNCING && item.lease_until > 0 && item.lease_until <= currentTime;
            }).slice(0, limit);

            return available.reduce(function (promise, item) {
                return promise.then(function (acquired) {
                    item.status = STATUS_SYNCING;
                    item.lease_owner = owner;
                    item.lease_until = currentTime + leaseMs;
                    item.attempted_at = currentTime;
                    item.attempt_count = Math.max(0, Number(item.attempt_count) || 0) + 1;
                    item.updated_at = currentTime;
                    return putAnswer(item).then(function (stored) {
                        if (stored) {
                            acquired.push(stored);
                        }
                        return acquired;
                    });
                });
            }, Promise.resolve([]));
        });
    }

    function markAcked(context, items) {
        var acked = [];
        return (Array.isArray(items) ? items : []).reduce(function (promise, item) {
            return promise.then(function () {
                var questionId = Number(item && item.question_id) || 0;
                var signature = String(item && item.signature ? item.signature : '');
                var queueKey = buildQueueKey(context, questionId);
                if (queueKey === '') {
                    return null;
                }
                return getAnswer(queueKey).then(function (existing) {
                    if (!existing) {
                        acked.push(item);
                        return null;
                    }
                    if (String(existing.signature || '') !== signature) {
                        return null;
                    }
                    acked.push(existing);
                    return deleteAnswer(queueKey);
                });
            });
        }, Promise.resolve()).then(function () {
            return acked;
        });
    }

    function releaseBatch(context, items, options) {
        options = options || {};
        var status = normalizeStatus(options.status || STATUS_FAILED_RETRYABLE);
        if (status === STATUS_SYNCING || status === STATUS_ACKED) {
            status = STATUS_FAILED_RETRYABLE;
        }
        var errorMessage = String(options.errorMessage || '');
        var currentTime = now();

        return (Array.isArray(items) ? items : []).reduce(function (promise, item) {
            return promise.then(function () {
                var questionId = Number(item && item.question_id) || 0;
                var signature = String(item && item.signature ? item.signature : '');
                var queueKey = buildQueueKey(context, questionId);
                if (queueKey === '') {
                    return null;
                }
                return getAnswer(queueKey).then(function (existing) {
                    if (!existing || String(existing.signature || '') !== signature) {
                        return null;
                    }
                    existing.status = status;
                    existing.lease_owner = '';
                    existing.lease_until = 0;
                    existing.last_error = errorMessage;
                    existing.updated_at = currentTime;
                    return putAnswer(existing);
                });
            });
        }, Promise.resolve());
    }

    function clearAttempt(context) {
        return listAnswers(context).then(function (items) {
            return items.reduce(function (promise, item) {
                return promise.then(function () {
                    return deleteAnswer(item.queue_key);
                });
            }, Promise.resolve());
        }).then(function () {
            return clearAuthGrant(context);
        });
    }

    function importPendingAnswersFromSnapshot(context, pendingByQuestion, pendingOrder) {
        var pendingLookup = pendingByQuestion && typeof pendingByQuestion === 'object' ? pendingByQuestion : {};
        var order = Array.isArray(pendingOrder) ? pendingOrder.slice() : Object.keys(pendingLookup);

        return getPendingCount(context).then(function (existingCount) {
            if (existingCount > 0) {
                return 0;
            }

            var importedCount = 0;
            return order.reduce(function (promise, rawQuestionId) {
                return promise.then(function () {
                    var questionId = Number(rawQuestionId) || 0;
                    var item = pendingLookup[questionId] || pendingLookup[String(questionId)];
                    if (questionId <= 0 || !item) {
                        return null;
                    }
                    return upsertAnswer(context, {
                        question_id: questionId,
                        answer: Object.prototype.hasOwnProperty.call(item, 'answer') ? item.answer : null,
                        signature: String(item.signature || '')
                    }).then(function (stored) {
                        if (stored) {
                            importedCount += 1;
                        }
                    });
                });
            }, Promise.resolve()).then(function () {
                return importedCount;
            });
        });
    }

    function storeAuthGrant(context, grant) {
        var normalized = normalizeGrant(context, grant, now());
        if (!normalized) {
            return Promise.resolve(null);
        }

        return runRequest(grantStore, 'readwrite', function (store) {
            return store.put(normalized);
        }, function () {
            return putLocalGrant(normalized);
        }).then(function () {
            return normalized;
        });
    }

    function getAuthGrant(context) {
        var grantKey = buildGrantKey(context);
        if (grantKey === '') {
            return Promise.resolve(null);
        }

        return runRequest(grantStore, 'readonly', function (store) {
            return store.get(grantKey);
        }, function () {
            return getLocalGrant(grantKey);
        }).then(function (grant) {
            grant = normalizeStoredGrant(grant);
            if (!grant || grant.expires_at_ms <= now()) {
                return null;
            }
            return grant;
        });
    }

    function clearAuthGrant(context) {
        var grantKey = buildGrantKey(context);
        if (grantKey === '') {
            return Promise.resolve(false);
        }

        return runRequest(grantStore, 'readwrite', function (store) {
            return store.delete(grantKey);
        }, function () {
            deleteLocalGrant(grantKey);
            return true;
        }).then(function () {
            return true;
        });
    }

    return {
        acquireBatch: acquireBatch,
        clearAttempt: clearAttempt,
        clearAuthGrant: clearAuthGrant,
        getAuthGrant: getAuthGrant,
        getPendingCount: getPendingCount,
        importPendingAnswersFromSnapshot: importPendingAnswersFromSnapshot,
        listAnswers: listAnswers,
        listPendingAnswers: listPendingAnswers,
        markAcked: markAcked,
        releaseBatch: releaseBatch,
        storeAuthGrant: storeAuthGrant,
        upsertAnswer: upsertAnswer
    };
}
