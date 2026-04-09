import { describe, expect, it } from 'vitest';
import { createQuestionCacheStorage } from '../../../src/frontend/app/storage/question-cache.js';

var SESSION_PREFIX = 'cbt-question-cache-session-';
var LOCAL_META_PREFIX = 'cbt-question-cache-meta-';
var LOCAL_ITEM_PREFIX = 'cbt-question-cache-item-';

function createQuestion(questionId, questionNumber) {
    return {
        id: Number(questionId) || 0,
        question_number: Number(questionNumber) || 0,
        question_type: 'multiple_choice',
        question_text: 'Question ' + String(Number(questionId) || 0)
    };
}

function createSnapshot(overrides = {}) {
    var attemptId = Number(overrides.attempt_id) || 55;
    var examId = Number(overrides.exam_id) || 9;
    var version = Number(overrides.version) || 1;
    var invalidatedAt = Number(overrides.invalidated_at) || (version * 10);
    var orderIds = Array.isArray(overrides.question_order_ids) ? overrides.question_order_ids.slice() : [101, 102];
    var payloadById = overrides.question_payload_by_id || {
        101: createQuestion(101, 1),
        102: createQuestion(102, 2)
    };

    return {
        attempt_id: attemptId,
        exam_id: examId,
        question_revision: {
            exam_id: examId,
            namespace: 'exam:' + String(examId),
            version: version,
            invalidated_at: invalidatedAt,
            signature: String(overrides.signature || ('exam:' + String(examId) + '|v:' + String(version) + '|t:' + String(invalidatedAt)))
        },
        question_order_signature: String(overrides.question_order_signature || 'a'.repeat(40)),
        total_questions: orderIds.length,
        question_order_ids: orderIds,
        question_payload_by_id: payloadById,
        answered_question_lookup: Object.assign({}, overrides.answered_question_lookup || {}),
        changed_question_lookup: Object.assign({}, overrides.changed_question_lookup || {}),
        answers: Object.assign({}, overrides.answers || {}),
        existing_answer_raw_by_question_id: Object.assign({}, overrides.existing_answer_raw_by_question_id || {}),
        finish_receipt: overrides.finish_receipt || null,
        cached_at: Number(overrides.cached_at) || 100
    };
}

function createManager(overrides = {}) {
    var sessionStorage = overrides.sessionStorage || globalThis.sessionStorage;
    var localStorage = overrides.localStorage || globalThis.localStorage;
    var state = Object.assign({
        user: {
            user_id: 77
        },
        attemptId: 55,
        selectedExamId: 9,
        totalQuestions: 0,
        questionOrderIds: [],
        questionManifest: [],
        questionPayloadById: {},
        answeredQuestionLookup: {},
        changedQuestionLookup: {},
        questionRevisionMarkerLookup: {},
        acknowledgedRevisionQuestionIds: {},
        answers: {},
        existingAnswerRawByQuestionId: {},
        loadedQuestionWindowOffsets: {},
        windowOffset: 0,
        windowLimit: 0
    }, overrides.state || {});

    var manager = createQuestionCacheStorage({
        state: state,
        getSessionStorage: function () {
            return sessionStorage;
        },
        getLocalStorage: function () {
            return localStorage;
        },
        getIndexedDb: function () {
            return undefined;
        },
        indexedDbName: 'cbt-test-question-cache',
        indexedDbStore: 'question-cache',
        sessionStorageKeyPrefix: SESSION_PREFIX,
        metaLocalStorageKeyPrefix: LOCAL_META_PREFIX,
        itemLocalStorageKeyPrefix: LOCAL_ITEM_PREFIX,
        normalizeExistingAnswerForQuestion: function (question) {
            if (question && Object.prototype.hasOwnProperty.call(question, 'existing_answer')) {
                return {
                    hasValue: true,
                    value: question.existing_answer
                };
            }

            return {
                hasValue: false,
                value: null
            };
        },
        getQuestionPayloadById: function (questionId) {
            return state.questionPayloadById[questionId] || null;
        },
        payloadSignature: function (payload) {
            return JSON.stringify(payload || null);
        },
        getAutoSaveState: function () {
            return {};
        },
        now: function () {
            return 1700000000000;
        },
        windowRef: {}
    });

    return {
        manager: manager,
        sessionStorage: sessionStorage,
        localStorage: localStorage
    };
}

