import { describe, expect, it } from 'vitest';
import { createAuthSessionManager } from '../../../src/frontend/app/core/auth-session.js';

function createManager(stateOverrides = {}, storage = globalThis.sessionStorage) {
    var state = Object.assign({
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
            token: 'token-123',
            selectedExamId: 44
        });
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
