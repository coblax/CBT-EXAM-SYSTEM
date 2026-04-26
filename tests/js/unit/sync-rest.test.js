import { describe, expect, it } from 'vitest';
import { createAnswerSyncManager } from '../../../src/frontend/app/exam/answer-sync.js';

function createQuestion(id, answer) {
    return {
        id,
        payload: answer
    };
}

function cloneValue(value) {
    return JSON.parse(JSON.stringify(value));
}

function createAnswerSyncFixture(overrides = {}) {
    var questions = Array.isArray(overrides.questions)
        ? overrides.questions.map(function (question) {
            return Object.assign({}, question);
        })
        : [
            createQuestion(101, { choice: 'A' }),
            createQuestion(102, { choice: 'B' }),
        ];
    var questionLookup = questions.reduce(function (lookup, question) {
        lookup[Number(question.id) || 0] = question;
        return lookup;
    }, {});
    var navigatorStatus = String(overrides.navigatorStatus || 'online');
    var generation = Number.isFinite(Number(overrides.generation)) ? Number(overrides.generation) : 1;
    var apiCalls = [];
    var state = Object.assign({
        attemptId: 77,
        connectionStatus: 'online',
        error: '',
        examLockedForPendingFinish: false,
        isFinishing: false,
        lastSyncError: '',
        pendingFinishAutoSubmit: false,
        pendingSyncCount: 0,
        questionPayloadById: questionLookup,
        stage: 'exam',
        syncBlockingReason: ''
    }, overrides.state || {});

    var manager = createAnswerSyncManager({
        diagnosticsManager: null,
        state,
        windowRef: {
            clearTimeout: function () {},
            setTimeout: function () {
                return 1;
            }
        },
        autoSaveChoiceDelayMs: 0,
        autoSaveTextDelayMs: 0,
        autoSaveChoiceDelayCongestedMs: 0,
        autoSaveTextDelayCongestedMs: 0,
        autoSaveCongestedWindowMs: 1000,
        autoSaveBatchMaxItems: overrides.autoSaveBatchMaxItems || 10,
        answerSyncRetryBaseDelayMs: 100,
        answerSyncRetryMaxDelayMs: 500,
        apiRequest: async function (path, options) {
            apiCalls.push({
                path,
                options: cloneValue(options)
            });

            if (typeof overrides.apiRequest === 'function') {
                return overrides.apiRequest(path, options);
            }

            if (path === 'submit_answers_batch') {
                return {
                    attempt_id: state.attemptId,
                    accepted_count: options.body.answers.length,
                    buffered: 0,
                    flushed: options.body.answers.length,
                    pending_count: 0,
                    items: options.body.answers.map(function (entry) {
                        return {
                            question_id: Number(entry.question_id) || 0,
                            is_correct: null,
                            score_awarded: 0,
                            deferred: 0,
                            cleared: 0
                        };
                    })
                };
            }

            if (path === 'submit_answer') {
                return {
                    question_id: Number(options.body.question_id) || 0,
                    is_correct: null,
                    score_awarded: 0,
                    deferred: 0,
                    cleared: 0
                };
            }

            throw new Error('Unexpected API path: ' + String(path));
        },
        getNavigatorConnectionStatus: function () {
            return navigatorStatus;
        },
        getQuestionById: function (questionId) {
            return questionLookup[Number(questionId) || 0] || null;
        },
        getQuestionPayloadById: function (questionId) {
            return questionLookup[Number(questionId) || 0] || null;
        },
        getQuestionDataGeneration: function () {
            return generation;
        },
        isQuestionRevisionRefreshActive: function () {
            return false;
        },
        maybeFinalizeLockedExam: function () {},
        normalizeExistingAnswerForQuestion: function () {
            return { hasValue: false };
        },
        normalizeStoredAutoSaveState: function (snapshot) {
            return Object.assign({
                examLockedForPendingFinish: false,
                lastSubmittedPayloadByQuestion: {},
                lastSyncError: '',
                pendingAnswerBatchByQuestion: {},
                pendingAnswerBatchOrder: [],
                syncBlockingReason: '',
                autoSaveCongestedUntil: 0
            }, snapshot || {});
        },
        payloadSignature: function (payload) {
            return payload === null ? '' : JSON.stringify(payload);
        },
        questionAnswerPayload: function (question) {
            return question && Object.prototype.hasOwnProperty.call(question, 'payload')
                ? cloneValue(question.payload)
                : null;
        },
        recordActionTrail: function () {},
        recordTimeline: function () {},
        render: function () {},
        scheduleQuestionCachePersist: function () {}
    });

    return {
        apiCalls,
        manager,
        questions,
        setNavigatorStatus: function (nextStatus) {
            navigatorStatus = String(nextStatus || 'online');
        },
        state
    };
}

