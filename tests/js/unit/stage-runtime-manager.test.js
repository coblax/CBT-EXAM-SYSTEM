import { describe, expect, it } from 'vitest';
import { createStageRuntimeManager } from '../../../src/frontend/app/core/stage-runtime.js';

async function flushAsyncWork() {
    await Promise.resolve();
    await Promise.resolve();
    await new Promise(function (resolve) {
        setTimeout(resolve, 0);
    });
}

async function waitForAssertion(assertion, attempts = 20) {
    var remaining = Math.max(1, Number(attempts) || 1);
    var lastError = null;

    while (remaining > 0) {
        try {
            assertion();
            return;
        } catch (error) {
            lastError = error;
            remaining -= 1;
            if (remaining <= 0) {
                throw lastError;
            }
            await flushAsyncWork();
        }
    }
}

function createFixture(overrides = {}) {
    var calls = {
        render: [],
        timeline: []
    };
    var root = document.createElement('div');
    document.body.appendChild(root);

    var state = Object.assign({
        attemptId: 77,
        calculatorError: '',
        calculatorExpression: '',
        calculatorResult: '',
        calculatorVisible: false,
        currentIndex: 0,
        navPanelVisible: false,
        navQuestionFilter: 'all',
        questionRegionRefreshing: false,
        questionRevisionRefreshing: false,
        selectedExamId: 55,
        stage: 'exam'
    }, overrides.state || {});

    var diagnosticsManager = overrides.diagnosticsManager || {
        enabled: false,
        consumeFailNextChunkLoad: function () {
            return false;
        }
    };

    var manager = createStageRuntimeManager({
        diagnosticsManager: diagnosticsManager,
        state,
        root,
        escapeHtml: function (value) {
            return String(value || '');
        },
        formatQuestionType: function (value) {
            return String(value || '');
        },
        formatScoreValue: function (value) {
            return String(value || '');
        },
        getChangedQuestionCount: function () {
            return 0;
        },
        getQuestionRevisionMarkerCount: function () {
            return 0;
        },
        getEffectiveCalculatorPanelPosition: function () {
            return 'right';
        },
        getEffectiveNavPanelPosition: function () {
            return 'right';
        },
        getExamFooterSyncMeta: function () {
            return {};
        },
        getExamProgressSummary: function () {
            return {
                answeredQuestions: 0,
                totalQuestions: 0
            };
        },
        getNavigationQuestionEntries: function () {
            return [];
        },
        getQuestionCount: function () {
            return 1;
        },
        getQuestionDisplayNumber: function () {
            return 1;
        },
        getQuestionIdAtIndex: function () {
            return 101;
        },
        getQuestionManifestById: function () {
            return null;
        },
        getQuestionPayloadById: function () {
            return null;
        },
        getSelectedExam: function () {
            return Object.assign({
                enable_calculator: 1,
                id: 55,
                title: 'TEST Runtime Fixture'
            }, overrides.selectedExam || {});
        },
        isCompactNavViewport: function () {
            return false;
        },
        isCompactViewport: function () {
            return false;
        },
        isExamAnswerEditingLocked: function () {
            return false;
        },
        isQuestionAnswered: function () {
            return false;
        },
        isQuestionChanged: function () {
            return false;
        },
        isQuestionRevisionMarked: function () {
            return false;
        },
        isQuestionDoubtful: function () {
            return false;
        },
        navigationQuestionFilterEmptyMessage: function () {
            return 'Kosong';
        },
        navigationQuestionTypeBadgeConfig: function () {
            return {
                className: 'type-default',
                code: 'MC'
            };
        },
        normalizeCalculatorPanelPosition: function (value) {
            return String(value || 'right');
        },
        normalizeNavigationQuestionFilter: function (value) {
            return String(value || 'all');
        },
        questionOptionKey: function () {
            return 'A';
        },
        render: function (reason, meta, options) {
            calls.render.push({
                meta: meta || {},
                options: options || {},
                reason: String(reason || '')
            });
        },
        renderAlert: function () {
            return '';
        },
        renderExamFullscreenPrompt: function () {
            return '';
        },
        renderNavigationAnswerBadges: function () {
            return '';
        },
        renderNavigationQuestionTypeBadge: function () {
            return '';
        },
        renderQuestionFontControls: function () {
            return '';
        },
        renderQuestionInput: function () {
            return '';
        },
        renderQuestionPrefetchIndicator: function () {
            return '';
        },
        renderQuestionStem: function () {
            return '';
        },
        recordTimeline: function (kind, summary, meta) {
            calls.timeline.push({
                kind: String(kind || ''),
                meta: meta || {},
                summary: String(summary || '')
            });
        },
        safeRichHtml: function (value) {
            return String(value || '');
        },
        windowRef: globalThis
    });

    return {
        calls,
        manager,
        root,
        state
    };
}

describe('createStageRuntimeManager', function () {
    it('paints calculator loading immediately before the feature chunk resolves', async function () {
        var fixture = createFixture();

        fixture.manager.toggleCalculator();

        expect(fixture.calls.render[0]).toMatchObject({
            reason: 'toggle-calculator-open',
            meta: {
                phase: 'loading'
            },
            options: {
                immediate: true,
                skipPostRenderEffects: true
            }
        });
        expect(fixture.state.calculatorVisible).toBe(true);

        await waitForAssertion(function () {
            expect(fixture.manager.renderCalculatorPanel()).toContain('KALKULATOR');
        });
    });

    it('returns to a safe state when calculator chunk loading fails', async function () {
        var failedOnce = false;
        var fixture = createFixture({
            diagnosticsManager: {
                enabled: true,
                consumeFailNextChunkLoad: function (target) {
                    if (!failedOnce && target === 'calculator') {
                        failedOnce = true;
                        return true;
                    }
                    return false;
                }
            }
        });

        fixture.manager.toggleCalculator();
        await waitForAssertion(function () {
            expect(fixture.state.calculatorVisible).toBe(false);
            expect(String(fixture.state.notice || '')).toContain('Kalkulator gagal dimuat');
        });
    });

    it('renders exam stage fallback with retry and back controls when the exam chunk fails', async function () {
        var failedOnce = false;
        var fixture = createFixture({
            diagnosticsManager: {
                enabled: true,
                consumeFailNextChunkLoad: function (target) {
                    if (!failedOnce && target === 'exam') {
                        failedOnce = true;
                        return true;
                    }
                    return false;
                }
            }
        });

        fixture.manager.renderExamStageShell();

        await waitForAssertion(function () {
            var markup = fixture.manager.renderExamStageShell();
            expect(markup).toContain('Runtime Ujian Gagal Dimuat');
            expect(markup).toContain('data-action="retry-load-exam-stage"');
            expect(markup).toContain('data-action="back-confirm"');
        });
    });

    it('does not start a parallel exam chunk import while a load is already in flight', function () {
        var fixture = createFixture();

        fixture.manager.renderExamStageShell();
        fixture.manager.retryLoadExamStage();
        fixture.manager.retryLoadExamStage();

        expect(fixture.calls.timeline.filter(function (entry) {
            return entry.kind === 'chunk:exam:load:start';
        })).toHaveLength(1);
    });
});