function buildLocalMetaKey(attemptId) {
    return LOCAL_META_PREFIX + '77_' + String(Number(attemptId) || 0);
}

function buildLocalItemKey(attemptId, questionId) {
    return LOCAL_ITEM_PREFIX + '77_' + String(Number(attemptId) || 0) + '_' + String(Number(questionId) || 0);
}

function writeSessionSnapshot(fixture, snapshot) {
    fixture.sessionStorage.setItem(
        fixture.manager.buildQuestionCacheSessionStorageKey(snapshot.attempt_id),
        JSON.stringify(snapshot)
    );
}

function writeLocalSnapshot(fixture, snapshot) {
    var storedQuestionIds = Object.keys(snapshot.question_payload_by_id || {}).reduce(function (accumulator, key) {
        var questionId = Number(key) || 0;
        if (questionId > 0) {
            accumulator.push(questionId);
        }
        return accumulator;
    }, []);

    var metaSnapshot = Object.assign({}, snapshot, {
        stored_question_ids: storedQuestionIds
    });
    delete metaSnapshot.question_payload_by_id;

    fixture.localStorage.setItem(buildLocalMetaKey(snapshot.attempt_id), JSON.stringify(metaSnapshot));
    storedQuestionIds.forEach(function (questionId) {
        fixture.localStorage.setItem(
            buildLocalItemKey(snapshot.attempt_id, questionId),
            JSON.stringify(snapshot.question_payload_by_id[questionId])
        );
    });
}

