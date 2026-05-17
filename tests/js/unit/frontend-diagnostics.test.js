import { describe, it, expect } from 'vitest';
import {
    createNoopFrontendDiagnosticsManager,
    createFrontendDiagnosticsManager
} from '../../../src/frontend/app/core/frontend-diagnostics';

describe('frontend-diagnostics', () => {
    describe('createNoopFrontendDiagnosticsManager', () => {
        it('returns disabled manager', () => {
            var mgr = createNoopFrontendDiagnosticsManager();
            expect(mgr.enabled).toBe(false);
        });

        it('all read methods return empty/defaults', () => {
            var mgr = createNoopFrontendDiagnosticsManager();
            expect(mgr.readErrors()).toEqual([]);
            expect(mgr.readRequestLogs()).toEqual([]);
            expect(mgr.readSnapshot()).toBeNull();
            expect(mgr.readSyncSnapshot()).toBeNull();
            expect(mgr.readTimeline()).toEqual([]);
            expect(mgr.readRenderStats()).toBeNull();
            expect(mgr.readActionTrail()).toEqual([]);
        });

        it('scenario methods return defaults', () => {
            var mgr = createNoopFrontendDiagnosticsManager();
            var scenario = mgr.readScenarioState();
            expect(scenario.forceOffline).toBe(false);
            expect(scenario.apiLatencyMs).toBe(0);
            expect(scenario.heartbeatScenario).toBe('off');
        });

        it('all record methods do not throw', () => {
            var mgr = createNoopFrontendDiagnosticsManager();
            expect(() => mgr.recordApiRequest({})).not.toThrow();
            expect(() => mgr.recordError('test', {})).not.toThrow();
            expect(() => mgr.recordRuntimeSnapshot({})).not.toThrow();
            expect(() => mgr.recordTimeline('test', 'summary')).not.toThrow();
            expect(() => mgr.recordRenderScheduled('test', {})).not.toThrow();
            expect(() => mgr.recordRenderPerformed('test', {})).not.toThrow();
            expect(() => mgr.recordActionTrail('test', 'summary')).not.toThrow();
        });

        it('consume methods return false/empty', () => {
            var mgr = createNoopFrontendDiagnosticsManager();
            expect(mgr.consumeFailNextApiRequest()).toBe(false);
            expect(mgr.consumeFailNextChunkLoad()).toBe(false);
            expect(mgr.consumeFailNextQuestionWindow()).toBe(false);
            expect(mgr.consumeFailFinishOnce()).toBe('');
            expect(mgr.consumeHeartbeatFailureOnce()).toBe(false);
        });

        it('getter methods return defaults', () => {
            var mgr = createNoopFrontendDiagnosticsManager();
            expect(mgr.getApiLatencyMs()).toBe(0);
            expect(mgr.getQuestionWindowLatencyMs()).toBe(0);
            expect(mgr.isForcedOffline()).toBe(false);
            expect(mgr.isPendingSyncForced()).toBe(false);
            expect(mgr.isAutoRetryDisabled()).toBe(false);
            expect(mgr.getHeartbeatScenario()).toBe('off');
        });

        it('pause methods work', () => {
            var mgr = createNoopFrontendDiagnosticsManager();
            expect(mgr.isCapturePaused()).toBe(false);
            expect(mgr.toggleCapturePaused()).toBe(false);
        });

        it('export returns noop bundle', () => {
            var mgr = createNoopFrontendDiagnosticsManager();
            var bundle = mgr.exportBundle();
            expect(bundle.enabled).toBe(false);
            expect(bundle.requestLogs).toEqual([]);
            expect(bundle.timeline).toEqual([]);
            expect(bundle.errors).toEqual([]);
        });

        it('clear methods do not throw', () => {
            var mgr = createNoopFrontendDiagnosticsManager();
            expect(() => mgr.clearAll()).not.toThrow();
            expect(() => mgr.clearErrors()).not.toThrow();
            expect(() => mgr.clearRequestLogs()).not.toThrow();
        });
    });

    describe('createFrontendDiagnosticsManager', () => {
        it('returns noop when disabled', () => {
            var mgr = createFrontendDiagnosticsManager({
                config: { frontendDiagnosticsEnabled: false },
                windowRef: {}
            });
            expect(mgr.enabled).toBe(false);
        });

        it('returns noop when no windowRef', () => {
            var mgr = createFrontendDiagnosticsManager({
                config: { frontendDiagnosticsEnabled: true },
                windowRef: null
            });
            expect(mgr.enabled).toBe(false);
        });

        it('returns noop when no localStorage', () => {
            var mgr = createFrontendDiagnosticsManager({
                config: { frontendDiagnosticsEnabled: true },
                windowRef: {}
            });
            expect(mgr.enabled).toBe(false);
        });

        it('returns enabled manager with localStorage', () => {
            var storage = {};
            var mgr = createFrontendDiagnosticsManager({
                config: { frontendDiagnosticsEnabled: true },
                windowRef: {
                    localStorage: {
                        getItem: (k) => storage[k] || null,
                        setItem: (k, v) => { storage[k] = v; },
                        removeItem: (k) => { delete storage[k]; }
                    }
                }
            });
            expect(mgr.enabled).toBe(true);
        });

        it('records and reads errors', () => {
            var storage = {};
            var mgr = createFrontendDiagnosticsManager({
                config: { frontendDiagnosticsEnabled: true },
                windowRef: {
                    localStorage: {
                        getItem: (k) => storage[k] || null,
                        setItem: (k, v) => { storage[k] = v; },
                        removeItem: (k) => { delete storage[k]; }
                    }
                }
            });
            mgr.recordError('test', { message: 'err' });
            var errors = mgr.readErrors();
            expect(errors.length).toBeGreaterThanOrEqual(1);
        });

        it('records and reads API requests', () => {
            var storage = {};
            var mgr = createFrontendDiagnosticsManager({
                config: { frontendDiagnosticsEnabled: true },
                windowRef: {
                    localStorage: {
                        getItem: (k) => storage[k] || null,
                        setItem: (k, v) => { storage[k] = v; },
                        removeItem: (k) => { delete storage[k]; }
                    }
                }
            });
            mgr.recordApiRequest({ url: '/api/test', status: 200 });
            var logs = mgr.readRequestLogs();
            expect(logs.length).toBe(1);
        });

        it('clearAll clears everything', () => {
            var storage = {};
            var mgr = createFrontendDiagnosticsManager({
                config: { frontendDiagnosticsEnabled: true },
                windowRef: {
                    localStorage: {
                        getItem: (k) => storage[k] || null,
                        setItem: (k, v) => { storage[k] = v; },
                        removeItem: (k) => { delete storage[k]; }
                    }
                }
            });
            mgr.recordError('test', {});
            mgr.clearAll();
            expect(mgr.readErrors()).toEqual([]);
        });

        it('pause toggle works', () => {
            var storage = {};
            var mgr = createFrontendDiagnosticsManager({
                config: { frontendDiagnosticsEnabled: true },
                windowRef: {
                    localStorage: {
                        getItem: (k) => storage[k] || null,
                        setItem: (k, v) => { storage[k] = v; },
                        removeItem: (k) => { delete storage[k]; }
                    }
                }
            });
            expect(mgr.isCapturePaused()).toBe(false);
            var paused = mgr.toggleCapturePaused();
            expect(paused).toBe(true);
            expect(mgr.isCapturePaused()).toBe(true);
        });

        it('export bundle returns enabled bundle', () => {
            var storage = {};
            var mgr = createFrontendDiagnosticsManager({
                config: { frontendDiagnosticsEnabled: true },
                windowRef: {
                    localStorage: {
                        getItem: (k) => storage[k] || null,
                        setItem: (k, v) => { storage[k] = v; },
                        removeItem: (k) => { delete storage[k]; }
                    }
                }
            });
            var bundle = mgr.exportBundle();
            expect(bundle.enabled).toBe(true);
            expect(bundle).toHaveProperty('exportedAt');
            expect(bundle).toHaveProperty('storageSummary');
        });
    });
});
