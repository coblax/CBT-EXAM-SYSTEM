import { describe, expect, it } from 'vitest';
import { createAttemptUiStateStorage } from '../../../src/frontend/app/storage/attempt-ui-state.js';

function createManager(overrides = {}) {
    var questionOrderIds = Array.isArray(overrides.questionOrderIds) ? overrides.questionOrderIds.slice() : [101, 102, 103];
    var storage = overrides.storage || globalThis.sessionStorage;
    var legacyDoubtfulState = Object.assign({}, overrides.legacyDoubtfulState || {});
    var clearedAttemptIds = [];
    var now = overrides.now || function () {
        return 1700000000000;
    };
    var state = Object.assign({
        user: {
            user_id: 77
        },
        attemptId: 55,
        currentIndex: 0,
        doubtful: {},
        questionOrderIds: questionOrderIds
    }, overrides.state || {});

    var manager = createAttemptUiStateStorage({
        state,
        getSessionStorage: function () {
            return storage;
        },
        getQuestionIdAtIndex: function (index) {
            return Number(questionOrderIds[index]) || 0;
        },
        storageKeyPrefix: 'cbt-attempt-ui-',
        buildDoubtfulSessionStorageKey: function (attemptId) {
            return 'legacy-doubtful-' + String(Number(attemptId) || 0);
        },
        clearPersistedDoubtfulState: function (attemptId) {
            clearedAttemptIds.push(Number(attemptId) || 0);
            Object.keys(legacyDoubtfulState).forEach(function (key) {
                delete legacyDoubtfulState[key];
            });
        },
        readPersistedDoubtfulState: function () {
            return Object.assign({}, legacyDoubtfulState);
        },
        now: now,
        getQuestionCount: function () {
            return questionOrderIds.length;
        },
        normalizeOrUseQuestionCacheSnapshot: function (snapshot) {
            return snapshot || null;
        },
        normalizeQuestionCacheSnapshot: function (snapshot) {
            return snapshot || null;
        },
        validAttemptQuestionIds: function () {
            return questionOrderIds.reduce(function (lookup, questionId) {
                lookup[questionId] = true;
                return lookup;
            }, {});
        }
    });

    return {
        clearedAttemptIds,
        manager,
        state,
        storage
    };
}

describe('createAttemptUiStateStorage', function () {
    it('returns null when stored snapshot JSON is invalid and no legacy doubtful state exists', function () {
        var fixture = createManager();
        var storageKey = fixture.manager.buildAttemptUiSessionStorageKey(55);

        fixture.storage.setItem(storageKey, '{"bad-json"');

        expect(fixture.manager.readPersistedAttemptUiState(55)).toBeNull();
    });

    it('falls back to legacy doubtful state when the stored snapshot cannot be parsed', function () {
        var fixture = createManager({
            legacyDoubtfulState: {
                101: true,
                999: true,
                103: true
            }
        });
        var storageKey = fixture.manager.buildAttemptUiSessionStorageKey(55);

        fixture.storage.setItem(storageKey, '{"bad-json"');

        expect(fixture.manager.readPersistedAttemptUiState(55)).toEqual({
            attempt_id: 55,
            current_index: 0,
            current_question_id: 101,
            doubtful_question_ids: [101, 103]
        });
    });

    it('normalizes invalid current_question_id back to a valid index in the active order', function () {
        var fixture = createManager();

        expect(fixture.manager.normalizeAttemptUiState({
            attempt_id: 55,
            current_index: 2,
            current_question_id: 999,
            doubtful_question_ids: [103, 999, 103]
        }, 55)).toEqual({
            attempt_id: 55,
            current_index: 2,
            current_question_id: 103,
            doubtful_question_ids: [103]
        });
    });

    it('prefers the local snapshot when question cache has payload for the local current index', function () {
        var fixture = createManager();

        expect(fixture.manager.choosePreferredAttemptUiState(
            {
                attempt_id: 55,
                current_index: 0,
                current_question_id: 101,
                doubtful_question_ids: [101]
            },
            {
                attempt_id: 55,
                current_index: 1,
                current_question_id: 102,
                doubtful_question_ids: [102]
            },
            {
                questionOrderIds: [101, 102, 103],
                questionPayloadById: {
                    102: { id: 102 }
                }
            },
            55
        )).toEqual({
            attempt_id: 55,
            current_index: 1,
            current_question_id: 102,
            doubtful_question_ids: [102, 101]
        });
    });

    it('prefers the remote snapshot when only the remote current index has cache payload', function () {
        var fixture = createManager();

        expect(fixture.manager.choosePreferredAttemptUiState(
            {
                attempt_id: 55,
                current_index: 2,
                current_question_id: 103,
                doubtful_question_ids: [103]
            },
            {
                attempt_id: 55,
                current_index: 0,
                current_question_id: 101,
                doubtful_question_ids: [101]
            },
            {
                questionOrderIds: [101, 102, 103],
                questionPayloadById: {
                    103: { id: 103 }
                }
            },
            55
        )).toEqual({
            attempt_id: 55,
            current_index: 2,
            current_question_id: 103,
            doubtful_question_ids: [101, 103]
        });
    });

    it('prefers the newer snapshot when no cache payload is available for conflict resolution', function () {
        var fixture = createManager();

        expect(fixture.manager.choosePreferredAttemptUiState(
            {
                attempt_id: 55,
                current_index: 2,
                current_question_id: 103,
                updated_at: 100,
                doubtful_question_ids: [103]
            },
            {
                attempt_id: 55,
                current_index: 1,
                current_question_id: 102,
                updated_at: 200,
                doubtful_question_ids: [102]
            },
            null,
            55
        )).toEqual({
            attempt_id: 55,
            current_index: 1,
            current_question_id: 102,
            updated_at: 200,
            doubtful_question_ids: [102, 103]
        });
    });

    it('persists the current attempt UI state and restores it with valid doubtful ids only', function () {
        var fixture = createManager({
            state: {
                currentIndex: 1,
                doubtful: {
                    101: true,
                    999: true,
                    102: true
                }
            }
        });

        fixture.manager.persistCurrentAttemptUiStateLocally();

        expect(fixture.manager.readPersistedAttemptUiState(55)).toEqual({
            attempt_id: 55,
            current_index: 1,
            current_question_id: 102,
            updated_at: 1700000000000,
            doubtful_question_ids: [101, 102]
        });
    });

    it('clears the stored snapshot and legacy doubtful state together', function () {
        var fixture = createManager({
            legacyDoubtfulState: {
                101: true
            }
        });
        var storageKey = fixture.manager.buildAttemptUiSessionStorageKey(55);
        fixture.storage.setItem(storageKey, JSON.stringify({
            attempt_id: 55,
            current_index: 0,
            current_question_id: 101,
            doubtful_question_ids: [101]
        }));

        fixture.manager.clearPersistedAttemptUiState(55);

        expect(fixture.storage.getItem(storageKey)).toBeNull();
        expect(fixture.clearedAttemptIds).toEqual([55]);
    });
});