describe('question cache recovery', function () {
    it('merges compatible snapshots and preserves valid answers from both sources', async function () {
        var fixture = createManager();
        var sessionSnapshot = createSnapshot({
            question_order_signature: 'a'.repeat(40),
            question_payload_by_id: {
                101: createQuestion(101, 1)
            },
            answered_question_lookup: {
                101: true
            },
            answers: {
                101: 'A'
            },
            cached_at: 100
        });
        var localSnapshot = createSnapshot({
            question_order_signature: 'a'.repeat(40),
            question_payload_by_id: {
                102: createQuestion(102, 2)
            },
            answered_question_lookup: {
                102: true
            },
            answers: {
                102: 'B'
            },
            cached_at: 200
        });

        writeSessionSnapshot(fixture, sessionSnapshot);
        writeLocalSnapshot(fixture, localSnapshot);

        var restored = await fixture.manager.readPersistedQuestionCache(55);

        expect(restored.questionRevision.version).toBe(1);
        expect(restored.questionOrderIds).toEqual([101, 102]);
        expect(restored.questionOrderSignature).toMatch(/^[a-f0-9]{40}$/);
        expect(restored.answers).toEqual({
            101: 'A',
            102: 'B'
        });
        expect(Object.keys(restored.questionPayloadById).map(Number).sort(function (left, right) {
            return left - right;
        })).toEqual([101, 102]);
    });

    it('rejects incompatible stale snapshots when revision changes and keeps the newer valid answer set', async function () {
        var fixture = createManager();
        var staleSessionSnapshot = createSnapshot({
            version: 1,
            signature: 'exam:9|v:1|t:10',
            question_order_signature: 'a'.repeat(40),
            question_order_ids: [101],
            question_payload_by_id: {
                101: createQuestion(101, 1)
            },
            answered_question_lookup: {
                101: true
            },
            answers: {
                101: 'A'
            },
            cached_at: 100
        });
        var freshLocalSnapshot = createSnapshot({
            version: 2,
            signature: 'exam:9|v:2|t:20',
            question_order_signature: 'b'.repeat(40),
            question_order_ids: [102],
            question_payload_by_id: {
                102: createQuestion(102, 2)
            },
            answered_question_lookup: {
                102: true
            },
            answers: {
                102: 'B'
            },
            cached_at: 200
        });

        writeSessionSnapshot(fixture, staleSessionSnapshot);
        writeLocalSnapshot(fixture, freshLocalSnapshot);

        var restored = await fixture.manager.readPersistedQuestionCache(55);

        expect(restored.questionRevision.version).toBe(2);
        expect(restored.questionOrderIds).toEqual([102]);
        expect(restored.answers).toEqual({
            102: 'B'
        });
        expect(Object.keys(restored.questionPayloadById).map(Number)).toEqual([102]);
    });

    it('preserves the latest valid finish receipt when session and local cache are merged', async function () {
        var fixture = createManager();
        var sessionSnapshot = createSnapshot({
            answers: {
                101: 'A'
            },
            finish_receipt: {
                attempt_id: 55,
                exam_id: 9,
                finished_at: '2026-04-09 09:00:00',
                status: 'completed',
                result_view_mode_hint: 'full',
                show_student_result_hint: 1,
                ack_source: 'finish_exam',
                pending_result_fetch: 1,
                updated_at: 100
            }
        });
        var localSnapshot = createSnapshot({
            answers: {
                102: 'B'
            },
            finish_receipt: {
                attempt_id: 55,
                exam_id: 9,
                finished_at: '2026-04-09 09:01:00',
                status: 'completed',
                result_view_mode_hint: 'restricted',
                show_student_result_hint: 0,
                ack_source: 'idempotent_finish_exam',
                pending_result_fetch: 1,
                updated_at: 200
            }
        });

        writeSessionSnapshot(fixture, sessionSnapshot);
        writeLocalSnapshot(fixture, localSnapshot);

        var restored = await fixture.manager.readPersistedQuestionCache(55);

        expect(restored.finishReceipt).toMatchObject({
            attemptId: 55,
            examId: 9,
            resultViewModeHint: 'restricted',
            showStudentResultHint: 0,
            ackSource: 'idempotent_finish_exam',
            pendingResultFetch: true,
            updatedAt: 200
        });
    });

    it('finds the latest persisted finish recovery snapshot for the selected exam across attempts', async function () {
        var fixture = createManager();
        var staleSnapshot = createSnapshot({
            attempt_id: 54,
            exam_id: 9,
            finish_receipt: {
                attempt_id: 54,
                exam_id: 9,
                finished_at: '2026-04-09 07:00:00',
                status: 'completed',
                result_view_mode_hint: 'full',
                show_student_result_hint: 1,
                ack_source: 'finish_exam',
                pending_result_fetch: 1,
                updated_at: 100
            },
            cached_at: 100
        });
        var freshSnapshot = createSnapshot({
            attempt_id: 55,
            exam_id: 9,
            finish_receipt: {
                attempt_id: 55,
                exam_id: 9,
                finished_at: '2026-04-09 08:00:00',
                status: 'completed',
                result_view_mode_hint: 'full',
                show_student_result_hint: 1,
                ack_source: 'finish_exam',
                pending_result_fetch: 1,
                updated_at: 200
            },
            cached_at: 200
        });

        writeLocalSnapshot(fixture, staleSnapshot);
        writeSessionSnapshot(fixture, freshSnapshot);

        var recovered = await fixture.manager.findPersistedFinishRecoveryForExam(9);

        expect(recovered).not.toBeNull();
        expect(recovered.attemptId).toBe(55);
        expect(recovered.finishReceipt).toMatchObject({
            attemptId: 55,
            examId: 9,
            status: 'completed',
            updatedAt: 200
        });
        expect(recovered.snapshot).toBeTruthy();
    });
});
