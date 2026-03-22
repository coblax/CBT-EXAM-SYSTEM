function createNoopBundle() {
    return {
        exportedAt: new Date().toISOString(),
        enabled: false,
        paused: false,
        source: 'Production Build',
        snapshot: null,
        syncSnapshot: null,
        requestLogs: [],
        timeline: [],
        renderStats: null,
        actionTrail: [],
        scenarios: null,
        errors: [],
        storageSummary: {
            localStorageKeys: [],
            sessionStorageKeys: [],
            indexedDbName: ''
        }
    };
}

function buildDefaultScenarioState() {
    return {
        forceOffline: false,
        apiLatencyMs: 0,
        failNextApiRequest: {
            enabled: false,
            target: 'any'
        },
        failNextChunkLoad: {
            enabled: false,
            target: 'exam'
        },
        questionWindowLatencyMs: 0,
        failNextQuestionWindow: {
            enabled: false,
            target: 'any'
        },
        forcePendingSync: false,
        failFinishOnce: {
            enabled: false,
            mode: 'network'
        },
        heartbeatScenario: 'off',
        disableAutoRetry: false,
        updatedAt: ''
    };
}

export function createNoopFrontendDiagnosticsManager() {
    return {
        enabled: false,
        recordApiRequest: function () {},
        recordError: function () {},
        recordRuntimeSnapshot: function () {},
        recordSyncSnapshot: function () {},
        recordTimeline: function () {},
        recordRenderScheduled: function () {},
        recordRenderPerformed: function () {},
        recordActionTrail: function () {},
        readErrors: function () { return []; },
        readRequestLogs: function () { return []; },
        readSnapshot: function () { return null; },
        readSyncSnapshot: function () { return null; },
        readTimeline: function () { return []; },
        readRenderStats: function () { return null; },
        readActionTrail: function () { return []; },
        readScenarioState: function () { return buildDefaultScenarioState(); },
        clearErrors: function () {},
        clearRequestLogs: function () {},
        clearSnapshot: function () {},
        clearSyncSnapshot: function () {},
        clearTimeline: function () {},
        clearRenderStats: function () {},
        clearActionTrail: function () {},
        resetScenarioState: function () { return buildDefaultScenarioState(); },
        writeScenarioState: function () { return buildDefaultScenarioState(); },
        clearAll: function () {},
        exportBundle: function () {
            return createNoopBundle();
        },
        isCapturePaused: function () {
            return false;
        },
        setCapturePaused: function () {},
        toggleCapturePaused: function () {
            return false;
        },
        getApiLatencyMs: function () {
            return 0;
        },
        getQuestionWindowLatencyMs: function () {
            return 0;
        },
        isForcedOffline: function () {
            return false;
        },
        isPendingSyncForced: function () {
            return false;
        },
        isAutoRetryDisabled: function () {
            return false;
        },
        getHeartbeatScenario: function () {
            return 'off';
        },
        consumeFailNextApiRequest: function () {
            return false;
        },
        consumeFailNextChunkLoad: function () {
            return false;
        },
        consumeFailNextQuestionWindow: function () {
            return false;
        },
        consumeFailFinishOnce: function () {
            return '';
        },
        consumeHeartbeatFailureOnce: function () {
            return false;
        }
    };
}

