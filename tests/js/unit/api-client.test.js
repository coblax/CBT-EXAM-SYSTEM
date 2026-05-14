import { describe, expect, it } from 'vitest';
import { createApiClient } from '../../../src/frontend/app/core/api.js';

function createFixture(responsePayload, status = 401, overrides = {}) {
    var calls = {
        expireAuthSession: [],
        expireAuthSessionContexts: [],
        fetch: [],
        schedulePendingAnswerRetry: [],
        setConnectionStatus: []
    };
    var state = Object.assign({
        token: 'login-token'
    }, overrides.state || {});
    var responseHeaders = overrides.headers && typeof overrides.headers === 'object'
        ? overrides.headers
        : {};

    var client = createApiClient({
        config: {
            restBasePath: '/wp-json/cbt/v1/'
        },
        diagnosticsManager: null,
        expireAuthSession: function (message, context) {
            calls.expireAuthSession.push(message);
            calls.expireAuthSessionContexts.push(context || {});
        },
        fetchImpl: async function (url, options) {
            calls.fetch.push({
                options: options || {},
                url
            });
            return {
                headers: {
                    get: function (name) {
                        var normalizedName = String(name || '').toLowerCase();
                        return responseHeaders[normalizedName] || responseHeaders[String(name || '')] || '';
                    }
                },
                ok: status >= 200 && status < 300,
                status,
                json: async function () {
                    return responsePayload || {};
                }
            };
        },
        getNavigatorConnectionStatus: function () {
            return 'online';
        },
        isAnswerSubmitPath: function () {
            return false;
        },
        schedulePendingAnswerRetry: function (reason, options) {
            calls.schedulePendingAnswerRetry.push({
                options: options || {},
                reason
            });
        },
        setConnectionStatus: function (statusValue, options) {
            calls.setConnectionStatus.push({
                options: options || {},
                status: statusValue
            });
        },
        state,
        windowRef: {
            location: {
                origin: 'https://ujian.example.sch.id'
            },
            setTimeout: globalThis.setTimeout
        }
    });

    return {
        calls,
        client,
        state
    };
}

