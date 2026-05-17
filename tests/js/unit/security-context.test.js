import { describe, it, expect, beforeEach } from 'vitest';
import {
    getSecurityViewportWidth,
    getSecurityViewportHeight,
    detectSecurityDevicePlatform,
    detectSecurityDeviceType,
    detectSecurityInputMode,
    buildSecurityClientContext
} from '../../../src/frontend/app/core/security-context';

describe('security-context', () => {
    describe('getSecurityViewportWidth', () => {
        it('returns max of document and window width', () => {
            var win = { innerWidth: 1024 };
            var doc = { documentElement: { clientWidth: 1200 } };
            expect(getSecurityViewportWidth(win, doc)).toBe(1200);
        });

        it('returns 0 when both are null', () => {
            expect(getSecurityViewportWidth(null, null)).toBe(0);
        });

        it('handles missing documentElement', () => {
            var win = { innerWidth: 800 };
            expect(getSecurityViewportWidth(win, {})).toBe(800);
        });
    });

    describe('getSecurityViewportHeight', () => {
        it('returns max of document and window height', () => {
            var win = { innerHeight: 768 };
            var doc = { documentElement: { clientHeight: 900 } };
            expect(getSecurityViewportHeight(win, doc)).toBe(900);
        });

        it('returns 0 for null inputs', () => {
            expect(getSecurityViewportHeight(null, null)).toBe(0);
        });
    });

    describe('detectSecurityDevicePlatform', () => {
        it('detects android', () => {
            var win = { navigator: { userAgent: 'Mozilla/5.0 (Linux; Android 12)' } };
            expect(detectSecurityDevicePlatform(win)).toBe('android');
        });

        it('detects ios from iphone', () => {
            var win = { navigator: { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16)' } };
            expect(detectSecurityDevicePlatform(win)).toBe('ios');
        });

        it('detects windows', () => {
            var win = { navigator: { userAgent: 'Mozilla/5.0 (Windows NT 10.0)' } };
            expect(detectSecurityDevicePlatform(win)).toBe('windows');
        });

        it('detects macos', () => {
            var win = { navigator: { userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X)' } };
            expect(detectSecurityDevicePlatform(win)).toBe('macos');
        });

        it('detects linux', () => {
            var win = { navigator: { userAgent: 'Mozilla/5.0 (X11; Linux x86_64)' } };
            expect(detectSecurityDevicePlatform(win)).toBe('linux');
        });

        it('detects chromeos', () => {
            var win = { navigator: { userAgent: 'Mozilla/5.0 (X11; CrOS x86_64)' } };
            expect(detectSecurityDevicePlatform(win)).toBe('chromeos');
        });

        it('returns unknown for empty ua', () => {
            var win = { navigator: { userAgent: '' } };
            expect(detectSecurityDevicePlatform(win)).toBe('unknown');
        });

        it('returns unknown for null win', () => {
            expect(detectSecurityDevicePlatform(null)).toBe('unknown');
        });
    });

    describe('detectSecurityDeviceType', () => {
        it('detects tablet from ipad UA', () => {
            var win = { navigator: { userAgent: 'Mozilla/5.0 (iPad; CPU OS 16)' } };
            expect(detectSecurityDeviceType(win, {})).toBe('tablet');
        });

        it('detects mobile from iphone UA', () => {
            var win = { navigator: { userAgent: 'Mozilla/5.0 (iPhone; CPU OS 16)', maxTouchPoints: 5 } };
            expect(detectSecurityDeviceType(win, {})).toBe('mobile');
        });

        it('detects desktop for standard UA', () => {
            var win = { navigator: { userAgent: 'Mozilla/5.0 (Windows NT 10.0)', maxTouchPoints: 0 } };
            expect(detectSecurityDeviceType(win, {})).toBe('desktop');
        });

        it('detects mobile from userAgentData', () => {
            var win = { navigator: { userAgent: '', userAgentData: { mobile: true } } };
            expect(detectSecurityDeviceType(win, {})).toBe('mobile');
        });
    });

    describe('detectSecurityInputMode', () => {
        it('returns pointer when no touch', () => {
            var win = { navigator: { maxTouchPoints: 0 } };
            expect(detectSecurityInputMode(win)).toBe('pointer');
        });

        it('returns touch when touchPoints > 0', () => {
            var win = { navigator: { maxTouchPoints: 5 } };
            expect(detectSecurityInputMode(win)).toBe('touch');
        });
    });

    describe('buildSecurityClientContext', () => {
        it('builds context with all fields', () => {
            var win = {
                innerWidth: 1920,
                innerHeight: 1080,
                navigator: { userAgent: 'Mozilla/5.0 (Windows NT 10.0)', maxTouchPoints: 0 }
            };
            var doc = { documentElement: { clientWidth: 1920, clientHeight: 1080 } };
            var context = buildSecurityClientContext(win, doc, {});
            expect(context.device_type).toBe('desktop');
            expect(context.device_platform).toBe('windows');
            expect(context.input_mode).toBe('pointer');
            expect(context.viewport_width).toBe(1920);
            expect(context.viewport_height).toBe(1080);
        });

        it('preserves existing context values', () => {
            var win = { navigator: { userAgent: 'Android' }, innerWidth: 360, innerHeight: 640 };
            var doc = { documentElement: { clientWidth: 360, clientHeight: 640 } };
            var context = buildSecurityClientContext(win, doc, { device_type: 'custom' });
            expect(context.device_type).toBe('custom');
        });

        it('handles null baseContext', () => {
            var win = { navigator: { userAgent: '' }, innerWidth: 0, innerHeight: 0 };
            var context = buildSecurityClientContext(win, {}, null);
            expect(context).toBeDefined();
            expect(context.device_type).toBeDefined();
        });
    });
});
