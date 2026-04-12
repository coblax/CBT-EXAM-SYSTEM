import { describe, expect, it, vi } from 'vitest';
import { createFinishFlowManager } from '../../../src/frontend/app/exam/finish-flow.js';

async function waitForAssertion(assertion, attempts) {
    var remaining = Math.max(1, Number(attempts) || 20);
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
            await new Promise(function (resolve) {
                setTimeout(resolve, 0);
            });
        }
    }
}

function createFixture(overrides = {}) {
    var calls = {
        clearAllAutoSaveTimers: 0,
        clearAttemptUiStateSyncTimer: 0,
        clearAutoSaveRuntimeState: 0,
        clearMessages: 0,
        clearPersistedAttemptUiState: [],
        clearPersistedQuestionCache: [],
        clearQuestionCachePersistTimer: 0,
        clearQuestionPrefetchRuntimeState: 0,
        ensureResultStageRenderer: [],
        exitFullscreenSilently: 0,
        flushAttemptUiState: [],
        flushPendingAnswerBatch: [],
        persistCurrentQuestionCacheLocally: 0,
        prefetchResultStageRenderer: 0,
        render: 0,
        renderSnapshots: [],
        schedulePendingAnswerRetry: [],
        setConnectionStatus: [],
        startTimer: 0,
        stopTimer: 0,
        submitFlowMetrics: [],
        syncFullscreenState: [],
        syncPendingAnswerRuntimeState: []
    };
    var state = Object.assign({
        attemptId: 88,
        error: '',
        examLockedForPendingFinish: true,
        exams: [
            {
                id: 9,
                kkm_percentage: 75,
                latest_attempt_id: 0,
                latest_attempt_is_passed: 0,
                latest_attempt_max_score: 0,
                latest_attempt_pass_label: '',
                latest_attempt_percentage: 0,
                latest_attempt_result_tone: '',
                latest_attempt_score: 0,
                latest_attempt_status: '',
                show_student_result: 1,
                title: 'Flow Result Fixture'
            }
        ],
        finishConfirmOpen: false,
        finishConfirmSummary: null,
        finishProgressDetail: '',
        finishProgressPercent: 0,
        finishProgressStatus: '',
        finishProgressStepIndex: 0,
        finishProgressStepTotal: 0,
        isFinishing: false,
        lastSyncError: '',
        finishReceipt: null,
        finishResultPending: false,
        finishRecoveryLastError: '',
        pendingFinishAutoSubmit: false,
        pendingSyncCount: 0,
        remainingSeconds: 120,
        result: null,
        selectedExamId: 9,
        stage: 'exam',
        success: ''
    }, overrides.state || {});
    var apiRequest = vi.fn(async function (path, options) {
        if (typeof overrides.apiRequest === 'function') {
            var overrideResult = await overrides.apiRequest(path, options);
            if (overrideResult !== undefined) {
                return overrideResult;
            }
        }

        if (path === 'finish_exam') {
            return {
                attempt_id: 88,
                finished_at: '2026-03-24 15:10:00',
                is_passed: 0,
                kkm_percentage: 75,
                max_score: 100,
                pass_label: 'TIDAK LULUS',
                percentage: 60,
                result_tone: 'fail',
                score: 60,
                show_student_result: 1,
                status: 'completed',
                submission_summary: {
                    answered_questions: 10,
                    pending_manual_questions: 0,
                    total_questions: 10
                }
            };
        }

        if (path === 'result') {
            return {
                attempt: {
                    exam_id: 9,
                    id: 88,
                    max_score: 100,
                    score: 80,
                    started_at: '2026-03-24 14:00:00',
                    status: 'completed'
                },
                exam: {
                    id: 9,
                    kkm_percentage: 75,
                    show_student_result: 1,
                    title: 'Flow Result Fixture'
                },
                is_passed: 1,
                kkm_percentage: 75,
                pass_label: 'LULUS',
                percentage: 80,
                result_tone: 'pass',
                result_view_mode: 'full',
                review_items: [],
                review_summary: {
                    answered_questions: 10,
                    correct_questions: 8,
                    manual_questions: 0,
                    total_questions: 10,
                    unanswered_questions: 0,
                    wrong_questions: 2
                },
                show_student_result: 1,
                submission_summary: {
                    answered_questions: 10,
                    pending_manual_questions: 0,
                    total_questions: 10
                }
            };
        }

        if (path === 'submit_flow_metric') {
            calls.submitFlowMetrics.push(options && options.body ? options.body : null);
            return {
                duplicate: false,
                ok: true,
                skipped: false
            };
        }

        throw new Error('Unexpected apiRequest path: ' + String(path));
    });

    var manager = createFinishFlowManager({
        apiRequest,
        clearAllAutoSaveTimers: function () {
            calls.clearAllAutoSaveTimers += 1;
        },
        clearAttemptUiStateSyncTimer: function () {
            calls.clearAttemptUiStateSyncTimer += 1;
        },
        clearAutoSaveRuntimeState: function () {
            calls.clearAutoSaveRuntimeState += 1;
        },
        clearMessages: function () {
            calls.clearMessages += 1;
        },
        clearPersistedAttemptUiState: function (attemptId) {
            calls.clearPersistedAttemptUiState.push(Number(attemptId) || 0);
        },
        clearPersistedQuestionCache: function (attemptId) {
            calls.clearPersistedQuestionCache.push(Number(attemptId) || 0);
        },
        clearQuestionCachePersistTimer: function () {
            calls.clearQuestionCachePersistTimer += 1;
        },
        clearQuestionPrefetchRuntimeState: function () {
            calls.clearQuestionPrefetchRuntimeState += 1;
        },
        diagnosticsManager: null,
        exitFullscreenSilently: function () {
            calls.exitFullscreenSilently += 1;
        },
        flushAttemptUiState: async function (options) {
            calls.flushAttemptUiState.push(options || null);
            return null;
        },
        flushPendingAnswerBatch: async function (options) {
            calls.flushPendingAnswerBatch.push(options || null);
            return null;
        },
        getExamProgressSummary: function () {
            return {
                answeredQuestions: 10,
                totalQuestions: 10
            };
        },
        getNavigatorConnectionStatus: function () {
            return String(overrides.connectionStatus || 'online');
        },
        getQuestionAtIndex: function () {
            return null;
        },
        getQuestionCount: function () {
            return 0;
        },
        handleRecoverableAnswerSyncFailure: function () {},
        hasAnswerBatchFlushInFlight: function () {
            return false;
        },
        isNetworkConnectivityError: function (error) {
            return !!(error && error.isNetworkError);
        },
        isQuestionAnswered: function () {
            return false;
        },
        isRetryableAnswerSyncError: function () {
            return false;
        },
        persistCurrentQuestionCacheLocally: function () {
            calls.persistCurrentQuestionCacheLocally += 1;
        },
        ensureResultStageRenderer: function (options) {
            calls.ensureResultStageRenderer.push(options || {});
            if (typeof overrides.ensureResultStageRenderer === 'function') {
                return overrides.ensureResultStageRenderer(options);
            }
            return Promise.resolve(null);
        },
        prefetchResultStageRenderer: function () {
            calls.prefetchResultStageRenderer += 1;
        },
        queueQuestionAnswer: function () {
            return false;
        },
        recordActionTrail: function () {},
        recordTimeline: function () {},
        render: function (reason, meta, options) {
            calls.render += 1;
            calls.renderSnapshots.push({
                finishProgressDetail: String(state.finishProgressDetail || ''),
                finishProgressPercent: Number(state.finishProgressPercent) || 0,
                finishProgressStatus: String(state.finishProgressStatus || ''),
                finishProgressStepIndex: Number(state.finishProgressStepIndex) || 0,
                isFinishing: !!state.isFinishing,
                meta: meta || null,
                options: options || null,
                reason: typeof reason === 'string' ? reason : '',
                stage: String(state.stage || '')
            });
        },
        schedulePendingAnswerRetry: function (reason, meta) {
            calls.schedulePendingAnswerRetry.push({
                meta: meta || null,
                reason: String(reason || '')
            });
        },
        setConnectionStatus: function (status, meta) {
            calls.setConnectionStatus.push({
                meta: meta || null,
                status: String(status || '')
            });
        },
        startTimer: function () {
            calls.startTimer += 1;
        },
        state,
        stopTimer: function () {
            calls.stopTimer += 1;
        },
        syncFullscreenState: function (value) {
            calls.syncFullscreenState.push(Boolean(value));
        },
        syncPendingAnswerRuntimeState: function (meta) {
            calls.syncPendingAnswerRuntimeState.push(meta || null);
        },
        windowRef: overrides.windowRef || globalThis
    });

    return {
        apiRequest,
        calls,
        manager,
        state
    };
}

