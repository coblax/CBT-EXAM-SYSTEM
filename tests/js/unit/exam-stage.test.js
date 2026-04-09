import { describe, expect, it } from 'vitest';
import { createExamStageRenderer } from '../../../src/frontend/app/stages/exam.js';

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function createFixture(overrides = {}) {
    var state = Object.assign({
        attemptId: 77,
        busy: true,
        currentIndex: 0,
        error: '',
        isOpeningAttempt: true,
        openingAttemptProgressDetail: 'Mengambil jendela soal pertama dan jawaban yang sudah pernah tersimpan.',
        openingAttemptProgressPercent: 76,
        openingAttemptProgressStatus: 'Memuat soal awal',
        openingAttemptProgressStepIndex: 4,
        openingAttemptProgressStepTotal: 5,
        stage: 'exam'
    }, overrides.state || {});

    return createExamStageRenderer({
        state,
        escapeHtml,
        renderAlert: function () {
            return '';
        },
        renderQuestionPrefetchIndicator: function () {
            return '';
        },
        renderQuestionFontControls: function () {
            return '';
        },
        renderQuestionStem: function () {
            return '';
        },
        renderQuestionInput: function () {
            return '';
        },
        renderNavigationAnswerBadges: function () {
            return '';
        },
        renderNavigationQuestionTypeBadge: function () {
            return '';
        },
        renderExamFullscreenPrompt: function () {
            return '';
        },
        renderCalculatorToggleButton: function () {
            return '';
        },
        renderCalculatorPanel: function () {
            return '';
        },
        formatScoreValue: function (value) {
            var number = Number(value);
            return Number.isFinite(number) ? String(number % 1 === 0 ? number.toFixed(0) : number.toFixed(2)) : '0';
        },
        formatQuestionType: function (value) {
            return String(value || '');
        },
        getSelectedExam: function () {
            return null;
        },
        getQuestionCount: function () {
            return 0;
        },
        getQuestionIdAtIndex: function () {
            return 0;
        },
        getQuestionManifestById: function () {
            return null;
        },
        getQuestionPayloadById: function () {
            return null;
        },
        ensureQuestionWindowForIndex: function () {
            return Promise.resolve(null);
        },
        refreshAttemptQuestionRevision: function () {
            return Promise.resolve(null);
        },
        getQuestionDisplayNumber: function () {
            return '1';
        },
        getExamProgressSummary: function () {
            return {
                answeredQuestions: 0,
                doubtfulQuestions: 0
            };
        },
        getEffectiveNavPanelPosition: function () {
            return 'right';
        },
        getEffectiveCalculatorPanelPosition: function () {
            return 'bottom';
        },
        getNavigationQuestionEntries: function () {
            return [];
        },
        normalizeNavigationQuestionFilter: function (value) {
            return String(value || 'all');
        },
        navigationQuestionFilterEmptyMessage: function () {
            return '';
        },
        navigationQuestionTypeBadgeConfig: function () {
            return null;
        },
        isCompactNavViewport: function () {
            return false;
        },
        isExamAnswerEditingLocked: function () {
            return false;
        },
        getExamFooterSyncMeta: function () {
            return {
                label: '',
                tone: 'neutral',
                value: ''
            };
        },
        getQuestionSaveFeedback: function () {
            return null;
        }
    });
}

