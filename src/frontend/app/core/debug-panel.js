export function createFrontendDebugManager(deps) {
    var config = deps.config || {};
    var diagnosticsManager = deps.diagnosticsManager;
    var documentRef = deps.documentRef;
    var root = deps.root;
    var state = deps.state;
    var windowRef = deps.windowRef;
    var enabled = Boolean(config.frontendDebugUi);
    var panel = null;
    var statusNode = null;
    var listNode = null;
    var toggleButton = null;
    var pauseButton = null;
    var developerButton = null;
    var forceOfflineButton = null;
    var logs = [];
    var maxLogs = 18;
    var styleId = 'cbt-frontend-debug-style';
    var minimizeStorageKey = 'cbt_frontend_debug_minimized';
    var isMinimized = false;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatTime(timestamp) {
        var date = new Date(timestamp);
        var hh = String(date.getHours()).padStart(2, '0');
        var mm = String(date.getMinutes()).padStart(2, '0');
        var ss = String(date.getSeconds()).padStart(2, '0');
        var ms = String(date.getMilliseconds()).padStart(3, '0');
        return hh + ':' + mm + ':' + ss + '.' + ms;
    }

    function describeElement(element) {
        if (!(element instanceof Element)) {
            return '-';
        }

        var text = element.tagName ? String(element.tagName).toLowerCase() : 'node';
        if (element.id) {
            text += '#' + String(element.id);
        }

        if (typeof element.className === 'string' && element.className.trim() !== '') {
            var classTokens = element.className.trim().split(/\s+/).slice(0, 3);
            if (classTokens.length) {
                text += '.' + classTokens.join('.');
            }
        }

        var action = element.getAttribute('data-action');
        if (action) {
            text += '[data-action="' + action + '"]';
        }

        return text;
    }

    function resolveEventElement(target) {
        if (target instanceof Element) {
            return target;
        }

        if (target && target.parentElement instanceof Element) {
            return target.parentElement;
        }

        return null;
    }

    function getStackFromPoint(event) {
        if (
            !event
            || typeof event.clientX !== 'number'
            || typeof event.clientY !== 'number'
            || !documentRef
            || typeof documentRef.elementsFromPoint !== 'function'
        ) {
            return [];
        }

        var elements = documentRef.elementsFromPoint(event.clientX, event.clientY);
        if (!Array.isArray(elements)) {
            return [];
        }

        return elements.slice(0, 6).map(function (element) {
            return describeElement(element);
        });
    }

    function readPersistedMinimizedState() {
        try {
            if (!windowRef || !windowRef.localStorage) {
                return false;
            }
            return windowRef.localStorage.getItem(minimizeStorageKey) === '1';
        } catch (error) {
            return false;
        }
    }

    function persistMinimizedState() {
        try {
            if (!windowRef || !windowRef.localStorage) {
                return;
            }
            windowRef.localStorage.setItem(minimizeStorageKey, isMinimized ? '1' : '0');
        } catch (error) {
            // Ignore storage failures in debug-only UI.
        }
    }

    function ensureStyle() {
        if (!enabled) {
            return;
        }

        if (documentRef.getElementById(styleId)) {
            return;
        }

        var style = documentRef.createElement('style');
        style.id = styleId;
        style.textContent = [
            '.cbt-debug-panel{position:fixed;right:12px;bottom:12px;z-index:20000;width:min(560px,calc(100vw - 24px));max-height:min(72vh,620px);display:grid;grid-template-rows:auto auto minmax(0,1fr);background:rgba(15,23,42,.94);color:#e2e8f0;border:1px solid rgba(148,163,184,.28);border-radius:14px;box-shadow:0 18px 38px rgba(2,6,23,.34);backdrop-filter:blur(10px);font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace;}',
            '.cbt-debug-panel.is-minimized{width:min(360px,calc(100vw - 24px));max-height:none;grid-template-rows:auto;}',
            '.cbt-debug-panel__head{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;padding:10px 12px;border-bottom:1px solid rgba(148,163,184,.18);}',
            '.cbt-debug-panel.is-minimized .cbt-debug-panel__head{border-bottom:0;}',
            '.cbt-debug-panel__title{font-weight:700;color:#f8fafc;}',
            '.cbt-debug-panel__controls{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px;}',
            '.cbt-debug-panel__btn{border:1px solid rgba(148,163,184,.28);background:rgba(30,41,59,.82);color:#e2e8f0;border-radius:8px;padding:4px 8px;font:inherit;cursor:pointer;}',
            '.cbt-debug-panel__btn.is-active{background:rgba(239,68,68,.18);border-color:rgba(248,113,113,.56);color:#fecaca;}',
            '.cbt-debug-panel__status{padding:8px 12px;border-bottom:1px solid rgba(148,163,184,.12);color:#cbd5e1;white-space:pre-wrap;}',
            '.cbt-debug-panel__list{margin:0;padding:0;list-style:none;overflow:auto;}',
            '.cbt-debug-panel.is-minimized .cbt-debug-panel__status,.cbt-debug-panel.is-minimized .cbt-debug-panel__list{display:none;}',
            '.cbt-debug-panel__item{padding:8px 12px;border-bottom:1px solid rgba(148,163,184,.08);}',
            '.cbt-debug-panel__item:last-child{border-bottom:0;}',
            '.cbt-debug-panel__kind{color:#93c5fd;font-weight:700;}',
            '.cbt-debug-panel__time{color:#94a3b8;}',
            '.cbt-debug-panel__meta{margin-top:4px;color:#cbd5e1;white-space:pre-wrap;word-break:break-word;}'
        ].join('');
        documentRef.head.appendChild(style);
    }

    function buildStatusText() {
        var lines = [
            'stage=' + String(state.stage || 'login'),
            'busy=' + String(Boolean(state.busy)),
            'opening=' + String(Boolean(state.isOpeningAttempt)),
            'attempt=' + String(Number(state.attemptId) || 0),
            'fullscreen=' + String(Boolean(state.isFullscreenActive))
        ];

        if (diagnosticsManager && diagnosticsManager.enabled) {
            lines.push('capture=' + (diagnosticsManager.isCapturePaused() ? 'paused' : 'live'));
            if (typeof diagnosticsManager.readScenarioState === 'function') {
                var scenarioState = diagnosticsManager.readScenarioState();
                lines.push('forced-offline=' + (scenarioState && scenarioState.forceOffline ? 'on' : 'off'));
                var scenarioSummary = buildScenarioSummaryLine(scenarioState);
                if (scenarioSummary !== '') {
                    lines.push('scenarios=' + scenarioSummary);
                }
            }
            if (typeof diagnosticsManager.readSyncSnapshot === 'function') {
                var syncSnapshot = diagnosticsManager.readSyncSnapshot();
                if (syncSnapshot && typeof syncSnapshot === 'object') {
                    var queueCount = Number(syncSnapshot.pendingSyncCount) || 0;
                    var retryOn = String(syncSnapshot.nextRetryDueAt || '') !== '';
                    lines.push('sync=' + (queueCount > 0 ? 'pending' : 'idle'));
                    lines.push('queue=' + String(queueCount));
                    lines.push('retry=' + (retryOn ? 'on' : 'off'));
                }
            }
            if (typeof diagnosticsManager.readTimeline === 'function') {
                lines.push('timeline=' + String((diagnosticsManager.readTimeline() || []).length));
            }
        }

        return lines.join(' | ');
    }

    function buildScenarioSummaryLine(scenarioState) {
        if (!scenarioState || typeof scenarioState !== 'object') {
            return '';
        }

        var tokens = [];
        if (scenarioState.forceOffline) {
            tokens.push('offline');
        }
        if ((Number(scenarioState.apiLatencyMs) || 0) > 0) {
            tokens.push('api+' + String(Number(scenarioState.apiLatencyMs) || 0) + 'ms');
        }
        if ((Number(scenarioState.questionWindowLatencyMs) || 0) > 0) {
            tokens.push('q+' + String(Number(scenarioState.questionWindowLatencyMs) || 0) + 'ms');
        }
        if (scenarioState.forcePendingSync) {
            tokens.push('pending-sync');
        }
        if (scenarioState.disableAutoRetry) {
            tokens.push('retry-off');
        }
        if (scenarioState.failNextQuestionWindow && scenarioState.failNextQuestionWindow.enabled) {
            tokens.push('fail-q');
        }
        if (scenarioState.failFinishOnce && scenarioState.failFinishOnce.enabled) {
            tokens.push('fail-finish');
        }
        if (String(scenarioState.heartbeatScenario || 'off') !== 'off') {
            tokens.push('heartbeat=' + String(scenarioState.heartbeatScenario || 'off'));
        }

        return tokens.slice(0, 4).join(',');
    }

    function updatePanelMinimizedState() {
        if (!(panel instanceof HTMLElement)) {
            return;
        }

        panel.classList.toggle('is-minimized', isMinimized);
        if (toggleButton instanceof HTMLElement) {
            toggleButton.textContent = isMinimized ? 'Expand' : 'Minimize';
            toggleButton.setAttribute('aria-pressed', isMinimized ? 'true' : 'false');
            toggleButton.setAttribute('title', isMinimized ? 'Tampilkan panel debug' : 'Minimize panel debug');
        }
    }

    function updatePauseButton() {
        if (!(pauseButton instanceof HTMLElement)) {
            return;
        }

        var paused = diagnosticsManager && diagnosticsManager.enabled && diagnosticsManager.isCapturePaused();
        pauseButton.textContent = paused ? 'Resume' : 'Pause';
        pauseButton.setAttribute('aria-pressed', paused ? 'true' : 'false');
        pauseButton.setAttribute('title', paused ? 'Lanjutkan capture diagnostics' : 'Pause capture diagnostics');
    }

    function updateForceOfflineButton() {
        if (!(forceOfflineButton instanceof HTMLElement)) {
            return;
        }

        var active = false;
        if (diagnosticsManager && diagnosticsManager.enabled && typeof diagnosticsManager.readScenarioState === 'function') {
            var scenarioState = diagnosticsManager.readScenarioState();
            active = Boolean(scenarioState && scenarioState.forceOffline);
        }

        forceOfflineButton.textContent = active ? 'Offline ON' : 'Force Offline';
        forceOfflineButton.classList.toggle('is-active', active);
        forceOfflineButton.setAttribute('aria-pressed', active ? 'true' : 'false');
        forceOfflineButton.setAttribute('title', active ? 'Matikan Force Offline scenario' : 'Aktifkan Force Offline scenario');
    }

    function downloadBundle() {
        if (!diagnosticsManager || !diagnosticsManager.enabled) {
            return;
        }

        var bundle = diagnosticsManager.exportBundle();
        var payload = JSON.stringify(bundle, null, 2);
        var blob = new Blob([payload], {
            type: 'application/json'
        });
        var href = windowRef.URL.createObjectURL(blob);
        var anchor = documentRef.createElement('a');
        anchor.href = href;
        anchor.download = 'cbt-bug-report-' + Date.now() + '.json';
        anchor.style.display = 'none';
        documentRef.body.appendChild(anchor);
        anchor.click();
        documentRef.body.removeChild(anchor);
        windowRef.setTimeout(function () {
            windowRef.URL.revokeObjectURL(href);
        }, 1000);
    }

    function renderPanel() {
        if (!enabled) {
            return;
        }

        ensureStyle();
        isMinimized = readPersistedMinimizedState();

        if (!(panel instanceof HTMLElement)) {
            panel = documentRef.createElement('aside');
            panel.className = 'cbt-debug-panel';
            panel.innerHTML = [
                '<div class="cbt-debug-panel__head">',
                '<div class="cbt-debug-panel__title">CBT Frontend Debug</div>',
                '<div class="cbt-debug-panel__controls">',
                '<button class="cbt-debug-panel__btn" data-debug-action="pause" type="button">Pause</button>',
                '<button class="cbt-debug-panel__btn" data-debug-action="force-offline" type="button">Force Offline</button>',
                '<button class="cbt-debug-panel__btn" data-debug-action="export" type="button">Export</button>',
                '<button class="cbt-debug-panel__btn" data-debug-action="developer" type="button">Developer</button>',
                '<button class="cbt-debug-panel__btn" data-debug-action="toggle" type="button">Minimize</button>',
                '<button class="cbt-debug-panel__btn" data-debug-action="clear" type="button">Clear</button>',
                '</div>',
                '</div>',
                '<div class="cbt-debug-panel__status"></div>',
                '<ol class="cbt-debug-panel__list"></ol>'
            ].join('');
            statusNode = panel.querySelector('.cbt-debug-panel__status');
            listNode = panel.querySelector('.cbt-debug-panel__list');
            toggleButton = panel.querySelector('[data-debug-action="toggle"]');
            pauseButton = panel.querySelector('[data-debug-action="pause"]');
            developerButton = panel.querySelector('[data-debug-action="developer"]');
            forceOfflineButton = panel.querySelector('[data-debug-action="force-offline"]');
            panel.addEventListener('click', function (event) {
                var target = resolveEventElement(event.target);
                if (!(target instanceof Element)) {
                    return;
                }

                var action = target.getAttribute('data-debug-action');
                if (action === 'pause') {
                    if (diagnosticsManager && diagnosticsManager.enabled) {
                        diagnosticsManager.toggleCapturePaused();
                        updatePauseButton();
                        refresh();
                    }
                    return;
                }

                if (action === 'force-offline') {
                    if (
                        diagnosticsManager
                        && diagnosticsManager.enabled
                        && typeof diagnosticsManager.readScenarioState === 'function'
                        && typeof diagnosticsManager.writeScenarioState === 'function'
                    ) {
                        var currentScenario = diagnosticsManager.readScenarioState();
                        diagnosticsManager.writeScenarioState(Object.assign({}, currentScenario || {}, {
                            forceOffline: !(currentScenario && currentScenario.forceOffline)
                        }));
                        updateForceOfflineButton();
                        refresh();
                    }
                    return;
                }

                if (action === 'export') {
                    downloadBundle();
                    return;
                }

                if (action === 'developer') {
                    if (config.frontendDeveloperPageUrl) {
                        windowRef.open(String(config.frontendDeveloperPageUrl), '_blank', 'noopener,noreferrer');
                    }
                    return;
                }

                if (action === 'toggle') {
                    isMinimized = !isMinimized;
                    persistMinimizedState();
                    updatePanelMinimizedState();
                    return;
                }

                if (action === 'clear') {
                    logs = [];
                    refresh();
                }
            });

            if (documentRef.body) {
                documentRef.body.appendChild(panel);
            }
        }

        if (developerButton instanceof HTMLElement && !config.frontendDeveloperPageUrl) {
            developerButton.style.display = 'none';
        }

        updatePanelMinimizedState();
        updatePauseButton();
        updateForceOfflineButton();
    }

    function refresh() {
        if (!enabled) {
            return;
        }

        if (!(panel instanceof HTMLElement)) {
            renderPanel();
        }

        if (statusNode instanceof HTMLElement) {
            statusNode.textContent = buildStatusText();
        }

        if (!(listNode instanceof HTMLElement)) {
            return;
        }

        listNode.innerHTML = logs.map(function (entry) {
            return [
                '<li class="cbt-debug-panel__item">',
                '<div><span class="cbt-debug-panel__kind">' + escapeHtml(entry.kind) + '</span> <span class="cbt-debug-panel__time">' + escapeHtml(entry.time) + '</span></div>',
                '<div class="cbt-debug-panel__meta">' + escapeHtml(entry.meta) + '</div>',
                '</li>'
            ].join('');
        }).join('');
    }

    function push(kind, meta) {
        if (!enabled) {
            return;
        }

        if (diagnosticsManager && diagnosticsManager.enabled && diagnosticsManager.isCapturePaused()) {
            return;
        }

        logs.unshift({
            kind: String(kind || 'event'),
            time: formatTime(Date.now()),
            meta: String(meta || '')
        });
        if (logs.length > maxLogs) {
            logs.length = maxLogs;
        }
        refresh();
    }

    function log(kind, payload) {
        var lines = [];
        payload = payload && typeof payload === 'object' ? payload : {};

        Object.keys(payload).forEach(function (key) {
            var value = payload[key];
            if (Array.isArray(value)) {
                lines.push(key + '=' + value.join(' | '));
                return;
            }

            lines.push(key + '=' + String(value));
        });

        push(kind, lines.join('\n'));
    }

    function logEvent(kind, event, extra) {
        extra = extra && typeof extra === 'object' ? extra : {};

        var target = resolveEventElement(event && event.target);
        var payload = {
            target: describeElement(target),
            stage: String(state.stage || 'login'),
            busy: String(Boolean(state.busy)),
            opening: String(Boolean(state.isOpeningAttempt))
        };

        if (event && typeof event.clientX === 'number' && typeof event.clientY === 'number') {
            payload.point = String(event.clientX) + ',' + String(event.clientY);
            payload.stack = getStackFromPoint(event);
        }

        Object.keys(extra).forEach(function (key) {
            payload[key] = extra[key];
        });

        log(kind, payload);
    }

    function mount() {
        if (!enabled) {
            return;
        }

        renderPanel();
        windowRef.addEventListener('error', function (event) {
            if (diagnosticsManager && diagnosticsManager.enabled) {
                diagnosticsManager.recordError('window:error', {
                    colno: event && event.colno !== undefined ? event.colno : '',
                    filename: event && event.filename ? String(event.filename) : '',
                    lineno: event && event.lineno !== undefined ? event.lineno : '',
                    message: event && event.message ? String(event.message) : ''
                });
                if (typeof diagnosticsManager.recordTimeline === 'function') {
                    diagnosticsManager.recordTimeline('runtime:error', event && event.message ? String(event.message) : 'window:error', {
                        filename: event && event.filename ? String(event.filename) : '',
                        lineno: event && event.lineno !== undefined ? event.lineno : '',
                        colno: event && event.colno !== undefined ? event.colno : ''
                    });
                }
            }
            logEvent('window:error', event, {
                message: event && event.message ? String(event.message) : '',
                filename: event && event.filename ? String(event.filename) : '',
                lineno: event && event.lineno !== undefined ? String(event.lineno) : '',
                colno: event && event.colno !== undefined ? String(event.colno) : ''
            });
        });
        windowRef.addEventListener('unhandledrejection', function (event) {
            var reason = event && Object.prototype.hasOwnProperty.call(event, 'reason') ? event.reason : '';
            if (diagnosticsManager && diagnosticsManager.enabled) {
                diagnosticsManager.recordError('window:unhandledrejection', {
                    reason: reason
                });
                if (typeof diagnosticsManager.recordTimeline === 'function') {
                    diagnosticsManager.recordTimeline('runtime:error', reason instanceof Error ? String(reason.message || '') : String(reason || 'unhandledrejection'), {
                        reason: reason instanceof Error ? {
                            message: String(reason.message || ''),
                            stack: String(reason.stack || '')
                        } : String(reason || '')
                    });
                }
            }
            log('window:unhandledrejection', {
                reason: reason instanceof Error
                    ? (String(reason.message || '') + (reason.stack ? '\n' + String(reason.stack) : ''))
                    : String(reason || '')
            });
        });
        windowRef.__CBTFrontendDebug = {
            log: log,
            logEvent: logEvent,
            refresh: refresh
        };
    }

    return {
        enabled: enabled,
        log: log,
        logEvent: logEvent,
        mount: mount,
        refresh: refresh
    };
}
