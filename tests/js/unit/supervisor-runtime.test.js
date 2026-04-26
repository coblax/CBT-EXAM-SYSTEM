import { describe, expect, it } from 'vitest';
import {
    buildSupervisorDashboardCacheKey,
    normalizeSupervisorPercentValue
} from '../../../src/frontend/app/supervisor/runtime.js';

describe('normalizeSupervisorPercentValue', function () {
    it('uses numeric percentage values from the API', function () {
        expect(normalizeSupervisorPercentValue(72.5, '0%')).toBe(72.5);
    });

    it('parses localized Indonesian percentage labels as a fallback', function () {
        expect(normalizeSupervisorPercentValue(null, '50,00%')).toBe(50);
        expect(normalizeSupervisorPercentValue(undefined, '1.234,56%')).toBe(100);
    });

    it('clamps invalid and out of range values for progress widths', function () {
        expect(normalizeSupervisorPercentValue(null, 'belum')).toBe(0);
        expect(normalizeSupervisorPercentValue(-12, '50%')).toBe(0);
        expect(normalizeSupervisorPercentValue(125, '50%')).toBe(100);
    });
});

describe('buildSupervisorDashboardCacheKey', function () {
    it('keeps tab cache stable when query object order changes', function () {
        var first = buildSupervisorDashboardCacheKey({
            tab: 'action_required',
            exam_id: 8,
            action_page: 2,
            kelas: 'XI TKJ 1'
        });
        var second = buildSupervisorDashboardCacheKey({
            kelas: 'XI TKJ 1',
            action_page: 2,
            exam_id: 8,
            tab: 'action_required'
        });

        expect(first).toBe(second);
    });

    it('separates cache entries by active tab page', function () {
        expect(buildSupervisorDashboardCacheKey({
            tab: 'action_required',
            action_page: 1
        })).not.toBe(buildSupervisorDashboardCacheKey({
            tab: 'action_required',
            action_page: 2
        }));
    });
});
