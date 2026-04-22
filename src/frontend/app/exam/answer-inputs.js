export function createAnswerInputManager(deps) {
    var documentRef = deps.documentRef || document;
    var state = deps.state;
    var autoSaveChoiceDelayMs = deps.autoSaveChoiceDelayMs;
    var autoSaveTextDelayMs = deps.autoSaveTextDelayMs;
    var clearMessages = deps.clearMessages;
    var normalizeExamToken = deps.normalizeExamToken;
    var render = deps.render;
    var renderExamPartial = typeof deps.renderExamPartial === 'function'
        ? deps.renderExamPartial
        : null;
    var root = deps.root;
    var scheduleAutoSave = deps.scheduleAutoSave;
    var scheduleQuestionCachePersist = deps.scheduleQuestionCachePersist;
    var updateSelectedExam = deps.updateSelectedExam;
    var windowRef = deps.windowRef || (documentRef && documentRef.defaultView ? documentRef.defaultView : window);
    var lastPointerChoiceInput = null;
    var lastPointerChoiceAt = 0;
    var pointerChoiceRetentionMs = 1500;

    function resolveEventElement(target) {
        if (target instanceof Element) {
            return target;
        }
        if (target && target.parentElement instanceof Element) {
            return target.parentElement;
        }
        return null;
    }

    function resolvePointerChoiceInput(target) {
        var element = resolveEventElement(target);
        if (!(element instanceof Element)) {
            return null;
        }

        var input = element.closest('input[data-action="answer-single"], input[data-action="answer-tf-matrix"]');
        if (input instanceof HTMLInputElement) {
            return input;
        }

        var label = element.closest('label');
        if (label instanceof HTMLLabelElement) {
            input = label.querySelector('input[data-action="answer-single"], input[data-action="answer-tf-matrix"]');
        }
        return input instanceof HTMLInputElement ? input : null;
    }

    function consumePointerChoiceInput(target) {
        if (!(target instanceof HTMLInputElement) || !(lastPointerChoiceInput instanceof HTMLInputElement)) {
            lastPointerChoiceInput = null;
            lastPointerChoiceAt = 0;
            return false;
        }

        if (Date.now() - lastPointerChoiceAt > pointerChoiceRetentionMs || lastPointerChoiceInput !== target) {
            lastPointerChoiceInput = null;
            lastPointerChoiceAt = 0;
            return false;
        }

        lastPointerChoiceInput = null;
        lastPointerChoiceAt = 0;
        return true;
    }

    function restoreExamShellFocusAfterPointerChoice(target) {
        if (!consumePointerChoiceInput(target)) {
            return;
        }

        var focusTarget = root instanceof HTMLElement
            ? root.querySelector('[data-cbt-exam-shell="1"]') || root.querySelector('.cbt-question-card')
            : null;

        var applyFocusRestore = function () {
            if (documentRef && documentRef.activeElement === target && typeof target.blur === 'function') {
                target.blur();
            }

            if (!(focusTarget instanceof HTMLElement)) {
                return;
            }

            if (!focusTarget.hasAttribute('tabindex')) {
                focusTarget.setAttribute('tabindex', '-1');
            }

            try {
                focusTarget.focus({
                    preventScroll: true
                });
            } catch (error) {
                focusTarget.focus();
            }
        };

        if (windowRef && typeof windowRef.setTimeout === 'function') {
            windowRef.setTimeout(applyFocusRestore, 0);
            return;
        }

        applyFocusRestore();
    }

    function renderAnswerChangePatch(reason, meta, options) {
        options = options || {};

        if (renderExamPartial) {
            var regions = {
                navigation: true,
                questionFooterProgress: true,
                questionSaveFeedback: true
            };

            if (options.includeInput !== false) {
                regions.questionInput = true;
            }

            if (options.includeQuestionHead !== false) {
                regions.questionHead = true;
            }

            if (options.includeNotice) {
                regions.notice = true;
            }

            if (renderExamPartial(regions, reason, meta || {})) {
                return;
            }
        }

        render(reason, meta);
    }

    function syncChoiceSelectionUi(questionId) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || !(root instanceof HTMLElement)) {
            return;
        }

        var selector = '.cbt-option input[data-qid="' + safeQuestionId + '"][data-action="answer-single"], .cbt-option input[data-qid="' + safeQuestionId + '"][data-action="answer-multi"]';
        var optionInputs = root.querySelectorAll(selector);
        optionInputs.forEach(function (node) {
            if (!(node instanceof HTMLInputElement)) {
                return;
            }

            var optionNode = node.closest('.cbt-option');
            if (optionNode instanceof HTMLElement) {
                optionNode.classList.toggle('is-selected', !!node.checked);
            }
        });
    }

    function syncCurrentQuestionHeadUi(questionId) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || !(root instanceof HTMLElement)) {
            return;
        }

        var questionHeadNode = root.querySelector('[data-cbt-exam-question-region="questionHead"] .cbt-question-head');
        if (!(questionHeadNode instanceof HTMLElement)) {
            return;
        }

        var isDoubtful = !!(state.doubtful && state.doubtful[safeQuestionId]);
        var isAnswered = !!(state.answeredQuestionLookup && state.answeredQuestionLookup[safeQuestionId]);
        questionHeadNode.classList.toggle('is-doubtful', isDoubtful);
        questionHeadNode.classList.toggle('is-answered', !isDoubtful && isAnswered);
    }

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
                var hadVisibleMessages = !!(state.error || state.notice || state.success);
                state.answers[singleQid] = singleOptionId;
                state.answeredQuestionLookup[singleQid] = true;
                scheduleQuestionCachePersist(200);
                clearMessages();
                scheduleAutoSave(singleQid, autoSaveChoiceDelayMs);
                syncChoiceSelectionUi(singleQid);
                syncCurrentQuestionHeadUi(singleQid);
                renderAnswerChangePatch('answer-change', {
                    questionId: singleQid,
                    inputType: 'single'
                }, {
                    includeInput: false,
                    includeQuestionHead: false,
                    includeNotice: hadVisibleMessages
                });
                restoreExamShellFocusAfterPointerChoice(target);
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
            var normalizedSelected = [];
            var seenSelectedLookup = {};
            selected.forEach(function (item) {
                var optionId = Number(item) || 0;
                if (optionId <= 0 || seenSelectedLookup[optionId]) {
                    return;
                }

                seenSelectedLookup[optionId] = true;
                normalizedSelected.push(optionId);
            });
            selected = normalizedSelected;
            var checked = target instanceof HTMLInputElement ? target.checked : false;

            if (checked && selected.indexOf(multiOptionId) < 0) {
                selected.push(multiOptionId);
            }
            if (!checked) {
                selected = selected.filter(function (item) { return Number(item) !== multiOptionId; });
            }

            var hadVisibleMessages = !!(state.error || state.notice || state.success);
            state.answers[multiQid] = selected;
            if (selected.length > 0) {
                state.answeredQuestionLookup[multiQid] = true;
            } else {
                delete state.answeredQuestionLookup[multiQid];
            }
            scheduleQuestionCachePersist(200);
            clearMessages();
            scheduleAutoSave(multiQid, autoSaveChoiceDelayMs);
            syncChoiceSelectionUi(multiQid);
            syncCurrentQuestionHeadUi(multiQid);
            renderAnswerChangePatch('answer-change', {
                questionId: multiQid,
                inputType: 'multi'
            }, {
                includeInput: false,
                includeQuestionHead: false,
                includeNotice: hadVisibleMessages
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
            var hadVisibleMessages = !!(state.error || state.notice || state.success);
            state.answers[matrixQid][matrixKey] = matrixValue;
            state.answeredQuestionLookup[matrixQid] = true;
            scheduleQuestionCachePersist(240);
            clearMessages();
            scheduleAutoSave(matrixQid, autoSaveChoiceDelayMs);
            syncCurrentQuestionHeadUi(matrixQid);
            renderAnswerChangePatch('answer-change', {
                questionId: matrixQid,
                inputType: 'true-false-matrix'
            }, {
                includeInput: false,
                includeQuestionHead: false,
                includeNotice: hadVisibleMessages
            });
            restoreExamShellFocusAfterPointerChoice(target);
            return true;
        }

        return false;
    }

    function handlePointerTarget(target) {
        var pointerChoiceInput = resolvePointerChoiceInput(target);
        if (!(pointerChoiceInput instanceof HTMLInputElement)) {
            return false;
        }

        lastPointerChoiceInput = pointerChoiceInput;
        lastPointerChoiceAt = Date.now();
        return true;
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
            syncCurrentQuestionHeadUi(shortQid);
            renderAnswerChangePatch('answer-input', {
                questionId: shortQid,
                inputType: 'short'
            }, {
                includeInput: false,
                includeQuestionHead: false
            });
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
            syncCurrentQuestionHeadUi(textQid);
            renderAnswerChangePatch('answer-input', {
                questionId: textQid,
                inputType: 'text'
            }, {
                includeInput: false,
                includeQuestionHead: false
            });
            return true;
        }

        return false;
    }

    return {
        handlePointerTarget: handlePointerTarget,
        handleChangeTarget: handleChangeTarget,
        handleInputTarget: handleInputTarget
    };
}
