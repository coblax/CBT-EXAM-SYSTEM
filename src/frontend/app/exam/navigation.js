export function createExamNavigationManager(deps) {
    var state = deps.state;
    var attemptUiStateNavigationSyncDelayMs = deps.attemptUiStateNavigationSyncDelayMs;
    var attemptUiStateSyncDelayMs = deps.attemptUiStateSyncDelayMs;
    var acknowledgeQuestionRevisionMarker = deps.acknowledgeQuestionRevisionMarker;
    var clampQuestionIndex = deps.clampQuestionIndex;
    var clearStickyQuestionRevisionNotice = deps.clearStickyQuestionRevisionNotice;
    var clearMessages = deps.clearMessages;
    var documentRef = deps.documentRef;
    var ensureQuestionWindowForIndex = deps.ensureQuestionWindowForIndex;
    var escapeHtml = deps.escapeHtml;
    var getNavigatorConnectionStatus = deps.getNavigatorConnectionStatus;
    var getQuestionAtIndex = deps.getQuestionAtIndex;
    var getQuestionById = deps.getQuestionById;
    var getQuestionCount = deps.getQuestionCount;
    var getQuestionDisplayNumber = typeof deps.getQuestionDisplayNumber === 'function'
        ? deps.getQuestionDisplayNumber
        : function (question, fallbackIndex) {
            var questionNumber = Number(question && question.question_number !== undefined ? question.question_number : 0) || 0;
            return questionNumber > 0 ? questionNumber : Math.max(1, Math.floor(Number(fallbackIndex) || 0) + 1);
        };
    var getQuestionIdAtIndex = deps.getQuestionIdAtIndex;
    var getCategorizationItems = typeof deps.getCategorizationItems === 'function' ? deps.getCategorizationItems : function () { return []; };
    var getClozeDropdownBlanks = typeof deps.getClozeDropdownBlanks === 'function' ? deps.getClozeDropdownBlanks : function () { return []; };
    var getMatchingItems = typeof deps.getMatchingItems === 'function' ? deps.getMatchingItems : function () { return []; };
    var getPendingSyncQuestionIds = typeof deps.getPendingSyncQuestionIds === 'function' ? deps.getPendingSyncQuestionIds : function () { return []; };
    var getShortAnswerKeys = deps.getShortAnswerKeys;
    var getTableCompletionCells = typeof deps.getTableCompletionCells === 'function' ? deps.getTableCompletionCells : function () { return []; };
    var getTrueFalseMatrixItems = deps.getTrueFalseMatrixItems;
    var hasUsableLocalAnswerForQuestion = deps.hasUsableLocalAnswerForQuestion;
    var isExamAnswerEditingLocked = deps.isExamAnswerEditingLocked;
    var isNetworkConnectivityError = deps.isNetworkConnectivityError;
    var isQuestionPayloadLoaded = deps.isQuestionPayloadLoaded;
    var navQuestionFilterAll = deps.navQuestionFilterAll;
    var navQuestionFilterAnswered = deps.navQuestionFilterAnswered;
    var navQuestionFilterDoubtful = deps.navQuestionFilterDoubtful;
    var navQuestionFilterUnanswered = deps.navQuestionFilterUnanswered;
    var navigationQuestionTypeBadgeConfig = deps.navigationQuestionTypeBadgeConfig;
    var normalizeDropdownOptionAnswer = typeof deps.normalizeDropdownOptionAnswer === 'function' ? deps.normalizeDropdownOptionAnswer : function () { return {}; };
    var normalizeTableCompletionAnswer = typeof deps.normalizeTableCompletionAnswer === 'function' ? deps.normalizeTableCompletionAnswer : function () { return {}; };
    var normalizeTrueFalseMatrixAnswer = deps.normalizeTrueFalseMatrixAnswer;
    var persistCurrentAttemptUiStateLocally = deps.persistCurrentAttemptUiStateLocally;
    var prefetchNextQuestionBatch = typeof deps.prefetchNextQuestionBatch === 'function' ? deps.prefetchNextQuestionBatch : function () { return Promise.resolve(null); };
    var prefetchNextQuestionWindow = typeof deps.prefetchNextQuestionWindow === 'function' ? deps.prefetchNextQuestionWindow : function () { return Promise.resolve(null); };
    var questionOptionKey = deps.questionOptionKey;
    var questionWindowSize = deps.questionWindowSize;
    var queueQuestionAnswer = deps.queueQuestionAnswer;
    var render = deps.render;
    var renderExamPartial = deps.renderExamPartial;
    var resetQuestionPrefetchIdleTimer = deps.resetQuestionPrefetchIdleTimer;
    var resolveStoredAnswerValueForQuestion = deps.resolveStoredAnswerValueForQuestion;
    var scheduleAttemptUiStateSync = deps.scheduleAttemptUiStateSync;
    var schedulePendingAnswerRetry = deps.schedulePendingAnswerRetry;
    var scheduleQuestionCachePersist = deps.scheduleQuestionCachePersist;
    var setActiveQuestionWindowForIndex = deps.setActiveQuestionWindowForIndex;
    var windowRef = (documentRef && documentRef.defaultView) || (typeof window !== 'undefined' ? window : globalThis);

    function waitForInteractiveNavigationPaint() {
        if (!windowRef) {
            return Promise.resolve();
        }

        if (windowRef.document && windowRef.document.visibilityState === 'hidden') {
            return new Promise(function (resolve) {
                if (typeof windowRef.setTimeout === 'function') {
                    windowRef.setTimeout(resolve, 0);
                    return;
                }
                resolve();
            });
        }

        if (typeof windowRef.requestAnimationFrame !== 'function') {
            return new Promise(function (resolve) {
                if (typeof windowRef.setTimeout === 'function') {
                    windowRef.setTimeout(resolve, 0);
                    return;
                }
                resolve();
            });
        }

        return new Promise(function (resolve) {
            windowRef.requestAnimationFrame(function () {
                windowRef.requestAnimationFrame(function () {
                    resolve();
                });
            });
        });
    }

    function scheduleQuestionPrefetchAfterNavigation() {
        prefetchNextQuestionWindow();
        prefetchNextQuestionBatch();
        resetQuestionPrefetchIdleTimer();
    }

    function isQuestionDoubtful(question) {
        if (!question) {
            return false;
        }
        return !!state.doubtful[question.id];
    }

    function countObjectKeys(value) {
        return value && typeof value === 'object' && !Array.isArray(value)
            ? Object.keys(value).length
            : 0;
    }

    function buildAnswerProgress(answered, total) {
        var safeTotal = Math.max(0, Number(total) || 0);
        var safeAnswered = Math.max(0, Number(answered) || 0);
        if (safeTotal > 0) {
            safeAnswered = Math.min(safeAnswered, safeTotal);
        }
        var status = 'empty';
        if (safeTotal > 0 && safeAnswered >= safeTotal) {
            status = 'complete';
        } else if (safeAnswered > 0) {
            status = 'partial';
        }

        return {
            answered: safeAnswered,
            total: safeTotal,
            label: safeTotal > 0 ? (String(safeAnswered) + '/' + String(safeTotal)) : '',
            status: status
        };
    }

    function resolveNavigationAnswer(question) {
        var questionId = Number(question && question.id) || 0;
        var hasLocalAnswer = hasUsableLocalAnswerForQuestion(questionId, question);
        return {
            answer: hasLocalAnswer ? state.answers[questionId] : resolveStoredAnswerValueForQuestion(question),
            hasLocalAnswer: hasLocalAnswer,
            questionId: questionId
        };
    }

    function getStructuredAnswerProgress(question, answer) {
        if (!question) {
            return null;
        }

        var type = String(question.question_type || '');
        if (type === 'short_answer') {
            var shortAnswerKeys = getShortAnswerKeys(question);
            var shortAnswerCount = answer && typeof answer === 'object' && !Array.isArray(answer)
                ? shortAnswerKeys.reduce(function (acc, key) {
                    return acc + (String(answer[key] || '').trim() !== '' ? 1 : 0);
                }, 0)
                : 0;
            return buildAnswerProgress(shortAnswerCount, shortAnswerKeys.length);
        }

        if (type === 'true_false_matrix') {
            var matrixItems = getTrueFalseMatrixItems(question);
            var matrixAnswer = normalizeTrueFalseMatrixAnswer(answer);
            var matrixAnsweredCount = matrixItems.reduce(function (acc, item) {
                var value = String(matrixAnswer[item.key] || '').trim().toLowerCase();
                return acc + ((value === 'true' || value === 'false') ? 1 : 0);
            }, 0);
            return buildAnswerProgress(matrixAnsweredCount, matrixItems.length);
        }

        if (type === 'matching') {
            var matchingItems = getMatchingItems(question);
            return buildAnswerProgress(countObjectKeys(normalizeDropdownOptionAnswer(question, answer, type)), matchingItems.length);
        }

        if (type === 'cloze_dropdown') {
            var clozeBlanks = getClozeDropdownBlanks(question);
            return buildAnswerProgress(countObjectKeys(normalizeDropdownOptionAnswer(question, answer, type)), clozeBlanks.length);
        }

        if (type === 'categorization') {
            var categorizationItems = getCategorizationItems(question);
            return buildAnswerProgress(countObjectKeys(normalizeDropdownOptionAnswer(question, answer, type)), categorizationItems.length);
        }

        if (type === 'table_completion') {
            var answerCells = getTableCompletionCells(question).filter(function (cell) {
                var cellType = String(cell && cell.type ? cell.type : 'static');
                var cellKey = String(cell && cell.key ? cell.key : '').trim();
                return cellKey !== '' && (cellType === 'text' || cellType === 'dropdown');
            });
            return buildAnswerProgress(countObjectKeys(normalizeTableCompletionAnswer(question, answer)), answerCells.length);
        }

        return null;
    }

    function isQuestionAnswered(question) {
        if (!question) {
            return false;
        }

        var resolvedAnswer = resolveNavigationAnswer(question);
        var structuredProgress = getStructuredAnswerProgress(question, resolvedAnswer.answer);

        if (structuredProgress) {
            if (structuredProgress.answered > 0) {
                return true;
            }
            if (!resolvedAnswer.hasLocalAnswer) {
                return !!(state.answeredQuestionLookup && state.answeredQuestionLookup[resolvedAnswer.questionId]);
            }
            return false;
        }

        if (!resolvedAnswer.hasLocalAnswer) {
            return !!(state.answeredQuestionLookup && state.answeredQuestionLookup[resolvedAnswer.questionId]);
        }

        var answer = resolvedAnswer.answer;

        if (question.question_type === 'multiple_choice' || question.question_type === 'true_false') {
            return Number(answer) > 0;
        }

        if (question.question_type === 'true_false_matrix') {
            var normalizedMatrixAnswer = normalizeTrueFalseMatrixAnswer(answer);
            return Object.keys(normalizedMatrixAnswer).some(function (key) {
                var value = String(normalizedMatrixAnswer[key] || '').trim().toLowerCase();
                return value === 'true' || value === 'false';
            });
        }

        if (question.question_type === 'multiple_answer') {
            return Array.isArray(answer) && answer.length > 0;
        }

        if (question.question_type === 'ordering') {
            return Array.isArray(answer) && answer.length > 1;
        }

        if (question.question_type === 'short_answer') {
            if (!answer || typeof answer !== 'object') {
                return false;
            }
            return Object.keys(answer).some(function (key) {
                return String(answer[key] || '').trim() !== '';
            });
        }

        return String(answer || '').trim() !== '';
    }

    function normalizeNavigationQuestionFilter(filter) {
        var normalizedFilter = String(filter || navQuestionFilterAll).trim().toLowerCase();
        if (
            normalizedFilter === navQuestionFilterAnswered
            || normalizedFilter === navQuestionFilterUnanswered
            || normalizedFilter === navQuestionFilterDoubtful
        ) {
            return normalizedFilter;
        }
        return navQuestionFilterAll;
    }

    function navigationQuestionFilterEmptyMessage(filter) {
        var normalizedFilter = normalizeNavigationQuestionFilter(filter);
        if (normalizedFilter === navQuestionFilterAnswered) {
            return 'Belum ada soal yang terjawab.';
        }
        if (normalizedFilter === navQuestionFilterUnanswered) {
            return 'Semua soal sudah terjawab.';
        }
        if (normalizedFilter === navQuestionFilterDoubtful) {
            return 'Belum ada soal yang ditandai ragu-ragu.';
        }
        return 'Belum ada soal yang bisa ditampilkan.';
    }

    function questionMatchesNavigationFilter(question, filter) {
        var normalizedFilter = normalizeNavigationQuestionFilter(filter);
        if (normalizedFilter === navQuestionFilterAll) {
            return true;
        }
        if (!question) {
            return false;
        }
        if (normalizedFilter === navQuestionFilterAnswered) {
            return isQuestionAnswered(question);
        }
        if (normalizedFilter === navQuestionFilterUnanswered) {
            return !isQuestionAnswered(question);
        }
        if (normalizedFilter === navQuestionFilterDoubtful) {
            return isQuestionDoubtful(question);
        }
        return true;
    }

    function getNavigationQuestionEntries(filter) {
        var navigationQuestionIds = Array.isArray(state.questionOrderIds) && state.questionOrderIds.length
            ? state.questionOrderIds
            : state.questions.map(function (question) { return Number(question && question.id) || 0; });

        return navigationQuestionIds.reduce(function (accumulator, questionId, index) {
            var question = getQuestionById(questionId);
            if (!question || !questionMatchesNavigationFilter(question, filter)) {
                return accumulator;
            }
            accumulator.push({
                questionId: questionId,
                index: index,
                question: question
            });
            return accumulator;
        }, []);
    }

    function getExamProgressSummary() {
        var totalQuestions = getQuestionCount();
        var answeredQuestions = 0;
        var doubtfulQuestions = 0;
        var unansweredQuestionItems = [];
        var doubtfulQuestionItems = [];
        var partialQuestionItems = [];
        var pendingSyncQuestionItems = [];

        var summaryQuestionIds = Array.isArray(state.questionOrderIds) && state.questionOrderIds.length
            ? state.questionOrderIds
            : (Array.isArray(state.questionManifest) && state.questionManifest.length
                ? state.questionManifest.map(function (question) { return Number(question && question.id) || 0; })
                : state.questions.map(function (question) { return Number(question && question.id) || 0; }));
        var pendingSyncQuestionLookup = {};
        var pendingSyncQuestionIds = getPendingSyncQuestionIds();
        (Array.isArray(pendingSyncQuestionIds) ? pendingSyncQuestionIds : []).forEach(function (questionId) {
            var safeQuestionId = Number(questionId) || 0;
            if (safeQuestionId > 0) {
                pendingSyncQuestionLookup[safeQuestionId] = true;
            }
        });

        summaryQuestionIds.forEach(function (questionId, questionIndex) {
            var question = getQuestionById(questionId);
            if (!question) {
                return;
            }
            var questionDisplayNumber = getQuestionDisplayNumber(question, questionIndex);
            var questionItem = {
                questionId: Number(question.id) || Number(questionId) || 0,
                index: questionIndex,
                number: questionDisplayNumber,
                label: String(questionDisplayNumber)
            };
            var resolvedAnswer = resolveNavigationAnswer(question);
            var answerProgress = getStructuredAnswerProgress(question, resolvedAnswer.answer);
            if (isQuestionAnswered(question)) {
                answeredQuestions += 1;
            } else {
                unansweredQuestionItems.push(questionItem);
            }
            if (answerProgress && answerProgress.status === 'partial') {
                partialQuestionItems.push(Object.assign({}, questionItem, {
                    progressLabel: answerProgress.label,
                    status: answerProgress.status
                }));
            }
            if (isQuestionDoubtful(question)) {
                doubtfulQuestions += 1;
                doubtfulQuestionItems.push(questionItem);
            }
            if (pendingSyncQuestionLookup[Number(question.id) || Number(questionId) || 0]) {
                pendingSyncQuestionItems.push(questionItem);
            }
        });

        var unansweredQuestions = Math.max(0, totalQuestions - answeredQuestions);
        var answeredPercentage = totalQuestions > 0 ? (answeredQuestions / totalQuestions) * 100 : 0;

        return {
            totalQuestions: totalQuestions,
            answeredQuestions: answeredQuestions,
            unansweredQuestions: unansweredQuestions,
            doubtfulQuestions: doubtfulQuestions,
            unansweredQuestionItems: unansweredQuestionItems,
            doubtfulQuestionItems: doubtfulQuestionItems,
            partialQuestionItems: partialQuestionItems,
            pendingSyncQuestionItems: pendingSyncQuestionItems,
            unansweredQuestionNumbers: unansweredQuestionItems.map(function (item) { return item.label; }),
            doubtfulQuestionNumbers: doubtfulQuestionItems.map(function (item) { return item.label; }),
            partialQuestionNumbers: partialQuestionItems.map(function (item) { return item.label; }),
            pendingSyncQuestionNumbers: pendingSyncQuestionItems.map(function (item) { return item.label; }),
            answeredPercentage: answeredPercentage
        };
    }

    function getNavigationAnswerKeys(question) {
        if (!question) {
            return [];
        }

        var type = String(question.question_type || '');
        var options = Array.isArray(question.options) ? question.options : [];
        var answer = resolveStoredAnswerValueForQuestion(question);
        var structuredProgress = getStructuredAnswerProgress(question, answer);

        if (type === 'multiple_choice') {
            var selectedOptionId = Number(answer) || 0;
            if (selectedOptionId <= 0) {
                return [];
            }

            for (var i = 0; i < options.length; i++) {
                var option = options[i];
                if (Number(option.id) === selectedOptionId) {
                    var singleKey = String(questionOptionKey(option, i) || '').toUpperCase();
                    return singleKey !== '' ? [singleKey] : [];
                }
            }

            return [];
        }

        if (type === 'true_false') {
            var selectedTrueFalseOptionId = Number(answer) || 0;
            if (selectedTrueFalseOptionId <= 0) {
                return [];
            }

            for (var tfIndex = 0; tfIndex < options.length; tfIndex++) {
                var trueFalseOption = options[tfIndex];
                if (Number(trueFalseOption && trueFalseOption.id) !== selectedTrueFalseOptionId) {
                    continue;
                }

                var rawText = String(trueFalseOption && trueFalseOption.option_text ? trueFalseOption.option_text : '')
                    .trim()
                    .toLowerCase();
                if (rawText === 'true' || rawText === '1' || rawText === 't' || rawText === 'benar' || rawText === 'ya' || rawText === 'yes') {
                    return ['TRUE'];
                }
                if (rawText === 'false' || rawText === '0' || rawText === 'f' || rawText === 'salah' || rawText === 'tidak' || rawText === 'no') {
                    return ['FALSE'];
                }

                var fallbackKey = String(questionOptionKey(trueFalseOption, tfIndex) || '').toUpperCase();
                return fallbackKey !== '' ? [fallbackKey] : [];
            }

            return [];
        }

        if (type === 'multiple_answer') {
            var selectedOptionIds = Array.isArray(answer)
                ? answer.map(function (item) { return Number(item) || 0; }).filter(function (item) { return item > 0; })
                : [];

            if (!selectedOptionIds.length) {
                return [];
            }

            var selectedLookup = {};
            selectedOptionIds.forEach(function (id) {
                selectedLookup[id] = true;
            });

            var keys = [];
            for (var j = 0; j < options.length; j++) {
                var multiOption = options[j];
                var multiOptionId = Number(multiOption && multiOption.id) || 0;
                if (multiOptionId <= 0 || !selectedLookup[multiOptionId]) {
                    continue;
                }
                var key = String(questionOptionKey(multiOption, j) || '').toUpperCase();
                if (key !== '' && keys.indexOf(key) < 0) {
                    keys.push(key);
                }
            }

            return keys;
        }

        if (type === 'ordering') {
            var orderingAnswerIds = Array.isArray(answer)
                ? answer.map(function (item) { return Number(item) || 0; }).filter(function (item) { return item > 0; })
                : [];
            if (orderingAnswerIds.length <= 1 || options.length <= 1) {
                return [];
            }
            return [String(orderingAnswerIds.length) + '/' + String(options.length)];
        }

        if (structuredProgress) {
            if (!structuredProgress || structuredProgress.total <= 0 || structuredProgress.answered <= 0) {
                return [];
            }
            return [structuredProgress.label];
        }

        return [];
    }

    function getNavigationAnswerBadgeStatus(question, keyText) {
        var answer = resolveStoredAnswerValueForQuestion(question);
        var structuredProgress = getStructuredAnswerProgress(question, answer);
        if (!structuredProgress || structuredProgress.total <= 0 || structuredProgress.answered <= 0) {
            return '';
        }
        return String(keyText || '') === structuredProgress.label ? structuredProgress.status : '';
    }

    function renderNavigationAnswerBadges(question) {
        var keys = getNavigationAnswerKeys(question);
        if (!keys.length) {
            return '';
        }

        var visibleKeys = [];
        if (keys.length <= 3) {
            visibleKeys = keys.slice(0);
        } else {
            visibleKeys = [keys[0], keys[1], ('+' + (keys.length - 2))];
        }

        return [
            '<span class="cbt-nav-answer-badges">',
            visibleKeys.map(function (key) {
                var keyText = String(key || '').trim();
                var badgeClass = 'cbt-nav-answer-badge' + (keyText.length > 2 ? ' is-long' : '');
                var badgeStatus = getNavigationAnswerBadgeStatus(question, keyText);
                if (badgeStatus === 'partial') {
                    badgeClass += ' is-partial';
                } else if (badgeStatus === 'complete') {
                    badgeClass += ' is-complete';
                }
                return '<span class="' + badgeClass + '">' + escapeHtml(keyText) + '</span>';
            }).join(''),
            '</span>'
        ].join('');
    }

    function renderNavigationQuestionTypeBadge(question) {
        var config = navigationQuestionTypeBadgeConfig(question && question.question_type);
        return '<span class="cbt-nav-type-badge ' + escapeHtml(config.className) + '">' + escapeHtml(config.code) + '</span>';
    }

    function renderNavigationPatch(regions, reason, meta, options) {
        if (typeof renderExamPartial === 'function') {
            var didPatch = renderExamPartial(regions, reason, meta);
            if (didPatch) {
                return true;
            }
        }

        render(reason, meta, options);
        return false;
    }

    async function goToQuestion(nextIndex) {
        if (state.busy || state.isFinishing) {
            return;
        }

        var questionCount = getQuestionCount();
        if (questionCount <= 0) {
            return;
        }

        var safeIndex = clampQuestionIndex(nextIndex);
        var currentQuestion = getQuestionAtIndex(state.currentIndex);
        var currentQuestionId = currentQuestion ? (Number(currentQuestion.id) || 0) : 0;

        var targetQuestionId = getQuestionIdAtIndex(safeIndex);
        var requiresWindowLoad = !isQuestionPayloadLoaded(targetQuestionId);
        if (requiresWindowLoad && getNavigatorConnectionStatus() === 'offline') {
            state.error = '';
            state.notice = 'Koneksi terputus. Soal tujuan belum tersimpan di perangkat ini.';
            renderNavigationPatch({
                notice: true
            }, 'navigation:offline-target', {
                nextIndex: safeIndex
            });
            return;
        }

        state.navigationRefreshing = true;

        if (requiresWindowLoad) {
            state.questionRegionRefreshing = true;
            renderNavigationPatch({
                question: true,
                navigation: true,
                notice: true
            }, 'navigation:question-loading', {
                nextIndex: safeIndex
            }, {
                immediate: true,
                skipPostRenderEffects: true
            });

            await waitForInteractiveNavigationPaint();

            if (currentQuestionId > 0 && queueQuestionAnswer(currentQuestion, { force: true })) {
                scheduleQuestionCachePersist(0);
                schedulePendingAnswerRetry('question-navigation', {
                    delayMs: 150
                });
            }

            try {
                await ensureQuestionWindowForIndex(safeIndex, {
                    includeExisting: 1,
                    limit: questionWindowSize
                });
            } catch (error) {
                state.navigationRefreshing = false;
                state.questionRegionRefreshing = false;
                if (isNetworkConnectivityError(error)) {
                    state.error = '';
                    state.notice = 'Koneksi terputus. Soal tujuan belum tersimpan di perangkat ini.';
                } else {
                    state.error = error instanceof Error ? error.message : 'Gagal memuat soal.';
                }
                renderNavigationPatch({
                    question: true,
                    notice: true
                }, 'navigation:question-load-error', {
                    nextIndex: safeIndex
                });
                return;
            }
        } else {
            state.questionRegionRefreshing = false;
            state.currentIndex = safeIndex;
            setActiveQuestionWindowForIndex(safeIndex, questionWindowSize);
            var didAcknowledgeLoadedRevisionMarker = typeof acknowledgeQuestionRevisionMarker === 'function'
                ? acknowledgeQuestionRevisionMarker(targetQuestionId, {
                    persist: false
                })
                : false;
            var didClearLoadedStickyRevisionNotice = typeof clearStickyQuestionRevisionNotice === 'function'
                ? clearStickyQuestionRevisionNotice()
                : false;
            if (state.notice === 'Koneksi terputus. Soal tujuan belum tersimpan di perangkat ini.') {
                state.notice = '';
            }
            renderNavigationPatch({
                navigation: true,
                notice: didClearLoadedStickyRevisionNotice || state.notice !== '',
                question: true
            }, 'navigation:question-transition', {
                nextIndex: safeIndex,
                requiresWindowLoad: false
            }, {
                immediate: true,
                skipPostRenderEffects: true
            });

            await waitForInteractiveNavigationPaint();

            if (currentQuestionId > 0 && queueQuestionAnswer(currentQuestion, { force: true })) {
                scheduleQuestionCachePersist(0);
                schedulePendingAnswerRetry('question-navigation', {
                    delayMs: 150
                });
            }

            persistCurrentAttemptUiStateLocally();
            if (didAcknowledgeLoadedRevisionMarker) {
                scheduleQuestionCachePersist(0);
            }
            scheduleAttemptUiStateSync(attemptUiStateNavigationSyncDelayMs);
            state.navigationRefreshing = false;
            renderNavigationPatch({
                navigation: true,
                notice: didClearLoadedStickyRevisionNotice || state.notice !== ''
            }, 'navigation:jump', {
                nextIndex: safeIndex,
                requiresWindowLoad: false
            });
            scheduleQuestionPrefetchAfterNavigation();
            return;
        }

        state.questionRegionRefreshing = false;
        state.currentIndex = safeIndex;
        setActiveQuestionWindowForIndex(safeIndex, questionWindowSize);
        var didAcknowledgeRevisionMarker = typeof acknowledgeQuestionRevisionMarker === 'function'
            ? acknowledgeQuestionRevisionMarker(targetQuestionId, {
                persist: false
            })
            : false;
        var didClearStickyRevisionNotice = typeof clearStickyQuestionRevisionNotice === 'function'
            ? clearStickyQuestionRevisionNotice()
            : false;
        if (state.notice === 'Koneksi terputus. Soal tujuan belum tersimpan di perangkat ini.') {
            state.notice = '';
        }
        persistCurrentAttemptUiStateLocally();
        if (didAcknowledgeRevisionMarker) {
            scheduleQuestionCachePersist(0);
        }
        scheduleAttemptUiStateSync(attemptUiStateNavigationSyncDelayMs);
        state.navigationRefreshing = false;
        renderNavigationPatch({
            navigation: true,
            notice: didClearStickyRevisionNotice || state.notice !== '',
            question: true
        }, 'navigation:jump', {
            nextIndex: safeIndex,
            requiresWindowLoad: requiresWindowLoad,
            revisionMarkerAcknowledged: didAcknowledgeRevisionMarker
        });
        scheduleQuestionPrefetchAfterNavigation();
    }

    function focusFinishReviewIssue(action) {
        var reviewFilter = action === 'finish-review-doubtful'
            ? navQuestionFilterDoubtful
            : navQuestionFilterUnanswered;
        var reviewEntries = [];
        var targetIndex = -1;

        if (action === 'finish-review-partial') {
            reviewFilter = navQuestionFilterAll;
            var partialQuestionItems = getExamProgressSummary().partialQuestionItems;
            targetIndex = partialQuestionItems.length ? clampQuestionIndex(partialQuestionItems[0].index) : -1;
        } else if (action === 'finish-review-pending-sync') {
            reviewFilter = navQuestionFilterAll;
            var pendingSyncQuestionItems = getExamProgressSummary().pendingSyncQuestionItems;
            targetIndex = pendingSyncQuestionItems.length ? clampQuestionIndex(pendingSyncQuestionItems[0].index) : -1;
        } else {
            reviewEntries = getNavigationQuestionEntries(reviewFilter);
            targetIndex = reviewEntries.length ? clampQuestionIndex(reviewEntries[0].index) : -1;
        }
        state.finishConfirmOpen = false;
        state.finishConfirmSummary = null;
        state.navQuestionFilter = reviewFilter;
        clearMessages();

        if (targetIndex < 0) {
            render('finish-review:empty-filter', {
                filter: reviewFilter,
                targetIndex: -1
            }, {
                immediate: true
            });
            return;
        }

        var targetQuestionId = getQuestionIdAtIndex(targetIndex);
        if (targetIndex === state.currentIndex && isQuestionPayloadLoaded(targetQuestionId)) {
            state.navigationRefreshing = false;
            state.questionRegionRefreshing = false;
            setActiveQuestionWindowForIndex(targetIndex, questionWindowSize);
            persistCurrentAttemptUiStateLocally();
            scheduleAttemptUiStateSync(attemptUiStateNavigationSyncDelayMs);
            render('finish-review:focus-current', {
                filter: reviewFilter,
                targetIndex: targetIndex
            }, {
                immediate: true
            });
            scheduleQuestionPrefetchAfterNavigation();
            return;
        }

        render('finish-review:focus-filter', {
            filter: reviewFilter,
            targetIndex: targetIndex
        }, {
            immediate: true
        });
        goToQuestion(targetIndex);
    }

    function shouldIgnoreArrowQuestionNavigation() {
        var activeElement = documentRef.activeElement;
        if (!(activeElement instanceof HTMLElement)) {
            return false;
        }

        if (activeElement.isContentEditable) {
            return true;
        }

        if (
            activeElement instanceof HTMLInputElement
            || activeElement instanceof HTMLTextAreaElement
            || activeElement instanceof HTMLSelectElement
        ) {
            return true;
        }

        return !!activeElement.closest('.cbt-calc-panel');
    }

    function handleAction(action, actionNode) {
        if (action === 'finish-review-unanswered' || action === 'finish-review-partial' || action === 'finish-review-doubtful' || action === 'finish-review-pending-sync') {
            focusFinishReviewIssue(action);
            return true;
        }

        if (action === 'prev') {
            goToQuestion(state.currentIndex - 1);
            return true;
        }

        if (action === 'next') {
            goToQuestion(state.currentIndex + 1);
            return true;
        }

        if (action === 'jump') {
            var index = Number(actionNode.getAttribute('data-index'));
            goToQuestion(index);
            return true;
        }

        if (action === 'filter-nav') {
            var requestedFilter = normalizeNavigationQuestionFilter(actionNode.getAttribute('data-filter'));
            var nextFilter = requestedFilter === normalizeNavigationQuestionFilter(state.navQuestionFilter)
                ? navQuestionFilterAll
                : requestedFilter;
            state.navQuestionFilter = nextFilter;

            if (nextFilter !== navQuestionFilterAll) {
                var filteredEntries = getNavigationQuestionEntries(nextFilter);
                var currentQuestionForFilter = getQuestionAtIndex(state.currentIndex);
                if (
                    filteredEntries.length
                    && !questionMatchesNavigationFilter(currentQuestionForFilter, nextFilter)
                ) {
                    goToQuestion(filteredEntries[0].index);
                    return true;
                }
            }

            renderNavigationPatch({
                navigation: true
            }, 'navigation:filter', {
                filter: nextFilter
            });
            return true;
        }

        if (action === 'toggle-doubtful') {
            if (isExamAnswerEditingLocked()) {
                return true;
            }

            var doubtfulQid = Number(actionNode.getAttribute('data-qid')) || 0;
            if (doubtfulQid > 0) {
                var hadVisibleMessages = !!(state.error || state.notice || state.success);
                state.doubtful[doubtfulQid] = !state.doubtful[doubtfulQid];
                if (!state.doubtful[doubtfulQid]) {
                    delete state.doubtful[doubtfulQid];
                }
                persistCurrentAttemptUiStateLocally();
                scheduleAttemptUiStateSync(attemptUiStateSyncDelayMs);
                clearMessages();
                var patchRegions = {
                    navigation: true,
                    questionHead: true,
                    questionQuickNav: true
                };
                if (hadVisibleMessages) {
                    patchRegions.notice = true;
                }
                renderNavigationPatch(patchRegions, 'navigation:toggle-doubtful', {
                    questionId: doubtfulQid
                });
            }
            return true;
        }

        return false;
    }

    function handleArrowNavigationKey(event) {
        if (
            state.stage !== 'exam'
            || state.finishConfirmOpen
            || state.richZoomModalOpen
            || state.userPhotoModalOpen
            || event.altKey
            || event.ctrlKey
            || event.metaKey
            || event.repeat
            || shouldIgnoreArrowQuestionNavigation()
        ) {
            return false;
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            goToQuestion(state.currentIndex - 1);
            return true;
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            goToQuestion(state.currentIndex + 1);
            return true;
        }

        return false;
    }

    return {
        getExamProgressSummary: getExamProgressSummary,
        getNavigationQuestionEntries: getNavigationQuestionEntries,
        goToQuestion: goToQuestion,
        handleAction: handleAction,
        handleArrowNavigationKey: handleArrowNavigationKey,
        isQuestionAnswered: isQuestionAnswered,
        navigationQuestionFilterEmptyMessage: navigationQuestionFilterEmptyMessage,
        normalizeNavigationQuestionFilter: normalizeNavigationQuestionFilter,
        questionMatchesNavigationFilter: questionMatchesNavigationFilter,
        renderNavigationAnswerBadges: renderNavigationAnswerBadges,
        renderNavigationQuestionTypeBadge: renderNavigationQuestionTypeBadge
    };
}
