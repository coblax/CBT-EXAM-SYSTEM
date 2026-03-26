import { describe, expect, it } from 'vitest';
import { getFrontendConfig } from '../../../src/frontend/app/core/config.js';

describe('getFrontendConfig security idle settings', function () {
    it('defaults idle detection to enabled with a five minute threshold for existing installs', function () {
        var config = getFrontendConfig({
            CBTExamFrontendConfig: {
                securityLogEvents: 1
            }
        });

        expect(config.securityDetectIdle).toBe(true);
        expect(config.securityIdleThresholdMinutes).toBe(5);
        expect(config.securityIdleThresholdSeconds).toBe(300);
    });

    it('normalizes explicit idle detection values from localized frontend config', function () {
        var config = getFrontendConfig({
            CBTExamFrontendConfig: {
                securityDetectIdle: '0',
                securityIdleThresholdMinutes: '9',
                securityIdleThresholdSeconds: '540'
            }
        });

        expect(config.securityDetectIdle).toBe(false);
        expect(config.securityIdleThresholdMinutes).toBe(9);
        expect(config.securityIdleThresholdSeconds).toBe(540);
    });
});
