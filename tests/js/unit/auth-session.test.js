import { describe, expect, it } from 'vitest';
import { createAuthSessionManager } from '../../../src/frontend/app/core/auth-session.js';

function createManager(stateOverrides = {}, storage = globalThis.sessionStorage) {
    var state = Object.assign({
        stage: 'login',
        token: '',
        user: null,
        selectedExamId: 0
    }, stateOverrides);

    return createAuthSessionManager({
        state,
        getSessionStorage: function () {
            return storage;
        },
        storageKey: 'cbt-auth-session'
    });
}

describe('createAuthSessionManager', function () {
    it('normalizes persisted user and rejects invalid values', function () {
        var manager = createManager();

        expect(manager.normalizePersistedUser({
            user_id: 12,
            role: 'student',
            display_name: 'Siswa'
        })).toMatchObject({
            user_id: 12,
            role: 'student',
            display_name: 'Siswa'
        });

        expect(manager.normalizePersistedUser({
            user_id: 0,
            role: ''
        })).toBeNull();
    });

    it('persists and restores an auth session snapshot', function () {
        var storage = globalThis.sessionStorage;
        var manager = createManager({
            stage: 'confirm',
            token: 'token-123',
            user: {
                user_id: 9,
                role: 'student',
                display_name: 'Ayu',
                username: 'ayu',
                email: 'ayu@example.com'
            },
            selectedExamId: 44
        }, storage);

        manager.persistAuthSession();

        expect(storage.getItem('cbt-auth-session')).toContain('token-123');
        expect(manager.readPersistedAuthSession()).toMatchObject({
            lastStage: 'confirm',
            token: 'token-123',
            selectedExamId: 44
        });
    });

    it('does not overwrite a newer stored token during guarded recovery persist', function () {
        var storage = globalThis.sessionStorage;
        storage.setItem('cbt-auth-session', JSON.stringify({
            token: 'token-new',
            user: {
                user_id: 9,
                role: 'student',
                display_name: 'Ayu'
            },
            selected_exam_id: 44,
            last_stage: 'exam'
        }));
        var manager = createManager({
            stage: 'exam',
            token: 'token-old',
            user: {
                user_id: 9,
                role: 'student',
                display_name: 'Ayu'
            },
            selectedExamId: 44
        }, storage);

        expect(manager.persistAuthSession({
            skipIfStorageTokenDiffers: true
        })).toBe(false);
        expect(JSON.parse(storage.getItem('cbt-auth-session')).token).toBe('token-new');
    });

    it('normalizes persisted stage and drops unsupported values safely', function () {
        var manager = createManager();

        expect(manager.normalizePersistedStage('exam')).toBe('exam');
        expect(manager.normalizePersistedStage('CONFIRM')).toBe('confirm');
        expect(manager.normalizePersistedStage('weird-stage')).toBe('');
    });

    it('rejects malformed persisted tokens before bootstrap can reuse them', function () {
        var storage = globalThis.sessionStorage;
        storage.setItem('cbt-auth-session', JSON.stringify({
            token: { value: 'token-object' },
            user: {
                user_id: 9,
                role: 'student',
                display_name: 'Ayu'
            },
            selected_exam_id: 44,
            last_stage: 'confirm'
        }));
        var manager = createManager({}, storage);

        expect(manager.normalizePersistedToken('  token-123  ')).toBe('token-123');
        expect(manager.normalizePersistedToken({ value: 'token-object' })).toBe('');
        expect(manager.normalizePersistedToken(str_repeat('a', 4097))).toBe('');
        expect(manager.readPersistedAuthSession()).toBeNull();
    });

    it('returns null when persisted payload is malformed', function () {
        var storage = globalThis.sessionStorage;
        storage.setItem('cbt-auth-session', '{"token":"","user":{}}');

        var manager = createManager({}, storage);

        expect(manager.readPersistedAuthSession()).toBeNull();
    });

    it('returns null and does not throw when storage access is unavailable', function () {
        var storage = {
            getItem: function () {
                throw new Error('storage blocked');
            },
            removeItem: function () {
                throw new Error('storage blocked');
            },
            setItem: function () {
                throw new Error('storage blocked');
            }
        };
        var manager = createManager({
            token: 'token-123',
            user: {
                user_id: 9,
                role: 'student',
                display_name: 'Ayu'
            }
        }, storage);

        expect(function () {
            manager.persistAuthSession();
        }).not.toThrow();
        expect(manager.readPersistedAuthSession()).toBeNull();
        expect(function () {
            manager.clearPersistedAuthSession();
        }).not.toThrow();
    });
});

function str_repeat(value, count) {
    return String(value || '').repeat(Number(count) || 0);
}