describe('createApiClient auth expiry handling', function () {
    it('passes custom request headers and exposes response metadata on successful payloads', async function () {
        var fixture = createFixture({
            items: []
        }, 200, {
            headers: {
                etag: '"question-etag"'
            }
        });

        var payload = await fixture.client.api('questions', {
            headers: {
                'If-None-Match': '"cached-etag"'
            },
            query: {
                attempt_id: 55,
                exam_id: 9
            }
        });

        expect(fixture.calls.fetch[0].options.headers['If-None-Match']).toBe('"cached-etag"');
        expect(payload.__responseMeta).toEqual({
            etag: '"question-etag"',
            status: 200
        });
        expect(Object.keys(payload)).toEqual(['items']);
    });

    it('returns a non-enumerable not-modified marker for allowed 304 responses', async function () {
        var fixture = createFixture(null, 304, {
            headers: {
                etag: '"question-etag"'
            }
        });

        var payload = await fixture.client.api('questions', {
            allowNotModified: true,
            headers: {
                'If-None-Match': '"question-etag"'
            }
        });

        expect(payload.__notModified).toBe(true);
        expect(payload.__responseMeta).toEqual({
            etag: '"question-etag"',
            status: 304
        });
        expect(Object.keys(payload)).toEqual([]);
        expect(fixture.calls.schedulePendingAnswerRetry).toHaveLength(1);
    });

    it('does not clear the login session for exam-token errors', async function () {
        var fixture = createFixture({
            code: 'token_required',
            message: 'Token ujian wajib diisi'
        }, 401);

        await expect(fixture.client.api('start_attempt', {
            method: 'POST',
            body: {
                exam_id: 7,
                exam_token: ''
            }
        })).rejects.toMatchObject({
            code: 'token_required',
            status: 401
        });

        expect(fixture.calls.expireAuthSession).toHaveLength(0);
    });

    it('keeps local auth when a sent bearer token is reported missing by the server', async function () {
        var fixture = createFixture({
            code: 'missing_token',
            message: 'Authorization token not found'
        }, 401);

        await expect(fixture.client.api('exams')).rejects.toMatchObject({
            code: 'missing_token',
            status: 401
        });

        expect(fixture.calls.expireAuthSession).toHaveLength(0);
        expect(fixture.calls.fetch[0].options.headers.Authorization).toBe('Bearer login-token');
    });

    it('does not clear local auth on missing bearer responses even when the local token is empty', async function () {
        var fixture = createFixture({
            code: 'missing_token',
            message: 'Authorization token not found'
        }, 401, {
            state: {
                token: ''
            }
        });

        await expect(fixture.client.api('exams')).rejects.toMatchObject({
            code: 'missing_token',
            status: 401
        });

        expect(fixture.calls.expireAuthSession).toHaveLength(0);
    });

    it('clears the login session for revoked sessions', async function () {
        var fixture = createFixture({
            code: 'session_revoked',
            message: 'Sesi login ini sudah digantikan oleh login lain.'
        }, 401);

        await expect(fixture.client.api('session')).rejects.toMatchObject({
            code: 'session_revoked',
            status: 401
        });

        expect(fixture.calls.expireAuthSession).toEqual(['Sesi login ini sudah digantikan oleh login lain.']);
        expect(fixture.calls.expireAuthSessionContexts[0]).toMatchObject({
            code: 'session_revoked',
            method: 'GET',
            path: 'session',
            status: 401
        });
    });

    it('does not clear the current login when an older in-flight token is revoked', async function () {
        var fixture = createFixture({
            code: 'session_revoked',
            message: 'Sesi login ini sudah digantikan oleh login lain.'
        }, 401);
        fixture.state.token = 'new-login-token';

        await expect(fixture.client.api('session', {
            token: 'old-login-token'
        })).rejects.toMatchObject({
            code: 'session_revoked',
            status: 401
        });

        expect(fixture.calls.fetch[0].options.headers.Authorization).toBe('Bearer old-login-token');
        expect(fixture.calls.expireAuthSession).toHaveLength(0);
        expect(fixture.state.token).toBe('new-login-token');
    });

    it('supports suppressing auth expiry for best-effort requests', async function () {
        var fixture = createFixture({
            code: 'session_revoked',
            message: 'Sesi login ini sudah digantikan oleh login lain.'
        }, 401);

        await expect(fixture.client.api('ui_state', {
            method: 'POST',
            suppressAuthExpiry: true,
            body: {
                attempt_state: {
                    attempt_id: 55
                }
            }
        })).rejects.toMatchObject({
            code: 'session_revoked',
            status: 401
        });

        expect(fixture.calls.expireAuthSession).toHaveLength(0);
        expect(fixture.state.token).toBe('login-token');
    });

    it('falls back to read-only exams cache on network errors without caching mutations', async function () {
        var cacheCalls = {
            match: [],
            put: []
        };
        var state = {
            token: 'login-token',
            user: {
                user_id: 7
            }
        };
        var client = createApiClient({
            config: {
                restBasePath: '/wp-json/cbt/v1/'
            },
            diagnosticsManager: null,
            expireAuthSession: function () {},
            fetchImpl: async function () {
                throw new TypeError('Failed to fetch');
            },
            getNavigatorConnectionStatus: function () {
                return 'offline';
            },
            isAnswerSubmitPath: function () {
                return false;
            },
            readOnlyApiCache: {
                match: function (path, options) {
                    cacheCalls.match.push({ path, options });
                    if (path === 'exams') {
                        return {
                            items: [{ id: 15 }]
                        };
                    }
                    return null;
                },
                put: function (path, options, payload) {
                    cacheCalls.put.push({ path, options, payload });
                }
            },
            schedulePendingAnswerRetry: function () {},
            setConnectionStatus: function () {},
            state,
            windowRef: {
                location: {
                    origin: 'https://ujian.example.sch.id'
                },
                setTimeout: globalThis.setTimeout
            }
        });

        await expect(client.api('exams')).resolves.toEqual({
            items: [{ id: 15 }]
        });
        await expect(client.api('finish_exam', {
            method: 'POST',
            body: {
                attempt_id: 55
            }
        })).rejects.toMatchObject({
            code: 'network_error',
            status: 0
        });

        expect(cacheCalls.match).toEqual([
            {
                path: 'exams',
                options: {
                    method: 'GET',
                    query: null
                }
            }
        ]);
        expect(cacheCalls.put).toEqual([]);
    });
});
