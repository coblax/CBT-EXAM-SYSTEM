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

        it('reports durable storage health when IndexedDB is available', () => {
            var health = access.getStorageHealth();

            expect(health).toMatchObject({
                cacheApiAvailable: false,
                indexedDbAvailable: true,
                localAnswerStorageAvailable: true,
                localStorageAvailable: true,
                mode: 'durable',
                serviceWorkerControlled: false,
                sessionStorageAvailable: true,
                warningLevel: 'ok'
            });
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
            expect(access.getStorageHealth()).toMatchObject({
                localAnswerStorageAvailable: false,
                mode: 'memory_only',
                warningLevel: 'unsafe'
            });
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

        it('reports fallback health when only sessionStorage is usable', () => {
            var access = createBrowserStorageAccess({
                sessionStorage: {
                    setItem: () => {},
                    removeItem: () => {}
                }
            });

            expect(access.getStorageHealth()).toMatchObject({
                indexedDbAvailable: false,
                localAnswerStorageAvailable: true,
                localStorageAvailable: false,
                mode: 'fallback',
                sessionStorageAvailable: true,
                warningLevel: 'fallback'
            });
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

        it('reports Cache API and Service Worker controller availability', () => {
            var access = createBrowserStorageAccess({
                caches: {
                    open: () => Promise.resolve({})
                },
                navigator: {
                    serviceWorker: {
                        controller: {}
                    }
                }
            });

            expect(access.getStorageHealth()).toMatchObject({
                cacheApiAvailable: true,
                serviceWorkerControlled: true
            });
        });
    });
});
