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

    function buildResultBreakdown(totalQuestions, correctQuestions, wrongQuestions, unansweredQuestions, manualQuestions, gradedQuestions) {
        var normalizedTotal = Math.max(
            Number(totalQuestions) || 0,
            (Number(correctQuestions) || 0) +
            (Number(wrongQuestions) || 0) +
            (Number(gradedQuestions) || 0) +
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

        if ((Number(gradedQuestions) || 0) > 0) {
            segments.push({
                key: 'graded',
                label: 'Dinilai',
                value: Number(gradedQuestions) || 0,
                className: 'is-graded'
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

    function renderRestrictedResultStage(examTitle, submissionSummary) {
        var totalQuestions = submissionSummary ? Math.max(0, Number(submissionSummary.total_questions) || 0) : 0;
        var answeredQuestions = submissionSummary ? Math.max(0, Number(submissionSummary.answered_questions) || 0) : 0;
        var pendingManualQuestions = submissionSummary ? Math.max(0, Number(submissionSummary.pending_manual_questions) || 0) : 0;
        var restrictedLabel = pendingManualQuestions > 0 ? 'MENUNGGU KOREKSI' : 'HASIL BELUM DITAMPILKAN';
        var restrictedCopy = pendingManualQuestions > 0
            ? 'Masih ada jawaban yang menunggu koreksi guru sebelum hasil lengkap bisa ditampilkan.'
            : 'Hasil detail untuk exam ini belum ditampilkan oleh admin. Jawaban Anda tetap sudah tersimpan di sistem.';

        return [
            '<div class="cbt-result-wrap">',
            '<section class="cbt-card cbt-result-card cbt-result-card--restricted">',
            '<div class="cbt-result-status-strip cbt-result-status-strip--neutral">',
            '<span class="cbt-result-status-label">UJIAN SUDAH SELESAI</span>',
            '</div>',
            '<div class="cbt-result-hero cbt-result-hero--restricted">',
            '<h3>' + escapeHtml(examTitle) + '</h3>',
            '<div class="cbt-result-restricted-panel">',
            '<span class="cbt-result-restricted-kicker">' + escapeHtml(restrictedLabel) + '</span>',
            '<p class="cbt-result-restricted-copy">' + escapeHtml(restrictedCopy) + '</p>',
            '<div class="cbt-result-restricted-summary">JAWABAN TERSIMPAN : ' + escapeHtml(String(answeredQuestions)) + ' DARI ' + escapeHtml(String(totalQuestions)) + ' SOAL</div>',
            '</div>',
            '</div>',
            '<div class="cbt-result-body">',
            '<div class="cbt-actions cbt-result-actions">',
            '<button class="cbt-button cbt-button-secondary" data-action="back-confirm" type="button">KEMBALI KE DAFTAR EXAM</button>',
            '<button class="cbt-button cbt-button-danger" data-action="logout" type="button">LOGOUT</button>',
            '</div>',
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function renderResultStage() {
        var selectedExam = getSelectedExam();
        var resultExam = state.result && state.result.exam && typeof state.result.exam === 'object' ? state.result.exam : null;
        var resultAttempt = state.result && state.result.attempt && typeof state.result.attempt === 'object' ? state.result.attempt : null;
        var submissionSummary = state.result && state.result.submission_summary && typeof state.result.submission_summary === 'object'
            ? state.result.submission_summary
            : null;
        var reviewItems = state.result && Array.isArray(state.result.review_items) ? state.result.review_items : [];
        var reviewSummary = state.result && state.result.review_summary && typeof state.result.review_summary === 'object'
            ? state.result.review_summary
            : null;
        var examTitle = resultExam && resultExam.title
            ? String(resultExam.title)
            : (selectedExam && selectedExam.title ? String(selectedExam.title) : '-');
        var restrictedMode = Number(state.result && state.result.show_student_result !== undefined ? state.result.show_student_result : 1) !== 1
            || String(state.result && state.result.result_view_mode ? state.result.result_view_mode : '').toLowerCase() === 'restricted';
        if (restrictedMode) {
            return renderRestrictedResultStage(examTitle, submissionSummary);
        }
        var score = state.result && state.result.score !== undefined
            ? Number(state.result.score)
            : Number(resultAttempt && resultAttempt.score !== undefined ? resultAttempt.score : 0);
        var maxScore = state.result && state.result.max_score !== undefined
            ? Number(state.result.max_score)
            : Number(resultAttempt && resultAttempt.max_score !== undefined ? resultAttempt.max_score : 0);
        var safeScore = Number.isFinite(score) ? score : 0;
        var safeMaxScore = Number.isFinite(maxScore) ? maxScore : 0;
        var percentage = state.result && state.result.percentage !== undefined
            ? Number(state.result.percentage)
            : (safeMaxScore > 0 ? ((safeScore / safeMaxScore) * 100) : 0);
        var safePercentage = Number.isFinite(percentage) ? Math.max(0, percentage) : 0;
        var percentageValueText = formatScoreValue(safePercentage);
        var passingMeta = resolveResultPassMeta(safeScore, safeMaxScore, resultExam, selectedExam);
        var totalQuestions = reviewSummary
            ? Number(reviewSummary.total_questions) || reviewItems.length
            : (reviewItems.length || Number(resultExam && resultExam.total_questions !== undefined ? resultExam.total_questions : 0));
        var correctQuestions = reviewSummary ? Number(reviewSummary.correct_questions) || 0 : 0;
        var wrongQuestions = reviewSummary ? Number(reviewSummary.wrong_questions) || 0 : 0;
        var gradedQuestions = reviewSummary ? Number(reviewSummary.graded_questions) || 0 : 0;
        var unansweredQuestions = reviewSummary ? Number(reviewSummary.unanswered_questions) || 0 : 0;
        var manualQuestions = reviewSummary ? Number(reviewSummary.manual_questions) || 0 : 0;
        var pendingEssayQuestions = 0;
        var pendingEssayMaxPoints = 0;
        var pendingEssayInfoMarkup = '';
        reviewItems.forEach(function (item) {
            var status = String(item && item.status ? item.status : 'unanswered');
            var questionType = String(item && item.question_type ? item.question_type : '');
            var points = Number(item && item.points !== undefined ? item.points : 0);
            if (questionType === 'essay' && status === 'manual') {
                pendingEssayQuestions += 1;
                if (Number.isFinite(points) && points > 0) {
                    pendingEssayMaxPoints += points;
                }
            }
        });
        var breakdown = buildResultBreakdown(
            totalQuestions,
            correctQuestions,
            wrongQuestions,
            unansweredQuestions,
            manualQuestions,
            gradedQuestions
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
                '"><span class="cbt-result-progress-dot"></span><span class="cbt-result-progress-legend-label">' +
                escapeHtml(String(segment.label || '').toUpperCase()) +
                ' :</span><span class="cbt-result-progress-legend-value">' +
                escapeHtml(String(segment.value)) +
                ' SOAL</span>' +
                '</span>';
        }).join('');

        if (manualQuestions > 0) {
            var pendingEssayCount = pendingEssayQuestions > 0 ? pendingEssayQuestions : manualQuestions;
            pendingEssayInfoMarkup = [
                '<article class="cbt-result-pending-card">',
                '<div class="cbt-result-pending-head">',
                '<span class="cbt-result-pending-kicker">Menunggu Koreksi</span>',
                '<strong>' + escapeHtml(String(pendingEssayCount)) + ' SOAL ESAI</strong>',
                '</div>',
                '<div class="cbt-result-pending-meta">MAX POIN TAMBAHAN : ' + escapeHtml(formatScoreValue(pendingEssayMaxPoints)) + ' POIN</div>',
                '<p>Hasil ini masih sementara. Status lulus atau gagal masih bisa berubah setelah koreksi guru selesai.</p>',
                '</article>'
            ].join('');
        }

        return [
            '<div class="cbt-result-wrap">',
            '<section class="cbt-card cbt-result-card cbt-result-card--' + escapeHtml(passingMeta.resultTone) + '">',
            '<div class="cbt-result-status-strip cbt-result-status-strip--' + escapeHtml(passingMeta.resultTone) + '">',
            '<span class="cbt-result-status-label">' + escapeHtml(passingMeta.passLabel) + '</span>',
            '</div>',
            '<div class="cbt-result-hero">',
            '<h3>' + escapeHtml(examTitle) + '</h3>',
            '<div class="cbt-result-score-panel">',
            '<div class="cbt-score"><span class="cbt-score-value">' + escapeHtml(percentageValueText) + '</span></div>',
            '<p class="cbt-result-score-note">' +
            '<span class="cbt-result-score-metric-label">TOTAL POINT</span><span class="cbt-result-score-metric-colon">:</span><span class="cbt-result-score-metric-value">' + escapeHtml(formatScoreValue(safeMaxScore)) + ' POIN</span>' +
            '<span class="cbt-result-score-metric-label">PEROLEHAN</span><span class="cbt-result-score-metric-colon">:</span><span class="cbt-result-score-metric-value">' + escapeHtml(formatScoreValue(safeScore)) + ' POIN</span>' +
            '<span class="cbt-result-score-metric-label">BATAS LULUS</span><span class="cbt-result-score-metric-colon">:</span><span class="cbt-result-score-metric-value">' + escapeHtml(formatScoreValue(passingMeta.passingScore)) + ' POIN</span>' +
            '</p>',
            '<div class="cbt-result-progress-card">',
            '<div class="cbt-result-progress-track">',
            progressSegmentsMarkup,
            '</div>',
            '<div class="cbt-result-progress-meta">',
            '<div class="cbt-result-progress-legend-group">' + progressLegendMarkup + '</div>',
            '<span class="cbt-result-progress-pill">KKM ' + escapeHtml(formatScoreValue(passingMeta.kkmPercentage)) + '</span>',
            '</div>',
            '</div>',
            '</div>',
            '</div>',
            '<div class="cbt-result-body">',
            pendingEssayInfoMarkup,
            '<div class="cbt-actions cbt-result-actions">',
            '<button class="cbt-button cbt-button-secondary" data-action="back-confirm" type="button">KEMBALI KE DAFTAR EXAM</button>',
            '<button class="cbt-button cbt-button-danger" data-action="logout" type="button">LOGOUT</button>',
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
