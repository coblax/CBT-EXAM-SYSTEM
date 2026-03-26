import { afterEach, beforeEach, vi } from 'vitest';

function createMemoryStorage() {
    var storage = new Map();

    return {
        get length() {
            return storage.size;
        },
        clear() {
            storage.clear();
        },
        getItem(key) {
            return storage.has(String(key)) ? storage.get(String(key)) : null;
        },
        key(index) {
            return Array.from(storage.keys())[Number(index)] || null;
        },
        removeItem(key) {
            storage.delete(String(key));
        },
        setItem(key, value) {
            storage.set(String(key), String(value));
        }
    };
}

beforeEach(() => {
    Object.defineProperty(globalThis, 'sessionStorage', {
        value: createMemoryStorage(),
        configurable: true
    });

    Object.defineProperty(globalThis, 'localStorage', {
        value: createMemoryStorage(),
        configurable: true
    });

    Object.defineProperty(globalThis, 'indexedDB', {
        value: undefined,
        configurable: true
    });
});

afterEach(() => {
    vi.restoreAllMocks();
});
