import { escapeHtml } from '../core/html.js';
import { loadResultRendererModule } from '../core/dynamic-loader.js';
import { createAuthenticatedStageRuntime } from './authenticated-runtime.js';

export async function mountResultStage(context, options) {
    options = options || {};

    var resultStageRenderer = null;
    var resultStageRendererPromise = null;
    var resultStageLoadError = '';
    var resultStageLoading = false;

    var runtime = createAuthenticatedStageRuntime(context, {
        onAction: handleResultAction,
        renderResultStageShell: function (activeRuntime) {
            if (resultStageRenderer) {
                return resultStageRenderer.renderResultStage();
            }
            return renderResultFallback(activeRuntime, resultStageLoadError, resultStageLoading);
        },
        stage: 'result'
    });

    if (!runtime.state.token || !runtime.state.user) {
        await context.transitionTo('login', { reason: 'missing-auth-result' });
        return runtime;
    }

    if (Number(options.selectedExamId) > 0) {
        runtime.state.selectedExamId = Number(options.selectedExamId) || 0;
    }

    runtime.render('result-mount');
    await loadResult(runtime, options);

    return runtime;

    async function handleResultAction(action, target, activeRuntime, event) {
        if (action === 'back-confirm') {
            event.preventDefault();
            activeRuntime.persistAuthSession();
            await context.transitionTo('confirm', { reason: 'back-confirm' });
            return;
        }

        if (action === 'retry-load-result-stage') {
            event.preventDefault();
            resultStageLoadError = '';
            await ensureResultStageRenderer(activeRuntime, { force: true }).catch(function (error) {
                resultStageLoadError = error instanceof Error ? error.message : 'Gagal memuat tampilan hasil.';
            });
            activeRuntime.render('retry-load-result-stage');
            return;
        }

        if (action === 'retry-result') {
            event.preventDefault();
            await loadResult(activeRuntime, {
                selectedExamId: Number(activeRuntime.state.selectedExamId) || 0
            });
        }
    }

    async function loadResult(activeRuntime, loadOptions) {
        loadOptions = loadOptions || {};
        if (activeRuntime.state.busy) {
            return;
        }

        activeRuntime.clearMessages();

        if (Number(loadOptions.selectedExamId) > 0) {
            activeRuntime.state.selectedExamId = Number(loadOptions.selectedExamId) || 0;
        }

        var selectedExam = activeRuntime.getSelectedExam();
        if (!selectedExam && !activeRuntime.state.exams.length) {
            activeRuntime.state.busy = true;
            activeRuntime.updateResultProgress(
                14,
                1,
                'Memuat daftar ujian',
                'Kami sedang mengambil daftar ujian untuk menemukan attempt terakhir.',
                { render: false }
            );
            activeRuntime.render('result-load-exams');
            try {
                await activeRuntime.loadExams();
                selectedExam = activeRuntime.getSelectedExam();
            } catch (error) {
                activeRuntime.resetResultProgressState();
                activeRuntime.state.busy = false;
                activeRuntime.state.error = error instanceof Error ? error.message : 'Gagal memuat daftar ujian.';
                activeRuntime.render('result-load-exams-error');
                return;
            }
            activeRuntime.state.busy = false;
        }

        selectedExam = activeRuntime.getSelectedExam();
        if (!selectedExam) {
            activeRuntime.resetResultProgressState();
            activeRuntime.state.error = 'Pilih exam terlebih dahulu.';
            activeRuntime.render('result-missing-selection');
            return;
        }

        if (loadOptions.skipExamRefresh !== true) {
            activeRuntime.state.busy = true;
            activeRuntime.updateResultProgress(
                18,
                1,
                'Memperbarui status exam',
                'Kami sedang mengecek status attempt terbaru sebelum nilai ditampilkan.',
                { render: false }
            );
            activeRuntime.render('result-refresh-selection');

            try {
                var refreshedSelection = await activeRuntime.resolvePrimaryActionSelection('view-result');
                selectedExam = refreshedSelection && refreshedSelection.selectedExam
                    ? refreshedSelection.selectedExam
                    : null;
                activeRuntime.state.busy = false;

                if (!selectedExam) {
                    activeRuntime.resetResultProgressState();
                    activeRuntime.state.error = 'Exam yang dipilih sudah tidak tersedia.';
                    activeRuntime.render('result-missing-after-refresh');
                    return;
                }

                if (String(refreshedSelection && refreshedSelection.action ? refreshedSelection.action : '') === 'finalizing') {
                    activeRuntime.resetResultProgressState();
                    activeRuntime.state.notice = 'Hasil sedang diproses di background.';
                    activeRuntime.render('result-finalizing');
                    return;
                }

                if (String(refreshedSelection && refreshedSelection.action ? refreshedSelection.action : '') === 'start-exam') {
                    activeRuntime.resetResultProgressState();
                    await activeRuntime.handoffToLegacyStartExam({
                        reason: 'result-reroute-start-exam',
                        selectedExam: selectedExam,
                        skipExamRefresh: true
                    });
                    return;
                }
            } catch (error) {
                activeRuntime.resetResultProgressState();
                activeRuntime.state.busy = false;
                activeRuntime.state.error = error instanceof Error ? error.message : 'Gagal memperbarui status exam.';
                activeRuntime.render('result-refresh-error');
                return;
            }
        }

        var attemptId = Number(loadOptions.attemptId) || Number(selectedExam.latest_attempt_id) || 0;
        if (attemptId <= 0) {
            activeRuntime.resetResultProgressState();
            activeRuntime.state.error = 'Hasil ujian untuk exam ini belum tersedia.';
            activeRuntime.render('result-missing-attempt');
            return;
        }

        activeRuntime.state.busy = true;
        activeRuntime.updateResultProgress(
            46,
            2,
            'Mengambil hasil attempt',
            'Server sedang mengirim ringkasan nilai, status lulus, dan detail review jawaban.',
            { render: false }
        );
        activeRuntime.render('result-request');

        try {
            var reviewPayload = await activeRuntime.api('result', {
                query: {
                    attempt_id: attemptId
                }
            });
            activeRuntime.updateResultProgress(
                74,
                3,
                'Menyusun ringkasan nilai',
                'Kami sedang menghitung ulang skor aman, status lulus, dan ringkasan review yang akan ditampilkan.'
            );

            activeRuntime.state.result = activeRuntime.buildResultPayload(reviewPayload, selectedExam, attemptId);
            activeRuntime.state.stage = 'result';
            activeRuntime.state.error = '';
            activeRuntime.state.success = 'Menampilkan hasil ujian.';

            activeRuntime.updateResultProgress(
                92,
                4,
                'Menyiapkan halaman hasil',
                'Menyusun tampilan nilai akhir, progress jawaban, dan review soal untuk Anda.'
            );

            await ensureResultStageRenderer(activeRuntime).catch(function (error) {
                resultStageLoadError = error instanceof Error ? error.message : 'Gagal memuat tampilan hasil.';
            });
        } catch (error) {
            activeRuntime.state.error = error instanceof Error ? error.message : 'Gagal memuat hasil ujian.';
        } finally {
            activeRuntime.state.busy = false;
            activeRuntime.resetResultProgressState();
            activeRuntime.render('result-complete');
        }
    }

    function ensureResultStageRenderer(activeRuntime, rendererOptions) {
        rendererOptions = rendererOptions || {};
        if (resultStageRenderer && rendererOptions.force !== true) {
            return Promise.resolve(resultStageRenderer);
        }
        if (resultStageRendererPromise && rendererOptions.force !== true) {
            return resultStageRendererPromise;
        }

        resultStageLoading = true;
        resultStageRendererPromise = loadResultRendererModule()
            .then(function (module) {
                resultStageRenderer = module.createResultStageRenderer({
                    escapeHtml: escapeHtml,
                    formatQuestionType: activeRuntime.formatQuestionType,
                    formatScoreValue: activeRuntime.formatScoreValue,
                    getSelectedExam: activeRuntime.getSelectedExam,
                    questionOptionKey: activeRuntime.questionOptionKey,
                    renderAlert: activeRuntime.renderAlert,
                    safeRichHtml: activeRuntime.safeRichHtml,
                    state: activeRuntime.state
                });
                resultStageLoadError = '';
                return resultStageRenderer;
            })
            .catch(function (error) {
                resultStageRenderer = null;
                resultStageLoadError = error instanceof Error ? error.message : 'Gagal memuat tampilan hasil.';
                throw error;
            })
            .finally(function () {
                resultStageLoading = false;
                resultStageRendererPromise = null;
            });

        return resultStageRendererPromise;
    }
}

