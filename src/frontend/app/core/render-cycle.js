import { NAV_SIDE_LAYOUT_BREAKPOINT } from './config';

export function createRenderCycleManager(deps) {
    var applyUiPreferences = deps.applyUiPreferences;
    var documentRef = deps.documentRef;
    var enhanceRichMath = deps.enhanceRichMath;
    var getEffectiveNavPanelPosition = deps.getEffectiveNavPanelPosition;
    var maybePrefetchExamRuntime = deps.maybePrefetchExamRuntime;
    var recordRenderPerformed = deps.recordRenderPerformed;
    var recordRenderScheduled = deps.recordRenderScheduled;
    var recordTimeline = deps.recordTimeline;
    var recordRuntimeSnapshot = deps.recordRuntimeSnapshot;
    var refreshDebugPanel = deps.refreshDebugPanel;
    var renderExamRegions = deps.renderExamRegions;
    var renderBody = deps.renderBody;
    var renderAuthProgressOverlay = typeof deps.renderAuthProgressOverlay === 'function'
        ? deps.renderAuthProgressOverlay
        : function () {
            return '';
        };
    var renderResultProgressOverlay = typeof deps.renderResultProgressOverlay === 'function'
        ? deps.renderResultProgressOverlay
        : function () {
            return '';
        };
    var renderSessionRecoveryOverlay = typeof deps.renderSessionRecoveryOverlay === 'function'
        ? deps.renderSessionRecoveryOverlay
        : function () {
            return '';
        };
    var renderFinishConfirmModal = deps.renderFinishConfirmModal;
    var renderRichZoomModal = deps.renderRichZoomModal;
    var renderTopbar = deps.renderTopbar;
    var renderUserPhotoModal = deps.renderUserPhotoModal;
    var root = deps.root;
    var state = deps.state;
    var syncIdleDetectionState = deps.syncIdleDetectionState;
    var updateQuestionPrefetchIndicator = deps.updateQuestionPrefetchIndicator;
    var updateTimerLabel = deps.updateTimerLabel;
    var windowRef = deps.windowRef;

    var lastRenderedStage = '';
    var lastRenderedMarkup = '';
    var navGridLayoutFrameId = 0;
    var renderFrameId = 0;
    var pendingRenderReason = 'bootstrap';
    var pendingRenderMeta = {};
    var pendingRenderOptions = {};
    var QUESTION_SUBREGION_NAMES = [
        'questionHead',
        'questionQuickNav',
        'questionStem',
        'questionInput',
        'questionSaveFeedback',
        'questionFooterProgress',
        'questionFooterSync'
    ];

    function clampScrollOffset(value, maxOffset) {
        var safeValue = Number(value);
        var safeMaxOffset = Math.max(0, Number(maxOffset) || 0);

        if (!Number.isFinite(safeValue)) {
            safeValue = 0;
        }

        return Math.max(0, Math.min(safeMaxOffset, safeValue));
    }

    function setElementScrollPosition(element, axis, nextOffset) {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        var isHorizontal = axis === 'left';
        var maxOffset = isHorizontal
            ? Math.max(0, (Number(element.scrollWidth) || 0) - (Number(element.clientWidth) || 0))
            : Math.max(0, (Number(element.scrollHeight) || 0) - (Number(element.clientHeight) || 0));
        var safeOffset = clampScrollOffset(nextOffset, maxOffset);

        if (isHorizontal) {
            if (Math.abs((Number(element.scrollLeft) || 0) - safeOffset) < 1) {
                return;
            }
            element.scrollLeft = safeOffset;
            return;
        }

        if (Math.abs((Number(element.scrollTop) || 0) - safeOffset) < 1) {
            return;
        }
        element.scrollTop = safeOffset;
    }

    function ensureCurrentNavigationItemVisible() {
        if (state.stage !== 'exam' || !state.navPanelVisible) {
            return;
        }

        var navGrid = root.querySelector('.cbt-nav-grid');
        if (!(navGrid instanceof HTMLElement)) {
            return;
        }

        var navPosition = resolveRenderedNavigationPosition();
        var treatAsTopLayout = isHorizontalNavigationLayout(navPosition);

        var currentItem = navGrid.querySelector('.cbt-nav-btn.is-current');
        if (!(currentItem instanceof HTMLElement)) {
            return;
        }

        var gutter = 14;
        if (treatAsTopLayout) {
            var itemLeft = Number(currentItem.offsetLeft) || 0;
            var itemWidth = Number(currentItem.offsetWidth) || 0;
            var viewportLeft = Number(navGrid.scrollLeft) || 0;
            var viewportRight = viewportLeft + (Number(navGrid.clientWidth) || 0);
            var itemRight = itemLeft + itemWidth;

            if (itemLeft < (viewportLeft + gutter)) {
                setElementScrollPosition(navGrid, 'left', itemLeft - gutter);
                return;
            }

            if (itemRight > (viewportRight - gutter)) {
                setElementScrollPosition(
                    navGrid,
                    'left',
                    itemLeft - (((Number(navGrid.clientWidth) || 0) - itemWidth) / 2)
                );
            }
            return;
        }

        var itemTop = Number(currentItem.offsetTop) || 0;
        var itemHeight = Number(currentItem.offsetHeight) || 0;
        var viewportTop = Number(navGrid.scrollTop) || 0;
        var viewportBottom = viewportTop + (Number(navGrid.clientHeight) || 0);
        var itemBottom = itemTop + itemHeight;

        if (itemTop < (viewportTop + gutter)) {
            setElementScrollPosition(navGrid, 'top', itemTop - gutter);
            return;
        }

        if (itemBottom > (viewportBottom - gutter)) {
            setElementScrollPosition(
                navGrid,
                'top',
                itemTop - (((Number(navGrid.clientHeight) || 0) - itemHeight) / 2)
            );
        }
    }

    function updateNavigationGridRows() {
        if (state.stage !== 'exam' || !state.navPanelVisible) {
            return;
        }

        var navGrid = root.querySelector('.cbt-nav-grid');
        if (!(navGrid instanceof HTMLElement)) {
            return;
        }

        var navPosition = resolveRenderedNavigationPosition();
        var treatAsTopLayout = isHorizontalNavigationLayout(navPosition);
        if (!treatAsTopLayout) {
            navGrid.style.setProperty('--cbt-nav-rows', '1');
            return;
        }

        var navItems = navGrid.querySelectorAll('.cbt-nav-btn');
        var itemCount = navItems ? navItems.length : 0;
        if (itemCount <= 0) {
            navGrid.style.setProperty('--cbt-nav-rows', '1');
            return;
        }

        var firstItem = navItems[0];
        if (!(firstItem instanceof HTMLElement)) {
            navGrid.style.setProperty('--cbt-nav-rows', '1');
            return;
        }

        var availableWidth = navGrid.clientWidth;
        if (availableWidth <= 0) {
            return;
        }

        var navGridStyle = windowRef.getComputedStyle(navGrid);
        var columnGap = parseFloat(String(navGridStyle.columnGap || navGridStyle.gap || '0'));
        if (!Number.isFinite(columnGap) || columnGap < 0) {
            columnGap = 0;
        }

        var itemWidth = firstItem.offsetWidth;
        if (!Number.isFinite(itemWidth) || itemWidth <= 0) {
            itemWidth = parseFloat(String(navGridStyle.gridAutoColumns || '0'));
        }
        if (!Number.isFinite(itemWidth) || itemWidth <= 0) {
            itemWidth = 46;
        }

        var singleRowRequiredWidth = (itemWidth * itemCount) + (columnGap * Math.max(0, itemCount - 1));
        var targetRows = singleRowRequiredWidth <= (availableWidth + 1) ? '1' : '2';
        if (navGrid.style.getPropertyValue('--cbt-nav-rows') !== targetRows) {
            navGrid.style.setProperty('--cbt-nav-rows', targetRows);
        }
    }

    function resolveRenderedNavigationPosition() {
        var examLayout = root.querySelector('.cbt-exam-layout');
        if (examLayout instanceof HTMLElement) {
            if (examLayout.classList.contains('cbt-nav-pos-left')) {
                return 'left';
            }
            if (examLayout.classList.contains('cbt-nav-pos-right')) {
                return 'right';
            }
            if (examLayout.classList.contains('cbt-nav-pos-bottom')) {
                return 'bottom';
            }
            return 'top';
        }

        return getEffectiveNavPanelPosition();
    }

    function isHorizontalNavigationLayout(navPosition) {
        if (navPosition === 'bottom') {
            return false;
        }

        return (navPosition === 'top') || windowRef.innerWidth <= NAV_SIDE_LAYOUT_BREAKPOINT;
    }

    function scheduleNavigationGridLayout() {
        if (navGridLayoutFrameId) {
            return;
        }

        navGridLayoutFrameId = windowRef.requestAnimationFrame(function () {
            navGridLayoutFrameId = 0;
            updateNavigationGridRows();
            ensureCurrentNavigationItemVisible();
        });
    }

    function fitLoginHeroSchoolName() {
        if (state.stage !== 'login') {
            return;
        }

        var titleNode = root.querySelector('.cbt-login-hero-heading h1');
        if (!(titleNode instanceof HTMLElement)) {
            return;
        }

        titleNode.style.removeProperty('font-size');

        var computed = windowRef.getComputedStyle(titleNode);
        var baseFontSize = parseFloat(String(computed.fontSize || '0'));
        if (!Number.isFinite(baseFontSize) || baseFontSize <= 0) {
            return;
        }

        var lineHeight = parseFloat(String(computed.lineHeight || '0'));
        if (!Number.isFinite(lineHeight) || lineHeight <= 0) {
            lineHeight = baseFontSize * 1.08;
        }

        var fitsInTwoLines = function () {
            var currentComputed = windowRef.getComputedStyle(titleNode);
            var currentLineHeight = parseFloat(String(currentComputed.lineHeight || '0'));
            if (!Number.isFinite(currentLineHeight) || currentLineHeight <= 0) {
                var currentFontSize = parseFloat(String(currentComputed.fontSize || '0'));
                currentLineHeight = (Number.isFinite(currentFontSize) && currentFontSize > 0) ? currentFontSize * 1.08 : lineHeight;
            }
            var allowedHeight = (currentLineHeight * 2) + 1;
            return titleNode.scrollHeight <= allowedHeight;
        };

        if (fitsInTwoLines()) {
            return;
        }

        var minFontSize = Math.max(20, Math.round(baseFontSize * 0.58));
        var currentFontSize = Math.round(baseFontSize);
        while (currentFontSize > minFontSize) {
            currentFontSize -= 1;
            titleNode.style.fontSize = String(currentFontSize) + 'px';
            if (fitsInTwoLines()) {
                return;
            }
        }
    }

    function syncBodyStageClass() {
        if (!documentRef.body) {
            return;
        }

        var classes = ['cbt-stage-login', 'cbt-stage-confirm', 'cbt-stage-exam', 'cbt-stage-result'];
        for (var i = 0; i < classes.length; i++) {
            documentRef.body.classList.remove(classes[i]);
        }
        documentRef.body.classList.add('cbt-stage-' + String(state.stage || 'login'));
    }

    function buildRuntimeSnapshot(reason, meta) {
        return {
            attemptId: Number(state.attemptId) || 0,
            busy: Boolean(state.busy),
            calculatorPosition: String(state.calculatorPosition || ''),
            calculatorVisible: Boolean(state.calculatorVisible),
            connectionStatus: String(state.connectionStatus || 'online'),
            currentIndex: Number(state.currentIndex) || 0,
            fullscreen: Boolean(state.isFullscreenActive),
            isOpeningAttempt: Boolean(state.isOpeningAttempt),
            isFinishing: Boolean(state.isFinishing),
            lastRenderReason: reason,
            lastRenderMeta: meta,
            lastSyncError: String(state.lastSyncError || ''),
            navPanelPosition: String(getEffectiveNavPanelPosition ? getEffectiveNavPanelPosition() : ''),
            navPanelVisible: Boolean(state.navPanelVisible),
            navQuestionFilter: String(state.navQuestionFilter || ''),
            navigationRefreshing: Boolean(state.navigationRefreshing),
            pendingSyncCount: Number(state.pendingSyncCount) || 0,
            questionCount: Number(state.totalQuestions) || 0,
            questionRegionRefreshing: Boolean(state.questionRegionRefreshing),
            questionRevisionRefreshing: Boolean(state.questionRevisionRefreshing),
            result: state.result && typeof state.result === 'object'
                ? {
                    is_passed: Number(state.result.is_passed) || 0,
                    kkm_percentage: Number(state.result.kkm_percentage) || 0,
                    max_score: Number(state.result.max_score) || 0,
                    passing_score: Number(state.result.passing_score) || 0,
                    result_tone: String(state.result.result_tone || ''),
                    review_summary: state.result.review_summary && typeof state.result.review_summary === 'object'
                        ? state.result.review_summary
                        : null,
                    score: Number(state.result.score) || 0
                }
                : null,
            selectedExamId: Number(state.selectedExamId) || 0,
            stage: String(state.stage || 'login')
        };
    }

    function runPostRenderEffects(currentStage, currentReason, currentMeta) {
        if (typeof enhanceRichMath === 'function') {
            var mathEnhancementResult = enhanceRichMath();
            if (mathEnhancementResult && typeof mathEnhancementResult.catch === 'function') {
                mathEnhancementResult.catch(function () {});
            }
        }
        applyUiPreferences();
        syncBodyStageClass();
        updateTimerLabel();
        fitLoginHeroSchoolName();
        scheduleNavigationGridLayout();
        updateQuestionPrefetchIndicator();
        maybePrefetchExamRuntime();
        if (typeof syncIdleDetectionState === 'function') {
            syncIdleDetectionState();
        }
        if (typeof recordRenderPerformed === 'function') {
            recordRenderPerformed(currentReason, currentMeta, currentStage);
        }
        if (typeof recordRuntimeSnapshot === 'function') {
            recordRuntimeSnapshot(buildRuntimeSnapshot(currentReason, currentMeta));
        }
        if (typeof refreshDebugPanel === 'function') {
            refreshDebugPanel();
        }
    }

    function performRender() {
        var currentStage = String(state.stage || 'login');
        var currentReason = String(pendingRenderReason || 'unknown');
        var currentMeta = pendingRenderMeta && typeof pendingRenderMeta === 'object'
            ? pendingRenderMeta
            : {};
        var currentOptions = pendingRenderOptions && typeof pendingRenderOptions === 'object'
            ? pendingRenderOptions
            : {};

        renderFrameId = 0;
        pendingRenderReason = 'unknown';
        pendingRenderMeta = {};
        pendingRenderOptions = {};

        var loadingMarkup = state.busy
            && !state.sessionRecoveryVisible
            && !state.resultProgressVisible
            && state.stage !== 'exam'
            && state.stage !== 'login'
            && state.stage !== 'confirm'
            ? '<div class="cbt-loading" role="status" aria-live="polite"><span class="cbt-loading-dot" aria-hidden="true"></span><span>Memproses...</span></div>'
            : '';
        var showTopbar = state.stage !== 'login';
        var containerClass = showTopbar ? 'cbt-container' : 'cbt-container cbt-container-login';
        var stageChanged = lastRenderedStage !== currentStage;
        var stagePanelClass = 'cbt-stage-panel cbt-stage-panel-' + currentStage + (stageChanged ? ' is-stage-enter' : '');

        var nextMarkup = [
            '<div class="cbt-app">',
            showTopbar ? renderTopbar() : '',
            '<main class="' + containerClass + '">',
            loadingMarkup,
            '<section class="' + stagePanelClass + '">',
            renderBody(),
            '</section>',
            '</main>',
            renderFinishConfirmModal(),
            renderRichZoomModal(),
            renderUserPhotoModal(),
            renderAuthProgressOverlay(),
            renderResultProgressOverlay(),
            renderSessionRecoveryOverlay(),
            '</div>'
        ].join('');

        if (nextMarkup !== lastRenderedMarkup) {
            root.innerHTML = nextMarkup;
            lastRenderedMarkup = nextMarkup;
        }

        if (stageChanged && typeof recordTimeline === 'function') {
            recordTimeline('stage:' + currentStage, 'Stage aktif berubah ke ' + currentStage + '.', {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: Number(state.selectedExamId) || 0,
                stage: currentStage
            });
        }

        lastRenderedStage = currentStage;
        if (!(currentOptions && currentOptions.skipPostRenderEffects)) {
            runPostRenderEffects(currentStage, currentReason, currentMeta);
        }
    }

    function cancelPendingRender() {
        if (!renderFrameId) {
            return;
        }

        if (windowRef && typeof windowRef.cancelAnimationFrame === 'function') {
            windowRef.cancelAnimationFrame(renderFrameId);
        }
        if (windowRef && typeof windowRef.clearTimeout === 'function') {
            windowRef.clearTimeout(renderFrameId);
        }
        renderFrameId = 0;
    }

    function patchExamRegions(regions, reason, meta) {
        var currentStage = String(state.stage || 'login');
        var currentReason = String(reason || 'exam-partial');
        var currentMeta = meta && typeof meta === 'object' ? meta : {};
        var safeRegions = regions && typeof regions === 'object' ? regions : null;

        if (currentStage !== 'exam' || !safeRegions || typeof renderExamRegions !== 'function') {
            return false;
        }

        var examShell = root.querySelector('[data-cbt-exam-shell="1"]');
        if (!(examShell instanceof HTMLElement)) {
            return false;
        }

        var regionMarkup = renderExamRegions();
        if (!regionMarkup || typeof regionMarkup !== 'object') {
            return false;
        }

        var patched = false;
        ['notice', 'navigation'].forEach(function (regionName) {
            if (!safeRegions[regionName] || typeof regionMarkup[regionName] !== 'string') {
                return;
            }

            var regionNode = root.querySelector('[data-cbt-exam-region="' + regionName + '"]');
            if (!(regionNode instanceof HTMLElement)) {
                return;
            }

            regionNode.innerHTML = regionMarkup[regionName];
            patched = true;
        });

        var questionRequested = !!safeRegions.question;
        var requestedQuestionSubregions = QUESTION_SUBREGION_NAMES.filter(function (regionName) {
            return !!safeRegions[regionName];
        });
        var shouldFallbackToFullQuestionPatch = questionRequested;

        if (!shouldFallbackToFullQuestionPatch && requestedQuestionSubregions.length > 0) {
            var questionSubregions = regionMarkup.questionSubregions && typeof regionMarkup.questionSubregions === 'object'
                ? regionMarkup.questionSubregions
                : null;

            if (!questionSubregions) {
                shouldFallbackToFullQuestionPatch = true;
            } else {
                requestedQuestionSubregions.forEach(function (regionName) {
                    if (shouldFallbackToFullQuestionPatch) {
                        return;
                    }

                    if (typeof questionSubregions[regionName] !== 'string') {
                        shouldFallbackToFullQuestionPatch = true;
                        return;
                    }

                    var regionNodes = root.querySelectorAll('[data-cbt-exam-question-region="' + regionName + '"]');
                    if (!regionNodes.length) {
                        shouldFallbackToFullQuestionPatch = true;
                        return;
                    }

                    regionNodes.forEach(function (regionNode) {
                        if (regionNode instanceof HTMLElement) {
                            regionNode.innerHTML = questionSubregions[regionName];
                            patched = true;
                        }
                    });
                });
            }
        }

        if (shouldFallbackToFullQuestionPatch) {
            if (typeof regionMarkup.question !== 'string') {
                return false;
            }

            var questionRegionNode = root.querySelector('[data-cbt-exam-region="question"]');
            if (!(questionRegionNode instanceof HTMLElement)) {
                return false;
            }

            questionRegionNode.innerHTML = regionMarkup.question;
            patched = true;
        }

        if (!patched) {
            return false;
        }

        cancelPendingRender();
        runPostRenderEffects(currentStage, currentReason, Object.assign({
            partial: true,
            regions: Object.keys(safeRegions).filter(function (key) {
                return Boolean(safeRegions[key]);
            })
        }, currentMeta));
        return true;
    }

    function render(reason, meta, options) {
        if (typeof recordRenderScheduled === 'function') {
            recordRenderScheduled(reason, meta, String(state.stage || 'login'));
        }

        pendingRenderReason = String(reason || 'unknown');
        pendingRenderMeta = meta && typeof meta === 'object' ? meta : {};
        pendingRenderOptions = options && typeof options === 'object' ? options : {};

        if (lastRenderedMarkup === '' && !renderFrameId) {
            performRender();
            return;
        }

        if (pendingRenderOptions.immediate) {
            cancelPendingRender();
            performRender();
            return;
        }

        if (renderFrameId) {
            return;
        }

        if (windowRef && typeof windowRef.requestAnimationFrame === 'function') {
            renderFrameId = windowRef.requestAnimationFrame(performRender);
            return;
        }

        renderFrameId = windowRef.setTimeout(performRender, 16);
    }

    return {
        fitLoginHeroSchoolName: fitLoginHeroSchoolName,
        patchExamRegions: patchExamRegions,
        render: render,
        scheduleNavigationGridLayout: scheduleNavigationGridLayout,
        syncBodyStageClass: syncBodyStageClass
    };
}
