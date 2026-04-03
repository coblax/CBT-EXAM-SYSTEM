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

export function createQuestionRenderManager(deps) {
    var escapeHtml = deps.escapeHtml;
    var isExamAnswerEditingLocked = deps.isExamAnswerEditingLocked;
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

        var stemMarkup = safeRichHtml(stemWithFields);
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

    function renderQuestionStem(question) {
        if (question && question.question_type === 'short_answer') {
            return renderShortAnswerStem(question);
        }
        return safeRichHtml(question && question.question_text ? question.question_text : '');
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
                        '<span class="cbt-option-row">',
                        '<input type="radio" name="cbt_q_' + escapeHtml(question.id) + '" value="' + escapeHtml(optionId) + '" data-action="answer-single" data-qid="' + escapeHtml(question.id) + '" data-option-id="' + escapeHtml(optionId) + '"' + (checked ? ' checked' : '') + disabledAttr + ' />',
                        '<span class="cbt-option-key">' + escapeHtml(questionOptionKey(option, index)) + '</span>',
                        '<span class="cbt-option-label">' + safeRichHtml(option.option_text || '') + '</span>',
                        '</span>',
                        '</label>'
                    ].join('');
                }).join(''),
                '</div>'
            ].join('');
        }

        if (question.question_type === 'multiple_answer') {
            var selected = Array.isArray(answer) ? answer.map(function (item) { return Number(item) || 0; }) : [];
            return [
                '<div class="cbt-options">',
                (Array.isArray(question.options) ? question.options : []).map(function (option, index) {
                    var optionId = Number(option.id) || 0;
                    var checked = selected.indexOf(optionId) >= 0;
                    return [
                        '<label class="cbt-option' + (checked ? ' is-selected' : '') + '">',
                        '<span class="cbt-option-row">',
                        '<input type="checkbox" value="' + escapeHtml(optionId) + '" data-action="answer-multi" data-qid="' + escapeHtml(question.id) + '" data-option-id="' + escapeHtml(optionId) + '"' + (checked ? ' checked' : '') + disabledAttr + ' />',
                        '<span class="cbt-option-key">' + escapeHtml(questionOptionKey(option, index)) + '</span>',
                        '<span class="cbt-option-label">' + safeRichHtml(option.option_text || '') + '</span>',
                        '</span>',
                        '</label>'
                    ].join('');
                }).join(''),
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
                        '<td class="cbt-tf-matrix-statement"><span class="cbt-option-key">' + escapeHtml(index + 1) + '.</span> <span>' + safeRichHtml(item.text || '') + '</span></td>',
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

        if (question.question_type === 'short_answer') {
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
