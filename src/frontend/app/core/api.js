export function createApiClient(deps) {
    var config = deps.config;
    var diagnosticsManager = deps.diagnosticsManager;
    var state = deps.state;
    var fetchImpl = deps.fetchImpl;
    var expireAuthSession = deps.expireAuthSession;
    var getNavigatorConnectionStatus = deps.getNavigatorConnectionStatus;
    var isAnswerSubmitPath = deps.isAnswerSubmitPath;
    var readOnlyApiCache = deps.readOnlyApiCache || null;
    var schedulePendingAnswerRetry = deps.schedulePendingAnswerRetry;
    var setConnectionStatus = deps.setConnectionStatus;
    var windowRef = deps.windowRef;

    function delay(ms) {
        var waitMs = Math.max(0, Number(ms) || 0);
        if (waitMs <= 0) {
            return Promise.resolve();
        }

        return new Promise(function (resolve) {
            windowRef.setTimeout(resolve, waitMs);
        });
    }

    function normalizeScenarioApiTarget(path) {
        var normalizedPath = String(path || '').replace(/^\/+/, '').trim().toLowerCase();
        if (normalizedPath === 'finish_exam') {
            return 'finish_attempt';
        }
        return normalizedPath || 'any';
    }

    function buildScenarioNetworkError(message, code) {
        var networkError = new Error(String(message || 'Koneksi terputus.'));
        networkError.status = 0;
        networkError.code = String(code || 'network_error');
        networkError.isNetworkError = true;
        return networkError;
    }

    function apiErrorMessage(payload, fallback) {
        if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message;
        }
        return fallback;
    }

    function shouldExpireAuthSession(status, code) {
        if (Number(status) !== 401) {
            return false;
        }

        var normalizedCode = String(code || '').trim().toLowerCase();
        if (normalizedCode === 'missing_token') {
            return false;
        }

        if (
            normalizedCode === 'token_required' ||
            normalizedCode === 'token_required_local' ||
            normalizedCode === 'token_invalid' ||
            normalizedCode === 'token_invalid_length' ||
            normalizedCode === 'exam_token_required' ||
            normalizedCode === 'exam_token_invalid'
        ) {
            return false;
        }

        return true;
    }

    function isCurrentAuthToken(authToken) {
        var sentToken = String(authToken || '');
        if (sentToken === '') {
            return false;
        }

        return sentToken === String(state.token || '');
    }

    function buildUrl(path, query) {
        var baseInput = String(config.restBasePath || config.restBase || '/wp-json/cbt/v1/');
        var base;
        try {
            base = new URL(baseInput, windowRef.location.origin + '/').toString();
        } catch (error) {
            base = String(config.restBase || '/wp-json/cbt/v1/');
        }
        var normalizedPath = String(path || '').replace(/^\/+/, '');
        var url = new URL(normalizedPath, base);
        if (query && typeof query === 'object') {
            Object.keys(query).forEach(function (key) {
                var value = query[key];
                if (value === null || value === undefined || value === '') {
                    return;
                }
                url.searchParams.set(key, String(value));
            });
        }
        return url.toString();
    }

    async function api(path, options) {
        options = options || {};
        var method = String(options.method || 'GET').toUpperCase();
        var useAuth = options.auth !== false;
        var body = options.body || null;
        var query = options.query || null;
        var keepalive = !!options.keepalive;
        var signal = options.signal && typeof options.signal === 'object' ? options.signal : null;
        var suppressAuthExpiry = options.suppressAuthExpiry === true;
        var allowNotModified = options.allowNotModified === true;
        var authToken = options.token !== undefined ? String(options.token || '') : String(state.token || '');
        var requestUrl = buildUrl(path, query);
        var startedAt = Date.now();
        var scenarioTarget = normalizeScenarioApiTarget(path);

        var headers = {
            'Accept': 'application/json'
        };

        if (body !== null) {
            headers['Content-Type'] = 'application/json';
        }

        if (useAuth && authToken) {
            headers.Authorization = 'Bearer ' + authToken;
        }
        if (options.headers && typeof options.headers === 'object') {
            Object.keys(options.headers).forEach(function (key) {
                var value = options.headers[key];
                if (value === null || value === undefined || value === '') {
                    return;
                }
                headers[key] = String(value);
            });
        }

        if (diagnosticsManager && diagnosticsManager.enabled) {
            if (typeof diagnosticsManager.getApiLatencyMs === 'function') {
                await delay(diagnosticsManager.getApiLatencyMs());
            }

            if (typeof diagnosticsManager.isForcedOffline === 'function' && diagnosticsManager.isForcedOffline()) {
                var forcedOfflineError = buildScenarioNetworkError('Scenario aktif: Force Offline.', 'scenario_forced_offline');
                setConnectionStatus('offline', {
                    persist: false,
                    render: false,
                    triggerRetry: false
                });
                diagnosticsManager.recordApiRequest({
                    durationMs: Date.now() - startedAt,
                    endpoint: String(path || ''),
                    error: {
                        code: forcedOfflineError.code,
                        message: forcedOfflineError.message
                    },
                    method: method,
                    ok: false,
                    query: query,
                    response: null,
                    scenario: 'force-offline',
                    status: 0,
                    url: requestUrl,
                    body: body
                });
                throw forcedOfflineError;
            }

            if (
                typeof diagnosticsManager.consumeFailNextApiRequest === 'function'
                && diagnosticsManager.consumeFailNextApiRequest(scenarioTarget)
            ) {
                var simulatedError = buildScenarioNetworkError(
                    'Scenario aktif: fail next API request (' + scenarioTarget + ').',
                    'scenario_fail_next_api_request'
                );
                diagnosticsManager.recordApiRequest({
                    durationMs: Date.now() - startedAt,
                    endpoint: String(path || ''),
                    error: {
                        code: simulatedError.code,
                        message: simulatedError.message
                    },
                    method: method,
                    ok: false,
                    query: query,
                    response: null,
                    scenario: 'fail-next-api-request',
                    status: 0,
                    url: requestUrl,
                    body: body
                });
                throw simulatedError;
            }
        }

        var response;
        try {
            response = await fetchImpl(requestUrl, {
                method: method,
                headers: headers,
                body: body !== null ? JSON.stringify(body) : null,
                keepalive: keepalive,
                signal: signal || undefined
            });
            setConnectionStatus('online', {
                persist: false,
                render: false,
                triggerRetry: false
            });
        } catch (fetchError) {
            if (signal && signal.aborted === true) {
                var abortError = new Error('Request dibatalkan.');
                abortError.status = 0;
                abortError.code = 'request_aborted';
                abortError.isAbortError = true;
                abortError.cause = fetchError;
                throw abortError;
            }

            var networkError = new Error(
                getNavigatorConnectionStatus() === 'offline'
                    ? 'Koneksi terputus.'
                    : 'Gagal terhubung ke server.'
            );
            networkError.status = 0;
            networkError.code = 'network_error';
            networkError.isNetworkError = true;
            networkError.cause = fetchError;
            setConnectionStatus('offline', {
                persist: false,
                render: false,
                triggerRetry: false
            });
            if (diagnosticsManager && diagnosticsManager.enabled) {
                diagnosticsManager.recordApiRequest({
                    durationMs: Date.now() - startedAt,
                    endpoint: String(path || ''),
                    error: {
                        code: 'network_error',
                        message: networkError.message
                    },
                    method: method,
                    ok: false,
                    query: query,
                    response: null,
                    status: 0,
                    url: requestUrl,
                    body: body
                });
            }
            if (
                readOnlyApiCache
                && typeof readOnlyApiCache.match === 'function'
                && method === 'GET'
            ) {
                var cachedPayload = readOnlyApiCache.match(path, {
                    method: method,
                    query: query
                });
                if (cachedPayload) {
                    return cachedPayload;
                }
            }
            throw networkError;
        }

        var responseMeta = {
            etag: response && response.headers && typeof response.headers.get === 'function'
                ? String(response.headers.get('etag') || '')
                : '',
            status: Number(response.status) || 0
        };

        if (Number(response.status) === 304 && allowNotModified) {
            var notModifiedPayload = {};
            Object.defineProperty(notModifiedPayload, '__notModified', {
                enumerable: false,
                value: true
            });
            Object.defineProperty(notModifiedPayload, '__responseMeta', {
                enumerable: false,
                value: responseMeta
            });
            if (!isAnswerSubmitPath(path)) {
                schedulePendingAnswerRetry('api-success:' + String(path || ''), {
                    immediate: true,
                    resetBackoff: true,
                    persist: false
                });
            }
            if (diagnosticsManager && diagnosticsManager.enabled) {
                diagnosticsManager.recordApiRequest({
                    durationMs: Date.now() - startedAt,
                    endpoint: String(path || ''),
                    error: null,
                    method: method,
                    ok: true,
                    query: query,
                    response: null,
                    status: 304,
                    url: requestUrl,
                    body: body
                });
            }
            return notModifiedPayload;
        }

        var payload = {};
        try {
            payload = await response.json();
        } catch (error) {
            payload = {};
        }

        if (!response.ok) {
            var requestError = new Error(apiErrorMessage(payload, 'Request gagal.'));
            requestError.status = Number(response.status) || 0;
            requestError.code = payload && typeof payload.code === 'string' ? payload.code : '';
            if (payload && typeof payload === 'object') {
                requestError.retry_after_ms = Number(payload.retry_after_ms) || Number(payload.retryAfterMs) || 0;
                if (payload.data && typeof payload.data === 'object') {
                    requestError.retry_after_ms = requestError.retry_after_ms
                        || Number(payload.data.retry_after_ms)
                        || Number(payload.data.retryAfterMs)
                        || 0;
                    requestError.data = payload.data;
                }
            }

            if (!suppressAuthExpiry && useAuth && shouldExpireAuthSession(response.status, requestError.code) && isCurrentAuthToken(authToken)) {
                expireAuthSession(requestError.message, {
                    code: requestError.code,
                    method: method,
                    path: String(path || '').replace(/^\/+/, ''),
                    status: Number(response.status) || 0,
                    url: requestUrl
                });
            }

            if (diagnosticsManager && diagnosticsManager.enabled) {
                diagnosticsManager.recordApiRequest({
                    durationMs: Date.now() - startedAt,
                    endpoint: String(path || ''),
                    error: {
                        code: requestError.code || '',
                        message: requestError.message
                    },
                    method: method,
                    ok: false,
                    query: query,
                    response: payload,
                    status: Number(response.status) || 0,
                    url: requestUrl,
                    body: body
                });
            }
            throw requestError;
        }

        if (!isAnswerSubmitPath(path)) {
            schedulePendingAnswerRetry('api-success:' + String(path || ''), {
                immediate: true,
                resetBackoff: true,
                persist: false
            });
        }

        if (diagnosticsManager && diagnosticsManager.enabled) {
            diagnosticsManager.recordApiRequest({
                durationMs: Date.now() - startedAt,
                endpoint: String(path || ''),
                error: null,
                method: method,
                ok: true,
                query: query,
                response: payload,
                status: Number(response.status) || 0,
                url: requestUrl,
                body: body
            });
        }

        if (payload && typeof payload === 'object') {
            try {
                Object.defineProperty(payload, '__responseMeta', {
                    enumerable: false,
                    value: responseMeta
                });
            } catch (error) {
                // Ignore metadata attachment failures for non-extensible payloads.
            }
        }

        if (
            readOnlyApiCache
            && typeof readOnlyApiCache.put === 'function'
            && method === 'GET'
        ) {
            readOnlyApiCache.put(path, {
                method: method,
                query: query
            }, payload);
        }

        return payload;
    }

    return {
        api: api,
        apiErrorMessage: apiErrorMessage,
        buildUrl: buildUrl
    };
}
