import { describe, it, expect, beforeEach } from 'vitest';
import { createBrowserStorageAccess } from '../../../src/frontend/app/core/browser-storage';

describe('browser-storage', () => {
    describe('with working storage', () => {
        var storage, access;

        beforeEach(() => {
            storage = {};
            access = createBrowserStorageAccess({
                sessionStorage: {
                    getItem: (key) => storage[key] || null,
                    setItem: (key, val) => { storage[key] = val; },
                    removeItem: (key) => { delete storage[key]; }
                },
                localStorage: {
                    getItem: (key) => storage[key] || null,
                    setItem: (key, val) => { storage[key] = val; },
                    removeItem: (key) => { delete storage[key]; }
                },
                indexedDB: {}
            });
        });

        it('getSessionStorage returns sessionStorage', () => {
            var ss = access.getSessionStorage();
            expect(ss).not.toBeNull();
        });

        it('getLocalStorage returns localStorage', () => {
            var ls = access.getLocalStorage();
            expect(ls).not.toBeNull();
        });

        it('getIndexedDb returns indexedDB', () => {
            var idb = access.getIndexedDb();
            expect(idb).not.toBeNull();
        });

        it('caches sessionStorage result', () => {
            var first = access.getSessionStorage();
            var second = access.getSessionStorage();
            expect(first).toBe(second);
        });

        it('caches localStorage result', () => {
            var first = access.getLocalStorage();
            var second = access.getLocalStorage();
            expect(first).toBe(second);
        });
    });

    describe('with null windowRef', () => {
        it('returns null for all storage', () => {
            var access = createBrowserStorageAccess(null);
            expect(access.getSessionStorage()).toBeNull();
            expect(access.getLocalStorage()).toBeNull();
            expect(access.getIndexedDb()).toBeNull();
        });
    });

    describe('with storage that throws', () => {
        it('returns null for sessionStorage on error', () => {
            var access = createBrowserStorageAccess({
                sessionStorage: {
                    setItem: () => { throw new Error('Quota exceeded'); },
                    removeItem: () => {}
                }
            });
            expect(access.getSessionStorage()).toBeNull();
        });

        it('returns null for localStorage on error', () => {
            var access = createBrowserStorageAccess({
                localStorage: {
                    setItem: () => { throw new Error('Quota exceeded'); },
                    removeItem: () => {}
                }
            });
            expect(access.getLocalStorage()).toBeNull();
        });
    });

    describe('with no storage properties', () => {
        it('returns null for missing sessionStorage', () => {
            var access = createBrowserStorageAccess({});
            expect(access.getSessionStorage()).toBeNull();
        });

        it('returns null for missing localStorage', () => {
            var access = createBrowserStorageAccess({});
            expect(access.getLocalStorage()).toBeNull();
        });

        it('returns null for missing indexedDB', () => {
            var access = createBrowserStorageAccess({});
            expect(access.getIndexedDb()).toBeNull();
        });
    });
});
