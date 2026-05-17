import { describe, it, expect, beforeEach } from 'vitest';
import { createDoubtfulStateStorage } from '../../../src/frontend/app/storage/doubtful-state';

describe('doubtful-state', () => {
    var state, storage, manager;

    beforeEach(() => {
        state = { user: { user_id: 10 } };
        storage = {};
        manager = createDoubtfulStateStorage({
            state: state,
            getSessionStorage: () => ({
                getItem: (key) => storage[key] || null,
                setItem: (key, val) => { storage[key] = val; },
                removeItem: (key) => { delete storage[key]; }
            }),
            storageKeyPrefix: 'cbt_doubtful_'
        });
    });

    describe('buildDoubtfulSessionStorageKey', () => {
        it('builds key with user and attempt id', () => {
            var key = manager.buildDoubtfulSessionStorageKey(42);
            expect(key).toBe('cbt_doubtful_10_42');
        });

        it('returns empty for invalid attempt id', () => {
            expect(manager.buildDoubtfulSessionStorageKey(0)).toBe('');
            expect(manager.buildDoubtfulSessionStorageKey(-1)).toBe('');
        });

        it('returns empty when no user', () => {
            state.user = null;
            expect(manager.buildDoubtfulSessionStorageKey(42)).toBe('');
        });
    });

    describe('readPersistedDoubtfulState', () => {
        it('returns empty when no stored data', () => {
            var result = manager.readPersistedDoubtfulState(42);
            expect(result).toEqual({});
        });

        it('reads persisted question ids', () => {
            storage['cbt_doubtful_10_42'] = JSON.stringify({ question_ids: [1, 3, 5] });
            var result = manager.readPersistedDoubtfulState(42);
            expect(result).toEqual({ 1: true, 3: true, 5: true });
        });

        it('ignores invalid question ids', () => {
            storage['cbt_doubtful_10_42'] = JSON.stringify({ question_ids: [1, 0, -2, 'abc'] });
            var result = manager.readPersistedDoubtfulState(42);
            expect(result).toEqual({ 1: true });
        });

        it('returns empty for invalid JSON', () => {
            storage['cbt_doubtful_10_42'] = 'not valid json';
            var result = manager.readPersistedDoubtfulState(42);
            expect(result).toEqual({});
        });

        it('returns empty when storage is unavailable', () => {
            var noStorage = createDoubtfulStateStorage({
                state: state,
                getSessionStorage: () => null,
                storageKeyPrefix: 'cbt_doubtful_'
            });
            expect(noStorage.readPersistedDoubtfulState(42)).toEqual({});
        });
    });

    describe('clearPersistedDoubtfulState', () => {
        it('removes stored data', () => {
            storage['cbt_doubtful_10_42'] = JSON.stringify({ question_ids: [1] });
            manager.clearPersistedDoubtfulState(42);
            expect(storage['cbt_doubtful_10_42']).toBeUndefined();
        });

        it('handles missing key gracefully', () => {
            manager.clearPersistedDoubtfulState(999);
            expect(true).toBe(true);
        });

        it('handles no storage gracefully', () => {
            var noStorage = createDoubtfulStateStorage({
                state: state,
                getSessionStorage: () => null,
                storageKeyPrefix: 'cbt_doubtful_'
            });
            noStorage.clearPersistedDoubtfulState(42);
            expect(true).toBe(true);
        });
    });
});
