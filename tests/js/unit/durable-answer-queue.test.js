import { describe, expect, it, vi, beforeEach } from 'vitest';
import { createDurableAnswerQueueStorage } from '../../../src/frontend/app/storage/durable-answer-queue.js';

function createQueue(options = {}) {
    var nowMs = options.nowMs || 1710000000000;
    return createDurableAnswerQueueStorage({
        getIndexedDb: function () { return null; },
        getLocalStorage: function () { return globalThis.localStorage; },
        now: function () { return nowMs; },
        ...options
    });
}

function context(overrides = {}) {
    return {
        attemptId: 42,
        examId: 10,
        userId: 5,
        ...overrides
    };
}

describe('createDurableAnswerQueueStorage', function () {
    describe('upsertAnswer', function () {
        it('inserts a new answer item into the queue', async function () {
            var queue = createQueue();
            var ctx = context();

            var result = await queue.upsertAnswer(ctx, {
                question_id: 100,
                answer: 3,
                signature: 'sig-100'
            });

            expect(result).not.toBeNull();
            expect(result.question_id).toBe(100);
            expect(result.answer).toBe(3);
            expect(result.signature).toBe('sig-100');
            expect(result.status).toBe('pending');
            expect(result.attempt_count).toBe(0);
        });

        it('updates existing answer preserving created_at', async function () {
            var queue = createQueue({ nowMs: 1000 });
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 100, answer: 1, signature: 'sig-1' });

            var queue2 = createQueue({ nowMs: 2000 });
            var result = await queue2.upsertAnswer(ctx, { question_id: 100, answer: 2, signature: 'sig-2' });

            expect(result.answer).toBe(2);
            expect(result.signature).toBe('sig-2');
            expect(result.created_at).toBe(1000);
            expect(result.updated_at).toBe(2000);
        });

        it('rejects items with invalid context', async function () {
            var queue = createQueue();

            var result = await queue.upsertAnswer({ attemptId: 0, examId: 10, userId: 5 }, {
                question_id: 100,
                answer: 1,
                signature: 'sig'
            });

            expect(result).toBeNull();
        });

        it('rejects items with zero question_id', async function () {
            var queue = createQueue();

            var result = await queue.upsertAnswer(context(), {
                question_id: 0,
                answer: 1,
                signature: 'sig'
            });

            expect(result).toBeNull();
        });
    });

    describe('listPendingAnswers', function () {
        it('returns only pending and retryable items', async function () {
            var queue = createQueue();
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 1, answer: 'a', signature: 's1' });
            await queue.upsertAnswer(ctx, { question_id: 2, answer: 'b', signature: 's2' });

            var pending = await queue.listPendingAnswers(ctx);

            expect(pending.length).toBe(2);
            expect(pending[0].question_id).toBe(1);
            expect(pending[1].question_id).toBe(2);
        });

        it('excludes acked items', async function () {
            var queue = createQueue();
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 1, answer: 'a', signature: 's1' });
            await queue.upsertAnswer(ctx, { question_id: 2, answer: 'b', signature: 's2' });
            await queue.markAcked(ctx, [{ question_id: 1, signature: 's1' }]);

            var pending = await queue.listPendingAnswers(ctx);

            expect(pending.length).toBe(1);
            expect(pending[0].question_id).toBe(2);
        });
    });

    describe('acquireBatch', function () {
        it('acquires pending items with lease', async function () {
            var queue = createQueue({ nowMs: 5000 });
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 1, answer: 'a', signature: 's1' });
            await queue.upsertAnswer(ctx, { question_id: 2, answer: 'b', signature: 's2' });

            var batch = await queue.acquireBatch(ctx, { limit: 2, owner: 'test', leaseMs: 10000 });

            expect(batch.length).toBe(2);
            expect(batch[0].question_id).toBe(1);
            expect(batch[1].question_id).toBe(2);
        });

        it('respects limit parameter', async function () {
            var queue = createQueue();
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 1, answer: 'a', signature: 's1' });
            await queue.upsertAnswer(ctx, { question_id: 2, answer: 'b', signature: 's2' });
            await queue.upsertAnswer(ctx, { question_id: 3, answer: 'c', signature: 's3' });

            var batch = await queue.acquireBatch(ctx, { limit: 2, owner: 'test', leaseMs: 10000 });

            expect(batch.length).toBe(2);
        });

        it('re-acquires items with expired lease', async function () {
            var nowMs = 1000;
            var queue = createQueue({ nowMs: nowMs });
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 1, answer: 'a', signature: 's1' });
            await queue.acquireBatch(ctx, { limit: 1, owner: 'owner1', leaseMs: 5000 });

            // Simulate lease expiry
            var queue2 = createQueue({ nowMs: nowMs + 6000 });
            var batch = await queue2.acquireBatch(ctx, { limit: 1, owner: 'owner2', leaseMs: 5000 });

            expect(batch.length).toBe(1);
            expect(batch[0].question_id).toBe(1);
        });
    });

    describe('markAcked', function () {
        it('removes items from queue on signature match', async function () {
            var queue = createQueue();
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 1, answer: 'a', signature: 'sig-match' });

            var acked = await queue.markAcked(ctx, [{ question_id: 1, signature: 'sig-match' }]);

            expect(acked.length).toBe(1);
            var remaining = await queue.getPendingCount(ctx);
            expect(remaining).toBe(0);
        });

        it('does not ack on signature mismatch', async function () {
            var queue = createQueue();
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 1, answer: 'a', signature: 'sig-current' });

            var acked = await queue.markAcked(ctx, [{ question_id: 1, signature: 'sig-stale' }]);

            expect(acked.length).toBe(0);
            var remaining = await queue.getPendingCount(ctx);
            expect(remaining).toBe(1);
        });
    });

    describe('releaseBatch', function () {
        it('resets lease and sets failed_retryable status', async function () {
            var queue = createQueue();
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 1, answer: 'a', signature: 's1' });
            var batch = await queue.acquireBatch(ctx, { limit: 1, owner: 'test', leaseMs: 30000 });

            await queue.releaseBatch(ctx, batch, { status: 'failed_retryable', errorMessage: 'network error' });

            var pending = await queue.listPendingAnswers(ctx);
            expect(pending.length).toBe(1);
            expect(pending[0].status).toBe('failed_retryable');
            expect(pending[0].last_error).toBe('network error');
        });
    });

    describe('clearAttempt', function () {
        it('removes all answers and auth grant for attempt', async function () {
            var queue = createQueue();
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 1, answer: 'a', signature: 's1' });
            await queue.upsertAnswer(ctx, { question_id: 2, answer: 'b', signature: 's2' });
            await queue.storeAuthGrant(ctx, { token: 'tok', expiresAtMs: 9999999999999 });

            await queue.clearAttempt(ctx);

            var pending = await queue.getPendingCount(ctx);
            var grant = await queue.getAuthGrant(ctx);
            expect(pending).toBe(0);
            expect(grant).toBeNull();
        });
    });

    describe('importPendingAnswersFromSnapshot', function () {
        it('imports pending answers when queue is empty', async function () {
            var queue = createQueue();
            var ctx = context();

            var pendingByQuestion = {
                100: { answer: 1, signature: 'sig-100' },
                200: { answer: 2, signature: 'sig-200' }
            };

            var imported = await queue.importPendingAnswersFromSnapshot(ctx, pendingByQuestion, [100, 200]);

            expect(imported).toBe(2);
            var pending = await queue.getPendingCount(ctx);
            expect(pending).toBe(2);
        });

        it('skips import when queue already has items', async function () {
            var queue = createQueue();
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 50, answer: 'x', signature: 'existing' });

            var imported = await queue.importPendingAnswersFromSnapshot(ctx, {
                100: { answer: 1, signature: 'sig-100' }
            }, [100]);

            expect(imported).toBe(0);
            var pending = await queue.getPendingCount(ctx);
            expect(pending).toBe(1);
        });
    });

    describe('storeAuthGrant and getAuthGrant', function () {
        it('stores and retrieves a valid grant', async function () {
            var queue = createQueue({ nowMs: 1000 });
            var ctx = context();

            await queue.storeAuthGrant(ctx, { token: 'my-token', expiresAtMs: 99999 });

            var grant = await queue.getAuthGrant(ctx);
            expect(grant).not.toBeNull();
            expect(grant.token).toBe('my-token');
        });

        it('returns null for expired grant', async function () {
            var queue = createQueue({ nowMs: 100000 });
            var ctx = context();

            await queue.storeAuthGrant(ctx, { token: 'expired-token', expiresAtMs: 50000 });

            var grant = await queue.getAuthGrant(ctx);
            expect(grant).toBeNull();
        });
    });

    describe('getPendingCount', function () {
        it('returns correct count excluding acked items', async function () {
            var queue = createQueue();
            var ctx = context();

            await queue.upsertAnswer(ctx, { question_id: 1, answer: 'a', signature: 's1' });
            await queue.upsertAnswer(ctx, { question_id: 2, answer: 'b', signature: 's2' });
            await queue.upsertAnswer(ctx, { question_id: 3, answer: 'c', signature: 's3' });
            await queue.markAcked(ctx, [{ question_id: 2, signature: 's2' }]);

            var count = await queue.getPendingCount(ctx);
            expect(count).toBe(2);
        });
    });
});
