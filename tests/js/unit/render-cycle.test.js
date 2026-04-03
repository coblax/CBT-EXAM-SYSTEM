import { afterEach, describe, expect, it, vi } from 'vitest';
import { createRenderCycleManager } from '../../../src/frontend/app/core/render-cycle.js';

function createExamShellMarkup(regions) {
    return [
        '<div data-cbt-exam-shell="1">',
        '<div data-cbt-exam-region="notice">' + regions.notice + '</div>',
        '<div data-cbt-exam-region="navigation">' + regions.navigation + '</div>',
        '<div data-cbt-exam-region="question">' + regions.question + '</div>',
        '</div>'
    ].join('');
}

function createQuestionMarkup(payload) {
    return [
        '<section class="cbt-question-card">',
        '<div data-cbt-exam-question-region="questionHead">' + payload.questionSubregions.questionHead + '</div>',
        '<div class="cbt-question-body">',
        '<div class="cbt-question-quick-nav cbt-question-quick-nav-top" data-cbt-exam-question-region="questionQuickNav">' + payload.questionSubregions.questionQuickNav + '</div>',
        '<div class="cbt-question-stem" data-cbt-exam-question-region="questionStem">' + payload.questionSubregions.questionStem + '</div>',
        '<div data-cbt-exam-question-region="questionInput">' + payload.questionSubregions.questionInput + '</div>',
        '<div class="cbt-question-quick-nav cbt-question-quick-nav-bottom" data-cbt-exam-question-region="questionQuickNav">' + payload.questionSubregions.questionQuickNav + '</div>',
        '<div class="cbt-question-exam-footer-side">',
        '<div data-cbt-exam-question-region="questionFooterProgress">' + payload.questionSubregions.questionFooterProgress + '</div>',
        '<div data-cbt-exam-question-region="questionFooterSync">' + payload.questionSubregions.questionFooterSync + '</div>',
        '</div>',
        '</div>',
        '</section>'
    ].join('');
}

function createFixture(overrides) {
    overrides = overrides || {};
    var root = document.createElement('div');
    document.body.appendChild(root);
    var state = {
        attemptId: 55,
        currentIndex: 0,
        navPanelVisible: false,
        pendingSyncCount: 0,
        selectedExamId: 9,
        stage: 'exam',
        totalQuestions: 1
    };
    var currentRegions = {
        navigation: '<aside>Nav</aside>',
        notice: '',
        questionSubregions: {
            questionFooterProgress: '<div class="cbt-question-exam-footer-meta"><strong>100%</strong></div>',
            questionFooterSync: '<div class="cbt-question-exam-footer-meta cbt-question-exam-footer-meta-sync is-online"><strong>Online</strong></div>',
            questionHead: '<div class="cbt-question-head is-answered"><span>Head A</span></div>',
            questionInput: '<div class="cbt-options"><label>A</label></div>',
            questionQuickNav: '<button type="button">Next</button>',
            questionStem: '<p><img src="/img-a.png" alt="" />Stem A</p>'
        }
    };
    currentRegions.question = createQuestionMarkup(currentRegions);

    var manager = createRenderCycleManager({
        applyUiPreferences: overrides.applyUiPreferences || function () {},
        documentRef: document,
        enhanceRichMath: overrides.enhanceRichMath || function () {},
        getEffectiveNavPanelPosition: function () {
            return 'right';
        },
        maybePrefetchExamRuntime: overrides.maybePrefetchExamRuntime || function () {},
        recordRenderPerformed: overrides.recordRenderPerformed || function () {},
        recordRenderScheduled: function () {},
        recordTimeline: function () {},
        recordRuntimeSnapshot: function () {},
        refreshDebugPanel: function () {},
        renderExamRegions: function () {
            return currentRegions;
        },
        renderBody: function () {
            return createExamShellMarkup(currentRegions);
        },
        renderFinishConfirmModal: function () {
            return '';
        },
        renderTopbar: function () {
            return '';
        },
        renderUserPhotoModal: function () {
            return '';
        },
        root,
        state,
        syncIdleDetectionState: function () {},
        updateQuestionPrefetchIndicator: function () {},
        updateTimerLabel: function () {},
        windowRef: window
    });

    return {
        manager,
        root,
        setRegions: function (nextRegions) {
            currentRegions = Object.assign({}, currentRegions, nextRegions || {});
            currentRegions.questionSubregions = Object.assign({}, currentRegions.questionSubregions, nextRegions && nextRegions.questionSubregions ? nextRegions.questionSubregions : {});
            currentRegions.question = createQuestionMarkup(currentRegions);
        }
    };
}

