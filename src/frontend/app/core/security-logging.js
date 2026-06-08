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
    var pendingPageLeaveStorageKey = 'cbt_exam_frontend_pending_page_leave_v1';

    var securityEventLastSentAtByKey = {};
    var pageLeaveLoggedAttemptId = 0;
    var tabHiddenLogTimer = 0;
    var tabHiddenLogScheduledAttemptId = 0;
    var windowBlurLogTimer = 0;
    var windowBlurLogScheduledAttemptId = 0;
    var windowBlurBurstStartedAt = 0;
    var windowBlurBurstCount = 0;
    var WINDOW_BLUR_BURST_WINDOW_MS = 10000;

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
        windowBlurBurstStartedAt = 0;
        windowBlurBurstCount = 0;
        pageLeaveLoggedAttemptId = 0;
        securityEventLastSentAtByKey = {};
    }

    function getSessionStorage() {
        try {
            return windowRef && windowRef.sessionStorage ? windowRef.sessionStorage : null;
        } catch (error) {
            return null;
        }
    }

    function readPendingPageLeaveMarker() {
        var storage = getSessionStorage();
        var raw = '';
        var parsed = null;

        if (!storage) {
            return null;
        }

        try {
            raw = String(storage.getItem(pendingPageLeaveStorageKey) || '');
        } catch (error) {
            return null;
        }

        if (raw === '') {
            return null;
        }

        try {
            parsed = JSON.parse(raw);
        } catch (error) {
            return null;
        }

        if (!parsed || typeof parsed !== 'object') {
            return null;
        }

        return parsed;
    }

    function writePendingPageLeaveMarker(marker) {
        var storage = getSessionStorage();

        if (!storage || !marker || typeof marker !== 'object') {
            return;
        }

        try {
            storage.setItem(pendingPageLeaveStorageKey, JSON.stringify(marker));
        } catch (error) {
            // Ignore storage failures (private mode / blocked storage).
        }
    }

    function clearPendingPageLeaveMarker() {
        var storage = getSessionStorage();

        if (!storage) {
            return;
        }

        try {
            storage.removeItem(pendingPageLeaveStorageKey);
        } catch (error) {
            // Ignore storage failures (private mode / blocked storage).
        }
    }

    function detectNavigationType() {
        try {
            if (!windowRef || !windowRef.performance) {
                return '';
            }

            if (typeof windowRef.performance.getEntriesByType === 'function') {
                var entries = windowRef.performance.getEntriesByType('navigation');
                if (entries && entries[0] && typeof entries[0].type === 'string') {
                    return String(entries[0].type || '');
                }
            }

            if (windowRef.performance.navigation && Number(windowRef.performance.navigation.type) === 1) {
                return 'reload';
            }
        } catch (error) {
            return '';
        }

        return '';
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
            return Promise.resolve(false);
        }

        try {
            return fetchImpl(buildUrl('logout'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + authToken
                },
                keepalive: true
            });
        } catch (error) {
            return Promise.reject(error);
        }
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

    function noteWindowBlurBurst() {
        var now = Date.now();
        if (windowBlurBurstStartedAt <= 0 || (windowBlurBurstStartedAt + WINDOW_BLUR_BURST_WINDOW_MS) < now) {
            windowBlurBurstStartedAt = now;
            windowBlurBurstCount = 0;
        }

        windowBlurBurstCount += 1;

        return {
            count: windowBlurBurstCount,
            windowMs: Math.max(0, now - windowBlurBurstStartedAt)
        };
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
        var blurBurstMeta = noteWindowBlurBurst();
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
                blur_burst_count: Number(blurBurstMeta.count) || 1,
                blur_burst_window_ms: Number(blurBurstMeta.windowMs) || 0,
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
        writePendingPageLeaveMarker({
            attemptId: attemptId,
            recordedAt: Date.now(),
            source: String(source || 'pagehide'),
            href: windowRef && windowRef.location && windowRef.location.href
                ? String(windowRef.location.href)
                : ''
        });
        sendSecurityEventSilently('page_leave', {
            source: String(source || 'pagehide')
        }, {
            attemptId: attemptId,
            keepalive: true
        });
    }

    function reconcilePendingPageRefreshSecurityEvent() {
        var marker = readPendingPageLeaveMarker();
        var navigationType = detectNavigationType();
        var attemptId;
        var markerAttemptId;
        var markerAgeMs;

        if (!marker) {
            return false;
        }

        if (navigationType !== 'reload') {
            clearPendingPageLeaveMarker();
            return false;
        }

        attemptId = Number(state.attemptId) || 0;
        markerAttemptId = Number(marker.attemptId) || 0;
        markerAgeMs = Math.max(0, Date.now() - (Number(marker.recordedAt) || 0));

        if (
            !isSecurityLoggingActiveForAttempt()
            || attemptId <= 0
            || markerAttemptId <= 0
            || markerAttemptId !== attemptId
            || markerAgeMs > 30000
        ) {
            return false;
        }

        clearPendingPageLeaveMarker();

        return sendSecurityEventSilently('page_refresh', {
            source: 'reload_resume',
            unload_source: String(marker.source || 'pagehide'),
            navigation_type: navigationType,
            reload_delay_ms: markerAgeMs,
            previous_href: marker.href ? String(marker.href) : ''
        }, {
            attemptId: attemptId,
            keepalive: true,
            debounceMs: 500
        });
    }

    return {
        cancelScheduledTabHiddenSecurityLog: cancelScheduledTabHiddenSecurityLog,
        cancelScheduledWindowBlurSecurityLog: cancelScheduledWindowBlurSecurityLog,
        clearRuntimeState: clearRuntimeState,
        isWindowBlurLoggingActiveForAttempt: isWindowBlurLoggingActiveForAttempt,
        logPageLeaveSecurityEvent: logPageLeaveSecurityEvent,
        reconcilePendingPageRefreshSecurityEvent: reconcilePendingPageRefreshSecurityEvent,
        scheduleTabHiddenSecurityLog: scheduleTabHiddenSecurityLog,
        scheduleWindowBlurSecurityLog: scheduleWindowBlurSecurityLog,
        sendLogoutRequestSilently: sendLogoutRequestSilently,
        sendSecurityEventSilently: sendSecurityEventSilently
    };
}
