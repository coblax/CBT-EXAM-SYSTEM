import {
    AUTH_SESSION_STORAGE_KEY,
    ATTEMPT_UI_SESSION_STORAGE_KEY_PREFIX,
    DOUBTFUL_SESSION_STORAGE_KEY_PREFIX,
    EXAM_TOKEN_LENGTH,
    FONT_SCALE_DEFAULT,
    FONT_SCALE_MAX,
    FONT_SCALE_MIN,
    FONT_SCALE_STEP,
    NAV_QUESTION_FILTER_ALL,
    NAV_QUESTION_FILTER_ANSWERED,
    NAV_QUESTION_FILTER_DOUBTFUL,
    NAV_QUESTION_FILTER_UNANSWERED,
    QUESTION_CACHE_INDEXED_DB_NAME,
    QUESTION_CACHE_INDEXED_DB_STORE,
    QUESTION_CACHE_ITEM_LOCAL_STORAGE_KEY_PREFIX,
    QUESTION_CACHE_META_LOCAL_STORAGE_KEY_PREFIX,
    QUESTION_CACHE_SESSION_STORAGE_KEY_PREFIX,
    QUESTION_PREFETCH_BATCH_SIZE,
    QUESTION_PREFETCH_IDLE_DELAY_MS,
    QUESTION_WINDOW_SIZE,
    UI_PREF_STORAGE_KEY,
    createInitialState,
    getFrontendConfig
} from './core/config';
import {
    clamp,
    formatDateTime,
    formatDateTimeCompact,
    formatQuestionType,
    formatScoreValue,
    formatSeconds,
    navigationQuestionTypeBadgeConfig,
    normalizeExamToken,
    parseDateTime
} from './core/format';
import {
    buildSecurityClientContext,
    detectSecurityDevicePlatform,
    detectSecurityDeviceType,
    detectSecurityInputMode,
    getSecurityViewportHeight,
    getSecurityViewportWidth
} from './core/security-context';
import { createAppMetaManager } from './core/app-meta';
import { mountFrontendAppRuntime, startFrontendApp } from './core/app-bootstrap';
import { createFrontendDebugManagerBridge } from './core/debug-manager';
import { createFrontendDiagnosticsManager } from './core/frontend-diagnostics';
import { createAppEventManager } from './core/app-events';
import { createAttemptUiSyncManager } from './core/attempt-ui-sync';
import { createAuthStageManager } from './core/auth-stages';
import { createBrowserStorageAccess } from './core/browser-storage';
import { createBootstrapSessionManager } from './core/bootstrap-session';
import { createExamRuntimeLoader } from './core/exam-runtime-loader';
import { createLifecycleManager } from './core/lifecycle';
import { createLazyMathEnhancer } from './core/lazy-math';
import { createIdleDetectionManager } from './core/idle-detection';
import { createNativeBridgeManager } from './core/native-bridge';
import { createRenderCycleManager } from './core/render-cycle';
import { createSecurityLoggingManager } from './core/security-logging';
import { createExamSessionManager } from './core/exam-session';
import { createSessionHeartbeatManager } from './core/session-heartbeat';
import { createSessionLifecycleManager } from './core/session-lifecycle';
import { createSyncLifecycleBridge } from './core/sync-lifecycle-bridge';
import { createAppShellManager } from './core/app-shell';
import { createStageRuntimeManager } from './core/stage-runtime';
import { createUiPreferencesManager } from './core/ui-preferences';
import { createAuthSessionManager } from './core/auth-session';
import { createApiClient } from './core/api';
import { escapeHtml } from './core/html';
import { createFullscreenStateManager } from './core/fullscreen-state';
import { createDoubtfulStateStorage } from './storage/doubtful-state';
import { createQuestionRenderManager } from './exam/question-render';
import { createExamSecurityManager } from './exam/security';

