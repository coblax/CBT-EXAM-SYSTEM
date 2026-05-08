function questionOptionKey(option, index) {
    var key = String(option && option.option_key ? option.option_key : '').trim();
    if (key !== '') {
        return key;
    }

    var code = 65 + Number(index || 0);
    if (code >= 65 && code <= 90) {
        return String.fromCharCode(code);
    }

    return String((Number(index) || 0) + 1);
}

function getShortAnswerKeys(question) {
    var meta = question && question.short_answer_meta ? question.short_answer_meta : null;
    var keys = meta && Array.isArray(meta.input_keys) ? meta.input_keys.slice(0, 8) : [];
    if (!keys.length) {
        keys = ['A'];
    }

    return keys.map(function (item) {
        return String(item || '').trim().toUpperCase();
    }).filter(function (item) {
        return item !== '';
    });
}

function getTrueFalseMatrixItems(question) {
    var meta = question && question.true_false_matrix_meta ? question.true_false_matrix_meta : null;
    var items = meta && Array.isArray(meta.items) ? meta.items : [];

    return items.map(function (item, index) {
        var key = String(item && item.key ? item.key : (index + 1)).trim();
        if (key === '') {
            key = String(index + 1);
        }

        return {
            key: key,
            text: String(item && item.text ? item.text : '')
        };
    });
}

function getMatchingItems(question) {
    var meta = question && question.matching_meta ? question.matching_meta : null;
    var items = meta && Array.isArray(meta.items) ? meta.items : [];

    return items.map(function (item, index) {
        var key = String(item && item.key ? item.key : (index + 1)).trim();
        if (key === '') {
            key = String(index + 1);
        }

        return {
            key: key,
            text: String(item && item.text ? item.text : '')
        };
    });
}

function getClozeDropdownBlanks(question) {
    var meta = question && question.cloze_dropdown_meta ? question.cloze_dropdown_meta : null;
    var blanks = meta && Array.isArray(meta.blanks) ? meta.blanks : [];

    return blanks.map(function (blank, index) {
        var key = String(blank && blank.key ? blank.key : (index + 1)).trim();
        if (key === '') {
            key = String(index + 1);
        }

        var options = Array.isArray(blank && blank.options) ? blank.options : [];
        return {
            key: key,
            position: Number(blank && blank.position) || (index + 1),
            options: options.map(function (option, optionIndex) {
                return {
                    id: Number(option && option.id) || 0,
                    option_key: String(option && option.option_key ? option.option_key : questionOptionKey(option, optionIndex)),
                    option_text: String(option && option.option_text ? option.option_text : '')
                };
            }).filter(function (option) {
                return option.id > 0;
            })
        };
    });
}

function getCategorizationItems(question) {
    var meta = question && question.categorization_meta ? question.categorization_meta : null;
    var items = meta && Array.isArray(meta.items) ? meta.items : [];

    return items.map(function (item, index) {
        var key = String(item && item.key ? item.key : (index + 1)).trim();
        if (key === '') {
            key = String(index + 1);
        }

        return {
            key: key,
            text: String(item && item.text ? item.text : '')
        };
    });
}

function getTableCompletionCells(question) {
    var meta = question && question.table_completion_meta ? question.table_completion_meta : null;
    var cells = meta && Array.isArray(meta.cells) ? meta.cells : [];

    return cells.map(function (cell) {
        var type = String(cell && cell.type ? cell.type : 'static');
        if (['static', 'text', 'dropdown'].indexOf(type) < 0) {
            type = 'static';
        }
        var options = Array.isArray(cell && cell.options) ? cell.options : [];
        return {
            key: String(cell && cell.key ? cell.key : '').trim().toUpperCase(),
            row: Number(cell && cell.row) || 0,
            column: Number(cell && cell.column) || 0,
            type: type,
            text: String(cell && cell.text ? cell.text : ''),
            options: options.map(function (option, optionIndex) {
                return {
                    id: Number(option && option.id) || 0,
                    option_key: String(option && option.option_key ? option.option_key : questionOptionKey(option, optionIndex)),
                    option_text: String(option && option.option_text ? option.option_text : '')
                };
            }).filter(function (option) {
                return option.id > 0;
            })
        };
    });
}

function normalizeTrueFalseMatrixAnswer(answer) {
    if (!answer || typeof answer !== 'object') {
        return {};
    }

    return Object.keys(answer).reduce(function (accumulator, key) {
        var normalizedKey = String(key || '').trim();
        if (normalizedKey === '') {
            return accumulator;
        }

        var value = answer[key];
        accumulator[normalizedKey] = value === null || value === undefined ? '' : String(value);
        return accumulator;
    }, {});
}

