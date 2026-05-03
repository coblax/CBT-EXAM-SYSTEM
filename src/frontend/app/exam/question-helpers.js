export function questionOptionKey(option, index) {
    var key = String(option && option.option_key ? option.option_key : '').trim();
    if (key) {
        return key;
    }
    var code = 65 + index;
    if (code >= 65 && code <= 90) {
        return String.fromCharCode(code);
    }
    return String(index + 1);
}

export function getShortAnswerKeys(question) {
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

export function getTrueFalseMatrixItems(question) {
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

export function getMatchingItems(question) {
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

export function getClozeDropdownBlanks(question) {
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

export function findQuestionOptionById(question, optionId) {
    var safeOptionId = Number(optionId) || 0;
    if (safeOptionId <= 0 || !question || !Array.isArray(question.options)) {
        return null;
    }

    for (var index = 0; index < question.options.length; index++) {
        var option = question.options[index];
        if (Number(option && option.id) === safeOptionId) {
            return option;
        }
    }

    return null;
}

export function findQuestionOptionByKey(question, optionKey) {
    var normalizedKey = String(optionKey || '').trim().toUpperCase();
    if (normalizedKey === '' || !question || !Array.isArray(question.options)) {
        return null;
    }

    for (var index = 0; index < question.options.length; index++) {
        var option = question.options[index];
        if (String(questionOptionKey(option, index) || '').trim().toUpperCase() === normalizedKey) {
            return option;
        }
    }

    return null;
}

export function findQuestionOptionKeyById(question, optionId) {
    var safeOptionId = Number(optionId) || 0;
    if (safeOptionId <= 0 || !question || !Array.isArray(question.options)) {
        return '';
    }

    for (var index = 0; index < question.options.length; index++) {
        var option = question.options[index];
        if (Number(option && option.id) === safeOptionId) {
            return String(questionOptionKey(option, index) || '').trim().toUpperCase();
        }
    }

    return '';
}

export function normalizeTrueFalseMatrixAnswer(answer) {
    if (!answer || typeof answer !== 'object' || Array.isArray(answer)) {
        return {};
    }

    var normalized = {};
    Object.keys(answer).sort(function (a, b) {
        return Number(a) - Number(b);
    }).forEach(function (key) {
        var keyText = String(key || '').trim();
        if (keyText === '') {
            return;
        }
        var valueText = String(answer[key] || '').trim().toLowerCase();
        if (valueText === 'true' || valueText === 'false') {
            normalized[keyText] = valueText;
        }
    });

    return normalized;
}

export function normalizeDropdownOptionAnswer(question, rawAnswer, mode) {
    var sourceAnswer = rawAnswer;
    if (typeof sourceAnswer === 'string') {
        var trimmed = sourceAnswer.trim();
        if (trimmed === '') {
            return {};
        }
        try {
            sourceAnswer = JSON.parse(trimmed);
        } catch (error) {
            return {};
        }
    }

    if (!sourceAnswer || typeof sourceAnswer !== 'object' || Array.isArray(sourceAnswer)) {
        return {};
    }

    var normalizedMode = String(mode || '').trim();
    var allowedKeys = {};
    var allowedOptionIdsByKey = {};

    if (normalizedMode === 'cloze_dropdown') {
        getClozeDropdownBlanks(question).forEach(function (blank) {
            var key = String(blank.key || '').trim();
            if (key === '') {
                return;
            }
            allowedKeys[key] = true;
            allowedOptionIdsByKey[key] = {};
            blank.options.forEach(function (option) {
                var optionId = Number(option && option.id) || 0;
                if (optionId > 0) {
                    allowedOptionIdsByKey[key][optionId] = true;
                }
            });
        });
    } else {
        getMatchingItems(question).forEach(function (item) {
            var key = String(item.key || '').trim();
            if (key !== '') {
                allowedKeys[key] = true;
            }
        });
        var matchingOptionIds = {};
        (Array.isArray(question && question.options) ? question.options : []).forEach(function (option) {
            var optionId = Number(option && option.id) || 0;
            if (optionId > 0) {
                matchingOptionIds[optionId] = true;
            }
        });
        Object.keys(allowedKeys).forEach(function (key) {
            allowedOptionIdsByKey[key] = matchingOptionIds;
        });
    }

    return Object.keys(sourceAnswer).sort(function (a, b) {
        if (/^\d+$/.test(a) && /^\d+$/.test(b)) {
            return Number(a) - Number(b);
        }
        return String(a).localeCompare(String(b), undefined, {
            numeric: true
        });
    }).reduce(function (accumulator, key) {
        var normalizedKey = String(key || '').trim();
        var optionId = Number(sourceAnswer[key]) || 0;
        if (normalizedKey === '' || optionId <= 0 || !allowedKeys[normalizedKey]) {
            return accumulator;
        }
        if (allowedOptionIdsByKey[normalizedKey] && !allowedOptionIdsByKey[normalizedKey][optionId]) {
            return accumulator;
        }
        accumulator[normalizedKey] = optionId;
        return accumulator;
    }, {});
}

export function normalizeAnswerValueForQuestion(question, rawAnswer, options) {
    options = options || {};

    if (!question) {
        return {
            hasValue: false,
            value: null
        };
    }

    var preserveText = !!options.preserveText;
    var questionType = String(question.question_type || '');

    if (questionType === 'multiple_choice' || questionType === 'true_false') {
        var selectedId = Number(rawAnswer) || 0;
        var selectedOption = findQuestionOptionById(question, selectedId);
        return {
            hasValue: !!selectedOption,
            value: selectedOption ? Number(selectedOption.id) || 0 : null
        };
    }

    if (questionType === 'multiple_answer' || questionType === 'ordering') {
        if (!Array.isArray(rawAnswer)) {
            return {
                hasValue: false,
                value: null
            };
        }

        var selectedOptionIds = [];
        var seenOptionIds = {};
        rawAnswer.forEach(function (item) {
            var option = findQuestionOptionById(question, item);
            var optionId = Number(option && option.id) || 0;
            if (optionId <= 0 || seenOptionIds[optionId]) {
                return;
            }
            seenOptionIds[optionId] = true;
            selectedOptionIds.push(optionId);
        });

        return {
            hasValue: selectedOptionIds.length > 0,
            value: selectedOptionIds.length ? selectedOptionIds : null
        };
    }

    if (questionType === 'true_false_matrix') {
        var matrixValue = normalizeTrueFalseMatrixAnswer(rawAnswer);
        var validMatrixKeys = getTrueFalseMatrixItems(question).reduce(function (accumulator, item) {
            accumulator[String(item.key || '').trim()] = true;
            return accumulator;
        }, {});
        var filteredMatrixValue = Object.keys(matrixValue).reduce(function (accumulator, key) {
            if (!Object.keys(validMatrixKeys).length || validMatrixKeys[key]) {
                accumulator[key] = matrixValue[key];
            }
            return accumulator;
        }, {});

        return {
            hasValue: Object.keys(filteredMatrixValue).length > 0,
            value: Object.keys(filteredMatrixValue).length ? filteredMatrixValue : null
        };
    }

    if (questionType === 'matching' || questionType === 'cloze_dropdown') {
        var dropdownValue = normalizeDropdownOptionAnswer(question, rawAnswer, questionType);
        return {
            hasValue: Object.keys(dropdownValue).length > 0,
            value: Object.keys(dropdownValue).length ? dropdownValue : null
        };
    }

    if (questionType === 'short_answer') {
        if (!rawAnswer || typeof rawAnswer !== 'object' || Array.isArray(rawAnswer)) {
            return {
                hasValue: false,
                value: null
            };
        }

        var allowedKeys = getShortAnswerKeys(question).reduce(function (accumulator, key) {
            accumulator[String(key || '').trim().toUpperCase()] = true;
            return accumulator;
        }, {});
        var shortAnswerValue = Object.keys(rawAnswer).reduce(function (accumulator, key) {
            var normalizedKey = String(key || '').trim().toUpperCase();
            if (normalizedKey === '' || !allowedKeys[normalizedKey]) {
                return accumulator;
            }

            var nextValue = rawAnswer[key] === undefined || rawAnswer[key] === null
                ? ''
                : String(rawAnswer[key]);
            if (nextValue.trim() === '') {
                return accumulator;
            }

            accumulator[normalizedKey] = preserveText ? nextValue : nextValue.trim();
            return accumulator;
        }, {});

        return {
            hasValue: Object.keys(shortAnswerValue).length > 0,
            value: Object.keys(shortAnswerValue).length ? shortAnswerValue : null
        };
    }

    var textValue = rawAnswer === undefined || rawAnswer === null ? '' : String(rawAnswer);
    if (textValue.trim() === '') {
        return {
            hasValue: false,
            value: null
        };
    }

    return {
        hasValue: true,
        value: preserveText ? textValue : textValue.trim()
    };
}
