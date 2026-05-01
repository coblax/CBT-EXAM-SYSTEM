import { describe, expect, it } from 'vitest';
import {
    buildBenchmarkChartConfig,
    buildPredictiveGaugeConfig,
    buildQuadrantChartConfig,
    initAnalyticsCharts,
    readAnalyticsChartPayload
} from '../../../src/admin/analytics-main.js';

describe('admin analytics chart helpers', function () {
    it('reads safe JSON chart payload from the page', function () {
        document.body.innerHTML = '<script type="application/json" id="cbt-analytics-chart-data">{"predictive_pass_rate":{"status":"ok"}}</script>';

        expect(readAnalyticsChartPayload(document)).toEqual({
            predictive_pass_rate: {
                status: 'ok'
            }
        });
    });

    it('returns empty payload when chart JSON is invalid', function () {
        document.body.innerHTML = '<script type="application/json" id="cbt-analytics-chart-data">{invalid</script>';

        expect(readAnalyticsChartPayload(document)).toEqual({});
    });

    it('builds a quadrant scatter config with four behavior datasets and guide lines', function () {
        const config = buildQuadrantChartConfig({
            status: 'ok',
            duration_median_percent: 50,
            kkm_percentage: 75,
            points: [
                { x: 25, y: 90, quadrant: 'mastery', student_name: 'A', student_kelas: 'X' },
                { x: 75, y: 90, quadrant: 'diligent', student_name: 'B', student_kelas: 'X' },
                { x: 25, y: 40, quadrant: 'blind_guessing', student_name: 'C', student_kelas: 'Y' },
                { x: 75, y: 40, quadrant: 'struggling', student_name: 'D', student_kelas: 'Y' }
            ]
        });

        expect(config.type).toBe('scatter');
        expect(config.data.datasets).toHaveLength(6);
        expect(config.data.datasets[0].data).toHaveLength(1);
        expect(config.options.scales.x.max).toBe(100);
    });

    it('builds benchmark and predictive gauge configs without requiring canvas', function () {
        const benchmark = buildBenchmarkChartConfig({
            status: 'ok',
            selected_kelas: 'X IPA 1',
            labels: ['0-19%', '20-39%'],
            global_counts: [2, 3],
            class_counts: [1, 4],
            global_average_display: '50.00%',
            class_average_display: '60.00%',
            delta_average_display: '+10.00%'
        });
        const gauge = buildPredictiveGaugeConfig({
            status: 'insufficient_data',
            predicted_final_pass_rate: 68,
            predicted_final_pass_rate_display: '68.00%'
        });

        expect(benchmark.type).toBe('bar');
        expect(benchmark.data.datasets[1].label).toBe('X IPA 1');
        expect(gauge.type).toBe('doughnut');
        expect(gauge.data.datasets[0].data).toEqual([68, 32]);
        expect(gauge.options.plugins.cbtGaugeCenterText.subLabel).toBe('Estimasi belum stabil');
    });

    it('does not crash when chart containers are not available', function () {
        document.body.innerHTML = '<script type="application/json" id="cbt-analytics-chart-data">{}</script>';

        expect(initAnalyticsCharts(document)).toEqual([]);
    });
});
