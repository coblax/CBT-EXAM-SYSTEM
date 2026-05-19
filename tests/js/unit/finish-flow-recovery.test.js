import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createFinishFlowManager } from '../../../src/frontend/app/exam/finish-flow.js';

function createState(overrides = {}) {
    return {
        stage: 'exam',
        attemptId: 42,
        selectedExamId: 10,
        token: 'test-token',
        user: { user_id: 5, role: 'student', display_name: 'Siswa' },
        exams: [{ id: 10, title: 'Exam Test', kkm_percentage: 75, enable_calculator: 1, show_student_result: 1 }],
        isFinishing: false,
        examLockedForPendingFinish: false,
        pendingFinishAutoSubmit: false,
        pendingSyncCount: 0,
        connectionStatus: 'online',
        remainingSeconds: 3000,
        finishConfirmOpen: false,
        finishConfirmSummary: null,
        finishReceipt: null,
        finishResultPending: false,
        finishRecoveryLastError: '',
        finishProgressPercent: 0,
        finishProgressStepIndex: 0,
        finishProgressStepTotal: 0,
        finishProgressStatus: '',
        finishProgressDetail: '',
        result: null,
        success: '',
        error: '',
        lastSyncError: '',
        notice: '',
        currentIndex: 0,
        ...overrides
    };
}

function createDeps(state, overrides = {}) {
    return {
        diagnosticsManager: null,
        recordActionTrail: vi.fn(),
        recordTimeline: vi.fn(),
        state: state,
        apiRequest: overrides.apiRequest || vi.fn().mockResolvedValue({}),
        clearAllAutoSaveTimers: vi.fn(),
        clearAttemptUiStateSyncTimer: vi.fn(),
        clearAutoSaveRuntimeState: vi.fn(),
        clearMessages: vi.fn(),
        clearPersistedAttemptUiState: vi.fn(),
        clearPersistedQuestionCache: vi.fn(),
        clearQuestionCachePersistTimer: vi.fn(),
        clearQuestionPrefetchRuntimeState: vi.fn(),
        exitFullscreenSilently: vi.fn(),
        flushAttemptUiState: vi.fn().mockResolvedValue(undefined),
        flushPendingAnswerBatch: overrides.flushPendingAnswerBatch || vi.fn().mockResolvedValue({}),
        getExamProgressSummary: vi.fn().mockReturnValue({ total: 10, answered: 8 }),
        getNavigatorConnectionStatus: overrides.getNavigatorConnectionStatus || vi.fn().mockReturnValue('online'),
        getQuestionAtIndex: vi.fn().mockReturnValue(null),
        getQuestionCount: vi.fn().mockReturnValue(10),
        handleRecoverableAnswerSyncFailure: vi.fn(),
        hasAnswerBatchFlushInFlight: vi.fn().mockReturnValue(false),
        isNetworkConnectivityError: overrides.isNetworkConnectivityError || function (error) {
            return error && (error.isNetworkError === true || Number(error.status) === 0);
        },
        isQuestionAnswered: vi.fn().mockReturnValue(false),
        isRetryableAnswerSyncError: overrides.isRetryableAnswerSyncError || function (error) {
            return error && (error.isNetworkError === true || Number(error.status) === 0);
        },
        persistCurrentQuestionCacheLocally: vi.fn(),
        ensureResultStageRenderer: vi.fn().mockResolvedValue(null),
        prefetchResultStageRenderer: vi.fn(),
        queueQuestionAnswer: vi.fn().mockReturnValue(false),
        render: vi.fn(),
        schedulePendingAnswerRetry: vi.fn(),
        setConnectionStatus: vi.fn(),
        startTimer: vi.fn(),
        stopTimer: vi.fn(),
        syncFullscreenState: vi.fn(),
        syncPendingAnswerRuntimeState: vi.fn(),
        windowRef: {
            setTimeout: function (fn, ms) { fn(); return 1; },
            clearTimeout: vi.fn(),
            requestAnimationFrame: function (fn) { fn(); return 1; },
            document: { visibilityState: 'visible' }
        },
        ...overrides
    };
}

