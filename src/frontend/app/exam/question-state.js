import {
    findQuestionOptionByKey,
    findQuestionOptionKeyById,
    getClozeDropdownBlanks,
    getShortAnswerKeys,
    normalizeAnswerValueForQuestion,
    normalizeDropdownOptionAnswer,
    normalizeTableCompletionAnswer,
    normalizeTrueFalseMatrixAnswer
} from './question-helpers';

export function createQuestionStateManager(deps) {
    var state = deps.state;
    var getQuestionById = deps.getQuestionById;

    var pendingRevisionSafeAnswerRestoreByQuestion = {};

    function clearPendingRevisionSafeAnswerRestoreState() {
        pendingRevisionSafeAnswerRestoreByQuestion = {};
    }

    function normalizeExistingAnswerForQuestion(question) {
        if (!question || !Object.prototype.hasOwnProperty.call(question, 'existing_answer')) {
            return {
                hasValue: false,
                value: null
            };
        }

        var existing = question.existing_answer;
        if (existing === null || existing === undefined || existing === '') {
            return {
                hasValue: false,
                value: null
            };
        }
        return normalizeAnswerValueForQuestion(question, existing);
    }

    function rememberExistingAnswerRaw(questionId, rawAnswer) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || rawAnswer === undefined) {
            return;
        }

        if (!state.existingAnswerRawByQuestionId || typeof state.existingAnswerRawByQuestionId !== 'object') {
            state.existingAnswerRawByQuestionId = {};
        }

        state.existingAnswerRawByQuestionId[safeQuestionId] = rawAnswer;
    }

    function getRememberedExistingAnswerRaw(questionId) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || !state.existingAnswerRawByQuestionId || typeof state.existingAnswerRawByQuestionId !== 'object') {
            return undefined;
        }

        return Object.prototype.hasOwnProperty.call(state.existingAnswerRawByQuestionId, safeQuestionId)
            ? state.existingAnswerRawByQuestionId[safeQuestionId]
            : undefined;
    }

    function hasUsableLocalAnswerForQuestion(questionId, question) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || !Object.prototype.hasOwnProperty.call(state.answers, safeQuestionId)) {
            return false;
        }

        var referenceQuestion = question || getQuestionById(safeQuestionId);
        if (!referenceQuestion) {
            return true;
        }

        return normalizeAnswerValueForQuestion(referenceQuestion, state.answers[safeQuestionId], {
            preserveText: true
        }).hasValue;
    }

    function resolveStoredAnswerValueForQuestion(question) {
        var questionId = Number(question && question.id) || 0;
        if (questionId <= 0 || !question) {
            return undefined;
        }

        if (hasUsableLocalAnswerForQuestion(questionId, question)) {
            return state.answers[questionId];
        }

        var rawExistingAnswer = Object.prototype.hasOwnProperty.call(question, 'existing_answer')
            ? question.existing_answer
            : getRememberedExistingAnswerRaw(questionId);
        if (rawExistingAnswer === undefined) {
            return undefined;
        }

        var normalized = normalizeAnswerValueForQuestion(question, rawExistingAnswer, {
            preserveText: true
        });
        if (!normalized.hasValue) {
            return undefined;
        }

        state.answers[questionId] = normalized.value;
        state.answeredQuestionLookup[questionId] = true;
        rememberExistingAnswerRaw(questionId, rawExistingAnswer);
        return normalized.value;
    }

    function mergeExistingAnswersFromQuestionItems(questions, options) {
        options = options || {};
        var overwriteExisting = !!options.overwriteExisting;

        if (!Array.isArray(questions)) {
            return;
        }

        questions.forEach(function (question) {
            var questionId = Number(question && question.id) || 0;
            if (questionId <= 0) {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(question, 'existing_answer')) {
                rememberExistingAnswerRaw(questionId, question.existing_answer);
            }

            var normalized = normalizeExistingAnswerForQuestion(question);
            if (!normalized.hasValue) {
                return;
            }

            if (!overwriteExisting && hasUsableLocalAnswerForQuestion(questionId, question)) {
                return;
            }

            state.answers[questionId] = normalized.value;
            state.answeredQuestionLookup[questionId] = true;
        });
    }

    function mergeExistingAnswersMap(existingAnswersMap, options) {
        options = options || {};
        var overwriteExisting = !!options.overwriteExisting;

        if (!existingAnswersMap || typeof existingAnswersMap !== 'object') {
            return;
        }

        Object.keys(existingAnswersMap).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId <= 0) {
                return;
            }

            rememberExistingAnswerRaw(questionId, existingAnswersMap[key]);

            var question = getQuestionById(questionId);
            if (!overwriteExisting && hasUsableLocalAnswerForQuestion(questionId, question)) {
                return;
            }

            var normalized = normalizeAnswerValueForQuestion(question, existingAnswersMap[key]);
            if (!normalized.hasValue) {
                return;
            }

            state.answers[questionId] = normalized.value;
            state.answeredQuestionLookup[questionId] = true;
        });
    }

    function restoreLocalAnswerFromQuestion(question, options) {
        options = options || {};

        var questionId = Number(question && question.id) || 0;
        if (questionId <= 0) {
            return false;
        }

        if (!options.overwriteExisting && hasUsableLocalAnswerForQuestion(questionId, question)) {
            return true;
        }

        var normalized = normalizeExistingAnswerForQuestion(question);
        if (!normalized.hasValue) {
            return false;
        }

        state.answers[questionId] = normalized.value;
        state.answeredQuestionLookup[questionId] = true;
        return true;
    }

    function captureRevisionSafeAnswerForQuestion(questionId) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || !Object.prototype.hasOwnProperty.call(state.answers, safeQuestionId)) {
            return null;
        }

        var question = getQuestionById(safeQuestionId);
        if (!question) {
            return null;
        }

        var answer = state.answers[safeQuestionId];
        var questionType = String(question.question_type || '');
        var questionUpdatedAt = String(question && question.updated_at ? question.updated_at : '').trim();

        if (questionType === 'multiple_choice' || questionType === 'true_false') {
            var singleOptionKey = findQuestionOptionKeyById(question, answer);
            if (singleOptionKey === '') {
                return null;
            }
            return {
                kind: 'option_single',
                option_key: singleOptionKey,
                question_updated_at: questionUpdatedAt
            };
        }

        if (questionType === 'multiple_answer' || questionType === 'ordering') {
            if (!Array.isArray(answer)) {
                return null;
            }

            var selectedOptionKeys = [];
            var seenOptionKeys = {};
            answer.forEach(function (item) {
                var optionKey = findQuestionOptionKeyById(question, item);
                if (optionKey === '' || seenOptionKeys[optionKey]) {
                    return;
                }
                seenOptionKeys[optionKey] = true;
                selectedOptionKeys.push(optionKey);
            });

            if (!selectedOptionKeys.length) {
                return null;
            }

            return {
                kind: questionType === 'ordering' ? 'option_ordered' : 'option_multi',
                option_keys: selectedOptionKeys,
                question_updated_at: questionUpdatedAt
            };
        }

        if (questionType === 'true_false_matrix') {
            var normalizedMatrixAnswer = normalizeAnswerValueForQuestion(question, answer, {
                preserveText: true
            });
            if (!normalizedMatrixAnswer.hasValue) {
                return null;
            }

            return {
                kind: 'true_false_matrix',
                value: normalizedMatrixAnswer.value,
                question_updated_at: questionUpdatedAt
            };
        }

        if (questionType === 'matching' || questionType === 'cloze_dropdown' || questionType === 'categorization') {
            var normalizedDropdownAnswer = normalizeAnswerValueForQuestion(question, answer, {
                preserveText: true
            });
            if (!normalizedDropdownAnswer.hasValue) {
                return null;
            }

            var dropdownOptionKeysByAnswerKey = {};
            if (questionType === 'matching' || questionType === 'categorization') {
                Object.keys(normalizedDropdownAnswer.value).forEach(function (answerKey) {
                    var optionKey = findQuestionOptionKeyById(question, normalizedDropdownAnswer.value[answerKey]);
                    if (optionKey !== '') {
                        dropdownOptionKeysByAnswerKey[answerKey] = optionKey;
                    }
                });
            } else {
                var clozeBlanksByKey = getClozeDropdownBlanks(question).reduce(function (accumulator, blank) {
                    accumulator[String(blank.key || '').trim()] = blank;
                    return accumulator;
                }, {});
                Object.keys(normalizedDropdownAnswer.value).forEach(function (answerKey) {
                    var blank = clozeBlanksByKey[answerKey];
                    var selectedOptionId = Number(normalizedDropdownAnswer.value[answerKey]) || 0;
                    if (!blank || selectedOptionId <= 0) {
                        return;
                    }
                    var matchedOptionKey = '';
                    blank.options.forEach(function (option, index) {
                        if (matchedOptionKey !== '' || Number(option && option.id) !== selectedOptionId) {
                            return;
                        }
                        matchedOptionKey = String(option && option.option_key ? option.option_key : String.fromCharCode(65 + index)).trim().toUpperCase();
                    });
                    if (matchedOptionKey !== '') {
                        dropdownOptionKeysByAnswerKey[answerKey] = matchedOptionKey;
                    }
                });
            }

            if (!Object.keys(dropdownOptionKeysByAnswerKey).length) {
                return null;
            }

            return {
                kind: 'dropdown_option_map',
                question_type: questionType,
                option_keys_by_answer_key: dropdownOptionKeysByAnswerKey,
                question_updated_at: questionUpdatedAt
            };
        }

        if (questionType === 'table_completion') {
            var normalizedTableAnswer = normalizeAnswerValueForQuestion(question, answer, {
                preserveText: true
            });
            if (!normalizedTableAnswer.hasValue) {
                return null;
            }

            return {
                kind: 'table_completion',
                value: normalizedTableAnswer.value,
                question_updated_at: questionUpdatedAt
            };
        }

        if (questionType === 'short_answer') {
            var normalizedShortAnswer = normalizeAnswerValueForQuestion(question, answer, {
                preserveText: true
            });
            if (!normalizedShortAnswer.hasValue) {
                return null;
            }

            return {
                kind: 'short_answer',
                value: normalizedShortAnswer.value,
                question_updated_at: questionUpdatedAt
            };
        }

        var normalizedTextAnswer = normalizeAnswerValueForQuestion(question, answer, {
            preserveText: true
        });
        if (!normalizedTextAnswer.hasValue) {
            return null;
        }

        return {
            kind: 'text',
            value: normalizedTextAnswer.value,
            question_updated_at: questionUpdatedAt
        };
    }

    function captureRevisionSafeLocalAnswers() {
        return Object.keys(state.answers || {}).reduce(function (accumulator, key) {
            var questionId = Number(key) || 0;
            var preservedAnswer = captureRevisionSafeAnswerForQuestion(questionId);
            if (questionId > 0 && preservedAnswer) {
                accumulator[questionId] = preservedAnswer;
            }
            return accumulator;
        }, {});
    }

    function restoreRevisionSafeAnswerForQuestion(question, preservedAnswer) {
        var questionId = Number(question && question.id) || 0;
        if (questionId <= 0 || !preservedAnswer || typeof preservedAnswer !== 'object') {
            return false;
        }

        var preservedUpdatedAt = String(preservedAnswer.question_updated_at || '').trim();
        var currentUpdatedAt = String(question && question.updated_at ? question.updated_at : '').trim();
        if (preservedUpdatedAt !== '' && currentUpdatedAt !== '' && preservedUpdatedAt !== currentUpdatedAt) {
            delete state.answers[questionId];
            delete state.answeredQuestionLookup[questionId];
            return false;
        }

        var nextValue = null;
        var hasValue = false;
        var kind = String(preservedAnswer.kind || '');

        if (kind === 'option_single') {
            var selectedOption = findQuestionOptionByKey(question, preservedAnswer.option_key);
            nextValue = Number(selectedOption && selectedOption.id) || 0;
            hasValue = nextValue > 0;
        } else if (kind === 'option_multi' || kind === 'option_ordered') {
            var selectedOptionIds = [];
            var seenOptionIds = {};
            var optionKeys = Array.isArray(preservedAnswer.option_keys) ? preservedAnswer.option_keys : [];
            optionKeys.forEach(function (item) {
                var option = findQuestionOptionByKey(question, item);
                var optionId = Number(option && option.id) || 0;
                if (optionId <= 0 || seenOptionIds[optionId]) {
                    return;
                }
                seenOptionIds[optionId] = true;
                selectedOptionIds.push(optionId);
            });
            nextValue = selectedOptionIds;
            hasValue = selectedOptionIds.length > 0;
        } else if (kind === 'dropdown_option_map') {
            var dropdownValue = {};
            var dropdownQuestionType = String(question.question_type || '');
            var optionKeysByAnswerKey = preservedAnswer.option_keys_by_answer_key && typeof preservedAnswer.option_keys_by_answer_key === 'object'
                ? preservedAnswer.option_keys_by_answer_key
                : {};

            if (dropdownQuestionType === 'matching' || dropdownQuestionType === 'categorization') {
                Object.keys(optionKeysByAnswerKey).forEach(function (answerKey) {
                    var option = findQuestionOptionByKey(question, optionKeysByAnswerKey[answerKey]);
                    var optionId = Number(option && option.id) || 0;
                    if (optionId > 0) {
                        dropdownValue[String(answerKey || '').trim()] = optionId;
                    }
                });
            } else if (dropdownQuestionType === 'cloze_dropdown') {
                var blankLookup = getClozeDropdownBlanks(question).reduce(function (accumulator, blank) {
                    accumulator[String(blank.key || '').trim()] = blank;
                    return accumulator;
                }, {});
                Object.keys(optionKeysByAnswerKey).forEach(function (answerKey) {
                    var blank = blankLookup[String(answerKey || '').trim()];
                    var wantedOptionKey = String(optionKeysByAnswerKey[answerKey] || '').trim().toUpperCase();
                    if (!blank || wantedOptionKey === '') {
                        return;
                    }
                    blank.options.forEach(function (option) {
                        var optionKey = String(option && option.option_key ? option.option_key : '').trim().toUpperCase();
                        var optionId = Number(option && option.id) || 0;
                        if (optionId > 0 && optionKey === wantedOptionKey) {
                            dropdownValue[String(answerKey || '').trim()] = optionId;
                        }
                    });
                });
            }

            var normalizedDropdown = normalizeAnswerValueForQuestion(question, dropdownValue, {
                preserveText: true
            });
            nextValue = normalizedDropdown.value;
            hasValue = normalizedDropdown.hasValue;
        } else if (kind === 'true_false_matrix' || kind === 'short_answer' || kind === 'text' || kind === 'table_completion') {
            var normalizedAnswer = normalizeAnswerValueForQuestion(
                question,
                preservedAnswer.value,
                {
                    preserveText: true
                }
            );
            nextValue = normalizedAnswer.value;
            hasValue = normalizedAnswer.hasValue;
        }

        if (!hasValue) {
            delete state.answers[questionId];
            delete state.answeredQuestionLookup[questionId];
            return false;
        }

        state.answers[questionId] = nextValue;
        state.answeredQuestionLookup[questionId] = true;
        return true;
    }

    function restoreRevisionSafeLocalAnswers(preservedAnswers, options) {
        options = options || {};
        var shouldDeferMissingQuestions = options.deferMissing !== false;
        var restoredQuestionIds = [];

        if (!preservedAnswers || typeof preservedAnswers !== 'object') {
            return restoredQuestionIds;
        }

        Object.keys(preservedAnswers).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId <= 0) {
                return;
            }

            var question = getQuestionById(questionId);
            if (!question) {
                if (shouldDeferMissingQuestions) {
                    pendingRevisionSafeAnswerRestoreByQuestion[questionId] = preservedAnswers[key];
                }
                return;
            }

            if (restoreRevisionSafeAnswerForQuestion(question, preservedAnswers[key])) {
                restoredQuestionIds.push(questionId);
            }

            delete pendingRevisionSafeAnswerRestoreByQuestion[questionId];
        });

        return restoredQuestionIds;
    }

    function applyPendingRevisionSafeAnswersForLoadedQuestions(questions) {
        if (!Array.isArray(questions) || !questions.length) {
            return [];
        }

        var restoredQuestionIds = [];
        questions.forEach(function (question) {
            var questionId = Number(question && question.id) || 0;
            if (questionId <= 0 || !Object.prototype.hasOwnProperty.call(pendingRevisionSafeAnswerRestoreByQuestion, questionId)) {
                return;
            }

            if (restoreRevisionSafeAnswerForQuestion(question, pendingRevisionSafeAnswerRestoreByQuestion[questionId])) {
                restoredQuestionIds.push(questionId);
            }

            delete pendingRevisionSafeAnswerRestoreByQuestion[questionId];
        });

        return restoredQuestionIds;
    }

    function prunePendingRevisionSafeAnswerRestoreState(validLookup) {
        Object.keys(pendingRevisionSafeAnswerRestoreByQuestion || {}).forEach(function (key) {
            var questionId = Number(key) || 0;
            if (questionId > 0 && !validLookup[questionId]) {
                delete pendingRevisionSafeAnswerRestoreByQuestion[key];
            }
        });
    }

    function questionAnswerPayload(question) {
        if (!question) {
            return null;
        }

        var answer = resolveStoredAnswerValueForQuestion(question);

        if (question.question_type === 'multiple_choice' || question.question_type === 'true_false') {
            var selected = Number(answer) || 0;
            return selected > 0 ? selected : null;
        }

        if (question.question_type === 'multiple_answer' || question.question_type === 'ordering') {
            var normalizedMultiAnswer = normalizeAnswerValueForQuestion(question, answer, {
                preserveText: true
            });
            if (!normalizedMultiAnswer.hasValue || !Array.isArray(normalizedMultiAnswer.value) || !normalizedMultiAnswer.value.length) {
                return null;
            }
            var seen = {};
            return normalizedMultiAnswer.value.filter(function (item) {
                if (seen[item]) {
                    return false;
                }
                seen[item] = true;
                return true;
            });
        }

        if (question.question_type === 'true_false_matrix') {
            var normalizedMatrixAnswer = normalizeAnswerValueForQuestion(question, answer, {
                preserveText: true
            });
            if (!normalizedMatrixAnswer.hasValue || !normalizedMatrixAnswer.value || !Object.keys(normalizedMatrixAnswer.value).length) {
                return null;
            }
            return normalizeTrueFalseMatrixAnswer(normalizedMatrixAnswer.value);
        }

        if (question.question_type === 'matching' || question.question_type === 'cloze_dropdown' || question.question_type === 'categorization') {
            var normalizedDropdownAnswer = normalizeAnswerValueForQuestion(question, answer, {
                preserveText: true
            });
            if (!normalizedDropdownAnswer.hasValue || !normalizedDropdownAnswer.value || !Object.keys(normalizedDropdownAnswer.value).length) {
                return null;
            }
            return normalizeDropdownOptionAnswer(question, normalizedDropdownAnswer.value, question.question_type);
        }

        if (question.question_type === 'table_completion') {
            var normalizedTableAnswer = normalizeAnswerValueForQuestion(question, answer, {
                preserveText: true
            });
            if (!normalizedTableAnswer.hasValue || !normalizedTableAnswer.value || !Object.keys(normalizedTableAnswer.value).length) {
                return null;
            }
            return normalizeTableCompletionAnswer(question, normalizedTableAnswer.value);
        }

        if (question.question_type === 'short_answer') {
            if (!answer || typeof answer !== 'object') {
                return null;
            }
            var payload = {};
            var keys = getShortAnswerKeys(question);
            keys.forEach(function (key) {
                var raw = String(answer[key] || '').trim();
                if (raw !== '') {
                    payload['input_' + key.toLowerCase()] = raw;
                }
            });
            if (!Object.keys(payload).length) {
                return null;
            }
            return payload;
        }

        var textValue = String(answer || '').trim();
        return textValue !== '' ? textValue : null;
    }

    function payloadSignature(payload) {
        if (payload === null || payload === undefined) {
            return '';
        }
        if (Array.isArray(payload) || typeof payload === 'object') {
            try {
                return JSON.stringify(payload);
            } catch (error) {
                return String(payload);
            }
        }
        return String(payload);
    }

    return {
        applyPendingRevisionSafeAnswersForLoadedQuestions: applyPendingRevisionSafeAnswersForLoadedQuestions,
        captureRevisionSafeLocalAnswers: captureRevisionSafeLocalAnswers,
        clearPendingRevisionSafeAnswerRestoreState: clearPendingRevisionSafeAnswerRestoreState,
        hasUsableLocalAnswerForQuestion: hasUsableLocalAnswerForQuestion,
        mergeExistingAnswersFromQuestionItems: mergeExistingAnswersFromQuestionItems,
        mergeExistingAnswersMap: mergeExistingAnswersMap,
        normalizeExistingAnswerForQuestion: normalizeExistingAnswerForQuestion,
        payloadSignature: payloadSignature,
        prunePendingRevisionSafeAnswerRestoreState: prunePendingRevisionSafeAnswerRestoreState,
        questionAnswerPayload: questionAnswerPayload,
        resolveStoredAnswerValueForQuestion: resolveStoredAnswerValueForQuestion,
        restoreLocalAnswerFromQuestion: restoreLocalAnswerFromQuestion,
        restoreRevisionSafeLocalAnswers: restoreRevisionSafeLocalAnswers
    };
}
