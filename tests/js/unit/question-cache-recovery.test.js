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

    it('preserves object-map answers and structured metadata during cache recovery', async function () {
        var fixture = createManager();
        var snapshot = createSnapshot({
            question_order_ids: [201, 202, 203, 204],
            question_payload_by_id: {
                201: {
                    id: 201,
                    question_number: 1,
                    question_type: 'matching',
                    matching_meta: {
                        items: [{ key: '1', text: 'Kota' }]
                    },
                    options: [{ id: 71, option_text: 'Tokyo' }]
                },
                202: {
                    id: 202,
                    question_number: 2,
                    question_type: 'cloze_dropdown',
                    cloze_dropdown_meta: {
                        blanks: [
                            {
                                key: '1',
                                options: [{ id: 81, option_text: 'Tokyo' }]
                            }
                        ]
                    }
                },
                203: {
                    id: 203,
                    question_number: 3,
                    question_type: 'categorization',
                    categorization_meta: {
                        items: [{ key: '1', text: 'Kucing' }]
                    },
                    options: [{ id: 91, option_text: 'Mamalia' }]
                },
                204: {
                    id: 204,
                    question_number: 4,
                    question_type: 'table_completion',
                    table_completion_meta: {
                        rows: 1,
                        columns: 2,
                        cells: [
                            { key: 'A1', row: 1, column: 1, type: 'text', text: 'Kota' },
                            {
                                key: 'B1',
                                row: 1,
                                column: 2,
                                type: 'dropdown',
                                text: 'Negara',
                                options: [{ id: 102, option_text: 'Jepang' }]
                            }
                        ]
                    }
                }
            },
            answered_question_lookup: {
                201: true,
                202: true,
                203: true,
                204: true
            },
            answers: {
                201: { 1: 71 },
                202: { 1: 81 },
                203: { 1: 91 },
                204: { A1: 'Tokyo', B1: 102 }
            }
        });

        writeSessionSnapshot(fixture, snapshot);

        var restored = await fixture.manager.readPersistedQuestionCache(55);

        expect(restored.answers).toEqual({
            201: { 1: 71 },
            202: { 1: 81 },
            203: { 1: 91 },
            204: { A1: 'Tokyo', B1: 102 }
        });
        expect(restored.questionPayloadById[201].matching_meta.items[0].key).toBe('1');
        expect(restored.questionPayloadById[202].cloze_dropdown_meta.blanks[0].options[0].id).toBe(81);
        expect(restored.questionPayloadById[203].categorization_meta.items[0].text).toBe('Kucing');
        expect(restored.questionPayloadById[204].table_completion_meta.cells[1].options[0].id).toBe(102);
    });

    it('preserves object-map pending autosave state during cache recovery', async function () {
        var fixture = createManager();
        var objectMapAnswer = { 1: 71, 2: 72 };
        var submittedSignature = JSON.stringify({ 1: 70 });
        var pendingSignature = JSON.stringify(objectMapAnswer);
        var snapshot = createSnapshot({
            question_order_ids: [201],
            question_payload_by_id: {
                201: {
                    id: 201,
                    question_number: 1,
                    question_type: 'matching',
                    matching_meta: {
                        items: [
                            { key: '1', text: 'Kota' },
                            { key: '2', text: 'Negara' }
                        ]
                    },
                    options: [
                        { id: 71, option_text: 'Tokyo' },
                        { id: 72, option_text: 'Jepang' }
                    ]
                }
            },
            answered_question_lookup: {
                201: true
            },
            answers: {
                201: objectMapAnswer
            }
        });
        Object.assign(snapshot, {
            auto_save_congested_until: 1700000005000,
            last_submitted_payload_by_question: {
                201: submittedSignature
            },
            pending_answer_batch_by_question: {
                201: {
                    question_id: 201,
                    answer: objectMapAnswer,
                    signature: pendingSignature
                },
                invalid: {
                    question_id: 0,
                    answer: 'ignored',
                    signature: 'ignored'
                }
            },
            pending_answer_batch_order: [999, 201, 201],
            last_sync_error: 'Koneksi terputus',
            sync_blocking_reason: 'offline_pending_sync',
            exam_locked_for_pending_finish: 1
        });

        writeSessionSnapshot(fixture, snapshot);

        var restored = await fixture.manager.readPersistedQuestionCache(55);

        expect(restored.answers).toEqual({
            201: objectMapAnswer
        });
        expect(restored.pendingAnswerBatchByQuestion).toEqual({
            201: {
                question_id: 201,
                answer: objectMapAnswer,
                signature: pendingSignature
            }
        });
        expect(restored.pendingAnswerBatchOrder).toEqual([201]);
        expect(restored.lastSubmittedPayloadByQuestion).toEqual({
            201: submittedSignature
        });
        expect(restored.autoSaveCongestedUntil).toBe(1700000005000);
        expect(restored.lastSyncError).toBe('Koneksi terputus');
        expect(restored.syncBlockingReason).toBe('offline_pending_sync');
        expect(restored.examLockedForPendingFinish).toBe(true);
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

    it('rejects cached snapshots whose embedded attempt id does not match the requested attempt', async function () {
        var fixture = createManager();
        var staleSnapshot = createSnapshot({
            attempt_id: 54,
            exam_id: 9,
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
            cached_at: 300
        });

        fixture.sessionStorage.setItem(
            fixture.manager.buildQuestionCacheSessionStorageKey(55),
            JSON.stringify(staleSnapshot)
        );

        var restored = await fixture.manager.readPersistedQuestionCache(55);

        expect(restored).toBeNull();
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