afterEach(function () {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('createRenderCycleManager patchExamRegions', function () {
    it('patches question subregions without remounting the stem node', function () {
        var fixture = createFixture();

        fixture.manager.render('initial');

        var stemNode = document.querySelector('[data-cbt-exam-question-region="questionStem"]');
        var quickNavNodes = document.querySelectorAll('[data-cbt-exam-question-region="questionQuickNav"]');

        fixture.setRegions({
            questionSubregions: {
                questionFooterProgress: '<div class="cbt-question-exam-footer-meta"><strong>50%</strong></div>',
                questionFooterSync: '<div class="cbt-question-exam-footer-meta cbt-question-exam-footer-meta-sync is-pending"><strong>2 pending</strong></div>',
                questionHead: '<div class="cbt-question-head is-doubtful"><span>Head B</span></div>',
                questionInput: '<div class="cbt-options"><label>B</label></div>',
                questionQuickNav: '<button type="button">Prev</button>',
                questionStem: '<p><img src="/img-b.png" alt="" />Stem B</p>'
            }
        });

        var didPatch = fixture.manager.patchExamRegions({
            questionFooterProgress: true,
            questionFooterSync: true,
            questionHead: true,
            questionInput: true,
            questionQuickNav: true
        }, 'answer-change', {
            questionId: 10
        });

        expect(didPatch).toBe(true);
        expect(document.querySelector('[data-cbt-exam-question-region="questionStem"]')).toBe(stemNode);
        expect(stemNode.innerHTML).toContain('Stem A');
        expect(document.querySelector('[data-cbt-exam-question-region="questionHead"]').innerHTML).toContain('Head B');
        expect(document.querySelector('[data-cbt-exam-question-region="questionFooterSync"]').innerHTML).toContain('2 pending');
        expect(quickNavNodes).toHaveLength(2);
        quickNavNodes.forEach(function (node) {
            expect(node.innerHTML).toContain('Prev');
        });
    });

    it('falls back to a full question patch when a requested subregion node is missing', function () {
        var fixture = createFixture();

        fixture.manager.render('initial');

        var headRegion = document.querySelector('[data-cbt-exam-question-region="questionHead"]');
        headRegion.remove();

        fixture.setRegions({
            questionSubregions: {
                questionHead: '<div class="cbt-question-head is-doubtful"><span>Head B</span></div>',
                questionStem: '<p><img src="/img-b.png" alt="" />Stem B</p>'
            }
        });

        var didPatch = fixture.manager.patchExamRegions({
            questionHead: true
        }, 'fallback-question', {});

        expect(didPatch).toBe(true);
        expect(document.querySelector('[data-cbt-exam-question-region="questionStem"]').innerHTML).toContain('Stem B');
        expect(document.querySelector('[data-cbt-exam-question-region="questionHead"]').innerHTML).toContain('Head B');
    });

    it('keeps render flow stable when rich math enhancement is async', async function () {
        var enhanceRichMath = vi.fn(function () {
            return Promise.resolve(1);
        });
        var applyUiPreferences = vi.fn();
        var recordRenderPerformed = vi.fn();
        var fixture = createFixture({
            applyUiPreferences: applyUiPreferences,
            enhanceRichMath: enhanceRichMath,
            recordRenderPerformed: recordRenderPerformed
        });

        fixture.manager.render('initial');
        await Promise.resolve();

        expect(enhanceRichMath).toHaveBeenCalledTimes(1);
        expect(applyUiPreferences).toHaveBeenCalledTimes(1);
        expect(recordRenderPerformed).toHaveBeenCalledTimes(1);
        expect(document.querySelector('[data-cbt-exam-shell="1"]')).not.toBeNull();
    });
});
