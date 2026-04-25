import { describe, expect, it } from 'vitest';
import { createApiClient } from '../../../src/frontend/app/core/api.js';

function createFixture(responsePayload, status = 401, overrides = {}) {
    var calls = {
        expireAuthSession: [],
        fetch: [],
        schedulePendingAnswerRetry: [],
        setConnectionStatus: []
    };
    var state = Object.assign({
        token: 'login-token'
    }, overrides.state || {});

    var client = createApiClient({
        config: {
            restBasePath: '/wp-json/cbt/v1/'
        },
        diagnosticsManager: null,
        expireAuthSession: function (message) {
            calls.expireAuthSession.push(message);
        },
        fetchImpl: async function (url, options) {
            calls.fetch.push({
                options: options || {},
                url
            });
            return {
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

    it('clears the login session when no bearer token is available locally', async function () {
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

        expect(fixture.calls.expireAuthSession).toEqual(['Authorization token not found']);
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
    });
});
