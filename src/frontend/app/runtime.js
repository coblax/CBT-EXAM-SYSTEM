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
import { createLifecycleManager } from './core/lifecycle';
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
import { createAttemptUiStateStorage } from './storage/attempt-ui-state';
import { createQuestionCacheStorage } from './storage/question-cache';
import { createAnswerSyncManager } from './exam/answer-sync';
import { createAnswerInputManager } from './exam/answer-inputs';
import { createFinishFlowManager } from './exam/finish-flow';
import { createExamNavigationManager } from './exam/navigation';
import { createQuestionRuntimeManager } from './exam/question-runtime';
import {
    findQuestionOptionById,
    findQuestionOptionByKey,
    findQuestionOptionKeyById,
    getShortAnswerKeys,
    getTrueFalseMatrixItems,
    normalizeAnswerValueForQuestion,
    normalizeTrueFalseMatrixAnswer,
    questionOptionKey
} from './exam/question-helpers';
import { createQuestionRenderManager } from './exam/question-render';
import { createQuestionStateManager } from './exam/question-state';
import { createQuestionFlags } from './exam/question-flags';
import { createExamSecurityManager } from './exam/security';
import { createQuestionWindowManager } from './exam/question-window';
import { renderMathInContainer } from '../../shared/math-render';

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
    var authStageManager = null;
    var answerInputManager = null;
    var appShellManager = null;
    var examSessionManager = null;
    var finishFlowManager = null;
    var navigationManager = null;
    var questionRenderManager = null;
    var questionRuntimeManager = null;
    var questionStateManager = null;
    var renderCycleManager = null;
    var securityLoggingManager = null;
    var sessionHeartbeatManager = null;
    var sessionLifecycleManager = null;
    var stageRuntimeManager = null;
    var startupManager = null;

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
    var questionWindowManager = createQuestionWindowManager({
        escapeHtml: escapeHtml,
        getLoadQuestionWindow: function () {
            return loadQuestionWindow;
        },
        isQuestionRevisionRefreshActive: function () {
            if (typeof isQuestionRevisionRefreshActive === 'function') {
                return isQuestionRevisionRefreshActive.apply(null, arguments);
            }
            return false;
        },
        questionPrefetchBatchSize: QUESTION_PREFETCH_BATCH_SIZE,
        questionPrefetchIdleDelayMs: QUESTION_PREFETCH_IDLE_DELAY_MS,
        questionWindowSize: QUESTION_WINDOW_SIZE,
        root: root,
        state: state,
        windowRef: window
    });
    questionStateManager = createQuestionStateManager({
        getQuestionById: questionWindowManager.getQuestionById,
        state: state
    });
    var answerSyncManager = createAnswerSyncManager({
        answerSyncRetryBaseDelayMs: ANSWER_SYNC_RETRY_BASE_DELAY_MS,
        answerSyncRetryMaxDelayMs: ANSWER_SYNC_RETRY_MAX_DELAY_MS,
        apiRequest: function () {
            return api.apply(null, arguments);
        },
        autoSaveBatchMaxItems: AUTO_SAVE_BATCH_MAX_ITEMS,
        autoSaveChoiceDelayCongestedMs: AUTO_SAVE_CHOICE_DELAY_CONGESTED_MS,
        autoSaveChoiceDelayMs: AUTO_SAVE_CHOICE_DELAY_MS,
        autoSaveCongestedWindowMs: AUTO_SAVE_CONGESTED_WINDOW_MS,
        autoSaveTextDelayCongestedMs: AUTO_SAVE_TEXT_DELAY_CONGESTED_MS,
        autoSaveTextDelayMs: AUTO_SAVE_TEXT_DELAY_MS,
        getNavigatorConnectionStatus: getNavigatorConnectionStatus,
        getQuestionById: questionWindowManager.getQuestionById,
        getQuestionDataGeneration: function () {
            return questionRuntimeManager ? questionRuntimeManager.getQuestionDataGeneration() : 0;
        },
        getQuestionPayloadById: questionWindowManager.getQuestionPayloadById,
        diagnosticsManager: diagnosticsManager,
        isQuestionRevisionRefreshActive: function () {
            if (typeof isQuestionRevisionRefreshActive === 'function') {
                return isQuestionRevisionRefreshActive.apply(null, arguments);
            }
            return false;
        },
        maybeFinalizeLockedExam: function (reason) {
            if (finishFlowManager) {
                finishFlowManager.maybeFinalizeLockedExam(reason);
            }
        },
        normalizeExistingAnswerForQuestion: questionStateManager.normalizeExistingAnswerForQuestion,
        normalizeStoredAutoSaveState: function (snapshot) {
            return normalizeStoredAutoSaveState(snapshot);
        },
        payloadSignature: questionStateManager.payloadSignature,
        questionAnswerPayload: questionStateManager.questionAnswerPayload,
        recordActionTrail: recordActionTrail,
        recordTimeline: recordTimeline,
        render: render,
        scheduleQuestionCachePersist: function () {
            if (questionRuntimeManager && typeof questionRuntimeManager.scheduleQuestionCachePersist === 'function') {
                return questionRuntimeManager.scheduleQuestionCachePersist.apply(questionRuntimeManager, arguments);
            }
            return undefined;
        },
        state: state,
        windowRef: window
    });
    var questionCacheStorage = createQuestionCacheStorage({
        getAutoSaveState: answerSyncManager.getAutoSaveState,
        getIndexedDb: getIndexedDb,
        getLocalStorage: getLocalStorage,
        getQuestionPayloadById: questionWindowManager.getQuestionPayloadById,
        getSessionStorage: getSessionStorage,
        indexedDbName: QUESTION_CACHE_INDEXED_DB_NAME,
        indexedDbStore: QUESTION_CACHE_INDEXED_DB_STORE,
        itemLocalStorageKeyPrefix: QUESTION_CACHE_ITEM_LOCAL_STORAGE_KEY_PREFIX,
        metaLocalStorageKeyPrefix: QUESTION_CACHE_META_LOCAL_STORAGE_KEY_PREFIX,
        normalizeExistingAnswerForQuestion: questionStateManager.normalizeExistingAnswerForQuestion,
        now: Date.now,
        payloadSignature: questionStateManager.payloadSignature,
        sessionStorageKeyPrefix: QUESTION_CACHE_SESSION_STORAGE_KEY_PREFIX,
        state: state,
        windowRef: window
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
    var attemptUiStateStorage = createAttemptUiStateStorage({
        buildDoubtfulSessionStorageKey: doubtfulStateStorage.buildDoubtfulSessionStorageKey,
        clearPersistedDoubtfulState: doubtfulStateStorage.clearPersistedDoubtfulState,
        getQuestionCount: questionWindowManager.getQuestionCount,
        getQuestionIdAtIndex: questionWindowManager.getQuestionIdAtIndex,
        getSessionStorage: getSessionStorage,
        normalizeOrUseQuestionCacheSnapshot: questionCacheStorage.normalizeOrUseQuestionCacheSnapshot,
        normalizeQuestionCacheSnapshot: questionCacheStorage.normalizeQuestionCacheSnapshot,
        readPersistedDoubtfulState: doubtfulStateStorage.readPersistedDoubtfulState,
        state: state,
        storageKeyPrefix: ATTEMPT_UI_SESSION_STORAGE_KEY_PREFIX,
        validAttemptQuestionIds: questionWindowManager.validAttemptQuestionIds
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
        isAnswerSubmitPath: answerSyncManager.isAnswerSubmitPath,
        schedulePendingAnswerRetry: answerSyncManager.schedulePendingAnswerRetry,
        setConnectionStatus: answerSyncManager.setConnectionStatus,
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
        persistCurrentQuestionCacheLocally: questionCacheStorage.persistCurrentQuestionCacheLocally,
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
        setConnectionStatus: answerSyncManager.setConnectionStatus,
        state: state,
        triggerPendingSyncLifecycleRetry: function () {
            if (typeof triggerPendingSyncLifecycleRetry === 'function') {
                return triggerPendingSyncLifecycleRetry.apply(null, arguments);
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
    var getConfiguredPluginAuthor = appMetaManager.getConfiguredPluginAuthor;
    var getConfiguredPluginVersion = appMetaManager.getConfiguredPluginVersion;
    var getConfiguredSchoolLogoUrl = appMetaManager.getConfiguredSchoolLogoUrl;
    var getConfiguredSchoolMotto = appMetaManager.getConfiguredSchoolMotto;
    var getConfiguredSchoolName = appMetaManager.getConfiguredSchoolName;
    var getCurrentUserName = appMetaManager.getCurrentUserName;
    var getCurrentUserPhoto = appMetaManager.getCurrentUserPhoto;
    var getCurrentUserRole = appMetaManager.getCurrentUserRole;
    var getExamFooterSyncMeta = appMetaManager.getExamFooterSyncMeta;
    var getLoginHeroSchoolBranding = appMetaManager.getLoginHeroSchoolBranding;
    var getNavigatorConnectionStatus = appMetaManager.getNavigatorConnectionStatus;
    var getSelectedExam = appMetaManager.getSelectedExam;
    var getSyncStatusAlertMeta = appMetaManager.getSyncStatusAlertMeta;
    var getUserInitial = appMetaManager.getUserInitial;
    var isConnectionOffline = appMetaManager.isConnectionOffline;
    var isExamCopyPasteBlocked = appMetaManager.isExamCopyPasteBlocked;
    var isExamFullscreenRequired = appMetaManager.isExamFullscreenRequired;
    var isSecurityLoggingActiveForAttempt = appMetaManager.isSecurityLoggingActiveForAttempt;
    var isSecurityLoggingEnabled = appMetaManager.isSecurityLoggingEnabled;
    var renderAlert = appMetaManager.renderAlert;
    var normalizePersistedUser = authSessionManager.normalizePersistedUser;
    var persistAuthSession = authSessionManager.persistAuthSession;
    var readPersistedAuthSession = authSessionManager.readPersistedAuthSession;
    var buildDoubtfulSessionStorageKey = doubtfulStateStorage.buildDoubtfulSessionStorageKey;
    var clearPersistedDoubtfulState = doubtfulStateStorage.clearPersistedDoubtfulState;
    var readPersistedDoubtfulState = doubtfulStateStorage.readPersistedDoubtfulState;
    var buildAutoSaveStateSnapshot = questionCacheStorage.buildAutoSaveStateSnapshot;
    var buildChangedQuestionLookup = questionCacheStorage.buildChangedQuestionLookup;
    var buildQuestionOrderSignature = questionCacheStorage.buildQuestionOrderSignature;
    var buildQuestionCacheSessionStorageKey = questionCacheStorage.buildQuestionCacheSessionStorageKey;
    var buildQuestionCacheSnapshot = questionCacheStorage.buildQuestionCacheSnapshot;
    var buildQuestionManifestById = questionCacheStorage.buildQuestionManifestById;
    var buildQuestionManifestFromQuestions = questionCacheStorage.buildQuestionManifestFromQuestions;
    var clearPersistedQuestionCache = questionCacheStorage.clearPersistedQuestionCache;
    var compareQuestionRevisionFreshness = questionCacheStorage.compareQuestionRevisionFreshness;
    var clearAllAutoSaveTimers = answerSyncManager.clearAllAutoSaveTimers;
    var normalizeOrUseQuestionCacheSnapshot = questionCacheStorage.normalizeOrUseQuestionCacheSnapshot;
    var normalizeQuestionCacheSnapshot = questionCacheStorage.normalizeQuestionCacheSnapshot;
    var normalizeQuestionIdList = questionCacheStorage.normalizeQuestionIdList;
    var normalizeQuestionManifestItem = questionCacheStorage.normalizeQuestionManifestItem;
    var normalizeQuestionRevision = questionCacheStorage.normalizeQuestionRevision;
    var normalizeStoredAutoSaveState = questionCacheStorage.normalizeStoredAutoSaveState;
    var persistCurrentQuestionCacheLocally = questionCacheStorage.persistCurrentQuestionCacheLocally;
    var persistQuestionCacheLocally = questionCacheStorage.persistQuestionCacheLocally;
    var questionManifestContentSignature = questionCacheStorage.questionManifestContentSignature;
    var questionManifestUpdatedAt = questionCacheStorage.questionManifestUpdatedAt;
    var questionOrderSignatureEquals = questionCacheStorage.questionOrderSignatureEquals;
    var questionRevisionEquals = questionCacheStorage.questionRevisionEquals;
    var questionRevisionSignature = questionCacheStorage.questionRevisionSignature;
    var readPersistedQuestionCache = questionCacheStorage.readPersistedQuestionCache;
    var serializeQuestionRevision = questionCacheStorage.serializeQuestionRevision;
    var clearAutoSaveRuntimeState = answerSyncManager.clearAutoSaveRuntimeState;
    var flushPendingAnswerBatch = answerSyncManager.flushPendingAnswerBatch;
    var handleRecoverableAnswerSyncFailure = answerSyncManager.handleRecoverableAnswerSyncFailure;
    var hasAnswerBatchFlushInFlight = answerSyncManager.hasFlushInFlight;
    var hasPendingQueuedAnswerBatchItems = answerSyncManager.hasPendingBatchItems;
    var initializeSubmittedPayloadCache = answerSyncManager.initializeSubmittedPayloadCache;
    var isNetworkConnectivityError = answerSyncManager.isNetworkConnectivityError;
    var isRetryableAnswerSyncError = answerSyncManager.isRetryableAnswerSyncError;
    var primeSubmittedPayloadCacheFromQuestionItems = answerSyncManager.primeSubmittedPayloadCacheFromQuestionItems;
    var pruneAnswerSyncState = answerSyncManager.pruneQuestionAnswerState;
    var queueLoadedQuestionAnswersForFlush = answerSyncManager.queueLoadedQuestionAnswersForFlush;
    var queueQuestionAnswer = answerSyncManager.queueQuestionAnswer;
    var queueQuestionAnswersByIds = answerSyncManager.queueQuestionAnswersByIds;
    var restoreQuestionAutoSaveState = answerSyncManager.restoreQuestionAutoSaveState;
    var scheduleAutoSave = answerSyncManager.scheduleAutoSave;
    var schedulePendingAnswerRetry = answerSyncManager.schedulePendingAnswerRetry;
    var setConnectionStatus = answerSyncManager.setConnectionStatus;
    var syncPendingAnswerRuntimeState = answerSyncManager.syncPendingAnswerRuntimeState;
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
        getQuestionDisplayNumber: questionWindowManager.getQuestionDisplayNumber,
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
    var buildQuestionWindowItems = questionWindowManager.buildQuestionWindowItems;
    var clampQuestionIndex = questionWindowManager.clampQuestionIndex;
    var clearQuestionPrefetchRuntimeState = questionWindowManager.clearQuestionPrefetchRuntimeState;
    var getQuestionAtIndex = questionWindowManager.getQuestionAtIndex;
    var getQuestionById = questionWindowManager.getQuestionById;
    var getQuestionCount = questionWindowManager.getQuestionCount;
    var getQuestionDisplayNumber = questionWindowManager.getQuestionDisplayNumber;
    var getQuestionDisplayNumberById = questionWindowManager.getQuestionDisplayNumberById;
    var getQuestionIdAtIndex = questionWindowManager.getQuestionIdAtIndex;
    var getQuestionManifestById = questionWindowManager.getQuestionManifestById;
    var getQuestionPayloadById = questionWindowManager.getQuestionPayloadById;
    var isIndexInCurrentWindow = questionWindowManager.isIndexInCurrentWindow;
    var isQuestionPayloadLoaded = questionWindowManager.isQuestionPayloadLoaded;
    var isQuestionWindowLoaded = questionWindowManager.isQuestionWindowLoaded;
    var markQuestionWindowLoaded = questionWindowManager.markQuestionWindowLoaded;
    var noteQuestionPrefetchActivity = questionWindowManager.noteQuestionPrefetchActivity;
    var prefetchNextQuestionBatch = questionWindowManager.prefetchNextQuestionBatch;
    var questionWindowOffsetForIndex = questionWindowManager.questionWindowOffsetForIndex;
    var renderQuestionPrefetchIndicator = questionWindowManager.renderQuestionPrefetchIndicator;
    var resetQuestionPrefetchIdleTimer = questionWindowManager.resetQuestionPrefetchIdleTimer;
    var setActiveQuestionWindowForIndex = questionWindowManager.setActiveQuestionWindowForIndex;
    var setQuestionWindowFromLoadedPayloads = questionWindowManager.setQuestionWindowFromLoadedPayloads;
    var updateQuestionPrefetchIndicator = questionWindowManager.updateQuestionPrefetchIndicator;
    var validAttemptQuestionIds = questionWindowManager.validAttemptQuestionIds;
    var applyAttemptUiState = attemptUiStateStorage.applyAttemptUiState;
    var buildAttemptUiSessionStorageKey = attemptUiStateStorage.buildAttemptUiSessionStorageKey;
    var buildAttemptUiStateSnapshot = attemptUiStateStorage.buildAttemptUiStateSnapshot;
    var choosePreferredAttemptUiState = attemptUiStateStorage.choosePreferredAttemptUiState;
    var clearPersistedAttemptUiState = attemptUiStateStorage.clearPersistedAttemptUiState;
    var normalizeAttemptUiState = attemptUiStateStorage.normalizeAttemptUiState;
    var persistAttemptUiStateLocally = attemptUiStateStorage.persistAttemptUiStateLocally;
    var persistCurrentAttemptUiStateLocally = attemptUiStateStorage.persistCurrentAttemptUiStateLocally;
    var readPersistedAttemptUiState = attemptUiStateStorage.readPersistedAttemptUiState;
    var applyPendingRevisionSafeAnswersForLoadedQuestions = questionStateManager.applyPendingRevisionSafeAnswersForLoadedQuestions;
    var captureRevisionSafeLocalAnswers = questionStateManager.captureRevisionSafeLocalAnswers;
    var clearPendingRevisionSafeAnswerRestoreState = questionStateManager.clearPendingRevisionSafeAnswerRestoreState;
    var hasUsableLocalAnswerForQuestion = questionStateManager.hasUsableLocalAnswerForQuestion;
    var mergeExistingAnswersFromQuestionItems = questionStateManager.mergeExistingAnswersFromQuestionItems;
    var mergeExistingAnswersMap = questionStateManager.mergeExistingAnswersMap;
    var normalizeExistingAnswerForQuestion = questionStateManager.normalizeExistingAnswerForQuestion;
    var payloadSignature = questionStateManager.payloadSignature;
    var prunePendingRevisionSafeAnswerRestoreState = questionStateManager.prunePendingRevisionSafeAnswerRestoreState;
    var questionAnswerPayload = questionStateManager.questionAnswerPayload;
    var resolveStoredAnswerValueForQuestion = questionStateManager.resolveStoredAnswerValueForQuestion;
    var restoreLocalAnswerFromQuestion = questionStateManager.restoreLocalAnswerFromQuestion;
    var restoreRevisionSafeLocalAnswers = questionStateManager.restoreRevisionSafeLocalAnswers;
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
    questionRuntimeManager = createQuestionRuntimeManager({
        apiRequest: function () {
            return api.apply(null, arguments);
        },
        applyAttemptUiState: applyAttemptUiState,
        applyPendingRevisionSafeAnswersForLoadedQuestions: applyPendingRevisionSafeAnswersForLoadedQuestions,
        attemptUiStateSyncDelayMs: ATTEMPT_UI_STATE_SYNC_DELAY_MS,
        buildAttemptUiStateSnapshot: buildAttemptUiStateSnapshot,
        buildAutoSaveStateSnapshot: buildAutoSaveStateSnapshot,
        buildChangedQuestionLookup: buildChangedQuestionLookup,
        buildQuestionManifestById: buildQuestionManifestById,
        buildQuestionManifestFromQuestions: buildQuestionManifestFromQuestions,
        buildQuestionOrderSignature: buildQuestionOrderSignature,
        captureRevisionSafeLocalAnswers: captureRevisionSafeLocalAnswers,
        clearAttemptUiStateSyncTimer: clearAttemptUiStateSyncTimer,
        clearAutoSaveRuntimeState: clearAutoSaveRuntimeState,
        clearPendingRevisionSafeAnswerRestoreState: clearPendingRevisionSafeAnswerRestoreState,
        clearPersistedAttemptUiState: clearPersistedAttemptUiState,
        clearPersistedQuestionCache: clearPersistedQuestionCache,
        clearQuestionPrefetchRuntimeState: clearQuestionPrefetchRuntimeState,
        clampQuestionIndex: clampQuestionIndex,
        diagnosticsManager: diagnosticsManager,
        getQuestionCount: getQuestionCount,
        getQuestionIdAtIndex: getQuestionIdAtIndex,
        getQuestionManifestById: getQuestionManifestById,
        getQuestionPayloadById: getQuestionPayloadById,
        hasPendingQueuedAnswerBatchItems: hasPendingQueuedAnswerBatchItems,
        hasUsableLocalAnswerForQuestion: hasUsableLocalAnswerForQuestion,
        initializeSubmittedPayloadCache: initializeSubmittedPayloadCache,
        isIndexInCurrentWindow: isIndexInCurrentWindow,
        isQuestionPayloadLoaded: isQuestionPayloadLoaded,
        markQuestionWindowLoaded: markQuestionWindowLoaded,
        mergeExistingAnswersFromQuestionItems: mergeExistingAnswersFromQuestionItems,
        mergeExistingAnswersMap: mergeExistingAnswersMap,
        navQuestionFilterAll: NAV_QUESTION_FILTER_ALL,
        normalizeNavigationQuestionFilter: function (value) {
            if (typeof normalizeNavigationQuestionFilter === 'function') {
                return normalizeNavigationQuestionFilter(value);
            }
            return NAV_QUESTION_FILTER_ALL;
        },
        normalizeOrUseQuestionCacheSnapshot: normalizeOrUseQuestionCacheSnapshot,
        normalizeQuestionCacheSnapshot: normalizeQuestionCacheSnapshot,
        normalizeQuestionIdList: normalizeQuestionIdList,
        normalizeQuestionRevision: normalizeQuestionRevision,
        persistCurrentAttemptUiStateLocally: persistCurrentAttemptUiStateLocally,
        persistCurrentQuestionCacheLocally: persistCurrentQuestionCacheLocally,
        primeSubmittedPayloadCacheFromQuestionItems: primeSubmittedPayloadCacheFromQuestionItems,
        pruneAnswerSyncState: pruneAnswerSyncState,
        prunePendingRevisionSafeAnswerRestoreState: prunePendingRevisionSafeAnswerRestoreState,
        questionOrderSignatureEquals: questionOrderSignatureEquals,
        questionRevisionEquals: questionRevisionEquals,
        questionWindowOffsetForIndex: questionWindowOffsetForIndex,
        questionWindowSize: QUESTION_WINDOW_SIZE,
        queueLoadedQuestionAnswersForFlush: queueLoadedQuestionAnswersForFlush,
        queueQuestionAnswersByIds: queueQuestionAnswersByIds,
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
        resetQuestionPrefetchIdleTimer: resetQuestionPrefetchIdleTimer,
        restoreLocalAnswerFromQuestion: restoreLocalAnswerFromQuestion,
        restoreQuestionAutoSaveState: restoreQuestionAutoSaveState,
        restoreRevisionSafeLocalAnswers: restoreRevisionSafeLocalAnswers,
        scheduleAttemptUiStateSync: scheduleAttemptUiStateSync,
        schedulePendingAnswerRetry: schedulePendingAnswerRetry,
        serializeQuestionRevision: serializeQuestionRevision,
        setQuestionWindowFromLoadedPayloads: setQuestionWindowFromLoadedPayloads,
        state: state,
        syncAttemptUiStateSignatureToCurrentState: syncAttemptUiStateSignatureToCurrentState,
        updateQuestionPrefetchIndicator: updateQuestionPrefetchIndicator,
        validAttemptQuestionIds: validAttemptQuestionIds,
        windowRef: window
    });
    var applyPersistedQuestionCache = questionRuntimeManager.applyPersistedQuestionCache;
    var applyQuestionsResponse = questionRuntimeManager.applyQuestionsResponse;
    var bumpQuestionDataGeneration = questionRuntimeManager.bumpQuestionDataGeneration;
    var clearQuestionCachePersistTimer = questionRuntimeManager.clearQuestionCachePersistTimer;
    var clearStickyQuestionRevisionNotice = questionRuntimeManager.clearStickyQuestionRevisionNotice;
    var ensureQuestionWindowForIndex = questionRuntimeManager.ensureQuestionWindowForIndex;
    var getChangedQuestionCount = questionRuntimeManager.getChangedQuestionCount;
    var getQuestionRevisionMarkerCount = questionRuntimeManager.getQuestionRevisionMarkerCount;
    var isQuestionRevisionRefreshActive = questionRuntimeManager.isQuestionRevisionRefreshActive;
    var loadQuestionWindow = questionRuntimeManager.loadQuestionWindow;
    var mergeAttemptUiStateDoubtfulIds = questionRuntimeManager.mergeAttemptUiStateDoubtfulIds;
    var pruneQuestionScopedState = questionRuntimeManager.pruneQuestionScopedState;
    var questionCacheHasPayloadForIndex = questionRuntimeManager.questionCacheHasPayloadForIndex;
    var refreshAttemptQuestionRevision = questionRuntimeManager.refreshAttemptQuestionRevision;
    var resetQuestionDataState = questionRuntimeManager.resetQuestionDataState;
    var acknowledgeQuestionRevisionMarker = questionRuntimeManager.acknowledgeQuestionRevisionMarker;
    var scheduleQuestionCachePersist = questionRuntimeManager.scheduleQuestionCachePersist;
    var setQuestionRevision = questionRuntimeManager.setQuestionRevision;
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
            questionRuntimeManager.clearQuestionRevisionRefreshState();
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
        sendSecurityEventSilently: sendSecurityEventSilently,
        sessionHeartbeatIntervalMs: SESSION_HEARTBEAT_INTERVAL_MS,
        setQuestionRevision: setQuestionRevision,
        state: state,
        windowRef: window
    });
    var runSessionHeartbeat = sessionHeartbeatManager.run;
    var startSessionHeartbeat = sessionHeartbeatManager.start;
    var stopSessionHeartbeat = sessionHeartbeatManager.stop;
    finishFlowManager = createFinishFlowManager({
        apiRequest: function () {
            return api.apply(null, arguments);
        },
        clearAllAutoSaveTimers: clearAllAutoSaveTimers,
        clearAttemptUiStateSyncTimer: clearAttemptUiStateSyncTimer,
        clearAutoSaveRuntimeState: clearAutoSaveRuntimeState,
        clearMessages: clearMessages,
        clearPersistedAttemptUiState: clearPersistedAttemptUiState,
        clearPersistedQuestionCache: clearPersistedQuestionCache,
        clearQuestionCachePersistTimer: clearQuestionCachePersistTimer,
        clearQuestionPrefetchRuntimeState: clearQuestionPrefetchRuntimeState,
        diagnosticsManager: diagnosticsManager,
        exitFullscreenSilently: exitFullscreenSilently,
        flushAttemptUiState: flushAttemptUiState,
        flushPendingAnswerBatch: flushPendingAnswerBatch,
        getExamProgressSummary: function () {
            return navigationManager.getExamProgressSummary();
        },
        getNavigatorConnectionStatus: getNavigatorConnectionStatus,
        getQuestionAtIndex: getQuestionAtIndex,
        getQuestionCount: getQuestionCount,
        handleRecoverableAnswerSyncFailure: handleRecoverableAnswerSyncFailure,
        hasAnswerBatchFlushInFlight: hasAnswerBatchFlushInFlight,
        isNetworkConnectivityError: isNetworkConnectivityError,
        isQuestionAnswered: function (question) {
            return navigationManager.isQuestionAnswered(question);
        },
        isRetryableAnswerSyncError: isRetryableAnswerSyncError,
        persistCurrentQuestionCacheLocally: persistCurrentQuestionCacheLocally,
        prefetchResultStageRenderer: function () {
            if (stageRuntimeManager) {
                stageRuntimeManager.prefetchResultStageRenderer();
            }
        },
        queueQuestionAnswer: queueQuestionAnswer,
        recordActionTrail: recordActionTrail,
        recordTimeline: recordTimeline,
        render: render,
        schedulePendingAnswerRetry: schedulePendingAnswerRetry,
        setConnectionStatus: setConnectionStatus,
        startTimer: startTimer,
        state: state,
        stopTimer: stopTimer,
        syncFullscreenState: function () {
            if (typeof syncFullscreenState === 'function') {
                return syncFullscreenState.apply(null, arguments);
            }
            return undefined;
        },
        syncPendingAnswerRuntimeState: syncPendingAnswerRuntimeState
    });
    var closeFinishConfirmModal = finishFlowManager.closeFinishConfirmModal;
    var handleFinish = finishFlowManager.handleFinish;
    var maybeFinalizeLockedExam = finishFlowManager.maybeFinalizeLockedExam;
    var openFinishConfirmModal = finishFlowManager.openFinishConfirmModal;
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
            questionRuntimeManager.clearQuestionRevisionRefreshState();
        },
        clearSecurityLoggingRuntimeState: clearSecurityLoggingRuntimeState,
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
        getExamProgressSummary: function () {
            return navigationManager.getExamProgressSummary();
        },
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
    var renderThemeToggleControl = appShellManager.renderThemeToggleControl;
    var renderTopbar = appShellManager.renderTopbar;
    var renderUserPhotoModal = appShellManager.renderUserPhotoModal;
    questionRenderManager = createQuestionRenderManager({
        escapeHtml: escapeHtml,
        isExamAnswerEditingLocked: isExamAnswerEditingLocked,
        resolveStoredAnswerValueForQuestion: resolveStoredAnswerValueForQuestion,
        safeRichHtml: safeRichHtml
    });
    var renderQuestionInput = questionRenderManager.renderQuestionInput;
    var renderQuestionStem = questionRenderManager.renderQuestionStem;
    answerInputManager = createAnswerInputManager({
        autoSaveChoiceDelayMs: AUTO_SAVE_CHOICE_DELAY_MS,
        autoSaveTextDelayMs: AUTO_SAVE_TEXT_DELAY_MS,
        clearMessages: clearMessages,
        normalizeExamToken: normalizeExamToken,
        render: render,
        root: root,
        scheduleAutoSave: scheduleAutoSave,
        scheduleQuestionCachePersist: function () {
            if (questionRuntimeManager && typeof questionRuntimeManager.scheduleQuestionCachePersist === 'function') {
                return questionRuntimeManager.scheduleQuestionCachePersist.apply(questionRuntimeManager, arguments);
            }
            return undefined;
        },
        state: state,
        updateSelectedExam: updateSelectedExam
    });
    var handleAnswerChangeTarget = answerInputManager.handleChangeTarget;
    var handleAnswerInputTarget = answerInputManager.handleInputTarget;
    navigationManager = createExamNavigationManager({
        attemptUiStateNavigationSyncDelayMs: ATTEMPT_UI_STATE_NAVIGATION_SYNC_DELAY_MS,
        attemptUiStateSyncDelayMs: ATTEMPT_UI_STATE_SYNC_DELAY_MS,
        acknowledgeQuestionRevisionMarker: acknowledgeQuestionRevisionMarker,
        clampQuestionIndex: clampQuestionIndex,
        clearStickyQuestionRevisionNotice: clearStickyQuestionRevisionNotice,
        clearMessages: clearMessages,
        documentRef: document,
        ensureQuestionWindowForIndex: ensureQuestionWindowForIndex,
        escapeHtml: escapeHtml,
        getNavigatorConnectionStatus: getNavigatorConnectionStatus,
        getQuestionAtIndex: getQuestionAtIndex,
        getQuestionById: getQuestionById,
        getQuestionCount: getQuestionCount,
        getQuestionIdAtIndex: getQuestionIdAtIndex,
        getShortAnswerKeys: getShortAnswerKeys,
        getTrueFalseMatrixItems: getTrueFalseMatrixItems,
        hasUsableLocalAnswerForQuestion: hasUsableLocalAnswerForQuestion,
        isExamAnswerEditingLocked: isExamAnswerEditingLocked,
        isNetworkConnectivityError: isNetworkConnectivityError,
        isQuestionPayloadLoaded: isQuestionPayloadLoaded,
        navQuestionFilterAll: NAV_QUESTION_FILTER_ALL,
        navQuestionFilterAnswered: NAV_QUESTION_FILTER_ANSWERED,
        navQuestionFilterDoubtful: NAV_QUESTION_FILTER_DOUBTFUL,
        navQuestionFilterUnanswered: NAV_QUESTION_FILTER_UNANSWERED,
        navigationQuestionTypeBadgeConfig: navigationQuestionTypeBadgeConfig,
        normalizeTrueFalseMatrixAnswer: normalizeTrueFalseMatrixAnswer,
        persistCurrentAttemptUiStateLocally: persistCurrentAttemptUiStateLocally,
        prefetchNextQuestionBatch: prefetchNextQuestionBatch,
        questionOptionKey: questionOptionKey,
        questionWindowSize: QUESTION_WINDOW_SIZE,
        queueQuestionAnswer: queueQuestionAnswer,
        render: render,
        renderExamPartial: function (regions, reason, meta) {
            if (renderCycleManager && typeof renderCycleManager.patchExamRegions === 'function') {
                return renderCycleManager.patchExamRegions(regions, reason, meta);
            }
            render(reason, meta);
            return false;
        },
        resetQuestionPrefetchIdleTimer: resetQuestionPrefetchIdleTimer,
        resolveStoredAnswerValueForQuestion: resolveStoredAnswerValueForQuestion,
        scheduleAttemptUiStateSync: scheduleAttemptUiStateSync,
        schedulePendingAnswerRetry: schedulePendingAnswerRetry,
        scheduleQuestionCachePersist: function () {
            if (questionRuntimeManager && typeof questionRuntimeManager.scheduleQuestionCachePersist === 'function') {
                return questionRuntimeManager.scheduleQuestionCachePersist.apply(questionRuntimeManager, arguments);
            }
            return undefined;
        },
        setActiveQuestionWindowForIndex: setActiveQuestionWindowForIndex,
        state: state
    });
    var getExamProgressSummary = navigationManager.getExamProgressSummary;
    var getNavigationQuestionEntries = navigationManager.getNavigationQuestionEntries;
    var goToQuestion = navigationManager.goToQuestion;
    var handleNavigationAction = navigationManager.handleAction;
    var handleArrowNavigationKey = navigationManager.handleArrowNavigationKey;
    var isQuestionAnswered = navigationManager.isQuestionAnswered;
    var navigationQuestionFilterEmptyMessage = navigationManager.navigationQuestionFilterEmptyMessage;
    var normalizeNavigationQuestionFilter = navigationManager.normalizeNavigationQuestionFilter;
    var questionMatchesNavigationFilter = navigationManager.questionMatchesNavigationFilter;
    var renderNavigationAnswerBadges = navigationManager.renderNavigationAnswerBadges;
    var renderNavigationQuestionTypeBadge = navigationManager.renderNavigationQuestionTypeBadge;
    var questionFlags = createQuestionFlags({
        state: state
    });
    var isQuestionChanged = questionFlags.isQuestionChanged;
    var isQuestionRevisionMarked = questionFlags.isQuestionRevisionMarked;
    var isQuestionDoubtful = questionFlags.isQuestionDoubtful;
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
        getQuestionCount: getQuestionCount,
        getQuestionDisplayNumber: getQuestionDisplayNumber,
        getQuestionIdAtIndex: getQuestionIdAtIndex,
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
    var maybePrefetchExamRuntime = stageRuntimeManager.maybePrefetchExamRuntime;
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
        enhanceRichMath: function () {
            renderMathInContainer(root);
        },
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
