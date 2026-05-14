import {
    AUTH_SESSION_STORAGE_KEY,
    createInitialState,
    getFrontendConfig
} from '../core/config.js';
import { createBrowserStorageAccess } from '../core/browser-storage.js';
import { createAuthSessionManager } from '../core/auth-session.js';
import { escapeHtml } from '../core/html.js';

var VALID_STAGES = ['login', 'confirm', 'exam', 'result'];

export function bootstrapStudentShell() {
    var root = document.getElementById('cbt-exam-app');
    if (!root) {
        return null;
    }
    if (
        root.getAttribute('data-cbt-student-shell-mounted') === '1'
        || root.getAttribute('data-cbt-student-runtime-mounted') === '1'
    ) {
        return null;
    }

    root.setAttribute('data-cbt-student-shell-mounted', '1');
    root.removeAttribute('data-cbt-student-runtime-mounted');

    var config = getFrontendConfig(window);
    if (document.body) {
        document.body.classList.toggle('cbt-security-print-guard', Number(config.securityLogEvents || 0) === 1);
    }

    var browserStorage = createBrowserStorageAccess(window);
    var state = createInitialState(window);
    var authSession = createAuthSessionManager({
        getSessionStorage: browserStorage.getSessionStorage,
        state: state,
        storageKey: AUTH_SESSION_STORAGE_KEY
    });
    var currentController = null;
    var currentStage = '';
    var transitionSerial = 0;
    var context = {
        api: null,
        authSession: authSession,
        browserStorage: browserStorage,
        config: config,
        debugManager: createNoopDebugManager(),
        diagnosticsManager: null,
        loadLegacyRuntime: loadLegacyRuntime,
        recordActionTrail: recordActionTrail,
        recordTimeline: recordTimeline,
        renderFatalError: renderFatalError,
        renderShell: function () {
            if (currentController && typeof currentController.render === 'function') {
                currentController.render('shell-render', {});
            }
        },
        root: root,
        state: state,
        transitionTo: transitionTo
    };

    var persisted = authSession.readPersistedAuthSession();
    var initialStage = persisted ? normalizeStage(persisted.lastStage || 'confirm', 'confirm') : 'login';
    if (persisted) {
        state.token = persisted.token;
        state.user = persisted.user;
        state.selectedExamId = Number(persisted.selectedExamId) || 0;
        state.stage = initialStage;
    }

    transitionTo(initialStage, {
        reason: persisted ? 'persisted-auth' : 'fresh-login'
    }).catch(function (error) {
        renderFatalError('stage-load', error);
    });

    return {
        transitionTo: transitionTo
    };

    async function transitionTo(stage, options) {
        var normalizedStage = normalizeStage(stage, 'login');
        var serial = transitionSerial + 1;
        transitionSerial = serial;

        if (currentController && typeof currentController.unmount === 'function') {
            currentController.unmount();
        }
        currentController = null;
        currentStage = normalizedStage;
        state.stage = normalizedStage;
        renderBootShell(normalizedStage);

        try {
            var controller = await loadStageRuntime(normalizedStage, context, options || {});
            if (serial !== transitionSerial) {
                if (controller && typeof controller.unmount === 'function') {
                    controller.unmount();
                }
                return null;
            }
            currentController = controller || createEmptyStageController();
            if (currentController && typeof currentController.render === 'function') {
                currentController.render('stage-mounted', {
                    stage: currentStage
                });
            }
            return currentController;
        } catch (error) {
            if (serial === transitionSerial) {
                renderFatalError('stage-load', error);
            }
            throw error;
        }
    }

    async function loadLegacyRuntime(reason) {
        transitionSerial += 1;
        if (currentController && typeof currentController.unmount === 'function') {
            currentController.unmount();
        }
        currentController = null;
        root.removeAttribute('data-cbt-student-shell-mounted');
        root.removeAttribute('data-cbt-student-runtime-mounted');
        renderBootShell('legacy');

        try {
            var module = await import('../legacy-runtime.js');
            return module.bootstrapFrontendApp({
                handoffReason: String(reason || '')
            });
        } catch (error) {
            renderFatalError('legacy-runtime', error);
            throw error;
        }
    }

    function renderBootShell(stage) {
        root.innerHTML = [
            '<div class="cbt-boot-shell" role="status" aria-live="polite">',
            '<div class="cbt-boot-card">',
            '<span class="cbt-finish-live-spinner" aria-hidden="true"></span>',
            '<div>',
            '<strong>' + escapeHtml(resolveBootTitle(stage)) + '</strong>',
            '<p>' + escapeHtml(resolveBootDetail(stage)) + '</p>',
            '</div>',
            '</div>',
            '</div>'
        ].join('');
        syncBodyStageClass(stage === 'legacy' ? state.stage : stage);
    }

    function renderFatalError(phase, error) {
        var message = error instanceof Error && error.message
            ? error.message
            : 'Runtime frontend gagal dimuat.';
        root.innerHTML = [
            '<section class="cbt-card cbt-runtime-fatal" role="alert">',
            '<h3>Frontend tidak bisa dimuat</h3>',
            '<p class="cbt-subtitle">' + escapeHtml(message) + '</p>',
            '<p class="cbt-muted">Phase: ' + escapeHtml(String(phase || 'unknown')) + '</p>',
            '<button class="cbt-button cbt-button-primary" type="button" data-action="reload-page">Muat ulang</button>',
            '</section>'
        ].join('');
        root.addEventListener('click', handleFatalClick, { once: true });
    }

    function handleFatalClick(event) {
        var target = event && event.target && event.target.closest
            ? event.target.closest('[data-action="reload-page"]')
            : null;
        if (target) {
            window.location.reload();
        }
    }
}

