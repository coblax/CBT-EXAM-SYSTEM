import '../../styles/stage-result.css';
import { createReviewRenderer } from './review';

export function createResultStageRenderer(deps) {
    var state = deps.state;
    var getSelectedExam = deps.getSelectedExam;
    var escapeHtml = deps.escapeHtml;
    var formatScoreValue = deps.formatScoreValue;
    var renderAlert = deps.renderAlert;
    var reviewRenderer = createReviewRenderer(deps);

    function clampPercent(value) {
        var number = Number(value);
        if (!Number.isFinite(number)) {
            return 0;
        }
        return Math.max(0, Math.min(100, number));
    }

    function formatPercentStyle(value) {
        return clampPercent(value).toFixed(2).replace(/\.00$/, '');
    }

    function buildResultBreakdown(totalQuestions, correctQuestions, wrongQuestions, unansweredQuestions, manualQuestions) {
        var normalizedTotal = Math.max(
            Number(totalQuestions) || 0,
            (Number(correctQuestions) || 0) +
            (Number(wrongQuestions) || 0) +
            (Number(unansweredQuestions) || 0) +
            (Number(manualQuestions) || 0),
            1
        );
        var segments = [];

        if ((Number(correctQuestions) || 0) > 0) {
            segments.push({
                key: 'correct',
                label: 'Benar',
                value: Number(correctQuestions) || 0,
                className: 'is-correct'
            });
        }

        if ((Number(wrongQuestions) || 0) > 0) {
            segments.push({
                key: 'wrong',
                label: 'Salah',
                value: Number(wrongQuestions) || 0,
                className: 'is-wrong'
            });
        }

        if ((Number(unansweredQuestions) || 0) > 0) {
            segments.push({
                key: 'unanswered',
                label: 'Tdk Dijawab',
                value: Number(unansweredQuestions) || 0,
                className: 'is-unanswered'
            });
        }

        if ((Number(manualQuestions) || 0) > 0) {
            segments.push({
                key: 'pending',
                label: 'Menunggu Koreksi',
                value: Number(manualQuestions) || 0,
                className: 'is-pending'
            });
        }

        if (!segments.length) {
            segments.push({
                key: 'empty',
                label: 'Belum Ada Jawaban',
                value: normalizedTotal,
                className: 'is-empty'
            });
        }

        return {
            normalizedTotal: normalizedTotal,
            segments: segments
        };
    }

    function resolveResultPassMeta(score, maxScore, resultExam, selectedExam) {
        var rootResult = state.result && typeof state.result === 'object' ? state.result : null;
        var rawKkm = rootResult && rootResult.kkm_percentage !== undefined
            ? Number(rootResult.kkm_percentage)
            : Number(resultExam && resultExam.kkm_percentage !== undefined
                ? resultExam.kkm_percentage
                : (selectedExam && selectedExam.kkm_percentage !== undefined ? selectedExam.kkm_percentage : 75));
        var safeKkm = Number.isFinite(rawKkm) ? Math.max(0, Math.min(100, rawKkm)) : 75;
        var passingScore = rootResult && rootResult.passing_score !== undefined
            ? Number(rootResult.passing_score)
            : (maxScore > 0 ? ((maxScore * safeKkm) / 100) : 0);
        var safePassingScore = Number.isFinite(passingScore) ? Math.max(0, passingScore) : 0;
        var explicitPassed = rootResult && rootResult.is_passed !== undefined
            ? Number(rootResult.is_passed)
            : Number.NaN;
        var isPassed = Number.isFinite(explicitPassed)
            ? explicitPassed === 1
            : (maxScore > 0 ? (score + 0.0001 >= safePassingScore) : safeKkm <= 0);
        var resultTone = rootResult && rootResult.result_tone
            ? String(rootResult.result_tone)
            : (isPassed ? 'pass' : 'fail');
        var passLabel = rootResult && rootResult.pass_label
            ? String(rootResult.pass_label)
            : (isPassed ? 'LULUS' : 'TIDAK LULUS');

        return {
            isPassed: isPassed,
            kkmPercentage: safeKkm,
            passLabel: passLabel,
            passingScore: safePassingScore,
            resultTone: resultTone === 'pass' ? 'pass' : 'fail'
        };
    }

    function renderResultStage() {
        var selectedExam = getSelectedExam();
        var resultExam = state.result && state.result.exam && typeof state.result.exam === 'object' ? state.result.exam : null;
        var resultAttempt = state.result && state.result.attempt && typeof state.result.attempt === 'object' ? state.result.attempt : null;
        var reviewItems = state.result && Array.isArray(state.result.review_items) ? state.result.review_items : [];
        var reviewSummary = state.result && state.result.review_summary && typeof state.result.review_summary === 'object'
            ? state.result.review_summary
            : null;
        var examTitle = resultExam && resultExam.title
            ? String(resultExam.title)
            : (selectedExam && selectedExam.title ? String(selectedExam.title) : '-');
        var subjectLabel = resultExam && resultExam.subject_name
            ? String(resultExam.subject_name)
            : (selectedExam && selectedExam.subject_name ? String(selectedExam.subject_name) : examTitle);

        var score = state.result && state.result.score !== undefined
            ? Number(state.result.score)
            : Number(resultAttempt && resultAttempt.score !== undefined ? resultAttempt.score : 0);
        var maxScore = state.result && state.result.max_score !== undefined
            ? Number(state.result.max_score)
            : Number(resultAttempt && resultAttempt.max_score !== undefined ? resultAttempt.max_score : 0);
        var safeScore = Number.isFinite(score) ? score : 0;
        var safeMaxScore = Number.isFinite(maxScore) ? maxScore : 0;
        var scoreValueText = formatScoreValue(safeScore);
        var passingMeta = resolveResultPassMeta(safeScore, safeMaxScore, resultExam, selectedExam);
        var totalQuestions = reviewSummary
            ? Number(reviewSummary.total_questions) || reviewItems.length
            : (reviewItems.length || Number(resultExam && resultExam.total_questions !== undefined ? resultExam.total_questions : 0));
        var correctQuestions = reviewSummary ? Number(reviewSummary.correct_questions) || 0 : 0;
        var wrongQuestions = reviewSummary ? Number(reviewSummary.wrong_questions) || 0 : 0;
        var unansweredQuestions = reviewSummary ? Number(reviewSummary.unanswered_questions) || 0 : 0;
        var manualQuestions = reviewSummary ? Number(reviewSummary.manual_questions) || 0 : 0;
        var pendingEssayInfoMarkup = '';
        var breakdown = buildResultBreakdown(
            totalQuestions,
            correctQuestions,
            wrongQuestions,
            unansweredQuestions,
            manualQuestions
        );
        var progressSegmentsMarkup = breakdown.segments.map(function (segment) {
            return '<span class="cbt-result-progress-segment ' +
                escapeHtml(segment.className) +
                '" style="width:' +
                escapeHtml(formatPercentStyle((segment.value / breakdown.normalizedTotal) * 100)) +
                '%;" title="' +
                escapeHtml(segment.label + ': ' + segment.value) +
                '"></span>';
        }).join('');
        var progressLegendMarkup = breakdown.segments.map(function (segment) {
            return '<span class="cbt-result-progress-legend ' +
                escapeHtml(segment.className) +
                '"><span class="cbt-result-progress-dot"></span>' +
                escapeHtml(segment.label) +
                ' ' +
                escapeHtml(String(segment.value)) +
                ' soal' +
                '</span>';
        }).join('');

        if (manualQuestions > 0) {
            pendingEssayInfoMarkup = [
                '<article class="cbt-result-pending-card">',
                '<div class="cbt-result-pending-head">',
                '<span class="cbt-result-pending-kicker">Menunggu Koreksi</span>',
                '<strong>' + escapeHtml(String(manualQuestions)) + ' soal esai</strong>',
                '</div>',
                '<p>Hasil ini masih sementara. Status lulus atau gagal masih bisa berubah setelah koreksi guru selesai.</p>',
                '</article>'
            ].join('');
        }

        return [
            '<div class="cbt-result-wrap">',
            '<section class="cbt-card cbt-result-card cbt-result-card--' + escapeHtml(passingMeta.resultTone) + '">',
            '<div class="cbt-result-status-strip cbt-result-status-strip--' + escapeHtml(passingMeta.resultTone) + '">',
            '<span class="cbt-result-status-label">' + escapeHtml(passingMeta.passLabel) + '</span>',
            '<span class="cbt-result-status-subject">' + escapeHtml(subjectLabel) + '</span>',
            '</div>',
            '<div class="cbt-result-hero">',
            '<p class="cbt-result-kicker">SKOR AKHIR</p>',
            '<h3>' + escapeHtml(examTitle) + '</h3>',
            '<div class="cbt-result-score-panel">',
            '<div class="cbt-score">' + escapeHtml(scoreValueText) + '</div>',
            '<p class="cbt-result-score-note">Dari ' + escapeHtml(formatScoreValue(safeMaxScore)) + ' poin · Batas lulus ' + escapeHtml(formatScoreValue(passingMeta.passingScore)) + ' poin</p>',
            '<div class="cbt-result-progress-card">',
            '<div class="cbt-result-progress-track">',
            progressSegmentsMarkup,
            '</div>',
            '<div class="cbt-result-progress-meta">',
            '<div class="cbt-result-progress-legend-group">' + progressLegendMarkup + '</div>',
            '<span class="cbt-result-progress-pill">KKM ' + escapeHtml(formatScoreValue(passingMeta.kkmPercentage)) + '%</span>',
            '</div>',
            '</div>',
            '</div>',
            '</div>',
            '<div class="cbt-result-body">',
            pendingEssayInfoMarkup,
            renderAlert(),
            '<div class="cbt-result-stat-grid">',
            '<article class="cbt-result-stat is-correct"><strong>' + escapeHtml(String(correctQuestions)) + '</strong><span>BENAR</span></article>',
            '<article class="cbt-result-stat is-wrong"><strong>' + escapeHtml(String(wrongQuestions)) + '</strong><span>SALAH</span></article>',
            '<article class="cbt-result-stat is-unanswered"><strong>' + escapeHtml(String(unansweredQuestions)) + '</strong><span>TDK DIJAWAB</span></article>',
            '</div>',
            '<div class="cbt-result-detail-card">',
            '<div class="cbt-result-detail-row"><span>MATA UJIAN</span><strong>' + escapeHtml(subjectLabel) + '</strong></div>',
            '<div class="cbt-result-detail-row"><span>TOTAL SOAL</span><strong>' + escapeHtml(String(totalQuestions)) + ' soal</strong></div>',
            '<div class="cbt-result-detail-row"><span>BATAS LULUS</span><strong>' + escapeHtml(formatScoreValue(passingMeta.passingScore)) + ' poin (' + escapeHtml(formatScoreValue(passingMeta.kkmPercentage)) + '%)</strong></div>',
            '</div>',
            '<div class="cbt-actions cbt-result-actions">',
            '<button class="cbt-button cbt-button-secondary" data-action="back-confirm" type="button">Kembali ke Daftar Exam</button>',
            '<button class="cbt-button cbt-button-danger" data-action="logout" type="button">Logout</button>',
            '</div>',
            '</div>',
            '</section>',
            reviewRenderer.renderResultReviewSection(),
            '</div>'
        ].join('');
    }

    return {
        renderResultStage: renderResultStage
    };
}
