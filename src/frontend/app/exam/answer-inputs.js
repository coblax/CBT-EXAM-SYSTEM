export function createAnswerInputManager(deps) {
    var state = deps.state;
    var autoSaveChoiceDelayMs = deps.autoSaveChoiceDelayMs;
    var autoSaveTextDelayMs = deps.autoSaveTextDelayMs;
    var clearMessages = deps.clearMessages;
    var normalizeExamToken = deps.normalizeExamToken;
    var render = deps.render;
    var root = deps.root;
    var scheduleAutoSave = deps.scheduleAutoSave;
    var scheduleQuestionCachePersist = deps.scheduleQuestionCachePersist;
    var updateSelectedExam = deps.updateSelectedExam;

    function handleChangeTarget(target) {
        var targetAction = String(target.getAttribute('data-action') || '');

        if (targetAction === 'select-exam-mobile') {
            if (target instanceof HTMLSelectElement) {
                updateSelectedExam(target.value);
                return true;
            }
            return false;
        }

        if (targetAction === 'answer-single') {
            var singleQid = Number(target.getAttribute('data-qid')) || 0;
            var singleOptionId = Number(target.getAttribute('data-option-id')) || 0;
            if (singleQid > 0 && singleOptionId > 0) {
                state.answers[singleQid] = singleOptionId;
                state.answeredQuestionLookup[singleQid] = true;
                scheduleQuestionCachePersist(200);
                clearMessages();
                scheduleAutoSave(singleQid, autoSaveChoiceDelayMs);
                render('answer-change', {
                    questionId: singleQid,
                    inputType: 'single'
                });
            }
            return true;
        }

        if (targetAction === 'answer-multi') {
            var multiQid = Number(target.getAttribute('data-qid')) || 0;
            var multiOptionId = Number(target.getAttribute('data-option-id')) || 0;
            if (multiQid <= 0 || multiOptionId <= 0) {
                return true;
            }

            var selected = Array.isArray(state.answers[multiQid]) ? state.answers[multiQid].slice() : [];
            var checked = target instanceof HTMLInputElement ? target.checked : false;

            if (checked && selected.indexOf(multiOptionId) < 0) {
                selected.push(multiOptionId);
            }
            if (!checked) {
                selected = selected.filter(function (item) { return Number(item) !== multiOptionId; });
            }

            state.answers[multiQid] = selected;
            if (selected.length > 0) {
                state.answeredQuestionLookup[multiQid] = true;
            } else {
                delete state.answeredQuestionLookup[multiQid];
            }
            scheduleQuestionCachePersist(200);
            clearMessages();
            scheduleAutoSave(multiQid, autoSaveChoiceDelayMs);
            render('answer-change', {
                questionId: multiQid,
                inputType: 'multi'
            });
            return true;
        }

        if (targetAction === 'answer-tf-matrix') {
            var matrixQid = Number(target.getAttribute('data-qid')) || 0;
            var matrixKey = String(target.getAttribute('data-key') || '').trim();
            var matrixValue = String(target.getAttribute('data-value') || '').trim().toLowerCase();
            if (matrixQid <= 0 || matrixKey === '' || (matrixValue !== 'true' && matrixValue !== 'false')) {
                return true;
            }
            if (!state.answers[matrixQid] || typeof state.answers[matrixQid] !== 'object' || Array.isArray(state.answers[matrixQid])) {
                state.answers[matrixQid] = {};
            }
            state.answers[matrixQid][matrixKey] = matrixValue;
            state.answeredQuestionLookup[matrixQid] = true;
            scheduleQuestionCachePersist(240);
            clearMessages();
            scheduleAutoSave(matrixQid, autoSaveChoiceDelayMs);
            render('answer-change', {
                questionId: matrixQid,
                inputType: 'true-false-matrix'
            });
            return true;
        }

        return false;
    }

    function syncMirroredShortAnswerInputs(questionId, shortKey, shortValue, sourceTarget) {
        var mirrorSelector = '[data-action="answer-short"][data-qid="' + questionId + '"][data-short-key="' + shortKey + '"]';
        var mirrorInputs = root.querySelectorAll(mirrorSelector);
        for (var mirrorIndex = 0; mirrorIndex < mirrorInputs.length; mirrorIndex++) {
            var mirrorNode = mirrorInputs[mirrorIndex];
            if (!(mirrorNode instanceof HTMLInputElement || mirrorNode instanceof HTMLTextAreaElement)) {
                continue;
            }
            if (mirrorNode === sourceTarget) {
                continue;
            }
            if (mirrorNode.value !== shortValue) {
                mirrorNode.value = shortValue;
            }
        }
    }

    function handleInputTarget(target) {
        if (target.getAttribute('name') === 'identifier') {
            if (target instanceof HTMLInputElement) {
                state.loginIdentifier = String(target.value || '');
                return true;
            }
            return false;
        }

        if (target.getAttribute('name') === 'password') {
            if (target instanceof HTMLInputElement) {
                state.loginPassword = String(target.value || '');
                return true;
            }
            return false;
        }

        if (target.getAttribute('name') === 'exam_token') {
            if (target instanceof HTMLInputElement) {
                state.examToken = normalizeExamToken(target.value || '');
                if (target.value !== state.examToken) {
                    target.value = state.examToken;
                }
                return true;
            }
            return false;
        }

        var action = String(target.getAttribute('data-action') || '');

        if (action === 'answer-short') {
            var shortQid = Number(target.getAttribute('data-qid')) || 0;
            var shortKey = String(target.getAttribute('data-short-key') || '').trim().toUpperCase();
            if (shortQid <= 0 || shortKey === '') {
                return true;
            }
            if (!state.answers[shortQid] || typeof state.answers[shortQid] !== 'object') {
                state.answers[shortQid] = {};
            }
            var shortValue = '';
            if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
                shortValue = String(target.value || '');
                state.answers[shortQid][shortKey] = shortValue;
            }
            var shortAnswerValues = state.answers[shortQid] && typeof state.answers[shortQid] === 'object'
                ? Object.keys(state.answers[shortQid]).map(function (key) {
                    return String(state.answers[shortQid][key] || '');
                })
                : [];
            if (shortAnswerValues.some(function (value) { return value !== ''; })) {
                state.answeredQuestionLookup[shortQid] = true;
            } else {
                delete state.answeredQuestionLookup[shortQid];
            }
            syncMirroredShortAnswerInputs(shortQid, shortKey, shortValue, target);
            scheduleQuestionCachePersist(500);
            scheduleAutoSave(shortQid, autoSaveTextDelayMs);
            return true;
        }

        if (action === 'answer-text') {
            var textQid = Number(target.getAttribute('data-qid')) || 0;
            if (textQid <= 0) {
                return true;
            }
            if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
                state.answers[textQid] = target.value;
                if (String(target.value || '') !== '') {
                    state.answeredQuestionLookup[textQid] = true;
                } else {
                    delete state.answeredQuestionLookup[textQid];
                }
            }
            scheduleQuestionCachePersist(500);
            scheduleAutoSave(textQid, autoSaveTextDelayMs);
            return true;
        }

        return false;
    }

    return {
        handleChangeTarget: handleChangeTarget,
        handleInputTarget: handleInputTarget
    };
}
