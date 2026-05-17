import { describe, it, expect, vi } from 'vitest';
import {
    createNoopFrontendDebugManager,
    createFrontendDebugManagerBridge
} from '../../../src/frontend/app/core/debug-manager';

describe('debug-manager', () => {
    describe('createNoopFrontendDebugManager', () => {
        it('returns object with all methods', () => {
            var noop = createNoopFrontendDebugManager();
            expect(noop.enabled).toBe(false);
            expect(typeof noop.mount).toBe('function');
            expect(typeof noop.refresh).toBe('function');
            expect(typeof noop.log).toBe('function');
            expect(typeof noop.logEvent).toBe('function');
        });

        it('methods do not throw', () => {
            var noop = createNoopFrontendDebugManager();
            expect(() => noop.mount()).not.toThrow();
            expect(() => noop.refresh()).not.toThrow();
            expect(() => noop.log('test', {})).not.toThrow();
            expect(() => noop.logEvent('test', 'event', {})).not.toThrow();
        });
    });

    describe('createFrontendDebugManagerBridge', () => {
        it('starts disabled', () => {
            var bridge = createFrontendDebugManagerBridge();
            expect(bridge.enabled).toBe(false);
        });

        it('delegates to implementation after setImplementation', () => {
            var bridge = createFrontendDebugManagerBridge();
            var mountCalled = false;
            bridge.setImplementation({
                enabled: true,
                mount: () => { mountCalled = true; },
                refresh: () => {},
                log: () => {},
                logEvent: () => {}
            });

            expect(bridge.enabled).toBe(true);
            bridge.mount();
            expect(mountCalled).toBe(true);
        });

        it('falls back to noop when setImplementation receives null', () => {
            var bridge = createFrontendDebugManagerBridge();
            bridge.setImplementation(null);
            expect(bridge.enabled).toBe(false);
            expect(() => bridge.mount()).not.toThrow();
        });

        it('log delegates to implementation', () => {
            var bridge = createFrontendDebugManagerBridge();
            var logArgs = [];
            bridge.setImplementation({
                enabled: true,
                mount: () => {},
                refresh: () => {},
                log: (kind, payload) => { logArgs.push({ kind, payload }); },
                logEvent: () => {}
            });

            bridge.log('test-kind', { data: 1 });
            expect(logArgs).toHaveLength(1);
            expect(logArgs[0].kind).toBe('test-kind');
        });

        it('logEvent delegates to implementation', () => {
            var bridge = createFrontendDebugManagerBridge();
            var eventArgs = [];
            bridge.setImplementation({
                enabled: true,
                mount: () => {},
                refresh: () => {},
                log: () => {},
                logEvent: (kind, event, extra) => { eventArgs.push({ kind, event, extra }); }
            });

            bridge.logEvent('security', 'focus-loss', { tab: 'hidden' });
            expect(eventArgs).toHaveLength(1);
            expect(eventArgs[0].kind).toBe('security');
        });

        it('refresh delegates to implementation', () => {
            var bridge = createFrontendDebugManagerBridge();
            var refreshCalled = false;
            bridge.setImplementation({
                enabled: true,
                mount: () => {},
                refresh: () => { refreshCalled = true; },
                log: () => {},
                logEvent: () => {}
            });

            bridge.refresh();
            expect(refreshCalled).toBe(true);
        });
    });
});
