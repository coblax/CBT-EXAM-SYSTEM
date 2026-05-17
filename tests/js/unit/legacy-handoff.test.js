import { describe, it, expect, beforeEach } from 'vitest';
import {
    writeLegacyHandoffIntent,
    consumeLegacyHandoffIntent,
    clearLegacyHandoffIntent,
    LEGACY_HANDOFF_STORAGE_KEY,
    LEGACY_HANDOFF_TTL_MS
} from '../../../src/frontend/app/core/legacy-handoff';

describe('legacy-handoff', () => {
    var storage, getStorage;

    beforeEach(() => {
        storage = {};
        getStorage = () => ({
            getItem: (key) => storage[key] || null,
            setItem: (key, val) => { storage[key] = val; },
            removeItem: (key) => { delete storage[key]; }
        });
    });

    describe('writeLegacyHandoffIntent', () => {
        it('writes valid intent to storage', () => {
            var result = writeLegacyHandoffIntent(getStorage, {
                action: 'start-exam',
                selected_exam_id: 10,
                exam_token: 'ABC123'
            }, 1000);
            expect(result).toBe(true);
            expect(storage[LEGACY_HANDOFF_STORAGE_KEY]).toBeDefined();
        });

        it('returns false for invalid action', () => {
            var result = writeLegacyHandoffIntent(getStorage, {
                action: 'invalid',
                selected_exam_id: 10
            }, 1000);
            expect(result).toBe(false);
        });

        it('returns false when storage is null', () => {
            var result = writeLegacyHandoffIntent(() => null, {
                action: 'start-exam',
                selected_exam_id: 10
            }, 1000);
            expect(result).toBe(false);
        });

        it('returns false for null intent', () => {
            var result = writeLegacyHandoffIntent(getStorage, null, 1000);
            expect(result).toBe(false);
        });
    });

    describe('consumeLegacyHandoffIntent', () => {
        it('consumes valid intent', () => {
            var now = Date.now();
            writeLegacyHandoffIntent(getStorage, {
                action: 'start-exam',
                selected_exam_id: 10,
                exam_token: 'TOKEN'
            }, now);

            var result = consumeLegacyHandoffIntent(getStorage, now + 1000);
            expect(result).not.toBeNull();
            expect(result.action).toBe('start-exam');
            expect(result.selected_exam_id).toBe(10);
            expect(result.exam_token).toBe('TOKEN');
        });

        it('returns null for expired intent', () => {
            var now = Date.now();
            writeLegacyHandoffIntent(getStorage, {
                action: 'start-exam',
                selected_exam_id: 10
            }, now);

            var result = consumeLegacyHandoffIntent(getStorage, now + LEGACY_HANDOFF_TTL_MS + 1000);
            expect(result).toBeNull();
        });

        it('removes intent after consuming', () => {
            writeLegacyHandoffIntent(getStorage, {
                action: 'start-exam',
                selected_exam_id: 10
            }, Date.now());

            consumeLegacyHandoffIntent(getStorage, Date.now());
            expect(storage[LEGACY_HANDOFF_STORAGE_KEY]).toBeUndefined();
        });

        it('returns null when no storage', () => {
            var result = consumeLegacyHandoffIntent(() => null, Date.now());
            expect(result).toBeNull();
        });

        it('returns null when no data stored', () => {
            var result = consumeLegacyHandoffIntent(getStorage, Date.now());
            expect(result).toBeNull();
        });
    });

    describe('clearLegacyHandoffIntent', () => {
        it('removes stored intent', () => {
            writeLegacyHandoffIntent(getStorage, {
                action: 'start-exam',
                selected_exam_id: 5
            }, Date.now());

            clearLegacyHandoffIntent(getStorage);
            expect(storage[LEGACY_HANDOFF_STORAGE_KEY]).toBeUndefined();
        });

        it('does not throw when no storage', () => {
            expect(() => clearLegacyHandoffIntent(() => null)).not.toThrow();
        });
    });
});
