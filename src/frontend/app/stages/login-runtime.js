import {
    FONT_SCALE_MAX,
    FONT_SCALE_MIN,
    UI_PREF_STORAGE_KEY
} from '../core/config.js';
import {
    formatDateTime,
    formatDateTimeCompact,
    formatScoreValue,
    formatSeconds
} from '../core/format.js';
import { createApiClient } from '../core/api.js';
import { createAppMetaManager } from '../core/app-meta.js';
import { createAppShellManager } from '../core/app-shell.js';
import { createAuthStageManager } from '../core/auth-stages.js';
import { createUiPreferencesManager } from '../core/ui-preferences.js';
import { escapeHtml } from '../core/html.js';

var LOGIN_IDENTIFIER_MAX_LENGTH = 191;
var LOGIN_PASSWORD_MAX_LENGTH = 1024;

export function mountLoginStage(context) {
    var root = context.root;
    var state = context.state;
    var config = context.config;
    var browserStorage = context.browserStorage;
    var authSession = context.authSession;
    var mounted = true;
    var loginRequestId = 0;
    var compactViewportState = false;

    var uiPreferencesManager = createUiPreferencesManager({
        getLocalStorage: browserStorage.getLocalStorage,
        root: root,
        state: state,
        storageKey: UI_PREF_STORAGE_KEY,
        windowRef: window
    });
    var appMetaManager = createAppMetaManager({
        config: config,
        escapeHtml: escapeHtml,
        state: state,
        windowRef: window
    });
    var apiClient = createApiClient({
        config: config,
        diagnosticsManager: null,
        expireAuthSession: function (message) {
            state.error = String(message || 'Sesi berakhir. Silakan login kembali.');
            render('auth-expired', {});
        },
        fetchImpl: window.fetch.bind(window),
        getNavigatorConnectionStatus: appMetaManager.getNavigatorConnectionStatus,
        isAnswerSubmitPath: function () {
            return false;
        },
        schedulePendingAnswerRetry: function () {},
        setConnectionStatus: function (status) {
            state.connectionStatus = String(status || 'online') === 'offline' ? 'offline' : 'online';
        },
        state: state,
        windowRef: window
    });
    var authStageManager = createAuthStageManager({
        clearMessages: appMetaManager.clearMessages,
        escapeHtml: escapeHtml,
        formatDateTime: formatDateTime,
        formatDateTimeCompact: formatDateTimeCompact,
        formatScoreValue: formatScoreValue,
        getConfiguredPluginAuthor: appMetaManager.getConfiguredPluginAuthor,
        getConfiguredPluginVersion: appMetaManager.getConfiguredPluginVersion,
        getConfiguredSchoolLogoUrl: appMetaManager.getConfiguredSchoolLogoUrl,
        getConfiguredSchoolMotto: appMetaManager.getConfiguredSchoolMotto,
        getConfiguredSchoolName: appMetaManager.getConfiguredSchoolName,
        getCurrentUserName: appMetaManager.getCurrentUserName,
        getCurrentUserPhoto: appMetaManager.getCurrentUserPhoto,
        getLoginHeroSchoolBranding: appMetaManager.getLoginHeroSchoolBranding,
        getSelectedExam: appMetaManager.getSelectedExam,
        getUserInitial: appMetaManager.getUserInitial,
        persistAuthSession: authSession.persistAuthSession,
        recordTimeline: context.recordTimeline,
        render: render,
        renderAlert: appMetaManager.renderAlert,
        state: state
    });
    var appShellManager = createAppShellManager({
        escapeHtml: escapeHtml,
        fontScaleMax: FONT_SCALE_MAX,
        fontScaleMin: FONT_SCALE_MIN,
        formatFontScaleLabel: uiPreferencesManager.formatFontScaleLabel,
        formatScoreValue: formatScoreValue,
        formatSeconds: formatSeconds,
        getConfiguredSchoolLogoUrl: appMetaManager.getConfiguredSchoolLogoUrl,
        getConfiguredSchoolName: appMetaManager.getConfiguredSchoolName,
        getCurrentUserName: appMetaManager.getCurrentUserName,
        getCurrentUserPhoto: appMetaManager.getCurrentUserPhoto,
        getExamProgressSummary: function () {
            return {};
        },
        getSelectedExam: appMetaManager.getSelectedExam,
        getUserInitial: appMetaManager.getUserInitial,
        renderAlert: appMetaManager.renderAlert,
        renderConfirmStage: function () {
            return renderStageLoading('confirm');
        },
        renderExamStageShell: function () {
            return renderStageLoading('exam');
        },
        renderLoginStage: authStageManager.renderLoginStage,
        renderResultStageShell: function () {
            return renderStageLoading('result');
        },
        state: state
    });

    context.api = apiClient.api;
    state.stage = 'login';
    restoreUiPreferences();
    mountListeners();
    render('login-stage-mounted', {});

    return {
        render: render,
        unmount: unmount
    };

    function restoreUiPreferences() {
        var persistedUiPreferences = uiPreferencesManager.readPersistedUiPreferences();
        if (persistedUiPreferences) {
            state.uiTheme = persistedUiPreferences.theme;
            state.fontScale = persistedUiPreferences.fontScale;
            state.navPanelPosition = persistedUiPreferences.navPanelPosition;
            state.calculatorPosition = persistedUiPreferences.calculatorPosition;
        }
        compactViewportState = isCompactViewport();
        uiPreferencesManager.applyUiPreferences();
    }

    function mountListeners() {
        root.addEventListener('submit', handleSubmit);
        root.addEventListener('click', handleClick);
        root.addEventListener('input', handleInput);
        root.addEventListener('error', handleImageError, true);
        window.addEventListener('online', handleConnectivityChange);
        window.addEventListener('offline', handleConnectivityChange);
        window.addEventListener('resize', handleResize);
        if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
            document.fonts.ready.then(function () {
                if (mounted) {
                    fitLoginHeroSchoolName();
                }
            }).catch(function () {});
        }
    }

    function unmount() {
        mounted = false;
        root.removeEventListener('submit', handleSubmit);
        root.removeEventListener('click', handleClick);
        root.removeEventListener('input', handleInput);
        root.removeEventListener('error', handleImageError, true);
        window.removeEventListener('online', handleConnectivityChange);
        window.removeEventListener('offline', handleConnectivityChange);
        window.removeEventListener('resize', handleResize);
        if (state.loginRateLimitTimer) {
            window.clearInterval(state.loginRateLimitTimer);
            state.loginRateLimitTimer = 0;
        }
    }

    function handleSubmit(event) {
        var form = event && event.target && event.target.closest
            ? event.target.closest('#cbt-login-form')
            : null;
        if (!form) {
            return;
        }
        event.preventDefault();
        handleLogin(form);
    }

    function handleClick(event) {
        var target = event && event.target && event.target.closest
            ? event.target.closest('[data-action]')
            : null;
        if (!target || !root.contains(target)) {
            return;
        }

        var action = String(target.getAttribute('data-action') || '');
        if (action === 'toggle-password') {
            state.loginPasswordVisible = !state.loginPasswordVisible;
            render('toggle-password', {});
            return;
        }
        if (action === 'dismiss-alert') {
            appMetaManager.clearMessages();
            render('dismiss-alert', {});
            return;
        }
        if (action === 'toggle-theme') {
            uiPreferencesManager.toggleTheme();
            render('toggle-theme', {});
        }
    }

    function handleInput(event) {
        var target = event && event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }
        if (target.name === 'identifier') {
            state.loginIdentifier = String(target.value || '');
            return;
        }
        if (target.name === 'password') {
            state.loginPassword = String(target.value || '');
        }
    }

    function handleImageError(event) {
        var target = event && event.target;
        if (!(target instanceof HTMLImageElement)) {
            return;
        }
        if (target.getAttribute('data-cbt-profile-photo') === null) {
            return;
        }
        if (target.getAttribute('data-cbt-profile-photo-error') === '1') {
            return;
        }

        target.setAttribute('data-cbt-profile-photo-error', '1');
        target.setAttribute('aria-hidden', 'true');
        target.hidden = true;

        var fallback = target.parentElement instanceof Element
            ? target.parentElement.querySelector('[data-cbt-profile-photo-fallback]')
            : null;
        if (fallback instanceof HTMLElement) {
            fallback.hidden = false;
            fallback.removeAttribute('hidden');
        }
    }

    function handleConnectivityChange() {
        state.connectionStatus = appMetaManager.getNavigatorConnectionStatus();
        render('connectivity-change', {});
    }

    function handleResize() {
        var nextCompact = isCompactViewport();
        if (nextCompact !== compactViewportState) {
            compactViewportState = nextCompact;
            render('viewport-resize', {
                compact: nextCompact
            });
            return;
        }
        fitLoginHeroSchoolName();
    }

    async function handleLogin(form) {
        if (state.busy || String(state.stage || '') !== 'login') {
            return;
        }

        var requestId = loginRequestId + 1;
        loginRequestId = requestId;
        var identifierEl = form.querySelector('[name="identifier"]');
        var passwordEl = form.querySelector('[name="password"]');
        var identifier = String(state.loginIdentifier || (identifierEl ? identifierEl.value || '' : '')).trim();
        var password = String(state.loginPassword || (passwordEl ? passwordEl.value || '' : ''));
        state.loginIdentifier = identifier;
        state.loginPassword = password;

        appMetaManager.clearMessages();
        if (!identifier || !password) {
            state.error = 'Identifier dan password wajib diisi.';
            render('login-validation', {});
            return;
        }
        if (identifier.length > LOGIN_IDENTIFIER_MAX_LENGTH || password.length > LOGIN_PASSWORD_MAX_LENGTH) {
            state.error = 'Identifier atau password terlalu panjang.';
            render('login-validation', {});
            return;
        }
        if (Number(state.loginRateLimitRemaining) > 0) {
            state.error = 'Harap tunggu ' + String(state.loginRateLimitRemaining) + ' detik lagi sebelum mencoba login.';
            render('login-rate-limit-blocked', {});
            return;
        }

        state.busy = true;
        updateAuthProgress(18, 1, 'Menghubungi server login', 'Identifier dan password Anda sedang diverifikasi.');
        render('login-submit', {});

        try {
            var loginPayload = await apiClient.api('login', {
                method: 'POST',
                auth: false,
                body: {
                    identifier: identifier,
                    password: password
                }
            });
            if (!mounted || requestId !== loginRequestId || String(state.stage || '') !== 'login') {
                return;
            }
            updateAuthProgress(72, 2, 'Menyiapkan sesi peserta', 'Token login dan profil singkat sedang disiapkan.');
            applyLoginPayload(loginPayload);
            state.stage = 'confirm';
            state.success = '';
            state.error = '';
            state.loginIdentifier = '';
            state.loginPassword = '';
            state.loginPasswordVisible = false;
            authSession.persistAuthSession();
            updateAuthProgress(100, 4, 'Login berhasil', 'Runtime sesi aktif sedang dimuat.');
            render('login-success', {});
            await context.transitionTo('confirm', {
                reason: 'login-success'
            });
        } catch (error) {
            if (!mounted || requestId !== loginRequestId || String(state.stage || '') !== 'login') {
                return;
            }
            handleLoginError(error);
            render('login-error', {});
        } finally {
            if (mounted && requestId === loginRequestId && String(state.stage || '') === 'login') {
                state.busy = false;
                resetAuthProgressState();
                render('login-finally', {});
            }
        }
    }

    function applyLoginPayload(payload) {
        var source = payload && typeof payload === 'object' ? payload : {};
        state.token = String(source.token || '');
        state.user = {
            user_id: Number(source.user_id) || 0,
            role: String(source.role || ''),
            display_name: String(source.display_name || ''),
            username: String(source.username || ''),
            email: String(source.email || ''),
            kode_kelas: String(source.kode_kelas || ''),
            kode_ruang: String(source.kode_ruang || ''),
            agama: String(source.agama || ''),
            foto: String(source.foto || '')
        };
        state.selectedExamId = 0;
        state.examToken = '';
    }

    function handleLoginError(error) {
        var retryAfter = extractRetryAfterSeconds(error);
        state.error = error instanceof Error && error.message ? error.message : 'Login gagal.';
        if (retryAfter <= 0) {
            resetAuthProgressState();
            return;
        }

        state.loginRateLimitRemaining = retryAfter;
        if (state.loginRateLimitTimer) {
            window.clearInterval(state.loginRateLimitTimer);
        }
        state.loginRateLimitTimer = window.setInterval(function () {
            state.loginRateLimitRemaining = Math.max(0, Number(state.loginRateLimitRemaining) - 1);
            if (state.loginRateLimitRemaining <= 0) {
                window.clearInterval(state.loginRateLimitTimer);
                state.loginRateLimitTimer = 0;
                state.error = '';
            }
            render('login-rate-limit', {
                remaining: state.loginRateLimitRemaining
            });
        }, 1000);
        resetAuthProgressState();
    }

    function render(reason, meta) {
        if (!mounted) {
            return;
        }
        root.innerHTML = [
            '<div class="cbt-app">',
            '<main class="cbt-container cbt-container-login">',
            appShellManager.renderBody(),
            '</main>',
            appShellManager.renderAuthProgressOverlay(),
            '</div>'
        ].join('');
        uiPreferencesManager.applyUiPreferences();
        syncBodyStageClass();
        fitLoginHeroSchoolName();
        if (typeof context.recordTimeline === 'function' && reason) {
            context.recordTimeline('render:' + String(reason), 'Login stage render.', Object.assign({
                stage: 'login'
            }, meta || {}));
        }
    }

    function updateAuthProgress(percent, stepIndex, status, detail) {
        state.authProgressVisible = true;
        state.authProgressMode = 'login';
        state.authProgressPercent = Math.max(0, Math.min(100, Number(percent) || 0));
        state.authProgressStepIndex = Math.max(1, Number(stepIndex) || 1);
        state.authProgressStepTotal = 4;
        state.authProgressStatus = String(status || '');
        state.authProgressDetail = String(detail || '');
    }

    function resetAuthProgressState() {
        state.authProgressVisible = false;
        state.authProgressMode = '';
        state.authProgressPercent = 0;
        state.authProgressStepIndex = 0;
        state.authProgressStepTotal = 0;
        state.authProgressStatus = '';
        state.authProgressDetail = '';
    }

    function renderStageLoading(stage) {
        return [
            '<section class="cbt-card">',
            '<h3>Memuat stage</h3>',
            '<p class="cbt-subtitle">' + escapeHtml('Stage ' + String(stage || '-') + ' sedang disiapkan.') + '</p>',
            '</section>'
        ].join('');
    }

    function isCompactViewport() {
        return Boolean(window.innerWidth <= 1100);
    }

    function syncBodyStageClass() {
        if (!document.body) {
            return;
        }
        ['login', 'confirm', 'exam', 'result'].forEach(function (stage) {
            document.body.classList.remove('cbt-stage-' + stage);
        });
        document.body.classList.add('cbt-stage-login');
    }

    function fitLoginHeroSchoolName() {
        if (state.stage !== 'login') {
            return;
        }

        var titleNode = root.querySelector('.cbt-login-hero-heading h1');
        if (!(titleNode instanceof HTMLElement)) {
            return;
        }

        titleNode.style.removeProperty('font-size');
        var computed = window.getComputedStyle(titleNode);
        var baseFontSize = parseFloat(String(computed.fontSize || '0'));
        if (!Number.isFinite(baseFontSize) || baseFontSize <= 0) {
            return;
        }
        var lineHeight = parseFloat(String(computed.lineHeight || '0'));
        if (!Number.isFinite(lineHeight) || lineHeight <= 0) {
            lineHeight = baseFontSize * 1.08;
        }

        var fitsInTwoLines = function () {
            var currentComputed = window.getComputedStyle(titleNode);
            var currentLineHeight = parseFloat(String(currentComputed.lineHeight || '0'));
            if (!Number.isFinite(currentLineHeight) || currentLineHeight <= 0) {
                var currentFontSize = parseFloat(String(currentComputed.fontSize || '0'));
                currentLineHeight = Number.isFinite(currentFontSize) && currentFontSize > 0 ? currentFontSize * 1.08 : lineHeight;
            }
            return titleNode.scrollHeight <= (currentLineHeight * 2) + 1;
        };

        if (fitsInTwoLines()) {
            return;
        }

        var minFontSize = Math.max(20, Math.round(baseFontSize * 0.58));
        var currentFontSize = Math.round(baseFontSize);
        while (currentFontSize > minFontSize) {
            currentFontSize -= 1;
            titleNode.style.fontSize = String(currentFontSize) + 'px';
            if (fitsInTwoLines()) {
                return;
            }
        }
    }
}

function extractRetryAfterSeconds(error) {
    var retryAfter = Number(error && error.retry_after) || 0;
    if (retryAfter <= 0 && error && error.data && typeof error.data === 'object') {
        retryAfter = Number(error.data.retry_after) || 0;
    }
    if (retryAfter <= 0) {
        retryAfter = Math.ceil((Number(error && error.retry_after_ms) || 0) / 1000);
    }
    return Math.max(0, retryAfter);
}
