import { describe, it, expect, beforeEach } from 'vitest';
import { createFullscreenStateManager } from '../../../src/frontend/app/core/fullscreen-state';

describe('fullscreen-state', () => {
    var state, renderCalls, documentRef, windowRef, manager;

    beforeEach(() => {
        state = { isFullscreenActive: false };
        renderCalls = [];
        documentRef = {};
        windowRef = {};
        manager = createFullscreenStateManager({
            documentRef: documentRef,
            render: () => renderCalls.push(Date.now()),
            state: state,
            windowRef: windowRef
        });
    });

    describe('getFullscreenElement', () => {
        it('returns null when no fullscreen element', () => {
            expect(manager.getFullscreenElement()).toBeNull();
        });

        it('returns fullscreenElement when present', () => {
            documentRef.fullscreenElement = { id: 'test' };
            expect(manager.getFullscreenElement()).toEqual({ id: 'test' });
        });

        it('falls back to webkit prefix', () => {
            documentRef.webkitFullscreenElement = { id: 'webkit' };
            expect(manager.getFullscreenElement()).toEqual({ id: 'webkit' });
        });
    });

    describe('setNativeFullscreenActive', () => {
        it('sets fullscreen active with true', () => {
            var result = manager.setNativeFullscreenActive(true, false);
            expect(result).toBe(true);
        });

        it('returns false for null-like values', () => {
            var result = manager.setNativeFullscreenActive(undefined, false);
            expect(result).toBe(false);
        });

        it('accepts string boolean values', () => {
            expect(manager.setNativeFullscreenActive('true', false)).toBe(true);
            expect(manager.setNativeFullscreenActive('false', false)).toBe(true);
        });

        it('triggers render when shouldRender is true', () => {
            manager.setNativeFullscreenActive(true, true);
            expect(state.isFullscreenActive).toBe(true);
            expect(renderCalls.length).toBeGreaterThanOrEqual(1);
        });
    });

    describe('getNativeFullscreenState', () => {
        it('returns null when no bridge exists', () => {
            expect(manager.getNativeFullscreenState()).toBeNull();
        });

        it('reads from bridge isActive function', () => {
            windowRef.CBTNativeFullscreenBridge = { isActive: () => true };
            expect(manager.getNativeFullscreenState()).toBe(true);
        });

        it('reads from bridge active property', () => {
            windowRef.CBTNativeFullscreenBridge = { active: false };
            expect(manager.getNativeFullscreenState()).toBe(false);
        });

        it('reads from global override', () => {
            windowRef.__CBT_NATIVE_FULLSCREEN_ACTIVE__ = true;
            expect(manager.getNativeFullscreenState()).toBe(true);
        });
    });

    describe('syncFullscreenState', () => {
        it('updates state from document fullscreen', () => {
            documentRef.fullscreenElement = { id: 'test' };
            manager.syncFullscreenState(false);
            expect(state.isFullscreenActive).toBe(true);
        });

        it('does not render when shouldRender is false', () => {
            documentRef.fullscreenElement = { id: 'test' };
            manager.syncFullscreenState(false);
            expect(renderCalls.length).toBe(0);
        });

        it('renders when shouldRender is true and state changes', () => {
            documentRef.fullscreenElement = { id: 'test' };
            manager.syncFullscreenState(true);
            expect(renderCalls.length).toBe(1);
        });

        it('does not render when state has not changed', () => {
            manager.syncFullscreenState(true);
            expect(renderCalls.length).toBe(0);
        });
    });

    describe('requestNativeFullscreen', () => {
        it('resolves false when no bridge', async () => {
            var result = await manager.requestNativeFullscreen();
            expect(result).toBe(false);
        });

        it('calls bridge requestFullscreen', async () => {
            windowRef.CBTNativeFullscreenBridge = { requestFullscreen: () => true };
            var result = await manager.requestNativeFullscreen();
            expect(result).toBe(true);
        });
    });

    describe('exitNativeFullscreen', () => {
        it('resets state when no bridge', async () => {
            state.isFullscreenActive = true;
            manager.setNativeFullscreenActive(true, false);
            await manager.exitNativeFullscreen();
            expect(state.isFullscreenActive).toBe(false);
        });

        it('calls bridge exitFullscreen', async () => {
            windowRef.CBTNativeFullscreenBridge = { exitFullscreen: () => false };
            state.isFullscreenActive = true;
            await manager.exitNativeFullscreen();
            expect(state.isFullscreenActive).toBe(false);
        });
    });
});
