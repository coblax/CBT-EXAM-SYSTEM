export function createSecurityLoggingManager(deps) {
    var buildSecurityClientContext = deps.buildSecurityClientContext;
    var buildUrl = deps.buildUrl;
    var documentRef = deps.documentRef;
    var fetchImpl = deps.fetchImpl;
    var isExamFullscreenRequired = deps.isExamFullscreenRequired;
    var isSecurityLoggingActiveForAttempt = deps.isSecurityLoggingActiveForAttempt;
    var isSecurityLoggingEnabled = deps.isSecurityLoggingEnabled;
    var recordTimeline = deps.recordTimeline;
    var state = deps.state;
    var windowBlurLogDelayMs = deps.windowBlurLogDelayMs;
    var windowRef = deps.windowRef;

    var securityEventLastSentAtByKey = {};
    var pageLeaveLoggedAttemptId = 0;
    var tabHiddenLogTimer = 0;
    var tabHiddenLogScheduledAttemptId = 0;
    var windowBlurLogTimer = 0;
    var windowBlurLogScheduledAttemptId = 0;

    function clearRuntimeState() {
        if (tabHiddenLogTimer) {
            windowRef.clearTimeout(tabHiddenLogTimer);
        }
        if (windowBlurLogTimer) {
            windowRef.clearTimeout(windowBlurLogTimer);
        }
        tabHiddenLogTimer = 0;
        tabHiddenLogScheduledAttemptId = 0;
        windowBlurLogTimer = 0;
        windowBlurLogScheduledAttemptId = 0;
        pageLeaveLoggedAttemptId = 0;
        securityEventLastSentAtByKey = {};
    }

    function shouldThrottleSecurityEvent(eventType, attemptId, debounceMs) {
        var safeAttemptId = Number(attemptId) || 0;
        var safeDebounceMs = Math.max(0, Number(debounceMs) || 0);
        var throttleKey = String(eventType || '') + ':' + String(safeAttemptId);
        var now = Date.now();
        var lastSentAt = Number(securityEventLastSentAtByKey[throttleKey] || 0);

        if (safeDebounceMs > 0 && lastSentAt > 0 && (lastSentAt + safeDebounceMs) > now) {
            return true;
        }

        securityEventLastSentAtByKey[throttleKey] = now;
        return false;
    }

    function sendLogoutRequestSilently(token) {
        var authToken = String(token || '');
        if (authToken === '') {
            return;
        }

        fetchImpl(buildUrl('logout'), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + authToken
            },
            keepalive: true
        }).catch(function () {});
    }

    function sendSecurityEventSilently(eventType, context, options) {
        options = options || {};

        var safeEventType = String(eventType || '').trim();
        var attemptId = Number(options.attemptId !== undefined ? options.attemptId : state.attemptId) || 0;
        var stage = String(options.stage !== undefined ? options.stage : state.stage || '');
        var authToken = options.token !== undefined ? String(options.token || '') : String(state.token || '');
        var keepalive = !!options.keepalive;
        var debounceMs = Math.max(0, Number(options.debounceMs) || 0);
        var requireFullscreen = !!options.requireFullscreen;
        var payloadContext = buildSecurityClientContext(context && typeof context === 'object' ? context : {});

        if (safeEventType === '' || attemptId <= 0 || stage !== 'exam' || authToken === '' || !isSecurityLoggingEnabled()) {
            return false;
        }

        if (requireFullscreen && !isExamFullscreenRequired()) {
            return false;
        }

        if (shouldThrottleSecurityEvent(safeEventType, attemptId, debounceMs)) {
            return false;
        }

        try {
            fetchImpl(buildUrl('security_event'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + authToken
                },
                body: JSON.stringify({
                    attempt_id: attemptId,
                    event_type: safeEventType,
                    context: payloadContext
                }),
                keepalive: keepalive
            }).catch(function () {});
            if (typeof recordTimeline === 'function') {
                recordTimeline('security:event:sent', 'Security event terkirim.', {
                    attemptId: attemptId,
                    stage: stage,
                    eventType: safeEventType
                });
            }
            return true;
        } catch (error) {
            return false;
        }
    }

    function cancelScheduledTabHiddenSecurityLog() {
        if (tabHiddenLogTimer) {
            windowRef.clearTimeout(tabHiddenLogTimer);
        }
        tabHiddenLogTimer = 0;
        tabHiddenLogScheduledAttemptId = 0;
    }

    function cancelScheduledWindowBlurSecurityLog() {
        if (windowBlurLogTimer) {
            windowRef.clearTimeout(windowBlurLogTimer);
        }
        windowBlurLogTimer = 0;
        windowBlurLogScheduledAttemptId = 0;
    }

    function isWindowBlurLoggingActiveForAttempt() {
        return isSecurityLoggingActiveForAttempt() && !state.isFinishing;
    }

    function scheduleTabHiddenSecurityLog() {
        if (!isSecurityLoggingActiveForAttempt()) {
            return;
        }

        var attemptId = Number(state.attemptId) || 0;
        if (attemptId <= 0) {
            return;
        }

        cancelScheduledTabHiddenSecurityLog();
        tabHiddenLogScheduledAttemptId = attemptId;
        tabHiddenLogTimer = windowRef.setTimeout(function () {
            tabHiddenLogTimer = 0;
            tabHiddenLogScheduledAttemptId = 0;

            if (!isSecurityLoggingActiveForAttempt()) {
                return;
            }
            if (pageLeaveLoggedAttemptId === attemptId) {
                return;
            }
            if (documentRef.visibilityState !== 'hidden') {
                return;
            }

            sendSecurityEventSilently('tab_hidden', {
                source: 'visibilitychange',
                visibility_state: String(documentRef.visibilityState || '')
            }, {
                attemptId: attemptId,
                keepalive: true,
                debounceMs: 1500
            });
        }, 500);
    }

    function scheduleWindowBlurSecurityLog(source) {
        if (!isWindowBlurLoggingActiveForAttempt()) {
            return;
        }

        var attemptId = Number(state.attemptId) || 0;
        if (attemptId <= 0) {
            return;
        }

        cancelScheduledWindowBlurSecurityLog();
        windowBlurLogScheduledAttemptId = attemptId;
        windowBlurLogTimer = windowRef.setTimeout(function () {
            var hasFocus = typeof documentRef.hasFocus === 'function' ? documentRef.hasFocus() : true;
            windowBlurLogTimer = 0;
            windowBlurLogScheduledAttemptId = 0;

            if (!isWindowBlurLoggingActiveForAttempt()) {
                return;
            }
            if (pageLeaveLoggedAttemptId === attemptId) {
                return;
            }
            if (documentRef.visibilityState === 'hidden') {
                return;
            }
            if (hasFocus) {
                return;
            }

            sendSecurityEventSilently('window_blur', {
                source: String(source || 'blur'),
                visibility_state: String(documentRef.visibilityState || ''),
                has_focus: hasFocus ? 1 : 0
            }, {
                attemptId: attemptId,
                keepalive: true,
                debounceMs: 2500
            });
        }, windowBlurLogDelayMs);
    }

    function logPageLeaveSecurityEvent(source) {
        if (!isSecurityLoggingActiveForAttempt()) {
            return;
        }

        var attemptId = Number(state.attemptId) || 0;
        if (attemptId <= 0 || pageLeaveLoggedAttemptId === attemptId) {
            return;
        }

        pageLeaveLoggedAttemptId = attemptId;
        cancelScheduledTabHiddenSecurityLog();
        cancelScheduledWindowBlurSecurityLog();
        sendSecurityEventSilently('page_leave', {
            source: String(source || 'pagehide')
        }, {
            attemptId: attemptId,
            keepalive: true
        });
    }

    return {
        cancelScheduledTabHiddenSecurityLog: cancelScheduledTabHiddenSecurityLog,
        cancelScheduledWindowBlurSecurityLog: cancelScheduledWindowBlurSecurityLog,
        clearRuntimeState: clearRuntimeState,
        isWindowBlurLoggingActiveForAttempt: isWindowBlurLoggingActiveForAttempt,
        logPageLeaveSecurityEvent: logPageLeaveSecurityEvent,
        scheduleTabHiddenSecurityLog: scheduleTabHiddenSecurityLog,
        scheduleWindowBlurSecurityLog: scheduleWindowBlurSecurityLog,
        sendLogoutRequestSilently: sendLogoutRequestSilently,
        sendSecurityEventSilently: sendSecurityEventSilently
    };
}
