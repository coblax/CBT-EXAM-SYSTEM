import { describe, expect, it, vi } from 'vitest';
import { registerServiceWorker } from '../../../src/frontend/app/core/service-worker-registration.js';

function baseConfig(overrides) {
    return Object.assign({
        frontendAssetSource: 'Production Build',
        frontendMode: 'student',
        serviceWorkerEnabled: true,
        serviceWorkerScope: '/cbt-ujian/',
        serviceWorkerUrl: '/?cbt_exam_sw=student&v=abc'
    }, overrides || {});
}

function createHarness(overrides) {
    var listeners = {};
    var serviceWorkerListeners = {};
    var register = vi.fn(function () {
        return Promise.resolve({ scope: '/cbt-ujian/' });
    });
    var windowRef = {
        addEventListener: vi.fn(function (eventName, callback) {
            listeners[eventName] = callback;
        }),
        CustomEvent: function (name, eventInit) {
            return {
                detail: eventInit && eventInit.detail ? eventInit.detail : {},
                type: name
            };
        },
        console: {
            warn: vi.fn()
        },
        document: {
            readyState: 'loading'
        },
        dispatchEvent: vi.fn(),
        navigator: {
            serviceWorker: {
                addEventListener: vi.fn(function (eventName, callback) {
                    serviceWorkerListeners[eventName] = callback;
                }),
                register: register
            }
        }
    };

    if (overrides && overrides.windowRef) {
        windowRef = Object.assign(windowRef, overrides.windowRef);
    }

    return {
        listeners: listeners,
        register: register,
        serviceWorkerListeners: serviceWorkerListeners,
        windowRef: windowRef
    };
}

describe('registerServiceWorker', function () {
    it('skips when disabled, unsupported, dev, or supervisor mode', function () {
        var disabled = createHarness();
        expect(registerServiceWorker(baseConfig({ serviceWorkerEnabled: false }), {
            windowRef: disabled.windowRef
        })).toBe(false);
        expect(disabled.register).not.toHaveBeenCalled();

        var unsupported = createHarness({
            windowRef: {
                navigator: {}
            }
        });
        expect(registerServiceWorker(baseConfig(), {
            windowRef: unsupported.windowRef
        })).toBe(false);

        var dev = createHarness();
        expect(registerServiceWorker(baseConfig({ frontendAssetSource: 'Vite Dev Server' }), {
            windowRef: dev.windowRef
        })).toBe(false);

        var supervisor = createHarness();
        expect(registerServiceWorker(baseConfig({ frontendMode: 'supervisor' }), {
            windowRef: supervisor.windowRef
        })).toBe(false);
    });

    it('registers configured service worker on window load', async function () {
        var harness = createHarness();

        expect(registerServiceWorker(baseConfig(), {
            windowRef: harness.windowRef
        })).toBe(true);

        expect(harness.windowRef.addEventListener).toHaveBeenCalledWith('load', expect.any(Function), { once: true });
        expect(harness.register).not.toHaveBeenCalled();

        await harness.listeners.load();

        expect(harness.register).toHaveBeenCalledWith('/?cbt_exam_sw=student&v=abc', {
            scope: '/cbt-ujian/'
        });
    });

    it('registers immediately when document is already complete', function () {
        var harness = createHarness({
            windowRef: {
                document: {
                    readyState: 'complete'
                }
            }
        });

        expect(registerServiceWorker(baseConfig(), {
            windowRef: harness.windowRef
        })).toBe(true);

        expect(harness.register).toHaveBeenCalledWith('/?cbt_exam_sw=student&v=abc', {
            scope: '/cbt-ujian/'
        });
        expect(harness.windowRef.addEventListener).not.toHaveBeenCalled();
    });

    it('logs registration failures without throwing', async function () {
        var harness = createHarness();
        var error = new Error('registration failed');
        harness.register.mockImplementation(function () {
            return Promise.reject(error);
        });

        expect(registerServiceWorker(baseConfig(), {
            windowRef: harness.windowRef
        })).toBe(true);

        await harness.listeners.load();

        expect(harness.windowRef.console.warn).toHaveBeenCalledWith(
            'CBT Service Worker registration failed:',
            error
        );
    });

    it('dispatches an app event when the service worker reports answer sync completion', function () {
        var harness = createHarness();

        expect(registerServiceWorker(baseConfig(), {
            windowRef: harness.windowRef
        })).toBe(true);

        harness.serviceWorkerListeners.message({
            data: {
                remaining: 2,
                type: 'CBT_SW_ANSWER_SYNC_COMPLETE'
            }
        });

        expect(harness.windowRef.dispatchEvent).toHaveBeenCalledWith({
            detail: {
                remaining: 2
            },
            type: 'cbt:sw-answer-sync-complete'
        });
    });
});