describe('createFinishFlowManager', function () {
    it('uses the reviewed result payload when finish score drifts from the final review snapshot', async function () {
        var fixture = createFixture();

        await fixture.manager.maybeFinalizeLockedExam('unit-test');

        expect(fixture.state.stage).toBe('result');
        expect(fixture.state.result.score).toBe(80);
        expect(fixture.state.result.max_score).toBe(100);
        expect(fixture.state.result.pass_label).toBe('LULUS');
        expect(fixture.state.result.result_tone).toBe('pass');
        expect(fixture.state.exams[0].latest_attempt_score).toBe(80);
        expect(fixture.state.exams[0].latest_attempt_is_passed).toBe(1);
        expect(fixture.state.exams[0].latest_attempt_pass_label).toBe('LULUS');
        expect(fixture.calls.clearPersistedAttemptUiState).toEqual([88]);
        expect(fixture.calls.clearPersistedQuestionCache).toEqual([88]);
        expect(fixture.calls.persistCurrentQuestionCacheLocally).toBe(1);
        expect(fixture.calls.ensureResultStageRenderer).toEqual([
            {
                renderOnResolve: false
            }
        ]);
    });

    it('zeros restricted result details and keeps the exam list from leaking score fields', async function () {
        var fixture = createFixture({
            apiRequest: async function (path) {
                if (path === 'submit_flow_metric') {
                    return undefined;
                }

                if (path === 'finish_exam') {
                    return {
                        attempt_id: 88,
                        finished_at: '2026-03-24 15:10:00',
                        score: 60,
                        show_student_result: 0,
                        status: 'completed'
                    };
                }

                if (path === 'result') {
                    return {
                        attempt: {
                            exam_id: 9,
                            id: 88,
                            max_score: 100,
                            score: 90,
                            started_at: '2026-03-24 14:00:00',
                            status: 'completed'
                        },
                        exam: {
                            id: 9,
                            show_student_result: 0,
                            title: 'Restricted Flow Result'
                        },
                        is_passed: 1,
                        pass_label: 'LULUS',
                        percentage: 90,
                        result_tone: 'pass',
                        result_view_mode: 'restricted',
                        review_items: [
                            {
                                id: 3
                            }
                        ],
                        review_summary: {
                            answered_questions: 10,
                            correct_questions: 9,
                            manual_questions: 0,
                            total_questions: 10,
                            unanswered_questions: 0,
                            wrong_questions: 1
                        },
                        show_student_result: 0,
                        submission_summary: {
                            answered_questions: 10,
                            pending_manual_questions: 0,
                            total_questions: 10
                        }
                    };
                }

                throw new Error('Unexpected apiRequest path: ' + String(path));
            }
        });

        await fixture.manager.maybeFinalizeLockedExam('unit-test');

        expect(fixture.state.stage).toBe('result');
        expect(fixture.state.result.show_student_result).toBe(0);
        expect(fixture.state.result.result_view_mode).toBe('restricted');
        expect(fixture.state.result.score).toBe(0);
        expect(fixture.state.result.max_score).toBe(0);
        expect(fixture.state.result.review_items).toEqual([]);
        expect(fixture.state.result.review_summary).toBeNull();
        expect(fixture.state.exams[0].show_student_result).toBe(0);
        expect(fixture.state.exams[0].latest_attempt_score).toBe(0);
        expect(fixture.state.exams[0].latest_attempt_pass_label).toBe('');
        expect(fixture.state.exams[0].latest_attempt_result_tone).toBe('');
    });

    it('persists an unlocked cache snapshot immediately when finish finalization fails', async function () {
        var fixture = createFixture({
            apiRequest: async function (path) {
                if (path === 'submit_flow_metric') {
                    return undefined;
                }

                if (path === 'finish_exam') {
                    var error = new Error('Finish gagal dari unit test.');
                    error.status = 503;
                    throw error;
                }

                throw new Error('Unexpected apiRequest path: ' + String(path));
            }
        });

        await fixture.manager.maybeFinalizeLockedExam('unit-test');

        expect(fixture.state.examLockedForPendingFinish).toBe(false);
        expect(fixture.state.pendingFinishAutoSubmit).toBe(false);
        expect(fixture.state.isFinishing).toBe(false);
        expect(fixture.state.finishProgressPercent).toBe(0);
        expect(fixture.state.finishProgressStepIndex).toBe(0);
        expect(fixture.state.error).toBe('Finish gagal dari unit test.');
        expect(fixture.calls.persistCurrentQuestionCacheLocally).toBe(1);
        expect(fixture.calls.startTimer).toBe(1);
        expect(fixture.calls.syncPendingAnswerRuntimeState).toEqual([
            {
                clearLastSyncError: false,
                persist: false
            },
            {
                clearLastSyncError: false,
                persist: true
            }
        ]);
    });

    it('emits submit telemetry events across submit, ack, and ready phases', async function () {
        var fixture = createFixture();

        await fixture.manager.handleFinish(false, { skipConfirmation: true });
        await waitForAssertion(function () {
            expect(fixture.state.stage).toBe('result');
        });

        var events = fixture.calls.submitFlowMetrics.map(function (payload) {
            return payload && payload.event ? payload.event : '';
        });

        expect(events).toContain('finish_submit_started');
        expect(events).toContain('finish_acknowledged');
        expect(events).toContain('finish_recovery_started');
        expect(events).toContain('finish_result_ready');
        expect(
            fixture.calls.submitFlowMetrics.some(function (payload) {
                return payload
                    && payload.event === 'finish_acknowledged'
                    && payload.duration_ms >= 0;
            })
        ).toBe(true);
        expect(
            fixture.calls.submitFlowMetrics.some(function (payload) {
                return payload
                    && payload.event === 'finish_result_ready'
                    && payload.phase_durations
                    && payload.phase_durations.ack_to_result_ready_ms >= 0;
            })
        ).toBe(true);
    });

    it('does not let submit telemetry failures break finish recovery', async function () {
        var fixture = createFixture({
            apiRequest: async function (path, options) {
                if (path === 'submit_flow_metric') {
                    throw new Error('Telemetry gagal dari unit test.');
                }

                if (path === 'finish_exam') {
                    return {
                        attempt_id: 88,
                        finished_at: '2026-03-24 15:10:00',
                        show_student_result: 1,
                        status: 'completed'
                    };
                }

                if (path === 'result') {
                    return {
                        attempt: {
                            exam_id: 9,
                            id: 88,
                            max_score: 100,
                            score: 85,
                            started_at: '2026-03-24 14:00:00',
                            status: 'completed'
                        },
                        exam: {
                            id: 9,
                            show_student_result: 1,
                            title: 'Flow Result Fixture'
                        },
                        is_passed: 1,
                        kkm_percentage: 75,
                        pass_label: 'LULUS',
                        percentage: 85,
                        result_tone: 'pass',
                        result_view_mode: 'full',
                        review_items: [],
                        review_summary: {
                            answered_questions: 10,
                            correct_questions: 8,
                            manual_questions: 0,
                            total_questions: 10,
                            unanswered_questions: 0,
                            wrong_questions: 2
                        },
                        show_student_result: 1,
                        submission_summary: {
                            answered_questions: 10,
                            pending_manual_questions: 0,
                            total_questions: 10
                        }
                    };
                }

                throw new Error('Unexpected apiRequest path: ' + String(path));
            }
        });

        await fixture.manager.handleFinish(false, { skipConfirmation: true });
        await waitForAssertion(function () {
            expect(fixture.state.stage).toBe('result');
        });

        expect(fixture.state.result).not.toBeNull();
        expect(fixture.state.error).toBe('');
    });

    it('emits staged finish progress from submit until result is ready', async function () {
        var fixture = createFixture();

        await fixture.manager.handleFinish(false, { skipConfirmation: true });
        await waitForAssertion(function () {
            expect(fixture.state.stage).toBe('result');
        });

        expect(
            fixture.calls.renderSnapshots.some(function (snapshot) {
                return snapshot.finishProgressPercent === 12
                    && snapshot.finishProgressStepIndex === 1
                    && snapshot.finishProgressStatus === 'Cek jawaban'
                    && snapshot.options
                    && snapshot.options.immediate === true
                    && snapshot.options.skipPostRenderEffects === true;
            })
        ).toBe(true);
        expect(
            fixture.calls.renderSnapshots.some(function (snapshot) {
                return snapshot.finishProgressPercent === 34
                    && snapshot.finishProgressStepIndex === 2
                    && snapshot.finishProgressStatus === 'Sinkron';
            })
        ).toBe(true);
        expect(
            fixture.calls.renderSnapshots.some(function (snapshot) {
                return snapshot.finishProgressPercent === 72
                    && snapshot.finishProgressStepIndex === 3
                    && snapshot.finishProgressStatus === 'Mengirim finalisasi ujian'
                    && snapshot.isFinishing === true;
            })
        ).toBe(true);
        expect(
            fixture.calls.renderSnapshots.some(function (snapshot) {
                return snapshot.finishProgressPercent === 90
                    && snapshot.finishProgressStepIndex === 4
                    && snapshot.finishProgressStatus === 'Memuat hasil ujian';
            })
        ).toBe(true);
        expect(fixture.state.finishProgressPercent).toBe(0);
        expect(fixture.state.finishProgressStepIndex).toBe(0);
    });

    it('yields one paint window before starting the heavier finish sync work', async function () {
        var rafCallbacks = [];
        var fixture = createFixture({
            windowRef: {
                document: {
                    visibilityState: 'visible'
                },
                requestAnimationFrame: function (callback) {
                    rafCallbacks.push(callback);
                    return rafCallbacks.length;
                },
                setTimeout: function (callback) {
                    callback();
                    return 1;
                },
                clearTimeout: function () {}
            }
        });

        var finishPromise = fixture.manager.handleFinish(false, { skipConfirmation: true });

        expect(fixture.state.finishProgressStepIndex).toBe(1);
        expect(fixture.state.finishProgressStatus).toBe('Cek jawaban');
        expect(fixture.calls.flushAttemptUiState).toHaveLength(0);
        expect(rafCallbacks).toHaveLength(1);

        rafCallbacks.shift()(0);
        expect(fixture.calls.flushAttemptUiState).toHaveLength(0);
        expect(rafCallbacks).toHaveLength(1);

        rafCallbacks.shift()(16);
        await Promise.resolve();

        expect(fixture.calls.flushAttemptUiState).toHaveLength(1);

        await finishPromise;
    });

    it('keeps finish progress on exam stage until the result renderer is ready', async function () {
        var resolveRenderer = null;
        var fixture = createFixture({
            ensureResultStageRenderer: function () {
                return new Promise(function (resolve) {
                    resolveRenderer = resolve;
                });
            }
        });

        var finalizePromise = fixture.manager.maybeFinalizeLockedExam('unit-test');

        await waitForAssertion(function () {
            expect(fixture.state.finishProgressPercent).toBe(90);
            expect(fixture.state.finishProgressStepIndex).toBe(4);
            expect(fixture.state.finishProgressStatus).toBe('Memuat hasil ujian');
        });

        expect(fixture.state.stage).toBe('exam');
        expect(fixture.calls.ensureResultStageRenderer).toEqual([
            {
                renderOnResolve: false
            }
        ]);

        resolveRenderer();
        await finalizePromise;

        expect(fixture.state.stage).toBe('result');
        expect(fixture.state.finishProgressPercent).toBe(0);
        expect(fixture.state.finishProgressStepIndex).toBe(0);
    });

    it('keeps an interactive waiting state when finish starts while offline', async function () {
        var fixture = createFixture({
            connectionStatus: 'offline'
        });

        await fixture.manager.handleFinish(false, { skipConfirmation: true });

        expect(fixture.state.stage).toBe('exam');
        expect(fixture.state.examLockedForPendingFinish).toBe(true);
        expect(fixture.state.isFinishing).toBe(false);
        expect(fixture.state.finishProgressPercent).toBe(34);
        expect(fixture.state.finishProgressStepIndex).toBe(2);
        expect(fixture.state.finishProgressStatus).toBe('Menunggu koneksi');
        expect(fixture.state.finishProgressDetail).toBe('Menunggu koneksi.');
        expect(
            fixture.calls.renderSnapshots.some(function (snapshot) {
                return snapshot.finishProgressStatus === 'Menunggu koneksi'
                    && snapshot.finishProgressStepIndex === 2
                    && snapshot.stage === 'exam';
            })
        ).toBe(true);
    });

    it('persists a finish receipt and keeps the exam locked when result recovery fails after server ack', async function () {
        var fixture = createFixture({
            apiRequest: async function (path) {
                if (path === 'submit_flow_metric') {
                    return undefined;
                }

                if (path === 'finish_exam') {
                    return {
                        attempt_id: 88,
                        finished_at: '2026-03-24 15:10:00',
                        show_student_result: 1,
                        status: 'completed'
                    };
                }

                if (path === 'result') {
                    throw new Error('Result fetch gagal dari unit test.');
                }

                throw new Error('Unexpected apiRequest path: ' + String(path));
            }
        });

        await fixture.manager.maybeFinalizeLockedExam('unit-test');

        expect(fixture.state.stage).toBe('exam');
        expect(fixture.state.examLockedForPendingFinish).toBe(true);
        expect(fixture.state.finishResultPending).toBe(true);
        expect(fixture.state.finishReceipt).toMatchObject({
            attempt_id: 88,
            exam_id: 9,
            status: 'completed',
            pending_result_fetch: 1
        });
        expect(fixture.state.finishProgressStatus).toBe('Finalisasi diterima server');
        expect(fixture.calls.persistCurrentQuestionCacheLocally).toBe(1);
        expect(fixture.calls.clearPersistedAttemptUiState).toEqual([]);
        expect(fixture.calls.clearPersistedQuestionCache).toEqual([]);
    });

    it('recovers the result from a persisted finish receipt without reopening editing mode', async function () {
        var fixture = createFixture({
            state: {
                finishReceipt: {
                    ack_source: 'finish_exam',
                    attempt_id: 88,
                    exam_id: 9,
                    finished_at: '2026-03-24 15:10:00',
                    pending_result_fetch: 1,
                    result_view_mode_hint: 'full',
                    show_student_result_hint: 1,
                    status: 'completed',
                    updated_at: 1710000000000
                },
                finishResultPending: true
            }
        });

        await fixture.manager.maybeFinalizeLockedExam('unit-test-recovery');

        expect(fixture.state.stage).toBe('result');
        expect(fixture.state.finishReceipt).toBeNull();
        expect(fixture.state.finishResultPending).toBe(false);
        expect(fixture.calls.clearPersistedAttemptUiState).toEqual([88]);
        expect(fixture.calls.clearPersistedQuestionCache).toEqual([88]);
        expect(
            fixture.calls.renderSnapshots.some(function (snapshot) {
                return snapshot.stage === 'result';
            })
        ).toBe(true);
    });
});
