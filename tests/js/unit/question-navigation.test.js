import { afterEach, describe, expect, it, vi } from 'vitest';
import { createExamNavigationManager } from '../../../src/frontend/app/exam/navigation.js';
import { createAnswerInputManager } from '../../../src/frontend/app/exam/answer-inputs.js';
import { getShortAnswerKeys, getTrueFalseMatrixItems, normalizeTrueFalseMatrixAnswer, questionOptionKey } from '../../../src/frontend/app/exam/question-helpers.js';

function createQuestion(id, type, overrides = {}) {
    return Object.assign({
        id: Number(id) || 0,
        options: [],
        question_number: Number(overrides.question_number || 0) || 0,
        question_type: String(type || 'essay')
    }, overrides || {});
}

function createFixture(overrides = {}) {
    var calls = {
        acknowledgeQuestionRevisionMarker: [],
        clearMessages: 0,
        ensureQuestionWindowForIndex: [],
        operationOrder: [],
        persistCurrentAttemptUiStateLocally: 0,
        prefetchNextQuestionBatch: 0,
        queueQuestionAnswer: [],
        render: [],
        renderExamPartial: [],
        resetQuestionPrefetchIdleTimer: 0,
        scheduleAttemptUiStateSync: [],
        schedulePendingAnswerRetry: [],
        scheduleQuestionCachePersist: [],
        setActiveQuestionWindowForIndex: []
    };
    var questionLookup = Object.assign({
        101: createQuestion(101, 'multiple_choice', {
            options: [
                { id: 11, option_key: 'A' },
                { id: 12, option_key: 'B' }
            ],
            question_number: 1
        }),
        102: createQuestion(102, 'short_answer', {
            question_number: 2,
            short_answer_meta: {
                input_keys: ['A']
            }
        }),
        103: createQuestion(103, 'multiple_answer', {
            options: [
                { id: 31, option_key: 'A' },
                { id: 32, option_key: 'B' }
            ],
            question_number: 3
        })
    }, overrides.questionLookup || {});
    var questionOrderIds = Array.isArray(overrides.questionOrderIds) ? overrides.questionOrderIds.slice() : [101, 102, 103];
    var loadedQuestionIds = new Set(Array.isArray(overrides.loadedQuestionIds) ? overrides.loadedQuestionIds : questionOrderIds);
    var navigatorStatus = String(overrides.navigatorStatus || 'online');
    var state = Object.assign({
        answers: {
            101: 12,
            102: { A: 'jawaban' }
        },
        answeredQuestionLookup: {
            101: true,
            102: true
        },
        attemptId: 55,
        busy: false,
        currentIndex: 0,
        doubtful: {},
        error: '',
        finishConfirmOpen: false,
        isFinishing: false,
        navQuestionFilter: 'all',
        notice: '',
        questionOrderIds: questionOrderIds,
        questionRegionRefreshing: false,
        questions: questionOrderIds.map(function (questionId) {
            return questionLookup[questionId];
        }),
        stage: 'exam',
        userPhotoModalOpen: false
    }, overrides.state || {});
    var root = document.createElement('div');
    document.body.appendChild(root);

    var navigationManager = createExamNavigationManager({
        acknowledgeQuestionRevisionMarker: function (questionId) {
            calls.acknowledgeQuestionRevisionMarker.push(Number(questionId) || 0);
            return false;
        },
        attemptUiStateNavigationSyncDelayMs: 150,
        attemptUiStateSyncDelayMs: 300,
        clampQuestionIndex: function (index) {
            var maxIndex = Math.max(0, questionOrderIds.length - 1);
            return Math.max(0, Math.min(maxIndex, Math.floor(Number(index) || 0)));
        },
        clearStickyQuestionRevisionNotice: function () {
            return false;
        },
        clearMessages: function () {
            calls.clearMessages += 1;
            calls.operationOrder.push('clearMessages');
        },
        documentRef: document,
        ensureQuestionWindowForIndex: async function (index) {
            var safeIndex = Math.max(0, Math.min(questionOrderIds.length - 1, Math.floor(Number(index) || 0)));
            var questionId = questionOrderIds[safeIndex];
            loadedQuestionIds.add(questionId);
            calls.ensureQuestionWindowForIndex.push(safeIndex);
            calls.operationOrder.push('ensureQuestionWindowForIndex:' + String(safeIndex));
            return questionLookup[questionId] || null;
        },
        escapeHtml: function (value) {
            return String(value || '');
        },
        getNavigatorConnectionStatus: function () {
            return navigatorStatus;
        },
        getQuestionAtIndex: function (index) {
            var questionId = questionOrderIds[Math.max(0, Math.min(questionOrderIds.length - 1, Math.floor(Number(index) || 0)))] || 0;
            return questionLookup[questionId] || null;
        },
        getQuestionById: function (questionId) {
            return questionLookup[Number(questionId) || 0] || null;
        },
        getQuestionCount: function () {
            return questionOrderIds.length;
        },
        getQuestionIdAtIndex: function (index) {
            return Number(questionOrderIds[Math.max(0, Math.min(questionOrderIds.length - 1, Math.floor(Number(index) || 0)))]) || 0;
        },
        getShortAnswerKeys: getShortAnswerKeys,
        getTrueFalseMatrixItems: getTrueFalseMatrixItems,
        hasUsableLocalAnswerForQuestion: function (questionId) {
            return Object.prototype.hasOwnProperty.call(state.answers, Number(questionId) || 0);
        },
        isExamAnswerEditingLocked: function () {
            return false;
        },
        isNetworkConnectivityError: function (error) {
            return !!(error && error.isNetworkError);
        },
        isQuestionPayloadLoaded: function (questionId) {
            return loadedQuestionIds.has(Number(questionId) || 0);
        },
        navQuestionFilterAll: 'all',
        navQuestionFilterAnswered: 'answered',
        navQuestionFilterDoubtful: 'doubtful',
        navQuestionFilterUnanswered: 'unanswered',
        navigationQuestionTypeBadgeConfig: function (type) {
            return {
                className: 'type-' + String(type || ''),
                code: String(type || '').slice(0, 2).toUpperCase()
            };
        },
        normalizeTrueFalseMatrixAnswer: normalizeTrueFalseMatrixAnswer,
        persistCurrentAttemptUiStateLocally: function () {
            calls.persistCurrentAttemptUiStateLocally += 1;
            calls.operationOrder.push('persistCurrentAttemptUiStateLocally');
        },
        prefetchNextQuestionBatch: function () {
            calls.prefetchNextQuestionBatch += 1;
        },
        questionOptionKey: questionOptionKey,
        questionWindowSize: 2,
        queueQuestionAnswer: function (question) {
            calls.queueQuestionAnswer.push(Number(question && question.id) || 0);
            calls.operationOrder.push('queueQuestionAnswer:' + String(Number(question && question.id) || 0));
            return true;
        },
        render: function (reason, meta) {
            calls.render.push({
                meta: meta || null,
                reason: String(reason || '')
            });
            calls.operationOrder.push('render:' + String(reason || ''));
        },
        renderExamPartial: function (regions, reason, meta) {
            calls.renderExamPartial.push({
                meta: meta || null,
                reason: String(reason || ''),
                regions: regions || null
            });
            calls.operationOrder.push('renderExamPartial:' + String(reason || ''));
            return false;
        },
        resetQuestionPrefetchIdleTimer: function () {
            calls.resetQuestionPrefetchIdleTimer += 1;
        },
        resolveStoredAnswerValueForQuestion: function (question) {
            var questionId = Number(question && question.id) || 0;
            return Object.prototype.hasOwnProperty.call(state.answers, questionId)
                ? state.answers[questionId]
                : (question && Object.prototype.hasOwnProperty.call(question, 'existing_answer') ? question.existing_answer : null);
        },
        scheduleAttemptUiStateSync: function (delayMs) {
            calls.scheduleAttemptUiStateSync.push(Number(delayMs) || 0);
        },
        schedulePendingAnswerRetry: function (reason, meta) {
            calls.schedulePendingAnswerRetry.push({
                meta: meta || null,
                reason: String(reason || '')
            });
        },
        scheduleQuestionCachePersist: function (delayMs) {
            calls.scheduleQuestionCachePersist.push(Number(delayMs) || 0);
        },
        setActiveQuestionWindowForIndex: function (index, size) {
            calls.setActiveQuestionWindowForIndex.push({
                index: Number(index) || 0,
                size: Number(size) || 0
            });
        },
        state
    });

    var inputManager = createAnswerInputManager({
        autoSaveChoiceDelayMs: 200,
        autoSaveTextDelayMs: 500,
        clearMessages: function () {
            calls.clearMessages += 1;
        },
        normalizeExamToken: function (value) {
            return String(value || '');
        },
        render: function () {},
        root,
        scheduleAutoSave: function () {},
        scheduleQuestionCachePersist: function () {},
        state,
        updateSelectedExam: function () {}
    });

    return {
        calls,
        inputManager,
        navigationManager,
        questionLookup,
        setNavigatorStatus: function (nextStatus) {
            navigatorStatus = String(nextStatus || 'online');
        },
        state
    };
}

