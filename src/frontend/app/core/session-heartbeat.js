export function createSessionHeartbeatManager(deps) {
    var apiRequest = deps.apiRequest;
    var applyAttemptTimerPayload = deps.applyAttemptTimerPayload;
    var clearCalculatorRuntimeState = deps.clearCalculatorRuntimeState;
    var diagnosticsManager = deps.diagnosticsManager;
    var documentRef = deps.documentRef;
    var getQuestionCount = deps.getQuestionCount;
    var isHeartbeatLostDetectionEnabled = typeof deps.isHeartbeatLostDetectionEnabled === 'function'
        ? deps.isHeartbeatLostDetectionEnabled
        : function () {
            return false;
        };
    var normalizeQuestionRevision = deps.normalizeQuestionRevision;
    var questionOrderSignatureEquals = deps.questionOrderSignatureEquals;
    var questionRevisionEquals = deps.questionRevisionEquals;
    var refreshAttemptQuestionRevision = deps.refreshAttemptQuestionRevision;
    var sessionHeartbeatIntervalMs = deps.sessionHeartbeatIntervalMs;
    var sendSecurityEventSilently = typeof deps.sendSecurityEventSilently === 'function'
        ? deps.sendSecurityEventSilently
        : function () {
            return false;
        };
    var setQuestionRevision = deps.setQuestionRevision;
    var state = deps.state;
    var windowRef = deps.windowRef;
    var recordTimeline = deps.recordTimeline;
    var recordActionTrail = deps.recordActionTrail;
    var render = deps.render;

    var heartbeatTimer = 0;
    var heartbeatInFlight = null;
    var CALCULATOR_DISABLED_NOTICE = 'Kalkulator dinonaktifkan oleh guru untuk exam ini.';
    var HEARTBEAT_LOST_THRESHOLD = 3;
    var consecutiveHeartbeatFailures = 0;
    var heartbeatLostAttemptId = 0;

    function recordActionTrailEntry(kind, summary, meta) {
        if (typeof recordActionTrail === 'function') {
            recordActionTrail(kind, summary, meta || {});
        }
    }

    function delay(ms) {
        var waitMs = Math.max(0, Number(ms) || 0);
        if (waitMs <= 0) {
            return Promise.resolve();
        }

        return new Promise(function (resolve) {
            windowRef.setTimeout(resolve, waitMs);
        });
    }

    function buildHeartbeatScenarioError(mode) {
        var normalizedMode = String(mode || 'timeout');
        var error = new Error(
            normalizedMode === 'fail-next'
                ? 'Scenario aktif: heartbeat gagal sekali.'
                : 'Scenario aktif: heartbeat timeout.'
        );
        error.status = 0;
        error.code = normalizedMode === 'fail-next'
            ? 'scenario_heartbeat_fail_next'
            : 'scenario_heartbeat_timeout';
        error.isNetworkError = true;
        return error;
    }

    function stop() {
        if (heartbeatTimer) {
            windowRef.clearInterval(heartbeatTimer);
            heartbeatTimer = 0;
        }
        heartbeatInFlight = null;
        resetHeartbeatLostState({
            render: false
        });
    }

    function hasDocumentFocus() {
        try {
            return documentRef && typeof documentRef.hasFocus === 'function'
                ? !!documentRef.hasFocus()
                : true;
        } catch (error) {
            return true;
        }
    }

    function isBrowserOffline() {
        return !!(windowRef && windowRef.navigator && windowRef.navigator.onLine === false);
    }

    function setHeartbeatLostState(nextActive, failureCount, lastErrorCode, options) {
        options = options || {};

        var normalizedActive = !!nextActive;
        var normalizedFailureCount = Math.max(0, Number(failureCount) || 0);
        var normalizedLastErrorCode = String(lastErrorCode || '').trim();
        var hasChanged = (
            !!state.heartbeatLostActive !== normalizedActive
            || Math.max(0, Number(state.heartbeatLostFailureCount) || 0) !== normalizedFailureCount
            || String(state.heartbeatLostLastErrorCode || '') !== normalizedLastErrorCode
        );

        state.heartbeatLostActive = normalizedActive;
        state.heartbeatLostFailureCount = normalizedFailureCount;
        state.heartbeatLostLastErrorCode = normalizedLastErrorCode;

        if (hasChanged && options.render === true && typeof render === 'function') {
            render('heartbeat-lost-state', {
                active: normalizedActive,
                attemptId: Number(state.attemptId) || 0,
                failureCount: normalizedFailureCount
            });
        }
    }

    function resetHeartbeatLostState(options) {
        options = options || {};

        var shouldRender = options.render === true && !!state.heartbeatLostActive;
        consecutiveHeartbeatFailures = 0;
        heartbeatLostAttemptId = 0;
        setHeartbeatLostState(false, 0, '', {
            render: shouldRender
        });
    }

    function isCountableHeartbeatFailure(error) {
        if (!error || typeof error !== 'object' || isBrowserOffline()) {
            return false;
        }

        var status = Number(error.status) || 0;
        var code = String(error.code || '').trim().toLowerCase();

        if (error.isNetworkError === true || status === 0) {
            return true;
        }

        return code.indexOf('timeout') >= 0
            || code === 'network_error'
            || code === 'failed_to_fetch';
    }

    function buildHeartbeatLostContext(error, failureCount) {
        return {
            source: 'session_heartbeat',
            failure_count: Math.max(0, Number(failureCount) || 0),
            last_error_code: error && error.code ? String(error.code) : '',
            visibility_state: String(documentRef && documentRef.visibilityState ? documentRef.visibilityState : ''),
            has_focus: hasDocumentFocus() ? 1 : 0,
            connection_status: String(state.connectionStatus || 'online')
        };
    }

    function recordHeartbeatLostFailure(attemptId, error) {
        var safeAttemptId = Number(attemptId) || 0;
        if (safeAttemptId <= 0) {
            return;
        }

        if (heartbeatLostAttemptId !== safeAttemptId) {
            consecutiveHeartbeatFailures = 0;
            heartbeatLostAttemptId = safeAttemptId;
            setHeartbeatLostState(false, 0, '', {
                render: false
            });
        }

        consecutiveHeartbeatFailures += 1;

        var errorCode = error && error.code ? String(error.code) : '';
        var wasActive = !!state.heartbeatLostActive;
        var nextActive = consecutiveHeartbeatFailures >= HEARTBEAT_LOST_THRESHOLD;
        var shouldRender = nextActive && !wasActive;

        setHeartbeatLostState(nextActive, consecutiveHeartbeatFailures, errorCode, {
            render: shouldRender
        });

        if (nextActive && !wasActive && isHeartbeatLostDetectionEnabled()) {
            sendSecurityEventSilently('heartbeat_lost', buildHeartbeatLostContext(error, consecutiveHeartbeatFailures), {
                attemptId: safeAttemptId,
                keepalive: true
            });
        }
    }

    function normalizeCalculatorAvailability(value) {
        return Number(value) !== 0 ? 1 : 0;
    }

    function syncCalculatorAvailabilityFromSession(sessionPayload) {
        if (state.stage !== 'exam') {
            return false;
        }

        var selectedExamId = Number(state.selectedExamId) || 0;
        if (selectedExamId <= 0) {
            return false;
        }

        var exams = Array.isArray(state.exams) ? state.exams : [];
        var examIndex = -1;
        for (var index = 0; index < exams.length; index++) {
            if (Number(exams[index] && exams[index].id) === selectedExamId) {
                examIndex = index;
                break;
            }
        }
        if (examIndex < 0) {
            return false;
        }

        var currentExam = exams[examIndex] && typeof exams[examIndex] === 'object' ? exams[examIndex] : {};
        var previousAvailability = normalizeCalculatorAvailability(
            currentExam.enable_calculator !== undefined ? currentExam.enable_calculator : 1
        );
        var nextAvailability = normalizeCalculatorAvailability(
            sessionPayload && sessionPayload.enable_calculator !== undefined ? sessionPayload.enable_calculator : previousAvailability
        );
        if (previousAvailability === nextAvailability && currentExam.enable_calculator !== undefined) {
            return false;
        }

        state.exams = exams.slice();
        state.exams[examIndex] = Object.assign({}, currentExam, {
            enable_calculator: nextAvailability
        });

        if (previousAvailability === 1 && nextAvailability === 0) {
            if (typeof clearCalculatorRuntimeState === 'function') {
                clearCalculatorRuntimeState();
            }
            state.notice = CALCULATOR_DISABLED_NOTICE;
        } else if (previousAvailability === 0 && nextAvailability === 1 && state.notice === CALCULATOR_DISABLED_NOTICE) {
            state.notice = '';
        }

        return true;
    }

    function run() {
        if (!state.token || state.stage === 'login') {
            resetHeartbeatLostState({
                render: false
            });
            return Promise.resolve(null);
        }

        if (heartbeatInFlight) {
            return heartbeatInFlight;
        }

        var heartbeatAttemptId = state.stage === 'exam' && state.attemptId > 0
            ? Number(state.attemptId) || 0
            : 0;
        var heartbeatExamId = Number(state.selectedExamId) || 0;
        if (typeof recordTimeline === 'function') {
            recordTimeline('heartbeat:start', 'Heartbeat session dijalankan.', {
                attemptId: heartbeatAttemptId,
                selectedExamId: heartbeatExamId,
                stage: String(state.stage || '')
            });
        }

        heartbeatInFlight = Promise.resolve().then(async function () {
            var heartbeatScenario = (
                diagnosticsManager
                && diagnosticsManager.enabled
                && typeof diagnosticsManager.getHeartbeatScenario === 'function'
            )
                ? String(diagnosticsManager.getHeartbeatScenario() || 'off')
                : 'off';

            if (heartbeatScenario === 'slow') {
                recordTimeline('heartbeat:delayed', 'Heartbeat diperlambat oleh scenario.', {
                    attemptId: heartbeatAttemptId,
                    selectedExamId: heartbeatExamId,
                    stage: String(state.stage || ''),
                    scenario: heartbeatScenario
                });
                recordActionTrailEntry('heartbeat:delayed', 'Heartbeat diperlambat oleh scenario.', {
                    scenario: heartbeatScenario
                });
                await delay(2500);
            } else if (heartbeatScenario === 'timeout') {
                recordTimeline('heartbeat:timeout', 'Heartbeat timeout oleh scenario.', {
                    attemptId: heartbeatAttemptId,
                    selectedExamId: heartbeatExamId,
                    stage: String(state.stage || ''),
                    scenario: heartbeatScenario
                });
                recordActionTrailEntry('heartbeat:timeout', 'Heartbeat timeout oleh scenario.', {
                    scenario: heartbeatScenario
                });
                await delay(5000);
                throw buildHeartbeatScenarioError('timeout');
            } else if (
                heartbeatScenario === 'fail-next'
                && diagnosticsManager
                && diagnosticsManager.enabled
                && typeof diagnosticsManager.consumeHeartbeatFailureOnce === 'function'
                && diagnosticsManager.consumeHeartbeatFailureOnce()
            ) {
                recordTimeline('heartbeat:error', 'Heartbeat gagal sekali oleh scenario.', {
                    attemptId: heartbeatAttemptId,
                    selectedExamId: heartbeatExamId,
                    stage: String(state.stage || ''),
                    scenario: heartbeatScenario
                });
                recordActionTrailEntry('heartbeat:error', 'Heartbeat gagal sekali oleh scenario.', {
                    scenario: heartbeatScenario
                });
                throw buildHeartbeatScenarioError('fail-next');
            }

            return apiRequest('session', {
                query: {
                    attempt_id: heartbeatAttemptId > 0 ? heartbeatAttemptId : null
                }
            });
        }).then(function (sessionPayload) {
            resetHeartbeatLostState({
                render: !!state.heartbeatLostActive
            });

            if (
                heartbeatAttemptId > 0
                && state.stage === 'exam'
                && Number(state.attemptId) === heartbeatAttemptId
            ) {
                var calculatorAvailabilityChanged = syncCalculatorAvailabilityFromSession(sessionPayload);
                var sessionRevision = normalizeQuestionRevision(
                    sessionPayload && sessionPayload.question_revision,
                    heartbeatExamId
                );
                var sessionQuestionOrderSignature = String(sessionPayload && sessionPayload.question_order_signature || '').trim();
                var sessionQuestionCount = Math.max(0, Number(sessionPayload && sessionPayload.question_count) || 0);
                var localQuestionCount = Math.max(0, getQuestionCount());
                var shouldRefreshForCount = (
                    sessionQuestionCount > 0
                    && sessionQuestionCount !== localQuestionCount
                );
                var shouldRefreshForOrderSignature = (
                    sessionQuestionOrderSignature !== ''
                    && !questionOrderSignatureEquals(sessionQuestionOrderSignature, state.questionOrderSignature)
                );

                if (
                    shouldRefreshForCount ||
                    shouldRefreshForOrderSignature ||
                    (sessionRevision && state.questionRevision && !questionRevisionEquals(sessionRevision, state.questionRevision, heartbeatExamId))
                ) {
                    var refreshReason = shouldRefreshForCount
                        ? 'question-count'
                        : (shouldRefreshForOrderSignature ? 'question-order-signature' : 'question-revision');
                    if (typeof recordTimeline === 'function') {
                        recordTimeline('heartbeat:refresh', 'Heartbeat memicu refresh revision soal.', {
                            attemptId: heartbeatAttemptId,
                            selectedExamId: heartbeatExamId,
                            stage: String(state.stage || ''),
                            reason: refreshReason
                        });
                    }
                    return refreshAttemptQuestionRevision(sessionRevision, {
                        attemptId: heartbeatAttemptId,
                        examId: heartbeatExamId,
                        expectedQuestionOrderSignature: sessionQuestionOrderSignature,
                        force: shouldRefreshForCount || shouldRefreshForOrderSignature,
                        preferredIndex: state.currentIndex,
                        source: shouldRefreshForCount
                            ? 'heartbeat-count'
                            : (shouldRefreshForOrderSignature ? 'heartbeat-order' : 'heartbeat')
                    }).then(function () {
                        return sessionPayload;
                    });
                }

                if (sessionRevision && !state.questionRevision) {
                    setQuestionRevision(sessionRevision, heartbeatExamId);
                }

                applyAttemptTimerPayload(sessionPayload && sessionPayload.attempt_timer);

                if (calculatorAvailabilityChanged && typeof render === 'function') {
                    render('heartbeat-calculator-availability', {
                        attemptId: heartbeatAttemptId,
                        selectedExamId: heartbeatExamId
                    });
                }
            }

            if (typeof recordTimeline === 'function') {
                recordTimeline('heartbeat:ok', 'Heartbeat session berhasil.', {
                    attemptId: heartbeatAttemptId,
                    selectedExamId: heartbeatExamId,
                    stage: String(state.stage || '')
                });
            }

            return sessionPayload;
        }).catch(function (error) {
            if (
                state.stage === 'exam'
                && heartbeatAttemptId > 0
                && isHeartbeatLostDetectionEnabled()
            ) {
                if (isCountableHeartbeatFailure(error)) {
                    recordHeartbeatLostFailure(heartbeatAttemptId, error);
                } else {
                    resetHeartbeatLostState({
                        render: !!state.heartbeatLostActive
                    });
                }
            } else {
                resetHeartbeatLostState({
                    render: false
                });
            }

            if (typeof recordTimeline === 'function') {
                recordTimeline('heartbeat:error', error instanceof Error ? error.message : 'Heartbeat gagal.', {
                    attemptId: heartbeatAttemptId,
                    selectedExamId: heartbeatExamId,
                    stage: String(state.stage || ''),
                    code: error && error.code ? String(error.code) : ''
                });
            }
            recordActionTrailEntry('heartbeat:error', error instanceof Error ? error.message : 'Heartbeat gagal.', {
                code: error && error.code ? String(error.code) : ''
            });
            return null;
        })
            .finally(function () {
                heartbeatInFlight = null;
            });

        return heartbeatInFlight;
    }

    function start(options) {
        options = options || {};

        if (!state.token || state.stage === 'login') {
            return;
        }

        if (!heartbeatTimer) {
            heartbeatTimer = windowRef.setInterval(function () {
                run();
            }, sessionHeartbeatIntervalMs);
        }

        if (options.immediate !== false) {
            run();
        }
    }

    return {
        run: run,
        start: start,
        stop: stop
    };
}