export function bootstrapFrontendApp() {
    'use strict';

    var root = document.getElementById('cbt-exam-app');
    if (!root) {
        return;
    }

    var config = getFrontendConfig(window);
    if (document.body) {
        document.body.classList.toggle('cbt-security-print-guard', Number(config.securityLogEvents || 0) === 1);
    }
    var browserStorage = createBrowserStorageAccess(window);
    var state = createInitialState(window);
    var debugManager = createFrontendDebugManagerBridge();
    var diagnosticsManager = createFrontendDiagnosticsManager({
        config: config,
        windowRef: window
    });
    var bootProgressValue = 0;
    var enhanceRichMath = createLazyMathEnhancer({
        recordTimeline: recordTimeline,
        root: root
    });

    function setBootProgress(percent, label, status) {
        var nextValue = Number(percent);
        var fill;
        var labelNode;
        var statusNode;
        var valueNode;

        if (!Number.isFinite(nextValue)) {
            return;
        }

        nextValue = Math.max(0, Math.min(100, nextValue));
        if (nextValue < bootProgressValue) {
            nextValue = bootProgressValue;
        }
        bootProgressValue = nextValue;

        fill = root.querySelector('#cbt-boot-progress-fill');
        labelNode = root.querySelector('#cbt-boot-progress-label');
        statusNode = root.querySelector('#cbt-boot-progress-status');
        valueNode = root.querySelector('#cbt-boot-progress-value');

        if (fill instanceof HTMLElement) {
            fill.style.width = String(nextValue) + '%';
        }
        if (labelNode instanceof HTMLElement && typeof label === 'string' && label !== '') {
            labelNode.textContent = label;
        }
        if (statusNode instanceof HTMLElement && typeof status === 'string' && status !== '') {
            statusNode.textContent = status;
        }
        if (valueNode instanceof HTMLElement) {
            valueNode.textContent = String(Math.round(nextValue)) + '%';
        }
    }

    function recordTimeline(kind, summary, meta) {
        if (!diagnosticsManager || !diagnosticsManager.enabled || typeof diagnosticsManager.recordTimeline !== 'function') {
            return;
        }

        diagnosticsManager.recordTimeline(kind, summary, Object.assign({
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: Number(state.selectedExamId) || 0,
            stage: String(state.stage || 'login')
        }, meta || {}));
    }

    function recordActionTrail(kind, summary, meta) {
        if (!diagnosticsManager || !diagnosticsManager.enabled || typeof diagnosticsManager.recordActionTrail !== 'function') {
            return;
        }

        diagnosticsManager.recordActionTrail(String(kind || 'action'), String(summary || ''), Object.assign({
            attemptId: Number(state.attemptId) || 0,
            selectedExamId: Number(state.selectedExamId) || 0,
            stage: String(state.stage || 'login')
        }, meta || {}));
    }
    var AUTO_SAVE_CHOICE_DELAY_MS = 2000;
    var AUTO_SAVE_TEXT_DELAY_MS = 3500;
    var AUTO_SAVE_CHOICE_DELAY_CONGESTED_MS = 2600;
    var AUTO_SAVE_TEXT_DELAY_CONGESTED_MS = 4600;
    var AUTO_SAVE_CONGESTED_WINDOW_MS = 15000;
    var AUTO_SAVE_BATCH_MAX_ITEMS = 20;
    var ANSWER_SYNC_RETRY_BASE_DELAY_MS = 2500;
    var ANSWER_SYNC_RETRY_MAX_DELAY_MS = 20000;
    var ATTEMPT_UI_STATE_SYNC_DELAY_MS = 1200;
    var ATTEMPT_UI_STATE_NAVIGATION_SYNC_DELAY_MS = 1600;
    var SESSION_HEARTBEAT_INTERVAL_MS = 20000;
    var WINDOW_BLUR_LOG_DELAY_MS = 800;
    var compactViewportState = false;
    var uiPreferencesSyncTimer = 0;
    var fullscreenExitLogSuppressedUntil = 0;
    var appEventManager = null;
    var appMetaManager = null;
    var attemptUiSyncManager = null;
    var answerSyncManager = null;
    var authStageManager = null;
    var answerInputManager = null;
    var appShellManager = null;
    var examSessionManager = null;
    var finishFlowManager = null;
    var navigationManager = null;
    var questionRenderManager = null;
    var questionRuntimeManager = null;
    var questionStateManager = null;
    var questionWindowManager = null;
    var questionCacheStorage = null;
    var attemptUiStateStorage = null;
    var questionFlags = null;
    var renderCycleManager = null;
    var securityLoggingManager = null;
    var sessionHeartbeatManager = null;
    var sessionLifecycleManager = null;
    var stageRuntimeManager = null;
    var startupManager = null;
    var examRuntimeLoader = null;
    var examRuntimeLoadError = '';

    function buildFallbackQuestionRevision(revision, fallbackExamId) {
        var safeRevision = revision && typeof revision === 'object' ? revision : {};
        var examId = Number(safeRevision.exam_id !== undefined ? safeRevision.exam_id : fallbackExamId) || 0;
        var namespace = String(safeRevision.namespace || '');
        var version = Math.max(0, Number(safeRevision.version) || 0);
        var invalidatedAt = Math.max(0, Number(safeRevision.invalidated_at) || 0);
        var signature = String(safeRevision.signature || '');

        if (namespace === '' && examId > 0) {
            namespace = 'exam:' + String(examId);
        }
        if (signature === '' && namespace !== '' && version > 0) {
            signature = namespace + '|v:' + String(version) + '|t:' + String(invalidatedAt);
        }

        if (examId <= 0 || namespace === '' || version <= 0 || signature === '') {
            return null;
        }

        return {
            examId: examId,
            namespace: namespace,
            version: version,
            invalidatedAt: invalidatedAt,
            signature: signature
        };
    }

    function questionRevisionSignature(revision, fallbackExamId) {
        var normalized = buildFallbackQuestionRevision(revision, fallbackExamId);
        return normalized ? String(normalized.signature || '') : '';
    }

    function questionOptionKey(option, index) {
        var key = String(option && option.option_key ? option.option_key : '').trim();
        if (key !== '') {
            return key;
        }

        var code = 65 + Number(index || 0);
        if (code >= 65 && code <= 90) {
            return String.fromCharCode(code);
        }

        return String((Number(index) || 0) + 1);
    }

    function resetQuestionDataStateFallback(options) {
        options = options || {};

        state.questions = [];
        state.questionOrderIds = [];
        state.questionManifest = [];
        state.questionManifestById = {};
        state.questionPayloadById = {};
        state.archivedReviewItems = [];
        state.existingAnswerRawByQuestionId = {};
        state.answeredQuestionLookup = {};
        state.changedQuestionLookup = {};
        state.questionRevisionMarkerLookup = {};
        state.acknowledgedRevisionQuestionIds = {};
        state.loadedQuestionWindowOffsets = {};
        state.windowOffset = 0;
        state.windowLimit = 0;
        state.totalQuestions = 0;
        state.questionOrderSignature = '';
        state.answers = {};
        state.questionRevisionToastTimerId = 0;
        state.questionRevisionNotice = null;

        if (!options.preserveDoubtful) {
            state.doubtful = {};
        }
        if (!options.preserveCurrentIndex) {
            state.currentIndex = 0;
        }
        if (!options.preserveNavFilter) {
            state.navQuestionFilter = NAV_QUESTION_FILTER_ALL;
        }
        if (!options.preserveQuestionRevision) {
            state.questionRevision = null;
        }
    }

    function maybeRejectExamRuntimeLoadByScenario() {
        if (
            diagnosticsManager
            && diagnosticsManager.enabled
            && typeof diagnosticsManager.consumeFailNextChunkLoad === 'function'
            && diagnosticsManager.consumeFailNextChunkLoad('exam-runtime')
        ) {
            var error = new Error('Scenario aktif: fail next chunk load (exam-runtime).');
            error.code = 'scenario_fail_next_chunk_load';
            error.isScenarioError = true;
            throw error;
        }
    }

    function formatExamRuntimeLoadErrorMessage(error, fallback) {
        var message = error instanceof Error && error.message ? error.message : '';
        if (message === '') {
            return fallback;
        }

        if (
            message.indexOf('Failed to fetch dynamically imported module') >= 0
            || message.indexOf('Importing a module script failed') >= 0
            || message.indexOf('fetch dynamically imported') >= 0
        ) {
            return fallback;
        }

        return message;
    }

    function getExamRuntimeBundle() {
        return examRuntimeLoader ? examRuntimeLoader.getBundle() : null;
    }

    function ensureExamRuntimeBundle(options) {
        if (!examRuntimeLoader) {
            return Promise.reject(new Error('Runtime ujian belum siap.'));
        }

        return examRuntimeLoader.ensure(options);
    }

    function prefetchExamRuntimeBundle() {
        if (!examRuntimeLoader) {
            return;
        }

        examRuntimeLoader.prefetch();
    }

    function getExamRuntimeManager(managerName) {
        var runtimeBundle = getExamRuntimeBundle();
        if (!runtimeBundle || typeof runtimeBundle !== 'object') {
            return null;
        }

        return runtimeBundle[managerName] && typeof runtimeBundle[managerName] === 'object'
            ? runtimeBundle[managerName]
            : null;
    }

    function bindExamRuntimeMethod(managerName, methodName, fallbackValue) {
        return function () {
            var manager = getExamRuntimeManager(managerName);
            if (manager && typeof manager[methodName] === 'function') {
                return manager[methodName].apply(manager, arguments);
            }

            if (typeof fallbackValue === 'function') {
                return fallbackValue.apply(null, arguments);
            }

            return fallbackValue;
        };
    }

    function syncExamRuntimeManagers(runtimeBundle) {
        questionWindowManager = runtimeBundle && runtimeBundle.questionWindowManager ? runtimeBundle.questionWindowManager : null;
        questionStateManager = runtimeBundle && runtimeBundle.questionStateManager ? runtimeBundle.questionStateManager : null;
        answerSyncManager = runtimeBundle && runtimeBundle.answerSyncManager ? runtimeBundle.answerSyncManager : null;
        questionCacheStorage = runtimeBundle && runtimeBundle.questionCacheStorage ? runtimeBundle.questionCacheStorage : null;
        attemptUiStateStorage = runtimeBundle && runtimeBundle.attemptUiStateStorage ? runtimeBundle.attemptUiStateStorage : null;
        questionRuntimeManager = runtimeBundle && runtimeBundle.questionRuntimeManager ? runtimeBundle.questionRuntimeManager : null;
        answerInputManager = runtimeBundle && runtimeBundle.answerInputManager ? runtimeBundle.answerInputManager : null;
        navigationManager = runtimeBundle && runtimeBundle.navigationManager ? runtimeBundle.navigationManager : null;
        finishFlowManager = runtimeBundle && runtimeBundle.finishFlowManager ? runtimeBundle.finishFlowManager : null;
        questionFlags = runtimeBundle && runtimeBundle.questionFlags ? runtimeBundle.questionFlags : null;
    }

    function initializeFrontendDebug() {
        if (!config.frontendDebugUi) {
            return;
        }

        import('./core/debug-panel')
            .then(function (module) {
                if (!module || typeof module.createFrontendDebugManager !== 'function') {
                    return;
                }

                debugManager.setImplementation(module.createFrontendDebugManager({
                    config: config,
                    diagnosticsManager: diagnosticsManager,
                    documentRef: document,
                    root: root,
                    state: state,
                    windowRef: window
                }));
                debugManager.mount();
                debugManager.log('bootstrap', {
                    debugEnabled: String(Boolean(debugManager.enabled)),
                    reason: String(config.frontendDebugReason || ''),
                    source: String(config.frontendAssetSource || ''),
                    stage: String(state.stage || 'login')
                });
                debugManager.refresh();
            })
            .catch(function (error) {
                if (diagnosticsManager && diagnosticsManager.enabled) {
                    diagnosticsManager.recordError('debug-panel:load', {
                        message: error instanceof Error ? error.message : String(error || 'Failed to load debug panel.')
                    });
                }
                if (window && window.console && typeof window.console.error === 'function') {
                    window.console.error('CBT frontend debug failed to load.', error);
                }
            });
    }

    function renderFatalRuntimeError(origin, error) {
        var message = error instanceof Error && error.message
            ? error.message
            : String(error || 'Unknown frontend error.');
        var detail = error instanceof Error && error.stack
            ? String(error.stack)
            : '';

        if (debugManager) {
            debugManager.log('fatal:' + String(origin || 'runtime'), {
                message: message,
                stack: detail
            });
        }

        if (diagnosticsManager && diagnosticsManager.enabled) {
            diagnosticsManager.recordError('fatal:' + String(origin || 'runtime'), {
                message: message,
                stack: detail
            });
        }
        recordTimeline('fatal:error', message, {
            origin: String(origin || 'runtime'),
            stack: detail
        });

        root.innerHTML = [
            '<div class="cbt-app">',
            '<main class="cbt-container">',
            '<section class="cbt-card">',
            '<h3>Frontend CBT Error</h3>',
            '<p class="cbt-subtitle">Render frontend berhenti di tahap <strong>' + escapeHtml(String(origin || 'runtime')) + '</strong>.</p>',
            '<div class="cbt-alert cbt-alert-error">' + escapeHtml(message) + '</div>',
            detail !== ''
                ? '<pre style="margin-top:12px;max-width:100%;overflow:auto;padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc;font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;">' + escapeHtml(detail) + '</pre>'
                : '',
            '</section>',
            '</main>',
            '</div>'
        ].join('');
    }

    var getIndexedDb = browserStorage.getIndexedDb;
    var getLocalStorage = browserStorage.getLocalStorage;
    var getSessionStorage = browserStorage.getSessionStorage;

    function clearStorageAreaByPrefixes(area, prefixes) {
        if (!area || !Array.isArray(prefixes) || !prefixes.length) {
            return 0;
        }

        var removed = 0;
        try {
            for (var index = area.length - 1; index >= 0; index -= 1) {
                var key = typeof area.key === 'function' ? area.key(index) : '';
                if (typeof key !== 'string' || key === '') {
                    continue;
                }

                var shouldRemove = prefixes.some(function (prefix) {
                    return typeof prefix === 'string' && prefix !== '' && key.indexOf(prefix) === 0;
                });
                if (!shouldRemove) {
                    continue;
                }

                area.removeItem(key);
                removed += 1;
            }
        } catch (error) {
            return removed;
        }

        return removed;
    }

    function deleteQuestionCacheIndexedDbSilently() {
        var indexedDb = getIndexedDb();
        if (!indexedDb || typeof indexedDb.deleteDatabase !== 'function') {
            return;
        }

        try {
            indexedDb.deleteDatabase(QUESTION_CACHE_INDEXED_DB_NAME);
        } catch (error) {
            // Ignore IndexedDB cleanup failures from diagnostics toolbox.
        }
    }

    function mountDiagnosticsCommandBridge() {
        var commandKey = String(config.frontendDiagnosticsCommandKey || '');
        if (!diagnosticsManager || !diagnosticsManager.enabled || commandKey === '') {
            return;
        }

        window.addEventListener('storage', function (event) {
            if (!event || event.key !== commandKey || !event.newValue) {
                return;
            }

            var command;
            try {
                command = JSON.parse(event.newValue);
            } catch (error) {
                return;
            }

            if (!command || typeof command !== 'object') {
                return;
            }

            var action = String(command.action || '');
            if (action === '') {
                return;
            }

            if (action === 'clear-rest-logs') {
                diagnosticsManager.clearRequestLogs();
            } else if (action === 'clear-debug-snapshot') {
                diagnosticsManager.clearSnapshot();
                diagnosticsManager.clearErrors();
                diagnosticsManager.setCapturePaused(false);
            } else if (action === 'clear-sync-snapshot') {
                if (typeof diagnosticsManager.clearSyncSnapshot === 'function') {
                    diagnosticsManager.clearSyncSnapshot();
                }
            } else if (action === 'clear-timeline') {
                if (typeof diagnosticsManager.clearTimeline === 'function') {
                    diagnosticsManager.clearTimeline();
                }
            } else if (action === 'clear-render-stats') {
                if (typeof diagnosticsManager.clearRenderStats === 'function') {
                    diagnosticsManager.clearRenderStats();
                }
            } else if (action === 'clear-action-trail') {
                if (typeof diagnosticsManager.clearActionTrail === 'function') {
                    diagnosticsManager.clearActionTrail();
                }
            } else if (action === 'reset-scenarios') {
                if (typeof diagnosticsManager.resetScenarioState === 'function') {
                    diagnosticsManager.resetScenarioState();
                }
                if (answerSyncManager && typeof answerSyncManager.handleScenarioStateChange === 'function') {
                    answerSyncManager.handleScenarioStateChange();
                }
            } else if (action === 'sync-scenarios') {
                if (answerSyncManager && typeof answerSyncManager.handleScenarioStateChange === 'function') {
                    answerSyncManager.handleScenarioStateChange();
                }
            } else if (action === 'clear-auth-session') {
                clearStorageAreaByPrefixes(getSessionStorage(), [
                    AUTH_SESSION_STORAGE_KEY
                ]);
            } else if (action === 'clear-question-cache') {
                clearStorageAreaByPrefixes(getSessionStorage(), [
                    QUESTION_CACHE_SESSION_STORAGE_KEY_PREFIX
                ]);
                clearStorageAreaByPrefixes(getLocalStorage(), [
                    QUESTION_CACHE_META_LOCAL_STORAGE_KEY_PREFIX,
                    QUESTION_CACHE_ITEM_LOCAL_STORAGE_KEY_PREFIX
                ]);
                deleteQuestionCacheIndexedDbSilently();
            } else if (action === 'clear-attempt-ui-state') {
                clearStorageAreaByPrefixes(getSessionStorage(), [
                    ATTEMPT_UI_SESSION_STORAGE_KEY_PREFIX,
                    DOUBTFUL_SESSION_STORAGE_KEY_PREFIX
                ]);
            } else if (action === 'clear-frontend-browser-state') {
                clearStorageAreaByPrefixes(getLocalStorage(), [
                    String(config.frontendDiagnosticsStoragePrefix || 'cbt_exam_frontend_')
                ]);
                clearStorageAreaByPrefixes(getSessionStorage(), [
                    String(config.frontendDiagnosticsStoragePrefix || 'cbt_exam_frontend_')
                ]);
                deleteQuestionCacheIndexedDbSilently();
                diagnosticsManager.clearAll();
            } else {
                return;
            }

            if (debugManager && debugManager.enabled) {
                debugManager.log('developer-command', {
                    action: action
                });
                debugManager.refresh();
            }

            var shouldRenderAfterCommand = action === 'clear-auth-session'
                || action === 'clear-question-cache'
                || action === 'clear-attempt-ui-state'
                || action === 'clear-sync-snapshot'
                || action === 'clear-timeline'
                || action === 'reset-scenarios'
                || action === 'sync-scenarios';

            if (shouldRenderAfterCommand && renderCycleManager && typeof renderCycleManager.render === 'function') {
                renderCycleManager.render();
            } else if (shouldRenderAfterCommand && typeof render === 'function') {
                render();
            }
        });
    }

    initializeFrontendDebug();
    setBootProgress(18, 'Memuat konfigurasi frontend', 'Loading frontend runtime');
    recordTimeline('bootstrap', 'Frontend CBT bootstrap dimulai.', {
        source: String(config.frontendAssetSource || 'Production Build')
    });

    var uiPreferencesManager = createUiPreferencesManager({
        getLocalStorage: getLocalStorage,
        root: root,
        state: state,
        storageKey: UI_PREF_STORAGE_KEY,
        windowRef: window
    });
    setBootProgress(34, 'Menyiapkan state aplikasi', 'Preparing application state');
    appMetaManager = createAppMetaManager({
        config: config,
        escapeHtml: escapeHtml,
        state: state,
        windowRef: window
    });
    var clearMessages = appMetaManager.clearMessages;
    var findExamById = appMetaManager.findExamById;
    var getConfiguredPluginAuthor = appMetaManager.getConfiguredPluginAuthor;
    var getConfiguredPluginVersion = appMetaManager.getConfiguredPluginVersion;
    var getConfiguredSchoolLogoUrl = appMetaManager.getConfiguredSchoolLogoUrl;
    var getConfiguredSchoolMotto = appMetaManager.getConfiguredSchoolMotto;
    var getConfiguredSchoolName = appMetaManager.getConfiguredSchoolName;
    var getCurrentUserName = appMetaManager.getCurrentUserName;
    var getCurrentUserPhoto = appMetaManager.getCurrentUserPhoto;
    var getCurrentUserRole = appMetaManager.getCurrentUserRole;
    var getExamFooterSyncMeta = appMetaManager.getExamFooterSyncMeta;
    var getIdleDetectionThresholdSeconds = appMetaManager.getIdleDetectionThresholdSeconds;
    var getLoginHeroSchoolBranding = appMetaManager.getLoginHeroSchoolBranding;
    var getNavigatorConnectionStatus = appMetaManager.getNavigatorConnectionStatus;
    var getSelectedExam = appMetaManager.getSelectedExam;
    var getSyncStatusAlertMeta = appMetaManager.getSyncStatusAlertMeta;
    var getUserInitial = appMetaManager.getUserInitial;
    var isConnectionOffline = appMetaManager.isConnectionOffline;
    var isBrowserInspectionShortcutBlockingEnabled = appMetaManager.isBrowserInspectionShortcutBlockingEnabled;
    var isExamCopyPasteBlocked = appMetaManager.isExamCopyPasteBlocked;
    var isExamFullscreenRequired = appMetaManager.isExamFullscreenRequired;
    var isHeartbeatLostDetectionEnabled = appMetaManager.isHeartbeatLostDetectionEnabled;
    var isIdleDetectionEnabled = appMetaManager.isIdleDetectionEnabled;
    var isSecurityLoggingActiveForAttempt = appMetaManager.isSecurityLoggingActiveForAttempt;
    var isSecurityLoggingEnabled = appMetaManager.isSecurityLoggingEnabled;
    var renderAlert = appMetaManager.renderAlert;
    var renderExamRichHtml = appMetaManager.renderExamRichHtml;
    var safeRichHtml = appMetaManager.safeRichHtml;
    var authSessionManager = createAuthSessionManager({
        getSessionStorage: getSessionStorage,
        state: state,
        storageKey: AUTH_SESSION_STORAGE_KEY
    });
    var doubtfulStateStorage = createDoubtfulStateStorage({
        getSessionStorage: getSessionStorage,
        state: state,
        storageKeyPrefix: DOUBTFUL_SESSION_STORAGE_KEY_PREFIX
    });
    var examSecurityManager = createExamSecurityManager({
        clearMessages: clearMessages,
        documentRef: document,
        escapeHtml: escapeHtml,
        exitNativeFullscreen: function () {
            if (fullscreenStateManager && typeof fullscreenStateManager.exitNativeFullscreen === 'function') {
                return fullscreenStateManager.exitNativeFullscreen.apply(fullscreenStateManager, arguments);
            }
            return Promise.resolve(false);
        },
        isBrowserInspectionShortcutBlockingEnabled: isBrowserInspectionShortcutBlockingEnabled,
        isExamCopyPasteBlocked: isExamCopyPasteBlocked,
        isExamFullscreenRequired: isExamFullscreenRequired,
        isSecurityLoggingActiveForAttempt: isSecurityLoggingActiveForAttempt,
        requestNativeFullscreen: function () {
            if (fullscreenStateManager && typeof fullscreenStateManager.requestNativeFullscreen === 'function') {
                return fullscreenStateManager.requestNativeFullscreen.apply(fullscreenStateManager, arguments);
            }
            return Promise.resolve(false);
        },
        root: root,
        sendSecurityEventSilently: function () {
            if (securityLoggingManager && typeof securityLoggingManager.sendSecurityEventSilently === 'function') {
                return securityLoggingManager.sendSecurityEventSilently.apply(securityLoggingManager, arguments);
            }
            return false;
        },
        setNativeFullscreenActive: function () {
            if (fullscreenStateManager && typeof fullscreenStateManager.setNativeFullscreenActive === 'function') {
                return fullscreenStateManager.setNativeFullscreenActive.apply(fullscreenStateManager, arguments);
            }
            return false;
        },
        state: state,
        syncFullscreenState: function () {
            if (typeof syncFullscreenState === 'function') {
                return syncFullscreenState.apply(null, arguments);
            }
            return undefined;
        },
        windowRef: window
    });
    var applyUiPreferences = uiPreferencesManager.applyUiPreferences;
    var formatFontScaleLabel = uiPreferencesManager.formatFontScaleLabel;
    var getEffectiveCalculatorPanelPosition = uiPreferencesManager.getEffectiveCalculatorPanelPosition;
    var getEffectiveNavPanelPosition = uiPreferencesManager.getEffectiveNavPanelPosition;
    var isCompactNavViewport = uiPreferencesManager.isCompactNavViewport;
    var isCompactViewport = uiPreferencesManager.isCompactViewport;
    var normalizeCalculatorPanelPosition = uiPreferencesManager.normalizeCalculatorPanelPosition;
    var normalizeFontScale = uiPreferencesManager.normalizeFontScale;
    var normalizeNavPanelPosition = uiPreferencesManager.normalizeNavPanelPosition;
    var normalizeTheme = uiPreferencesManager.normalizeTheme;
    var persistUiPreferences = uiPreferencesManager.persistUiPreferences;
    var readPersistedUiPreferences = uiPreferencesManager.readPersistedUiPreferences;
    var toggleTheme = uiPreferencesManager.toggleTheme;
    var updateCalculatorPanelPosition = uiPreferencesManager.updateCalculatorPanelPosition;
    var updateFontScale = uiPreferencesManager.updateFontScale;
    var updateNavPanelPosition = uiPreferencesManager.updateNavPanelPosition;
    var clearMessages = appMetaManager.clearMessages;
    var findExamById = appMetaManager.findExamById;
    var clearPersistedAuthSession = authSessionManager.clearPersistedAuthSession;
    var persistAuthSession = authSessionManager.persistAuthSession;
    var readPersistedAuthSession = authSessionManager.readPersistedAuthSession;
    var buildDoubtfulSessionStorageKey = doubtfulStateStorage.buildDoubtfulSessionStorageKey;
    var clearPersistedDoubtfulState = doubtfulStateStorage.clearPersistedDoubtfulState;
    var readPersistedDoubtfulState = doubtfulStateStorage.readPersistedDoubtfulState;
    var getQuestionById = bindExamRuntimeMethod('questionWindowManager', 'getQuestionById', null);
    var getQuestionAtIndex = bindExamRuntimeMethod('questionWindowManager', 'getQuestionAtIndex', null);
    var buildQuestionWindowItems = bindExamRuntimeMethod('questionWindowManager', 'buildQuestionWindowItems', function () {
        return [];
    });
    var clampQuestionIndex = bindExamRuntimeMethod('questionWindowManager', 'clampQuestionIndex', 0);
    var getQuestionCount = bindExamRuntimeMethod('questionWindowManager', 'getQuestionCount', 0);
    var getQuestionDisplayNumber = bindExamRuntimeMethod('questionWindowManager', 'getQuestionDisplayNumber', function (question, fallbackIndex) {
        var safeFallbackIndex = Math.floor(Number(fallbackIndex) || 0);
        return Math.max(1, safeFallbackIndex + 1);
    });
    var getQuestionDisplayNumberById = bindExamRuntimeMethod('questionWindowManager', 'getQuestionDisplayNumberById', function (questionId, fallbackIndex) {
        return getQuestionDisplayNumber({
            id: questionId
        }, fallbackIndex);
    });
    var getQuestionIdAtIndex = bindExamRuntimeMethod('questionWindowManager', 'getQuestionIdAtIndex', 0);
    var getQuestionManifestById = bindExamRuntimeMethod('questionWindowManager', 'getQuestionManifestById', null);
    var getQuestionPayloadById = bindExamRuntimeMethod('questionWindowManager', 'getQuestionPayloadById', null);
    var clearQuestionPrefetchRuntimeState = bindExamRuntimeMethod('questionWindowManager', 'clearQuestionPrefetchRuntimeState', undefined);
    var isIndexInCurrentWindow = bindExamRuntimeMethod('questionWindowManager', 'isIndexInCurrentWindow', false);
    var isQuestionPayloadLoaded = bindExamRuntimeMethod('questionWindowManager', 'isQuestionPayloadLoaded', false);
    var isQuestionWindowLoaded = bindExamRuntimeMethod('questionWindowManager', 'isQuestionWindowLoaded', false);
    var markQuestionWindowLoaded = bindExamRuntimeMethod('questionWindowManager', 'markQuestionWindowLoaded', undefined);
    var noteQuestionPrefetchActivity = bindExamRuntimeMethod('questionWindowManager', 'noteQuestionPrefetchActivity', undefined);
    var prefetchNextQuestionBatch = bindExamRuntimeMethod('questionWindowManager', 'prefetchNextQuestionBatch', undefined);
    var questionWindowOffsetForIndex = bindExamRuntimeMethod('questionWindowManager', 'questionWindowOffsetForIndex', function (index, windowSize) {
        var safeWindowSize = Math.max(1, Number(windowSize) || QUESTION_WINDOW_SIZE);
        var safeIndex = Math.max(0, Math.floor(Number(index) || 0));
        return Math.floor(safeIndex / safeWindowSize) * safeWindowSize;
    });
    var renderQuestionPrefetchIndicator = bindExamRuntimeMethod('questionWindowManager', 'renderQuestionPrefetchIndicator', '');
    var resetQuestionPrefetchIdleTimer = bindExamRuntimeMethod('questionWindowManager', 'resetQuestionPrefetchIdleTimer', undefined);
    var setActiveQuestionWindowForIndex = bindExamRuntimeMethod('questionWindowManager', 'setActiveQuestionWindowForIndex', undefined);
    var setQuestionWindowFromLoadedPayloads = bindExamRuntimeMethod('questionWindowManager', 'setQuestionWindowFromLoadedPayloads', false);
    var updateQuestionPrefetchIndicator = bindExamRuntimeMethod('questionWindowManager', 'updateQuestionPrefetchIndicator', undefined);
    var validAttemptQuestionIds = bindExamRuntimeMethod('questionWindowManager', 'validAttemptQuestionIds', function () {
        return {};
    });

    var clearPersistedQuestionCache = bindExamRuntimeMethod('questionCacheStorage', 'clearPersistedQuestionCache', undefined);
    var normalizeQuestionRevision = bindExamRuntimeMethod('questionCacheStorage', 'normalizeQuestionRevision', function (revision, fallbackExamId) {
        return buildFallbackQuestionRevision(revision, fallbackExamId || state.selectedExamId || 0);
    });
    var questionOrderSignatureEquals = bindExamRuntimeMethod('questionCacheStorage', 'questionOrderSignatureEquals', function (leftSignature, rightSignature) {
        return String(leftSignature || '').trim() === String(rightSignature || '').trim();
    });
    var questionRevisionEquals = bindExamRuntimeMethod('questionCacheStorage', 'questionRevisionEquals', function (leftRevision, rightRevision, fallbackExamId) {
        return questionRevisionSignature(leftRevision, fallbackExamId) === questionRevisionSignature(rightRevision, fallbackExamId);
    });
    var readPersistedQuestionCache = bindExamRuntimeMethod('questionCacheStorage', 'readPersistedQuestionCache', function () {
        return Promise.resolve(null);
    });
    var persistCurrentQuestionCacheLocally = bindExamRuntimeMethod('questionCacheStorage', 'persistCurrentQuestionCacheLocally', undefined);

    var applyAttemptUiState = bindExamRuntimeMethod('attemptUiStateStorage', 'applyAttemptUiState', undefined);
    var buildAttemptUiStateSnapshot = bindExamRuntimeMethod('attemptUiStateStorage', 'buildAttemptUiStateSnapshot', null);
    var choosePreferredAttemptUiState = bindExamRuntimeMethod('attemptUiStateStorage', 'choosePreferredAttemptUiState', function (remoteSnapshot, localSnapshot) {
        return localSnapshot || remoteSnapshot || null;
    });
    var clearPersistedAttemptUiState = bindExamRuntimeMethod('attemptUiStateStorage', 'clearPersistedAttemptUiState', undefined);
    var persistAttemptUiStateLocally = bindExamRuntimeMethod('attemptUiStateStorage', 'persistAttemptUiStateLocally', undefined);
    var persistCurrentAttemptUiStateLocally = bindExamRuntimeMethod('attemptUiStateStorage', 'persistCurrentAttemptUiStateLocally', undefined);
    var readPersistedAttemptUiState = bindExamRuntimeMethod('attemptUiStateStorage', 'readPersistedAttemptUiState', null);

    var clearAutoSaveRuntimeState = bindExamRuntimeMethod('answerSyncManager', 'clearAutoSaveRuntimeState', undefined);
    var flushPendingAnswerBatch = bindExamRuntimeMethod('answerSyncManager', 'flushPendingAnswerBatch', function () {
        return Promise.resolve(null);
    });
    var handleRecoverableAnswerSyncFailure = bindExamRuntimeMethod('answerSyncManager', 'handleRecoverableAnswerSyncFailure', undefined);
    var hasAnswerBatchFlushInFlight = bindExamRuntimeMethod('answerSyncManager', 'hasFlushInFlight', false);
    var initializeSubmittedPayloadCache = bindExamRuntimeMethod('answerSyncManager', 'initializeSubmittedPayloadCache', undefined);
    var isNetworkConnectivityError = bindExamRuntimeMethod('answerSyncManager', 'isNetworkConnectivityError', false);
    var isRetryableAnswerSyncError = bindExamRuntimeMethod('answerSyncManager', 'isRetryableAnswerSyncError', false);
    var getQuestionSaveFeedback = bindExamRuntimeMethod('answerSyncManager', 'getQuestionSaveFeedback', function () {
        return null;
    });
    var queueLoadedQuestionAnswersForFlush = bindExamRuntimeMethod('answerSyncManager', 'queueLoadedQuestionAnswersForFlush', 0);
    var schedulePendingAnswerRetry = bindExamRuntimeMethod('answerSyncManager', 'schedulePendingAnswerRetry', undefined);
    var setConnectionStatus = bindExamRuntimeMethod('answerSyncManager', 'setConnectionStatus', undefined);
    var syncPendingAnswerRuntimeState = bindExamRuntimeMethod('answerSyncManager', 'syncPendingAnswerRuntimeState', undefined);
    var isAnswerSubmitPath = bindExamRuntimeMethod('answerSyncManager', 'isAnswerSubmitPath', false);

    var clearPendingRevisionSafeAnswerRestoreState = bindExamRuntimeMethod('questionStateManager', 'clearPendingRevisionSafeAnswerRestoreState', undefined);
    var applyPendingRevisionSafeAnswersForLoadedQuestions = bindExamRuntimeMethod('questionStateManager', 'applyPendingRevisionSafeAnswersForLoadedQuestions', undefined);
    var captureRevisionSafeLocalAnswers = bindExamRuntimeMethod('questionStateManager', 'captureRevisionSafeLocalAnswers', function () {
        return {};
    });
    var hasUsableLocalAnswerForQuestion = bindExamRuntimeMethod('questionStateManager', 'hasUsableLocalAnswerForQuestion', false);
    var mergeExistingAnswersFromQuestionItems = bindExamRuntimeMethod('questionStateManager', 'mergeExistingAnswersFromQuestionItems', function () {
        return {};
    });
    var mergeExistingAnswersMap = bindExamRuntimeMethod('questionStateManager', 'mergeExistingAnswersMap', function () {
        return {};
    });
    var normalizeExistingAnswerForQuestion = bindExamRuntimeMethod('questionStateManager', 'normalizeExistingAnswerForQuestion', null);
    var resolveStoredAnswerValueForQuestion = bindExamRuntimeMethod('questionStateManager', 'resolveStoredAnswerValueForQuestion', null);
    var payloadSignature = bindExamRuntimeMethod('questionStateManager', 'payloadSignature', '');
    var prunePendingRevisionSafeAnswerRestoreState = bindExamRuntimeMethod('questionStateManager', 'prunePendingRevisionSafeAnswerRestoreState', undefined);
    var questionAnswerPayload = bindExamRuntimeMethod('questionStateManager', 'questionAnswerPayload', null);
    var restoreLocalAnswerFromQuestion = bindExamRuntimeMethod('questionStateManager', 'restoreLocalAnswerFromQuestion', null);
    var restoreRevisionSafeLocalAnswers = bindExamRuntimeMethod('questionStateManager', 'restoreRevisionSafeLocalAnswers', undefined);

    var clearQuestionCachePersistTimer = bindExamRuntimeMethod('questionRuntimeManager', 'clearQuestionCachePersistTimer', undefined);
    var ensureQuestionWindowForIndex = bindExamRuntimeMethod('questionRuntimeManager', 'ensureQuestionWindowForIndex', function () {
        return Promise.resolve(false);
    });
    var getChangedQuestionCount = bindExamRuntimeMethod('questionRuntimeManager', 'getChangedQuestionCount', function () {
        return Object.keys(state.changedQuestionLookup || {}).length;
    });
    var getQuestionRevisionMarkerCount = bindExamRuntimeMethod('questionRuntimeManager', 'getQuestionRevisionMarkerCount', function () {
        return Object.keys(state.questionRevisionMarkerLookup || {}).length;
    });
    var isQuestionRevisionRefreshActive = bindExamRuntimeMethod('questionRuntimeManager', 'isQuestionRevisionRefreshActive', false);
    var loadQuestionWindow = bindExamRuntimeMethod('questionRuntimeManager', 'loadQuestionWindow', function () {
        return Promise.reject(new Error('Runtime ujian belum siap.'));
    });
    var refreshAttemptQuestionRevision = bindExamRuntimeMethod('questionRuntimeManager', 'refreshAttemptQuestionRevision', function () {
        return Promise.resolve(null);
    });
    var resetQuestionDataState = bindExamRuntimeMethod('questionRuntimeManager', 'resetQuestionDataState', resetQuestionDataStateFallback);
    var setQuestionRevision = bindExamRuntimeMethod('questionRuntimeManager', 'setQuestionRevision', function (revision, fallbackExamId) {
        state.questionRevision = normalizeQuestionRevision(revision, fallbackExamId || state.selectedExamId || 0);
        return state.questionRevision;
    });

    var closeFinishConfirmModal = bindExamRuntimeMethod('finishFlowManager', 'closeFinishConfirmModal', undefined);
    var handleFinish = bindExamRuntimeMethod('finishFlowManager', 'handleFinish', undefined);
    var maybeFinalizeLockedExam = bindExamRuntimeMethod('finishFlowManager', 'maybeFinalizeLockedExam', undefined);
    var openFinishConfirmModal = bindExamRuntimeMethod('finishFlowManager', 'openFinishConfirmModal', undefined);

    var getExamProgressSummary = bindExamRuntimeMethod('navigationManager', 'getExamProgressSummary', function () {
        var totalQuestions = Math.max(
            Array.isArray(state.questionOrderIds) ? state.questionOrderIds.length : 0,
            Number(state.totalQuestions) || 0
        );
        var doubtfulCount = Object.keys(state.doubtful || {}).reduce(function (count, key) {
            return count + (((Number(key) || 0) > 0 && state.doubtful[key]) ? 1 : 0);
        }, 0);
        return {
            answeredCount: 0,
            doubtfulCount: doubtfulCount,
            totalQuestions: totalQuestions,
            unansweredCount: Math.max(0, totalQuestions)
        };
    });
    var getNavigationQuestionEntries = bindExamRuntimeMethod('navigationManager', 'getNavigationQuestionEntries', function () {
        return [];
    });
    var goToQuestion = bindExamRuntimeMethod('navigationManager', 'goToQuestion', undefined);
    var handleNavigationAction = bindExamRuntimeMethod('navigationManager', 'handleAction', undefined);
    var handleArrowNavigationKey = bindExamRuntimeMethod('navigationManager', 'handleArrowNavigationKey', undefined);
    var isQuestionAnswered = bindExamRuntimeMethod('navigationManager', 'isQuestionAnswered', false);
    var navigationQuestionFilterEmptyMessage = bindExamRuntimeMethod('navigationManager', 'navigationQuestionFilterEmptyMessage', '');
    var normalizeNavigationQuestionFilter = bindExamRuntimeMethod('navigationManager', 'normalizeNavigationQuestionFilter', function () {
        return NAV_QUESTION_FILTER_ALL;
    });
    var renderNavigationAnswerBadges = bindExamRuntimeMethod('navigationManager', 'renderNavigationAnswerBadges', '');
    var renderNavigationQuestionTypeBadge = bindExamRuntimeMethod('navigationManager', 'renderNavigationQuestionTypeBadge', '');

    var handleAnswerChangeTarget = bindExamRuntimeMethod('answerInputManager', 'handleChangeTarget', undefined);
    var handleAnswerInputTarget = bindExamRuntimeMethod('answerInputManager', 'handleInputTarget', undefined);

    var isQuestionChanged = bindExamRuntimeMethod('questionFlags', 'isQuestionChanged', function (questionId) {
        var safeQuestionId = Number(questionId) || 0;
        return safeQuestionId > 0 && !!(state.changedQuestionLookup && state.changedQuestionLookup[safeQuestionId]);
    });
    var isQuestionRevisionMarked = bindExamRuntimeMethod('questionFlags', 'isQuestionRevisionMarked', function (questionId) {
        var safeQuestionId = Number(questionId) || 0;
        return safeQuestionId > 0 && !!(state.questionRevisionMarkerLookup && state.questionRevisionMarkerLookup[safeQuestionId]);
    });
    var isQuestionDoubtful = bindExamRuntimeMethod('questionFlags', 'isQuestionDoubtful', function (questionId) {
        var safeQuestionId = Number(questionId) || 0;
        return safeQuestionId > 0 && !!(state.doubtful && state.doubtful[safeQuestionId]);
    });

    var apiClient = createApiClient({
        config: config,
        diagnosticsManager: diagnosticsManager,
        expireAuthSession: function (message) {
            if (sessionLifecycleManager) {
                sessionLifecycleManager.expireAuthSession(message);
            }
        },
        fetchImpl: fetch,
        getNavigatorConnectionStatus: function () {
            if (typeof getNavigatorConnectionStatus === 'function') {
                return getNavigatorConnectionStatus();
            }
            return (window.navigator && window.navigator.onLine === false) ? 'offline' : 'online';
        },
        isAnswerSubmitPath: isAnswerSubmitPath,
        schedulePendingAnswerRetry: schedulePendingAnswerRetry,
        setConnectionStatus: setConnectionStatus,
        state: state,
        windowRef: window
    });
    var lifecycleManager = createLifecycleManager({
        cancelScheduledTabHiddenSecurityLog: function () {
            if (typeof cancelScheduledTabHiddenSecurityLog === 'function') {
                return cancelScheduledTabHiddenSecurityLog.apply(null, arguments);
            }
            return undefined;
        },
        cancelScheduledWindowBlurSecurityLog: function () {
            if (typeof cancelScheduledWindowBlurSecurityLog === 'function') {
                return cancelScheduledWindowBlurSecurityLog.apply(null, arguments);
            }
            return undefined;
        },
        documentRef: document,
        fitLoginHeroSchoolName: fitLoginHeroSchoolName,
        flushAttemptUiStateSilently: function () {
            if (typeof flushAttemptUiStateSilently === 'function') {
                return flushAttemptUiStateSilently.apply(null, arguments);
            }
            return undefined;
        },
        flushPendingAnswerBatchSilently: function () {
            if (typeof flushPendingAnswerBatchSilently === 'function') {
                return flushPendingAnswerBatchSilently.apply(null, arguments);
            }
            return undefined;
        },
        getCompactViewportState: function () {
            return compactViewportState;
        },
        isCompactViewport: uiPreferencesManager.isCompactViewport,
        isWindowBlurLoggingActiveForAttempt: function () {
            if (typeof isWindowBlurLoggingActiveForAttempt === 'function') {
                return isWindowBlurLoggingActiveForAttempt.apply(null, arguments);
            }
            return false;
        },
        logPageLeaveSecurityEvent: function () {
            if (typeof logPageLeaveSecurityEvent === 'function') {
                return logPageLeaveSecurityEvent.apply(null, arguments);
            }
            return undefined;
        },
        persistCurrentQuestionCacheLocally: persistCurrentQuestionCacheLocally,
        recordActionTrail: recordActionTrail,
        render: render,
        runSessionHeartbeat: function () {
            if (typeof runSessionHeartbeat === 'function') {
                return runSessionHeartbeat.apply(null, arguments);
            }
            return Promise.resolve(null);
        },
        scheduleNavigationGridLayout: scheduleNavigationGridLayout,
        scheduleTabHiddenSecurityLog: function () {
            if (typeof scheduleTabHiddenSecurityLog === 'function') {
                return scheduleTabHiddenSecurityLog.apply(null, arguments);
            }
            return undefined;
        },
        scheduleWindowBlurSecurityLog: function () {
            if (typeof scheduleWindowBlurSecurityLog === 'function') {
                return scheduleWindowBlurSecurityLog.apply(null, arguments);
            }
            return undefined;
        },
        setCompactViewportState: function (nextState) {
            compactViewportState = !!nextState;
        },
        setConnectionStatus: setConnectionStatus,
        state: state,
        triggerPendingSyncLifecycleRetry: function () {
            if (typeof triggerPendingSyncLifecycleRetry === 'function') {
                return triggerPendingSyncLifecycleRetry.apply(null, arguments);
            }
            return undefined;
        },
        windowRef: window
    });
    var api = apiClient.api;
    var apiErrorMessage = apiClient.apiErrorMessage;
    var buildUrl = apiClient.buildUrl;
    securityLoggingManager = createSecurityLoggingManager({
        buildSecurityClientContext: buildSecurityClientContext,
        buildUrl: buildUrl,
        documentRef: document,
        fetchImpl: fetch,
        isExamFullscreenRequired: isExamFullscreenRequired,
        isSecurityLoggingActiveForAttempt: isSecurityLoggingActiveForAttempt,
        isSecurityLoggingEnabled: isSecurityLoggingEnabled,
        recordTimeline: recordTimeline,
        state: state,
        windowBlurLogDelayMs: WINDOW_BLUR_LOG_DELAY_MS,
        windowRef: window
    });
    var nativeBridgeManager = createNativeBridgeManager({
        buildUrl: buildUrl,
        isSecurityLoggingEnabled: isSecurityLoggingEnabled,
        readPersistedAuthSession: readPersistedAuthSession,
        state: state,
        windowRef: window
    });
    nativeBridgeManager.mount();
    nativeBridgeManager.sync('mount');
    var idleDetectionManager = createIdleDetectionManager({
        documentRef: document,
        getIdleThresholdSeconds: getIdleDetectionThresholdSeconds,
        getQuestionDisplayNumber: getQuestionDisplayNumber,
        isExamFullscreenBlockingActive: function () {
            return typeof isExamFullscreenBlockingActive === 'function'
                ? isExamFullscreenBlockingActive()
                : false;
        },
        isIdleDetectionEnabled: isIdleDetectionEnabled,
        isSecurityLoggingEnabled: isSecurityLoggingEnabled,
        sendSecurityEventSilently: securityLoggingManager.sendSecurityEventSilently,
        state: state,
        windowRef: window
    });
    var cancelScheduledTabHiddenSecurityLog = securityLoggingManager.cancelScheduledTabHiddenSecurityLog;
    var cancelScheduledWindowBlurSecurityLog = securityLoggingManager.cancelScheduledWindowBlurSecurityLog;
    var clearSecurityLoggingRuntimeState = securityLoggingManager.clearRuntimeState;
    var clearIdleDetectionRuntimeState = idleDetectionManager.clearRuntimeState;
    var syncIdleDetectionState = idleDetectionManager.syncState;
    var clearSecurityRuntimeState = function () {
        clearSecurityLoggingRuntimeState();
        clearIdleDetectionRuntimeState();
    };
    var isWindowBlurLoggingActiveForAttempt = securityLoggingManager.isWindowBlurLoggingActiveForAttempt;
    var logPageLeaveSecurityEvent = securityLoggingManager.logPageLeaveSecurityEvent;
    var scheduleTabHiddenSecurityLog = securityLoggingManager.scheduleTabHiddenSecurityLog;
    var scheduleWindowBlurSecurityLog = securityLoggingManager.scheduleWindowBlurSecurityLog;
    var sendLogoutRequestSilently = securityLoggingManager.sendLogoutRequestSilently;
    var sendSecurityEventSilently = securityLoggingManager.sendSecurityEventSilently;
    var exitFullscreenSilently = examSecurityManager.exitFullscreenSilently;
    var handleBlockedBrowserInspectionShortcutAction = examSecurityManager.handleBlockedBrowserInspectionShortcutAction;
    var handleBlockedClipboardAction = examSecurityManager.handleBlockedClipboardAction;
    var handleBlockedPrintAction = examSecurityManager.handleBlockedPrintAction;
    var isExamAnswerEditingLocked = examSecurityManager.isExamAnswerEditingLocked;
    var isExamClipboardBlockingActive = examSecurityManager.isExamClipboardBlockingActive;
    var isExamFullscreenBlockingActive = examSecurityManager.isExamFullscreenBlockingActive;
    var renderExamFullscreenPrompt = examSecurityManager.renderExamFullscreenPrompt;
    var requestExamFullscreen = examSecurityManager.requestExamFullscreen;
    var safeRichHtml = appMetaManager.safeRichHtml;
    attemptUiSyncManager = createAttemptUiSyncManager({
        apiRequest: function () {
            return api.apply(null, arguments);
        },
        attemptUiStateSyncDelayMs: ATTEMPT_UI_STATE_SYNC_DELAY_MS,
        buildAttemptUiStateSnapshot: buildAttemptUiStateSnapshot,
        payloadSignature: payloadSignature,
        persistAttemptUiStateLocally: persistAttemptUiStateLocally,
        state: state,
        windowRef: window
    });
    var clearAttemptUiStateSyncTimer = attemptUiSyncManager.clearTimer;
    var flushAttemptUiState = attemptUiSyncManager.flush;
    var scheduleAttemptUiStateSync = attemptUiSyncManager.scheduleSync;
    var syncAttemptUiStateSignatureToCurrentState = attemptUiSyncManager.syncSignatureToCurrentState;
    var applyPersistedQuestionCache = bindExamRuntimeMethod('questionRuntimeManager', 'applyPersistedQuestionCache', false);
    var bumpQuestionDataGeneration = bindExamRuntimeMethod('questionRuntimeManager', 'bumpQuestionDataGeneration', function () {
        return 1;
    });
    var clearStickyQuestionRevisionNotice = bindExamRuntimeMethod('questionRuntimeManager', 'clearStickyQuestionRevisionNotice', undefined);
    var acknowledgeQuestionRevisionMarker = bindExamRuntimeMethod('questionRuntimeManager', 'acknowledgeQuestionRevisionMarker', undefined);
    sessionLifecycleManager = createSessionLifecycleManager({
        bumpQuestionDataGeneration: bumpQuestionDataGeneration,
        clearAttemptUiStateSyncTimer: clearAttemptUiStateSyncTimer,
        clearAttemptUiSyncRuntimeState: function () {
            attemptUiSyncManager.clearRuntimeState();
        },
        clearAutoSaveRuntimeState: clearAutoSaveRuntimeState,
        clearMessages: clearMessages,
        clearPendingRevisionSafeAnswerRestoreState: clearPendingRevisionSafeAnswerRestoreState,
        clearPersistedAttemptUiState: clearPersistedAttemptUiState,
        clearPersistedAuthSession: clearPersistedAuthSession,
        clearPersistedQuestionCache: clearPersistedQuestionCache,
        clearQuestionCachePersistTimer: clearQuestionCachePersistTimer,
        clearQuestionPrefetchRuntimeState: clearQuestionPrefetchRuntimeState,
        clearQuestionRevisionRefreshState: function () {
            if (questionRuntimeManager && typeof questionRuntimeManager.clearQuestionRevisionRefreshState === 'function') {
                questionRuntimeManager.clearQuestionRevisionRefreshState();
            }
        },
        clearSecurityLoggingRuntimeState: clearSecurityRuntimeState,
        exitFullscreenSilently: exitFullscreenSilently,
        flushAttemptUiState: flushAttemptUiState,
        flushPendingAnswerBatch: flushPendingAnswerBatch,
        formatSeconds: formatSeconds,
        handleFinish: function (autoSubmit, options) {
            if (finishFlowManager) {
                return finishFlowManager.handleFinish(autoSubmit, options);
            }
            return undefined;
        },
        queueLoadedQuestionAnswersForFlush: queueLoadedQuestionAnswersForFlush,
        recordTimeline: recordTimeline,
        render: render,
        resetQuestionDataState: resetQuestionDataState,
        root: root,
        logoutSyncTimeoutMs: 8000,
        sendLogoutRequestSilently: sendLogoutRequestSilently,
        state: state,
        stopSessionHeartbeat: function () {
            if (typeof stopSessionHeartbeat === 'function') {
                return stopSessionHeartbeat.apply(null, arguments);
            }
            return undefined;
        },
        windowRef: window
    });
    var applyAttemptTimerPayload = sessionLifecycleManager.applyAttemptTimerPayload;
    var clearAuthenticatedFrontendState = sessionLifecycleManager.clearAuthenticatedFrontendState;
    var expireAuthSession = sessionLifecycleManager.expireAuthSession;
    var fullLogout = sessionLifecycleManager.fullLogout;
    var resetExamSession = sessionLifecycleManager.resetExamSession;
    var startTimer = sessionLifecycleManager.startTimer;
    var stopTimer = sessionLifecycleManager.stopTimer;
    var updateTimerLabel = sessionLifecycleManager.updateTimerLabel;
    sessionHeartbeatManager = createSessionHeartbeatManager({
        apiRequest: function () {
            return api.apply(null, arguments);
        },
        applyAttemptTimerPayload: applyAttemptTimerPayload,
        clearCalculatorRuntimeState: function () {
            if (stageRuntimeManager && typeof stageRuntimeManager.clearCalculatorRuntimeState === 'function') {
                return stageRuntimeManager.clearCalculatorRuntimeState.apply(stageRuntimeManager, arguments);
            }
            return undefined;
        },
        diagnosticsManager: diagnosticsManager,
        documentRef: document,
        getQuestionCount: getQuestionCount,
        isHeartbeatLostDetectionEnabled: isHeartbeatLostDetectionEnabled,
        normalizeQuestionRevision: normalizeQuestionRevision,
        questionOrderSignatureEquals: questionOrderSignatureEquals,
        questionRevisionEquals: questionRevisionEquals,
        refreshAttemptQuestionRevision: refreshAttemptQuestionRevision,
        recordActionTrail: recordActionTrail,
        recordTimeline: recordTimeline,
        render: render,
        renderExamPartial: function (regions, reason, meta) {
            if (renderCycleManager && typeof renderCycleManager.patchExamRegions === 'function') {
                return renderCycleManager.patchExamRegions(regions, reason, meta);
            }
            render(reason, meta);
            return false;
        },
        sendSecurityEventSilently: sendSecurityEventSilently,
        sessionHeartbeatIntervalMs: SESSION_HEARTBEAT_INTERVAL_MS,
        setQuestionRevision: setQuestionRevision,
        state: state,
        windowRef: window
    });
    var runSessionHeartbeat = sessionHeartbeatManager.run;
    var startSessionHeartbeat = sessionHeartbeatManager.start;
    var stopSessionHeartbeat = sessionHeartbeatManager.stop;
    examRuntimeLoader = createExamRuntimeLoader({
        formatErrorMessage: formatExamRuntimeLoadErrorMessage,
        importRuntimeBundle: function () {
            maybeRejectExamRuntimeLoadByScenario();
            return import('./exam/runtime-bundle.js');
        },
        instantiateBundle: function (module) {
            if (!module || typeof module.createExamRuntimeBundle !== 'function') {
                throw new Error('Bundle runtime ujian tidak valid.');
            }

            var runtimeBundle = module.createExamRuntimeBundle({
                answerSyncRetryBaseDelayMs: ANSWER_SYNC_RETRY_BASE_DELAY_MS,
                answerSyncRetryMaxDelayMs: ANSWER_SYNC_RETRY_MAX_DELAY_MS,
                apiRequest: function () {
                    return api.apply(null, arguments);
                },
                attemptUiSessionStorageKeyPrefix: ATTEMPT_UI_SESSION_STORAGE_KEY_PREFIX,
                attemptUiStateNavigationSyncDelayMs: ATTEMPT_UI_STATE_NAVIGATION_SYNC_DELAY_MS,
                attemptUiStateSyncDelayMs: ATTEMPT_UI_STATE_SYNC_DELAY_MS,
                autoSaveBatchMaxItems: AUTO_SAVE_BATCH_MAX_ITEMS,
                autoSaveChoiceDelayCongestedMs: AUTO_SAVE_CHOICE_DELAY_CONGESTED_MS,
                autoSaveChoiceDelayMs: AUTO_SAVE_CHOICE_DELAY_MS,
                autoSaveCongestedWindowMs: AUTO_SAVE_CONGESTED_WINDOW_MS,
                autoSaveTextDelayCongestedMs: AUTO_SAVE_TEXT_DELAY_CONGESTED_MS,
                autoSaveTextDelayMs: AUTO_SAVE_TEXT_DELAY_MS,
                buildDoubtfulSessionStorageKey: buildDoubtfulSessionStorageKey,
                clearMessages: clearMessages,
                clearPersistedDoubtfulState: clearPersistedDoubtfulState,
                clearAttemptUiStateSyncTimer: clearAttemptUiStateSyncTimer,
                diagnosticsManager: diagnosticsManager,
                documentRef: document,
                escapeHtml: escapeHtml,
                exitFullscreenSilently: exitFullscreenSilently,
                flushAttemptUiState: flushAttemptUiState,
                getIndexedDb: getIndexedDb,
                getLocalStorage: getLocalStorage,
                getNavigatorConnectionStatus: getNavigatorConnectionStatus,
                getSessionStorage: getSessionStorage,
                isExamAnswerEditingLocked: isExamAnswerEditingLocked,
                navQuestionFilterAll: NAV_QUESTION_FILTER_ALL,
                navQuestionFilterAnswered: NAV_QUESTION_FILTER_ANSWERED,
                navQuestionFilterDoubtful: NAV_QUESTION_FILTER_DOUBTFUL,
                navQuestionFilterUnanswered: NAV_QUESTION_FILTER_UNANSWERED,
                navigationQuestionTypeBadgeConfig: navigationQuestionTypeBadgeConfig,
                normalizeExamToken: normalizeExamToken,
                prefetchResultStageRenderer: function () {
                    if (stageRuntimeManager) {
                        stageRuntimeManager.prefetchResultStageRenderer();
                    }
                },
                questionCacheIndexedDbName: QUESTION_CACHE_INDEXED_DB_NAME,
                questionCacheIndexedDbStore: QUESTION_CACHE_INDEXED_DB_STORE,
                questionCacheItemLocalStorageKeyPrefix: QUESTION_CACHE_ITEM_LOCAL_STORAGE_KEY_PREFIX,
                questionCacheMetaLocalStorageKeyPrefix: QUESTION_CACHE_META_LOCAL_STORAGE_KEY_PREFIX,
                questionCacheSessionStorageKeyPrefix: QUESTION_CACHE_SESSION_STORAGE_KEY_PREFIX,
                questionPrefetchBatchSize: QUESTION_PREFETCH_BATCH_SIZE,
                questionPrefetchIdleDelayMs: QUESTION_PREFETCH_IDLE_DELAY_MS,
                questionWindowSize: QUESTION_WINDOW_SIZE,
                readPersistedDoubtfulState: readPersistedDoubtfulState,
                recordActionTrail: recordActionTrail,
                recordTimeline: recordTimeline,
                render: render,
                renderExamPartial: function (regions, reason, meta) {
                    if (renderCycleManager && typeof renderCycleManager.patchExamRegions === 'function') {
                        return renderCycleManager.patchExamRegions(regions, reason, meta);
                    }
                    render(reason, meta);
                    return false;
                },
                root: root,
                scheduleAttemptUiStateSync: scheduleAttemptUiStateSync,
                startTimer: startTimer,
                state: state,
                stopTimer: stopTimer,
                syncFullscreenState: function () {
                    if (typeof syncFullscreenState === 'function') {
                        return syncFullscreenState.apply(null, arguments);
                    }
                    return undefined;
                },
                syncAttemptUiStateSignatureToCurrentState: syncAttemptUiStateSignatureToCurrentState,
                updateSelectedExam: function () {
                    if (typeof updateSelectedExam === 'function') {
                        return updateSelectedExam.apply(null, arguments);
                    }
                    return undefined;
                },
                windowRef: window
            });

            syncExamRuntimeManagers(runtimeBundle);
            return runtimeBundle;
        },
        onLoadError: function (error, message) {
            examRuntimeLoadError = String(message || '');
            recordTimeline('chunk:exam-runtime:load:error', examRuntimeLoadError || 'Runtime ujian gagal dimuat.', {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || ''),
                target: 'exam-runtime',
                error: error instanceof Error ? {
                    message: String(error.message || ''),
                    code: String(error.code || '')
                } : null
            });
        },
        onLoadStart: function () {
            recordTimeline('chunk:exam-runtime:load:start', 'Memuat runtime ujian.', {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || ''),
                target: 'exam-runtime'
            });
        },
        onLoadSuccess: function () {
            examRuntimeLoadError = '';
            recordTimeline('chunk:exam-runtime:load:success', 'Runtime ujian siap.', {
                attemptId: Number(state.attemptId) || 0,
                stage: String(state.stage || ''),
                target: 'exam-runtime'
            });
        }
    });
    var syncLifecycleBridge = createSyncLifecycleBridge({
        flushAttemptUiState: flushAttemptUiState,
        flushPendingAnswerBatch: flushPendingAnswerBatch,
        getNavigatorConnectionStatus: getNavigatorConnectionStatus,
        maybeFinalizeLockedExam: maybeFinalizeLockedExam,
        queueLoadedQuestionAnswersForFlush: queueLoadedQuestionAnswersForFlush,
        schedulePendingAnswerRetry: schedulePendingAnswerRetry,
        setConnectionStatus: setConnectionStatus,
        state: state
    });
    var flushAttemptUiStateSilently = syncLifecycleBridge.flushAttemptUiStateSilently;
    var flushPendingAnswerBatchSilently = syncLifecycleBridge.flushPendingAnswerBatchSilently;
    var triggerPendingSyncLifecycleRetry = syncLifecycleBridge.triggerPendingSyncLifecycleRetry;
    examSessionManager = createExamSessionManager({
        apiRequest: function () {
            return api.apply(null, arguments);
        },
        applyAttemptUiState: applyAttemptUiState,
        applyPersistedQuestionCache: applyPersistedQuestionCache,
        attemptUiStateSyncDelayMs: ATTEMPT_UI_STATE_SYNC_DELAY_MS,
        bumpQuestionDataGeneration: bumpQuestionDataGeneration,
        choosePreferredAttemptUiState: choosePreferredAttemptUiState,
        clearAttemptUiStateSyncTimer: clearAttemptUiStateSyncTimer,
        clearAttemptUiSyncRuntimeState: function () {
            attemptUiSyncManager.clearRuntimeState();
        },
        clearAutoSaveRuntimeState: clearAutoSaveRuntimeState,
        clearMessages: clearMessages,
        clearPendingRevisionSafeAnswerRestoreState: clearPendingRevisionSafeAnswerRestoreState,
        clearPersistedQuestionCache: clearPersistedQuestionCache,
        clearQuestionPrefetchRuntimeState: clearQuestionPrefetchRuntimeState,
        clearQuestionRevisionRefreshState: function () {
            if (questionRuntimeManager && typeof questionRuntimeManager.clearQuestionRevisionRefreshState === 'function') {
                questionRuntimeManager.clearQuestionRevisionRefreshState();
            }
        },
        clearSecurityLoggingRuntimeState: clearSecurityLoggingRuntimeState,
        ensureExamRuntimeBundle: function (options) {
            return ensureExamRuntimeBundle(options);
        },
        ensureExamStageRenderer: function (options) {
            if (!stageRuntimeManager) {
                return Promise.reject(new Error('Runtime ujian belum siap.'));
            }
            return stageRuntimeManager.ensureExamStageRenderer(options);
        },
        ensureQuestionWindowForIndex: ensureQuestionWindowForIndex,
        examTokenLength: EXAM_TOKEN_LENGTH,
        exitFullscreenSilently: exitFullscreenSilently,
        findExamById: findExamById,
        getNavigatorConnectionStatus: getNavigatorConnectionStatus,
        getQuestionCount: getQuestionCount,
        getSelectedExam: getSelectedExam,
        initializeSubmittedPayloadCache: initializeSubmittedPayloadCache,
        isExamFullscreenRequired: isExamFullscreenRequired,
        loadQuestionWindow: loadQuestionWindow,
        maybeFinalizeLockedExam: maybeFinalizeLockedExam,
        normalizeExamToken: normalizeExamToken,
        parseDateTime: parseDateTime,
        persistAuthSession: persistAuthSession,
        persistCurrentAttemptUiStateLocally: persistCurrentAttemptUiStateLocally,
        persistCurrentQuestionCacheLocally: persistCurrentQuestionCacheLocally,
        prefetchCalculatorFeature: function () {
            if (stageRuntimeManager) {
                stageRuntimeManager.prefetchCalculatorFeature();
            }
        },
        prefetchResultStageRenderer: function () {
            if (stageRuntimeManager) {
                stageRuntimeManager.prefetchResultStageRenderer();
            }
        },
        recordActionTrail: recordActionTrail,
        recordTimeline: recordTimeline,
        queueLoadedQuestionAnswersForFlush: queueLoadedQuestionAnswersForFlush,
        questionRevisionEquals: questionRevisionEquals,
        questionWindowOffsetForIndex: questionWindowOffsetForIndex,
        questionWindowSize: QUESTION_WINDOW_SIZE,
        readPersistedAttemptUiState: readPersistedAttemptUiState,
        readPersistedQuestionCache: readPersistedQuestionCache,
        render: render,
        requestExamFullscreen: requestExamFullscreen,
        resetQuestionDataState: resetQuestionDataState,
        resetQuestionPrefetchIdleTimer: resetQuestionPrefetchIdleTimer,
        scheduleAttemptUiStateSync: scheduleAttemptUiStateSync,
        schedulePendingAnswerRetry: schedulePendingAnswerRetry,
        setConnectionStatus: setConnectionStatus,
        setQuestionRevision: setQuestionRevision,
        startSessionHeartbeat: startSessionHeartbeat,
        startTimer: startTimer,
        state: state,
        syncAttemptUiStateSignatureToCurrentState: syncAttemptUiStateSignatureToCurrentState,
        syncFullscreenState: function () {
            if (typeof syncFullscreenState === 'function') {
                return syncFullscreenState.apply(null, arguments);
            }
            return undefined;
        },
        syncPendingAnswerRuntimeState: syncPendingAnswerRuntimeState
    });
    var handleLogin = examSessionManager.handleLogin;
    var handleStartExam = examSessionManager.handleStartExam;
    var handleViewResult = examSessionManager.handleViewResult;
    var loadExams = examSessionManager.loadExams;
    var openAttemptSession = examSessionManager.openAttemptSession;
    var tryResumeActiveAttemptFromExamList = examSessionManager.tryResumeActiveAttemptFromExamList;
    authStageManager = createAuthStageManager({
        clearMessages: clearMessages,
        escapeHtml: escapeHtml,
        formatDateTime: formatDateTime,
        formatDateTimeCompact: formatDateTimeCompact,
        formatScoreValue: formatScoreValue,
        getConfiguredPluginAuthor: getConfiguredPluginAuthor,
        getConfiguredPluginVersion: getConfiguredPluginVersion,
        getConfiguredSchoolLogoUrl: getConfiguredSchoolLogoUrl,
        getConfiguredSchoolMotto: getConfiguredSchoolMotto,
        getConfiguredSchoolName: getConfiguredSchoolName,
        getCurrentUserName: getCurrentUserName,
        getCurrentUserPhoto: getCurrentUserPhoto,
        getLoginHeroSchoolBranding: getLoginHeroSchoolBranding,
        getSelectedExam: getSelectedExam,
        getUserInitial: getUserInitial,
        persistAuthSession: persistAuthSession,
        recordTimeline: recordTimeline,
        render: render,
        renderAlert: renderAlert,
        state: state
    });
    var renderConfirmStage = authStageManager.renderConfirmStage;
    var renderLoginStage = authStageManager.renderLoginStage;
    var updateSelectedExam = authStageManager.updateSelectedExam;
    appShellManager = createAppShellManager({
        escapeHtml: escapeHtml,
        fontScaleMax: FONT_SCALE_MAX,
        fontScaleMin: FONT_SCALE_MIN,
        formatFontScaleLabel: formatFontScaleLabel,
        formatScoreValue: formatScoreValue,
        formatSeconds: formatSeconds,
        getConfiguredSchoolLogoUrl: getConfiguredSchoolLogoUrl,
        getConfiguredSchoolName: getConfiguredSchoolName,
        getCurrentUserName: getCurrentUserName,
        getCurrentUserPhoto: getCurrentUserPhoto,
        getExamProgressSummary: getExamProgressSummary,
        getSelectedExam: getSelectedExam,
        getUserInitial: getUserInitial,
        renderAlert: renderAlert,
        renderConfirmStage: renderConfirmStage,
        renderExamStageShell: function () {
            return stageRuntimeManager ? stageRuntimeManager.renderExamStageShell() : '';
        },
        renderLoginStage: renderLoginStage,
        renderResultStageShell: function () {
            return stageRuntimeManager ? stageRuntimeManager.renderResultStageShell() : '';
        },
        state: state
    });
    var renderBody = appShellManager.renderBody;
    var renderFinishConfirmModal = appShellManager.renderFinishConfirmModal;
    var renderQuestionFontControls = appShellManager.renderQuestionFontControls;
    var renderRichZoomModal = appShellManager.renderRichZoomModal;
    var renderThemeToggleControl = appShellManager.renderThemeToggleControl;
    var renderTopbar = appShellManager.renderTopbar;
    var renderUserPhotoModal = appShellManager.renderUserPhotoModal;
    questionRenderManager = createQuestionRenderManager({
        escapeHtml: escapeHtml,
        isExamAnswerEditingLocked: isExamAnswerEditingLocked,
        renderExamRichHtml: renderExamRichHtml,
        resolveStoredAnswerValueForQuestion: resolveStoredAnswerValueForQuestion,
        safeRichHtml: safeRichHtml
    });
    var renderQuestionInput = questionRenderManager.renderQuestionInput;
    var renderQuestionStem = questionRenderManager.renderQuestionStem;
    stageRuntimeManager = createStageRuntimeManager({
        diagnosticsManager: diagnosticsManager,
        escapeHtml: escapeHtml,
        fontScaleDefault: FONT_SCALE_DEFAULT,
        fontScaleMax: FONT_SCALE_MAX,
        fontScaleMin: FONT_SCALE_MIN,
        formatQuestionType: formatQuestionType,
        formatScoreValue: formatScoreValue,
        getChangedQuestionCount: getChangedQuestionCount,
        getQuestionRevisionMarkerCount: getQuestionRevisionMarkerCount,
        getEffectiveCalculatorPanelPosition: getEffectiveCalculatorPanelPosition,
        getEffectiveNavPanelPosition: getEffectiveNavPanelPosition,
        getExamFooterSyncMeta: getExamFooterSyncMeta,
        getExamProgressSummary: getExamProgressSummary,
        getNavigationQuestionEntries: getNavigationQuestionEntries,
        getQuestionSaveFeedback: getQuestionSaveFeedback,
        getQuestionCount: getQuestionCount,
        getQuestionDisplayNumber: getQuestionDisplayNumber,
        getQuestionIdAtIndex: getQuestionIdAtIndex,
        getQuestionManifestById: getQuestionManifestById,
        getQuestionPayloadById: getQuestionPayloadById,
        ensureQuestionWindowForIndex: ensureQuestionWindowForIndex,
        getSelectedExam: getSelectedExam,
        isCompactNavViewport: isCompactNavViewport,
        isCompactViewport: isCompactViewport,
        isExamAnswerEditingLocked: isExamAnswerEditingLocked,
        isQuestionAnswered: isQuestionAnswered,
        isQuestionChanged: isQuestionChanged,
        isQuestionRevisionMarked: isQuestionRevisionMarked,
        isQuestionDoubtful: isQuestionDoubtful,
        navQuestionFilterAnswered: NAV_QUESTION_FILTER_ANSWERED,
        navQuestionFilterDoubtful: NAV_QUESTION_FILTER_DOUBTFUL,
        navQuestionFilterUnanswered: NAV_QUESTION_FILTER_UNANSWERED,
        navigationQuestionFilterEmptyMessage: navigationQuestionFilterEmptyMessage,
        navigationQuestionTypeBadgeConfig: navigationQuestionTypeBadgeConfig,
        normalizeCalculatorPanelPosition: normalizeCalculatorPanelPosition,
        normalizeNavigationQuestionFilter: normalizeNavigationQuestionFilter,
        questionOptionKey: questionOptionKey,
        recordTimeline: recordTimeline,
        render: render,
        renderAlert: renderAlert,
        renderExamPartial: function (regions, reason, meta) {
            if (renderCycleManager && typeof renderCycleManager.patchExamRegions === 'function') {
                return renderCycleManager.patchExamRegions(regions, reason, meta);
            }
            render(reason, meta);
            return false;
        },
        renderExamFullscreenPrompt: renderExamFullscreenPrompt,
        renderNavigationAnswerBadges: renderNavigationAnswerBadges,
        renderNavigationQuestionTypeBadge: renderNavigationQuestionTypeBadge,
        renderQuestionFontControls: renderQuestionFontControls,
        renderQuestionInput: renderQuestionInput,
        renderQuestionPrefetchIndicator: renderQuestionPrefetchIndicator,
        renderQuestionStem: renderQuestionStem,
        refreshAttemptQuestionRevision: refreshAttemptQuestionRevision,
        root: root,
        safeRichHtml: safeRichHtml,
        state: state
    });
    var ensureCalculatorFeature = stageRuntimeManager.ensureCalculatorFeature;
    var ensureExamStageRenderer = stageRuntimeManager.ensureExamStageRenderer;
    var ensureResultStageRenderer = stageRuntimeManager.ensureResultStageRenderer;
    var maybePrefetchExamRuntime = function () {
        if (stageRuntimeManager) {
            stageRuntimeManager.maybePrefetchExamRuntime();
        }
        prefetchExamRuntimeBundle();
    };
    var prefetchCalculatorFeature = stageRuntimeManager.prefetchCalculatorFeature;
    var prefetchExamStageRenderer = stageRuntimeManager.prefetchExamStageRenderer;
    var prefetchResultStageRenderer = stageRuntimeManager.prefetchResultStageRenderer;
    var renderCalculatorPanel = stageRuntimeManager.renderCalculatorPanel;
    var renderCalculatorToggleButton = stageRuntimeManager.renderCalculatorToggleButton;
    var renderExamStageShell = stageRuntimeManager.renderExamStageShell;
    var renderResultStageShell = stageRuntimeManager.renderResultStageShell;

    function scheduleNavigationGridLayout() {
        if (renderCycleManager) {
            renderCycleManager.scheduleNavigationGridLayout();
        }
    }

    function fitLoginHeroSchoolName() {
        if (renderCycleManager) {
            renderCycleManager.fitLoginHeroSchoolName();
        }
    }

    function render(reason, meta) {
        if (renderCycleManager) {
            try {
                renderCycleManager.render(reason, meta);
                nativeBridgeManager.sync(reason || 'render');
            } catch (error) {
                renderFatalRuntimeError('render', error);
            }
        }
    }

    function syncBodyStageClass() {
        if (renderCycleManager) {
            renderCycleManager.syncBodyStageClass();
        }
    }

    var fullscreenStateManager = createFullscreenStateManager({
        documentRef: document,
        render: render,
        state: state,
        windowRef: window
    });
    var syncFullscreenState = fullscreenStateManager.syncFullscreenState;

    renderCycleManager = createRenderCycleManager({
        applyUiPreferences: applyUiPreferences,
        documentRef: document,
        enhanceRichMath: enhanceRichMath,
        getEffectiveNavPanelPosition: getEffectiveNavPanelPosition,
        maybePrefetchExamRuntime: maybePrefetchExamRuntime,
        recordRenderPerformed: function (reason, meta, stage) {
            if (diagnosticsManager && diagnosticsManager.enabled && typeof diagnosticsManager.recordRenderPerformed === 'function') {
                diagnosticsManager.recordRenderPerformed(reason, meta, stage);
            }
        },
        recordRenderScheduled: function (reason, meta, stage) {
            if (diagnosticsManager && diagnosticsManager.enabled && typeof diagnosticsManager.recordRenderScheduled === 'function') {
                diagnosticsManager.recordRenderScheduled(reason, meta, stage);
            }
        },
        recordTimeline: recordTimeline,
        recordRuntimeSnapshot: function (snapshot) {
            if (diagnosticsManager && diagnosticsManager.enabled) {
                diagnosticsManager.recordRuntimeSnapshot(Object.assign({
                    frontendAssetSource: String(config.frontendAssetSource || 'Production Build')
                }, snapshot || {}));
            }
        },
        refreshDebugPanel: function () {
            if (debugManager) {
                debugManager.refresh();
            }
        },
        renderExamRegions: function () {
            return stageRuntimeManager ? stageRuntimeManager.renderExamRegions() : null;
        },
        renderBody: renderBody,
        renderFinishConfirmModal: renderFinishConfirmModal,
        renderRichZoomModal: renderRichZoomModal,
        renderTopbar: renderTopbar,
        renderUserPhotoModal: renderUserPhotoModal,
        root: root,
        state: state,
        syncIdleDetectionState: syncIdleDetectionState,
        updateQuestionPrefetchIndicator: updateQuestionPrefetchIndicator,
        updateTimerLabel: updateTimerLabel,
        windowRef: window
    });
    setBootProgress(68, 'Merangkai modul antarmuka', 'Preparing interface modules');

    appEventManager = createAppEventManager({
        clearMessages: clearMessages,
        closeFinishConfirmModal: closeFinishConfirmModal,
        debugManager: debugManager,
        documentRef: document,
        flushAttemptUiStateSilently: flushAttemptUiStateSilently,
        flushPendingAnswerBatchSilently: flushPendingAnswerBatchSilently,
        fontScaleDefault: FONT_SCALE_DEFAULT,
        fontScaleStep: FONT_SCALE_STEP,
        fullLogout: fullLogout,
        getCurrentUserPhoto: getCurrentUserPhoto,
        recordActionTrail: recordActionTrail,
        handleAnswerChangeTarget: handleAnswerChangeTarget,
        handleAnswerInputTarget: handleAnswerInputTarget,
        handleArrowNavigationKey: handleArrowNavigationKey,
        handleBlockedBrowserInspectionShortcutAction: handleBlockedBrowserInspectionShortcutAction,
        handleBlockedClipboardAction: handleBlockedClipboardAction,
        handleBlockedPrintAction: handleBlockedPrintAction,
        handleFinish: handleFinish,
        handleLogin: handleLogin,
        handleNavigationAction: handleNavigationAction,
        handleStartExam: handleStartExam,
        handleViewResult: handleViewResult,
        isCompactNavViewport: isCompactNavViewport,
        isExamAnswerEditingLocked: isExamAnswerEditingLocked,
        isExamClipboardBlockingActive: isExamClipboardBlockingActive,
        isExamFullscreenBlockingActive: isExamFullscreenBlockingActive,
        isQuestionRevisionRefreshActive: isQuestionRevisionRefreshActive,
        loadExams: loadExams,
        noteQuestionPrefetchActivity: noteQuestionPrefetchActivity,
        render: render,
        requestExamFullscreen: requestExamFullscreen,
        resetExamSession: resetExamSession,
        root: root,
        stageRuntimeManager: stageRuntimeManager,
        state: state,
        toggleTheme: toggleTheme,
        updateFontScale: updateFontScale,
        updateNavPanelPosition: updateNavPanelPosition,
        updateSelectedExam: updateSelectedExam
    });
    startupManager = createBootstrapSessionManager({
        clearMessages: clearMessages,
        fullLogout: fullLogout,
        loadExams: loadExams,
        persistAuthSession: persistAuthSession,
        readPersistedAuthSession: readPersistedAuthSession,
        reconcilePendingPageRefreshSecurityEvent: securityLoggingManager.reconcilePendingPageRefreshSecurityEvent,
        render: render,
        startSessionHeartbeat: startSessionHeartbeat,
        state: state,
        triggerPendingSyncLifecycleRetry: triggerPendingSyncLifecycleRetry,
        tryResumeActiveAttemptFromExamList: tryResumeActiveAttemptFromExamList
    });
    setBootProgress(82, 'Memasang listener aplikasi', 'Mounting application listeners');

    mountFrontendAppRuntime({
        appEventManager: appEventManager,
        debugManager: debugManager,
        documentRef: document,
        examSecurityManager: examSecurityManager,
        idleDetectionManager: idleDetectionManager,
        lifecycleManager: lifecycleManager,
        root: root
    });
    mountDiagnosticsCommandBridge();
    setBootProgress(94, 'Menyinkronkan sesi awal', 'Syncing initial session');

    setBootProgress(100, 'Menyiapkan tampilan pertama', 'Rendering first screen');

    try {
        startFrontendApp({
            applyUiPreferences: applyUiPreferences,
            bootstrapFromPersistedSession: startupManager.bootstrapFromPersistedSession,
            isCompactViewport: isCompactViewport,
            readPersistedUiPreferences: readPersistedUiPreferences,
            setCompactViewportState: function (nextState) {
                compactViewportState = !!nextState;
            },
            state: state,
            syncFullscreenState: syncFullscreenState
        });
    } catch (error) {
        renderFatalRuntimeError('startup', error);
    }
}
