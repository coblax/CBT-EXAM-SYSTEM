import { describe, expect, it } from 'vitest';
import { createReadonlyApiCache } from '../../../src/frontend/app/storage/readonly-api-cache.js';

describe('createReadonlyApiCache', function () {
    it('caches only student exams GET responses and marks fallback payloads as stale', function () {
        var now = 1000;
        var state = {
            user: {
                user_id: 7
            }
        };
        var cache = createReadonlyApiCache({
            getLocalStorage: function () {
                return localStorage;
            },
            now: function () {
                return now;
            },
            state: state,
            ttlMs: 60000
        });

        expect(cache.canCache('exams', { method: 'GET' })).toBe(true);
        expect(cache.canCache('finish_exam', { method: 'POST' })).toBe(false);
        expect(cache.canCache('submit_answers_batch', { method: 'POST' })).toBe(false);
        expect(cache.canCache('login', { method: 'POST' })).toBe(false);

        expect(cache.put('exams', { method: 'GET' }, {
            items: [{ id: 15, title: 'Ujian' }]
        })).toBe(true);

        var cached = cache.match('exams', { method: 'GET' });
        expect(cached).toEqual({
            items: [{ id: 15, title: 'Ujian' }]
        });
        expect(cached.__offlineCache).toEqual({
            cachedAt: 1000,
            stale: true
        });
        expect(Object.keys(cached)).toEqual(['items']);

        state.user.user_id = 8;
        expect(cache.match('exams', { method: 'GET' })).toBeNull();

        state.user.user_id = 7;
        now = 70001;
        expect(cache.match('exams', { method: 'GET' })).toBeNull();
    });
});
