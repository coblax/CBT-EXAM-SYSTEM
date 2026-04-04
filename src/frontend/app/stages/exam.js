import '../../styles/stage-exam.css';
import { createReviewRenderer } from './review';

export function createExamStageRenderer(deps) {
    var OPENING_PROGRESS_STEP_LABELS = [
        'Sesi',
        'Runtime',
        'Data Lokal',
        'Soal Awal',
        'Finalisasi'
    ];
    var state = deps.state;
    var escapeHtml = deps.escapeHtml;
    var renderAlert = deps.renderAlert;
    var renderQuestionPrefetchIndicator = deps.renderQuestionPrefetchIndicator;
    var renderQuestionFontControls = deps.renderQuestionFontControls;
    var renderQuestionStem = deps.renderQuestionStem;
    var renderQuestionInput = deps.renderQuestionInput;
    var renderNavigationAnswerBadges = deps.renderNavigationAnswerBadges;
    var renderNavigationQuestionTypeBadge = deps.renderNavigationQuestionTypeBadge;
    var renderExamFullscreenPrompt = deps.renderExamFullscreenPrompt;
    var renderCalculatorToggleButton = deps.renderCalculatorToggleButton;
    var renderCalculatorPanel = deps.renderCalculatorPanel;
    var formatScoreValue = deps.formatScoreValue;
    var formatQuestionType = deps.formatQuestionType;
    var getSelectedExam = deps.getSelectedExam;
    var getQuestionCount = deps.getQuestionCount;
    var getQuestionIdAtIndex = deps.getQuestionIdAtIndex;
    var getQuestionManifestById = typeof deps.getQuestionManifestById === 'function'
        ? deps.getQuestionManifestById
        : function () {
            return null;
        };
    var getQuestionPayloadById = deps.getQuestionPayloadById;
    var ensureQuestionWindowForIndex = deps.ensureQuestionWindowForIndex;
    var refreshAttemptQuestionRevision = deps.refreshAttemptQuestionRevision;
    var getQuestionDisplayNumber = deps.getQuestionDisplayNumber;
    var getExamProgressSummary = deps.getExamProgressSummary;
    var getChangedQuestionCount = typeof deps.getChangedQuestionCount === 'function'
        ? deps.getChangedQuestionCount
        : function () {
            return 0;
        };
    var getQuestionRevisionMarkerCount = typeof deps.getQuestionRevisionMarkerCount === 'function'
        ? deps.getQuestionRevisionMarkerCount
        : function () {
            return 0;
        };
    var getEffectiveNavPanelPosition = deps.getEffectiveNavPanelPosition;
    var getEffectiveCalculatorPanelPosition = deps.getEffectiveCalculatorPanelPosition;
    var getNavigationQuestionEntries = deps.getNavigationQuestionEntries;
    var getQuestionSaveFeedback = typeof deps.getQuestionSaveFeedback === 'function'
        ? deps.getQuestionSaveFeedback
        : function () {
            return null;
        };
    var normalizeNavigationQuestionFilter = deps.normalizeNavigationQuestionFilter;
    var navigationQuestionFilterEmptyMessage = deps.navigationQuestionFilterEmptyMessage;
    var navigationQuestionTypeBadgeConfig = deps.navigationQuestionTypeBadgeConfig;
    var isQuestionAnswered = typeof deps.isQuestionAnswered === 'function'
        ? deps.isQuestionAnswered
        : function () {
            return false;
        };
    var isQuestionDoubtful = typeof deps.isQuestionDoubtful === 'function'
        ? deps.isQuestionDoubtful
        : function () {
            return false;
        };
    var isQuestionChanged = typeof deps.isQuestionChanged === 'function'
        ? deps.isQuestionChanged
        : function () {
            return false;
        };
    var isQuestionRevisionMarked = typeof deps.isQuestionRevisionMarked === 'function'
        ? deps.isQuestionRevisionMarked
        : function () {
            return false;
        };
    var isExamAnswerEditingLocked = deps.isExamAnswerEditingLocked;
    var getExamFooterSyncMeta = deps.getExamFooterSyncMeta;
    var reviewRenderer = createReviewRenderer(deps);
    var questionRecoveryInFlight = null;
    var lastQuestionRecoveryKey = '';
    var lastQuestionRecoveryAt = 0;

    function requestCurrentQuestionRecovery(questionId, totalQuestions) {
        var attemptId = Number(state.attemptId) || 0;
        var examId = Number(state.selectedExamId) || 0;
        var safeQuestionId = Number(questionId) || 0;
        if (attemptId <= 0 || examId <= 0 || totalQuestions <= 0 || state.stage !== 'exam') {
            return;
        }

        var recoveryKey = [
            attemptId,
            examId,
            Number(state.currentIndex) || 0,
            safeQuestionId,
            Number(totalQuestions) || 0
        ].join(':');
        var now = Date.now();
        if (questionRecoveryInFlight || (recoveryKey === lastQuestionRecoveryKey && (now - lastQuestionRecoveryAt) < 1200)) {
            return;
        }

        lastQuestionRecoveryKey = recoveryKey;
        lastQuestionRecoveryAt = now;
        questionRecoveryInFlight = Promise.resolve()
            .then(function () {
                if (safeQuestionId > 0 && typeof ensureQuestionWindowForIndex === 'function') {
                    return ensureQuestionWindowForIndex(state.currentIndex, {
                        attemptId: attemptId,
                        examId: examId,
                        includeExisting: 1
                    });
                }

                return null;
            })
            .then(function (recoveredQuestion) {
                if (recoveredQuestion) {
                    return recoveredQuestion;
                }

                if (typeof refreshAttemptQuestionRevision === 'function') {
                    return refreshAttemptQuestionRevision(state.questionRevision, {
                        attemptId: attemptId,
                        examId: examId,
                        force: true,
                        preferredIndex: state.currentIndex,
                        source: 'render-missing-current-question'
                    });
                }

                return null;
            })
            .catch(function () {
                return null;
            })
            .finally(function () {
                questionRecoveryInFlight = null;
                if (state.stage !== 'exam') {
                    return;
                }

                if (typeof deps.renderExamPartial === 'function') {
                    var didPatch = deps.renderExamPartial({
                        notice: true,
                        question: true
                    }, 'question-recovery:complete', {
                        currentIndex: Number(state.currentIndex) || 0
                    });
                    if (didPatch) {
                        return;
                    }
                }

                if (typeof deps.render === 'function') {
                    deps.render();
                }
            });
    }

    function renderNavToggleButton(isOpen, extraClass) {
        var classes = ['cbt-icon-button', 'cbt-nav-toggle'];
        if (isOpen) {
            classes.push('is-open');
        }
        if (extraClass) {
            classes.push(String(extraClass));
        }

        var label = isOpen ? 'Tutup navigasi soal' : 'Buka navigasi soal';

        return [
            '<button class="' + classes.join(' ') + '" data-action="toggle-nav" type="button" aria-label="' + escapeHtml(label) + '" title="' + escapeHtml(label) + '">',
            '<span class="cbt-nav-toggle-icon" aria-hidden="true"><span></span><span></span><span></span></span>',
            '</button>'
        ].join('');
    }

    function renderNavPositionControl(extraClass) {
        var activePosition = getEffectiveNavPanelPosition();
        var groupClass = 'cbt-access-group cbt-nav-position-group';

        if (extraClass) {
            groupClass += ' ' + String(extraClass);
        }

        var compactMode = deps.isCompactNavViewport();
        var options = compactMode
            ? [
                { value: 'top', label: 'Atas', arrow: '\u2191' },
                { value: 'bottom', label: 'Bawah', arrow: '\u2193' }
            ]
            : [
                { value: 'top', label: 'Atas', arrow: '\u2191' },
                { value: 'left', label: 'Kiri', arrow: '\u2190' },
                { value: 'right', label: 'Kanan', arrow: '\u2192' },
                { value: 'bottom', label: 'Bawah', arrow: '\u2193' }
            ];

        return [
            '<div class="' + groupClass + '" role="group" aria-label="Posisi Navigasi Soal">',
            options.map(function (option) {
                var isActive = option.value === activePosition;
                var classes = 'cbt-icon-button cbt-nav-position-btn' + (isActive ? ' is-active' : '');
                return '<button class="' + classes + '" data-action="set-nav-position" data-position="' + escapeHtml(option.value) + '" type="button" aria-label="' + escapeHtml(option.label) + '" title="' + escapeHtml(option.label) + '"' + (isActive ? ' aria-pressed="true"' : ' aria-pressed="false"') + '><span aria-hidden="true">' + escapeHtml(option.arrow) + '</span></button>';
            }).join(''),
            '</div>'
        ].join('');
    }

    function buildExamStageViewModel() {
        var totalQuestions = getQuestionCount();
        var progressSummary = getExamProgressSummary();
        var selectedExam = getSelectedExam();
        var calculatorEnabled = !selectedExam || Number(selectedExam.enable_calculator !== undefined ? selectedExam.enable_calculator : 1) !== 0;
        var activeExamTitle = selectedExam && selectedExam.title ? String(selectedExam.title) : '-';
        var answeredCount = progressSummary.answeredQuestions;
        var answeredPercentage = totalQuestions > 0 ? (answeredCount / totalQuestions) * 100 : 0;
        var answeredPercentageText = formatScoreValue(answeredPercentage);
        var answeredPercentageWidth = Math.max(0, Math.min(100, answeredPercentage)).toFixed(2);
        var examFooterProgressValue = totalQuestions > 0 ? (answeredPercentageText + '%') : '-';
        var examFooterProgressNote = totalQuestions > 0
            ? (String(answeredCount) + '/' + String(totalQuestions) + ' soal')
            : 'Belum ada soal';
        var examFooterSyncMeta = getExamFooterSyncMeta();
        var doubtfulCount = progressSummary.doubtfulQuestions;
        var unansweredCount = Math.max(0, totalQuestions - answeredCount);
        var changedQuestionCount = getChangedQuestionCount();
        var revisionMarkerCount = typeof getQuestionRevisionMarkerCount === 'function'
            ? getQuestionRevisionMarkerCount()
            : 0;
        var currentQuestionId = totalQuestions > 0 ? getQuestionIdAtIndex(state.currentIndex) : 0;
        var currentQuestionManifest = currentQuestionId > 0 ? getQuestionManifestById(currentQuestionId) : null;
        var currentQuestion = currentQuestionId > 0 ? getQuestionPayloadById(currentQuestionId) : null;
        var currentQuestionReference = currentQuestion || currentQuestionManifest;
        var currentQuestionIsAnswered = currentQuestionReference ? isQuestionAnswered(currentQuestionReference) : false;
        var currentQuestionIsDoubtful = currentQuestionReference ? isQuestionDoubtful(currentQuestionReference) : false;
        var currentQuestionIsChanged = currentQuestionReference ? isQuestionChanged(currentQuestionReference) : false;
        var isLastQuestion = totalQuestions > 0 && state.currentIndex >= (totalQuestions - 1);
        var allQuestionsAnswered = totalQuestions > 0 && answeredCount >= totalQuestions;
        var showCollectAction = isLastQuestion || allQuestionsAnswered;
        var showCollectAnywhereNotice = allQuestionsAnswered && !isLastQuestion;
        var currentQuestionType = currentQuestionReference ? String(currentQuestionReference.question_type || '').trim().toLowerCase() : '';
        var currentQuestionTypeLabel = currentQuestionReference ? formatQuestionType(currentQuestionReference.question_type) : '';
        var currentQuestionTypeBadge = currentQuestionReference ? navigationQuestionTypeBadgeConfig(currentQuestionReference.question_type) : null;
        var currentQuestionTypeCode = currentQuestionTypeBadge && currentQuestionTypeBadge.code
            ? String(currentQuestionTypeBadge.code)
            : '';
        var currentQuestionPointsRaw = currentQuestionReference && currentQuestionReference.points !== undefined ? currentQuestionReference.points : '-';
        var currentQuestionPointsNumber = Number(currentQuestionPointsRaw);
        var currentQuestionPoints = Number.isFinite(currentQuestionPointsNumber)
            ? formatScoreValue(currentQuestionPointsNumber)
            : String(currentQuestionPointsRaw);
        var currentQuestionDisplayNumber = currentQuestionReference ? getQuestionDisplayNumber(currentQuestionReference, state.currentIndex) : Math.max(1, Number(state.currentIndex) + 1);
        var currentQuestionMetaLabel = currentQuestionReference ? (currentQuestionTypeLabel + ' | Poin ' + currentQuestionPoints) : '';
        var currentQuestionMetaCompact = currentQuestionReference ? (currentQuestionTypeCode + '\u00b7' + currentQuestionPoints) : '';
        var currentQuestionMetaMobileFull = currentQuestionReference ? (currentQuestionTypeLabel + ' \u2022 Poin ' + currentQuestionPoints) : '';
        var currentQuestionSaveFeedback = currentQuestionReference ? getQuestionSaveFeedback(currentQuestionReference.id) : null;
        var doubtfulActionLabel = currentQuestionIsDoubtful ? 'Batalkan ragu-ragu' : 'Tandai ragu-ragu';
        var doubtfulActionClass = 'cbt-action-icon cbt-action-icon-doubtful' + (currentQuestionIsDoubtful ? ' is-active' : '');
        var answerEditingLocked = isExamAnswerEditingLocked();
        var navPanelPosition = getEffectiveNavPanelPosition();
        var calculatorPanelPosition = getEffectiveCalculatorPanelPosition();
        var examLayoutClass = 'cbt-exam-layout cbt-nav-pos-' + navPanelPosition + (state.navPanelVisible ? '' : ' is-nav-hidden');
        var questionHeadClasses = ['cbt-question-head'];

        if (currentQuestionIsDoubtful) {
            questionHeadClasses.push('is-doubtful');
        } else if (currentQuestionIsAnswered) {
            questionHeadClasses.push('is-answered');
        }

        var navQuestionFilter = normalizeNavigationQuestionFilter(state.navQuestionFilter);
        var filteredNavigationEntries = totalQuestions > 0 ? getNavigationQuestionEntries(navQuestionFilter) : [];
        var navItems = filteredNavigationEntries.map(function (entry) {
            var question = entry.question;
            var classes = ['cbt-nav-btn'];
            var revisionMarked = isQuestionRevisionMarked(question);
            if (isQuestionAnswered(question)) {
                classes.push('is-answered');
            }
            if (isQuestionDoubtful(question)) {
                classes.push('is-doubtful');
            }
            if (revisionMarked) {
                classes.push('is-changed');
            }
            if (entry.index === state.currentIndex) {
                classes.push('is-current');
            }
            var answerBadge = renderNavigationAnswerBadges(question);
            var questionTypeBadge = renderNavigationQuestionTypeBadge(question);
            var displayNumber = getQuestionDisplayNumber(question, entry.index);
            var buttonLabel = 'Soal ' + String(displayNumber);
            if (revisionMarked) {
                buttonLabel += ', soal baru atau berubah';
            }
            return '<button type="button" class="' + classes.join(' ') + '" data-action="jump" data-index="' + escapeHtml(entry.index) + '" aria-label="' + escapeHtml(buttonLabel) + '" title="' + escapeHtml(buttonLabel) + '">' + (revisionMarked ? '<span class="cbt-nav-revision-marker" aria-hidden="true">!</span>' : '') + '<span class="cbt-nav-number">' + escapeHtml(displayNumber) + '</span>' + questionTypeBadge + answerBadge + '</button>';
        }).join('');
        var navGridClass = 'cbt-nav-grid' + (filteredNavigationEntries.length ? '' : ' is-empty');
        var navGridMarkup = filteredNavigationEntries.length
            ? navItems
            : ('<div class="cbt-nav-empty">' + escapeHtml(navigationQuestionFilterEmptyMessage(navQuestionFilter)) + '</div>');
        var answeredFilterActive = navQuestionFilter === deps.NAV_QUESTION_FILTER_ANSWERED;
        var unansweredFilterActive = navQuestionFilter === deps.NAV_QUESTION_FILTER_UNANSWERED;
        var doubtfulFilterActive = navQuestionFilter === deps.NAV_QUESTION_FILTER_DOUBTFUL;
        var answeredFilterTitle = answeredFilterActive
            ? 'Tampilkan semua nomor soal'
            : 'Tampilkan hanya soal yang sudah terjawab';
        var unansweredFilterTitle = unansweredFilterActive
            ? 'Tampilkan semua nomor soal'
            : 'Tampilkan hanya soal yang belum dijawab';
        var doubtfulFilterTitle = doubtfulFilterActive
            ? 'Tampilkan semua nomor soal'
            : 'Tampilkan hanya soal yang ditandai ragu-ragu';
        var quickNavigationMarkup = currentQuestionReference ? [
            '<button class="cbt-action-icon cbt-action-icon-prev" data-action="prev" type="button" aria-label="Sebelumnya" title="Sebelumnya"' + (state.currentIndex <= 0 || state.busy ? ' disabled' : '') + '><span class="cbt-visually-hidden">Sebelumnya</span></button>',
            '<button class="' + doubtfulActionClass + '" data-action="toggle-doubtful" data-qid="' + escapeHtml(currentQuestionId) + '" type="button" aria-label="' + escapeHtml(doubtfulActionLabel) + '" title="' + escapeHtml(doubtfulActionLabel) + '"' + (state.busy || answerEditingLocked || currentQuestionId <= 0 ? ' disabled' : '') + '><span class="cbt-visually-hidden">' + escapeHtml(doubtfulActionLabel) + '</span></button>',
            '<button class="cbt-action-icon cbt-action-icon-next" data-action="next" type="button" aria-label="Selanjutnya" title="Selanjutnya"' + (state.currentIndex >= totalQuestions - 1 || state.busy ? ' disabled' : '') + '><span class="cbt-visually-hidden">Selanjutnya</span></button>'
        ].join('') : '';

        return {
            activeExamTitle: activeExamTitle,
            answerEditingLocked: answerEditingLocked,
            answeredCount: answeredCount,
            answeredFilterActive: answeredFilterActive,
            answeredFilterTitle: answeredFilterTitle,
            answeredPercentageText: answeredPercentageText,
            answeredPercentageWidth: answeredPercentageWidth,
            calculatorEnabled: calculatorEnabled,
            calculatorPanelPosition: calculatorPanelPosition,
            changedQuestionCount: changedQuestionCount,
            revisionMarkerCount: revisionMarkerCount,
            currentQuestion: currentQuestion,
            currentQuestionDisplayNumber: currentQuestionDisplayNumber,
            currentQuestionId: currentQuestionId,
            currentQuestionIsChanged: currentQuestionIsChanged,
            currentQuestionIsDoubtful: currentQuestionIsDoubtful,
            currentQuestionManifest: currentQuestionManifest,
            currentQuestionMetaCompact: currentQuestionMetaCompact,
            currentQuestionMetaMobileFull: currentQuestionMetaMobileFull,
            currentQuestionMetaLabel: currentQuestionMetaLabel,
            currentQuestionPoints: currentQuestionPoints,
            currentQuestionSaveFeedback: currentQuestionSaveFeedback,
            currentQuestionType: currentQuestionType,
            currentQuestionTypeLabel: currentQuestionTypeLabel,
            doubtfulCount: doubtfulCount,
            doubtfulFilterActive: doubtfulFilterActive,
            doubtfulFilterTitle: doubtfulFilterTitle,
            examFooterProgressNote: examFooterProgressNote,
            examFooterProgressValue: examFooterProgressValue,
            examFooterSyncMeta: examFooterSyncMeta,
            examLayoutClass: examLayoutClass,
            filteredNavigationEntries: filteredNavigationEntries,
            allQuestionsAnswered: allQuestionsAnswered,
            isLastQuestion: isLastQuestion,
            navGridClass: navGridClass,
            navGridMarkup: navGridMarkup,
            navPanelPosition: navPanelPosition,
            questionHeadClasses: questionHeadClasses,
            quickNavigationMarkup: quickNavigationMarkup,
            showCollectAction: showCollectAction,
            showCollectAnywhereNotice: showCollectAnywhereNotice,
            totalQuestions: totalQuestions,
            unansweredCount: unansweredCount,
            unansweredFilterActive: unansweredFilterActive,
            unansweredFilterTitle: unansweredFilterTitle
        };
    }

    function renderExamRevisionNoticeRegion() {
        var parts = [];
        var alertMarkup = renderAlert();
        if (alertMarkup) {
            parts.push(alertMarkup);
        }

        var revisionNotice = state.questionRevisionNotice && typeof state.questionRevisionNotice === 'object'
            ? state.questionRevisionNotice
            : null;
        if (revisionNotice && revisionNotice.message) {
            var noticeClasses = ['cbt-exam-revision-notice'];
            noticeClasses.push(revisionNotice.sticky ? 'is-sticky' : 'is-toast');
            noticeClasses.push(String(revisionNotice.tone || 'info') === 'warning' ? 'is-warning' : 'is-info');
            parts.push(
                '<div class="' + noticeClasses.join(' ') + '" role="status" aria-live="polite">'
                + '<span class="cbt-exam-revision-notice-dot" aria-hidden="true"></span>'
                + '<div class="cbt-exam-revision-notice-copy">' + escapeHtml(revisionNotice.message) + '</div>'
                + '</div>'
            );
        }

        return parts.join('');
    }

    function renderExamNavigationRegion(viewModel) {
        return [
            '<aside class="cbt-side-card' + (state.navPanelVisible ? '' : ' is-hidden') + '">',
            '<div class="cbt-side-card-head">',
            '<div class="cbt-side-card-title-wrap">',
            '<h4 class="cbt-side-card-title">NAVIGASI SOAL</h4>',
            '<p class="cbt-side-card-subtitle">Pilih nomor untuk pindah soal</p>',
            '</div>',
            '<div class="cbt-side-card-head-actions">',
            renderNavPositionControl('cbt-nav-position-group-inline'),
            renderNavToggleButton(true, 'cbt-nav-toggle-side'),
            '</div>',
            '</div>',
            '<div class="cbt-exam-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(viewModel.answeredPercentageWidth) + '">',
            '<span class="cbt-exam-progress-fill" style="width: ' + escapeHtml(viewModel.answeredPercentageWidth) + '%;"></span>',
            '</div>',
            '<section class="cbt-side-summary">',
            '<div class="cbt-side-summary-top">',
            '<p class="cbt-side-summary-kicker">Progress Jawaban</p>',
            '<strong class="cbt-side-summary-value">' + escapeHtml(viewModel.answeredPercentageText) + '%</strong>',
            '</div>',
            '<div class="cbt-side-stat-grid">',
            '<button class="cbt-side-stat is-answered' + (viewModel.answeredFilterActive ? ' is-active' : '') + '" data-action="filter-nav" data-filter="' + deps.NAV_QUESTION_FILTER_ANSWERED + '" type="button" aria-pressed="' + (viewModel.answeredFilterActive ? 'true' : 'false') + '" title="' + escapeHtml(viewModel.answeredFilterTitle) + '" aria-label="Filter soal terjawab, ' + escapeHtml(viewModel.answeredCount) + ' dari ' + escapeHtml(viewModel.totalQuestions) + ' soal"><span>Terjawab</span><strong>' + escapeHtml(viewModel.answeredCount) + '/' + escapeHtml(viewModel.totalQuestions) + '</strong></button>',
            '<button class="cbt-side-stat is-unanswered' + (viewModel.unansweredFilterActive ? ' is-active' : '') + '" data-action="filter-nav" data-filter="' + deps.NAV_QUESTION_FILTER_UNANSWERED + '" type="button" aria-pressed="' + (viewModel.unansweredFilterActive ? 'true' : 'false') + '" title="' + escapeHtml(viewModel.unansweredFilterTitle) + '" aria-label="Filter soal belum dijawab, ' + escapeHtml(viewModel.unansweredCount) + ' soal"><span>Belum</span><strong>' + escapeHtml(viewModel.unansweredCount) + '</strong></button>',
            '<button class="cbt-side-stat is-doubtful' + (viewModel.doubtfulFilterActive ? ' is-active' : '') + '" data-action="filter-nav" data-filter="' + deps.NAV_QUESTION_FILTER_DOUBTFUL + '" type="button" aria-pressed="' + (viewModel.doubtfulFilterActive ? 'true' : 'false') + '" title="' + escapeHtml(viewModel.doubtfulFilterTitle) + '" aria-label="Filter soal ragu-ragu, ' + escapeHtml(viewModel.doubtfulCount) + ' soal"><span>Ragu</span><strong>' + escapeHtml(viewModel.doubtfulCount) + '</strong></button>',
            '</div>',
            '</section>',
            '<div class="' + viewModel.navGridClass + '">' + viewModel.navGridMarkup + '</div>',
            '<div class="cbt-legend"><span class="cbt-legend-item cbt-legend-item-current"><i class="cbt-dot cbt-dot-current"></i> Aktif</span><span class="cbt-legend-item cbt-legend-item-answered"><i class="cbt-dot cbt-dot-answered"></i> Terjawab</span><span class="cbt-legend-item cbt-legend-item-doubtful"><i class="cbt-dot cbt-dot-doubtful"></i> Ragu-ragu</span>' + (viewModel.revisionMarkerCount > 0 ? '<span class="cbt-legend-item cbt-legend-item-changed"><i class="cbt-dot cbt-dot-changed"></i> Baru / berubah</span>' : '') + '</div>',
            reviewRenderer.renderArchivedReviewHistorySection(),
            (viewModel.showCollectAction
                ? ('<div class="cbt-actions cbt-side-actions-compact"><button class="cbt-button cbt-button-primary" data-action="collect" type="button"' + (state.busy || state.isFinishing || state.examLockedForPendingFinish ? ' disabled' : '') + '>Kumpulkan Jawaban</button></div>')
                : ''),
            '</aside>'
        ].join('');
    }

    function renderQuestionLoadingQuickNavigation(viewModel) {
        return [
            '<button class="cbt-action-icon cbt-action-icon-prev" data-action="prev" type="button" aria-label="Sebelumnya" title="Sebelumnya"' + (state.currentIndex <= 0 || state.busy ? ' disabled' : '') + '><span class="cbt-visually-hidden">Sebelumnya</span></button>',
            '<span class="cbt-action-icon cbt-action-icon-placeholder" aria-hidden="true"></span>',
            '<button class="cbt-action-icon cbt-action-icon-next" data-action="next" type="button" aria-label="Selanjutnya" title="Selanjutnya"' + (state.currentIndex >= viewModel.totalQuestions - 1 || state.busy ? ' disabled' : '') + '><span class="cbt-visually-hidden">Selanjutnya</span></button>'
        ].join('');
    }

    function renderQuestionSkeletonLine(sizeClass, extraClass) {
        var classes = ['cbt-question-skeleton-line'];
        if (sizeClass) {
            classes.push(sizeClass);
        }
        if (extraClass) {
            classes.push(extraClass);
        }

        return '<span class="' + classes.join(' ') + '" aria-hidden="true"></span>';
    }

    function renderQuestionSkeletonOptionRows(markerClass) {
        return [
            '<div class="cbt-question-skeleton-options">',
            [1, 2, 3, 4].map(function () {
                return [
                    '<div class="cbt-question-skeleton-option">',
                    '<span class="cbt-question-skeleton-option-marker ' + escapeHtml(markerClass || 'is-generic') + '" aria-hidden="true"></span>',
                    '<div class="cbt-question-skeleton-option-lines">',
                    renderQuestionSkeletonLine('is-medium'),
                    renderQuestionSkeletonLine('is-short'),
                    '</div>',
                    '</div>'
                ].join('');
            }).join(''),
            '</div>'
        ].join('');
    }

    function renderQuestionSkeletonTextareaRows(count) {
        return [
            '<div class="cbt-question-skeleton-textareas">',
            new Array(Math.max(1, Number(count) || 1)).fill('').map(function (_, index) {
                return '<div class="cbt-question-skeleton-textarea' + (index > 0 ? ' is-secondary' : '') + '" aria-hidden="true"></div>';
            }).join(''),
            '</div>'
        ].join('');
    }

    function renderQuestionSkeletonShortAnswerInputs() {
        return [
            '<div class="cbt-question-skeleton-input-grid">',
            [1, 2, 3].map(function () {
                return '<span class="cbt-question-skeleton-input" aria-hidden="true"></span>';
            }).join(''),
            '</div>'
        ].join('');
    }

    function renderQuestionSkeletonMatrix() {
        return [
            '<div class="cbt-question-skeleton-matrix" aria-hidden="true">',
            '<div class="cbt-question-skeleton-matrix-row is-head">',
            '<span class="cbt-question-skeleton-line is-medium"></span>',
            '<span class="cbt-question-skeleton-line is-pill"></span>',
            '<span class="cbt-question-skeleton-line is-pill"></span>',
            '</div>',
            [1, 2, 3].map(function () {
                return [
                    '<div class="cbt-question-skeleton-matrix-row">',
                    '<span class="cbt-question-skeleton-line is-medium"></span>',
                    '<span class="cbt-question-skeleton-cell"></span>',
                    '<span class="cbt-question-skeleton-cell"></span>',
                    '</div>'
                ].join('');
            }).join(''),
            '</div>'
        ].join('');
    }

    function renderQuestionLoadingSkeleton(viewModel) {
        var currentQuestionType = String(viewModel.currentQuestionType || '').trim().toLowerCase();
        var skeletonClasses = ['cbt-question-skeleton'];
        var bodyMarkup = '';

        switch (currentQuestionType) {
        case 'multiple_choice':
        case 'true_false':
            skeletonClasses.push('is-multiple-choice');
            bodyMarkup = renderQuestionSkeletonOptionRows('is-radio');
            break;
        case 'multiple_answer':
            skeletonClasses.push('is-multiple-answer');
            bodyMarkup = renderQuestionSkeletonOptionRows('is-checkbox');
            break;
        case 'short_answer':
            skeletonClasses.push('is-short-answer');
            bodyMarkup = renderQuestionSkeletonShortAnswerInputs();
            break;
        case 'true_false_matrix':
            skeletonClasses.push('is-true-false-matrix');
            bodyMarkup = renderQuestionSkeletonMatrix();
            break;
        case 'essay':
        case 'text':
            skeletonClasses.push('is-textual');
            bodyMarkup = renderQuestionSkeletonTextareaRows(2);
            break;
        default:
            skeletonClasses.push('is-generic');
            bodyMarkup = renderQuestionSkeletonOptionRows('is-generic');
            break;
        }

        return [
            '<div class="' + skeletonClasses.join(' ') + '" aria-hidden="true">',
            '<div class="cbt-question-skeleton-copy">',
            renderQuestionSkeletonLine('is-kicker'),
            renderQuestionSkeletonLine('is-long'),
            renderQuestionSkeletonLine('is-medium'),
            '</div>',
            '<div class="cbt-question-skeleton-media"></div>',
            bodyMarkup,
            '</div>'
        ].join('');
    }

    function renderQuestionRegionLoadingMarkup(viewModel) {
        var loadingTitle = state.questionRegionRefreshing
            ? 'Memperbarui Soal'
            : 'Memuat Soal';
        var loadingSubtitle = state.questionRegionRefreshing
            ? 'Soal aktif sedang disinkronkan. Navigasi soal tetap tersedia.'
            : 'Window soal sedang dimuat ulang otomatis. Mohon tunggu sebentar.';

        requestCurrentQuestionRecovery(viewModel.currentQuestionId, viewModel.totalQuestions);

        return [
            '<section class="cbt-question-card cbt-question-card-loading">',
            '<div class="cbt-question-head">',
            '<div class="cbt-question-head-row cbt-question-head-row-top">',
            '<div class="cbt-question-head-row-info cbt-question-head-main">',
            '<div class="cbt-chip cbt-chip-question-index" aria-label="Soal aktif"><span class="cbt-chip-mobile-icon" aria-hidden="true">#</span><span class="cbt-chip-label">Soal</span><span class="cbt-chip-value">' + escapeHtml(viewModel.currentQuestionDisplayNumber) + '</span></div>',
            (viewModel.currentQuestionMetaLabel
                ? '<div class="cbt-chip cbt-chip-question-meta is-loading" title="' + escapeHtml(viewModel.currentQuestionMetaLabel) + '" aria-label="' + escapeHtml(viewModel.currentQuestionMetaLabel) + '"><span class="cbt-chip-mobile-meta" aria-hidden="true">' + escapeHtml(viewModel.currentQuestionMetaCompact) + '</span><span class="cbt-chip-mobile-meta-full" aria-hidden="true">' + escapeHtml(viewModel.currentQuestionMetaMobileFull) + '</span><span class="cbt-chip-type">' + escapeHtml(viewModel.currentQuestionTypeLabel) + '</span><span class="cbt-chip-separator" aria-hidden="true"></span><span class="cbt-chip-points">Poin ' + escapeHtml(viewModel.currentQuestionPoints) + '</span></div>'
                : ''),
            '</div>',
            '<div class="cbt-question-head-controls-right cbt-question-head-tools">',
            (viewModel.calculatorEnabled ? renderCalculatorToggleButton(state.calculatorVisible) : ''),
            renderNavToggleButton(state.navPanelVisible, 'cbt-nav-toggle-head'),
            '</div>',
            '</div>',
            '<div class="cbt-question-head-row cbt-question-head-row-controls">',
            '<div class="cbt-question-head-controls-left">',
            renderQuestionPrefetchIndicator('cbt-chip-prefetch-mobile-row'),
            renderQuestionFontControls(),
            '</div>',
            '</div>',
            '</div>',
            '<div class="cbt-question-body">',
            '<div class="cbt-question-quick-nav cbt-question-quick-nav-top" role="group" aria-label="Navigasi Cepat Soal">',
            (viewModel.quickNavigationMarkup || renderQuestionLoadingQuickNavigation(viewModel)),
            '</div>',
            '<div class="cbt-question-loading-status">',
            '<strong class="cbt-question-loading-title">' + escapeHtml(loadingTitle) + '</strong>',
            '<p class="cbt-question-loading-note">' + escapeHtml(loadingSubtitle) + '</p>',
            '</div>',
            renderQuestionLoadingSkeleton(viewModel),
            '<div class="cbt-question-quick-nav cbt-question-quick-nav-bottom" role="group" aria-label="Navigasi Cepat Soal">',
            (viewModel.quickNavigationMarkup || renderQuestionLoadingQuickNavigation(viewModel)),
            '</div>',
            '<div class="cbt-question-exam-footer" title="' + escapeHtml(viewModel.activeExamTitle) + '">',
            '<span class="cbt-question-exam-footer-badge" aria-hidden="true"><span class="cbt-question-exam-footer-badge-core"></span></span>',
            '<div class="cbt-question-exam-footer-copy">',
            '<span class="cbt-question-exam-footer-label">Ujian Aktif</span>',
            '<strong class="cbt-question-exam-footer-value">' + escapeHtml(viewModel.activeExamTitle) + '</strong>',
            '</div>',
            '<div class="cbt-question-exam-footer-side">',
            '<div class="cbt-question-exam-footer-meta" aria-label="Progress ' + escapeHtml(viewModel.examFooterProgressValue) + ', ' + escapeHtml(viewModel.examFooterProgressNote) + ' terjawab">',
            '<span class="cbt-question-exam-footer-meta-label">Progress</span>',
            '<strong class="cbt-question-exam-footer-meta-value">' + escapeHtml(viewModel.examFooterProgressValue) + '</strong>',
            '<small class="cbt-question-exam-footer-meta-note">' + escapeHtml(viewModel.examFooterProgressNote) + '</small>',
            '</div>',
            '<div class="cbt-question-exam-footer-meta cbt-question-exam-footer-meta-sync ' + escapeHtml(viewModel.examFooterSyncMeta.tone || '') + '" title="' + escapeHtml(viewModel.examFooterSyncMeta.title || '') + '" aria-label="' + escapeHtml(viewModel.examFooterSyncMeta.title || '') + '">',
            '<span class="cbt-question-exam-footer-meta-label">' + escapeHtml(viewModel.examFooterSyncMeta.label || 'Sinkron') + '</span>',
            '<strong class="cbt-question-exam-footer-meta-value">' + escapeHtml(viewModel.examFooterSyncMeta.value || '-') + '</strong>',
            '<small class="cbt-question-exam-footer-meta-note">' + escapeHtml(viewModel.examFooterSyncMeta.note || '') + '</small>',
            '</div>',
            '</div>',
            '</div>',
            '</div>',
            '</section>'
        ].join('');
    }

    function renderQuestionSaveFeedback(feedback) {
        var safeFeedback = feedback && typeof feedback === 'object' ? feedback : null;
        if (!safeFeedback || !safeFeedback.isVisible || !safeFeedback.label) {
            return '';
        }

        var tone = String(safeFeedback.tone || 'saved');
        var classes = ['cbt-question-save-feedback', 'is-' + tone];
        var detail = String(safeFeedback.detail || '').trim();

        return [
            '<div class="' + classes.join(' ') + '" role="status" aria-live="polite">',
            '<span class="cbt-question-save-feedback-dot" aria-hidden="true"></span>',
            '<div class="cbt-question-save-feedback-copy">',
            '<strong class="cbt-question-save-feedback-label">' + escapeHtml(safeFeedback.label) + '</strong>',
            (detail !== '' ? '<small class="cbt-question-save-feedback-note">' + escapeHtml(detail) + '</small>' : ''),
            '</div>',
            '</div>'
        ].join('');
    }

    function buildExamQuestionSubregionMarkup(viewModel) {
        var currentQuestion = viewModel.currentQuestion;
        if (!currentQuestion) {
            return null;
        }

        return {
            questionHead: [
                '<div class="' + viewModel.questionHeadClasses.join(' ') + '">',
                '<div class="cbt-question-head-row cbt-question-head-row-top">',
                '<div class="cbt-question-head-row-info cbt-question-head-main">',
                '<div class="cbt-chip cbt-chip-question-index" aria-label="Soal ' + escapeHtml(viewModel.currentQuestionDisplayNumber) + '"><span class="cbt-chip-mobile-icon" aria-hidden="true">#</span><span class="cbt-chip-label">Soal</span><span class="cbt-chip-value">' + escapeHtml(viewModel.currentQuestionDisplayNumber) + '</span></div>',
                '<div class="cbt-chip cbt-chip-question-meta" title="' + escapeHtml(viewModel.currentQuestionMetaLabel) + '" aria-label="' + escapeHtml(viewModel.currentQuestionMetaLabel) + '"><span class="cbt-chip-mobile-meta" aria-hidden="true">' + escapeHtml(viewModel.currentQuestionMetaCompact) + '</span><span class="cbt-chip-mobile-meta-full" aria-hidden="true">' + escapeHtml(viewModel.currentQuestionMetaMobileFull) + '</span><span class="cbt-chip-type">' + escapeHtml(viewModel.currentQuestionTypeLabel) + '</span><span class="cbt-chip-separator" aria-hidden="true"></span><span class="cbt-chip-points">Poin ' + escapeHtml(viewModel.currentQuestionPoints) + '</span></div>',
                renderQuestionPrefetchIndicator(),
                (viewModel.currentQuestionIsChanged ? '<div class="cbt-chip cbt-chip-danger">Soal baru / berubah</div>' : ''),
                (viewModel.currentQuestionIsDoubtful ? '<div class="cbt-chip cbt-chip-warning cbt-chip-warning-icon" aria-label="Ragu-ragu"><span class="cbt-chip-warning-symbol" aria-hidden="true">!</span><span class="cbt-visually-hidden">Ragu-ragu</span></div>' : ''),
                '</div>',
                '<div class="cbt-question-head-controls-right cbt-question-head-tools">',
                (viewModel.calculatorEnabled ? renderCalculatorToggleButton(state.calculatorVisible) : ''),
                renderNavToggleButton(state.navPanelVisible, 'cbt-nav-toggle-head'),
                '</div>',
                '</div>',
                '<div class="cbt-question-head-row cbt-question-head-row-controls">',
                '<div class="cbt-question-head-controls-left">',
                renderQuestionPrefetchIndicator('cbt-chip-prefetch-mobile-row'),
                renderQuestionFontControls(),
                '</div>',
                '</div>',
                '</div>'
            ].join(''),
            questionQuickNav: viewModel.quickNavigationMarkup,
            questionStem: renderQuestionStem(currentQuestion),
            questionInput: renderQuestionInput(currentQuestion),
            questionSaveFeedback: [
                renderQuestionSaveFeedback(viewModel.currentQuestionSaveFeedback),
                (viewModel.showCollectAnywhereNotice
                    ? '<div class="cbt-question-collect-ready-note" role="status" aria-live="polite">SEMUA SOAL SUDAH TERJAWAB. ANDA BISA LANGSUNG KUMPULKAN UJIAN DARI HALAMAN INI.</div>'
                    : ''),
                (viewModel.showCollectAction
                    ? ('<div class="cbt-question-actions cbt-question-actions-main"><button class="cbt-button cbt-button-primary" data-action="finish" type="button"' + (state.busy || state.isFinishing || state.examLockedForPendingFinish ? ' disabled' : '') + '>' + (state.isFinishing ? 'Mengirim...' : 'Kumpulkan Jawaban') + '</button></div>')
                    : '')
            ].join(''),
            questionFooterProgress: [
                '<div class="cbt-question-exam-footer-meta" aria-label="Progress ' + escapeHtml(viewModel.examFooterProgressValue) + ', ' + escapeHtml(viewModel.examFooterProgressNote) + ' terjawab">',
                '<span class="cbt-question-exam-footer-meta-label">Progress</span>',
                '<strong class="cbt-question-exam-footer-meta-value">' + escapeHtml(viewModel.examFooterProgressValue) + '</strong>',
                '<small class="cbt-question-exam-footer-meta-note">' + escapeHtml(viewModel.examFooterProgressNote) + '</small>',
                '</div>'
            ].join(''),
            questionFooterSync: [
                '<div class="cbt-question-exam-footer-meta cbt-question-exam-footer-meta-sync ' + escapeHtml(viewModel.examFooterSyncMeta.tone || '') + '" title="' + escapeHtml(viewModel.examFooterSyncMeta.title || '') + '" aria-label="' + escapeHtml(viewModel.examFooterSyncMeta.title || '') + '">',
                '<span class="cbt-question-exam-footer-meta-label">' + escapeHtml(viewModel.examFooterSyncMeta.label || 'Sinkron') + '</span>',
                '<strong class="cbt-question-exam-footer-meta-value">' + escapeHtml(viewModel.examFooterSyncMeta.value || '-') + '</strong>',
                '<small class="cbt-question-exam-footer-meta-note">' + escapeHtml(viewModel.examFooterSyncMeta.note || '') + '</small>',
                '</div>'
            ].join('')
        };
    }

    function renderExamQuestionRegion(viewModel, questionSubregions) {
        var currentQuestion = viewModel.currentQuestion;
        if (!currentQuestion) {
            return renderQuestionRegionLoadingMarkup(viewModel);
        }

        if (!questionSubregions || typeof questionSubregions !== 'object') {
            questionSubregions = buildExamQuestionSubregionMarkup(viewModel);
        }

        return [
            '<section class="cbt-question-card">',
            '<div data-cbt-exam-question-region="questionHead">' + questionSubregions.questionHead + '</div>',
            '<div class="cbt-question-body">',
            '<div class="cbt-question-quick-nav cbt-question-quick-nav-top" data-cbt-exam-question-region="questionQuickNav" role="group" aria-label="Navigasi Cepat Soal">',
            questionSubregions.questionQuickNav,
            '</div>',
            '<div class="cbt-question-stem' + (currentQuestion.question_type === 'short_answer' ? ' is-short-answer' : '') + '" data-cbt-exam-question-region="questionStem">' + questionSubregions.questionStem + '</div>',
            '<div data-cbt-exam-question-region="questionInput">' + questionSubregions.questionInput + '</div>',
            '<div data-cbt-exam-question-region="questionSaveFeedback">' + questionSubregions.questionSaveFeedback + '</div>',
            '<div class="cbt-question-quick-nav cbt-question-quick-nav-bottom" data-cbt-exam-question-region="questionQuickNav" role="group" aria-label="Navigasi Cepat Soal">',
            questionSubregions.questionQuickNav,
            '</div>',
            '<div class="cbt-question-exam-footer" title="' + escapeHtml(viewModel.activeExamTitle) + '">',
            '<span class="cbt-question-exam-footer-badge" aria-hidden="true"><span class="cbt-question-exam-footer-badge-core"></span></span>',
            '<div class="cbt-question-exam-footer-copy">',
            '<span class="cbt-question-exam-footer-label">Ujian Aktif</span>',
            '<strong class="cbt-question-exam-footer-value">' + escapeHtml(viewModel.activeExamTitle) + '</strong>',
            '</div>',
            '<div class="cbt-question-exam-footer-side">',
            '<div data-cbt-exam-question-region="questionFooterProgress">' + questionSubregions.questionFooterProgress + '</div>',
            '<div data-cbt-exam-question-region="questionFooterSync">' + questionSubregions.questionFooterSync + '</div>',
            '</div>',
            '</div>',
            '</div>',
            '</section>'
        ].join('');
    }

    function renderExamRegions() {
        var viewModel = buildExamStageViewModel();
        if (!viewModel.totalQuestions) {
            return null;
        }

        var questionSubregions = buildExamQuestionSubregionMarkup(viewModel);

        return {
            navigation: renderExamNavigationRegion(viewModel),
            notice: renderExamRevisionNoticeRegion(),
            question: renderExamQuestionRegion(viewModel, questionSubregions),
            questionSubregions: questionSubregions
        };
    }

    function renderOpeningAttemptCard() {
        var progressPercent = Math.max(0, Math.min(100, Number(state.openingAttemptProgressPercent) || 0));
        var progressPercentText = formatScoreValue(progressPercent);
        var stepIndex = Math.max(0, Number(state.openingAttemptProgressStepIndex) || 0);
        var stepTotal = Math.max(
            OPENING_PROGRESS_STEP_LABELS.length,
            Number(state.openingAttemptProgressStepTotal) || OPENING_PROGRESS_STEP_LABELS.length
        );
        var progressStatus = String(state.openingAttemptProgressStatus || 'Session ujian dan daftar soal sedang dimuat. Mohon tunggu sebentar.');
        var progressDetail = String(state.openingAttemptProgressDetail || 'Mohon tunggu sebentar, kami sedang menyiapkan tampilan ujian Anda.');

        return [
            '<section class="cbt-card cbt-exam-opening-card">',
            '<div class="cbt-exam-opening-head">',
            '<span class="cbt-exam-opening-chip">Loading ' + escapeHtml(stepIndex || 1) + '/' + escapeHtml(stepTotal) + '</span>',
            '<h3>Menyiapkan Ujian</h3>',
            '<p class="cbt-subtitle">' + escapeHtml(progressStatus) + '</p>',
            '</div>',
            renderAlert(),
            '<div class="cbt-exam-opening-progress-wrap">',
            '<div class="cbt-exam-opening-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(progressPercent.toFixed(2)) + '" aria-label="Progress persiapan ujian">',
            '<span class="cbt-exam-opening-progress-fill" style="width: ' + escapeHtml(progressPercent.toFixed(2)) + '%;"></span>',
            '</div>',
            '<div class="cbt-exam-opening-progress-meta">',
            '<strong class="cbt-exam-opening-progress-value">' + escapeHtml(progressPercentText) + '%</strong>',
            '<span class="cbt-exam-opening-progress-status">' + escapeHtml(progressDetail) + '</span>',
            '</div>',
            '</div>',
            '<ol class="cbt-exam-opening-steps" aria-label="Tahap persiapan ujian">',
            OPENING_PROGRESS_STEP_LABELS.map(function (label, index) {
                var stepNumber = index + 1;
                var stepClass = 'cbt-exam-opening-step';
                if (stepNumber < stepIndex) {
                    stepClass += ' is-complete';
                } else if (stepNumber === stepIndex) {
                    stepClass += ' is-current';
                }

                return [
                    '<li class="' + stepClass + '">',
                    '<span class="cbt-exam-opening-step-dot" aria-hidden="true"></span>',
                    '<span class="cbt-exam-opening-step-label">' + escapeHtml(label) + '</span>',
                    '</li>'
                ].join('');
            }).join(''),
            '</ol>',
            '<div class="cbt-actions"><button class="cbt-button cbt-button-secondary" data-action="logout" type="button">Logout</button></div>',
            '</section>'
        ].join('');
    }

    function renderExamStageShell() {
        var viewModel = buildExamStageViewModel();
        if (!viewModel.totalQuestions) {
            if (state.isOpeningAttempt || state.busy) {
                return renderOpeningAttemptCard();
            }

            return [
                '<section class="cbt-card">',
                '<h3>Soal Tidak Tersedia</h3>',
                '<p class="cbt-subtitle">Belum ada soal yang bisa ditampilkan untuk exam ini.</p>',
                '<div class="cbt-actions"><button class="cbt-button cbt-button-secondary" data-action="back-confirm" type="button">Kembali</button><button class="cbt-button cbt-button-danger" data-action="logout" type="button">Logout</button></div>',
                renderAlert(),
                '</section>'
            ].join('');
        }

        var regionMarkup = renderExamRegions();
        var navigationRegionMarkup = '<div class="cbt-exam-region cbt-exam-region-navigation" data-cbt-exam-region="navigation">' + regionMarkup.navigation + '</div>';
        var questionRegionMarkup = '<div class="cbt-exam-region cbt-exam-region-question" data-cbt-exam-region="question">' + regionMarkup.question + '</div>';
        var noticeRegionMarkup = '<div class="cbt-exam-notice-region" data-cbt-exam-region="notice">' + regionMarkup.notice + '</div>';
        var layoutContent = viewModel.navPanelPosition === 'right'
            ? (questionRegionMarkup + navigationRegionMarkup)
            : (viewModel.navPanelPosition === 'bottom'
                ? (questionRegionMarkup + navigationRegionMarkup)
                : (navigationRegionMarkup + questionRegionMarkup));

        var examLayoutMarkup = '<div class="' + viewModel.examLayoutClass + '" data-cbt-exam-layout="1">' + layoutContent + '</div>';
        var calculatorMarkup = renderCalculatorPanel();
        var stageShellClass = 'cbt-exam-stage-shell cbt-calc-pos-' + viewModel.calculatorPanelPosition + ((viewModel.calculatorEnabled && state.calculatorVisible) ? '' : ' is-calc-hidden');
        var fullscreenPromptMarkup = renderExamFullscreenPrompt();
        var stageContent;

        if (viewModel.calculatorPanelPosition === 'left') {
            stageContent = calculatorMarkup + examLayoutMarkup;
        } else if (viewModel.calculatorPanelPosition === 'right') {
            stageContent = examLayoutMarkup + calculatorMarkup;
        } else if (viewModel.calculatorPanelPosition === 'top') {
            stageContent = calculatorMarkup + examLayoutMarkup;
        } else {
            stageContent = examLayoutMarkup + calculatorMarkup;
        }

        return '<div class="' + stageShellClass + '" data-cbt-exam-shell="1">' + fullscreenPromptMarkup + noticeRegionMarkup + stageContent + '</div>';
    }

    return {
        renderExamRegions: renderExamRegions,
        renderExamStage: renderExamStageShell,
        renderExamStageShell: renderExamStageShell
    };
}
