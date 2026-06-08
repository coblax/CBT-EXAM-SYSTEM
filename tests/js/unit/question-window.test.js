import { describe, expect, it, vi } from 'vitest';
import { createQuestionWindowManager } from '../../../src/frontend/app/exam/question-window.js';

function createPayload(questionId) {
    return {
        id: questionId,
        question_number: questionId,
        question_type: 'essay',
        text: 'Soal ' + String(questionId)
    };
}

function createFixture(overrides = {}) {
    var questionOrderIds = Array.from({ length: overrides.totalQuestions || 20 }, function (_, index) {
        return index + 1;
    });
    var loadedUntil = Number(overrides.loadedUntil !== undefined ? overrides.loadedUntil : 10);
    var state = Object.assign({
        attemptId: 42,
        connectionStatus: 'online',
        currentIndex: 7,
        isFinishing: false,
        loadedQuestionWindowOffsets: {
            0: true
        },
        questionManifestById: {},
        questionOrderIds: questionOrderIds,
        questionPayloadById: {},
        questions: [],
        selectedExamId: 9,
        stage: 'exam',
        totalQuestions: questionOrderIds.length,
        windowLimit: 10,
        windowOffset: 0
    }, overrides.state || {});
    var root = document.createElement('div');
    var loadQuestionWindow = overrides.loadQuestionWindow || vi.fn(async function (offset, options) {
        var limit = Number(options && options.limit) || 0;
        for (var index = offset; index < offset + limit && index < questionOrderIds.length; index++) {
            state.questionPayloadById[questionOrderIds[index]] = createPayload(questionOrderIds[index]);
        }
        return {
            offset: offset,
            limit: limit
        };
    });
    var prewarmQuestionMedia = overrides.prewarmQuestionMedia || vi.fn();

    questionOrderIds.forEach(function (questionId, index) {
        state.questionManifestById[questionId] = createPayload(questionId);
        if (index < loadedUntil) {
            state.questionPayloadById[questionId] = createPayload(questionId);
        }
    });
    state.questions = questionOrderIds.slice(0, Math.min(loadedUntil, 10)).map(function (questionId) {
        return state.questionPayloadById[questionId];
    }).filter(Boolean);

    var manager = createQuestionWindowManager({
        escapeHtml: function (value) {
            return String(value || '');
        },
        getLoadQuestionWindow: function () {
            return loadQuestionWindow;
        },
        getNavigatorConnectionStatus: overrides.getNavigatorConnectionStatus || function () {
            return 'online';
        },
        isQuestionRevisionRefreshActive: overrides.isQuestionRevisionRefreshActive || function () {
            return false;
        },
        prewarmQuestionMedia: prewarmQuestionMedia,
        questionPrefetchBatchSize: 5,
        questionPrefetchIdleDelayMs: 30000,
        questionWindowSize: 10,
        root: root,
        state: state,
        windowRef: window
    });

    return {
        loadQuestionWindow: loadQuestionWindow,
        manager: manager,
        prewarmQuestionMedia: prewarmQuestionMedia,
        state: state
    };
}

describe('createQuestionWindowManager', function () {
    it('prefetches the full next question window near the active boundary', async function () {
        var fixture = createFixture();

        await fixture.manager.prefetchNextQuestionWindow();

        expect(fixture.loadQuestionWindow).toHaveBeenCalledTimes(1);
        expect(fixture.loadQuestionWindow).toHaveBeenCalledWith(10, expect.objectContaining({
            attemptId: 42,
            examId: 9,
            includeExisting: 1,
            limit: 10,
            preserveActiveWindow: true,
            scenarioTarget: 'prefetch'
        }));
        expect(fixture.prewarmQuestionMedia).toHaveBeenCalledWith(expect.arrayContaining([
            expect.objectContaining({ id: 11 }),
            expect.objectContaining({ id: 20 })
        ]), expect.objectContaining({
            attemptId: 42,
            offset: 10,
            reason: 'boundary-prefetch'
        }));
    });

    it('guards duplicate prefetch calls while the same offset is in flight', async function () {
        var resolveLoad;
        var loadQuestionWindow = vi.fn(function () {
            return new Promise(function (resolve) {
                resolveLoad = resolve;
            });
        });
        var fixture = createFixture({
            loadQuestionWindow: loadQuestionWindow
        });

        var first = fixture.manager.prefetchNextQuestionWindow();
        var second = fixture.manager.prefetchNextQuestionWindow();

        expect(first).toBe(second);
        expect(loadQuestionWindow).toHaveBeenCalledTimes(0);

        await Promise.resolve();
        expect(loadQuestionWindow).toHaveBeenCalledTimes(1);

        for (var questionId = 11; questionId <= 20; questionId++) {
            fixture.state.questionPayloadById[questionId] = createPayload(questionId);
        }
        resolveLoad({
            limit: 10,
            offset: 10
        });
        await first;
    });

    it('does not call the API when the next window is already loaded', async function () {
        var fixture = createFixture({
            loadedUntil: 20
        });

        await fixture.manager.prefetchNextQuestionWindow();

        expect(fixture.loadQuestionWindow).not.toHaveBeenCalled();
        expect(fixture.prewarmQuestionMedia).toHaveBeenCalled();
    });

    it('skips prefetch while offline, finishing, or refreshing revisions', async function () {
        var offlineFixture = createFixture({
            getNavigatorConnectionStatus: function () {
                return 'offline';
            }
        });
        var finishingFixture = createFixture({
            state: {
                isFinishing: true
            }
        });
        var revisionFixture = createFixture({
            isQuestionRevisionRefreshActive: function () {
                return true;
            }
        });

        await offlineFixture.manager.prefetchNextQuestionWindow();
        await finishingFixture.manager.prefetchNextQuestionWindow();
        await revisionFixture.manager.prefetchNextQuestionWindow();

        expect(offlineFixture.loadQuestionWindow).not.toHaveBeenCalled();
        expect(finishingFixture.loadQuestionWindow).not.toHaveBeenCalled();
        expect(revisionFixture.loadQuestionWindow).not.toHaveBeenCalled();
    });
});
