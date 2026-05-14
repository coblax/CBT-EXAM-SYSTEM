import { describe, expect, it } from 'vitest';
import { createDurableAnswerQueueStorage } from '../../../src/frontend/app/storage/durable-answer-queue.js';

function createQueue(nowRef) {
    return createDurableAnswerQueueStorage({
        getIndexedDb: function () {
            return null;
        },
        getLocalStorage: function () {
            return localStorage;
        },
        now: function () {
            return nowRef.value;
        }
    });
}

describe('createDurableAnswerQueueStorage', function () {
    it('keeps the latest answer when an older in-flight ack returns late', async function () {
        var nowRef = { value: 1000 };
        var queue = createQueue(nowRef);
        var context = {
            attemptId: 55,
            examId: 9,
            userId: 7
        };

        await queue.upsertAnswer(context, {
            question_id: 101,
            answer: { choice: 'A' },
            signature: 'sig-a'
        });

        var acquired = await queue.acquireBatch(context, {
            limit: 10,
            owner: 'main:test',
            leaseMs: 30000
        });
        expect(acquired).toHaveLength(1);
        expect(acquired[0]).toMatchObject({
            question_id: 101,
            signature: 'sig-a',
            status: 'syncing'
        });

        nowRef.value = 2000;
        await queue.upsertAnswer(context, {
            question_id: 101,
            answer: { choice: 'B' },
            signature: 'sig-b'
        });

        await queue.markAcked(context, acquired);
        var pending = await queue.listPendingAnswers(context);
        expect(pending).toHaveLength(1);
        expect(pending[0]).toMatchObject({
            answer: { choice: 'B' },
            question_id: 101,
            signature: 'sig-b',
            status: 'pending'
        });

        var latest = await queue.acquireBatch(context, {
            limit: 10,
            owner: 'main:test',
            leaseMs: 30000
        });
        await queue.markAcked(context, latest);
        expect(await queue.getPendingCount(context)).toBe(0);
    });

    it('imports legacy pending autosave snapshots only when the durable queue is empty', async function () {
        var nowRef = { value: 1000 };
        var queue = createQueue(nowRef);
        var context = {
            attemptId: 56,
            examId: 9,
            userId: 7
        };

        var imported = await queue.importPendingAnswersFromSnapshot(context, {
            201: {
                answer: { text: 'lama' },
                signature: 'sig-old'
            }
        }, [201]);

        expect(imported).toBe(1);
        expect(await queue.getPendingCount(context)).toBe(1);

        var skipped = await queue.importPendingAnswersFromSnapshot(context, {
            202: {
                answer: { text: 'baru' },
                signature: 'sig-new'
            }
        }, [202]);

        expect(skipped).toBe(0);
        var pending = await queue.listPendingAnswers(context);
        expect(pending.map(function (item) {
            return item.question_id;
        })).toEqual([201]);
    });

    it('stores scoped auth grants with expiry and clears them per attempt', async function () {
        var nowRef = { value: 1000 };
        var queue = createQueue(nowRef);
        var context = {
            attemptId: 57,
            examId: 9,
            userId: 7
        };

        await queue.storeAuthGrant(context, {
            token: 'sync-token',
            expiresAtMs: 5000
        });

        expect(await queue.getAuthGrant(context)).toMatchObject({
            token: 'sync-token'
        });

        nowRef.value = 6000;
        expect(await queue.getAuthGrant(context)).toBeNull();

        await queue.storeAuthGrant(context, {
            token: 'sync-token-2',
            expiresAtMs: 9000
        });
        await queue.clearAuthGrant(context);
        expect(await queue.getAuthGrant(context)).toBeNull();
    });
});
