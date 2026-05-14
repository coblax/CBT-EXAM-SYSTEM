import { describe, expect, it } from 'vitest';
import { getFrontendConfig } from '../../../src/frontend/app/core/config.js';

describe('getFrontendConfig security settings', function () {
    it('defaults idle detection to enabled with a five minute threshold for existing installs', function () {
        var config = getFrontendConfig({
            CBTExamFrontendConfig: {
                securityLogEvents: 1
            }
        });

        expect(config.securityBlockBrowserInspectionShortcuts).toBe(false);
        expect(config.securityDetectScreenshotKeys).toBe(false);
        expect(config.securityShowExamWatermark).toBe(false);
        expect(config.securityExamWatermarkOpacity).toBe(0.07);
        expect(config.securityDetectIdle).toBe(true);
        expect(config.securityDetectHeartbeatLost).toBe(false);
        expect(config.securityIdleThresholdMinutes).toBe(5);
        expect(config.securityIdleThresholdSeconds).toBe(300);
    });

    it('normalizes explicit idle detection values from localized frontend config', function () {
        var config = getFrontendConfig({
            CBTExamFrontendConfig: {
                securityBlockBrowserInspectionShortcuts: '1',
                securityDetectScreenshotKeys: '1',
                securityShowExamWatermark: '1',
                securityExamWatermarkOpacity: '0.2',
                securityDetectIdle: '0',
                securityDetectHeartbeatLost: '1',
                securityIdleThresholdMinutes: '9',
                securityIdleThresholdSeconds: '540'
            }
        });

        expect(config.securityBlockBrowserInspectionShortcuts).toBe(true);
        expect(config.securityDetectScreenshotKeys).toBe(true);
        expect(config.securityShowExamWatermark).toBe(true);
        expect(config.securityExamWatermarkOpacity).toBe(0.12);
        expect(config.securityDetectIdle).toBe(false);
        expect(config.securityDetectHeartbeatLost).toBe(true);
        expect(config.securityIdleThresholdMinutes).toBe(9);
        expect(config.securityIdleThresholdSeconds).toBe(540);
    });

    it('clamps watermark opacity to the low-visibility safety range', function () {
        var low = getFrontendConfig({
            CBTExamFrontendConfig: {
                securityExamWatermarkOpacity: '0.01'
            }
        });
        var invalid = getFrontendConfig({
            CBTExamFrontendConfig: {
                securityExamWatermarkOpacity: 'not-a-number'
            }
        });

        expect(low.securityExamWatermarkOpacity).toBe(0.03);
        expect(invalid.securityExamWatermarkOpacity).toBe(0.07);
    });

    it('accepts comma decimal watermark opacity from localized config', function () {
        var config = getFrontendConfig({
            CBTExamFrontendConfig: {
                securityExamWatermarkOpacity: '0,09'
            }
        });

        expect(config.securityExamWatermarkOpacity).toBe(0.09);
    });

    it('normalizes supervisor mode and frontend urls from localized config', function () {
        var config = getFrontendConfig({
            CBTExamFrontendConfig: {
                frontendMode: 'SUPERVISOR',
                studentFrontendUrl: '/cbt-ujian/',
                supervisorFrontendUrl: '/pengawas/'
            }
        });

        expect(config.frontendMode).toBe('supervisor');
        expect(config.studentFrontendUrl).toBe('/cbt-ujian/');
        expect(config.supervisorFrontendUrl).toBe('/pengawas/');
    });

    it('normalizes service worker config from localized frontend config', function () {
        var config = getFrontendConfig({
            CBTExamFrontendConfig: {
                serviceWorkerBuildId: 12345,
                serviceWorkerEnabled: '1',
                serviceWorkerScope: ' /cbt-ujian/ ',
                serviceWorkerUrl: ' /?cbt_exam_sw=student&v=abc '
            }
        });

        expect(config.serviceWorkerEnabled).toBe(true);
        expect(config.serviceWorkerUrl).toBe('/?cbt_exam_sw=student&v=abc');
        expect(config.serviceWorkerScope).toBe('/cbt-ujian/');
        expect(config.serviceWorkerBuildId).toBe('12345');
    });
});