describe('createAnswerSyncManager', function () {
    it('keeps single legacy fallback and batch submission effects equivalent for the same answers', async function () {
        var batchFixture = createAnswerSyncFixture();
        batchFixture.manager.queueQuestionAnswer(batchFixture.questions[0]);
        batchFixture.manager.queueQuestionAnswer(batchFixture.questions[1]);
        await batchFixture.manager.flushPendingAnswerBatch({ flushAll: true });

        var legacyFixture = createAnswerSyncFixture({
            apiRequest: async function (path, options) {
                if (path === 'submit_answers_batch') {
                    throw Object.assign(new Error('Runtime buffer unavailable'), {
                        code: 'runtime_buffer_unavailable',
                        status: 503
                    });
                }

                if (path === 'submit_answer') {
                    return {
                        question_id: Number(options.body.question_id) || 0,
                        is_correct: null,
                        score_awarded: 0,
                        deferred: 0,
                        cleared: 0
                    };
                }

                throw new Error('Unexpected API path: ' + String(path));
            }
        });
        legacyFixture.manager.queueQuestionAnswer(legacyFixture.questions[0]);
        legacyFixture.manager.queueQuestionAnswer(legacyFixture.questions[1]);
        await legacyFixture.manager.flushPendingAnswerBatch({ flushAll: true });

        expect(legacyFixture.manager.getAutoSaveState().lastSubmittedPayloadByQuestion).toEqual(
            batchFixture.manager.getAutoSaveState().lastSubmittedPayloadByQuestion
        );
        expect(legacyFixture.manager.getAutoSaveState().pendingAnswerBatchByQuestion).toEqual(
            batchFixture.manager.getAutoSaveState().pendingAnswerBatchByQuestion
        );
        expect(legacyFixture.manager.getAutoSaveState().pendingAnswerBatchOrder).toEqual(
            batchFixture.manager.getAutoSaveState().pendingAnswerBatchOrder
        );
        expect(legacyFixture.state.pendingSyncCount).toBe(batchFixture.state.pendingSyncCount);
        expect(legacyFixture.state.syncBlockingReason).toBe(batchFixture.state.syncBlockingReason);
        expect(legacyFixture.apiCalls.map(function (entry) {
            return entry.path;
        })).toEqual(['submit_answers_batch', 'submit_answer', 'submit_answer']);
    });

    it('falls back to legacy submit only for supported batch errors and not for retryable network errors', function () {
        var fixture = createAnswerSyncFixture();

        expect(fixture.manager.shouldFallbackToLegacyBatch({
            status: 503,
            message: 'Service unavailable'
        })).toBe(true);
        expect(fixture.manager.shouldFallbackToLegacyBatch({
            status: 429,
            message: 'Too many requests'
        })).toBe(true);
        expect(fixture.manager.shouldFallbackToLegacyBatch({
            code: 'runtime_buffer_unavailable'
        })).toBe(true);
        expect(fixture.manager.shouldFallbackToLegacyBatch({
            isNetworkError: true,
            status: 0,
            message: 'Failed to fetch'
        })).toBe(false);
    });

    it('marks answer autosave requests as best-effort auth so they cannot force a stage logout', async function () {
        var fixture = createAnswerSyncFixture();
        fixture.manager.queueQuestionAnswer(fixture.questions[0]);

        await fixture.manager.flushPendingAnswerBatch({ flushAll: true });

        expect(fixture.apiCalls[0]).toMatchObject({
            path: 'submit_answers_batch',
            options: {
                suppressAuthExpiry: true
            }
        });
    });

    it('marks legacy answer fallback requests as best-effort auth too', async function () {
        var fixture = createAnswerSyncFixture({
            apiRequest: async function (path, options) {
                if (path === 'submit_answers_batch') {
                    throw Object.assign(new Error('Runtime buffer unavailable'), {
                        code: 'runtime_buffer_unavailable',
                        status: 503
                    });
                }

                if (path === 'submit_answer') {
                    return {
                        question_id: Number(options.body.question_id) || 0,
                        is_correct: null,
                        score_awarded: 0,
                        deferred: 0,
                        cleared: 0
                    };
                }

                throw new Error('Unexpected API path: ' + String(path));
            }
        });
        fixture.manager.queueQuestionAnswer(fixture.questions[0]);

        await fixture.manager.flushPendingAnswerBatch({ flushAll: true });

        expect(fixture.apiCalls.map(function (entry) {
            return {
                path: entry.path,
                suppressAuthExpiry: Boolean(entry.options && entry.options.suppressAuthExpiry)
            };
        })).toEqual([
            {
                path: 'submit_answers_batch',
                suppressAuthExpiry: true
            },
            {
                path: 'submit_answer',
                suppressAuthExpiry: true
            }
        ]);
    });

    it('keeps partial legacy successes applied while requeueing the remaining answers when a later legacy submit fails', async function () {
        var legacySubmitCount = 0;
        var fixture = createAnswerSyncFixture({
            apiRequest: async function (path, options) {
                if (path === 'submit_answers_batch') {
                    throw Object.assign(new Error('Runtime buffer unavailable'), {
                        code: 'runtime_buffer_unavailable',
                        status: 503
                    });
                }

                if (path === 'submit_answer') {
                    legacySubmitCount += 1;
                    if (legacySubmitCount === 1) {
                        return {
                            question_id: Number(options.body.question_id) || 0,
                            is_correct: 1,
                            score_awarded: 5,
                            deferred: 0,
                            cleared: 0
                        };
                    }

                    throw Object.assign(new Error('Legacy submit failed on the second answer'), {
                        status: 500
                    });
                }

                throw new Error('Unexpected API path: ' + String(path));
            }
        });

        fixture.manager.queueQuestionAnswer(fixture.questions[0]);
        fixture.manager.queueQuestionAnswer(fixture.questions[1]);

        await expect(fixture.manager.flushPendingAnswerBatch({ flushAll: true })).rejects.toThrow('Legacy submit failed on the second answer');

        var autoSaveState = fixture.manager.getAutoSaveState();
        expect(autoSaveState.lastSubmittedPayloadByQuestion).toEqual({
            101: JSON.stringify({ choice: 'A' })
        });
        expect(autoSaveState.pendingAnswerBatchOrder).toEqual([102]);
        expect(autoSaveState.pendingAnswerBatchByQuestion[102]).toEqual({
            question_id: 102,
            answer: { choice: 'B' },
            signature: JSON.stringify({ choice: 'B' })
        });
        expect(fixture.state.pendingSyncCount).toBe(1);
        expect(fixture.state.lastSyncError).toBe('Legacy submit failed on the second answer');
    });

    it('maps offline and pending sync combinations to the expected blocking reasons', function () {
        var fixture = createAnswerSyncFixture();
        fixture.manager.queueQuestionAnswer(fixture.questions[0]);

        expect(fixture.state.syncBlockingReason).toBe('pending_sync');

        fixture.setNavigatorStatus('offline');
        fixture.manager.setConnectionStatus('offline', {
            triggerRetry: false
        });
        expect(fixture.state.syncBlockingReason).toBe('offline_pending_sync');

        fixture.state.examLockedForPendingFinish = true;
        fixture.manager.syncPendingAnswerRuntimeState({
            clearLastSyncError: false,
            persist: false
        });
        expect(fixture.state.syncBlockingReason).toBe('finish_wait_online');

        fixture.setNavigatorStatus('online');
        fixture.manager.setConnectionStatus('online', {
            triggerRetry: false
        });
        fixture.manager.syncPendingAnswerRuntimeState({
            clearLastSyncError: false,
            persist: false
        });
        expect(fixture.state.syncBlockingReason).toBe('finish_pending_sync');
    });

    it('returns finish_ready only when finish lock is active and no pending batch remains', function () {
        var fixture = createAnswerSyncFixture({
            state: {
                examLockedForPendingFinish: true
            }
        });

        fixture.manager.syncPendingAnswerRuntimeState({
            clearLastSyncError: false,
            persist: false
        });

        expect(fixture.state.syncBlockingReason).toBe('finish_ready');
    });
});
