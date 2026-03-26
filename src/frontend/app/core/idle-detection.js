export function createIdleDetectionManager(deps) {
    var documentRef = deps.documentRef;
    var getIdleThresholdSeconds = deps.getIdleThresholdSeconds;
    var getQuestionDisplayNumber = deps.getQuestionDisplayNumber;
    var isExamFullscreenBlockingActive = deps.isExamFullscreenBlockingActive;
    var isIdleDetectionEnabled = deps.isIdleDetectionEnabled;
    var isSecurityLoggingEnabled = deps.isSecurityLoggingEnabled;
    var pointerMoveThrottleMs = Math.max(250, Number(deps.pointerMoveThrottleMs) || 1200);
    var sendSecurityEventSilently = typeof deps.sendSecurityEventSilently === 'function'
        ? deps.sendSecurityEventSilently
        : function () {
            return false;
        };
    var state = deps.state;
    var windowRef = deps.windowRef;

    var idleTimerId = 0;
    var lastActivityAt = 0;
    var lastIdleReportedAt = 0;
    var lastPointerMoveActivityAt = 0;
    var listenersMounted = false;

    function clearIdleTimer() {
        if (idleTimerId) {
            windowRef.clearTimeout(idleTimerId);
        }
        idleTimerId = 0;
    }

    function getThresholdSeconds() {
        var threshold = Number(getIdleThresholdSeconds && getIdleThresholdSeconds());
        if (!Number.isFinite(threshold) || threshold <= 0) {
            return 300;
        }

        return Math.max(60, Math.floor(threshold));
    }

    function hasDocumentFocus() {
        if (!documentRef || typeof documentRef.hasFocus !== 'function') {
            return true;
        }

        try {
            return !!documentRef.hasFocus();
        } catch (error) {
            return true;
        }
    }

    function getVisibilityState() {
        return String(documentRef && documentRef.visibilityState ? documentRef.visibilityState : 'visible');
    }

    function shouldTrackIdle() {
        return !!isIdleDetectionEnabled()
            && !!isSecurityLoggingEnabled()
            && state.stage === 'exam'
            && (Number(state.attemptId) || 0) > 0
            && !state.isFinishing
            && !state.examLockedForPendingFinish
            && !state.finishConfirmOpen
            && !isExamFullscreenBlockingActive()
            && getVisibilityState() === 'visible'
            && hasDocumentFocus();
    }

    function buildIdleContext() {
        var currentIndex = Math.max(0, Number(state.currentIndex) || 0);
        var thresholdSeconds = getThresholdSeconds();
        var idleSeconds = thresholdSeconds;

        if (lastActivityAt > 0) {
            idleSeconds = Math.max(thresholdSeconds, Math.floor((Date.now() - lastActivityAt) / 1000));
        }

        var questionNumber = currentIndex + 1;
        if (typeof getQuestionDisplayNumber === 'function') {
            questionNumber = Number(getQuestionDisplayNumber(currentIndex)) || questionNumber;
        }

        return {
            source: 'idle_timer',
            idle_seconds: idleSeconds,
            idle_threshold_seconds: thresholdSeconds,
            question_index: currentIndex,
            question_number: questionNumber,
            visibility_state: getVisibilityState(),
            has_focus: hasDocumentFocus() ? 1 : 0,
            pending_sync_count: Math.max(0, Number(state.pendingSyncCount) || 0)
        };
    }

    function emitIdleEvent() {
        idleTimerId = 0;

        if (!shouldTrackIdle()) {
            clearRuntimeState();
            return;
        }

        var thresholdMs = getThresholdSeconds() * 1000;
        var now = Date.now();
        var elapsedMs = lastActivityAt > 0 ? Math.max(0, now - lastActivityAt) : 0;
        if (elapsedMs < thresholdMs) {
            scheduleIdleTimer();
            return;
        }

        if (lastIdleReportedAt > 0 && (now - lastIdleReportedAt) < thresholdMs) {
            scheduleIdleTimer();
            return;
        }

        sendSecurityEventSilently('idle_detected', buildIdleContext(), {
            attemptId: Number(state.attemptId) || 0,
            keepalive: true,
            debounceMs: getThresholdSeconds() * 1000
        });
        lastIdleReportedAt = now;
        scheduleIdleTimer();
    }

    function scheduleIdleTimer() {
        clearIdleTimer();

        if (!shouldTrackIdle()) {
            return;
        }

        var thresholdMs = getThresholdSeconds() * 1000;
        if (lastActivityAt <= 0) {
            lastActivityAt = Date.now();
        }

        var now = Date.now();
        var elapsedMs = Math.max(0, now - lastActivityAt);
        var nextReferenceAt = lastIdleReportedAt > 0 ? lastIdleReportedAt : lastActivityAt;
        var remainingMs = Math.max(0, (nextReferenceAt + thresholdMs) - now);
        if (lastIdleReportedAt <= 0 && elapsedMs < thresholdMs) {
            remainingMs = Math.max(0, thresholdMs - elapsedMs);
        }
        if (remainingMs <= 0) {
            emitIdleEvent();
            return;
        }

        idleTimerId = windowRef.setTimeout(emitIdleEvent, remainingMs);
    }

    function clearRuntimeState() {
        clearIdleTimer();
        lastActivityAt = 0;
        lastIdleReportedAt = 0;
        lastPointerMoveActivityAt = 0;
    }

    function syncState() {
        if (!shouldTrackIdle()) {
            clearRuntimeState();
            return;
        }

        if (lastActivityAt <= 0) {
            lastActivityAt = Date.now();
        }
        scheduleIdleTimer();
    }

    function markActivity(options) {
        options = options || {};
        var now = Date.now();

        if (options.throttledPointerMove) {
            if (lastPointerMoveActivityAt > 0 && (now - lastPointerMoveActivityAt) < pointerMoveThrottleMs) {
                return;
            }
            lastPointerMoveActivityAt = now;
        }

        if (!shouldTrackIdle()) {
            clearRuntimeState();
            return;
        }

        lastActivityAt = now;
        lastIdleReportedAt = 0;
        scheduleIdleTimer();
    }

    function mountIdleListeners() {
        if (listenersMounted) {
            return;
        }

        listenersMounted = true;

        documentRef.addEventListener('pointerdown', function () {
            markActivity();
        }, true);
        documentRef.addEventListener('pointermove', function () {
            markActivity({
                throttledPointerMove: true
            });
        }, true);
        documentRef.addEventListener('keydown', function () {
            markActivity();
        }, true);
        documentRef.addEventListener('wheel', function () {
            markActivity();
        }, true);
        documentRef.addEventListener('touchstart', function () {
            markActivity();
        }, true);
        documentRef.addEventListener('scroll', function () {
            markActivity();
        }, true);
        documentRef.addEventListener('visibilitychange', function () {
            if (getVisibilityState() === 'visible') {
                markActivity();
                return;
            }

            clearRuntimeState();
        });

        windowRef.addEventListener('focus', function () {
            markActivity();
        });
        windowRef.addEventListener('blur', function () {
            clearRuntimeState();
        });
        windowRef.addEventListener('scroll', function () {
            markActivity();
        }, true);
    }

    return {
        clearRuntimeState: clearRuntimeState,
        mountIdleListeners: mountIdleListeners,
        syncState: syncState
    };
}
