import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    ScatterController,
    Tooltip
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    ScatterController,
    Tooltip
);

const QUADRANT_STYLES = {
    mastery: {
        label: 'Mastery',
        backgroundColor: 'rgba(22, 163, 74, 0.78)',
        borderColor: '#15803d'
    },
    diligent: {
        label: 'Diligent',
        backgroundColor: 'rgba(37, 99, 235, 0.72)',
        borderColor: '#1d4ed8'
    },
    blind_guessing: {
        label: 'Blind Guessing',
        backgroundColor: 'rgba(245, 158, 11, 0.78)',
        borderColor: '#b45309'
    },
    struggling: {
        label: 'Struggling',
        backgroundColor: 'rgba(220, 38, 38, 0.72)',
        borderColor: '#b91c1c'
    }
};

function clampNumber(value, min, max) {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) {
        return min;
    }

    return Math.max(min, Math.min(max, parsed));
}

function formatPercent(value) {
    return clampNumber(value, 0, 100).toFixed(2) + '%';
}

function normalizeArray(value) {
    return Array.isArray(value) ? value : [];
}

export function readAnalyticsChartPayload(rootDocument = (typeof document !== 'undefined' ? document : null)) {
    if (!rootDocument || typeof rootDocument.getElementById !== 'function') {
        return {};
    }

    const node = rootDocument.getElementById('cbt-analytics-chart-data');
    if (!node) {
        return {};
    }

    try {
        const parsed = JSON.parse(node.textContent || '{}');
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (error) {
        return {};
    }
}

export function buildQuadrantChartConfig(quadrantPayload) {
    const points = normalizeArray(quadrantPayload && quadrantPayload.points);
    if (!quadrantPayload || quadrantPayload.status !== 'ok' || points.length === 0) {
        return null;
    }

    const medianDuration = clampNumber(quadrantPayload.duration_median_percent, 0, 200);
    const kkmPercentage = clampNumber(quadrantPayload.kkm_percentage, 0, 100);
    const maxPointX = points.reduce((max, point) => Math.max(max, clampNumber(point && point.x, 0, 200)), 0);
    const xMax = Math.max(100, Math.ceil(Math.max(maxPointX, medianDuration) / 20) * 20);
    const datasets = Object.keys(QUADRANT_STYLES).map((key) => {
        const style = QUADRANT_STYLES[key];
        return {
            label: style.label,
            data: points
                .filter((point) => String(point && point.quadrant ? point.quadrant : '') === key)
                .map((point) => ({
                    x: clampNumber(point.x, 0, 200),
                    y: clampNumber(point.y, 0, 100),
                    studentName: String(point.student_name || '-'),
                    studentKelas: String(point.student_kelas || '-'),
                    durationLabel: String(point.duration_label || '-'),
                    percentageDisplay: String(point.percentage_display || formatPercent(point.y)),
                    quadrantLabel: String(point.quadrant_label || style.label)
                })),
            backgroundColor: style.backgroundColor,
            borderColor: style.borderColor,
            pointRadius: 4,
            pointHoverRadius: 6
        };
    });

    datasets.push({
        type: 'line',
        label: 'Median Durasi',
        data: [
            { x: medianDuration, y: 0 },
            { x: medianDuration, y: 100 }
        ],
        borderColor: '#64748b',
        borderDash: [5, 5],
        borderWidth: 1.5,
        pointRadius: 0,
        fill: false
    });

    datasets.push({
        type: 'line',
        label: 'KKM',
        data: [
            { x: 0, y: kkmPercentage },
            { x: xMax, y: kkmPercentage }
        ],
        borderColor: '#0f4fa8',
        borderDash: [5, 5],
        borderWidth: 1.5,
        pointRadius: 0,
        fill: false
    });

    return {
        type: 'scatter',
        data: {
            datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            parsing: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const raw = context.raw || {};
                            if (!raw.studentName) {
                                return context.dataset.label + ': ' + formatPercent(raw.y);
                            }
                            return [
                                raw.studentName + ' (' + raw.studentKelas + ')',
                                'Nilai ' + raw.percentageDisplay,
                                'Durasi ' + raw.durationLabel,
                                raw.quadrantLabel
                            ];
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'linear',
                    min: 0,
                    max: xMax,
                    title: {
                        display: true,
                        text: String(quadrantPayload.x_axis_label || 'Durasi (% dari waktu ujian)')
                    },
                    ticks: {
                        callback(value) {
                            return value + '%';
                        }
                    }
                },
                y: {
                    min: 0,
                    max: 100,
                    title: {
                        display: true,
                        text: String(quadrantPayload.y_axis_label || 'Nilai (%)')
                    },
                    ticks: {
                        callback(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    };
}

export function buildBenchmarkChartConfig(benchmarkPayload) {
    const labels = normalizeArray(benchmarkPayload && benchmarkPayload.labels);
    if (!benchmarkPayload || benchmarkPayload.status !== 'ok' || labels.length === 0) {
        return null;
    }

    const globalCounts = normalizeArray(benchmarkPayload.global_counts).map((value) => Math.max(0, Number(value) || 0));
    const classCounts = normalizeArray(benchmarkPayload.class_counts).map((value) => Math.max(0, Number(value) || 0));
    const maxCount = Math.max(1, ...globalCounts, ...classCounts);

    return {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Global',
                    data: globalCounts,
                    backgroundColor: 'rgba(148, 163, 184, 0.48)',
                    borderColor: '#64748b',
                    borderWidth: 1
                },
                {
                    label: String(benchmarkPayload.selected_kelas || 'Kelas'),
                    data: classCounts,
                    backgroundColor: 'rgba(37, 99, 235, 0.66)',
                    borderColor: '#1d4ed8',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        afterBody() {
                            return [
                                'Global avg ' + String(benchmarkPayload.global_average_display || '0.00%'),
                                'Kelas avg ' + String(benchmarkPayload.class_average_display || '0.00%'),
                                'Delta ' + String(benchmarkPayload.delta_average_display || '0.00%')
                            ];
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: maxCount + 1,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    };
}

function getGaugeColor(value, status) {
    if (status === 'insufficient_data') {
        return '#f59e0b';
    }
    if (value >= 75) {
        return '#16a34a';
    }
    if (value >= 50) {
        return '#2563eb';
    }
    return '#dc2626';
}

export function buildPredictiveGaugeConfig(passRatePayload) {
    if (!passRatePayload || typeof passRatePayload !== 'object') {
        return null;
    }

    const predicted = clampNumber(passRatePayload.predicted_final_pass_rate, 0, 100);
    const status = String(passRatePayload.status || 'ok');

    return {
        type: 'doughnut',
        data: {
            labels: ['Prediksi Lulus', 'Belum Lulus'],
            datasets: [
                {
                    data: [predicted, Math.max(0, 100 - predicted)],
                    backgroundColor: [getGaugeColor(predicted, status), '#e5edf7'],
                    borderWidth: 0,
                    hoverOffset: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            rotation: 270,
            circumference: 180,
            cutout: '72%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label(context) {
                            return context.label + ': ' + formatPercent(context.parsed);
                        }
                    }
                },
                cbtGaugeCenterText: {
                    valueLabel: String(passRatePayload.predicted_final_pass_rate_display || formatPercent(predicted)),
                    subLabel: status === 'insufficient_data' ? 'Estimasi belum stabil' : 'Estimasi sementara'
                }
            }
        }
    };
}

const gaugeCenterTextPlugin = {
    id: 'cbtGaugeCenterText',
    afterDraw(chart) {
        const options = chart.options && chart.options.plugins && chart.options.plugins.cbtGaugeCenterText
            ? chart.options.plugins.cbtGaugeCenterText
            : null;
        if (!options) {
            return;
        }

        const ctx = chart.ctx;
        const area = chart.chartArea;
        if (!ctx || !area) {
            return;
        }

        const x = (area.left + area.right) / 2;
        const y = area.top + ((area.bottom - area.top) * 0.66);

        ctx.save();
        ctx.textAlign = 'center';
        ctx.fillStyle = '#0f172a';
        ctx.font = '700 24px sans-serif';
        ctx.fillText(String(options.valueLabel || '0.00%'), x, y);
        ctx.fillStyle = '#64748b';
        ctx.font = '600 12px sans-serif';
        ctx.fillText(String(options.subLabel || 'Estimasi sementara'), x, y + 22);
        ctx.restore();
    }
};

Chart.register(gaugeCenterTextPlugin);

function getCanvasContext(canvas) {
    if (!canvas || typeof canvas.getContext !== 'function') {
        return null;
    }

    try {
        return canvas.getContext('2d');
    } catch (error) {
        return null;
    }
}

function createChart(canvas, config) {
    const context = getCanvasContext(canvas);
    if (!context || !config) {
        return null;
    }

    return new Chart(context, config);
}

export function initAnalyticsCharts(rootDocument = (typeof document !== 'undefined' ? document : null)) {
    if (!rootDocument || typeof rootDocument.getElementById !== 'function') {
        return [];
    }

    const payload = readAnalyticsChartPayload(rootDocument);
    const charts = [];

    charts.push(createChart(
        rootDocument.getElementById('cbt-analytics-quadrant-chart'),
        buildQuadrantChartConfig(payload.behavioral_quadrant || {})
    ));
    charts.push(createChart(
        rootDocument.getElementById('cbt-analytics-benchmark-chart'),
        buildBenchmarkChartConfig(payload.benchmark_overlay || {})
    ));
    charts.push(createChart(
        rootDocument.getElementById('cbt-analytics-pass-gauge'),
        buildPredictiveGaugeConfig(payload.predictive_pass_rate || {})
    ));

    return charts.filter(Boolean);
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initAnalyticsCharts(document), { once: true });
    } else {
        initAnalyticsCharts(document);
    }
}
