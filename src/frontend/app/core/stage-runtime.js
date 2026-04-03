export function createStageRuntimeManager(deps) {
    var diagnosticsManager = deps.diagnosticsManager;
    var state = deps.state;
    var root = deps.root;
    var escapeHtml = deps.escapeHtml;
    var formatQuestionType = deps.formatQuestionType;
    var formatScoreValue = deps.formatScoreValue;
    var getChangedQuestionCount = deps.getChangedQuestionCount;
    var getQuestionRevisionMarkerCount = deps.getQuestionRevisionMarkerCount;
    var getEffectiveCalculatorPanelPosition = deps.getEffectiveCalculatorPanelPosition;
    var getEffectiveNavPanelPosition = deps.getEffectiveNavPanelPosition;
    var getExamFooterSyncMeta = deps.getExamFooterSyncMeta;
    var getExamProgressSummary = deps.getExamProgressSummary;
    var getNavigationQuestionEntries = deps.getNavigationQuestionEntries;
    var getQuestionCount = deps.getQuestionCount;
    var getQuestionDisplayNumber = deps.getQuestionDisplayNumber;
    var getQuestionIdAtIndex = deps.getQuestionIdAtIndex;
    var getQuestionPayloadById = deps.getQuestionPayloadById;
    var getSelectedExam = deps.getSelectedExam;
    var isCompactNavViewport = deps.isCompactNavViewport;
    var isCompactViewport = deps.isCompactViewport;
    var isExamAnswerEditingLocked = deps.isExamAnswerEditingLocked;
    var isQuestionAnswered = deps.isQuestionAnswered;
    var isQuestionChanged = deps.isQuestionChanged;
    var isQuestionRevisionMarked = deps.isQuestionRevisionMarked;
    var isQuestionDoubtful = deps.isQuestionDoubtful;
    var navigationQuestionFilterEmptyMessage = deps.navigationQuestionFilterEmptyMessage;
    var navigationQuestionTypeBadgeConfig = deps.navigationQuestionTypeBadgeConfig;
    var normalizeCalculatorPanelPosition = deps.normalizeCalculatorPanelPosition;
    var normalizeNavigationQuestionFilter = deps.normalizeNavigationQuestionFilter;
    var questionOptionKey = deps.questionOptionKey;
    var render = deps.render;
    var renderAlert = deps.renderAlert;
    var renderExamFullscreenPrompt = deps.renderExamFullscreenPrompt;
    var renderNavigationAnswerBadges = deps.renderNavigationAnswerBadges;
    var renderNavigationQuestionTypeBadge = deps.renderNavigationQuestionTypeBadge;
    var renderQuestionFontControls = deps.renderQuestionFontControls;
    var renderQuestionInput = deps.renderQuestionInput;
    var renderQuestionPrefetchIndicator = deps.renderQuestionPrefetchIndicator;
    var renderQuestionStem = deps.renderQuestionStem;
    var recordTimeline = deps.recordTimeline;
    var safeRichHtml = deps.safeRichHtml;

    var examStageRenderer = null;
    var examStageRendererPromise = null;
    var examStageLoadError = '';
    var resultStageRenderer = null;
    var resultStageRendererPromise = null;
    var resultStageLoadError = '';
    var calculatorFeature = null;
    var calculatorFeaturePromise = null;
    var calculatorFeatureLoading = false;
    var examStagePrefetched = false;
    var calculatorFeaturePrefetched = false;

    function formatLazyChunkErrorMessage(error, fallback) {
        var message = error instanceof Error && error.message ? error.message : '';
        if (message === '') {
            return fallback;
        }

        if (
            message.indexOf('Failed to fetch dynamically imported module') >= 0
            || message.indexOf('Importing a module script failed') >= 0
            || message.indexOf('fetch dynamically imported') >= 0
        ) {
            return fallback;
        }

        return message;
    }

    function recordTimelineEntry(kind, summary, meta) {
        if (typeof recordTimeline === 'function') {
            recordTimeline(kind, summary, meta || {});
        }
    }

    function isCalculatorEnabledForCurrentExam() {
        var selectedExam = getSelectedExam();
        if (!selectedExam || selectedExam.enable_calculator === undefined) {
            return true;
        }

        return Number(selectedExam.enable_calculator) !== 0;
    }

    function clearCalculatorRuntimeState() {
        state.calculatorVisible = false;
        state.calculatorExpression = '';
        state.calculatorResult = '';
        state.calculatorError = '';
    }

    function normalizeKkmPercentage(value) {
        var number = Number(value);
        if (!Number.isFinite(number)) {
            return 75;
        }
        return Math.max(0, Math.min(100, number));
    }

    function buildResultPassMeta(score, maxScore, rawKkm, rawIsPassed, rawPassLabel, rawResultTone) {
        var safeScore = Number.isFinite(Number(score)) ? Number(score) : 0;
        var safeMaxScore = Number.isFinite(Number(maxScore)) ? Math.max(0, Number(maxScore)) : 0;
        var kkmPercentage = normalizeKkmPercentage(rawKkm);
        var passingScore = safeMaxScore > 0 ? ((safeMaxScore * kkmPercentage) / 100) : 0;
        var explicitPassed = Number(rawIsPassed);
        var isPassed = Number.isFinite(explicitPassed)
            ? explicitPassed === 1
            : (safeMaxScore > 0 ? (safeScore + 0.0001 >= passingScore) : kkmPercentage <= 0);

        return {
            passLabel: rawPassLabel ? String(rawPassLabel) : (isPassed ? 'LULUS' : 'TIDAK LULUS'),
            passingScore: passingScore,
            resultTone: rawResultTone ? String(rawResultTone) : (isPassed ? 'pass' : 'fail')
        };
    }

    function maybeRejectChunkLoadByScenario(target) {
        if (
            diagnosticsManager
            && diagnosticsManager.enabled
            && typeof diagnosticsManager.consumeFailNextChunkLoad === 'function'
            && diagnosticsManager.consumeFailNextChunkLoad(target)
        ) {
            var error = new Error('Scenario aktif: fail next chunk load (' + target + ').');
            error.code = 'scenario_fail_next_chunk_load';
            error.isScenarioError = true;
            throw error;
        }
    }

    function buildStageRendererDeps() {
        return {
            FONT_SCALE_DEFAULT: deps.fontScaleDefault,
            FONT_SCALE_MAX: deps.fontScaleMax,
            FONT_SCALE_MIN: deps.fontScaleMin,
            NAV_QUESTION_FILTER_ANSWERED: deps.navQuestionFilterAnswered,
            NAV_QUESTION_FILTER_DOUBTFUL: deps.navQuestionFilterDoubtful,
            NAV_QUESTION_FILTER_UNANSWERED: deps.navQuestionFilterUnanswered,
            escapeHtml: escapeHtml,
            formatQuestionType: formatQuestionType,
            formatScoreValue: formatScoreValue,
            getChangedQuestionCount: getChangedQuestionCount,
            getQuestionRevisionMarkerCount: typeof getQuestionRevisionMarkerCount === 'function'
                ? getQuestionRevisionMarkerCount
                : function () {
                    return 0;
                },
            getEffectiveCalculatorPanelPosition: getEffectiveCalculatorPanelPosition,
            getEffectiveNavPanelPosition: getEffectiveNavPanelPosition,
            getExamFooterSyncMeta: getExamFooterSyncMeta,
            getExamProgressSummary: getExamProgressSummary,
            getNavigationQuestionEntries: getNavigationQuestionEntries,
            getQuestionCount: getQuestionCount,
            getQuestionDisplayNumber: getQuestionDisplayNumber,
            getQuestionIdAtIndex: getQuestionIdAtIndex,
            getQuestionPayloadById: getQuestionPayloadById,
            ensureQuestionWindowForIndex: deps.ensureQuestionWindowForIndex,
            getSelectedExam: getSelectedExam,
            isCompactNavViewport: isCompactNavViewport,
            isExamAnswerEditingLocked: isExamAnswerEditingLocked,
            isQuestionAnswered: typeof isQuestionAnswered === 'function'
                ? isQuestionAnswered
                : function () {
                    return false;
                },
            isQuestionChanged: typeof isQuestionChanged === 'function'
                ? isQuestionChanged
                : function () {
                    return false;
                },
            isQuestionRevisionMarked: typeof isQuestionRevisionMarked === 'function'
                ? isQuestionRevisionMarked
                : function () {
                    return false;
                },
            isQuestionDoubtful: typeof isQuestionDoubtful === 'function'
                ? isQuestionDoubtful
                : function () {
                    return false;
                },
            navigationQuestionFilterEmptyMessage: navigationQuestionFilterEmptyMessage,
            navigationQuestionTypeBadgeConfig: navigationQuestionTypeBadgeConfig,
            normalizeNavigationQuestionFilter: normalizeNavigationQuestionFilter,
            questionOptionKey: questionOptionKey,
            renderAlert: renderAlert,
            renderCalculatorPanel: renderCalculatorPanel,
            renderCalculatorToggleButton: renderCalculatorToggleButton,
            renderExamFullscreenPrompt: renderExamFullscreenPrompt,
            renderNavigationAnswerBadges: renderNavigationAnswerBadges,
            renderNavigationQuestionTypeBadge: renderNavigationQuestionTypeBadge,
            renderExamPartial: deps.renderExamPartial,
            renderQuestionFontControls: renderQuestionFontControls,
            renderQuestionInput: renderQuestionInput,
            renderQuestionPrefetchIndicator: renderQuestionPrefetchIndicator,
            renderQuestionStem: renderQuestionStem,
            refreshAttemptQuestionRevision: deps.refreshAttemptQuestionRevision,
            safeRichHtml: safeRichHtml,
            state: state
        };
    }

    function buildCalculatorFeatureDeps() {
        return {
            escapeHtml: escapeHtml,
            getEffectiveCalculatorPanelPosition: getEffectiveCalculatorPanelPosition,
            isCompactViewport: isCompactViewport,
            normalizeCalculatorPanelPosition: normalizeCalculatorPanelPosition,
            root: root,
            state: state
        };
    }

    function ensureExamStageRenderer(options) {
        options = options || {};

        if (examStageRenderer) {
            return Promise.resolve(examStageRenderer);
        }

        if (!examStageRendererPromise) {
            if (!options.prefetchOnly) {
                examStageLoadError = '';
            }

            recordTimelineEntry('chunk:exam:load:start', 'Memuat chunk exam.', {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || ''),
                target: 'exam'
            });

            examStageRendererPromise = Promise.resolve().then(function () {
                maybeRejectChunkLoadByScenario('exam');
                return import('../stages/exam.js');
            })
                .then(function (module) {
                    examStageRenderer = module.createExamStageRenderer(buildStageRendererDeps());
                    examStageLoadError = '';
                    recordTimelineEntry('chunk:exam:load:success', 'Chunk exam siap.', {
                        attemptId: Number(state.attemptId) || 0,
                        stage: String(state.stage || ''),
                        target: 'exam'
                    });
                    return examStageRenderer;
                })
                .catch(function (error) {
                    if (!options.prefetchOnly) {
                        examStageLoadError = formatLazyChunkErrorMessage(
                            error,
                            'Tampilan ujian gagal dimuat. Periksa koneksi lalu coba lagi.'
                        );
                    }
                    recordTimelineEntry('chunk:exam:load:error', examStageLoadError || 'Chunk exam gagal dimuat.', {
                        attemptId: Number(state.attemptId) || 0,
                        stage: String(state.stage || ''),
                        target: 'exam',
                        error: error instanceof Error ? {
                            message: String(error.message || ''),
                            code: String(error.code || '')
                        } : null
                    });
                    throw error;
                })
                .finally(function () {
                    examStageRendererPromise = null;
                });
        }

        if (options.renderOnResolve) {
            examStageRendererPromise.then(function () {
                render();
            }).catch(function () {
                render();
            });
        }

        return examStageRendererPromise;
    }

    function prefetchExamStageRenderer() {
        if (examStageRenderer || examStageRendererPromise || examStagePrefetched) {
            return;
        }

        examStagePrefetched = true;
        ensureExamStageRenderer({
            prefetchOnly: true
        }).catch(function () {
            examStagePrefetched = false;
        });
    }

    function ensureResultStageRenderer(options) {
        options = options || {};

        if (resultStageRenderer) {
            return Promise.resolve(resultStageRenderer);
        }

        if (!resultStageRendererPromise) {
            if (!options.prefetchOnly) {
                resultStageLoadError = '';
            }

            recordTimelineEntry('chunk:result:load:start', 'Memuat chunk result.', {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || ''),
                target: 'result'
            });

            resultStageRendererPromise = Promise.resolve().then(function () {
                maybeRejectChunkLoadByScenario('result');
                return import('../stages/result.js');
            })
                .then(function (module) {
                    resultStageRenderer = module.createResultStageRenderer(buildStageRendererDeps());
                    resultStageLoadError = '';
                    recordTimelineEntry('chunk:result:load:success', 'Chunk result siap.', {
                        attemptId: Number(state.attemptId) || 0,
                        stage: String(state.stage || ''),
                        target: 'result'
                    });
                    return resultStageRenderer;
                })
                .catch(function (error) {
                    if (!options.prefetchOnly) {
                        resultStageLoadError = formatLazyChunkErrorMessage(
                            error,
                            'Review hasil gagal dimuat. Nilai utama tetap tersedia.'
                        );
                    }
                    recordTimelineEntry('chunk:result:load:error', resultStageLoadError || 'Chunk result gagal dimuat.', {
                        attemptId: Number(state.attemptId) || 0,
                        stage: String(state.stage || ''),
                        target: 'result',
                        error: error instanceof Error ? {
                            message: String(error.message || ''),
                            code: String(error.code || '')
                        } : null
                    });
                    throw error;
                })
                .finally(function () {
                    resultStageRendererPromise = null;
                });
        }

        if (options.renderOnResolve) {
            resultStageRendererPromise.then(function () {
                render();
            }).catch(function () {
                render();
            });
        }

        return resultStageRendererPromise;
    }

    function prefetchResultStageRenderer() {
        ensureResultStageRenderer({
            prefetchOnly: true,
            renderOnResolve: state.stage === 'result'
        }).catch(function () {});
    }

    function ensureCalculatorFeature(options) {
        options = options || {};

        if (calculatorFeature) {
            return Promise.resolve(calculatorFeature);
        }

        if (!calculatorFeaturePromise) {
            calculatorFeatureLoading = true;

            recordTimelineEntry('chunk:calculator:load:start', 'Memuat chunk calculator.', {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || ''),
                target: 'calculator'
            });

            calculatorFeaturePromise = Promise.resolve().then(function () {
                maybeRejectChunkLoadByScenario('calculator');
                return import('../features/calculator.js');
            })
                .then(function (module) {
                    calculatorFeature = module.createCalculatorFeature(buildCalculatorFeatureDeps());
                    recordTimelineEntry('chunk:calculator:load:success', 'Chunk calculator siap.', {
                        attemptId: Number(state.attemptId) || 0,
                        stage: String(state.stage || ''),
                        target: 'calculator'
                    });
                    return calculatorFeature;
                })
                .catch(function (error) {
                    recordTimelineEntry('chunk:calculator:load:error', 'Chunk calculator gagal dimuat.', {
                        attemptId: Number(state.attemptId) || 0,
                        stage: String(state.stage || ''),
                        target: 'calculator',
                        error: error instanceof Error ? {
                            message: String(error.message || ''),
                            code: String(error.code || '')
                        } : null
                    });
                    throw error;
                })
                .finally(function () {
                    calculatorFeatureLoading = false;
                    calculatorFeaturePromise = null;
                });
        }

        if (options.renderOnResolve) {
            calculatorFeaturePromise.then(function () {
                render();
            }).catch(function () {
                render();
            });
        }

        return calculatorFeaturePromise;
    }

    function prefetchCalculatorFeature() {
        if (!isCalculatorEnabledForCurrentExam() || calculatorFeature || calculatorFeaturePromise || calculatorFeaturePrefetched) {
            return;
        }

        calculatorFeaturePrefetched = true;
        ensureCalculatorFeature({
            prefetchOnly: true
        }).catch(function () {
            calculatorFeaturePrefetched = false;
        });
    }

    function maybePrefetchExamRuntime() {
        if (state.stage !== 'confirm' || state.busy) {
            return;
        }

        if ((Number(state.selectedExamId) || 0) <= 0 && !getSelectedExam()) {
            return;
        }

        prefetchExamStageRenderer();
        if (isCalculatorEnabledForCurrentExam()) {
            prefetchCalculatorFeature();
        }
    }

    function renderCalculatorToggleButton(isOpen) {
        if (!isCalculatorEnabledForCurrentExam()) {
            return '';
        }

        var classes = ['cbt-icon-button', 'cbt-calculator-toggle'];
        if (isOpen) {
            classes.push('is-open');
        }
        if (calculatorFeatureLoading) {
            classes.push('is-loading');
        }

        var label = calculatorFeatureLoading
            ? 'Memuat kalkulator'
            : (isOpen ? 'Sembunyikan kalkulator' : 'Tampilkan kalkulator');

        return [
            '<button class="' + classes.join(' ') + '" data-action="toggle-calculator" type="button" aria-label="' + escapeHtml(label) + '" title="' + escapeHtml(label) + '"' + (calculatorFeatureLoading ? ' disabled' : '') + '>',
            '<span class="cbt-calculator-toggle-icon" aria-hidden="true">',
            '<span class="cbt-calculator-icon-glyph">',
            '<svg viewBox="0 0 24 24" focusable="false">',
            '<rect x="3" y="2" width="18" height="20" rx="2.8"></rect>',
            '<rect x="6.5" y="5.5" width="11" height="4.3" rx="1.1"></rect>',
            '<rect x="6.5" y="12" width="3.2" height="3.2" rx="0.6"></rect>',
            '<rect x="10.4" y="12" width="3.2" height="3.2" rx="0.6"></rect>',
            '<rect x="14.3" y="12" width="3.2" height="3.2" rx="0.6"></rect>',
            '<rect x="6.5" y="15.9" width="3.2" height="3.2" rx="0.6"></rect>',
            '<rect x="10.4" y="15.9" width="3.2" height="3.2" rx="0.6"></rect>',
            '<rect x="14.3" y="15.9" width="3.2" height="3.2" rx="0.6"></rect>',
            '</svg>',
            '</span>',
            '<span class="cbt-calculator-icon-close"></span>',
            '</span>',
            '<span class="cbt-visually-hidden">' + escapeHtml(label) + '</span>',
            '</button>'
        ].join('');
    }

    function renderCalculatorPanel() {
        if (!isCalculatorEnabledForCurrentExam()) {
            return '';
        }

        if (calculatorFeature) {
            return calculatorFeature.renderPanel();
        }

        if (calculatorFeatureLoading && state.calculatorVisible) {
            return [
                '<aside class="cbt-calc-panel" aria-hidden="false">',
                '<div class="cbt-calc-head">',
                '<div class="cbt-calc-head-title-wrap">',
                '<strong class="cbt-calc-head-title">KALKULATOR</strong>',
                '<p class="cbt-calc-head-subtitle">Memuat fitur kalkulator...</p>',
                '</div>',
                '</div>',
                '<div class="cbt-calc-display">',
                '<div class="cbt-calc-status">Memuat kalkulator...</div>',
                '</div>',
                '</aside>'
            ].join('');
        }

        return '<aside class="cbt-calc-panel is-hidden" aria-hidden="true"></aside>';
    }

    function renderExamStageShell() {
        if (examStageRenderer) {
            if (typeof examStageRenderer.renderExamStageShell === 'function') {
                return examStageRenderer.renderExamStageShell();
            }
            return examStageRenderer.renderExamStage();
        }

        if (!examStageRendererPromise && !examStageLoadError) {
            ensureExamStageRenderer({
                renderOnResolve: true
            }).catch(function () {});
        }

        if (examStageLoadError) {
            return [
                '<section class="cbt-card">',
                '<h3>Runtime Ujian Gagal Dimuat</h3>',
                '<p class="cbt-subtitle">' + escapeHtml(examStageLoadError) + '</p>',
                '<div class="cbt-actions">',
                '<button class="cbt-button cbt-button-primary" data-action="retry-load-exam-stage" type="button">Coba Lagi</button>',
                '<button class="cbt-button cbt-button-secondary" data-action="back-confirm" type="button">Kembali</button>',
                '</div>',
                renderAlert(),
                '</section>'
            ].join('');
        }

        return [
            '<section class="cbt-card">',
            '<h3>Memuat Tampilan Ujian</h3>',
            '<p class="cbt-subtitle">Komponen ujian sedang disiapkan. Mohon tunggu sebentar.</p>',
            renderAlert(),
            '</section>'
        ].join('');
    }

    function renderExamRegions() {
        if (!examStageRenderer || typeof examStageRenderer.renderExamRegions !== 'function') {
            return null;
        }

        return examStageRenderer.renderExamRegions();
    }

    function renderResultStageFallback(extraMessage, tone, includeRetry) {
        var selectedExam = getSelectedExam();
        var resultExam = state.result && state.result.exam && typeof state.result.exam === 'object' ? state.result.exam : null;
        var resultAttempt = state.result && state.result.attempt && typeof state.result.attempt === 'object' ? state.result.attempt : null;
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
        var passMeta = buildResultPassMeta(
            safeScore,
            safeMaxScore,
            state.result && state.result.kkm_percentage !== undefined
                ? state.result.kkm_percentage
                : (resultExam && resultExam.kkm_percentage !== undefined ? resultExam.kkm_percentage : (selectedExam && selectedExam.kkm_percentage)),
            state.result && state.result.is_passed !== undefined ? state.result.is_passed : undefined,
            state.result && state.result.pass_label ? state.result.pass_label : '',
            state.result && state.result.result_tone ? state.result.result_tone : ''
        );
        var fallbackNotice = extraMessage
            ? '<div class="cbt-alert cbt-alert-' + escapeHtml(tone || 'warning') + '">' + escapeHtml(extraMessage) + '</div>'
            : '';
        var retryButtonMarkup = includeRetry
            ? '<button class="cbt-button cbt-button-primary" data-action="retry-load-result-stage" type="button">Muat Review</button>'
            : '';

        return [
            '<div class="cbt-result-wrap">',
            '<section class="cbt-card cbt-result-card cbt-result-card--' + escapeHtml(passMeta.resultTone) + '">',
            '<div class="cbt-result-status-strip cbt-result-status-strip--' + escapeHtml(passMeta.resultTone) + '">',
            '<span class="cbt-result-status-label">' + escapeHtml(passMeta.passLabel) + '</span>',
            '<span class="cbt-result-status-subject">' + escapeHtml(subjectLabel) + '</span>',
            '</div>',
            '<div class="cbt-result-hero">',
            '<p class="cbt-result-kicker">SKOR AKHIR</p>',
            '<h3>' + escapeHtml(examTitle) + '</h3>',
            '<div class="cbt-score">' + escapeHtml(formatScoreValue(safeScore)) + '</div>',
            '<p class="cbt-subtitle">Batas lulus <strong>' + escapeHtml(formatScoreValue(passMeta.passingScore)) + '</strong> dari <strong>' + escapeHtml(formatScoreValue(safeMaxScore)) + '</strong> poin</p>',
            '</div>',
            '<div class="cbt-result-body">',
            fallbackNotice,
            renderAlert(),
            '<div class="cbt-actions cbt-result-actions">',
            retryButtonMarkup,
            '<button class="cbt-button cbt-button-secondary" data-action="back-confirm" type="button">Kembali ke Daftar Exam</button>',
            '<button class="cbt-button cbt-button-danger" data-action="logout" type="button">Logout</button>',
            '</div>',
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function renderResultStageShell() {
        if (resultStageRenderer) {
            return resultStageRenderer.renderResultStage();
        }

        if (!resultStageLoadError) {
            ensureResultStageRenderer({
                renderOnResolve: true
            }).catch(function () {});
        }

        if (resultStageLoadError) {
            return renderResultStageFallback(resultStageLoadError, 'warning', true);
        }

        return renderResultStageFallback('Memuat review jawaban...', 'info', false);
    }

    function retryLoadExamStage() {
        examStageLoadError = '';
        ensureExamStageRenderer({
            renderOnResolve: true
        }).catch(function () {});
        render();
    }

    function retryLoadResultStage() {
        resultStageLoadError = '';
        ensureResultStageRenderer({
            renderOnResolve: true
        }).catch(function () {});
        render();
    }

    function toggleCalculator() {
        if (state.stage !== 'exam') {
            return;
        }
        if (!isCalculatorEnabledForCurrentExam()) {
            if (state.calculatorVisible || state.calculatorExpression || state.calculatorResult || state.calculatorError) {
                clearCalculatorRuntimeState();
                render();
            }
            return;
        }
        if (state.calculatorVisible) {
            state.calculatorVisible = false;
            state.calculatorError = '';
            render();
            return;
        }

        state.calculatorVisible = true;
        state.calculatorError = '';
        render();
        ensureCalculatorFeature({
            renderOnResolve: true
        }).then(function (feature) {
            if (!state.calculatorVisible || !feature) {
                return;
            }
            feature.focusInput();
        }).catch(function (error) {
            state.calculatorVisible = false;
            state.notice = error instanceof Error && error.message
                ? ('Kalkulator gagal dimuat. ' + error.message)
                : 'Kalkulator gagal dimuat. Ujian tetap bisa dilanjutkan.';
            render();
        });
    }

    function handleCalculatorAction(action, actionNode) {
        if (state.stage !== 'exam' || !calculatorFeature || !isCalculatorEnabledForCurrentExam()) {
            return false;
        }

        var actionResult = calculatorFeature.handleAction(action, actionNode);
        if (!actionResult || !actionResult.handled) {
            return false;
        }

        if (actionResult.shouldRender) {
            render();
        }
        if (actionResult.focusInput) {
            calculatorFeature.focusInput();
        }

        return true;
    }

    function handleCalculatorInput(target) {
        if (target instanceof HTMLInputElement && calculatorFeature && isCalculatorEnabledForCurrentExam()) {
            calculatorFeature.handleInput(target);
        }
    }

    function handleCalculatorEnterKey() {
        if (!calculatorFeature || !isCalculatorEnabledForCurrentExam()) {
            return false;
        }

        var actionResult = calculatorFeature.handleEnterKey();
        if (actionResult && actionResult.shouldRender) {
            render();
        }
        if (actionResult && actionResult.focusInput) {
            calculatorFeature.focusInput();
        }

        return true;
    }

    return {
        ensureCalculatorFeature: ensureCalculatorFeature,
        ensureExamStageRenderer: ensureExamStageRenderer,
        ensureResultStageRenderer: ensureResultStageRenderer,
        clearCalculatorRuntimeState: clearCalculatorRuntimeState,
        handleCalculatorAction: handleCalculatorAction,
        handleCalculatorEnterKey: handleCalculatorEnterKey,
        handleCalculatorInput: handleCalculatorInput,
        maybePrefetchExamRuntime: maybePrefetchExamRuntime,
        prefetchCalculatorFeature: prefetchCalculatorFeature,
        prefetchExamStageRenderer: prefetchExamStageRenderer,
        prefetchResultStageRenderer: prefetchResultStageRenderer,
        renderCalculatorPanel: renderCalculatorPanel,
        renderCalculatorToggleButton: renderCalculatorToggleButton,
        renderExamRegions: renderExamRegions,
        renderExamStageShell: renderExamStageShell,
        renderResultStageShell: renderResultStageShell,
        retryLoadExamStage: retryLoadExamStage,
        retryLoadResultStage: retryLoadResultStage,
        toggleCalculator: toggleCalculator
    };
}
