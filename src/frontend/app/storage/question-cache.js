export function createQuestionCacheStorage(deps) {
    var state = deps.state;
    var getSessionStorage = deps.getSessionStorage;
    var getLocalStorage = deps.getLocalStorage;
    var getIndexedDb = deps.getIndexedDb;
    var indexedDbName = String(deps.indexedDbName || '');
    var indexedDbStore = String(deps.indexedDbStore || '');
    var sessionStorageKeyPrefix = String(deps.sessionStorageKeyPrefix || '');
    var metaLocalStorageKeyPrefix = String(deps.metaLocalStorageKeyPrefix || '');
    var itemLocalStorageKeyPrefix = String(deps.itemLocalStorageKeyPrefix || '');
    var normalizeExistingAnswerForQuestion = deps.normalizeExistingAnswerForQuestion;
    var getQuestionPayloadById = deps.getQuestionPayloadById;
    var payloadSignature = deps.payloadSignature;
    var getAutoSaveState = deps.getAutoSaveState;
    var now = typeof deps.now === 'function' ? deps.now : Date.now;
    var windowRef = deps.windowRef;
    var questionCacheIndexedDbPromise = null;

    function openQuestionCacheIndexedDb() {
        if (questionCacheIndexedDbPromise !== null) {
            return questionCacheIndexedDbPromise;
        }

        var indexedDb = getIndexedDb();
        if (!indexedDb || indexedDbName === '' || indexedDbStore === '') {
            questionCacheIndexedDbPromise = Promise.resolve(null);
            return questionCacheIndexedDbPromise;
        }

        questionCacheIndexedDbPromise = new Promise(function (resolve) {
            var request;
            try {
                request = indexedDb.open(indexedDbName, 1);
            } catch (error) {
                resolve(null);
                return;
            }

            request.onupgradeneeded = function () {
                var database = request.result;
                if (!database.objectStoreNames.contains(indexedDbStore)) {
                    database.createObjectStore(indexedDbStore, {
                        keyPath: 'cache_key'
                    });
                }
            };

            request.onsuccess = function () {
                resolve(request.result || null);
            };

            request.onerror = function () {
                resolve(null);
            };

            request.onblocked = function () {
                resolve(null);
            };
        });

        return questionCacheIndexedDbPromise;
    }

    function setQuestionCacheRestoreDebug(summary) {
        try {
            if (windowRef) {
                windowRef.__CBTQuestionCacheDebug = summary && typeof summary === 'object' ? summary : null;
            }
        } catch (error) {
            // Ignore debug export failures.
        }
    }

    function buildQuestionCacheSessionStorageKey(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || userId <= 0 || sessionStorageKeyPrefix === '') {
            return '';
        }
        return sessionStorageKeyPrefix + String(userId) + '_' + String(safeAttemptId);
    }

    function buildQuestionCacheMetaLocalStorageKey(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || userId <= 0 || metaLocalStorageKeyPrefix === '') {
            return '';
        }
        return metaLocalStorageKeyPrefix + String(userId) + '_' + String(safeAttemptId);
    }

    function buildQuestionCacheItemLocalStorageKey(attemptId, questionId) {
        var safeAttemptId = Number(attemptId) || 0;
        var safeQuestionId = Number(questionId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || safeQuestionId <= 0 || userId <= 0 || itemLocalStorageKeyPrefix === '') {
            return '';
        }
        return itemLocalStorageKeyPrefix + String(userId) + '_' + String(safeAttemptId) + '_' + String(safeQuestionId);
    }

    function buildQuestionCacheIndexedDbMetaKey(attemptId) {
        var storageKey = buildQuestionCacheSessionStorageKey(attemptId);
        return storageKey === '' ? '' : (storageKey + '__meta');
    }

    function buildQuestionCacheIndexedDbItemKey(attemptId, questionId) {
        var storageKey = buildQuestionCacheSessionStorageKey(attemptId);
        var safeQuestionId = Number(questionId) || 0;
        if (storageKey === '' || safeQuestionId <= 0) {
            return '';
        }
        return storageKey + '__item_' + String(safeQuestionId);
    }

    function buildQuestionCacheSessionStorageMetaKey(attemptId) {
        var storageKey = buildQuestionCacheSessionStorageKey(attemptId);
        return storageKey === '' ? '' : (storageKey + '__meta');
    }

    function buildQuestionCacheSessionStorageItemKey(attemptId, questionId) {
        var storageKey = buildQuestionCacheSessionStorageKey(attemptId);
        var safeQuestionId = Number(questionId) || 0;
        if (storageKey === '' || safeQuestionId <= 0) {
            return '';
        }
        return storageKey + '__item_' + String(safeQuestionId);
    }

    function buildQuestionCacheLocalStorageItemKeyPrefix(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var userId = Number(state.user && state.user.user_id) || 0;
        if (safeAttemptId <= 0 || userId <= 0 || itemLocalStorageKeyPrefix === '') {
            return '';
        }
        return itemLocalStorageKeyPrefix + String(userId) + '_' + String(safeAttemptId) + '_';
    }

    function buildQuestionCacheItemKeyPrefix(storageKey) {
        return storageKey === '' ? '' : (storageKey + '__item_');
    }

    function parseQuestionIdFromCacheItemKey(cacheKey, keyPrefix) {
        var safeKey = String(cacheKey || '');
        var safePrefix = String(keyPrefix || '');
        if (safeKey === '' || safePrefix === '' || safeKey.indexOf(safePrefix) !== 0) {
            return 0;
        }

        return Number(safeKey.slice(safePrefix.length)) || 0;
    }

    function normalizeQuestionIdList(rawQuestionIds) {
        if (!Array.isArray(rawQuestionIds)) {
            return [];
        }

        var seen = {};
        return rawQuestionIds.reduce(function (accumulator, item) {
            var questionId = Number(item) || 0;
            if (questionId <= 0 || seen[questionId]) {
                return accumulator;
            }
            seen[questionId] = true;
            accumulator.push(questionId);
            return accumulator;
        }, []);
    }

    function mergeQuestionCacheStoredIds(primaryIds, secondaryIds) {
        return normalizeQuestionIdList([].concat(
            Array.isArray(primaryIds) ? primaryIds : [],
            Array.isArray(secondaryIds) ? secondaryIds : []
        ));
    }

    function collectStorageQuestionCacheIds(storage, itemKeyPrefix) {
        if (!storage || itemKeyPrefix === '') {
            return [];
        }

        var discoveredIds = [];
        try {
            var storageLength = Number(storage.length) || 0;
            for (var index = 0; index < storageLength; index++) {
                var currentKey = typeof storage.key === 'function' ? storage.key(index) : '';
                var questionId = parseQuestionIdFromCacheItemKey(currentKey, itemKeyPrefix);
                if (questionId > 0) {
                    discoveredIds.push(questionId);
                }
            }
        } catch (error) {
            return normalizeQuestionIdList(discoveredIds);
        }

        return normalizeQuestionIdList(discoveredIds);
    }

    function normalizeQuestionRevision(rawRevision, fallbackExamId) {
        var revision = rawRevision && typeof rawRevision === 'object' ? rawRevision : {};
        var examId = Number(revision.exam_id !== undefined ? revision.exam_id : fallbackExamId) || 0;
        var namespace = String(revision.namespace || '');
        var version = Math.max(0, Number(revision.version) || 0);
        var invalidatedAt = Math.max(0, Number(revision.invalidated_at) || 0);
        var signature = String(revision.signature || '');

        if (namespace === '' && examId > 0) {
            namespace = 'exam:' + String(examId);
        }
        if (signature === '' && namespace !== '' && version > 0) {
            signature = namespace + '|v:' + String(version) + '|t:' + String(invalidatedAt);
        }

        if (examId <= 0 || namespace === '' || version <= 0 || signature === '') {
            return null;
        }

        return {
            examId: examId,
            namespace: namespace,
            version: version,
            invalidatedAt: invalidatedAt,
            signature: signature
        };
    }

    function serializeQuestionRevision(revision, fallbackExamId) {
        var normalized = normalizeQuestionRevision(revision, fallbackExamId);
        if (!normalized) {
            return null;
        }

        return {
            exam_id: normalized.examId,
            namespace: normalized.namespace,
            version: normalized.version,
            invalidated_at: normalized.invalidatedAt,
            signature: normalized.signature
        };
    }

    function questionRevisionSignature(revision, fallbackExamId) {
        var normalized = normalizeQuestionRevision(revision, fallbackExamId);
        return normalized ? String(normalized.signature || '') : '';
    }

    function questionRevisionEquals(leftRevision, rightRevision, fallbackExamId) {
        var leftSignature = questionRevisionSignature(leftRevision, fallbackExamId);
        var rightSignature = questionRevisionSignature(rightRevision, fallbackExamId);
        if (leftSignature === '' || rightSignature === '') {
            return leftSignature === rightSignature;
        }
        return leftSignature === rightSignature;
    }

    function compareQuestionRevisionFreshness(leftRevision, rightRevision, fallbackExamId) {
        var left = normalizeQuestionRevision(leftRevision, fallbackExamId);
        var right = normalizeQuestionRevision(rightRevision, fallbackExamId);

        if (!left && !right) {
            return 0;
        }
        if (left && !right) {
            return 1;
        }
        if (!left && right) {
            return -1;
        }
        if (left.version !== right.version) {
            return left.version > right.version ? 1 : -1;
        }
        if (left.invalidatedAt !== right.invalidatedAt) {
            return left.invalidatedAt > right.invalidatedAt ? 1 : -1;
        }
        return 0;
    }

    function normalizeQuestionManifestItem(question) {
        var item = question && typeof question === 'object' ? question : {};
        var questionId = Number(item.id) || 0;
        if (questionId <= 0) {
            return null;
        }

        var normalized = {
            id: questionId,
            question_type: String(item.question_type || ''),
            updated_at: String(item.updated_at || '')
        };

        if (typeof item.question_text === 'string' && item.question_text !== '') {
            normalized.question_text = String(item.question_text);
        }

        if (Object.prototype.hasOwnProperty.call(item, 'points')) {
            normalized.points = Number(item.points) || 0;
        }

        var questionNumber = Number(item.question_number) || 0;
        if (questionNumber > 0) {
            normalized.question_number = questionNumber;
        }

        if (Array.isArray(item.options)) {
            normalized.options = item.options.map(function (option) {
                var optionItem = option && typeof option === 'object' ? option : {};
                return {
                    id: Number(optionItem.id) || 0,
                    option_key: String(optionItem.option_key || ''),
                    option_text: String(optionItem.option_text || ''),
                    is_correct: Number(optionItem.is_correct) === 1 ? 1 : 0
                };
            }).filter(function (option) {
                return Number(option.id) > 0;
            });
        }

        if (item.true_false_matrix_meta && typeof item.true_false_matrix_meta === 'object') {
            normalized.true_false_matrix_meta = item.true_false_matrix_meta;
        }

        if (item.short_answer_meta && typeof item.short_answer_meta === 'object') {
            normalized.short_answer_meta = item.short_answer_meta;
        }

        return normalized;
    }

    function buildQuestionManifestFromQuestions(questions) {
        if (!Array.isArray(questions)) {
            return [];
        }

        return questions.reduce(function (accumulator, question) {
            var normalized = normalizeQuestionManifestItem(question);
            if (normalized) {
                accumulator.push(normalized);
            }
            return accumulator;
        }, []);
    }

    function buildQuestionManifestById(manifestItems) {
        if (!Array.isArray(manifestItems)) {
            return {};
        }

        return manifestItems.reduce(function (accumulator, item) {
            var normalized = normalizeQuestionManifestItem(item);
            if (!normalized) {
                return accumulator;
            }
            accumulator[normalized.id] = normalized;
            return accumulator;
        }, {});
    }

    function questionManifestUpdatedAt(question) {
        var normalized = normalizeQuestionManifestItem(question);
        if (!normalized) {
            return '';
        }

        return String(normalized.updated_at || '').trim();
    }

    function questionManifestContentSignature(question) {
        var normalized = normalizeQuestionManifestItem(question);
        if (!normalized) {
            return '';
        }

        delete normalized.updated_at;
        delete normalized.question_number;
        return payloadSignature(normalized);
    }

    function normalizeStoredQuestionPayloadById(rawQuestionPayloadById) {
        if (!rawQuestionPayloadById || typeof rawQuestionPayloadById !== 'object') {
            return {};
        }

        return Object.keys(rawQuestionPayloadById).reduce(function (accumulator, key) {
            var question = rawQuestionPayloadById[key];
            var questionId = Number(question && question.id !== undefined ? question.id : key) || 0;
            if (questionId <= 0 || !question || typeof question !== 'object') {
                return accumulator;
            }

            accumulator[questionId] = question;
            return accumulator;
        }, {});
    }

    function normalizeStoredBooleanLookup(rawLookup) {
        if (!rawLookup || typeof rawLookup !== 'object') {
            return {};
        }

        return Object.keys(rawLookup).reduce(function (accumulator, key) {
            var numericKey = Number(key) || 0;
            if (numericKey > 0 && rawLookup[key]) {
                accumulator[numericKey] = true;
            }
            return accumulator;
        }, {});
    }

    function normalizeStoredAnswers(rawAnswers) {
        if (!rawAnswers || typeof rawAnswers !== 'object') {
            return {};
        }

        return Object.keys(rawAnswers).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0) {
                accumulator[questionId] = rawAnswers[key];
            }
            return accumulator;
        }, {});
    }

    function normalizeStoredExistingAnswerRawMap(rawMap) {
        if (!rawMap || typeof rawMap !== 'object') {
            return {};
        }

        return Object.keys(rawMap).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && rawMap[key] !== undefined) {
                accumulator[questionId] = rawMap[key];
            }
            return accumulator;
        }, {});
    }

    function normalizeStoredStringLookup(rawLookup) {
        if (!rawLookup || typeof rawLookup !== 'object') {
            return {};
        }

        return Object.keys(rawLookup).reduce(function (accumulator, key) {
            var numericKey = Number(key) || 0;
            if (numericKey > 0 && typeof rawLookup[key] === 'string') {
                accumulator[numericKey] = rawLookup[key];
            }
            return accumulator;
        }, {});
    }

    function normalizeStoredPendingAnswerBatchMap(rawMap) {
        if (!rawMap || typeof rawMap !== 'object') {
            return {};
        }

        return Object.keys(rawMap).reduce(function (accumulator, key) {
            var item = rawMap[key];
            var questionId = Number(item && item.question_id !== undefined ? item.question_id : key) || 0;
            if (questionId <= 0 || !item || typeof item !== 'object') {
                return accumulator;
            }

            accumulator[questionId] = {
                question_id: questionId,
                answer: Object.prototype.hasOwnProperty.call(item, 'answer') ? item.answer : null,
                signature: String(item.signature || '')
            };
            return accumulator;
        }, {});
    }

    function normalizeStoredPendingAnswerBatchOrder(rawOrder, pendingMap) {
        var normalizedPendingMap = pendingMap && typeof pendingMap === 'object' ? pendingMap : {};
        if (!Array.isArray(rawOrder)) {
            rawOrder = [];
        }

        var seen = {};
        var order = rawOrder.reduce(function (accumulator, item) {
            var questionId = Number(item) || 0;
            if (questionId <= 0 || seen[questionId] || !Object.prototype.hasOwnProperty.call(normalizedPendingMap, questionId)) {
                return accumulator;
            }
            seen[questionId] = true;
            accumulator.push(questionId);
            return accumulator;
        }, []);

        Object.keys(normalizedPendingMap).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && !seen[questionId]) {
                seen[questionId] = true;
                order.push(questionId);
            }
        });

        return order;
    }

    function normalizeStoredAutoSaveState(snapshot) {
        var safeSnapshot = snapshot && typeof snapshot === 'object' ? snapshot : {};
        var pendingBatchMap = normalizeStoredPendingAnswerBatchMap(
            safeSnapshot.pending_answer_batch_by_question !== undefined
                ? safeSnapshot.pending_answer_batch_by_question
                : safeSnapshot.pendingAnswerBatchByQuestion
        );

        return {
            autoSaveCongestedUntil: Math.max(
                0,
                Number(
                    safeSnapshot.auto_save_congested_until !== undefined
                        ? safeSnapshot.auto_save_congested_until
                        : safeSnapshot.autoSaveCongestedUntil
                ) || 0
            ),
            lastSubmittedPayloadByQuestion: normalizeStoredStringLookup(
                safeSnapshot.last_submitted_payload_by_question !== undefined
                    ? safeSnapshot.last_submitted_payload_by_question
                    : safeSnapshot.lastSubmittedPayloadByQuestion
            ),
            pendingAnswerBatchByQuestion: pendingBatchMap,
            pendingAnswerBatchOrder: normalizeStoredPendingAnswerBatchOrder(
                safeSnapshot.pending_answer_batch_order !== undefined
                    ? safeSnapshot.pending_answer_batch_order
                    : safeSnapshot.pendingAnswerBatchOrder,
                pendingBatchMap
            ),
            lastSyncError: String(
                safeSnapshot.last_sync_error !== undefined
                    ? safeSnapshot.last_sync_error
                    : (safeSnapshot.lastSyncError || '')
            ),
            syncBlockingReason: String(
                safeSnapshot.sync_blocking_reason !== undefined
                    ? safeSnapshot.sync_blocking_reason
                    : (safeSnapshot.syncBlockingReason || '')
            ),
            examLockedForPendingFinish: Number(
                safeSnapshot.exam_locked_for_pending_finish !== undefined
                    ? safeSnapshot.exam_locked_for_pending_finish
                    : (safeSnapshot.examLockedForPendingFinish ? 1 : 0)
            ) === 1
        };
    }

    function buildChangedQuestionLookup(previousManifestById, nextManifestById, preservedLookup) {
        var changedLookup = normalizeStoredBooleanLookup(preservedLookup);
        var safePreviousManifestById = previousManifestById && typeof previousManifestById === 'object'
            ? previousManifestById
            : {};
        var safeNextManifestById = nextManifestById && typeof nextManifestById === 'object'
            ? nextManifestById
            : {};

        Object.keys(safeNextManifestById).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId <= 0) {
                return;
            }

            var previousManifest = safePreviousManifestById[questionId] || null;
            var nextManifest = safeNextManifestById[questionId] || null;
            if (!nextManifest) {
                return;
            }

            if (!previousManifest) {
                changedLookup[questionId] = true;
                return;
            }

            var previousUpdatedAt = questionManifestUpdatedAt(previousManifest);
            var nextUpdatedAt = questionManifestUpdatedAt(nextManifest);
            var contentChanged = questionManifestContentSignature(previousManifest) !== questionManifestContentSignature(nextManifest);
            if (previousUpdatedAt !== '' && nextUpdatedAt !== '') {
                if (previousUpdatedAt !== nextUpdatedAt && contentChanged) {
                    changedLookup[questionId] = true;
                }
                return;
            }

            if (contentChanged) {
                changedLookup[questionId] = true;
            }
        });

        return changedLookup;
    }

    function normalizeOrUseQuestionCacheSnapshot(snapshot, attemptId) {
        if (
            snapshot &&
            typeof snapshot === 'object' &&
            Object.prototype.hasOwnProperty.call(snapshot, 'questionPayloadById') &&
            Object.prototype.hasOwnProperty.call(snapshot, 'questionOrderIds')
        ) {
            return snapshot;
        }

        return normalizeQuestionCacheSnapshot(snapshot, attemptId);
    }

    function questionCacheSnapshotsShareRevision(primarySnapshot, secondarySnapshot, attemptId) {
        var normalizedPrimary = normalizeOrUseQuestionCacheSnapshot(primarySnapshot, attemptId);
        var normalizedSecondary = normalizeOrUseQuestionCacheSnapshot(secondarySnapshot, attemptId);
        if (!normalizedPrimary || !normalizedSecondary) {
            return true;
        }

        var primarySignature = questionRevisionSignature(
            normalizedPrimary.questionRevision,
            normalizedPrimary.examId || attemptId || 0
        );
        var secondarySignature = questionRevisionSignature(
            normalizedSecondary.questionRevision,
            normalizedSecondary.examId || attemptId || 0
        );

        if (primarySignature === '' && secondarySignature === '') {
            return true;
        }

        return primarySignature !== '' && primarySignature === secondarySignature;
    }

    function mergeQuestionCachePayloadMaps(primaryPayloadById, secondaryPayloadById) {
        var mergedPayloadById = {};

        [primaryPayloadById, secondaryPayloadById].forEach(function (payloadMap) {
            var normalizedPayloadMap = normalizeStoredQuestionPayloadById(payloadMap);
            Object.keys(normalizedPayloadMap).forEach(function (key) {
                var questionId = Number(key) || 0;
                if (questionId > 0) {
                    mergedPayloadById[questionId] = normalizedPayloadMap[questionId];
                }
            });
        });

        return mergedPayloadById;
    }

    function buildQuestionCacheSnapshotFromBaseAndPayloads(baseSnapshot, extraPayloadById, attemptId) {
        var normalizedBaseSnapshot = normalizeOrUseQuestionCacheSnapshot(baseSnapshot, attemptId);
        var mergedPayloadById = mergeQuestionCachePayloadMaps(
            normalizedBaseSnapshot ? normalizedBaseSnapshot.questionPayloadById : null,
            extraPayloadById
        );

        if (!normalizedBaseSnapshot && !Object.keys(mergedPayloadById).length) {
            return null;
        }

        return normalizeQuestionCacheSnapshot({
            attempt_id: Number(attemptId) || 0,
            exam_id: normalizedBaseSnapshot ? normalizedBaseSnapshot.examId : Number(baseSnapshot && baseSnapshot.exam_id) || 0,
            question_revision: normalizedBaseSnapshot
                ? serializeQuestionRevision(normalizedBaseSnapshot.questionRevision, normalizedBaseSnapshot.examId)
                : (baseSnapshot && baseSnapshot.question_revision),
            total_questions: normalizedBaseSnapshot ? normalizedBaseSnapshot.totalQuestions : Number(baseSnapshot && baseSnapshot.total_questions) || 0,
            question_order_ids: normalizedBaseSnapshot ? normalizedBaseSnapshot.questionOrderIds : (baseSnapshot && baseSnapshot.question_order_ids),
            question_manifest: Object.keys(mergedPayloadById).length
                ? []
                : (normalizedBaseSnapshot ? normalizedBaseSnapshot.questionManifest : (baseSnapshot && baseSnapshot.question_manifest)),
            question_payload_by_id: mergedPayloadById,
            answered_question_lookup: normalizedBaseSnapshot ? normalizedBaseSnapshot.answeredQuestionLookup : (baseSnapshot && baseSnapshot.answered_question_lookup),
            changed_question_lookup: normalizedBaseSnapshot ? normalizedBaseSnapshot.changedQuestionLookup : (baseSnapshot && baseSnapshot.changed_question_lookup),
            question_revision_marker_lookup: normalizedBaseSnapshot ? normalizedBaseSnapshot.questionRevisionMarkerLookup : (baseSnapshot && baseSnapshot.question_revision_marker_lookup),
            acknowledged_revision_question_ids: normalizedBaseSnapshot ? normalizedBaseSnapshot.acknowledgedRevisionQuestionIds : (baseSnapshot && baseSnapshot.acknowledged_revision_question_ids),
            answers: normalizedBaseSnapshot ? normalizedBaseSnapshot.answers : (baseSnapshot && baseSnapshot.answers),
            existing_answer_raw_by_question_id: normalizedBaseSnapshot ? normalizedBaseSnapshot.existingAnswerRawByQuestionId : (baseSnapshot && baseSnapshot.existing_answer_raw_by_question_id),
            loaded_question_window_offsets: normalizedBaseSnapshot ? normalizedBaseSnapshot.loadedQuestionWindowOffsets : (baseSnapshot && baseSnapshot.loaded_question_window_offsets),
            auto_save_congested_until: normalizedBaseSnapshot ? normalizedBaseSnapshot.autoSaveCongestedUntil : (baseSnapshot && baseSnapshot.auto_save_congested_until),
            last_submitted_payload_by_question: normalizedBaseSnapshot ? normalizedBaseSnapshot.lastSubmittedPayloadByQuestion : (baseSnapshot && baseSnapshot.last_submitted_payload_by_question),
            pending_answer_batch_by_question: normalizedBaseSnapshot ? normalizedBaseSnapshot.pendingAnswerBatchByQuestion : (baseSnapshot && baseSnapshot.pending_answer_batch_by_question),
            pending_answer_batch_order: normalizedBaseSnapshot ? normalizedBaseSnapshot.pendingAnswerBatchOrder : (baseSnapshot && baseSnapshot.pending_answer_batch_order),
            last_sync_error: normalizedBaseSnapshot ? normalizedBaseSnapshot.lastSyncError : (baseSnapshot && baseSnapshot.last_sync_error),
            sync_blocking_reason: normalizedBaseSnapshot ? normalizedBaseSnapshot.syncBlockingReason : (baseSnapshot && baseSnapshot.sync_blocking_reason),
            exam_locked_for_pending_finish: normalizedBaseSnapshot ? (normalizedBaseSnapshot.examLockedForPendingFinish ? 1 : 0) : (baseSnapshot && baseSnapshot.exam_locked_for_pending_finish),
            window_offset: normalizedBaseSnapshot ? normalizedBaseSnapshot.windowOffset : (baseSnapshot && baseSnapshot.window_offset),
            window_limit: normalizedBaseSnapshot ? normalizedBaseSnapshot.windowLimit : (baseSnapshot && baseSnapshot.window_limit),
            cached_at: Math.max(
                Number(normalizedBaseSnapshot && normalizedBaseSnapshot.cachedAt) || 0,
                Number(baseSnapshot && baseSnapshot.cached_at) || 0
            )
        }, attemptId);
    }

    function normalizeQuestionCacheSnapshot(snapshot, attemptId) {
        var safeAttemptId = Number(attemptId || (snapshot && snapshot.attempt_id)) || 0;
        if (safeAttemptId <= 0 || !snapshot || typeof snapshot !== 'object') {
            return null;
        }

        var questionPayloadById = normalizeStoredQuestionPayloadById(snapshot.question_payload_by_id);
        var payloadQuestions = Object.keys(questionPayloadById).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            var question = questionId > 0 ? questionPayloadById[questionId] : null;
            if (question) {
                accumulator.push(question);
            }
            return accumulator;
        }, []);

        var questionOrderIds = normalizeQuestionIdList(snapshot.question_order_ids);
        if (!questionOrderIds.length && payloadQuestions.length) {
            questionOrderIds = normalizeQuestionIdList(payloadQuestions.map(function (question) {
                return Number(question && question.id) || 0;
            }));
        }

        var questionManifest = Array.isArray(snapshot.question_manifest)
            ? snapshot.question_manifest.map(normalizeQuestionManifestItem).filter(function (item) { return !!item; })
            : [];
        if (!questionManifest.length && payloadQuestions.length) {
            questionManifest = buildQuestionManifestFromQuestions(payloadQuestions);
        }

        var answeredQuestionLookup = normalizeStoredBooleanLookup(snapshot.answered_question_lookup);
        var changedQuestionLookup = normalizeStoredBooleanLookup(snapshot.changed_question_lookup);
        var questionRevisionMarkerLookup = normalizeStoredBooleanLookup(snapshot.question_revision_marker_lookup);
        var acknowledgedRevisionQuestionIds = normalizeStoredBooleanLookup(snapshot.acknowledged_revision_question_ids);
        var answers = normalizeStoredAnswers(snapshot.answers);
        var existingAnswerRawByQuestionId = normalizeStoredExistingAnswerRawMap(snapshot.existing_answer_raw_by_question_id);
        var autoSaveState = normalizeStoredAutoSaveState(snapshot);
        if (payloadQuestions.length && (!Object.keys(answeredQuestionLookup).length || !Object.keys(answers).length)) {
            payloadQuestions.forEach(function (question) {
                var questionId = Number(question && question.id) || 0;
                var normalizedExistingAnswer = normalizeExistingAnswerForQuestion(question);
                if (questionId <= 0 || !normalizedExistingAnswer.hasValue) {
                    return;
                }

                answeredQuestionLookup[questionId] = true;
                if (!Object.prototype.hasOwnProperty.call(answers, questionId)) {
                    answers[questionId] = normalizedExistingAnswer.value;
                }
                if (Object.prototype.hasOwnProperty.call(question, 'existing_answer')) {
                    existingAnswerRawByQuestionId[questionId] = question.existing_answer;
                }
            });
        }

        Object.keys(acknowledgedRevisionQuestionIds).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && acknowledgedRevisionQuestionIds[key]) {
                delete questionRevisionMarkerLookup[questionId];
            }
        });

        if (!questionOrderIds.length && !payloadQuestions.length) {
            return null;
        }

        return {
            attemptId: safeAttemptId,
            examId: Number(snapshot.exam_id) || 0,
            questionRevision: normalizeQuestionRevision(snapshot.question_revision, Number(snapshot.exam_id) || 0),
            totalQuestions: Math.max(
                Number(snapshot.total_questions) || 0,
                questionOrderIds.length,
                payloadQuestions.length
            ),
            questionOrderIds: questionOrderIds,
            questionManifest: questionManifest,
            questionPayloadById: questionPayloadById,
            answeredQuestionLookup: answeredQuestionLookup,
            changedQuestionLookup: changedQuestionLookup,
            questionRevisionMarkerLookup: questionRevisionMarkerLookup,
            acknowledgedRevisionQuestionIds: acknowledgedRevisionQuestionIds,
            answers: answers,
            existingAnswerRawByQuestionId: existingAnswerRawByQuestionId,
            loadedQuestionWindowOffsets: normalizeStoredBooleanLookup(snapshot.loaded_question_window_offsets),
            autoSaveCongestedUntil: autoSaveState.autoSaveCongestedUntil,
            lastSubmittedPayloadByQuestion: autoSaveState.lastSubmittedPayloadByQuestion,
            pendingAnswerBatchByQuestion: autoSaveState.pendingAnswerBatchByQuestion,
            pendingAnswerBatchOrder: autoSaveState.pendingAnswerBatchOrder,
            lastSyncError: autoSaveState.lastSyncError,
            syncBlockingReason: autoSaveState.syncBlockingReason,
            examLockedForPendingFinish: autoSaveState.examLockedForPendingFinish,
            windowOffset: Math.max(0, Number(snapshot.window_offset) || 0),
            windowLimit: Math.max(0, Number(snapshot.window_limit) || 0),
            cachedAt: Math.max(0, Number(snapshot.cached_at) || 0)
        };
    }

    function buildAutoSaveStateSnapshot() {
        var autoSaveState = getAutoSaveState() || {};
        var pendingAnswerBatchByQuestion = autoSaveState.pendingAnswerBatchByQuestion && typeof autoSaveState.pendingAnswerBatchByQuestion === 'object'
            ? autoSaveState.pendingAnswerBatchByQuestion
            : {};
        var pendingAnswerBatchOrder = Array.isArray(autoSaveState.pendingAnswerBatchOrder)
            ? autoSaveState.pendingAnswerBatchOrder
            : [];
        var lastSubmittedPayloadByQuestion = autoSaveState.lastSubmittedPayloadByQuestion && typeof autoSaveState.lastSubmittedPayloadByQuestion === 'object'
            ? autoSaveState.lastSubmittedPayloadByQuestion
            : {};
        var answerBatchInFlightItems = Array.isArray(autoSaveState.answerBatchInFlightItems)
            ? autoSaveState.answerBatchInFlightItems
            : [];

        var pendingSnapshotByQuestion = Object.keys(pendingAnswerBatchByQuestion).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId <= 0 || !pendingAnswerBatchByQuestion[questionId]) {
                return accumulator;
            }

            accumulator[questionId] = {
                question_id: questionId,
                answer: Object.prototype.hasOwnProperty.call(pendingAnswerBatchByQuestion[questionId], 'answer')
                    ? pendingAnswerBatchByQuestion[questionId].answer
                    : null,
                signature: String(pendingAnswerBatchByQuestion[questionId].signature || '')
            };
            return accumulator;
        }, {});
        var pendingSnapshotOrder = pendingAnswerBatchOrder.slice();

        answerBatchInFlightItems.forEach(function (item) {
            var questionId = Number(item && item.question_id) || 0;
            if (questionId <= 0 || Object.prototype.hasOwnProperty.call(pendingSnapshotByQuestion, questionId)) {
                return;
            }

            pendingSnapshotByQuestion[questionId] = {
                question_id: questionId,
                answer: Object.prototype.hasOwnProperty.call(item, 'answer') ? item.answer : null,
                signature: String(item.signature || '')
            };
            pendingSnapshotOrder.push(questionId);
        });

        return {
            auto_save_congested_until: Math.max(0, Number(autoSaveState.autoSaveCongestedUntil) || 0),
            last_submitted_payload_by_question: Object.assign({}, lastSubmittedPayloadByQuestion),
            pending_answer_batch_by_question: pendingSnapshotByQuestion,
            pending_answer_batch_order: pendingSnapshotOrder,
            last_sync_error: String(autoSaveState.lastSyncError || ''),
            sync_blocking_reason: String(autoSaveState.syncBlockingReason || ''),
            exam_locked_for_pending_finish: autoSaveState.examLockedForPendingFinish ? 1 : 0
        };
    }

    function buildQuestionCacheSnapshot(attemptId) {
        var safeAttemptId = Number(attemptId || state.attemptId) || 0;
        if (safeAttemptId <= 0) {
            return null;
        }

        var questionPayloadById = Object.keys(state.questionPayloadById || {}).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            var question = questionId > 0 ? getQuestionPayloadById(questionId) : null;
            if (question) {
                accumulator[questionId] = question;
            }
            return accumulator;
        }, {});

        var questionPayloadCount = Object.keys(questionPayloadById).length;
        if (questionPayloadCount <= 0) {
            return null;
        }

        var questionOrderIds = Array.isArray(state.questionOrderIds) ? state.questionOrderIds.slice() : [];
        var manifestItems = Array.isArray(state.questionManifest) && state.questionManifest.length
            ? state.questionManifest.slice()
            : buildQuestionManifestFromQuestions(Object.keys(questionPayloadById).map(function (key) {
                return questionPayloadById[key];
            }));
        var autoSaveState = buildAutoSaveStateSnapshot();

        return {
            attempt_id: safeAttemptId,
            exam_id: Number(state.selectedExamId) || 0,
            question_revision: serializeQuestionRevision(state.questionRevision, Number(state.selectedExamId) || 0),
            total_questions: Math.max(Number(state.totalQuestions) || 0, questionOrderIds.length, questionPayloadCount),
            question_order_ids: questionOrderIds,
            question_manifest: manifestItems,
            question_payload_by_id: questionPayloadById,
            answered_question_lookup: Object.keys(state.answeredQuestionLookup || {}).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.answeredQuestionLookup[key]) {
                    accumulator[questionId] = true;
                }
                return accumulator;
            }, {}),
            changed_question_lookup: Object.keys(state.changedQuestionLookup || {}).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.changedQuestionLookup[key]) {
                    accumulator[questionId] = true;
                }
                return accumulator;
            }, {}),
            question_revision_marker_lookup: Object.keys(state.questionRevisionMarkerLookup || {}).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.questionRevisionMarkerLookup[key]) {
                    accumulator[questionId] = true;
                }
                return accumulator;
            }, {}),
            acknowledged_revision_question_ids: Object.keys(state.acknowledgedRevisionQuestionIds || {}).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.acknowledgedRevisionQuestionIds[key]) {
                    accumulator[questionId] = true;
                }
                return accumulator;
            }, {}),
            answers: Object.keys(state.answers || {}).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.answers[key] !== undefined) {
                    accumulator[questionId] = state.answers[key];
                }
                return accumulator;
            }, {}),
            existing_answer_raw_by_question_id: Object.keys(state.existingAnswerRawByQuestionId || {}).reduce(function (accumulator, key) {
                var questionId = Number(key) || 0;
                if (questionId > 0 && state.existingAnswerRawByQuestionId[key] !== undefined) {
                    accumulator[questionId] = state.existingAnswerRawByQuestionId[key];
                }
                return accumulator;
            }, {}),
            loaded_question_window_offsets: Object.keys(state.loadedQuestionWindowOffsets || {}).reduce(function (accumulator, key) {
                var offset = Number(key);
                if (Number.isFinite(offset) && offset >= 0 && state.loadedQuestionWindowOffsets[key]) {
                    accumulator[offset] = true;
                }
                return accumulator;
            }, {}),
            auto_save_congested_until: autoSaveState.auto_save_congested_until,
            last_submitted_payload_by_question: autoSaveState.last_submitted_payload_by_question,
            pending_answer_batch_by_question: autoSaveState.pending_answer_batch_by_question,
            pending_answer_batch_order: autoSaveState.pending_answer_batch_order,
            last_sync_error: autoSaveState.last_sync_error,
            sync_blocking_reason: autoSaveState.sync_blocking_reason,
            exam_locked_for_pending_finish: autoSaveState.exam_locked_for_pending_finish,
            window_offset: Math.max(0, Number(state.windowOffset) || 0),
            window_limit: Math.max(0, Number(state.windowLimit) || 0),
            cached_at: now()
        };
    }

    function serializeQuestionCacheSnapshot(normalizedSnapshot) {
        if (!normalizedSnapshot) {
            return null;
        }

        return {
            attempt_id: normalizedSnapshot.attemptId,
            exam_id: normalizedSnapshot.examId,
            question_revision: serializeQuestionRevision(normalizedSnapshot.questionRevision, normalizedSnapshot.examId),
            total_questions: normalizedSnapshot.totalQuestions,
            question_order_ids: normalizedSnapshot.questionOrderIds,
            question_manifest: normalizedSnapshot.questionManifest,
            question_payload_by_id: normalizedSnapshot.questionPayloadById,
            answered_question_lookup: normalizedSnapshot.answeredQuestionLookup,
            changed_question_lookup: normalizedSnapshot.changedQuestionLookup,
            question_revision_marker_lookup: normalizedSnapshot.questionRevisionMarkerLookup,
            acknowledged_revision_question_ids: normalizedSnapshot.acknowledgedRevisionQuestionIds,
            answers: normalizedSnapshot.answers,
            existing_answer_raw_by_question_id: normalizedSnapshot.existingAnswerRawByQuestionId,
            loaded_question_window_offsets: normalizedSnapshot.loadedQuestionWindowOffsets,
            auto_save_congested_until: normalizedSnapshot.autoSaveCongestedUntil,
            last_submitted_payload_by_question: normalizedSnapshot.lastSubmittedPayloadByQuestion,
            pending_answer_batch_by_question: normalizedSnapshot.pendingAnswerBatchByQuestion,
            pending_answer_batch_order: normalizedSnapshot.pendingAnswerBatchOrder,
            last_sync_error: normalizedSnapshot.lastSyncError,
            sync_blocking_reason: normalizedSnapshot.syncBlockingReason,
            exam_locked_for_pending_finish: normalizedSnapshot.examLockedForPendingFinish ? 1 : 0,
            window_offset: normalizedSnapshot.windowOffset,
            window_limit: normalizedSnapshot.windowLimit,
            cached_at: now()
        };
    }

    function buildQuestionCacheStoredQuestionIds(questionPayloadById) {
        if (!questionPayloadById || typeof questionPayloadById !== 'object') {
            return [];
        }

        return Object.keys(questionPayloadById).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && questionPayloadById[questionId]) {
                accumulator.push(questionId);
            }
            return accumulator;
        }, []);
    }

    function serializeQuestionCacheMetaSnapshot(normalizedSnapshot, storedQuestionIds) {
        if (!normalizedSnapshot) {
            return null;
        }

        return {
            attempt_id: normalizedSnapshot.attemptId,
            exam_id: normalizedSnapshot.examId,
            question_revision: serializeQuestionRevision(normalizedSnapshot.questionRevision, normalizedSnapshot.examId),
            total_questions: normalizedSnapshot.totalQuestions,
            question_order_ids: normalizedSnapshot.questionOrderIds,
            answered_question_lookup: normalizedSnapshot.answeredQuestionLookup,
            changed_question_lookup: normalizedSnapshot.changedQuestionLookup,
            question_revision_marker_lookup: normalizedSnapshot.questionRevisionMarkerLookup,
            acknowledged_revision_question_ids: normalizedSnapshot.acknowledgedRevisionQuestionIds,
            answers: normalizedSnapshot.answers,
            existing_answer_raw_by_question_id: normalizedSnapshot.existingAnswerRawByQuestionId,
            loaded_question_window_offsets: normalizedSnapshot.loadedQuestionWindowOffsets,
            auto_save_congested_until: normalizedSnapshot.autoSaveCongestedUntil,
            last_submitted_payload_by_question: normalizedSnapshot.lastSubmittedPayloadByQuestion,
            pending_answer_batch_by_question: normalizedSnapshot.pendingAnswerBatchByQuestion,
            pending_answer_batch_order: normalizedSnapshot.pendingAnswerBatchOrder,
            last_sync_error: normalizedSnapshot.lastSyncError,
            sync_blocking_reason: normalizedSnapshot.syncBlockingReason,
            exam_locked_for_pending_finish: normalizedSnapshot.examLockedForPendingFinish ? 1 : 0,
            window_offset: normalizedSnapshot.windowOffset,
            window_limit: normalizedSnapshot.windowLimit,
            stored_question_ids: normalizeQuestionIdList(storedQuestionIds),
            cached_at: now()
        };
    }

    function normalizeQuestionCacheSnapshotFromMeta(metaSnapshot, questionPayloadById, attemptId) {
        return normalizeQuestionCacheSnapshot({
            attempt_id: attemptId,
            exam_id: metaSnapshot && metaSnapshot.exam_id,
            question_revision: metaSnapshot && metaSnapshot.question_revision,
            total_questions: metaSnapshot && metaSnapshot.total_questions,
            question_order_ids: metaSnapshot && metaSnapshot.question_order_ids,
            question_manifest: metaSnapshot && metaSnapshot.question_manifest,
            question_payload_by_id: questionPayloadById || {},
            answered_question_lookup: metaSnapshot && metaSnapshot.answered_question_lookup,
            changed_question_lookup: metaSnapshot && metaSnapshot.changed_question_lookup,
            question_revision_marker_lookup: metaSnapshot && metaSnapshot.question_revision_marker_lookup,
            acknowledged_revision_question_ids: metaSnapshot && metaSnapshot.acknowledged_revision_question_ids,
            answers: metaSnapshot && metaSnapshot.answers,
            existing_answer_raw_by_question_id: metaSnapshot && metaSnapshot.existing_answer_raw_by_question_id,
            loaded_question_window_offsets: metaSnapshot && metaSnapshot.loaded_question_window_offsets,
            auto_save_congested_until: metaSnapshot && metaSnapshot.auto_save_congested_until,
            last_submitted_payload_by_question: metaSnapshot && metaSnapshot.last_submitted_payload_by_question,
            pending_answer_batch_by_question: metaSnapshot && metaSnapshot.pending_answer_batch_by_question,
            pending_answer_batch_order: metaSnapshot && metaSnapshot.pending_answer_batch_order,
            last_sync_error: metaSnapshot && metaSnapshot.last_sync_error,
            sync_blocking_reason: metaSnapshot && metaSnapshot.sync_blocking_reason,
            exam_locked_for_pending_finish: metaSnapshot && metaSnapshot.exam_locked_for_pending_finish,
            window_offset: metaSnapshot && metaSnapshot.window_offset,
            window_limit: metaSnapshot && metaSnapshot.window_limit,
            cached_at: metaSnapshot && metaSnapshot.cached_at
        }, attemptId);
    }

    function questionCachePayloadCount(snapshot) {
        if (!snapshot || !snapshot.questionPayloadById || typeof snapshot.questionPayloadById !== 'object') {
            return 0;
        }

        return Object.keys(snapshot.questionPayloadById).reduce(function (count, key) {
            var questionId = Number(key) || 0;
            return count + (questionId > 0 ? 1 : 0);
        }, 0);
    }

    function choosePreferredQuestionCacheSnapshot(primarySnapshot, secondarySnapshot) {
        if (!primarySnapshot) {
            return secondarySnapshot || null;
        }
        if (!secondarySnapshot) {
            return primarySnapshot;
        }

        var revisionComparison = compareQuestionRevisionFreshness(
            primarySnapshot.questionRevision,
            secondarySnapshot.questionRevision,
            primarySnapshot.examId || secondarySnapshot.examId || 0
        );
        if (revisionComparison !== 0) {
            return revisionComparison > 0 ? primarySnapshot : secondarySnapshot;
        }

        var primaryCount = questionCachePayloadCount(primarySnapshot);
        var secondaryCount = questionCachePayloadCount(secondarySnapshot);
        if (primaryCount !== secondaryCount) {
            return primaryCount > secondaryCount ? primarySnapshot : secondarySnapshot;
        }

        return (Number(primarySnapshot.cachedAt) || 0) >= (Number(secondarySnapshot.cachedAt) || 0)
            ? primarySnapshot
            : secondarySnapshot;
    }

    function mergeStoredBooleanLookups(primaryLookup, secondaryLookup) {
        return normalizeStoredBooleanLookup(Object.assign(
            {},
            primaryLookup && typeof primaryLookup === 'object' ? primaryLookup : {},
            secondaryLookup && typeof secondaryLookup === 'object' ? secondaryLookup : {}
        ));
    }

    function mergeStoredAnswers(primaryAnswers, secondaryAnswers) {
        return normalizeStoredAnswers(Object.assign(
            {},
            primaryAnswers && typeof primaryAnswers === 'object' ? primaryAnswers : {},
            secondaryAnswers && typeof secondaryAnswers === 'object' ? secondaryAnswers : {}
        ));
    }

    function mergeStoredExistingAnswerRawMaps(primaryMap, secondaryMap) {
        return normalizeStoredExistingAnswerRawMap(Object.assign(
            {},
            primaryMap && typeof primaryMap === 'object' ? primaryMap : {},
            secondaryMap && typeof secondaryMap === 'object' ? secondaryMap : {}
        ));
    }

    function choosePreferredQuestionOrderSnapshot(preferredBaseSnapshot, primarySnapshot, secondarySnapshot) {
        if (preferredBaseSnapshot && Array.isArray(preferredBaseSnapshot.questionOrderIds) && preferredBaseSnapshot.questionOrderIds.length) {
            return preferredBaseSnapshot;
        }

        if (primarySnapshot.questionOrderIds.length !== secondarySnapshot.questionOrderIds.length) {
            return primarySnapshot.questionOrderIds.length > secondarySnapshot.questionOrderIds.length
                ? primarySnapshot
                : secondarySnapshot;
        }

        return (Number(primarySnapshot.cachedAt) || 0) >= (Number(secondarySnapshot.cachedAt) || 0)
            ? primarySnapshot
            : secondarySnapshot;
    }

    function mergeQuestionCacheSnapshots(primarySnapshot, secondarySnapshot, attemptId) {
        var normalizedPrimary = normalizeOrUseQuestionCacheSnapshot(primarySnapshot, attemptId);
        var normalizedSecondary = normalizeOrUseQuestionCacheSnapshot(secondarySnapshot, attemptId);
        if (!normalizedPrimary) {
            return normalizedSecondary;
        }
        if (!normalizedSecondary) {
            return normalizedPrimary;
        }
        if (!questionCacheSnapshotsShareRevision(normalizedPrimary, normalizedSecondary, attemptId)) {
            return choosePreferredQuestionCacheSnapshot(normalizedPrimary, normalizedSecondary);
        }

        var preferredBaseSnapshot = choosePreferredQuestionCacheSnapshot(normalizedPrimary, normalizedSecondary);
        var preferredOrderSnapshot = choosePreferredQuestionOrderSnapshot(
            preferredBaseSnapshot,
            normalizedPrimary,
            normalizedSecondary
        );
        var mergedPayloadById = mergeQuestionCachePayloadMaps(
            normalizedPrimary.questionPayloadById,
            normalizedSecondary.questionPayloadById
        );

        return buildQuestionCacheSnapshotFromBaseAndPayloads({
            attempt_id: Number(attemptId) || normalizedPrimary.attemptId || normalizedSecondary.attemptId || 0,
            exam_id: preferredBaseSnapshot.examId || preferredOrderSnapshot.examId || 0,
            question_revision: serializeQuestionRevision(
                preferredBaseSnapshot.questionRevision || preferredOrderSnapshot.questionRevision,
                preferredBaseSnapshot.examId || preferredOrderSnapshot.examId || 0
            ),
            total_questions: Math.max(
                Number(normalizedPrimary.totalQuestions) || 0,
                Number(normalizedSecondary.totalQuestions) || 0,
                Object.keys(mergedPayloadById).length
            ),
            question_order_ids: preferredOrderSnapshot.questionOrderIds,
            question_manifest: preferredOrderSnapshot.questionManifest,
            answered_question_lookup: mergeStoredBooleanLookups(
                normalizedPrimary.answeredQuestionLookup,
                normalizedSecondary.answeredQuestionLookup
            ),
            changed_question_lookup: mergeStoredBooleanLookups(
                normalizedPrimary.changedQuestionLookup,
                normalizedSecondary.changedQuestionLookup
            ),
            question_revision_marker_lookup: mergeStoredBooleanLookups(
                normalizedPrimary.questionRevisionMarkerLookup,
                normalizedSecondary.questionRevisionMarkerLookup
            ),
            acknowledged_revision_question_ids: mergeStoredBooleanLookups(
                normalizedPrimary.acknowledgedRevisionQuestionIds,
                normalizedSecondary.acknowledgedRevisionQuestionIds
            ),
            answers: mergeStoredAnswers(
                normalizedPrimary.answers,
                normalizedSecondary.answers
            ),
            existing_answer_raw_by_question_id: mergeStoredExistingAnswerRawMaps(
                normalizedPrimary.existingAnswerRawByQuestionId,
                normalizedSecondary.existingAnswerRawByQuestionId
            ),
            loaded_question_window_offsets: mergeStoredBooleanLookups(
                normalizedPrimary.loadedQuestionWindowOffsets,
                normalizedSecondary.loadedQuestionWindowOffsets
            ),
            window_offset: Number(preferredBaseSnapshot.windowOffset) || 0,
            window_limit: Number(preferredBaseSnapshot.windowLimit) || 0,
            cached_at: Math.max(
                Number(normalizedPrimary.cachedAt) || 0,
                Number(normalizedSecondary.cachedAt) || 0
            )
        }, mergedPayloadById, attemptId);
    }

    function persistQuestionCacheToSessionStorage(storageKey, normalizedSnapshot, storedSnapshot) {
        var storage = getSessionStorage();
        if (!storage || storageKey === '' || !normalizedSnapshot) {
            return;
        }

        var metaKey = buildQuestionCacheSessionStorageMetaKey(normalizedSnapshot.attemptId);
        var storedQuestionIds = [];
        buildQuestionCacheStoredQuestionIds(normalizedSnapshot.questionPayloadById).forEach(function (questionId) {
            var itemKey = buildQuestionCacheSessionStorageItemKey(normalizedSnapshot.attemptId, questionId);
            var questionPayload = normalizedSnapshot.questionPayloadById[questionId];
            if (itemKey === '' || !questionPayload) {
                return;
            }

            try {
                storage.setItem(itemKey, JSON.stringify(questionPayload));
                storedQuestionIds.push(questionId);
            } catch (error) {
                // Stop growing the per-question session cache when quota is reached.
            }
        });

        if (metaKey !== '') {
            try {
                storage.setItem(metaKey, JSON.stringify(serializeQuestionCacheMetaSnapshot(normalizedSnapshot, storedQuestionIds)));
            } catch (error) {
                // Ignore storage failures (quota or disabled storage).
            }
        }

        if (!storedSnapshot) {
            return;
        }

        try {
            storage.setItem(storageKey, JSON.stringify(storedSnapshot));
        } catch (error) {
            // Ignore storage failures (quota or disabled storage).
        }
    }

    function persistQuestionCacheToLocalStorage(normalizedSnapshot) {
        var storage = getLocalStorage();
        if (!storage || !normalizedSnapshot) {
            return false;
        }

        var metaKey = buildQuestionCacheMetaLocalStorageKey(normalizedSnapshot.attemptId);
        if (metaKey === '') {
            return false;
        }

        var storedQuestionIds = [];
        buildQuestionCacheStoredQuestionIds(normalizedSnapshot.questionPayloadById).forEach(function (questionId) {
            var questionPayload = normalizedSnapshot.questionPayloadById[questionId];
            if (!questionPayload) {
                return;
            }

            var itemKey = buildQuestionCacheItemLocalStorageKey(normalizedSnapshot.attemptId, questionId);
            if (itemKey === '') {
                return;
            }

            try {
                storage.setItem(itemKey, JSON.stringify(questionPayload));
                storedQuestionIds.push(questionId);
            } catch (error) {
                // Stop growing the cache when browser quota is reached.
            }
        });

        try {
            storage.setItem(metaKey, JSON.stringify(serializeQuestionCacheMetaSnapshot(normalizedSnapshot, storedQuestionIds)));
            return true;
        } catch (error) {
            return false;
        }
    }

    function persistQuestionCacheToIndexedDb(storageKey, normalizedSnapshot) {
        if (storageKey === '' || !normalizedSnapshot) {
            return Promise.resolve(false);
        }

        var metaKey = buildQuestionCacheIndexedDbMetaKey(normalizedSnapshot.attemptId);
        if (metaKey === '') {
            return Promise.resolve(false);
        }

        var storedQuestionIds = buildQuestionCacheStoredQuestionIds(normalizedSnapshot.questionPayloadById);
        var metaSnapshot = serializeQuestionCacheMetaSnapshot(normalizedSnapshot, storedQuestionIds);
        if (!metaSnapshot) {
            return Promise.resolve(false);
        }

        return openQuestionCacheIndexedDb().then(function (database) {
            if (!database) {
                return false;
            }

            return new Promise(function (resolve) {
                try {
                    var transaction = database.transaction(indexedDbStore, 'readwrite');
                    var store = transaction.objectStore(indexedDbStore);
                    store.put({
                        cache_key: metaKey,
                        snapshot: metaSnapshot,
                        updated_at: now()
                    });
                    storedQuestionIds.forEach(function (questionId) {
                        var itemKey = buildQuestionCacheIndexedDbItemKey(normalizedSnapshot.attemptId, questionId);
                        var questionPayload = normalizedSnapshot.questionPayloadById[questionId];
                        if (itemKey === '' || !questionPayload) {
                            return;
                        }

                        store.put({
                            cache_key: itemKey,
                            payload: questionPayload,
                            updated_at: now()
                        });
                    });
                    store.delete(storageKey);
                    transaction.oncomplete = function () {
                        resolve(true);
                    };
                    transaction.onerror = function () {
                        resolve(false);
                    };
                    transaction.onabort = function () {
                        resolve(false);
                    };
                } catch (error) {
                    resolve(false);
                }
            });
        }).catch(function () {
            return false;
        });
    }

    function persistQuestionCacheLocally(snapshot) {
        var normalizedSnapshot = normalizeQuestionCacheSnapshot(snapshot, snapshot && snapshot.attempt_id);
        if (!normalizedSnapshot) {
            return;
        }

        var storageKey = buildQuestionCacheSessionStorageKey(normalizedSnapshot.attemptId);
        if (storageKey === '') {
            return;
        }

        var storedSnapshot = serializeQuestionCacheSnapshot(normalizedSnapshot);
        if (storedSnapshot) {
            persistQuestionCacheToSessionStorage(storageKey, normalizedSnapshot, storedSnapshot);
        }
        persistQuestionCacheToLocalStorage(normalizedSnapshot);
        persistQuestionCacheToIndexedDb(storageKey, normalizedSnapshot);
    }

    function persistCurrentQuestionCacheLocally() {
        var snapshot = buildQuestionCacheSnapshot();
        if (!snapshot) {
            return;
        }

        persistQuestionCacheLocally(snapshot);
    }

    function readPersistedQuestionCacheFromSessionStorage(attemptId) {
        var storage = getSessionStorage();
        if (!storage) {
            return null;
        }

        var safeAttemptId = Number(attemptId) || 0;
        var storageKey = buildQuestionCacheSessionStorageKey(safeAttemptId);
        var metaKey = buildQuestionCacheSessionStorageMetaKey(safeAttemptId);
        if (storageKey === '') {
            return null;
        }

        if (metaKey !== '') {
            try {
                var rawMeta = storage.getItem(metaKey);
                var discoveredQuestionIds = collectStorageQuestionCacheIds(storage, buildQuestionCacheItemKeyPrefix(storageKey));
                var questionPayloadById = {};
                discoveredQuestionIds.forEach(function (questionId) {
                    var itemKey = buildQuestionCacheSessionStorageItemKey(safeAttemptId, questionId);
                    if (itemKey === '') {
                        return;
                    }

                    try {
                        var rawItem = storage.getItem(itemKey);
                        if (!rawItem) {
                            return;
                        }

                        var parsedQuestion = JSON.parse(rawItem);
                        if (parsedQuestion && typeof parsedQuestion === 'object') {
                            questionPayloadById[questionId] = parsedQuestion;
                        }
                    } catch (error) {
                        // Ignore broken session question cache items.
                    }
                });

                var parsedMeta = rawMeta ? JSON.parse(rawMeta) : null;
                var rawLegacy = storage.getItem(storageKey);
                var parsedLegacy = rawLegacy ? JSON.parse(rawLegacy) : null;
                var mergedBaseSnapshot = mergeQuestionCacheSnapshots(parsedMeta, parsedLegacy, safeAttemptId);

                var mergedSnapshot = buildQuestionCacheSnapshotFromBaseAndPayloads(
                    mergedBaseSnapshot || parsedMeta || parsedLegacy,
                    questionPayloadById,
                    safeAttemptId
                );
                if (mergedSnapshot) {
                    return mergedSnapshot;
                }
            } catch (error) {
                // Fall through to legacy monolithic snapshot.
            }
        }

        try {
            var raw = storage.getItem(storageKey);
            if (!raw) {
                return null;
            }

            return normalizeQuestionCacheSnapshot(JSON.parse(raw), safeAttemptId);
        } catch (error) {
            return null;
        }
    }

    function readPersistedQuestionCacheFromLocalStorage(attemptId) {
        var storage = getLocalStorage();
        if (!storage) {
            return null;
        }

        var safeAttemptId = Number(attemptId) || 0;
        var metaKey = buildQuestionCacheMetaLocalStorageKey(safeAttemptId);
        if (metaKey === '') {
            return null;
        }

        try {
            var rawMeta = storage.getItem(metaKey);
            var baseSnapshot = null;
            if (rawMeta) {
                baseSnapshot = JSON.parse(rawMeta);
            }

            var storedQuestionIds = collectStorageQuestionCacheIds(storage, buildQuestionCacheLocalStorageItemKeyPrefix(safeAttemptId));
            if (!storedQuestionIds.length && !baseSnapshot) {
                return null;
            }
            var questionPayloadById = {};

            storedQuestionIds.forEach(function (questionId) {
                var itemKey = buildQuestionCacheItemLocalStorageKey(safeAttemptId, questionId);
                if (itemKey === '') {
                    return;
                }

                try {
                    var rawItem = storage.getItem(itemKey);
                    if (!rawItem) {
                        return;
                    }

                    var parsedQuestion = JSON.parse(rawItem);
                    if (parsedQuestion && typeof parsedQuestion === 'object') {
                        questionPayloadById[questionId] = parsedQuestion;
                    }
                } catch (error) {
                    // Ignore broken question payload cache items.
                }
            });

            return buildQuestionCacheSnapshotFromBaseAndPayloads(
                baseSnapshot || readPersistedQuestionCacheFromSessionStorage(safeAttemptId),
                questionPayloadById,
                safeAttemptId
            );
        } catch (error) {
            return null;
        }
    }

    function readPersistedQuestionCacheFromIndexedDb(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        var storageKey = buildQuestionCacheSessionStorageKey(safeAttemptId);
        var metaKey = buildQuestionCacheIndexedDbMetaKey(safeAttemptId);
        if (storageKey === '' || metaKey === '') {
            return Promise.resolve(null);
        }

        return openQuestionCacheIndexedDb().then(function (database) {
            if (!database) {
                return null;
            }

            return new Promise(function (resolve) {
                try {
                    var transaction = database.transaction(indexedDbStore, 'readonly');
                    var store = transaction.objectStore(indexedDbStore);
                    var resolved = false;
                    var itemKeyPrefix = buildQuestionCacheItemKeyPrefix(storageKey);
                    function resolveOnce(snapshot) {
                        if (resolved) {
                            return;
                        }
                        resolved = true;
                        resolve(snapshot);
                    }

                    var metaSnapshot = null;
                    var metaResolved = false;
                    var cursorResolved = false;
                    var questionPayloadById = {};
                    var discoveredQuestionIds = [];

                    function finalizeIndexedDbSnapshot() {
                        if (!metaResolved || !cursorResolved) {
                            return;
                        }

                        var mergedMetaSnapshot = metaSnapshot ? Object.keys(metaSnapshot).reduce(function (accumulator, key) {
                            accumulator[key] = metaSnapshot[key];
                            return accumulator;
                        }, {}) : null;
                        if (mergedMetaSnapshot) {
                            mergedMetaSnapshot.stored_question_ids = mergeQuestionCacheStoredIds(
                                metaSnapshot && metaSnapshot.stored_question_ids,
                                discoveredQuestionIds
                            );
                        }
                        try {
                            var legacyRequest = store.get(storageKey);
                            legacyRequest.onsuccess = function () {
                                var record = legacyRequest.result;
                                var legacySnapshot = record && record.snapshot ? record.snapshot : null;
                                var mergedBaseSnapshot = mergeQuestionCacheSnapshots(
                                    mergedMetaSnapshot,
                                    legacySnapshot,
                                    safeAttemptId
                                );
                                var mergedSnapshot = buildQuestionCacheSnapshotFromBaseAndPayloads(
                                    mergedBaseSnapshot || mergedMetaSnapshot || legacySnapshot,
                                    questionPayloadById,
                                    safeAttemptId
                                );
                                resolveOnce(mergedSnapshot);
                            };
                            legacyRequest.onerror = function () {
                                var mergedSnapshot = buildQuestionCacheSnapshotFromBaseAndPayloads(
                                    mergedMetaSnapshot,
                                    questionPayloadById,
                                    safeAttemptId
                                );
                                resolveOnce(mergedSnapshot);
                            };
                        } catch (error) {
                            var mergedSnapshot = buildQuestionCacheSnapshotFromBaseAndPayloads(
                                mergedMetaSnapshot,
                                questionPayloadById,
                                safeAttemptId
                            );
                            resolveOnce(mergedSnapshot);
                        }
                    }

                    var metaRequest = store.get(metaKey);
                    metaRequest.onsuccess = function () {
                        var metaRecord = metaRequest.result;
                        metaSnapshot = metaRecord && metaRecord.snapshot ? metaRecord.snapshot : null;
                        metaResolved = true;
                        finalizeIndexedDbSnapshot();
                    };

                    metaRequest.onerror = function () {
                        metaResolved = true;
                        metaSnapshot = null;
                        finalizeIndexedDbSnapshot();
                    };

                    var cursorRequest = store.openCursor();
                    cursorRequest.onsuccess = function (event) {
                        var cursor = event && event.target ? event.target.result : null;
                        if (!cursor) {
                            cursorResolved = true;
                            finalizeIndexedDbSnapshot();
                            return;
                        }

                        var cacheKey = String(cursor.key || '');
                        var questionId = parseQuestionIdFromCacheItemKey(cacheKey, itemKeyPrefix);
                        var itemRecord = cursor.value;
                        if (questionId > 0 && itemRecord && itemRecord.payload && typeof itemRecord.payload === 'object') {
                            discoveredQuestionIds.push(questionId);
                            questionPayloadById[questionId] = itemRecord.payload;
                        }

                        cursor.continue();
                    };

                    cursorRequest.onerror = function () {
                        cursorResolved = true;
                        finalizeIndexedDbSnapshot();
                    };

                    transaction.onerror = function () {
                        resolveOnce(null);
                    };
                } catch (error) {
                    resolve(null);
                }
            });
        }).catch(function () {
            return null;
        });
    }

    async function readPersistedQuestionCache(attemptId) {
        var indexedDbSnapshot = await readPersistedQuestionCacheFromIndexedDb(attemptId);
        var localStorageSnapshot = readPersistedQuestionCacheFromLocalStorage(attemptId);
        var sessionSnapshot = readPersistedQuestionCacheFromSessionStorage(attemptId);
        var mergedSnapshot = mergeQuestionCacheSnapshots(
            mergeQuestionCacheSnapshots(indexedDbSnapshot, localStorageSnapshot, attemptId),
            sessionSnapshot,
            attemptId
        );
        setQuestionCacheRestoreDebug({
            attemptId: Number(attemptId) || 0,
            indexedDbPayloadCount: questionCachePayloadCount(indexedDbSnapshot),
            indexedDbRevision: questionRevisionSignature(indexedDbSnapshot && indexedDbSnapshot.questionRevision, indexedDbSnapshot && indexedDbSnapshot.examId),
            localStoragePayloadCount: questionCachePayloadCount(localStorageSnapshot),
            localStorageRevision: questionRevisionSignature(localStorageSnapshot && localStorageSnapshot.questionRevision, localStorageSnapshot && localStorageSnapshot.examId),
            sessionPayloadCount: questionCachePayloadCount(sessionSnapshot),
            sessionRevision: questionRevisionSignature(sessionSnapshot && sessionSnapshot.questionRevision, sessionSnapshot && sessionSnapshot.examId),
            mergedPayloadCount: questionCachePayloadCount(mergedSnapshot),
            mergedOrderCount: mergedSnapshot && Array.isArray(mergedSnapshot.questionOrderIds)
                ? mergedSnapshot.questionOrderIds.length
                : 0,
            mergedWindowOffset: mergedSnapshot ? Number(mergedSnapshot.windowOffset) || 0 : 0,
            mergedWindowLimit: mergedSnapshot ? Number(mergedSnapshot.windowLimit) || 0 : 0,
            mergedCachedAt: mergedSnapshot ? Number(mergedSnapshot.cachedAt) || 0 : 0,
            mergedRevision: questionRevisionSignature(mergedSnapshot && mergedSnapshot.questionRevision, mergedSnapshot && mergedSnapshot.examId),
            timestamp: now()
        });
        return mergedSnapshot;
    }

    function clearPersistedQuestionCache(attemptId) {
        var storageKey = buildQuestionCacheSessionStorageKey(attemptId);
        if (storageKey === '') {
            return;
        }

        var storage = getSessionStorage();
        try {
            if (storage) {
                var metaKey = buildQuestionCacheSessionStorageMetaKey(attemptId);
                var itemKeyPrefix = buildQuestionCacheItemKeyPrefix(storageKey);
                if (metaKey !== '') {
                    try {
                        collectStorageQuestionCacheIds(storage, itemKeyPrefix).forEach(function (questionId) {
                            var itemKey = buildQuestionCacheSessionStorageItemKey(attemptId, questionId);
                            if (itemKey !== '') {
                                storage.removeItem(itemKey);
                            }
                        });
                    } catch (error) {
                        // Ignore sessionStorage cache cleanup failures.
                    }

                    try {
                        var storageLength = Number(storage.length) || 0;
                        for (var index = storageLength - 1; index >= 0; index--) {
                            var extraStorageKey = typeof storage.key === 'function' ? storage.key(index) : '';
                            if (parseQuestionIdFromCacheItemKey(extraStorageKey, itemKeyPrefix) > 0) {
                                storage.removeItem(extraStorageKey);
                            }
                        }
                    } catch (error) {
                        // Ignore sessionStorage cache cleanup failures.
                    }

                    try {
                        storage.removeItem(metaKey);
                    } catch (error) {
                        // Ignore sessionStorage cache cleanup failures.
                    }
                }

                storage.removeItem(storageKey);
            }
        } catch (error) {
            // Ignore storage failures (private mode / blocked storage).
        }

        var localStorage = getLocalStorage();
        var metaKey = buildQuestionCacheMetaLocalStorageKey(attemptId);
        if (localStorage && metaKey !== '') {
            var localItemKeyPrefix = buildQuestionCacheLocalStorageItemKeyPrefix(attemptId);
            try {
                collectStorageQuestionCacheIds(localStorage, localItemKeyPrefix).forEach(function (questionId) {
                    var itemKey = buildQuestionCacheItemLocalStorageKey(attemptId, questionId);
                    if (itemKey !== '') {
                        localStorage.removeItem(itemKey);
                    }
                });
            } catch (error) {
                // Ignore localStorage cache cleanup failures.
            }

            try {
                var localStorageLength = Number(localStorage.length) || 0;
                for (var localIndex = localStorageLength - 1; localIndex >= 0; localIndex--) {
                    var extraLocalKey = typeof localStorage.key === 'function' ? localStorage.key(localIndex) : '';
                    if (parseQuestionIdFromCacheItemKey(extraLocalKey, localItemKeyPrefix) > 0) {
                        localStorage.removeItem(extraLocalKey);
                    }
                }
            } catch (error) {
                // Ignore localStorage cache cleanup failures.
            }

            try {
                localStorage.removeItem(metaKey);
            } catch (error) {
                // Ignore localStorage cache cleanup failures.
            }
        }

        openQuestionCacheIndexedDb().then(function (database) {
            if (!database) {
                return;
            }

            try {
                var transaction = database.transaction(indexedDbStore, 'readwrite');
                var store = transaction.objectStore(indexedDbStore);
                var indexedDbMetaKey = buildQuestionCacheIndexedDbMetaKey(attemptId);
                var itemKeyPrefix = buildQuestionCacheItemKeyPrefix(storageKey);

                store.delete(storageKey);
                if (indexedDbMetaKey !== '') {
                    var cursorRequest = store.openCursor();
                    cursorRequest.onsuccess = function (event) {
                        var cursor = event && event.target ? event.target.result : null;
                        if (!cursor) {
                            store.delete(indexedDbMetaKey);
                            return;
                        }

                        var cacheKey = String(cursor.key || '');
                        if (parseQuestionIdFromCacheItemKey(cacheKey, itemKeyPrefix) > 0) {
                            store.delete(cacheKey);
                        }
                        cursor.continue();
                    };
                    cursorRequest.onerror = function () {
                        store.delete(indexedDbMetaKey);
                    };
                }
            } catch (error) {
                // Ignore IndexedDB deletion failures.
            }
        }).catch(function () {
            // Ignore IndexedDB deletion failures.
        });
    }

    return {
        buildAutoSaveStateSnapshot: buildAutoSaveStateSnapshot,
        buildChangedQuestionLookup: buildChangedQuestionLookup,
        buildQuestionCacheSessionStorageKey: buildQuestionCacheSessionStorageKey,
        buildQuestionCacheSnapshot: buildQuestionCacheSnapshot,
        buildQuestionManifestById: buildQuestionManifestById,
        buildQuestionManifestFromQuestions: buildQuestionManifestFromQuestions,
        clearPersistedQuestionCache: clearPersistedQuestionCache,
        compareQuestionRevisionFreshness: compareQuestionRevisionFreshness,
        normalizeOrUseQuestionCacheSnapshot: normalizeOrUseQuestionCacheSnapshot,
        normalizeQuestionCacheSnapshot: normalizeQuestionCacheSnapshot,
        normalizeQuestionIdList: normalizeQuestionIdList,
        normalizeQuestionManifestItem: normalizeQuestionManifestItem,
        normalizeQuestionRevision: normalizeQuestionRevision,
        normalizeStoredAutoSaveState: normalizeStoredAutoSaveState,
        persistCurrentQuestionCacheLocally: persistCurrentQuestionCacheLocally,
        persistQuestionCacheLocally: persistQuestionCacheLocally,
        questionManifestContentSignature: questionManifestContentSignature,
        questionManifestUpdatedAt: questionManifestUpdatedAt,
        questionRevisionEquals: questionRevisionEquals,
        questionRevisionSignature: questionRevisionSignature,
        readPersistedQuestionCache: readPersistedQuestionCache,
        serializeQuestionRevision: serializeQuestionRevision
    };
}