function normalizeDropdownOptionAnswer(answer) {
    if (!answer || typeof answer !== 'object' || Array.isArray(answer)) {
        return {};
    }

    return Object.keys(answer).reduce(function (accumulator, key) {
        var normalizedKey = String(key || '').trim();
        var optionId = Number(answer[key]) || 0;
        if (normalizedKey !== '' && optionId > 0) {
            accumulator[normalizedKey] = optionId;
        }
        return accumulator;
    }, {});
}

function normalizeTableCompletionAnswer(answer) {
    if (!answer || typeof answer !== 'object' || Array.isArray(answer)) {
        return {};
    }

    return Object.keys(answer).reduce(function (accumulator, key) {
        var normalizedKey = String(key || '').trim().toUpperCase();
        if (normalizedKey === '') {
            return accumulator;
        }
        var value = answer[key];
        if (value === null || value === undefined) {
            return accumulator;
        }
        var textValue = String(value).trim();
        if (textValue === '') {
            return accumulator;
        }
        accumulator[normalizedKey] = /^\d+$/.test(textValue) ? Number(textValue) : textValue;
        return accumulator;
    }, {});
}

function htmlToPlainText(html) {
    var text = String(html || '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/gi, ' ');

    return text.replace(/\s+/g, ' ').trim();
}

export function createQuestionRenderManager(deps) {
    var escapeHtml = deps.escapeHtml;
    var isExamAnswerEditingLocked = deps.isExamAnswerEditingLocked;
    var renderExamRichHtml = typeof deps.renderExamRichHtml === 'function'
        ? deps.renderExamRichHtml
        : deps.safeRichHtml;
    var resolveStoredAnswerValueForQuestion = deps.resolveStoredAnswerValueForQuestion;
    var safeRichHtml = deps.safeRichHtml;

    function shortAnswerKeyToIndex(key) {
        var normalized = String(key || '').trim().toUpperCase();
        if (/^[1-8]$/.test(normalized)) {
            return Number(normalized);
        }
        if (/^[A-H]$/.test(normalized)) {
            return normalized.charCodeAt(0) - 64;
        }
        return 0;
    }

    function hasShortAnswerPlaceholder(questionText) {
        return /\[\s*input(?:\s*[_-]?\s*)?([a-h1-8])\s*\]/i.test(String(questionText || ''));
    }

    function buildShortAnswerPlaceholderPattern(token) {
        var escapedToken = String(token || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return new RegExp('\\[\\s*input(?:\\s*[_-]?\\s*)?' + escapedToken + '\\s*\\]', 'ig');
    }

    function renderShortAnswerInlineField(questionId, key, value, instance, isFallback) {
        var safeQuestionId = Number(questionId) || 0;
        var safeKey = String(key || '').trim().toUpperCase();
        var safeInstance = Number(instance) || 1;
        var inputId = 'cbt_short_' + safeQuestionId + '_' + safeKey + '_' + safeInstance;
        var wrapperClass = 'cbt-short-inline-field' + (isFallback ? ' is-fallback' : '');
        var keyChip = isFallback ? ('<span class="cbt-short-inline-key">' + escapeHtml(safeKey) + '</span>') : '';
        var disabledAttr = isExamAnswerEditingLocked() ? ' disabled' : '';

        return [
            '<span class="' + wrapperClass + '">',
            keyChip,
            '<input',
            ' id="' + escapeHtml(inputId) + '"',
            ' class="cbt-input cbt-short-inline-input"',
            ' data-action="answer-short"',
            ' data-qid="' + escapeHtml(safeQuestionId) + '"',
            ' data-short-key="' + escapeHtml(safeKey) + '"',
            ' value="' + escapeHtml(String(value || '')) + '"',
            ' aria-label="Input ' + escapeHtml(safeKey) + '"',
            ' placeholder="' + escapeHtml(safeKey) + '"',
            disabledAttr,
            ' />',
            '</span>'
        ].join('');
    }

    function renderShortAnswerStem(question) {
        var rawStem = String(question && question.question_text ? question.question_text : '');
        var questionId = Number(question && question.id) || 0;
        var keys = getShortAnswerKeys(question);
        var answer = resolveStoredAnswerValueForQuestion(question);
        var values = answer && typeof answer === 'object' ? answer : {};
        var inlineFieldCountByKey = {};
        var usedKeyMap = {};
        var hasInlinePlaceholders = hasShortAnswerPlaceholder(rawStem);
        var stemWithFields = rawStem;

        function nextFieldInstance(key) {
            var safeKey = String(key || '').trim().toUpperCase();
            var nextValue = Number(inlineFieldCountByKey[safeKey] || 0) + 1;
            inlineFieldCountByKey[safeKey] = nextValue;
            return nextValue;
        }

        function replacePlaceholder(pattern, key) {
            stemWithFields = stemWithFields.replace(pattern, function () {
                usedKeyMap[key] = true;
                return renderShortAnswerInlineField(
                    questionId,
                    key,
                    values[key],
                    nextFieldInstance(key),
                    false
                );
            });
        }

        keys.forEach(function (key) {
            replacePlaceholder(buildShortAnswerPlaceholderPattern(key), key);

            var keyIndex = shortAnswerKeyToIndex(key);
            if (keyIndex > 0) {
                replacePlaceholder(buildShortAnswerPlaceholderPattern(String(keyIndex)), key);
            }
        });

        var stemMarkup = renderExamRichHtml(stemWithFields, {
            context: 'question'
        });
        var missingKeys = keys.filter(function (key) {
            return !usedKeyMap[key];
        });

        if (!hasInlinePlaceholders || missingKeys.length) {
            var fallbackKeys = hasInlinePlaceholders ? missingKeys : keys;
            var fallbackMarkup = fallbackKeys.map(function (key) {
                return renderShortAnswerInlineField(
                    questionId,
                    key,
                    values[key],
                    nextFieldInstance(key),
                    true
                );
            }).join('');

            if (fallbackMarkup !== '') {
                stemMarkup += '<div class="cbt-short-inline-fallback">' + fallbackMarkup + '</div>';
            }
        }

        return stemMarkup;
    }

    function clozeDropdownKeyToIndex(key) {
        var normalized = String(key || '').trim();
        return /^[1-8]$/.test(normalized) ? Number(normalized) : 0;
    }

    function hasClozeDropdownPlaceholder(questionText) {
        return /\[\s*dropdown(?:\s*[_-]?\s*)?([1-8])\s*\]/i.test(String(questionText || ''));
    }

    function buildClozeDropdownPlaceholderPattern(token) {
        var escapedToken = String(token || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return new RegExp('\\[\\s*dropdown(?:\\s*[_-]?\\s*)?' + escapedToken + '\\s*\\]', 'ig');
    }

    function renderDropdownOptionTags(options, selectedOptionId) {
        var safeSelectedOptionId = Number(selectedOptionId) || 0;
        return [
            '<option value=""></option>',
            options.map(function (option, index) {
                var optionId = Number(option && option.id) || 0;
                if (optionId <= 0) {
                    return '';
                }
                var selectedAttr = optionId === safeSelectedOptionId ? ' selected' : '';
                var label = htmlToPlainText(option.option_text || '');
                if (label === '') {
                    label = questionOptionKey(option, index);
                }
                return '<option value="' + escapeHtml(optionId) + '"' + selectedAttr + '>' + escapeHtml(label) + '</option>';
            }).join('')
        ].join('');
    }

    function getDropdownOptionLabel(option, index) {
        var label = htmlToPlainText(option && option.option_text ? option.option_text : '');
        if (label === '') {
            label = questionOptionKey(option, index);
        }
        return label;
    }

    function renderAnswerSelect(config) {
        config = config || {};
        var options = Array.isArray(config.options) ? config.options : [];
        var selectedOptionId = Number(config.selectedOptionId) || 0;
        var selectedLabel = '';
        var validOptions = [];
        options.forEach(function (option, index) {
            var optionId = Number(option && option.id) || 0;
            if (optionId <= 0) {
                return;
            }
            var label = getDropdownOptionLabel(option, index);
            if (optionId === selectedOptionId) {
                selectedLabel = label;
            }
            validOptions.push({
                id: optionId,
                label: label
            });
        });

        var disabledAttr = config.disabled ? ' disabled' : '';
        var nativeClass = 'cbt-input cbt-answer-select cbt-answer-select-native ' + String(config.selectClass || '');
        var buttonClass = 'cbt-answer-select-button' + (selectedLabel === '' ? ' is-empty' : '');
        var selectAttributes = [
            ' class="' + escapeHtml(nativeClass.trim()) + '"',
            ' data-action="' + escapeHtml(config.action || '') + '"',
            ' data-qid="' + escapeHtml(Number(config.questionId) || 0) + '"'
        ];
        if (config.keyAttr && config.keyValue !== undefined) {
            selectAttributes.push(' ' + escapeHtml(config.keyAttr) + '="' + escapeHtml(config.keyValue) + '"');
        }
        if (config.ariaLabel) {
            selectAttributes.push(' aria-label="' + escapeHtml(config.ariaLabel) + '"');
        }

        return [
            '<span class="cbt-answer-select-ui" data-cbt-answer-select="1">',
            '<select',
            selectAttributes.join(''),
            disabledAttr,
            ' tabindex="-1" aria-hidden="true">',
            renderDropdownOptionTags(options, selectedOptionId),
            '</select>',
            '<button type="button" class="' + escapeHtml(buttonClass) + '" data-action="answer-select-toggle" aria-haspopup="listbox" aria-expanded="false"' + disabledAttr + '>',
            '<span class="cbt-answer-select-value">' + escapeHtml(selectedLabel || 'Pilih jawaban') + '</span>',
            '<span class="cbt-answer-select-chevron" aria-hidden="true"></span>',
            '</button>',
            '<span class="cbt-answer-select-menu" role="listbox">',
            '<button type="button" class="cbt-answer-select-option is-placeholder' + (selectedOptionId <= 0 ? ' is-selected' : '') + '" data-action="answer-select-option" data-option-id="" role="option" aria-selected="' + (selectedOptionId <= 0 ? 'true' : 'false') + '">Pilih jawaban</button>',
            validOptions.map(function (option) {
                var selected = option.id === selectedOptionId;
                return '<button type="button" class="cbt-answer-select-option' + (selected ? ' is-selected' : '') + '" data-action="answer-select-option" data-option-id="' + escapeHtml(option.id) + '" role="option" aria-selected="' + (selected ? 'true' : 'false') + '">' + escapeHtml(option.label) + '</button>';
            }).join(''),
            '</span>',
            '</span>'
        ].join('');
    }

    function renderClozeDropdownInlineField(questionId, blank, value, instance, isFallback) {
        var safeQuestionId = Number(questionId) || 0;
        var safeKey = String(blank && blank.key ? blank.key : '').trim();
        var safeInstance = Number(instance) || 1;
        var wrapperClass = 'cbt-cloze-inline-field' + (isFallback ? ' is-fallback' : '');
        var keyChip = isFallback ? ('<span class="cbt-short-inline-key">' + escapeHtml(safeKey) + '</span>') : '';
        var selectedOptionId = Number(value) || 0;
        var options = Array.isArray(blank && blank.options) ? blank.options : [];

        return [
            '<span class="' + wrapperClass + '">',
            keyChip,
            renderAnswerSelect({
                action: 'answer-cloze-dropdown',
                ariaLabel: 'Dropdown ' + safeKey,
                disabled: isExamAnswerEditingLocked(),
                keyAttr: 'data-cloze-key',
                keyValue: safeKey,
                options: options,
                questionId: safeQuestionId,
                selectClass: 'cbt-cloze-inline-select',
                selectedOptionId: selectedOptionId
            }),
            '</span>'
        ].join('');
    }

    function renderClozeDropdownStem(question) {
        var rawStem = String(question && question.question_text ? question.question_text : '');
        var questionId = Number(question && question.id) || 0;
        var blanks = getClozeDropdownBlanks(question);
        var answer = normalizeDropdownOptionAnswer(resolveStoredAnswerValueForQuestion(question));
        var blanksByKey = {};
        var inlineFieldCountByKey = {};
        var usedKeyMap = {};
        var hasInlinePlaceholders = hasClozeDropdownPlaceholder(rawStem);
        var stemWithFields = rawStem;

        blanks.forEach(function (blank) {
            blanksByKey[String(blank.key || '').trim()] = blank;
        });

        function nextFieldInstance(key) {
            var safeKey = String(key || '').trim();
            var nextValue = Number(inlineFieldCountByKey[safeKey] || 0) + 1;
            inlineFieldCountByKey[safeKey] = nextValue;
            return nextValue;
        }

        function replacePlaceholder(pattern, key) {
            var blank = blanksByKey[key];
            if (!blank) {
                return;
            }
            stemWithFields = stemWithFields.replace(pattern, function () {
                usedKeyMap[key] = true;
                return renderClozeDropdownInlineField(
                    questionId,
                    blank,
                    answer[key],
                    nextFieldInstance(key),
                    false
                );
            });
        }

        blanks.forEach(function (blank) {
            var key = String(blank.key || '').trim();
            if (key === '') {
                return;
            }
            replacePlaceholder(buildClozeDropdownPlaceholderPattern(key), key);

            var keyIndex = clozeDropdownKeyToIndex(key);
            if (keyIndex > 0) {
                replacePlaceholder(buildClozeDropdownPlaceholderPattern(String(keyIndex)), key);
            }
        });

        var stemMarkup = renderExamRichHtml(stemWithFields, {
            context: 'question'
        });
        var missingBlanks = blanks.filter(function (blank) {
            return !usedKeyMap[String(blank.key || '').trim()];
        });

        if (!hasInlinePlaceholders || missingBlanks.length) {
            var fallbackBlanks = hasInlinePlaceholders ? missingBlanks : blanks;
            var fallbackMarkup = fallbackBlanks.map(function (blank) {
                var key = String(blank.key || '').trim();
                return renderClozeDropdownInlineField(
                    questionId,
                    blank,
                    answer[key],
                    nextFieldInstance(key),
                    true
                );
            }).join('');

            if (fallbackMarkup !== '') {
                stemMarkup += '<div class="cbt-short-inline-fallback">' + fallbackMarkup + '</div>';
            }
        }

        return stemMarkup;
    }

    function renderQuestionStem(question) {
        if (question && question.question_type === 'short_answer') {
            return renderShortAnswerStem(question);
        }
        if (question && question.question_type === 'cloze_dropdown') {
            return renderClozeDropdownStem(question);
        }
        return renderExamRichHtml(question && question.question_text ? question.question_text : '', {
            context: 'question'
        });
    }

    function renderQuestionInput(question) {
        var answer = resolveStoredAnswerValueForQuestion(question);
        var disabledAttr = isExamAnswerEditingLocked() ? ' disabled' : '';

        if (question.question_type === 'multiple_choice' || question.question_type === 'true_false') {
            var selectedId = Number(answer) || 0;
            return [
                '<div class="cbt-options">',
                (Array.isArray(question.options) ? question.options : []).map(function (option, index) {
                    var optionId = Number(option.id) || 0;
                    var checked = optionId === selectedId;
                    return [
                        '<label class="cbt-option' + (checked ? ' is-selected' : '') + '">',
                        '<div class="cbt-option-row">',
                        '<input type="radio" name="cbt_q_' + escapeHtml(question.id) + '" value="' + escapeHtml(optionId) + '" data-action="answer-single" data-qid="' + escapeHtml(question.id) + '" data-option-id="' + escapeHtml(optionId) + '"' + (checked ? ' checked' : '') + disabledAttr + ' />',
                        '<span class="cbt-option-key">' + escapeHtml(questionOptionKey(option, index)) + '</span>',
                        '<div class="cbt-option-label">' + renderExamRichHtml(option.option_text || '', {
                            context: 'option'
                        }) + '</div>',
                        '</div>',
                        '</label>'
                    ].join('');
                }).join(''),
                '</div>'
            ].join('');
        }

        if (question.question_type === 'multiple_answer') {
            var selected = Array.isArray(answer) ? answer.map(function (item) { return Number(item) || 0; }) : [];
            return [
                '<div class="cbt-choice-mode cbt-choice-mode--multi" role="note" aria-label="Instruksi multiple answer">',
                '<span class="cbt-choice-mode-badge">Multi Answer</span>',
                '<span class="cbt-choice-mode-text">Pilih satu atau lebih jawaban.</span>',
                '</div>',
                '<div class="cbt-options cbt-options--multi">',
                (Array.isArray(question.options) ? question.options : []).map(function (option, index) {
                    var optionId = Number(option.id) || 0;
                    var checked = selected.indexOf(optionId) >= 0;
                    return [
                        '<label class="cbt-option cbt-option--multi' + (checked ? ' is-selected' : '') + '">',
                        '<div class="cbt-option-row">',
                        '<input type="checkbox" value="' + escapeHtml(optionId) + '" data-action="answer-multi" data-qid="' + escapeHtml(question.id) + '" data-option-id="' + escapeHtml(optionId) + '"' + (checked ? ' checked' : '') + disabledAttr + ' />',
                        '<span class="cbt-option-key">' + escapeHtml(questionOptionKey(option, index)) + '</span>',
                        '<div class="cbt-option-label">' + renderExamRichHtml(option.option_text || '', {
                            context: 'option'
                        }) + '</div>',
                        '</div>',
                        '</label>'
                    ].join('');
                }).join(''),
                '</div>'
            ].join('');
        }

        if (question.question_type === 'ordering') {
            var optionLookup = {};
            var rawOptions = Array.isArray(question.options) ? question.options : [];
            rawOptions.forEach(function (option, index) {
                var optionId = Number(option && option.id) || 0;
                if (optionId <= 0) {
                    return;
                }
                optionLookup[optionId] = {
                    index: index,
                    option: option
                };
            });

            var orderedIds = [];
            var seenOrderingIds = {};
            if (Array.isArray(answer)) {
                answer.forEach(function (item) {
                    var optionId = Number(item) || 0;
                    if (optionId <= 0 || seenOrderingIds[optionId] || !optionLookup[optionId]) {
                        return;
                    }
                    seenOrderingIds[optionId] = true;
                    orderedIds.push(optionId);
                });
            }
            rawOptions.forEach(function (option) {
                var optionId = Number(option && option.id) || 0;
                if (optionId <= 0 || seenOrderingIds[optionId]) {
                    return;
                }
                seenOrderingIds[optionId] = true;
                orderedIds.push(optionId);
            });

            if (orderedIds.length < 2) {
                return '<p class="cbt-muted">Item ordering belum tersedia.</p>';
            }

            return [
                '<div class="cbt-ordering" data-cbt-ordering-list="1" data-qid="' + escapeHtml(question.id) + '">',
                orderedIds.map(function (optionId, positionIndex) {
                    var entry = optionLookup[optionId] || {};
                    var option = entry.option || {};
                    var upDisabled = positionIndex <= 0 || isExamAnswerEditingLocked();
                    var downDisabled = positionIndex >= orderedIds.length - 1 || isExamAnswerEditingLocked();
                    return [
                        '<div class="cbt-ordering-item" data-option-id="' + escapeHtml(optionId) + '">',
                        '<div class="cbt-ordering-position">' + escapeHtml(positionIndex + 1) + '</div>',
                        '<div class="cbt-ordering-content">',
                        '<div class="cbt-option-label">' + renderExamRichHtml(option.option_text || '', {
                            context: 'option'
                        }) + '</div>',
                        '</div>',
                        '<div class="cbt-ordering-controls">',
                        '<button type="button" class="cbt-ordering-btn" data-action="answer-ordering-move" data-qid="' + escapeHtml(question.id) + '" data-option-id="' + escapeHtml(optionId) + '" data-direction="up" aria-label="Naikkan item" title="Naikkan item"' + (upDisabled ? ' disabled' : '') + '><span class="cbt-ordering-btn-icon cbt-ordering-btn-icon-up" aria-hidden="true"></span><span class="cbt-visually-hidden">Naikkan item</span></button>',
                        '<button type="button" class="cbt-ordering-btn" data-action="answer-ordering-move" data-qid="' + escapeHtml(question.id) + '" data-option-id="' + escapeHtml(optionId) + '" data-direction="down" aria-label="Turunkan item" title="Turunkan item"' + (downDisabled ? ' disabled' : '') + '><span class="cbt-ordering-btn-icon cbt-ordering-btn-icon-down" aria-hidden="true"></span><span class="cbt-visually-hidden">Turunkan item</span></button>',
                        '</div>',
                        '</div>'
                    ].join('');
                }).join(''),
                '</div>'
            ].join('');
        }

        if (question.question_type === 'matching') {
            var matchingItems = getMatchingItems(question);
            var matchingAnswer = normalizeDropdownOptionAnswer(answer);
            var matchingOptions = Array.isArray(question.options) ? question.options : [];
            if (!matchingItems.length || !matchingOptions.length) {
                return '<p class="cbt-muted">Konfigurasi matching belum tersedia.</p>';
            }

            return [
                '<div class="cbt-matching-wrap">',
                matchingItems.map(function (item, index) {
                    var selectedOptionId = Number(matchingAnswer[item.key]) || 0;
                    return [
                        '<div class="cbt-matching-row">',
                        '<div class="cbt-matching-prompt">',
                        '<span class="cbt-matching-index">' + escapeHtml(index + 1) + '</span>',
                        '<div class="cbt-matching-prompt-text">' + renderExamRichHtml(item.text || '', {
                            context: 'question'
                        }) + '</div>',
                        '</div>',
                        '<div class="cbt-matching-choice">',
                        renderAnswerSelect({
                            action: 'answer-matching',
                            ariaLabel: 'Jawaban matching ' + String(index + 1),
                            disabled: isExamAnswerEditingLocked(),
                            keyAttr: 'data-matching-key',
                            keyValue: item.key,
                            options: matchingOptions,
                            questionId: question.id,
                            selectClass: 'cbt-matching-select',
                            selectedOptionId: selectedOptionId
                        }),
                        '</div>',
                        '</div>'
                    ].join('');
                }).join(''),
                '</div>'
            ].join('');
        }

        if (question.question_type === 'categorization') {
            var categorizationItems = getCategorizationItems(question);
            var categorizationAnswer = normalizeDropdownOptionAnswer(answer);
            var categorizationOptions = Array.isArray(question.options) ? question.options : [];
            if (!categorizationItems.length || !categorizationOptions.length) {
                return '<p class="cbt-muted">Konfigurasi categorization belum tersedia.</p>';
            }

            return [
                '<div class="cbt-matching-wrap cbt-categorization-wrap">',
                '<table class="cbt-matching-table cbt-categorization-table">',
                '<tbody>',
                categorizationItems.map(function (item, index) {
                    var selectedOptionId = Number(categorizationAnswer[item.key]) || 0;
                    return [
                        '<tr>',
                        '<td class="cbt-matching-prompt"><span class="cbt-option-key">' + escapeHtml(index + 1) + '.</span> <span>' + renderExamRichHtml(item.text || '', {
                            context: 'question'
                        }) + '</span></td>',
                        '<td class="cbt-matching-choice">',
                        renderAnswerSelect({
                            action: 'answer-categorization',
                            ariaLabel: 'Kategori item ' + String(index + 1),
                            disabled: isExamAnswerEditingLocked(),
                            keyAttr: 'data-categorization-key',
                            keyValue: item.key,
                            options: categorizationOptions,
                            questionId: question.id,
                            selectClass: 'cbt-matching-select',
                            selectedOptionId: selectedOptionId
                        }),
                        '</td>',
                        '</tr>'
                    ].join('');
                }).join(''),
                '</tbody>',
                '</table>',
                '</div>'
            ].join('');
        }

        if (question.question_type === 'table_completion') {
            var tableMeta = question.table_completion_meta || {};
            var tableRows = Math.max(1, Number(tableMeta.rows) || 0);
            var tableColumns = Math.max(1, Number(tableMeta.columns) || 0);
            var tableCells = getTableCompletionCells(question);
            var tableAnswer = normalizeTableCompletionAnswer(answer);
            var tableCellsByPosition = {};
            tableCells.forEach(function (cell) {
                if (cell.row > 0 && cell.column > 0) {
                    tableCellsByPosition[cell.row + ':' + cell.column] = cell;
                }
            });
            if (!tableRows || !tableColumns || !tableCells.length) {
                return '<p class="cbt-muted">Konfigurasi Table Completion belum tersedia.</p>';
            }

            var bodyRows = [];
            for (var row = 1; row <= tableRows; row += 1) {
                var cellsMarkup = [];
                for (var column = 1; column <= tableColumns; column += 1) {
                    var cell = tableCellsByPosition[row + ':' + column] || {
                        key: '',
                        type: 'static',
                        text: ''
                    };
                    if (cell.type === 'text') {
                        cellsMarkup.push([
                            '<td class="cbt-table-completion-cell is-answer is-text" data-table-cell-key="' + escapeHtml(cell.key) + '">',
                            '<div class="cbt-table-completion-cell-head">',
                            '<span class="cbt-table-completion-cell-key">' + escapeHtml(cell.key || '') + '</span>',
                            cell.text ? '<div class="cbt-table-completion-cell-label">' + renderExamRichHtml(cell.text || '', { context: 'question' }) + '</div>' : '',
                            '</div>',
                            '<input class="cbt-input cbt-table-completion-input" data-action="answer-table-completion-text" data-qid="' + escapeHtml(question.id) + '" data-table-key="' + escapeHtml(cell.key) + '" aria-label="Jawaban sel ' + escapeHtml(cell.key || '') + '" value="' + escapeHtml(String(tableAnswer[cell.key] || '')) + '"' + disabledAttr + ' />',
                            '</td>'
                        ].join(''));
                    } else if (cell.type === 'dropdown') {
                        cellsMarkup.push([
                            '<td class="cbt-table-completion-cell is-answer is-dropdown" data-table-cell-key="' + escapeHtml(cell.key) + '">',
                            '<div class="cbt-table-completion-cell-head">',
                            '<span class="cbt-table-completion-cell-key">' + escapeHtml(cell.key || '') + '</span>',
                            cell.text ? '<div class="cbt-table-completion-cell-label">' + renderExamRichHtml(cell.text || '', { context: 'question' }) + '</div>' : '',
                            '</div>',
                            renderAnswerSelect({
                                action: 'answer-table-completion-dropdown',
                                ariaLabel: 'Jawaban sel ' + String(cell.key || ''),
                                disabled: isExamAnswerEditingLocked(),
                                keyAttr: 'data-table-key',
                                keyValue: cell.key,
                                options: cell.options || [],
                                questionId: question.id,
                                selectClass: 'cbt-table-completion-select',
                                selectedOptionId: Number(tableAnswer[cell.key]) || 0
                            }),
                            '</td>'
                        ].join(''));
                    } else {
                        cellsMarkup.push('<td class="cbt-table-completion-cell is-static">' + '<div class="cbt-table-completion-static">' + renderExamRichHtml(cell.text || '', { context: 'question' }) + '</div></td>');
                    }
                }
                bodyRows.push('<tr>' + cellsMarkup.join('') + '</tr>');
            }

            return [
                '<div class="cbt-table-completion-wrap" role="region" aria-label="Table Completion">',
                '<table class="cbt-table-completion-table">',
                '<tbody>',
                bodyRows.join(''),
                '</tbody>',
                '</table>',
                '</div>'
            ].join('');
        }

        if (question.question_type === 'true_false_matrix') {
            var matrixItems = getTrueFalseMatrixItems(question);
            var matrixAnswer = normalizeTrueFalseMatrixAnswer(answer);
            if (!matrixItems.length) {
                return '<p class="cbt-muted">Konfigurasi pernyataan belum tersedia.</p>';
            }

            return [
                '<div class="cbt-tf-matrix-wrap">',
                '<table class="cbt-tf-matrix-table">',
                '<thead><tr><th>Pernyataan</th><th>Benar</th><th>Salah</th></tr></thead>',
                '<tbody>',
                matrixItems.map(function (item, index) {
                    var selectedValue = String(matrixAnswer[item.key] || '').toLowerCase();
                    var trueChecked = selectedValue === 'true';
                    var falseChecked = selectedValue === 'false';
                    var rowName = 'cbt_tfm_' + String(question.id) + '_' + String(item.key);

                    return [
                        '<tr>',
                        '<td class="cbt-tf-matrix-statement"><span class="cbt-option-key">' + escapeHtml(index + 1) + '.</span> <span>' + renderExamRichHtml(item.text || '', {
                            context: 'question'
                        }) + '</span></td>',
                        '<td class="cbt-tf-matrix-choice">',
                        '<label>',
                        '<input type="radio" name="' + escapeHtml(rowName) + '" data-action="answer-tf-matrix" data-qid="' + escapeHtml(question.id) + '" data-key="' + escapeHtml(item.key) + '" data-value="true"' + (trueChecked ? ' checked' : '') + disabledAttr + ' />',
                        '<span>Benar</span>',
                        '</label>',
                        '</td>',
                        '<td class="cbt-tf-matrix-choice">',
                        '<label>',
                        '<input type="radio" name="' + escapeHtml(rowName) + '" data-action="answer-tf-matrix" data-qid="' + escapeHtml(question.id) + '" data-key="' + escapeHtml(item.key) + '" data-value="false"' + (falseChecked ? ' checked' : '') + disabledAttr + ' />',
                        '<span>Salah</span>',
                        '</label>',
                        '</td>',
                        '</tr>'
                    ].join('');
                }).join(''),
                '</tbody>',
                '</table>',
                '</div>'
            ].join('');
        }

        if (question.question_type === 'short_answer' || question.question_type === 'cloze_dropdown') {
            return '';
        }

        var essayValue = String(answer || '');
        return '<textarea class="cbt-textarea" rows="8" data-action="answer-text" data-qid="' + escapeHtml(question.id) + '"' + disabledAttr + '>' + escapeHtml(essayValue) + '</textarea>';
    }

    return {
        renderQuestionInput: renderQuestionInput,
        renderQuestionStem: renderQuestionStem
    };
}