export function createFrontendDiagnosticsManager(deps) {
    var config = deps.config || {};
    var windowRef = deps.windowRef;
    var enabled = Boolean(config.frontendDiagnosticsEnabled);

    if (!enabled || !windowRef || !windowRef.localStorage) {
        return createNoopFrontendDiagnosticsManager();
    }

    var storagePrefix = String(config.frontendDiagnosticsStoragePrefix || 'cbt_exam_frontend_');
    var indexedDbName = String(config.frontendDiagnosticsIndexedDbName || '');
    var requestsKey = String(config.frontendDiagnosticsStorageKey || 'cbt_exam_frontend_debug_rest_v1');
    var snapshotKey = String(config.frontendDiagnosticsSnapshotKey || 'cbt_exam_frontend_debug_snapshot_v1');
    var syncSnapshotKey = String(config.frontendDiagnosticsSyncKey || 'cbt_exam_frontend_debug_sync_v1');
    var timelineKey = String(config.frontendDiagnosticsTimelineKey || 'cbt_exam_frontend_debug_timeline_v1');
    var scenarioKey = String(config.frontendDiagnosticsScenarioKey || 'cbt_exam_frontend_debug_scenarios_v1');
    var errorsKey = String(config.frontendDiagnosticsErrorsKey || 'cbt_exam_frontend_debug_errors_v1');
    var stateKey = String(config.frontendDiagnosticsStateKey || 'cbt_exam_frontend_debug_state_v1');
    var renderStatsKey = String(config.frontendDiagnosticsRenderStatsKey || 'cbt_exam_frontend_debug_render_stats_v1');
    var actionTrailKey = String(config.frontendDiagnosticsActionTrailKey || 'cbt_exam_frontend_debug_action_trail_v1');
    var maxEntries = Math.max(10, Number(config.frontendDiagnosticsMaxEntries) || 50);
    var maxErrors = Math.max(10, Math.min(maxEntries, 20));
    var maxTimelineEntries = Math.max(20, Number(config.frontendDiagnosticsTimelineMaxEntries) || 150);
    var maxActionTrailEntries = 30;
    var scenariosEnabled = Boolean(config.frontendDiagnosticsScenarioEnabled);
    var renderStatsEnabled = Boolean(config.frontendDiagnosticsRenderStatsEnabled);
    var maxArrayItems = 20;
    var maxObjectKeys = 30;
    var maxStringLength = 280;

    function readJson(key, fallback) {
        try {
            var raw = windowRef.localStorage.getItem(key);
            if (!raw) {
                return fallback;
            }

            var parsed = JSON.parse(raw);
            return parsed === null || parsed === undefined ? fallback : parsed;
        } catch (error) {
            return fallback;
        }
    }

    function writeJson(key, value) {
        try {
            windowRef.localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {
            // Ignore storage quota failures in diagnostics-only path.
        }
    }

    function removeKey(key) {
        try {
            windowRef.localStorage.removeItem(key);
        } catch (error) {
            // Ignore storage removal failures in diagnostics-only path.
        }
    }

    function isSensitiveKey(key) {
        var normalized = String(key || '').trim().toLowerCase();
        if (normalized === '') {
            return false;
        }

        return normalized === 'password'
            || normalized === 'pass'
            || normalized === 'exam_token'
            || normalized === 'authorization'
            || normalized.indexOf('authorization') !== -1
            || normalized.indexOf('bearer') !== -1
            || normalized.indexOf('secret') !== -1
            || normalized.indexOf('cookie') !== -1
            || normalized.endsWith('_token')
            || normalized === 'token';
    }

    function truncateString(value) {
        var text = String(value);
        if (text.length <= maxStringLength) {
            return text;
        }

        return text.slice(0, maxStringLength) + '...';
    }

    function normalizeError(error) {
        if (!(error instanceof Error)) {
            return truncateString(error);
        }

        return {
            name: truncateString(error.name || 'Error'),
            message: truncateString(error.message || ''),
            stack: truncateString(error.stack || '')
        };
    }

    function normalizeForStorage(value, depth, keyName) {
        if (depth > 4) {
            return '[max-depth]';
        }

        if (isSensitiveKey(keyName)) {
            return '[redacted]';
        }

        if (value === null || value === undefined) {
            return value;
        }

        if (value instanceof Error) {
            return normalizeError(value);
        }

        var valueType = typeof value;
        if (valueType === 'string') {
            return truncateString(value);
        }

        if (valueType === 'number' || valueType === 'boolean') {
            return value;
        }

        if (valueType === 'function') {
            return '[function]';
        }

        if (Array.isArray(value)) {
            return value.slice(0, maxArrayItems).map(function (item) {
                return normalizeForStorage(item, depth + 1, '');
            });
        }

        if (valueType === 'object') {
            var output = {};
            Object.keys(value).slice(0, maxObjectKeys).forEach(function (key) {
                output[key] = normalizeForStorage(value[key], depth + 1, key);
            });
            return output;
        }

        return truncateString(value);
    }

    function readState() {
        var raw = readJson(stateKey, {});
        if (!raw || typeof raw !== 'object') {
            raw = {};
        }

        return {
            paused: Boolean(raw.paused),
            updatedAt: typeof raw.updatedAt === 'string' ? raw.updatedAt : ''
        };
    }

    function writeState(nextState) {
        writeJson(stateKey, {
            paused: Boolean(nextState.paused),
            updatedAt: new Date().toISOString()
        });
    }

    function isCapturePaused() {
        return Boolean(readState().paused);
    }

    function setCapturePaused(nextPaused) {
        writeState({
            paused: Boolean(nextPaused)
        });
    }

    function toggleCapturePaused() {
        var nextPaused = !isCapturePaused();
        setCapturePaused(nextPaused);
        return nextPaused;
    }

    function pushBounded(key, entry, limit, allowPaused) {
        if (!allowPaused && isCapturePaused()) {
            return;
        }

        var items = readJson(key, []);
        if (!Array.isArray(items)) {
            items = [];
        }

        items.unshift(entry);
        if (items.length > limit) {
            items.length = limit;
        }

        writeJson(key, items);
    }

    function normalizeScenarioChoice(value, allowed, fallback) {
        var normalized = String(value || '').trim().toLowerCase();
        if (allowed.indexOf(normalized) >= 0) {
            return normalized;
        }
        return fallback;
    }

    function normalizeScenarioState(input) {
        var source = input && typeof input === 'object' ? input : {};
        var normalized = buildDefaultScenarioState();
        normalized.forceOffline = Boolean(source.forceOffline);
        normalized.apiLatencyMs = [0, 800, 2000].indexOf(Number(source.apiLatencyMs) || 0) >= 0
            ? Number(source.apiLatencyMs) || 0
            : 0;
        normalized.failNextApiRequest = {
            enabled: Boolean(source.failNextApiRequest && source.failNextApiRequest.enabled),
            target: normalizeScenarioChoice(
                source.failNextApiRequest && source.failNextApiRequest.target,
                ['any', 'login', 'exams', 'start_attempt', 'submit_answer', 'submit_answers_batch', 'session', 'finish_attempt', 'finish_exam', 'result'],
                'any'
            )
        };
        normalized.failNextChunkLoad = {
            enabled: Boolean(source.failNextChunkLoad && source.failNextChunkLoad.enabled),
            target: normalizeScenarioChoice(
                source.failNextChunkLoad && source.failNextChunkLoad.target,
                ['any', 'exam', 'result', 'calculator'],
                'exam'
            )
        };
        normalized.questionWindowLatencyMs = [0, 600, 1500, 3000].indexOf(Number(source.questionWindowLatencyMs) || 0) >= 0
            ? (Number(source.questionWindowLatencyMs) || 0)
            : 0;
        normalized.failNextQuestionWindow = {
            enabled: Boolean(source.failNextQuestionWindow && source.failNextQuestionWindow.enabled),
            target: normalizeScenarioChoice(
                source.failNextQuestionWindow && source.failNextQuestionWindow.target,
                ['any', 'current', 'prefetch'],
                'any'
            )
        };
        normalized.forcePendingSync = Boolean(source.forcePendingSync);
        normalized.failFinishOnce = {
            enabled: Boolean(source.failFinishOnce && source.failFinishOnce.enabled),
            mode: normalizeScenarioChoice(
                source.failFinishOnce && source.failFinishOnce.mode,
                ['network', 'server', 'validation'],
                'network'
            )
        };
        normalized.heartbeatScenario = normalizeScenarioChoice(
            source.heartbeatScenario,
            ['off', 'slow', 'fail-next', 'timeout'],
            'off'
        );
        normalized.disableAutoRetry = Boolean(source.disableAutoRetry);
        normalized.updatedAt = typeof source.updatedAt === 'string' && source.updatedAt !== ''
            ? source.updatedAt
            : '';
        return normalized;
    }

    function readScenarioState() {
        if (!scenariosEnabled) {
            return buildDefaultScenarioState();
        }

        return normalizeScenarioState(readJson(scenarioKey, buildDefaultScenarioState()));
    }

    function writeScenarioState(nextState) {
        var normalized = normalizeScenarioState(nextState);
        normalized.updatedAt = new Date().toISOString();
        writeJson(scenarioKey, normalized);
        return normalized;
    }

    function resetScenarioState() {
        if (!scenariosEnabled) {
            return buildDefaultScenarioState();
        }

        removeKey(scenarioKey);
        return buildDefaultScenarioState();
    }

    function consumeScenarioBranch(branchKey, targetKey, value) {
        if (!scenariosEnabled) {
            return false;
        }

        var current = readScenarioState();
        var branch = current && current[branchKey] && typeof current[branchKey] === 'object'
            ? current[branchKey]
            : null;
        if (!branch || !branch.enabled) {
            return false;
        }

        var requestedTarget = String(value || '').trim().toLowerCase() || 'any';
        var currentTarget = String(branch[targetKey] || '').trim().toLowerCase() || 'any';
        if (currentTarget !== 'any' && currentTarget !== requestedTarget) {
            return false;
        }

        branch.enabled = false;
        current.updatedAt = new Date().toISOString();
        writeJson(scenarioKey, current);
        return true;
    }

    function consumeScenarioBranchValue(branchKey, targetKey, fallbackValue) {
        if (!scenariosEnabled) {
            return '';
        }

        var current = readScenarioState();
        var branch = current && current[branchKey] && typeof current[branchKey] === 'object'
            ? current[branchKey]
            : null;
        if (!branch || !branch.enabled) {
            return '';
        }

        var resolvedValue = String(branch[targetKey] || fallbackValue || '').trim().toLowerCase();
        branch.enabled = false;
        current.updatedAt = new Date().toISOString();
        writeJson(scenarioKey, current);
        return resolvedValue;
    }

    function readRequestLogs() {
        var logs = readJson(requestsKey, []);
        return Array.isArray(logs) ? logs : [];
    }

    function readErrors() {
        var errors = readJson(errorsKey, []);
        return Array.isArray(errors) ? errors : [];
    }

    function readSnapshot() {
        var snapshot = readJson(snapshotKey, null);
        return snapshot && typeof snapshot === 'object' ? snapshot : null;
    }

    function readSyncSnapshot() {
        var snapshot = readJson(syncSnapshotKey, null);
        return snapshot && typeof snapshot === 'object' ? snapshot : null;
    }

    function readTimeline() {
        var items = readJson(timelineKey, []);
        return Array.isArray(items) ? items : [];
    }

    function readRenderStats() {
        var snapshot = readJson(renderStatsKey, null);
        return snapshot && typeof snapshot === 'object' ? snapshot : null;
    }

    function readActionTrail() {
        var items = readJson(actionTrailKey, []);
        return Array.isArray(items) ? items : [];
    }

    function summarizeStorageArea(area) {
        var keys = [];
        if (!area || typeof area.length !== 'number' || typeof area.key !== 'function') {
            return keys;
        }

        for (var index = 0; index < area.length; index += 1) {
            var currentKey = area.key(index);
            if (typeof currentKey === 'string' && currentKey.indexOf(storagePrefix) === 0) {
                keys.push(currentKey);
            }
        }

        return keys.sort();
    }

    function recordApiRequest(entry) {
        pushBounded(requestsKey, normalizeForStorage(Object.assign({
            time: new Date().toISOString()
        }, entry || {}), 0, ''), maxEntries);
    }

    function recordRuntimeSnapshot(snapshot) {
        if (isCapturePaused()) {
            return;
        }

        writeJson(snapshotKey, normalizeForStorage(Object.assign({
            updatedAt: new Date().toISOString()
        }, snapshot || {}), 0, ''));
    }

    function recordSyncSnapshot(snapshot) {
        if (isCapturePaused()) {
            return;
        }

        writeJson(syncSnapshotKey, normalizeForStorage(Object.assign({
            updatedAt: new Date().toISOString()
        }, snapshot || {}), 0, ''));
    }

    function recordTimeline(kind, summary, meta) {
        pushBounded(timelineKey, normalizeForStorage({
            time: new Date().toISOString(),
            kind: String(kind || 'runtime'),
            summary: String(summary || ''),
            meta: meta || {}
        }, 0, ''), maxTimelineEntries);
    }

    function writeRenderStats(nextStats) {
        if (!renderStatsEnabled || isCapturePaused()) {
            return;
        }

        writeJson(renderStatsKey, normalizeForStorage(nextStats, 0, ''));
    }

    function updateRenderStats(nextReason, nextMeta, stage, options) {
        if (!renderStatsEnabled) {
            return;
        }

        options = options && typeof options === 'object' ? options : {};

        var nowMs = Date.now();
        var current = readRenderStats();
        if (!current || typeof current !== 'object') {
            current = {};
        }

        var scheduledCount = Math.max(0, Number(current.totalScheduled) || 0);
        var performedCount = Math.max(0, Number(current.totalPerformed) || 0);
        var perStage = current.perStage && typeof current.perStage === 'object' ? current.perStage : {};
        var performedTimeline = Array.isArray(current.recentPerformedAt) ? current.recentPerformedAt.slice(0, 20) : [];
        var safeStage = String(stage || 'login');
        var safeReason = String(nextReason || 'unknown');

        if (options.incrementScheduled) {
            scheduledCount += 1;
        }

        if (options.incrementPerformed) {
            performedCount += 1;
            perStage[safeStage] = Math.max(0, Number(perStage[safeStage]) || 0) + 1;
            performedTimeline.unshift(nowMs);
            performedTimeline = performedTimeline.filter(function (value) {
                return Number(value) > 0 && (nowMs - Number(value)) <= 2000;
            }).slice(0, 20);
        }

        writeRenderStats({
            totalScheduled: scheduledCount,
            totalPerformed: performedCount,
            perStage: perStage,
            lastRenderTime: new Date(nowMs).toISOString(),
            lastRenderReason: safeReason,
            lastRenderMeta: nextMeta || {},
            burstLast2s: performedTimeline.length,
            recentPerformedAt: performedTimeline,
            updatedAt: new Date(nowMs).toISOString()
        });
    }

    function recordRenderScheduled(reason, meta, stage) {
        updateRenderStats(reason, meta, stage, {
            incrementScheduled: true
        });
    }

    function recordRenderPerformed(reason, meta, stage) {
        updateRenderStats(reason, meta, stage, {
            incrementPerformed: true
        });
    }

    function recordActionTrail(kind, summary, meta) {
        pushBounded(actionTrailKey, normalizeForStorage({
            time: new Date().toISOString(),
            kind: String(kind || 'action'),
            summary: String(summary || ''),
            meta: meta || {}
        }, 0, ''), maxActionTrailEntries);
    }

    function recordError(kind, payload) {
        pushBounded(errorsKey, normalizeForStorage({
            time: new Date().toISOString(),
            kind: String(kind || 'runtime'),
            payload: payload || {}
        }, 0, ''), maxErrors, true);
    }

    function exportBundle() {
        var snapshot = readSnapshot();
        return {
            exportedAt: new Date().toISOString(),
            enabled: true,
            paused: isCapturePaused(),
            source: String(config.frontendAssetSource || 'Production Build'),
            debugReason: String(config.frontendDebugReason || ''),
            diagnosticsReason: String(config.frontendDiagnosticsReason || config.frontendDebugReason || ''),
            snapshot: snapshot,
            syncSnapshot: readSyncSnapshot(),
            requestLogs: readRequestLogs(),
            timeline: readTimeline(),
            renderStats: readRenderStats(),
            actionTrail: readActionTrail(),
            scenarios: readScenarioState(),
            errors: readErrors(),
            storageSummary: {
                localStorageKeys: summarizeStorageArea(windowRef.localStorage),
                sessionStorageKeys: windowRef.sessionStorage ? summarizeStorageArea(windowRef.sessionStorage) : [],
                indexedDbName: indexedDbName
            }
        };
    }

    function clearRequestLogs() {
        removeKey(requestsKey);
    }

    function clearSnapshot() {
        removeKey(snapshotKey);
    }

    function clearSyncSnapshot() {
        removeKey(syncSnapshotKey);
    }

    function clearTimeline() {
        removeKey(timelineKey);
    }

    function clearRenderStats() {
        removeKey(renderStatsKey);
    }

    function clearActionTrail() {
        removeKey(actionTrailKey);
    }

    function clearErrors() {
        removeKey(errorsKey);
    }

    function clearAll() {
        clearRequestLogs();
        clearSnapshot();
        clearSyncSnapshot();
        clearTimeline();
        clearRenderStats();
        clearActionTrail();
        clearErrors();
        resetScenarioState();
        removeKey(stateKey);
    }

    return {
        enabled: true,
        recordApiRequest: recordApiRequest,
        recordError: recordError,
        recordRuntimeSnapshot: recordRuntimeSnapshot,
        recordSyncSnapshot: recordSyncSnapshot,
        recordTimeline: recordTimeline,
        recordRenderScheduled: recordRenderScheduled,
        recordRenderPerformed: recordRenderPerformed,
        recordActionTrail: recordActionTrail,
        readErrors: readErrors,
        readRequestLogs: readRequestLogs,
        readSnapshot: readSnapshot,
        readSyncSnapshot: readSyncSnapshot,
        readTimeline: readTimeline,
        readRenderStats: readRenderStats,
        readActionTrail: readActionTrail,
        readScenarioState: readScenarioState,
        writeScenarioState: writeScenarioState,
        resetScenarioState: resetScenarioState,
        clearErrors: clearErrors,
        clearRequestLogs: clearRequestLogs,
        clearSnapshot: clearSnapshot,
        clearSyncSnapshot: clearSyncSnapshot,
        clearTimeline: clearTimeline,
        clearRenderStats: clearRenderStats,
        clearActionTrail: clearActionTrail,
        clearAll: clearAll,
        exportBundle: exportBundle,
        isCapturePaused: isCapturePaused,
        setCapturePaused: setCapturePaused,
        toggleCapturePaused: toggleCapturePaused,
        getApiLatencyMs: function () {
            return Number(readScenarioState().apiLatencyMs) || 0;
        },
        getQuestionWindowLatencyMs: function () {
            return Number(readScenarioState().questionWindowLatencyMs) || 0;
        },
        isForcedOffline: function () {
            return Boolean(readScenarioState().forceOffline);
        },
        isPendingSyncForced: function () {
            return Boolean(readScenarioState().forcePendingSync);
        },
        isAutoRetryDisabled: function () {
            return Boolean(readScenarioState().disableAutoRetry);
        },
        getHeartbeatScenario: function () {
            return String(readScenarioState().heartbeatScenario || 'off');
        },
        consumeFailNextApiRequest: function (target) {
            return consumeScenarioBranch('failNextApiRequest', 'target', target || 'any');
        },
        consumeFailNextChunkLoad: function (target) {
            return consumeScenarioBranch('failNextChunkLoad', 'target', target || 'any');
        },
        consumeFailNextQuestionWindow: function (target) {
            return consumeScenarioBranch('failNextQuestionWindow', 'target', target || 'any');
        },
        consumeFailFinishOnce: function () {
            return consumeScenarioBranchValue('failFinishOnce', 'mode', 'network');
        },
        consumeHeartbeatFailureOnce: function () {
            if (!scenariosEnabled) {
                return false;
            }

            var current = readScenarioState();
            if (String(current.heartbeatScenario || 'off') !== 'fail-next') {
                return false;
            }

            current.heartbeatScenario = 'off';
            current.updatedAt = new Date().toISOString();
            writeJson(scenarioKey, current);
            return true;
        }
    };
}