describe('createFinishFlowManager', function () {
    afterEach(function () {
        vi.useRealTimers();
    });

    describe('handleFinish', function () {
        it('opens confirm modal when not auto-submit and not skipping confirmation', async function () {
            var state = createState();
            var deps = createDeps(state);
            var manager = createFinishFlowManager(deps);

            await manager.handleFinish(false);

            expect(state.finishConfirmOpen).toBe(true);
        });

        it('locks exam and queues answers on confirmed finish', async function () {
            var state = createState();
            var apiRequest = vi.fn().mockResolvedValue({
                attempt_id: 42,
                status: 'completed',
                finished_at: '2026-03-24 11:00:00',
                show_student_result: 1,
                result_view_mode: 'full',
                score: 80,
                max_score: 100,
                percentage: 80,
                kkm_percentage: 75,
                passing_score: 75,
                is_passed: 1,
                pass_label: 'LULUS',
                result_tone: 'pass'
            });
            var deps = createDeps(state, { apiRequest: apiRequest });
            var manager = createFinishFlowManager(deps);

            await manager.handleFinish(false, { skipConfirmation: true });

            expect(state.examLockedForPendingFinish).toBe(true);
        });
    });

    describe('openFinishConfirmModal and closeFinishConfirmModal', function () {
        it('opens and closes the confirm modal', function () {
            var state = createState();
            var deps = createDeps(state);
            var manager = createFinishFlowManager(deps);

            manager.openFinishConfirmModal();
            expect(state.finishConfirmOpen).toBe(true);

            manager.closeFinishConfirmModal();
            expect(state.finishConfirmOpen).toBe(false);
        });

        it('does not open modal when already finishing', function () {
            var state = createState({ isFinishing: true });
            var deps = createDeps(state);
            var manager = createFinishFlowManager(deps);

            manager.openFinishConfirmModal();
            expect(state.finishConfirmOpen).toBe(false);
        });
    });

    describe('maybeFinalizeLockedExam', function () {
        it('does nothing when exam is not locked', async function () {
            var state = createState({ examLockedForPendingFinish: false });
            var deps = createDeps(state);
            var manager = createFinishFlowManager(deps);

            var result = await manager.maybeFinalizeLockedExam('test');
            expect(result).toBeNull();
        });

        it('does nothing when pending sync count > 0', async function () {
            var state = createState({ examLockedForPendingFinish: true, pendingSyncCount: 3 });
            var deps = createDeps(state);
            var manager = createFinishFlowManager(deps);

            var result = await manager.maybeFinalizeLockedExam('test');
            expect(result).toBeNull();
        });

        it('does nothing when offline', async function () {
            var state = createState({ examLockedForPendingFinish: true, pendingSyncCount: 0 });
            var deps = createDeps(state, {
                getNavigatorConnectionStatus: vi.fn().mockReturnValue('offline')
            });
            var manager = createFinishFlowManager(deps);

            var result = await manager.maybeFinalizeLockedExam('test');
            expect(result).toBeNull();
        });

        it('recovers from finish receipt when result is pending', async function () {
            var state = createState({
                examLockedForPendingFinish: true,
                pendingSyncCount: 0,
                finishResultPending: true,
                finishReceipt: {
                    attempt_id: 42,
                    exam_id: 10,
                    finished_at: '2026-03-24 11:00:00',
                    status: 'completed',
                    result_view_mode_hint: 'full',
                    show_student_result_hint: 1,
                    ack_source: 'finish_exam',
                    pending_result_fetch: 1,
                    updated_at: 1710000000000
                }
            });
            var apiRequest = vi.fn().mockResolvedValue({
                attempt: { id: 42, exam_id: 10, student_id: 5, status: 'completed', score: 80, max_score: 100, started_at: '2026-03-24 10:00:00', finished_at: '2026-03-24 11:00:00' },
                exam: { id: 10, title: 'Exam Test', duration_minutes: 60, kkm_percentage: 75, show_student_result: 1 },
                show_student_result: 1,
                result_view_mode: 'full',
                answers: [],
                review_items: [],
                review_summary: { total_questions: 10, correct_questions: 8, wrong_questions: 2 },
                percentage: 80,
                kkm_percentage: 75,
                passing_score: 75,
                is_passed: 1,
                pass_label: 'LULUS',
                result_tone: 'pass'
            });
            var deps = createDeps(state, { apiRequest: apiRequest });
            var manager = createFinishFlowManager(deps);

            await manager.maybeFinalizeLockedExam('recovery-test');

            expect(state.stage).toBe('result');
            expect(state.result).not.toBeNull();
            expect(state.isFinishing).toBe(false);
        });
    });

    describe('restricted result handling', function () {
        it('handles restricted result (show_student_result=0) without score data', async function () {
            var state = createState({
                examLockedForPendingFinish: true,
                pendingSyncCount: 0,
                finishResultPending: true,
                finishReceipt: {
                    attempt_id: 42,
                    exam_id: 10,
                    finished_at: '2026-03-24 11:00:00',
                    status: 'completed',
                    result_view_mode_hint: 'restricted',
                    show_student_result_hint: 0,
                    ack_source: 'finish_exam',
                    pending_result_fetch: 1,
                    updated_at: 1710000000000
                }
            });
            var apiRequest = vi.fn().mockResolvedValue({
                attempt: { id: 42, exam_id: 10, student_id: 5, status: 'completed', started_at: '2026-03-24 10:00:00', finished_at: '2026-03-24 11:00:00' },
                exam: { id: 10, title: 'Exam Test', duration_minutes: 60, show_student_result: 0 },
                show_student_result: 0,
                result_view_mode: 'restricted',
                submission_summary: { total_questions: 10, answered_questions: 8, pending_manual_questions: 0 }
            });
            var deps = createDeps(state, { apiRequest: apiRequest });
            var manager = createFinishFlowManager(deps);

            await manager.maybeFinalizeLockedExam('restricted-test');

            expect(state.stage).toBe('result');
            expect(state.result).not.toBeNull();
            expect(state.result.show_student_result).toBe(0);
            expect(state.result.score).toBe(0);
        });
    });

    describe('network failure during recovery', function () {
        it('sets recovery error and schedules retry on network failure', async function () {
            var state = createState({
                examLockedForPendingFinish: true,
                pendingSyncCount: 0,
                finishResultPending: true,
                finishReceipt: {
                    attempt_id: 42,
                    exam_id: 10,
                    finished_at: '2026-03-24 11:00:00',
                    status: 'completed',
                    result_view_mode_hint: 'full',
                    show_student_result_hint: 1,
                    ack_source: 'finish_exam',
                    pending_result_fetch: 1,
                    updated_at: 1710000000000
                }
            });
            var networkError = new Error('Koneksi terputus.');
            networkError.status = 0;
            networkError.code = 'network_error';
            networkError.isNetworkError = true;
            var apiRequest = vi.fn().mockRejectedValue(networkError);
            var deps = createDeps(state, { apiRequest: apiRequest });
            var manager = createFinishFlowManager(deps);

            await manager.maybeFinalizeLockedExam('network-fail-test');

            expect(state.stage).toBe('exam');
            expect(state.finishResultPending).toBe(true);
            expect(state.finishRecoveryLastError).toContain('Koneksi');
            expect(state.isFinishing).toBe(false);
        });
    });

    describe('finish recovery escape hatch', function () {
        it('records lock start time and only opens exit after the 120 second timeout', async function () {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2026-05-19T10:00:00Z'));
            var state = createState();
            var deps = createDeps(state, {
                getNavigatorConnectionStatus: vi.fn().mockReturnValue('offline'),
                windowRef: {
                    clearTimeout: function (id) {
                        clearTimeout(id);
                    },
                    document: { visibilityState: 'visible' },
                    requestAnimationFrame: function (fn) {
                        fn();
                        return 1;
                    },
                    setTimeout: function (fn, ms) {
                        return setTimeout(fn, ms);
                    }
                }
            });
            var manager = createFinishFlowManager(deps);

            await manager.handleFinish(false, { skipConfirmation: true });

            expect(state.examLockedForPendingFinish).toBe(true);
            expect(state.finishLockStartedAt).toBe(Date.now());
            expect(state.finishRecoveryCanExit).toBe(false);

            vi.advanceTimersByTime(119999);
            await Promise.resolve();
            expect(state.finishRecoveryCanExit).toBe(false);

            vi.advanceTimersByTime(1);
            await Promise.resolve();
            expect(state.finishRecoveryCanExit).toBe(true);
            expect(deps.render).toHaveBeenCalled();
        });

        it('manual finish recovery retry keeps the exam locked and hides the exit option while retrying', async function () {
            vi.useFakeTimers();
            vi.setSystemTime(new Date('2026-05-19T10:00:00Z'));
            var state = createState({
                examLockedForPendingFinish: true,
                finishLockStartedAt: Date.now() - 130000,
                finishRecoveryCanExit: true,
                finishResultPending: true,
                finishReceipt: {
                    attempt_id: 42,
                    exam_id: 10,
                    finished_at: '2026-03-24 11:00:00',
                    status: 'completed',
                    result_view_mode_hint: 'full',
                    show_student_result_hint: 1,
                    ack_source: 'finish_exam',
                    pending_result_fetch: 1,
                    updated_at: 1710000000000
                }
            });
            var deps = createDeps(state, {
                apiRequest: vi.fn().mockRejectedValue(new Error('Result belum tersedia')),
                windowRef: {
                    clearTimeout: function (id) {
                        clearTimeout(id);
                    },
                    document: { visibilityState: 'visible' },
                    requestAnimationFrame: function (fn) {
                        fn();
                        return 1;
                    },
                    setTimeout: function (fn, ms) {
                        return setTimeout(fn, ms);
                    }
                }
            });
            var manager = createFinishFlowManager(deps);

            await manager.maybeFinalizeLockedExam('manual-finish-recovery');

            expect(state.stage).toBe('exam');
            expect(state.examLockedForPendingFinish).toBe(true);
            expect(state.finishRecoveryCanExit).toBe(false);
            expect(state.finishLockStartedAt).toBe(Date.now());
        });
    });
});