async function loadStageRuntime(stage, context, options) {
    if (stage === 'login') {
        var loginModule = await import('../stages/login-runtime.js');
        return loginModule.mountLoginStage(context, options || {});
    }
    if (stage === 'confirm') {
        var confirmModule = await import('../stages/confirm-runtime.js');
        return confirmModule.mountConfirmStage(context, options || {});
    }
    if (stage === 'exam') {
        var examModule = await import('../stages/exam-runtime.js');
        return examModule.mountExamStage(context, options || {});
    }
    if (stage === 'result') {
        var resultModule = await import('../stages/result-runtime.js');
        return resultModule.mountResultStage(context, options || {});
    }

    throw new Error('Stage frontend tidak dikenali: ' + String(stage || '-'));
}

function normalizeStage(stage, fallback) {
    var normalized = String(stage || '').trim().toLowerCase();
    return VALID_STAGES.indexOf(normalized) !== -1 ? normalized : String(fallback || 'login');
}

function resolveBootTitle(stage) {
    if (stage === 'login') {
        return 'Menyiapkan halaman login';
    }
    if (stage === 'confirm') {
        return 'Menyiapkan daftar ujian';
    }
    if (stage === 'exam') {
        return 'Menyiapkan runtime ujian';
    }
    if (stage === 'result') {
        return 'Menyiapkan hasil ujian';
    }
    return 'Menyiapkan aplikasi CBT';
}

function resolveBootDetail(stage) {
    if (stage === 'login') {
        return 'Module login sedang dimuat.';
    }
    if (stage === 'legacy') {
        return 'Runtime penuh sedang dimuat untuk sesi aktif.';
    }
    return 'Mohon tunggu sebentar.';
}

function syncBodyStageClass(stage) {
    if (!document.body) {
        return;
    }

    ['login', 'confirm', 'exam', 'result'].forEach(function (name) {
        document.body.classList.remove('cbt-stage-' + name);
    });
    document.body.classList.add('cbt-stage-' + normalizeStage(stage, 'login'));
}

function createNoopDebugManager() {
    return {
        enabled: false,
        log: function () {},
        logEvent: function () {},
        refresh: function () {}
    };
}

function createEmptyStageController() {
    return {
        render: function () {},
        unmount: function () {}
    };
}

function recordActionTrail() {}

function recordTimeline() {}