afterEach(function () {
    document.body.innerHTML = '';
});

describe('createExamNavigationManager', function () {
    it('keeps currentIndex clamped to a valid question and preserves per-question state across navigation jumps', async function () {
        var fixture = createFixture();

        await fixture.navigationManager.goToQuestion(99);

        expect(fixture.state.currentIndex).toBe(2);
        expect(fixture.state.answers[101]).toBe(12);
        expect(fixture.state.answers[102]).toEqual({ A: 'jawaban' });
        expect(fixture.calls.queueQuestionAnswer).toEqual([101]);
        expect(fixture.calls.setActiveQuestionWindowForIndex).toEqual([
            {
                index: 2,
                size: 2
            }
        ]);

        await fixture.navigationManager.goToQuestion(-10);

        expect(fixture.state.currentIndex).toBe(0);
        expect(fixture.calls.queueQuestionAnswer).toEqual([101, 103]);
    });

    it('keeps doubtful flags intact when answers change and the user moves away and back', async function () {
        var fixture = createFixture();
        var doubtfulButton = document.createElement('button');
        doubtfulButton.setAttribute('data-qid', '101');

        fixture.navigationManager.handleAction('toggle-doubtful', doubtfulButton);

        var input = document.createElement('textarea');
        input.setAttribute('data-action', 'answer-text');
        input.setAttribute('data-qid', '102');
        input.value = 'jawaban revisi';
        fixture.inputManager.handleInputTarget(input);

        await fixture.navigationManager.goToQuestion(1);
        await fixture.navigationManager.goToQuestion(0);

        expect(fixture.state.doubtful[101]).toBe(true);
        expect(fixture.state.answers[102]).toBe('jawaban revisi');
        expect(fixture.state.currentIndex).toBe(0);
    });

    it('filters navigation entries using answered and doubtful state without corrupting the active question', function () {
        var fixture = createFixture({
            state: {
                answers: {
                    101: 12
                },
                answeredQuestionLookup: {
                    101: true
                },
                currentIndex: 1,
                doubtful: {
                    103: true
                }
            }
        });

        expect(fixture.navigationManager.normalizeNavigationQuestionFilter('??')).toBe('all');
        expect(fixture.navigationManager.getNavigationQuestionEntries('answered').map(function (entry) {
            return entry.questionId;
        })).toEqual([101]);
        expect(fixture.navigationManager.getNavigationQuestionEntries('doubtful').map(function (entry) {
            return entry.questionId;
        })).toEqual([103]);
        expect(fixture.state.currentIndex).toBe(1);
    });

    it('shows a navigation transition before flushing the previous answer when the target payload is already loaded', async function () {
        var fixture = createFixture();

        await fixture.navigationManager.goToQuestion(1);

        expect(fixture.state.currentIndex).toBe(1);
        expect(fixture.state.navigationRefreshing).toBe(false);
        expect(fixture.calls.operationOrder.indexOf('render:navigation:question-transition')).toBeGreaterThanOrEqual(0);
        expect(fixture.calls.operationOrder.indexOf('queueQuestionAnswer:101')).toBeGreaterThanOrEqual(0);
        expect(fixture.calls.operationOrder.indexOf('render:navigation:question-transition')).toBeLessThan(
            fixture.calls.operationOrder.indexOf('queueQuestionAnswer:101')
        );
        expect(fixture.calls.operationOrder.indexOf('persistCurrentAttemptUiStateLocally')).toBeGreaterThan(
            fixture.calls.operationOrder.indexOf('queueQuestionAnswer:101')
        );
    });

    it('shows the question loading patch before loading an unloaded target question', async function () {
        var fixture = createFixture({
            loadedQuestionIds: [101]
        });

        await fixture.navigationManager.goToQuestion(1);

        expect(fixture.state.currentIndex).toBe(1);
        expect(fixture.state.navigationRefreshing).toBe(false);
        expect(fixture.state.questionRegionRefreshing).toBe(false);
        expect(fixture.calls.operationOrder.indexOf('render:navigation:question-loading')).toBeGreaterThanOrEqual(0);
        expect(fixture.calls.operationOrder.indexOf('ensureQuestionWindowForIndex:1')).toBeGreaterThanOrEqual(0);
        expect(fixture.calls.operationOrder.indexOf('render:navigation:question-loading')).toBeLessThan(
            fixture.calls.operationOrder.indexOf('ensureQuestionWindowForIndex:1')
        );
    });
});
