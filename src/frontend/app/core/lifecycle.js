export function createLifecycleManager(deps) {
    var documentRef = deps.documentRef;
    var windowRef = deps.windowRef;
    var state = deps.state;
    var fitLoginHeroSchoolName = deps.fitLoginHeroSchoolName;
    var flushAttemptUiStateSilently = deps.flushAttemptUiStateSilently;
    var flushPendingAnswerBatchSilently = deps.flushPendingAnswerBatchSilently;
    var getCompactViewportState = deps.getCompactViewportState;
    var isCompactViewport = deps.isCompactViewport;
    var logPageLeaveSecurityEvent = deps.logPageLeaveSecurityEvent;
    var persistCurrentQuestionCacheLocally = deps.persistCurrentQuestionCacheLocally;
    var recordActionTrail = deps.recordActionTrail;
    var render = deps.render;
    var runSessionHeartbeat = deps.runSessionHeartbeat;
    var scheduleNavigationGridLayout = deps.scheduleNavigationGridLayout;
    var setCompactViewportState = deps.setCompactViewportState;
    var setConnectionStatus = deps.setConnectionStatus;
    var triggerPendingSyncLifecycleRetry = deps.triggerPendingSyncLifecycleRetry;
    var cancelScheduledTabHiddenSecurityLog = deps.cancelScheduledTabHiddenSecurityLog;
    var cancelScheduledWindowBlurSecurityLog = deps.cancelScheduledWindowBlurSecurityLog;
    var scheduleTabHiddenSecurityLog = deps.scheduleTabHiddenSecurityLog;
    var scheduleWindowBlurSecurityLog = deps.scheduleWindowBlurSecurityLog;
    var isWindowBlurLoggingActiveForAttempt = deps.isWindowBlurLoggingActiveForAttempt;
    var reconnectBurstWindowMs = 1200;
    var lastReconnectRetryAt = 0;

    function hasRecentReconnectRetry() {
        return lastReconnectRetryAt > 0 && (Date.now() - lastReconnectRetryAt) < reconnectBurstWindowMs;
    }

    function markReconnectRetry() {
        lastReconnectRetryAt = Date.now();
    }

    function recordReconnectAction(kind, summary, meta) {
        if (typeof recordActionTrail !== 'function') {
            return;
        }

        recordActionTrail(kind, summary, Object.assign({
            connectionStatus: String(state.connectionStatus || 'online')
        }, meta || {}));
    }

    function mountLifecycleListeners() {
        windowRef.addEventListener('resize', function () {
            var nextCompactState = isCompactViewport();
            if (nextCompactState !== getCompactViewportState()) {
                setCompactViewportState(nextCompactState);
                render('viewport-resize', {
                    compact: nextCompactState
                });
                return;
            }
            fitLoginHeroSchoolName();
            scheduleNavigationGridLayout();
        });

        if (documentRef.fonts && documentRef.fonts.ready && typeof documentRef.fonts.ready.then === 'function') {
            documentRef.fonts.ready.then(function () {
                fitLoginHeroSchoolName();
            }).catch(function () {
                // Ignore font loading errors.
            });
        }

        documentRef.addEventListener('visibilitychange', function () {
            if (documentRef.visibilityState === 'visible') {
                cancelScheduledTabHiddenSecurityLog();
                cancelScheduledWindowBlurSecurityLog();
                if (!hasRecentReconnectRetry()) {
                    markReconnectRetry();
                    recordReconnectAction('reconnect:visible', 'Retry sync dipicu saat tab kembali terlihat.', {
                        source: 'visibilitychange'
                    });
                    triggerPendingSyncLifecycleRetry('visible', {
                        delayMs: 180
                    });
                    if (typeof runSessionHeartbeat === 'function') {
                        runSessionHeartbeat();
                    }
                }
                flushAttemptUiStateSilently();
            }

            if (documentRef.visibilityState === 'hidden') {
                cancelScheduledWindowBlurSecurityLog();
                scheduleTabHiddenSecurityLog();
                persistCurrentQuestionCacheLocally();
                flushPendingAnswerBatchSilently({
                    flushAll: true,
                    keepalive: true
                });
                flushAttemptUiStateSilently({
                    force: true,
                    keepalive: true
                });
            }
        });

        windowRef.addEventListener('blur', function () {
            if (!isWindowBlurLoggingActiveForAttempt()) {
                return;
            }
            if (documentRef.visibilityState === 'hidden') {
                return;
            }

            scheduleWindowBlurSecurityLog('blur');
            persistCurrentQuestionCacheLocally();
            flushPendingAnswerBatchSilently({
                flushAll: true,
                keepalive: true
            });
            flushAttemptUiStateSilently({
                keepalive: true
            });
        });

        windowRef.addEventListener('focus', function () {
            cancelScheduledWindowBlurSecurityLog();
            if (!hasRecentReconnectRetry()) {
                markReconnectRetry();
                recordReconnectAction('reconnect:focus', 'Retry sync dipicu saat window kembali fokus.', {
                    source: 'focus'
                });
                triggerPendingSyncLifecycleRetry('focus', {
                    delayMs: 180
                });
                if (typeof runSessionHeartbeat === 'function') {
                    runSessionHeartbeat();
                }
            }
            flushAttemptUiStateSilently();
        });

        windowRef.addEventListener('online', function () {
            var shouldDeferRenderUntilSyncSettles = state.stage === 'exam' && (Number(state.pendingSyncCount) || 0) > 0;
            var skipDuplicateRetry = hasRecentReconnectRetry();
            markReconnectRetry();
            recordReconnectAction('reconnect:online', 'Status koneksi kembali online.', {
                pendingSyncCount: Number(state.pendingSyncCount) || 0,
                skippedRetry: skipDuplicateRetry
            });
            setConnectionStatus('online', {
                render: !shouldDeferRenderUntilSyncSettles,
                immediate: true,
                resetBackoff: true,
                triggerRetry: !skipDuplicateRetry
            });
            if (!skipDuplicateRetry && typeof runSessionHeartbeat === 'function') {
                runSessionHeartbeat();
            }
            flushAttemptUiStateSilently();
        });

        windowRef.addEventListener('offline', function () {
            setConnectionStatus('offline', {
                render: true,
                triggerRetry: false
            });
            recordReconnectAction('connection:offline', 'Status koneksi berubah ke offline.', {});
        });

        windowRef.addEventListener('pagehide', function () {
            logPageLeaveSecurityEvent('pagehide');
            persistCurrentQuestionCacheLocally();
            flushPendingAnswerBatchSilently({
                flushAll: true,
                keepalive: true
            });
            flushAttemptUiStateSilently({
                force: true,
                keepalive: true
            });
        });

        windowRef.addEventListener('beforeunload', function () {
            logPageLeaveSecurityEvent('beforeunload');
            persistCurrentQuestionCacheLocally();
            flushPendingAnswerBatchSilently({
                flushAll: true,
                keepalive: true
            });
            flushAttemptUiStateSilently({
                force: true,
                keepalive: true
            });
        });
    }

    return {
        mountLifecycleListeners: mountLifecycleListeners
    };
}
