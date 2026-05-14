export const READONLY_API_CACHE_LOCAL_STORAGE_KEY = 'cbt_exam_readonly_api_cache_v1';
export const READONLY_API_CACHE_INDEXED_DB_NAME = 'cbt_exam_readonly_api_cache_v1';
export const READONLY_API_CACHE_INDEXED_DB_STORE = 'responses';
export const READONLY_API_CACHE_TTL_MS = 60000;

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

function normalizeUserId(state) {
    return Number(state && state.user && state.user.user_id) || 0;
}

function normalizeQuery(query) {
    var source = query && typeof query === 'object' ? query : {};
    return Object.keys(source).sort().reduce(function (accumulator, key) {
        var value = source[key];
        if (value === null || value === undefined || value === '') {
            return accumulator;
        }
        accumulator.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(value)));
        return accumulator;
    }, []).join('&');
}

function normalizePath(path) {
    return String(path || '').replace(/^\/+/, '').trim().toLowerCase();
}

function buildCacheKey(state, path, query) {
    var userId = normalizeUserId(state);
    var normalizedPath = normalizePath(path);
    if (userId <= 0 || normalizedPath !== 'exams') {
        return '';
    }
    return String(userId) + ':' + normalizedPath + ':' + normalizeQuery(query);
}

function attachOfflineCacheMeta(payload, meta) {
    if (!payload || typeof payload !== 'object') {
        return payload;
    }

    try {
        Object.defineProperty(payload, '__offlineCache', {
            enumerable: false,
            value: {
                cachedAt: Number(meta && meta.cached_at) || 0,
                stale: true
            }
        });
    } catch (error) {
        // Ignore metadata attachment failures for non-extensible payloads.
    }

    return payload;
}

export function createReadonlyApiCache(deps) {
    deps = deps || {};
    var getIndexedDb = typeof deps.getIndexedDb === 'function' ? deps.getIndexedDb : function () { return null; };
    var getLocalStorage = typeof deps.getLocalStorage === 'function' ? deps.getLocalStorage : function () { return null; };
    var now = typeof deps.now === 'function' ? deps.now : Date.now;
    var state = deps.state || {};
    var ttlMs = Math.max(1000, Number(deps.ttlMs) || READONLY_API_CACHE_TTL_MS);
    var storageKey = String(deps.storageKey || READONLY_API_CACHE_LOCAL_STORAGE_KEY);
    var indexedDbName = String(deps.indexedDbName || READONLY_API_CACHE_INDEXED_DB_NAME);
    var indexedDbStore = String(deps.indexedDbStore || READONLY_API_CACHE_INDEXED_DB_STORE);
    var dbPromise = null;

    function openDb() {
        if (dbPromise !== null) {
            return dbPromise;
        }

        var indexedDb = getIndexedDb();
        if (!indexedDb || indexedDbName === '' || indexedDbStore === '') {
            dbPromise = Promise.resolve(null);
            return dbPromise;
        }

        dbPromise = new Promise(function (resolve) {
            var request;
            try {
                request = indexedDb.open(indexedDbName, 1);
            } catch (error) {
                resolve(null);
                return;
            }

            request.onupgradeneeded = function () {
                var database = request.result;
                if (!database.objectStoreNames.contains(indexedDbStore)) {
                    database.createObjectStore(indexedDbStore, { keyPath: 'cache_key' });
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

    function putIndexedDbEntry(entry) {
        openDb().then(function (database) {
            if (!database) {
                return null;
            }

            return new Promise(function (resolve) {
                var tx;
                try {
                    tx = database.transaction(indexedDbStore, 'readwrite');
                    tx.objectStore(indexedDbStore).put(entry);
                } catch (error) {
                    resolve(null);
                    return;
                }
                tx.oncomplete = function () {
                    resolve(true);
                };
                tx.onerror = function () {
                    resolve(null);
                };
                tx.onabort = tx.onerror;
            });
        }).catch(function () {});
    }

    function readState() {
        var storage = getLocalStorage();
        if (!storage || storageKey === '') {
            return {};
        }

        try {
            var parsed = JSON.parse(storage.getItem(storageKey) || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function writeState(value) {
        var storage = getLocalStorage();
        if (!storage || storageKey === '') {
            return;
        }

        try {
            storage.setItem(storageKey, JSON.stringify(value && typeof value === 'object' ? value : {}));
        } catch (error) {
            // Cache is best-effort only.
        }
    }

    function canCache(path, options) {
        options = options || {};
        var method = String(options.method || 'GET').toUpperCase();
        return method === 'GET' && buildCacheKey(state, path, options.query || null) !== '';
    }

    function put(path, options, payload) {
        if (!canCache(path, options) || !payload || typeof payload !== 'object') {
            return false;
        }

        var key = buildCacheKey(state, path, options && options.query);
        if (key === '') {
            return false;
        }

        var cacheState = readState();
        cacheState[key] = {
            cache_key: key,
            cached_at: now(),
            payload: cloneJson(payload)
        };
        writeState(cacheState);
        putIndexedDbEntry(cacheState[key]);
        return true;
    }

    function match(path, options) {
        if (!canCache(path, options)) {
            return null;
        }

        var key = buildCacheKey(state, path, options && options.query);
        if (key === '') {
            return null;
        }

        var item = readState()[key];
        if (!item || typeof item !== 'object') {
            return null;
        }

        var cachedAt = Math.max(0, Number(item.cached_at) || 0);
        if (cachedAt <= 0 || (now() - cachedAt) > ttlMs) {
            return null;
        }

        return attachOfflineCacheMeta(cloneJson(item.payload), item);
    }

    return {
        canCache: canCache,
        match: match,
        put: put
    };
}
