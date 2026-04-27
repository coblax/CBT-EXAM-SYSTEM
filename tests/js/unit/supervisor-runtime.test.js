import { describe, expect, it } from 'vitest';
import {
    buildSupervisorDashboardCacheKey,
    normalizeSupervisorPercentValue,
    renderSupervisorSecurityTimelineSection
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

describe('renderSupervisorSecurityTimelineSection', function () {
    it('renders grouped timeline summary and severity tone', function () {
        var html = renderSupervisorSecurityTimelineSection({
            summary: {
                total_events: 3,
                warning_count: 1,
                critical_count: 2,
                risk_tone: 'high-risk',
                risk_label: 'High Risk',
                risk_score_label: '11',
                top_indicators: [
                    {
                        event_type: 'tab_hidden',
                        label: 'Pindah tab',
                        count: 2
                    }
                ]
            },
            items: [
                {
                    event_type: 'tab_hidden',
                    event_label: 'Pindah tab',
                    severity: 'critical',
                    message_display: 'Window ujian kehilangan fokus.',
                    device_summary: 'Desktop • Windows',
                    first_occurred_at: '2026-04-24 08:00:00',
                    last_occurred_at: '2026-04-24 08:01:00',
                    count: 2
                }
            ]
        }, []);

        expect(html).toContain('Security Timeline');
        expect(html).toContain('High Risk');
        expect(html).toContain('3 event');
        expect(html).toContain('Pindah tab');
        expect(html).toContain('x2');
        expect(html).toContain('is-critical');
    });

    it('renders empty state when attempt has no security event', function () {
        var html = renderSupervisorSecurityTimelineSection({
            summary: {
                total_events: 0,
                risk_tone: 'normal',
                risk_label: 'Normal',
                risk_score_label: '0'
            },
            items: []
        }, []);

        expect(html).toContain('Belum ada event security untuk attempt ini.');
        expect(html).toContain('Normal');
    });
});