function createAnsweredExamFixture(overrides = {}) {
    var state = Object.assign({
        attemptId: 77,
        busy: false,
        currentIndex: 1,
        error: '',
        examLockedForPendingFinish: false,
        isFinishing: false,
        isOpeningAttempt: false,
        openingAttemptProgressDetail: '',
        openingAttemptProgressPercent: 0,
        openingAttemptProgressStatus: '',
        openingAttemptProgressStepIndex: 0,
        openingAttemptProgressStepTotal: 0,
        stage: 'exam'
    }, overrides.state || {});

    var question = {
        id: 22,
        points: 1,
        question_type: 'multiple_choice'
    };

    return createExamStageRenderer({
        state,
        escapeHtml,
        renderAlert: function () {
            return '';
        },
        renderQuestionPrefetchIndicator: function () {
            return '';
        },
        renderQuestionFontControls: function () {
            return '';
        },
        renderQuestionStem: function () {
            return '<p>Stem soal</p>';
        },
        renderQuestionInput: function () {
            return '<div class="cbt-options"><label>Opsi</label></div>';
        },
        renderNavigationAnswerBadges: function () {
            return '';
        },
        renderNavigationQuestionTypeBadge: function () {
            return '';
        },
        renderExamFullscreenPrompt: function () {
            return '';
        },
        renderCalculatorToggleButton: function () {
            return '';
        },
        renderCalculatorPanel: function () {
            return '';
        },
        formatScoreValue: function (value) {
            var number = Number(value);
            return Number.isFinite(number) ? String(number % 1 === 0 ? number.toFixed(0) : number.toFixed(2)) : '0';
        },
        formatQuestionType: function () {
            return 'Multiple Choice';
        },
        getSelectedExam: function () {
            return {
                enable_calculator: 1,
                title: 'Ujian Demo'
            };
        },
        getQuestionCount: function () {
            return 3;
        },
        getQuestionIdAtIndex: function () {
            return 22;
        },
        getQuestionManifestById: function () {
            return question;
        },
        getQuestionPayloadById: function () {
            return question;
        },
        ensureQuestionWindowForIndex: function () {
            return Promise.resolve(null);
        },
        refreshAttemptQuestionRevision: function () {
            return Promise.resolve(null);
        },
        getQuestionDisplayNumber: function () {
            return '2';
        },
        getExamProgressSummary: function () {
            return {
                answeredQuestions: 3,
                doubtfulQuestions: 0
            };
        },
        getChangedQuestionCount: function () {
            return 0;
        },
        getQuestionRevisionMarkerCount: function () {
            return 0;
        },
        getEffectiveNavPanelPosition: function () {
            return 'right';
        },
        getEffectiveCalculatorPanelPosition: function () {
            return 'bottom';
        },
        getNavigationQuestionEntries: function () {
            return [];
        },
        normalizeNavigationQuestionFilter: function (value) {
            return String(value || 'all');
        },
        navigationQuestionFilterEmptyMessage: function () {
            return '';
        },
        navigationQuestionTypeBadgeConfig: function () {
            return {
                code: 'MC'
            };
        },
        isCompactNavViewport: function () {
            return false;
        },
        isQuestionAnswered: function () {
            return true;
        },
        isQuestionDoubtful: function () {
            return false;
        },
        isQuestionChanged: function () {
            return false;
        },
        isQuestionRevisionMarked: function () {
            return false;
        },
        isExamAnswerEditingLocked: function () {
            return false;
        },
        getExamFooterSyncMeta: function () {
            return {
                label: 'Sinkron',
                note: 'Semua aman',
                tone: 'neutral',
                value: 'Online'
            };
        },
        getQuestionSaveFeedback: function () {
            return {
                detail: 'Jawaban soal ini sudah aman tersimpan di server.',
                isVisible: true,
                label: 'Tersimpan',
                tone: 'saved'
            };
        }
    });
}

function createLoadingQuestionFixture(questionType, overrides = {}) {
    var state = Object.assign({
        attemptId: 77,
        busy: false,
        calculatorVisible: false,
        currentIndex: 1,
        error: '',
        examLockedForPendingFinish: false,
        isFinishing: false,
        isOpeningAttempt: false,
        navPanelVisible: false,
        openingAttemptProgressDetail: '',
        openingAttemptProgressPercent: 0,
        openingAttemptProgressStatus: '',
        openingAttemptProgressStepIndex: 0,
        openingAttemptProgressStepTotal: 0,
        questionRegionRefreshing: true,
        stage: 'exam'
    }, overrides.state || {});

    var manifestQuestion = questionType
        ? {
            id: 22,
            points: questionType === 'essay' ? 10 : 1,
            question_number: 2,
            question_type: questionType
        }
        : null;

    return createExamStageRenderer({
        state,
        escapeHtml,
        renderAlert: function () {
            return '';
        },
        renderQuestionPrefetchIndicator: function () {
            return '';
        },
        renderQuestionFontControls: function () {
            return '<div class="cbt-font-controls"></div>';
        },
        renderQuestionStem: function () {
            return '';
        },
        renderQuestionInput: function () {
            return '';
        },
        renderNavigationAnswerBadges: function () {
            return '';
        },
        renderNavigationQuestionTypeBadge: function () {
            return '';
        },
        renderExamFullscreenPrompt: function () {
            return '';
        },
        renderCalculatorToggleButton: function () {
            return '<button type="button" class="cbt-calculator-toggle">Calc</button>';
        },
        renderCalculatorPanel: function () {
            return '';
        },
        formatScoreValue: function (value) {
            var number = Number(value);
            return Number.isFinite(number) ? String(number % 1 === 0 ? number.toFixed(0) : number.toFixed(2)) : '0';
        },
        formatQuestionType: function (value) {
            var type = String(value || '');
            if (type === 'multiple_choice') {
                return 'Multiple Choice';
            }
            if (type === 'multiple_answer') {
                return 'Multiple Answer';
            }
            if (type === 'true_false_matrix') {
                return 'True False Matrix';
            }
            if (type === 'short_answer') {
                return 'Short Answer';
            }
            if (type === 'essay' || type === 'text') {
                return 'Essay';
            }
            return type;
        },
        getSelectedExam: function () {
            return {
                enable_calculator: 1,
                title: 'Ujian Demo'
            };
        },
        getQuestionCount: function () {
            return 3;
        },
        getQuestionIdAtIndex: function () {
            return 22;
        },
        getQuestionManifestById: function () {
            return manifestQuestion;
        },
        getQuestionPayloadById: function () {
            return null;
        },
        ensureQuestionWindowForIndex: function () {
            return Promise.resolve(null);
        },
        refreshAttemptQuestionRevision: function () {
            return Promise.resolve(null);
        },
        getQuestionDisplayNumber: function (question, fallbackIndex) {
            return question && question.question_number ? String(question.question_number) : String(Math.max(1, Number(fallbackIndex) + 1));
        },
        getExamProgressSummary: function () {
            return {
                answeredQuestions: 1,
                doubtfulQuestions: 0
            };
        },
        getChangedQuestionCount: function () {
            return 0;
        },
        getQuestionRevisionMarkerCount: function () {
            return 0;
        },
        getEffectiveNavPanelPosition: function () {
            return 'right';
        },
        getEffectiveCalculatorPanelPosition: function () {
            return 'bottom';
        },
        getNavigationQuestionEntries: function () {
            return [];
        },
        normalizeNavigationQuestionFilter: function (value) {
            return String(value || 'all');
        },
        navigationQuestionFilterEmptyMessage: function () {
            return '';
        },
        navigationQuestionTypeBadgeConfig: function () {
            return {
                code: 'MC'
            };
        },
        isCompactNavViewport: function () {
            return false;
        },
        isExamAnswerEditingLocked: function () {
            return false;
        },
        getExamFooterSyncMeta: function () {
            return {
                label: 'Sinkron',
                note: 'Menunggu refresh',
                tone: 'neutral',
                value: 'Online'
            };
        },
        getQuestionSaveFeedback: function () {
            return null;
        }
    });
}

