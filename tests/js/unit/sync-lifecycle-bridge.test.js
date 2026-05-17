import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createSyncLifecycleBridge } from '../../../src/frontend/app/core/sync-lifecycle-bridge';

describe('sync-lifecycle-bridge', () => {
    var state, deps, bridge;

    beforeEach(() => {
        state = { stage: 'exam', attemptId: 42, isFinishing: false };
        deps = {
            state: state,
            flushAttemptUiState: vi.fn().mockResolvedValue(undefined),
            flushPendingAnswerBatch: vi.fn().mockResolvedValue(undefined),
            getNavigatorConnectionStatus: vi.fn().mockReturnValue('online'),
            maybeFinalizeLockedExam: vi.fn(),
            queueLoadedQuestionAnswersForFlush: vi.fn(),
            schedulePendingAnswerRetry: vi.fn(),
            setConnectionStatus: vi.fn()
        };
        bridge = createSyncLifecycleBridge(deps);
    });

    describe('flushPendingAnswerBatchSilently', () => {
        it('flushes when in exam stage', () => {
            bridge.flushPendingAnswerBatchSilently({});
            expect(deps.queueLoadedQuestionAnswersForFlush).toHaveBeenCalled();
            expect(deps.flushPendingAnswerBatch).toHaveBeenCalled();
        });

        it('skips when not in exam stage', () => {
            state.stage = 'login';
            bridge.flushPendingAnswerBatchSilently({});
            expect(deps.flushPendingAnswerBatch).not.toHaveBeenCalled();
        });

        it('skips when attemptId is 0', () => {
            state.attemptId = 0;
            bridge.flushPendingAnswerBatchSilently({});
            expect(deps.flushPendingAnswerBatch).not.toHaveBeenCalled();
        });

        it('skips when finishing', () => {
            state.isFinishing = true;
            bridge.flushPendingAnswerBatchSilently({});
            expect(deps.flushPendingAnswerBatch).not.toHaveBeenCalled();
        });
    });

    describe('flushAttemptUiStateSilently', () => {
        it('flushes when in exam stage', () => {
            bridge.flushAttemptUiStateSilently({});
            expect(deps.flushAttemptUiState).toHaveBeenCalled();
        });

        it('skips when not in exam stage', () => {
            state.stage = 'result';
            bridge.flushAttemptUiStateSilently({});
            expect(deps.flushAttemptUiState).not.toHaveBeenCalled();
        });
    });

    describe('triggerPendingSyncLifecycleRetry', () => {
        it('calls setConnectionStatus and schedulePendingAnswerRetry', () => {
            bridge.triggerPendingSyncLifecycleRetry('visibility');
            expect(deps.setConnectionStatus).toHaveBeenCalledWith('online', expect.any(Object));
            expect(deps.schedulePendingAnswerRetry).toHaveBeenCalledWith('visibility', expect.objectContaining({
                immediate: true,
                resetBackoff: true
            }));
            expect(deps.maybeFinalizeLockedExam).toHaveBeenCalledWith('visibility');
        });

        it('uses lifecycle as default reason', () => {
            bridge.triggerPendingSyncLifecycleRetry(null);
            expect(deps.schedulePendingAnswerRetry).toHaveBeenCalledWith('lifecycle', expect.any(Object));
        });

        it('passes delayMs option', () => {
            bridge.triggerPendingSyncLifecycleRetry('resume', { delayMs: 500 });
            expect(deps.schedulePendingAnswerRetry).toHaveBeenCalledWith('resume', expect.objectContaining({
                delayMs: 500
            }));
        });
    });
});
