export function createSessionHeartbeatManager(deps) {
    var apiRequest = deps.apiRequest;
    var applyAttemptTimerPayload = deps.applyAttemptTimerPayload;
    var diagnosticsManager = deps.diagnosticsManager;
    var getQuestionCount = deps.getQuestionCount;
    var normalizeQuestionRevision = deps.normalizeQuestionRevision;
    var questionRevisionEquals = deps.questionRevisionEquals;
    var refreshAttemptQuestionRevision = deps.refreshAttemptQuestionRevision;
    var sessionHeartbeatIntervalMs = deps.sessionHeartbeatIntervalMs;
    var setQuestionRevision = deps.setQuestionRevision;
    var state = deps.state;
    var windowRef = deps.windowRef;
    var recordTimeline = deps.recordTimeline;
    var recordActionTrail = deps.recordActionTrail;

    var heartbeatTimer = 0;
    var heartbeatInFlight = null;

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
    }

    function run() {
        if (!state.token || state.stage === 'login') {
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
            if (
                heartbeatAttemptId > 0
                && state.stage === 'exam'
                && Number(state.attemptId) === heartbeatAttemptId
            ) {
                var sessionRevision = normalizeQuestionRevision(
                    sessionPayload && sessionPayload.question_revision,
                    heartbeatExamId
                );
                var sessionQuestionCount = Math.max(0, Number(sessionPayload && sessionPayload.question_count) || 0);
                var localQuestionCount = Math.max(0, getQuestionCount());
                var shouldRefreshForCount = (
                    sessionQuestionCount > 0
                    && sessionQuestionCount !== localQuestionCount
                );

                if (
                    shouldRefreshForCount ||
                    (sessionRevision && state.questionRevision && !questionRevisionEquals(sessionRevision, state.questionRevision, heartbeatExamId))
                ) {
                    if (typeof recordTimeline === 'function') {
                        recordTimeline('heartbeat:refresh', 'Heartbeat memicu refresh revision soal.', {
                            attemptId: heartbeatAttemptId,
                            selectedExamId: heartbeatExamId,
                            stage: String(state.stage || ''),
                            reason: shouldRefreshForCount ? 'question-count' : 'question-revision'
                        });
                    }
                    return refreshAttemptQuestionRevision(sessionRevision, {
                        attemptId: heartbeatAttemptId,
                        examId: heartbeatExamId,
                        force: shouldRefreshForCount,
                        preferredIndex: state.currentIndex,
                        source: shouldRefreshForCount ? 'heartbeat-count' : 'heartbeat'
                    }).then(function () {
                        return sessionPayload;
                    });
                }

                if (sessionRevision && !state.questionRevision) {
                    setQuestionRevision(sessionRevision, heartbeatExamId);
                }

                applyAttemptTimerPayload(sessionPayload && sessionPayload.attempt_timer);
            }

            if (typeof recordTimeline === 'function') {
                recordTimeline('heartbeat:ok', 'Heartbeat session berhasil.', {
                    attemptId: heartbeatAttemptId,
                    selectedExamId: heartbeatExamId,
                    stage: String(state.stage || '')
                });
            }

            return sessionPayload;
        })
            .catch(function (error) {
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