function renderResultFallback(runtime, loadError, loading) {
    var state = runtime.state;
    var result = state.result && typeof state.result === 'object' ? state.result : null;
    var selectedExam = runtime.getSelectedExam();
    var resultTitle = result && result.exam && result.exam.title
        ? result.exam.title
        : (selectedExam && selectedExam.title ? selectedExam.title : 'Hasil Ujian');
    var summaryMarkup = result
        ? [
            '<div class="cbt-result-summary">',
            '<div class="cbt-result-score-card">',
            '<span>Skor</span>',
            '<strong>' + escapeHtml(runtime.formatScoreValue(result.score) + ' / ' + runtime.formatScoreValue(result.max_score)) + '</strong>',
            '<small>' + escapeHtml(runtime.formatScoreValue(result.percentage)) + '%</small>',
            '</div>',
            '</div>'
        ].join('')
        : '<p class="cbt-subtitle">Hasil belum tersedia.</p>';
    var retryMarkup = loadError
        ? [
            '<div class="cbt-alert cbt-alert-warning">',
            '<div class="cbt-alert-copy">' + escapeHtml(loadError) + '</div>',
            '<button class="cbt-button cbt-button-secondary" data-action="retry-load-result-stage" type="button">Coba lagi</button>',
            '</div>'
        ].join('')
        : '';
    var loadingMarkup = loading
        ? '<div class="cbt-finish-live-card"><div class="cbt-finish-live-head"><span class="cbt-finish-live-spinner" aria-hidden="true"></span><div class="cbt-finish-live-copy"><strong>Memuat tampilan hasil</strong><span>Mohon tunggu sebentar.</span></div></div></div>'
        : '';

    return [
        '<section class="cbt-card cbt-result-stage">',
        '<div class="cbt-result-head">',
        '<div>',
        '<p class="cbt-confirm-kicker">Hasil Ujian</p>',
        '<h3>' + escapeHtml(resultTitle) + '</h3>',
        '</div>',
        '<button class="cbt-button cbt-button-secondary" data-action="back-confirm" type="button">Kembali</button>',
        '</div>',
        runtime.renderAlert(),
        summaryMarkup,
        retryMarkup,
        loadingMarkup,
        !result && !state.busy ? '<button class="cbt-button cbt-button-primary" data-action="retry-result" type="button">Muat ulang hasil</button>' : '',
        '</section>'
    ].join('');
}