describe('createExamStageRenderer', function () {
    it('renders a staged progress card while an exam session is opening', function () {
        var renderer = createFixture();

        var markup = renderer.renderExamStageShell();

        expect(markup).toContain('Menyiapkan Ujian');
        expect(markup).toContain('Loading 4/5');
        expect(markup).toContain('Memuat soal awal');
        expect(markup).toContain('76%');
        expect(markup).toContain('cbt-exam-opening-progress');
        expect(markup).toContain('cbt-exam-opening-step is-current');
        expect(markup).toContain('Soal Awal');
    });

    it('renders queue and recovery actions inside the same opening shell', function () {
        var renderer = createFixture({
            state: {
                openingAttemptPhase: 'opening_waiting_queue',
                openingAttemptCanRetry: true,
                openingAttemptCanRefreshStatus: true,
                openingAttemptCanBack: true,
                openingAttemptQueuePosition: 14,
                openingAttemptQueueEstimatedWaitSeconds: 2,
                openingAttemptProgressStatus: 'Menunggu giliran masuk ujian'
            }
        });

        var markup = renderer.renderExamStageShell();

        expect(markup).toContain('Dalam Antrean');
        expect(markup).toContain('Posisi antrean: 14');
        expect(markup).toContain('data-action="retry-opening-attempt"');
        expect(markup).toContain('data-action="refresh-opening-attempt-status"');
        expect(markup).toContain('data-action="back-confirm"');
    });

    it('shows collect action on a non-last question once all questions are answered', function () {
        var renderer = createAnsweredExamFixture();

        var markup = renderer.renderExamStageShell();

        expect(markup).toContain('Tersimpan');
        expect(markup).toContain('Jawaban soal ini sudah aman tersimpan di server.');
        expect(markup).toContain('SEMUA SOAL SUDAH TERJAWAB. ANDA BISA LANGSUNG KUMPULKAN UJIAN DARI HALAMAN INI.');
        expect(markup).toContain('data-action="finish"');
        expect(markup).toContain('data-action="collect"');
        expect(markup).toContain('Kumpulkan Jawaban');
    });

    it('renders multiple choice skeleton content while the active payload is still loading', function () {
        var renderer = createLoadingQuestionFixture('multiple_choice');

        var markup = renderer.renderExamStageShell();

        expect(markup).toContain('cbt-question-skeleton is-multiple-choice');
        expect(markup).toContain('cbt-question-skeleton-option-marker is-radio');
        expect(markup).toContain('cbt-question-skeleton-media');
        expect(markup).toContain('Memperbarui Soal');
        expect(markup).not.toContain('cbt-card-inline-loading');
    });

    it('renders essay skeleton content with textarea placeholders', function () {
        var renderer = createLoadingQuestionFixture('essay');

        var markup = renderer.renderExamStageShell();

        expect(markup).toContain('cbt-question-skeleton is-textual');
        expect(markup).toContain('cbt-question-skeleton-textarea');
    });

    it('renders short answer skeleton content with input placeholders', function () {
        var renderer = createLoadingQuestionFixture('short_answer');

        var markup = renderer.renderExamStageShell();

        expect(markup).toContain('cbt-question-skeleton is-short-answer');
        expect(markup).toContain('cbt-question-skeleton-input-grid');
    });

    it('renders true false matrix skeleton content with a matrix layout', function () {
        var renderer = createLoadingQuestionFixture('true_false_matrix');

        var markup = renderer.renderExamStageShell();

        expect(markup).toContain('cbt-question-skeleton is-true-false-matrix');
        expect(markup).toContain('cbt-question-skeleton-matrix-row');
    });

    it('falls back to a generic skeleton when the active manifest is not available yet', function () {
        var renderer = createLoadingQuestionFixture('');

        var markup = renderer.renderExamStageShell();

        expect(markup).toContain('cbt-question-skeleton is-generic');
        expect(markup).toContain('cbt-question-quick-nav-top');
        expect(markup).toContain('cbt-question-exam-footer');
    });
});
