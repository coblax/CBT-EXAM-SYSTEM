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

    function readOrderingOptionIdsFromDom(questionId) {
        var safeQuestionId = Number(questionId) || 0;
        if (safeQuestionId <= 0 || !(root instanceof HTMLElement)) {
            return [];
        }

        var list = root.querySelector('[data-cbt-ordering-list="1"][data-qid="' + safeQuestionId + '"]');
        if (!(list instanceof HTMLElement)) {
            return [];
        }

        var ids = [];
        var seen = {};
        list.querySelectorAll('.cbt-ordering-item[data-option-id]').forEach(function (node) {
            if (!(node instanceof HTMLElement)) {
                return;
            }
            var optionId = Number(node.getAttribute('data-option-id')) || 0;
            if (optionId <= 0 || seen[optionId]) {
                return;
            }
            seen[optionId] = true;
            ids.push(optionId);
        });

        return ids;
    }

    function closeAnswerSelectMenus(exceptShell) {
        if (!(root instanceof HTMLElement)) {
            return;
        }
        root.querySelectorAll('.cbt-answer-select-ui.is-open').forEach(function (node) {
            if (!(node instanceof HTMLElement) || node === exceptShell) {
                return;
            }
            node.classList.remove('is-open');
            var toggle = node.querySelector('[data-action="answer-select-toggle"]');
            if (toggle instanceof HTMLElement) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function syncAnswerSelectUi(select) {
        if (!(select instanceof HTMLSelectElement)) {
            return;
        }
        var shell = select.closest('.cbt-answer-select-ui');
        if (!(shell instanceof HTMLElement)) {
            return;
        }

        var selectedValue = String(select.value || '');
        var selectedOption = select.options[select.selectedIndex] || null;
        var selectedLabel = selectedValue !== '' && selectedOption ? String(selectedOption.textContent || '').trim() : '';
        var valueNode = shell.querySelector('.cbt-answer-select-value');
        if (valueNode instanceof HTMLElement) {
            valueNode.textContent = selectedLabel || 'Pilih jawaban';
        }
        var button = shell.querySelector('[data-action="answer-select-toggle"]');
        if (button instanceof HTMLElement) {
            button.classList.toggle('is-empty', selectedValue === '');
            button.setAttribute('aria-expanded', 'false');
        }
        shell.querySelectorAll('.cbt-answer-select-option[data-option-id]').forEach(function (node) {
            if (!(node instanceof HTMLElement)) {
                return;
            }
            var isSelected = String(node.getAttribute('data-option-id') || '') === selectedValue;
            node.classList.toggle('is-selected', isSelected);
            node.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        });
        shell.classList.remove('is-open');
    }

    function handleClickTarget(target) {
        var action = String(target.getAttribute('data-action') || '');
        if (action === 'answer-select-toggle') {
            var toggleShell = target.closest('.cbt-answer-select-ui');
            if (!(toggleShell instanceof HTMLElement)) {
                return true;
            }
            var select = toggleShell.querySelector('select.cbt-answer-select-native');
            if (select instanceof HTMLSelectElement && select.disabled) {
                return true;
            }
            var willOpen = !toggleShell.classList.contains('is-open');
            closeAnswerSelectMenus(toggleShell);
            toggleShell.classList.toggle('is-open', willOpen);
            target.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            return true;
        }

        if (action === 'answer-select-option') {
            var optionShell = target.closest('.cbt-answer-select-ui');
            if (!(optionShell instanceof HTMLElement)) {
                return true;
            }
            var optionSelect = optionShell.querySelector('select.cbt-answer-select-native');
            if (!(optionSelect instanceof HTMLSelectElement) || optionSelect.disabled) {
                return true;
            }
            optionSelect.value = String(target.getAttribute('data-option-id') || '');
            syncAnswerSelectUi(optionSelect);
            handleChangeTarget(optionSelect);
            closeAnswerSelectMenus();
            return true;
        }

        if (action !== 'answer-ordering-move') {
            closeAnswerSelectMenus();
            return false;
        }

        var questionId = Number(target.getAttribute('data-qid')) || 0;
        var optionId = Number(target.getAttribute('data-option-id')) || 0;
        var direction = String(target.getAttribute('data-direction') || '').trim().toLowerCase();
        if (questionId <= 0 || optionId <= 0 || (direction !== 'up' && direction !== 'down')) {
            return true;
        }

        var orderedIds = readOrderingOptionIdsFromDom(questionId);
        if (!orderedIds.length && Array.isArray(state.answers[questionId])) {
            var seenStateIds = {};
            orderedIds = state.answers[questionId].reduce(function (accumulator, item) {
                var currentId = Number(item) || 0;
                if (currentId <= 0 || seenStateIds[currentId]) {
                    return accumulator;
                }
                seenStateIds[currentId] = true;
                accumulator.push(currentId);
                return accumulator;
            }, []);
        }

        var index = orderedIds.indexOf(optionId);
        var nextIndex = direction === 'up' ? index - 1 : index + 1;
        if (index < 0 || nextIndex < 0 || nextIndex >= orderedIds.length) {
            return true;
        }

        var moved = orderedIds[index];
        orderedIds[index] = orderedIds[nextIndex];
        orderedIds[nextIndex] = moved;

        var hadVisibleMessages = !!(state.error || state.notice || state.success);
        state.answers[questionId] = orderedIds;
        if (orderedIds.length > 1) {
            state.answeredQuestionLookup[questionId] = true;
        } else {
            delete state.answeredQuestionLookup[questionId];
        }
        scheduleQuestionCachePersist(200);
        clearMessages();
        scheduleAutoSave(questionId, autoSaveChoiceDelayMs);
        syncCurrentQuestionHeadUi(questionId);
        renderAnswerChangePatch('answer-change', {
            questionId: questionId,
            inputType: 'ordering'
        }, {
            includeNotice: hadVisibleMessages
        });

        return true;
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

        if (targetAction === 'answer-matching' || targetAction === 'answer-cloze-dropdown' || targetAction === 'answer-categorization') {
            var dropdownQid = Number(target.getAttribute('data-qid')) || 0;
            var dropdownKeyAttr = 'data-cloze-key';
            if (targetAction === 'answer-matching') {
                dropdownKeyAttr = 'data-matching-key';
            } else if (targetAction === 'answer-categorization') {
                dropdownKeyAttr = 'data-categorization-key';
            }
            var dropdownKey = String(target.getAttribute(dropdownKeyAttr) || '').trim();
            var dropdownOptionId = target instanceof HTMLSelectElement ? (Number(target.value) || 0) : 0;
            if (dropdownQid <= 0 || dropdownKey === '') {
                return true;
            }
            if (!state.answers[dropdownQid] || typeof state.answers[dropdownQid] !== 'object' || Array.isArray(state.answers[dropdownQid])) {
                state.answers[dropdownQid] = {};
            }

            var hadDropdownVisibleMessages = !!(state.error || state.notice || state.success);
            if (dropdownOptionId > 0) {
                state.answers[dropdownQid][dropdownKey] = dropdownOptionId;
            } else {
                delete state.answers[dropdownQid][dropdownKey];
            }

            if (Object.keys(state.answers[dropdownQid]).length > 0) {
                state.answeredQuestionLookup[dropdownQid] = true;
            } else {
                delete state.answeredQuestionLookup[dropdownQid];
            }

            scheduleQuestionCachePersist(240);
            clearMessages();
            scheduleAutoSave(dropdownQid, autoSaveChoiceDelayMs);
            syncCurrentQuestionHeadUi(dropdownQid);
            renderAnswerChangePatch('answer-change', {
                questionId: dropdownQid,
                inputType: targetAction === 'answer-matching' ? 'matching' : (targetAction === 'answer-categorization' ? 'categorization' : 'cloze-dropdown')
            }, {
                includeInput: false,
                includeQuestionHead: false,
                includeNotice: hadDropdownVisibleMessages
            });
            return true;
        }

        if (targetAction === 'answer-table-completion-dropdown') {
            var tableDropdownQid = Number(target.getAttribute('data-qid')) || 0;
            var tableDropdownKey = String(target.getAttribute('data-table-key') || '').trim().toUpperCase();
            var tableDropdownOptionId = target instanceof HTMLSelectElement ? (Number(target.value) || 0) : 0;
            if (tableDropdownQid <= 0 || tableDropdownKey === '') {
                return true;
            }
            if (!state.answers[tableDropdownQid] || typeof state.answers[tableDropdownQid] !== 'object' || Array.isArray(state.answers[tableDropdownQid])) {
                state.answers[tableDropdownQid] = {};
            }
            var hadTableDropdownVisibleMessages = !!(state.error || state.notice || state.success);
            if (tableDropdownOptionId > 0) {
                state.answers[tableDropdownQid][tableDropdownKey] = tableDropdownOptionId;
            } else {
                delete state.answers[tableDropdownQid][tableDropdownKey];
            }
            if (Object.keys(state.answers[tableDropdownQid]).length > 0) {
                state.answeredQuestionLookup[tableDropdownQid] = true;
            } else {
                delete state.answeredQuestionLookup[tableDropdownQid];
            }
            scheduleQuestionCachePersist(240);
            clearMessages();
            scheduleAutoSave(tableDropdownQid, autoSaveChoiceDelayMs);
            syncCurrentQuestionHeadUi(tableDropdownQid);
            renderAnswerChangePatch('answer-change', {
                questionId: tableDropdownQid,
                inputType: 'table-completion'
            }, {
                includeInput: false,
                includeQuestionHead: false,
                includeNotice: hadTableDropdownVisibleMessages
            });
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

        if (action === 'answer-table-completion-text') {
            var tableTextQid = Number(target.getAttribute('data-qid')) || 0;
            var tableTextKey = String(target.getAttribute('data-table-key') || '').trim().toUpperCase();
            if (tableTextQid <= 0 || tableTextKey === '') {
                return true;
            }
            if (!state.answers[tableTextQid] || typeof state.answers[tableTextQid] !== 'object' || Array.isArray(state.answers[tableTextQid])) {
                state.answers[tableTextQid] = {};
            }
            var tableTextValue = target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement
                ? String(target.value || '')
                : '';
            if (tableTextValue.trim() !== '') {
                state.answers[tableTextQid][tableTextKey] = tableTextValue;
            } else {
                delete state.answers[tableTextQid][tableTextKey];
            }
            if (Object.keys(state.answers[tableTextQid]).length > 0) {
                state.answeredQuestionLookup[tableTextQid] = true;
            } else {
                delete state.answeredQuestionLookup[tableTextQid];
            }
            scheduleQuestionCachePersist(500);
            scheduleAutoSave(tableTextQid, autoSaveTextDelayMs);
            syncCurrentQuestionHeadUi(tableTextQid);
            renderAnswerChangePatch('answer-input', {
                questionId: tableTextQid,
                inputType: 'table-completion'
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
        handleClickTarget: handleClickTarget,
        handleChangeTarget: handleChangeTarget,
        handleInputTarget: handleInputTarget
    };
}
