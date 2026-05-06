export function createReviewRenderer(deps) {
    var state = deps.state;
    var escapeHtml = deps.escapeHtml;
    var safeRichHtml = deps.safeRichHtml;
    var questionOptionKey = deps.questionOptionKey;
    var formatQuestionType = deps.formatQuestionType;
    var formatScoreValue = deps.formatScoreValue;

    function normalizeReviewValueList(value) {
        if (Array.isArray(value)) {
            return value.map(function (item) {
                return String(item || '').trim();
            }).filter(function (item) {
                return item !== '';
            });
        }

        var raw = String(value || '').trim();
        if (raw === '') {
            return [];
        }

        if (raw.charAt(0) === '[') {
            try {
                var decoded = JSON.parse(raw);
                if (Array.isArray(decoded)) {
                    return decoded.map(function (item) {
                        return String(item || '').trim();
                    }).filter(function (item) {
                        return item !== '';
                    });
                }
            } catch (error) {
                // Fall through and treat as plain text.
            }
        }

        return [raw];
    }

    function normalizeReviewValueListWithEmpty(value) {
        if (Array.isArray(value)) {
            return value.map(function (item) {
                if (item === null || item === undefined) {
                    return '';
                }
                return String(item).trim();
            });
        }

        var raw = String(value || '').trim();
        if (raw === '') {
            return [];
        }

        if (raw.charAt(0) === '[') {
            try {
                var decoded = JSON.parse(raw);
                if (Array.isArray(decoded)) {
                    return decoded.map(function (item) {
                        if (item === null || item === undefined) {
                            return '';
                        }
                        return String(item).trim();
                    });
                }
            } catch (error) {
                // Fall through and treat as plain text.
            }
        }

        return [raw];
    }

    function shortAnswerInputLabelFromToken(token) {
        var normalized = String(token || '').trim().toUpperCase();
        if (/^[1-8]$/.test(normalized)) {
            return 'INPUT_' + normalized;
        }
        if (/^[A-H]$/.test(normalized)) {
            return 'INPUT_' + String(normalized.charCodeAt(0) - 64);
        }
        return '';
    }

    function resolveShortAnswerReviewInputLabels(item, submittedValues, correctValues) {
        var labels = [];
        var seen = {};
        var explicitKeys = item && Array.isArray(item.short_answer_input_keys) ? item.short_answer_input_keys : [];

        if (explicitKeys.length) {
            explicitKeys.forEach(function (key) {
                var label = shortAnswerInputLabelFromToken(key);
                if (label === '' || seen[label]) {
                    return;
                }
                seen[label] = true;
                labels.push(label);
            });
        } else {
            var questionText = String(item && item.question_text ? item.question_text : '');
            var pattern = /\[\s*input(?:\s*[_-]?\s*)?([a-h1-8])\s*\]/ig;
            var match;
            while ((match = pattern.exec(questionText)) !== null) {
                var parsedLabel = shortAnswerInputLabelFromToken(match[1]);
                if (parsedLabel === '' || seen[parsedLabel]) {
                    continue;
                }
                seen[parsedLabel] = true;
                labels.push(parsedLabel);
            }
        }

        var slotCount = Math.max(labels.length, submittedValues.length, correctValues.length);
        if (slotCount <= 0) {
            slotCount = 1;
        }
        for (var i = labels.length + 1; i <= slotCount; i++) {
            labels.push('INPUT_' + i);
        }
        return labels;
    }

    function renderReviewLabeledChips(labels, values) {
        if (!Array.isArray(labels) || !labels.length) {
            return '<span class="cbt-review-empty">-</span>';
        }

        return labels.map(function (label, index) {
            var rawEntry = Array.isArray(values) ? values[index] : '';
            var rawValue = (rawEntry === null || rawEntry === undefined) ? '' : String(rawEntry).trim();
            var valueMarkup = rawValue !== ''
                ? ('<span class="cbt-review-chip-value">' + escapeHtml(rawValue) + '</span>')
                : '<span class="cbt-review-chip-value cbt-review-chip-value-empty">-</span>';

            return [
                '<span class="cbt-review-chip cbt-review-chip-labeled">',
                '<span class="cbt-review-chip-key">' + escapeHtml(label) + '</span>',
                valueMarkup,
                '</span>'
            ].join('');
        }).join('');
    }

    function renderReviewText(value) {
        var text = String(value || '').trim();
        if (text === '') {
            return '<span class="cbt-review-empty">-</span>';
        }
        return escapeHtml(text).replace(/\r?\n/g, '<br />');
    }

    function buildReviewPlainPreview(value, maxLength) {
        var text = String(value || '')
            .replace(/<[^>]*>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        if (text === '') {
            return '-';
        }

        var limit = Math.max(24, Number(maxLength) || 120);
        if (text.length <= limit) {
            return text;
        }

        return text.slice(0, limit - 1).trim() + '…';
    }

    function reviewStatusLabel(status) {
        status = String(status || '').trim().toLowerCase();
        if (status === 'incorrect') {
            status = 'wrong';
        }

        var map = {
            correct: 'Benar',
            wrong: 'Salah',
            graded: 'Sudah dinilai',
            unanswered: 'Belum dijawab',
            manual: 'Perlu penilaian guru'
        };
        return map[status] || 'Belum dijawab';
    }

    function reviewStatusClass(status) {
        status = String(status || '').trim().toLowerCase();
        if (status === 'incorrect') {
            status = 'wrong';
        }

        var map = {
            correct: 'is-correct',
            wrong: 'is-wrong',
            graded: 'is-graded',
            unanswered: 'is-unanswered',
            manual: 'is-manual'
        };
        return map[status] || 'is-unanswered';
    }

    function buildReviewItemContext(item) {
        var status = String(item && item.status ? item.status : 'unanswered');
        var questionType = String(item && item.question_type ? item.question_type : '');
        var options = item && Array.isArray(item.options) ? item.options : [];
        var points = Number(item && item.points !== undefined ? item.points : 0);
        var scoreAwarded = Number(item && item.score_awarded !== undefined ? item.score_awarded : 0);
        var explanationText = String(item && item.explanation ? item.explanation : '').trim();

        var answerMarkup = '';
        if (questionType === 'ordering') {
            var orderingRows = item && Array.isArray(item.ordering_rows) ? item.ordering_rows : [];
            if (!orderingRows.length) {
                answerMarkup = '<div class="cbt-review-text">Data urutan tidak tersedia.</div>';
            } else {
                answerMarkup = [
                    '<div class="cbt-review-ordering">',
                    '<table class="cbt-ordering-review-table">',
                    '<thead><tr><th>Posisi</th><th>Jawaban Anda</th><th>Kunci</th></tr></thead>',
                    '<tbody>',
                    orderingRows.map(function (row) {
                        var isMatch = Number(row && row.is_match) === 1;
                        var matchClass = isMatch ? ' is-match' : ' is-mismatch';
                        return [
                            '<tr>',
                            '<td class="cbt-ordering-review-position">' + escapeHtml(row && row.position ? row.position : '-') + '</td>',
                            '<td class="cbt-ordering-review-answer' + matchClass + '">' + safeRichHtml(row && row.submitted_text ? row.submitted_text : '') + '</td>',
                            '<td class="cbt-ordering-review-answer">' + safeRichHtml(row && row.correct_text ? row.correct_text : '') + '</td>',
                            '</tr>'
                        ].join('');
                    }).join(''),
                    '</tbody>',
                    '</table>',
                    '</div>'
                ].join('');
            }
        } else if (questionType === 'matching') {
            var matchingRows = item && Array.isArray(item.matching_rows) ? item.matching_rows : [];
            if (!matchingRows.length) {
                answerMarkup = '<div class="cbt-review-text">Data matching tidak tersedia.</div>';
            } else {
                answerMarkup = [
                    '<div class="cbt-review-ordering">',
                    '<table class="cbt-ordering-review-table">',
                    '<thead><tr><th>Item</th><th>Jawaban Anda</th><th>Kunci</th></tr></thead>',
                    '<tbody>',
                    matchingRows.map(function (row, index) {
                        var isMatch = Number(row && row.is_match) === 1;
                        var matchClass = isMatch ? ' is-match' : ' is-mismatch';
                        var prompt = row && row.prompt_text ? safeRichHtml(row.prompt_text) : ('Item ' + (index + 1));
                        return [
                            '<tr>',
                            '<td class="cbt-ordering-review-position">' + prompt + '</td>',
                            '<td class="cbt-ordering-review-answer' + matchClass + '">' + (row && row.submitted_text ? safeRichHtml(row.submitted_text) : '<span class="cbt-review-empty">-</span>') + '</td>',
                            '<td class="cbt-ordering-review-answer">' + (row && row.correct_text ? safeRichHtml(row.correct_text) : '<span class="cbt-review-empty">-</span>') + '</td>',
                            '</tr>'
                        ].join('');
                    }).join(''),
                    '</tbody>',
                    '</table>',
                    '</div>'
                ].join('');
            }
        } else if (questionType === 'cloze_dropdown') {
            var clozeRows = item && Array.isArray(item.cloze_dropdown_rows) ? item.cloze_dropdown_rows : [];
            if (!clozeRows.length) {
                answerMarkup = '<div class="cbt-review-text">Data dropdown tidak tersedia.</div>';
            } else {
                answerMarkup = [
                    '<div class="cbt-review-ordering">',
                    '<table class="cbt-ordering-review-table">',
                    '<thead><tr><th>Dropdown</th><th>Jawaban Anda</th><th>Kunci</th></tr></thead>',
                    '<tbody>',
                    clozeRows.map(function (row) {
                        var isMatch = Number(row && row.is_match) === 1;
                        var matchClass = isMatch ? ' is-match' : ' is-mismatch';
                        return [
                            '<tr>',
                            '<td class="cbt-ordering-review-position">' + escapeHtml(row && row.key ? ('Dropdown ' + row.key) : '-') + '</td>',
                            '<td class="cbt-ordering-review-answer' + matchClass + '">' + (row && row.submitted_text ? escapeHtml(row.submitted_text) : '<span class="cbt-review-empty">-</span>') + '</td>',
                            '<td class="cbt-ordering-review-answer">' + (row && row.correct_text ? escapeHtml(row.correct_text) : '<span class="cbt-review-empty">-</span>') + '</td>',
                            '</tr>'
                        ].join('');
                    }).join(''),
                    '</tbody>',
                    '</table>',
                    '</div>'
                ].join('');
            }
        } else if (questionType === 'categorization') {
            var categorizationRows = item && Array.isArray(item.categorization_rows) ? item.categorization_rows : [];
            if (!categorizationRows.length) {
                answerMarkup = '<div class="cbt-review-text">Data categorization tidak tersedia.</div>';
            } else {
                answerMarkup = [
                    '<div class="cbt-review-ordering">',
                    '<table class="cbt-ordering-review-table">',
                    '<thead><tr><th>Item</th><th>Jawaban Anda</th><th>Kunci</th></tr></thead>',
                    '<tbody>',
                    categorizationRows.map(function (row, index) {
                        var isMatch = Number(row && row.is_match) === 1;
                        var matchClass = isMatch ? ' is-match' : ' is-mismatch';
                        var itemText = row && row.item_text ? safeRichHtml(row.item_text) : ('Item ' + (index + 1));
                        return [
                            '<tr>',
                            '<td class="cbt-ordering-review-position">' + itemText + '</td>',
                            '<td class="cbt-ordering-review-answer' + matchClass + '">' + (row && row.submitted_text ? escapeHtml(row.submitted_text) : '<span class="cbt-review-empty">-</span>') + '</td>',
                            '<td class="cbt-ordering-review-answer">' + (row && row.correct_text ? escapeHtml(row.correct_text) : '<span class="cbt-review-empty">-</span>') + '</td>',
                            '</tr>'
                        ].join('');
                    }).join(''),
                    '</tbody>',
                    '</table>',
                    '</div>'
                ].join('');
            }
        } else if (questionType === 'table_completion') {
            var tableRows = item && Array.isArray(item.table_completion_rows) ? item.table_completion_rows : [];
            if (!tableRows.length) {
                answerMarkup = '<div class="cbt-review-text">Data Table Completion tidak tersedia.</div>';
            } else {
                answerMarkup = [
                    '<div class="cbt-review-table-completion" role="region" aria-label="Review Table Completion">',
                    '<table class="cbt-table-completion-review-table">',
                    '<thead><tr><th>Sel</th><th>Status</th><th>Jawaban Anda</th><th>Kunci</th></tr></thead>',
                    '<tbody>',
                    tableRows.map(function (row) {
                        var isMatch = Number(row && row.is_match) === 1;
                        var matchClass = isMatch ? ' is-match' : ' is-mismatch';
                        var cellType = row && row.cell_type ? String(row.cell_type) : '';
                        var statusText = isMatch ? 'Benar' : 'Salah';
                        return [
                            '<tr>',
                            '<td class="cbt-table-completion-review-cell-key" data-review-label="Sel"><span>' + escapeHtml(row && row.key ? row.key : '-') + '</span>' + (cellType ? '<small>' + escapeHtml(cellType) + '</small>' : '') + '</td>',
                            '<td class="cbt-table-completion-review-status' + matchClass + '" data-review-label="Status"><span>' + escapeHtml(statusText) + '</span></td>',
                            '<td class="cbt-table-completion-review-answer' + matchClass + '" data-review-label="Jawaban Anda">' + (row && row.submitted_text ? escapeHtml(row.submitted_text) : '<span class="cbt-review-empty">-</span>') + '</td>',
                            '<td class="cbt-table-completion-review-answer is-key" data-review-label="Kunci">' + (row && row.correct_text ? escapeHtml(row.correct_text) : '<span class="cbt-review-empty">-</span>') + '</td>',
                            '</tr>'
                        ].join('');
                    }).join(''),
                    '</tbody>',
                    '</table>',
                    '</div>'
                ].join('');
            }
        } else if (options.length > 0) {
            answerMarkup = [
                '<div class="cbt-review-options">',
                options.map(function (option, index) {
                    var optionId = Number(option && option.id) || 0;
                    var isSelected = Number(option && option.is_selected) === 1;
                    var isCorrect = Number(option && option.is_correct) === 1;
                    var optionClasses = ['cbt-review-option'];
                    if (isSelected) {
                        optionClasses.push('is-selected');
                    }
                    if (isCorrect) {
                        optionClasses.push('is-correct');
                    }

                    var badges = [];
                    if (isSelected) {
                        badges.push('<span class="cbt-review-badge cbt-review-badge-selected">Jawaban Anda</span>');
                    }
                    if (isCorrect) {
                        badges.push('<span class="cbt-review-badge cbt-review-badge-correct">Kunci</span>');
                    }

                    return [
                        '<div class="' + optionClasses.join(' ') + '" data-option-id="' + escapeHtml(optionId) + '">',
                        '<div class="cbt-review-option-main">',
                        '<span class="cbt-option-key">' + escapeHtml(questionOptionKey(option, index)) + '</span>',
                        '<div class="cbt-option-label">' + safeRichHtml(option && option.option_text ? option.option_text : '') + '</div>',
                        '</div>',
                        badges.length ? '<div class="cbt-review-option-badges">' + badges.join('') + '</div>' : '',
                        '</div>'
                    ].join('');
                }).join(''),
                '</div>'
            ].join('');
        } else if (questionType === 'true_false_matrix') {
            var tfMatrixRows = item && Array.isArray(item.true_false_matrix_rows) ? item.true_false_matrix_rows : [];
            if (!tfMatrixRows.length) {
                answerMarkup = '<div class="cbt-review-text">Data jawaban tidak tersedia.</div>';
            } else {
                answerMarkup = [
                    '<div class="cbt-review-tf-matrix">',
                    '<table class="cbt-tf-matrix-table cbt-tf-matrix-review">',
                    '<thead><tr><th>Pernyataan</th><th>Jawaban Anda</th><th>Kunci</th></tr></thead>',
                    '<tbody>',
                    tfMatrixRows.map(function (row, index) {
                        var submitted = String(row && row.submitted ? row.submitted : '-');
                        var correct = String(row && row.correct ? row.correct : '-');
                        var isMatch = Number(row && row.is_match) === 1;
                        var matchClass = isMatch ? ' is-match' : ' is-mismatch';
                        return [
                            '<tr>',
                            '<td class="cbt-tf-matrix-statement"><span class="cbt-option-key">' + escapeHtml(index + 1) + '.</span> ' + safeRichHtml(row && row.text ? row.text : '') + '</td>',
                            '<td class="cbt-tf-matrix-choice' + matchClass + '">' + escapeHtml(submitted) + '</td>',
                            '<td class="cbt-tf-matrix-choice">' + escapeHtml(correct) + '</td>',
                            '</tr>'
                        ].join('');
                    }).join(''),
                    '</tbody>',
                    '</table>',
                    '</div>'
                ].join('');
            }
        } else if (questionType === 'short_answer') {
            var submittedShort = normalizeReviewValueListWithEmpty(item && item.submitted_short_answers ? item.submitted_short_answers : []);
            if (!submittedShort.length) {
                submittedShort = normalizeReviewValueListWithEmpty(item && item.answer_text ? item.answer_text : '');
            }
            var correctShort = normalizeReviewValueListWithEmpty(item && item.correct_short_answers ? item.correct_short_answers : []);
            var shortInputLabels = resolveShortAnswerReviewInputLabels(item, submittedShort, correctShort);
            answerMarkup = [
                '<div class="cbt-review-short-answer">',
                '<div class="cbt-review-pair"><strong>Jawaban Anda:</strong><div class="cbt-review-chip-list">' + renderReviewLabeledChips(shortInputLabels, submittedShort) + '</div></div>',
                '<div class="cbt-review-pair"><strong>Kunci Jawaban:</strong><div class="cbt-review-chip-list">' + renderReviewLabeledChips(shortInputLabels, correctShort) + '</div></div>',
                '</div>'
            ].join('');
        } else if (questionType === 'essay') {
            var rubricText = String(item && item.essay_rubric ? item.essay_rubric : '').trim();
            answerMarkup = [
                '<div class="cbt-review-essay-answer">',
                '<div class="cbt-review-pair"><strong>Jawaban Anda:</strong><div class="cbt-review-text">' + renderReviewText(item && item.answer_text ? item.answer_text : '') + '</div></div>',
                '<div class="cbt-review-pair"><strong>Acuan/Rubrik:</strong><div class="cbt-review-text">' + (rubricText !== '' ? safeRichHtml(rubricText) : '<span class="cbt-review-empty">-</span>') + '</div></div>',
                '</div>'
            ].join('');
        } else {
            answerMarkup = [
                '<div class="cbt-review-essay-answer">',
                '<div class="cbt-review-pair"><strong>Jawaban Anda:</strong><div class="cbt-review-text">' + renderReviewText(item && item.answer_text ? item.answer_text : '') + '</div></div>',
                '</div>'
            ].join('');
        }

        return {
            answerMarkup: answerMarkup,
            explanationText: explanationText,
            points: points,
            questionPreview: buildReviewPlainPreview(item && item.question_text ? item.question_text : '', 132),
            questionType: questionType,
            scoreAwarded: scoreAwarded,
            status: status
        };
    }

    function renderReviewItemBody(context, item) {
        return [
            '<div class="cbt-review-question">' + safeRichHtml(item && item.question_text ? item.question_text : '') + '</div>',
            context.answerMarkup,
            context.explanationText !== '' ? ('<div class="cbt-review-explanation"><strong>Pembahasan:</strong> ' + safeRichHtml(context.explanationText) + '</div>') : ''
        ].join('');
    }

    function renderReviewItem(item) {
        var context = buildReviewItemContext(item);

        return [
            '<article class="cbt-review-item">',
            '<header class="cbt-review-item-head">',
            '<div class="cbt-review-item-main">',
            '<h4>Soal ' + escapeHtml(item && item.question_number ? item.question_number : '-') + '</h4>',
            '<p class="cbt-muted">' + escapeHtml(formatQuestionType(context.questionType)) + '</p>',
            '</div>',
            '<div class="cbt-review-item-status-group">',
            '<span class="cbt-review-status ' + reviewStatusClass(context.status) + '">' + escapeHtml(reviewStatusLabel(context.status)) + '</span>',
            '<small class="cbt-muted">Skor ' + escapeHtml(formatScoreValue(context.scoreAwarded)) + ' / ' + escapeHtml(formatScoreValue(context.points)) + '</small>',
            '</div>',
            '</header>',
            renderReviewItemBody(context, item),
            '</article>'
        ].join('');
    }

    function renderArchivedReviewItem(item) {
        var context = buildReviewItemContext(item);
        var questionNumber = item && item.question_number ? item.question_number : '-';

        return [
            '<article class="cbt-archived-review-card">',
            '<header class="cbt-archived-review-card-head">',
            '<div class="cbt-archived-review-card-main">',
            '<div class="cbt-archived-review-card-kicker">Soal ' + escapeHtml(questionNumber) + '</div>',
            '<div class="cbt-archived-review-card-title-row">',
            '<strong class="cbt-archived-review-card-type">' + escapeHtml(formatQuestionType(context.questionType)) + '</strong>',
            '<span class="cbt-archived-review-card-score">Skor ' + escapeHtml(formatScoreValue(context.scoreAwarded)) + ' / ' + escapeHtml(formatScoreValue(context.points)) + '</span>',
            '</div>',
            '</div>',
            '<div class="cbt-archived-review-card-side">',
            '<span class="cbt-review-status ' + reviewStatusClass(context.status) + '">' + escapeHtml(reviewStatusLabel(context.status)) + '</span>',
            '</div>',
            '</header>',
            '<div class="cbt-archived-review-card-note">Jawaban tetap tersimpan meski soal ini sudah tidak aktif di exam saat ini.</div>',
            '<div class="cbt-archived-review-card-content">',
            renderReviewItemBody(context, item),
            '</div>',
            '</article>'
        ].join('');
    }

    function renderResultReviewSection() {
        var reviewItems = state.result && Array.isArray(state.result.review_items) ? state.result.review_items : [];
        if (!reviewItems.length) {
            return '';
        }

        return [
            '<section class="cbt-card cbt-review-card">',
            '<h3 class="cbt-review-card-title">REVIEW JAWABAN</h3>',
            '<div class="cbt-review-list">',
            reviewItems.map(renderReviewItem).join(''),
            '</div>',
            '</section>'
        ].join('');
    }

    function renderArchivedReviewHistorySection() {
        var archivedItems = Array.isArray(state.archivedReviewItems) ? state.archivedReviewItems : [];
        if (!archivedItems.length) {
            return '';
        }

        return [
            '<details class="cbt-archived-review-section">',
            '<summary class="cbt-archived-review-summary"><span class="cbt-archived-review-summary-row"><span class="cbt-archived-review-summary-label">Soal Nonaktif</span><span class="cbt-archived-review-count">' + escapeHtml(archivedItems.length) + ' item</span><span class="cbt-archived-review-close" aria-hidden="true"><span class="cbt-archived-review-close-when-closed">Buka</span><span class="cbt-archived-review-close-when-open">Tutup</span></span></span></summary>',
            '<p class="cbt-archived-review-note">Gunakan panel ini untuk meninjau soal yang dihapus/nonaktif tanpa kehilangan jawaban lama. Semua riwayat ditampilkan langsung agar lebih cepat discan.</p>',
            '<div class="cbt-review-list cbt-archived-review-list">',
            archivedItems.map(function (item) {
                return renderArchivedReviewItem(item);
            }).join(''),
            '</div>',
            '</details>'
        ].join('');
    }

    return {
        renderArchivedReviewHistorySection: renderArchivedReviewHistorySection,
        renderResultReviewSection: renderResultReviewSection
    };
}
