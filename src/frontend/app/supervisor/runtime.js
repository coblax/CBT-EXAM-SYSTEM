import { getFrontendConfig } from '../core/config.js';
import { createBrowserStorageAccess } from '../core/browser-storage.js';
import { createApiClient } from '../core/api.js';
import { escapeHtml } from '../core/html.js';

var SUPERVISOR_AUTH_STORAGE_KEY = 'cbt_exam_frontend_supervisor_auth_v1';
var SUPERVISOR_AUTO_REFRESH_MS = 15000;
var LOGIN_IDENTIFIER_MAX_LENGTH = 191;
var LOGIN_PASSWORD_MAX_LENGTH = 1024;

export function normalizeSupervisorPercentValue(value, fallbackValue) {
    var candidates = [value, fallbackValue];

    for (var i = 0; i < candidates.length; i++) {
        var candidate = candidates[i];
        if (candidate === null || candidate === undefined) {
            continue;
        }

        var numeric = Number(candidate);
        if (typeof candidate === 'number' && Number.isFinite(numeric)) {
            return Math.max(0, Math.min(100, numeric));
        }

        var raw = String(candidate).trim();
        if (raw === '') {
            continue;
        }

        raw = raw.replace(/%/g, '').replace(/\s+/g, '');
        if (raw.indexOf(',') !== -1 && raw.indexOf('.') !== -1) {
            raw = raw.lastIndexOf(',') > raw.lastIndexOf('.')
                ? raw.replace(/\./g, '').replace(',', '.')
                : raw.replace(/,/g, '');
        } else if (raw.indexOf(',') !== -1) {
            raw = raw.replace(',', '.');
        }

        numeric = Number(raw);
        if (Number.isFinite(numeric)) {
            return Math.max(0, Math.min(100, numeric));
        }
    }

    return 0;
}

export function buildSupervisorDashboardCacheKey(query) {
    var source = query && typeof query === 'object' ? query : {};
    var normalized = {};
    Object.keys(source).sort().forEach(function (key) {
        normalized[key] = source[key];
    });

    return JSON.stringify(normalized);
}

function renderSupervisorSecurityMeta(parts) {
    var values = (Array.isArray(parts) ? parts : []).map(function (part) {
        return String(part || '').trim();
    }).filter(Boolean);

    if (!values.length) {
        return '';
    }

    return '<small class="cbt-supervisor-row-meta">' + values.map(function (part) {
        return '<span>' + escapeHtml(part) + '</span>';
    }).join('') + '</small>';
}

function normalizeSupervisorSecurityClass(value, fallbackValue, allowedValues) {
    var normalized = String(value || fallbackValue || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
    return allowedValues.indexOf(normalized) !== -1 ? normalized : String(fallbackValue || '');
}

export function renderSupervisorSecurityTimelineSection(securityTimeline, fallbackEvents) {
    var timeline = securityTimeline && typeof securityTimeline === 'object' ? securityTimeline : {};
    var summary = timeline.summary && typeof timeline.summary === 'object' ? timeline.summary : {};
    var items = Array.isArray(timeline.items) ? timeline.items : [];
    var fallbackItems = Array.isArray(fallbackEvents) ? fallbackEvents : [];
    if (!items.length && fallbackItems.length) {
        items = fallbackItems;
    }
    var topIndicators = Array.isArray(summary.top_indicators) ? summary.top_indicators : [];
    var eventCounts = Array.isArray(timeline.event_counts)
        ? timeline.event_counts
        : (timeline.event_counts && typeof timeline.event_counts === 'object' ? Object.keys(timeline.event_counts).map(function (key) {
            return timeline.event_counts[key];
        }) : []);
    var indicators = topIndicators.length ? topIndicators : eventCounts;
    var totalEvents = Math.max(0, Number(summary.total_events !== undefined ? summary.total_events : items.length) || 0);
    var warningCount = Math.max(0, Number(summary.warning_count) || 0);
    var criticalCount = Math.max(0, Number(summary.critical_count) || 0);
    var riskTone = normalizeSupervisorSecurityClass(summary.risk_tone, 'normal', ['normal', 'watch', 'high-risk']);
    var riskLabel = String(summary.risk_label || 'Normal');
    var riskScoreLabel = String(summary.risk_score_label || '0');

    indicators = indicators.filter(function (indicator) {
        return indicator && typeof indicator === 'object';
    }).sort(function (left, right) {
        var countCompare = (Number(right.count) || 0) - (Number(left.count) || 0);
        if (countCompare !== 0) {
            return countCompare;
        }
        return String(left.label || left.event_type || '').localeCompare(String(right.label || right.event_type || ''));
    }).slice(0, 5);

    return [
        '<section class="cbt-supervisor-detail-section cbt-supervisor-security-timeline">',
        '<div class="cbt-supervisor-security-timeline-head">',
        '<div>',
        '<h3>Security Timeline</h3>',
        '<p>Event tercatat sebagai indikasi forensik, bukan vonis otomatis.</p>',
        '</div>',
        '<div class="cbt-supervisor-security-summary">',
        '<span class="cbt-supervisor-security-chip is-' + escapeHtml(riskTone) + '">' + escapeHtml(riskLabel + ' · Skor ' + riskScoreLabel) + '</span>',
        '<span class="cbt-supervisor-security-chip">' + escapeHtml(String(totalEvents) + ' event') + '</span>',
        '<span class="cbt-supervisor-security-chip is-warning">' + escapeHtml(String(warningCount) + ' warning') + '</span>',
        '<span class="cbt-supervisor-security-chip is-critical">' + escapeHtml(String(criticalCount) + ' critical') + '</span>',
        '</div>',
        '</div>',
        indicators.length ? '<div class="cbt-supervisor-security-indicators">' + indicators.map(function (indicator) {
            return '<span>' + escapeHtml(String(indicator.label || indicator.event_label || indicator.event_type || 'Event')) + '<strong>' + escapeHtml(String(Math.max(0, Number(indicator.count) || 0))) + '</strong></span>';
        }).join('') + '</div>' : '',
        items.length ? '<div class="cbt-supervisor-detail-events cbt-supervisor-security-timeline-list">' + items.map(function (eventItem) {
            var severity = normalizeSupervisorSecurityClass(eventItem && eventItem.severity, 'info', ['info', 'warning', 'critical']);
            var count = Math.max(1, Number(eventItem && eventItem.count) || 1);
            var firstTime = String((eventItem && (eventItem.first_occurred_at || eventItem.occurred_at)) || '');
            var lastTime = String((eventItem && (eventItem.last_occurred_at || eventItem.occurred_at || eventItem.created_at)) || '');
            var timeLabel = firstTime && lastTime && firstTime !== lastTime ? firstTime + ' - ' + lastTime : (lastTime || firstTime);

            return [
                '<div class="cbt-supervisor-security-event is-' + escapeHtml(severity) + '">',
                '<span class="cbt-supervisor-pill is-' + escapeHtml(severity) + '">' + escapeHtml(severity) + '</span>',
                '<strong>' + escapeHtml(String((eventItem && (eventItem.event_label || eventItem.event_type)) || '-')) + '</strong>',
                count > 1 ? '<span class="cbt-supervisor-security-count">x' + escapeHtml(String(count)) + '</span>' : '',
                renderSupervisorSecurityMeta([
                    eventItem && eventItem.message_display,
                    eventItem && eventItem.device_summary,
                    timeLabel
                ]),
                '</div>'
            ].join('');
        }).join('') + '</div>' : '<div class="cbt-supervisor-empty-state is-compact">Belum ada event security untuk attempt ini.</div>',
        '</section>'
    ].join('');
}

export function bootstrapSupervisorApp() {
    var root = document.getElementById('cbt-exam-app');
    if (!root) {
        return;
    }
    if (root.getAttribute('data-supervisor-runtime-mounted') === '1') {
        return;
    }
    root.setAttribute('data-supervisor-runtime-mounted', '1');

    var config = getFrontendConfig(window);
    var browserStorage = createBrowserStorageAccess(window);
    var refreshTimer = 0;
    var requestCounter = 0;
    var detailRequestCounter = 0;
    var state = {
        token: '',
        user: null,
        dashboard: null,
        activeTab: 'overview',
        filters: {
            examId: 0,
            kelas: '',
            ruang: '',
            studentKeyword: '',
            status: '',
            rosterPage: 1,
            attemptsPage: 1,
            actionPage: 1,
            securityPage: 1,
            securitySeverity: 'all',
            securityEventType: 'all',
            securityDeviceType: 'all',
            attendancePage: 1,
            attendanceStatus: ''
        },
        dashboardCache: {},
        loadingTabs: {},
        detailDrawer: {
            open: false,
            busy: false,
            error: '',
            attemptId: 0,
            data: null
        },
        loginBusy: false,
        dashboardBusy: false,
        notice: '',
        error: '',
        activeResetAttemptId: 0
    };
    var apiClient = createApiClient({
        config: config,
        diagnosticsManager: null,
        state: state,
        fetchImpl: window.fetch.bind(window),
        expireAuthSession: function (message) {
            clearPersistedAuthSession();
            state.token = '';
            state.user = null;
            state.dashboard = null;
            state.dashboardBusy = false;
            state.loginBusy = false;
            state.error = String(message || 'Sesi pengawas berakhir. Silakan login kembali.');
            render();
        },
        getNavigatorConnectionStatus: function () {
            return window.navigator && window.navigator.onLine === false ? 'offline' : 'online';
        },
        isAnswerSubmitPath: function () {
            return false;
        },
        schedulePendingAnswerRetry: function () {
        },
        setConnectionStatus: function () {
        },
        windowRef: window
    });

    function setBootProgress(percent, label, status) {
        var fill = root.querySelector('#cbt-boot-progress-fill');
        var labelNode = root.querySelector('#cbt-boot-progress-label');
        var statusNode = root.querySelector('#cbt-boot-progress-status');
        var valueNode = root.querySelector('#cbt-boot-progress-value');
        var safePercent = Math.max(0, Math.min(100, Number(percent) || 0));

        if (fill instanceof HTMLElement) {
            fill.style.width = String(safePercent) + '%';
        }
        if (labelNode instanceof HTMLElement && typeof label === 'string' && label !== '') {
            labelNode.textContent = label;
        }
        if (statusNode instanceof HTMLElement && typeof status === 'string' && status !== '') {
            statusNode.textContent = status;
        }
        if (valueNode instanceof HTMLElement) {
            valueNode.textContent = String(Math.round(safePercent)) + '%';
        }
    }

    function normalizeUser(rawUser) {
        if (!rawUser || typeof rawUser !== 'object') {
            return null;
        }

        var normalized = {
            user_id: Number(rawUser.user_id) || 0,
            role: String(rawUser.role || ''),
            display_name: String(rawUser.display_name || ''),
            username: String(rawUser.username || ''),
            email: String(rawUser.email || '')
        };

        if (normalized.user_id <= 0 || normalized.role === '') {
            return null;
        }

        return normalized;
    }

    function isSupervisorRole(role) {
        return ['admin', 'administrator', 'guru', 'teacher'].indexOf(String(role || '').toLowerCase()) !== -1;
    }

    function getSessionStorage() {
        return browserStorage.getSessionStorage();
    }

    function readPersistedAuthSession() {
        var storage = getSessionStorage();
        if (!storage) {
            return null;
        }

        try {
            var raw = storage.getItem(SUPERVISOR_AUTH_STORAGE_KEY);
            if (!raw) {
                return null;
            }

            var parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') {
                return null;
            }

            var token = String(parsed.token || '');
            var user = normalizeUser(parsed.user || null);
            if (token === '' || !user) {
                return null;
            }

            return {
                token: token,
                user: user
            };
        } catch (error) {
            return null;
        }
    }

    function persistAuthSession() {
        var storage = getSessionStorage();
        if (!storage || !state.token || !state.user) {
            return;
        }

        try {
            storage.setItem(SUPERVISOR_AUTH_STORAGE_KEY, JSON.stringify({
                token: String(state.token || ''),
                user: state.user
            }));
        } catch (error) {
            // Ignore storage errors for supervisor frontend.
        }
    }

    function clearPersistedAuthSession() {
        var storage = getSessionStorage();
        if (!storage) {
            return;
        }

        try {
            storage.removeItem(SUPERVISOR_AUTH_STORAGE_KEY);
        } catch (error) {
            // Ignore storage errors for supervisor frontend.
        }
    }

    function stopAutoRefresh() {
        if (refreshTimer) {
            window.clearTimeout(refreshTimer);
            refreshTimer = 0;
        }
    }

    function scheduleAutoRefresh() {
        stopAutoRefresh();
        if (!state.token || !state.user) {
            return;
        }

        refreshTimer = window.setTimeout(function () {
            if (document.hidden) {
                scheduleAutoRefresh();
                return;
            }

            loadDashboard({
                silent: true,
                keepNotice: true
            }).finally(scheduleAutoRefresh);
        }, SUPERVISOR_AUTO_REFRESH_MS);
    }

    function getActiveFilterMode(tab) {
        var activeTab = String(tab || state.activeTab || 'overview');
        if (activeTab === 'token_gate') {
            return 'token';
        }
        if (activeTab === 'security_log') {
            return 'security';
        }
        if (activeTab === 'attendance') {
            return 'attendance';
        }
        if (activeTab === 'action_required') {
            return 'action';
        }
        if (activeTab === 'submit_recovery') {
            return 'submit';
        }
        if (activeTab === 'live_roster' || activeTab === 'must_watch' || activeTab === 'monitoring_attempts') {
            return 'operational';
        }

        return 'overview';
    }

    function shouldSendExamFilter(mode) {
        return ['token', 'attendance', 'action', 'submit', 'operational'].indexOf(String(mode || '')) !== -1;
    }

    function shouldSendOperationalFilters(mode) {
        return ['action', 'operational', 'submit'].indexOf(String(mode || '')) !== -1;
    }

    function buildDashboardQuery() {
        var mode = getActiveFilterMode(state.activeTab);
        var activeTab = String(state.activeTab || 'overview');
        var query = {
            tab: activeTab,
            exam_id: 0,
            kelas: '',
            ruang: '',
            student_keyword: '',
            status: '',
            roster_page: activeTab === 'live_roster' ? (Number(state.filters.rosterPage) || 1) : 1,
            attempts_page: activeTab === 'monitoring_attempts' || activeTab === 'submit_recovery' ? (Number(state.filters.attemptsPage) || 1) : 1,
            action_page: activeTab === 'action_required' ? (Number(state.filters.actionPage) || 1) : 1,
            security_page: activeTab === 'security_log' ? (Number(state.filters.securityPage) || 1) : 1,
            security_severity: 'all',
            security_event_type: 'all',
            security_device_type: 'all',
            attendance_page: activeTab === 'attendance' ? (Number(state.filters.attendancePage) || 1) : 1,
            attendance_status: ''
        };

        if (shouldSendExamFilter(mode)) {
            query.exam_id = Number(state.filters.examId) || 0;
        }
        if (shouldSendOperationalFilters(mode)) {
            query.kelas = String(state.filters.kelas || '');
            query.ruang = String(state.filters.ruang || '');
            query.student_keyword = String(state.filters.studentKeyword || '');
        }
        if (mode === 'operational') {
            query.status = String(state.filters.status || '');
        }
        if (mode === 'security') {
            query.security_severity = String(state.filters.securitySeverity || 'all');
            query.security_event_type = String(state.filters.securityEventType || 'all');
            query.security_device_type = String(state.filters.securityDeviceType || 'all');
        }
        if (mode === 'attendance') {
            query.attendance_status = String(state.filters.attendanceStatus || '');
        }

        return query;
    }

    function isActiveTabLoading() {
        return !!state.loadingTabs[String(state.activeTab || 'overview')];
    }

    function isDashboardForActiveTab() {
        return !!(
            state.dashboard
            && state.dashboard.filters
            && String(state.dashboard.filters.tab || 'overview') === String(state.activeTab || 'overview')
        );
    }

    async function loadDashboard(options) {
        options = options || {};
        if (!state.token || !state.user) {
            return null;
        }

        var query = buildDashboardQuery();
        var cacheKey = buildSupervisorDashboardCacheKey(query);
        var activeTab = String(state.activeTab || 'overview');
        var cachedPayload = state.dashboardCache[cacheKey];
        if (cachedPayload && typeof cachedPayload === 'object') {
            state.dashboard = cachedPayload;
        }

        state.dashboardBusy = options.silent !== true;
        if (options.keepNotice !== true) {
            state.notice = '';
        }
        state.error = '';
        var requestId = ++requestCounter;
        state.loadingTabs[activeTab] = requestId;
        if (options.silent !== true || !cachedPayload) {
            render();
        }

        try {
            var payload = await apiClient.api('supervisor_dashboard', {
                method: 'GET',
                query: query
            });
            if (requestId !== requestCounter) {
                return payload;
            }

            state.dashboard = payload && typeof payload === 'object' ? payload : null;
            if (state.dashboard) {
                state.dashboardCache[cacheKey] = state.dashboard;
            }
            return state.dashboard;
        } catch (error) {
            if (requestId !== requestCounter) {
                return null;
            }

            state.error = error instanceof Error ? error.message : 'Gagal memuat dashboard pengawas.';
            if (Number(error && error.status) === 401 || Number(error && error.status) === 403) {
                clearPersistedAuthSession();
                state.token = '';
                state.user = null;
                state.dashboard = null;
            }
            return null;
        } finally {
            if (state.loadingTabs[activeTab] === requestId) {
                delete state.loadingTabs[activeTab];
            }
            if (requestId === requestCounter) {
                state.dashboardBusy = false;
                render();
            }
        }
    }

    async function submitLogin(identifier, password) {
        identifier = String(identifier || '').trim();
        password = String(password || '');
        if (identifier === '' || password === '') {
            state.error = 'Identifier dan password wajib diisi.';
            state.notice = '';
            render();
            return;
        }
        if (identifier.length > LOGIN_IDENTIFIER_MAX_LENGTH || password.length > LOGIN_PASSWORD_MAX_LENGTH) {
            state.error = 'Identifier atau password terlalu panjang.';
            state.notice = '';
            render();
            return;
        }

        state.loginBusy = true;
        state.error = '';
        state.notice = '';
        render();

        try {
            var payload = await apiClient.api('login', {
                method: 'POST',
                auth: false,
                body: {
                    identifier: identifier,
                    password: password
                }
            });
            var normalizedUser = normalizeUser(payload || null);
            if (!normalizedUser || !payload || typeof payload.token !== 'string' || payload.token.trim() === '') {
                throw new Error('Payload login pengawas tidak valid.');
            }

            if (!isSupervisorRole(normalizedUser.role)) {
                try {
                    await apiClient.api('logout', {
                        method: 'POST',
                        auth: true,
                        token: String(payload.token || '')
                    });
                } catch (logoutError) {
                    // Best effort cleanup if student login lands on /pengawas.
                }

                clearPersistedAuthSession();
                state.token = '';
                state.user = null;
                state.dashboard = null;
                state.error = 'Halaman /pengawas hanya untuk guru atau admin. Gunakan halaman ujian siswa untuk login peserta.';
                render();
                return;
            }

            state.token = String(payload.token || '');
            state.user = normalizedUser;
            persistAuthSession();
            render();
            await loadDashboard();
            scheduleAutoRefresh();
        } catch (error) {
            state.error = error instanceof Error ? error.message : 'Login pengawas gagal.';
        } finally {
            state.loginBusy = false;
            render();
        }
    }

    async function performLogout() {
        stopAutoRefresh();
        var activeToken = String(state.token || '');

        clearPersistedAuthSession();
        state.token = '';
        state.user = null;
        state.dashboard = null;
        state.activeResetAttemptId = 0;

        try {
            if (activeToken !== '') {
                await apiClient.api('logout', {
                    method: 'POST',
                    auth: true,
                    token: activeToken
                });
            }
        } catch (error) {
            // Logout error should not block local cleanup.
        }

        state.notice = 'Sesi pengawas sudah ditutup.';
        state.error = '';
        render();
    }

    async function performResetLogin(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        if (safeAttemptId <= 0 || state.activeResetAttemptId > 0) {
            return;
        }

        state.activeResetAttemptId = safeAttemptId;
        state.notice = '';
        state.error = '';
        render();

        try {
            var payload = await apiClient.api('supervisor_reset_login', {
                method: 'POST',
                body: {
                    attempt_id: safeAttemptId
                }
            });
            state.notice = payload && payload.message
                ? String(payload.message)
                : 'Login siswa berhasil di-reset.';
            state.dashboardCache = {};
            await loadDashboard({
                silent: true,
                keepNotice: true
            });
        } catch (error) {
            state.error = error instanceof Error ? error.message : 'Gagal mereset login siswa.';
        } finally {
            state.activeResetAttemptId = 0;
            render();
        }
    }

    async function openAttemptDetail(attemptId) {
        var safeAttemptId = Number(attemptId) || 0;
        if (safeAttemptId <= 0) {
            return;
        }

        var requestId = ++detailRequestCounter;
        state.detailDrawer = {
            open: true,
            busy: true,
            error: '',
            attemptId: safeAttemptId,
            data: null
        };
        render();

        try {
            var payload = await apiClient.api('supervisor_attempt_detail', {
                method: 'GET',
                query: {
                    attempt_id: safeAttemptId
                }
            });
            if (requestId !== detailRequestCounter) {
                return;
            }

            state.detailDrawer.data = payload && typeof payload === 'object' ? payload : null;
            state.detailDrawer.error = state.detailDrawer.data ? '' : 'Detail attempt kosong.';
        } catch (error) {
            if (requestId !== detailRequestCounter) {
                return;
            }

            state.detailDrawer.error = error instanceof Error ? error.message : 'Gagal memuat detail attempt.';
            state.detailDrawer.data = null;
        } finally {
            if (requestId === detailRequestCounter) {
                state.detailDrawer.busy = false;
                render();
            }
        }
    }

    function closeAttemptDetail() {
        detailRequestCounter++;
        state.detailDrawer = {
            open: false,
            busy: false,
            error: '',
            attemptId: 0,
            data: null
        };
        render();
    }

    function applyFilterForm(form) {
        var data = new window.FormData(form);
        if (data.has('exam_id')) {
            state.filters.examId = Number(data.get('exam_id')) || 0;
        }
        if (data.has('kelas')) {
            state.filters.kelas = String(data.get('kelas') || '');
        }
        if (data.has('ruang')) {
            state.filters.ruang = String(data.get('ruang') || '');
        }
        if (data.has('student_keyword')) {
            state.filters.studentKeyword = String(data.get('student_keyword') || '').trim();
        }
        if (data.has('status')) {
            state.filters.status = String(data.get('status') || '');
        }
        if (data.has('security_severity')) {
            state.filters.securitySeverity = String(data.get('security_severity') || 'all');
        }
        if (data.has('security_event_type')) {
            state.filters.securityEventType = String(data.get('security_event_type') || 'all');
        }
        if (data.has('security_device_type')) {
            state.filters.securityDeviceType = String(data.get('security_device_type') || 'all');
        }
        if (data.has('attendance_status')) {
            state.filters.attendanceStatus = String(data.get('attendance_status') || '');
        }
        state.filters.rosterPage = 1;
        state.filters.attemptsPage = 1;
        state.filters.actionPage = 1;
        state.filters.securityPage = 1;
        state.filters.attendancePage = 1;
    }

    function renderNoticeStack() {
        var fragments = [];

        if (state.notice !== '') {
            fragments.push('<div class="cbt-supervisor-alert is-success">' + escapeHtml(state.notice) + '</div>');
        }
        if (state.error !== '') {
            fragments.push('<div class="cbt-supervisor-alert is-error">' + escapeHtml(state.error) + '</div>');
        }

        return fragments.join('');
    }

    function renderSupervisorIcon(name) {
        var icons = {
            activity: '<path d="M22 12h-4l-3 8-6-16-3 8H2"></path>',
            alert: '<path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>',
            bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M13.7 21a2 2 0 0 1-3.4 0"></path>',
            calendar: '<path d="M8 2v4"></path><path d="M16 2v4"></path><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M3 10h18"></path>',
            clipboard: '<path d="M9 5h6"></path><path d="M9 12l2 2 4-4"></path><path d="M8 3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"></path>',
            dashboard: '<rect x="3" y="3" width="7" height="8" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="15" width="7" height="6" rx="1.5"></rect>',
            eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle>',
            filter: '<path d="M3 5h18"></path><path d="M7 12h10"></path><path d="M10 19h4"></path>',
            key: '<path d="M21 2l-2 2"></path><path d="M15 8l-2 2"></path><path d="m7 16 3-3"></path><circle cx="7.5" cy="16.5" r="4.5"></circle><path d="m11 13 8-8 2 2-8 8"></path>',
            logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path>',
            monitor: '<rect x="3" y="4" width="18" height="12" rx="2"></rect><path d="M8 20h8"></path><path d="M12 16v4"></path>',
            radio: '<path d="M4.9 19.1a10 10 0 0 1 0-14.2"></path><path d="M7.8 16.2a6 6 0 0 1 0-8.5"></path><circle cx="12" cy="12" r="2"></circle><path d="M16.2 7.8a6 6 0 0 1 0 8.5"></path><path d="M19.1 4.9a10 10 0 0 1 0 14.2"></path>',
            refresh: '<path d="M20 11a8.1 8.1 0 0 0-15.5-2M4 5v4h4"></path><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"></path>',
            search: '<circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path>',
            users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.9"></path><path d="M16 3.1a4 4 0 0 1 0 7.8"></path>',
            x: '<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>'
        };
        var path = icons[name] || icons.activity;
        return '<svg class="cbt-supervisor-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + path + '</svg>';
    }

    function renderLiveDot() {
        return '<span class="cbt-supervisor-live-dot" aria-hidden="true"><span></span></span>';
    }

    function getInitialsFromText(text, fallback) {
        var source = String(text || fallback || 'PG');
        var parts = source.trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return String(fallback || 'PG').slice(0, 2).toUpperCase();
        }
        if (parts.length === 1) {
            return parts[0].slice(0, 2).toUpperCase();
        }
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    function getSupervisorInitials(user) {
        return getInitialsFromText(user ? String(user.display_name || user.username || 'Pengawas') : 'Pengawas', 'PG');
    }

    function normalizeBrandUrl(value) {
        var text = String(value || '').trim();
        if (!text) {
            return '';
        }
        if (!/^https?:\/\//i.test(text) && !/^\/\//.test(text) && text.charAt(0) !== '/') {
            return '';
        }

        return text;
    }

    function getSupervisorBranding() {
        var schoolName = String(config.schoolName || config.siteName || '').replace(/\s+/g, ' ').trim();
        var programName = String(config.examProgramName || '').replace(/\s+/g, ' ').trim();
        var motto = String(config.schoolMotto || '').replace(/\s+/g, ' ').trim();
        var logoPrimaryUrl = normalizeBrandUrl(config.schoolLogoUrl || config.schoolLogo1Url || '');
        var logoSecondaryUrl = normalizeBrandUrl(config.schoolLogo2Url || '');

        if (schoolName === '') {
            schoolName = 'CBT Exam';
        }
        if (programName === '') {
            programName = 'Dashboard Pengawas';
        }

        return {
            schoolName: schoolName,
            programName: programName,
            motto: motto,
            logoPrimaryUrl: logoPrimaryUrl,
            logoSecondaryUrl: logoSecondaryUrl
        };
    }

    function renderSupervisorBrandMark(branding) {
        var brand = branding && typeof branding === 'object' ? branding : getSupervisorBranding();
        if (String(brand.logoPrimaryUrl || '') !== '') {
            return '<span class="cbt-supervisor-brand-mark has-logo"><img class="cbt-supervisor-brand-logo" src="' + escapeHtml(String(brand.logoPrimaryUrl || '')) + '" alt="' + escapeHtml(String(brand.schoolName || 'CBT Exam')) + '" loading="lazy" decoding="async" /></span>';
        }

        return '<span class="cbt-supervisor-brand-mark">' + renderSupervisorIcon('monitor') + '</span>';
    }

    function renderSupervisorBrandIdentity() {
        var branding = getSupervisorBranding();

        return [
            '<div class="cbt-supervisor-brand">',
            renderSupervisorBrandMark(branding),
            '<span><strong>' + escapeHtml(branding.schoolName) + '</strong><small>' + escapeHtml(branding.programName) + '</small></span>',
            '</div>'
        ].join('');
    }

    function renderProgressBar(percent) {
        var numericPercent = normalizeSupervisorPercentValue(percent);
        return '<span class="cbt-supervisor-progress" aria-hidden="true"><span style="width:' + escapeHtml(String(numericPercent)) + '%"></span></span>';
    }

    function formatSupervisorPercentLabel(percent, preferredLabel) {
        var label = String(preferredLabel || '').trim();
        if (label !== '') {
            return label;
        }

        var numericPercent = normalizeSupervisorPercentValue(percent);
        var rounded = Math.round(numericPercent * 100) / 100;
        return (Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(2)) + '%';
    }

    function renderAnswerProgressCell(item) {
        var answerCount = Math.max(0, Number(item && item.answer_count !== undefined ? item.answer_count : 0) || 0);
        var questionCount = Math.max(0, Number(item && item.question_count !== undefined ? item.question_count : 0) || 0);
        var answeredPercent = normalizeSupervisorPercentValue(
            item && item.answered_percentage !== undefined ? item.answered_percentage : null,
            item && item.answered_percentage_label !== undefined ? item.answered_percentage_label : null
        );
        var percentLabel = formatSupervisorPercentLabel(
            answeredPercent,
            item && item.answered_percentage_label !== undefined ? item.answered_percentage_label : ''
        );
        var countLabel = questionCount > 0
            ? String(answerCount) + ' / ' + String(questionCount) + ' soal terjawab'
            : String(answerCount) + ' soal terjawab';

        return [
            '<div class="cbt-supervisor-answer-progress">',
            '<div class="cbt-supervisor-answer-progress-line">',
            renderProgressBar(answeredPercent),
            '<strong>' + escapeHtml(percentLabel) + '</strong>',
            '</div>',
            '<small>' + escapeHtml(countLabel) + '</small>',
            '</div>'
        ].join('');
    }

    function renderRowMeta(parts) {
        var values = (Array.isArray(parts) ? parts : []).map(function (part) {
            return String(part || '').trim();
        }).filter(Boolean);

        if (!values.length) {
            return '';
        }

        return '<small class="cbt-supervisor-row-meta">' + values.map(function (part) {
            return '<span>' + escapeHtml(part) + '</span>';
        }).join('') + '</small>';
    }

    function renderStudentCell(name, fallback, metaParts) {
        return [
            '<div class="cbt-supervisor-student-cell">',
            '<span class="cbt-supervisor-avatar">' + escapeHtml(getInitialsFromText(name, fallback || 'SW')) + '</span>',
            '<span><strong>' + escapeHtml(String(name || '-')) + '</strong>' + renderRowMeta(metaParts) + '</span>',
            '</div>'
        ].join('');
    }

    function renderAttemptActions(item, options) {
        options = options || {};
        var source = item && typeof item === 'object' ? item : {};
        var attemptId = Number(source.attempt_id) || 0;
        if (attemptId <= 0) {
            return '<span class="cbt-supervisor-row-empty">-</span>';
        }

        var studentName = String(source.student_name || source.student_username || source.student_login || 'siswa');
        var resetBusy = Number(state.activeResetAttemptId) === attemptId;
        var buttons = [
            '<button class="cbt-supervisor-button is-small" type="button" data-action="open-attempt-detail" data-attempt-id="' + escapeHtml(String(attemptId)) + '">' + renderSupervisorIcon('eye') + '<span>Detail</span></button>'
        ];

        if (options.reset !== false) {
            buttons.push(
                '<button class="cbt-supervisor-button is-small" type="button" data-action="reset-login" data-attempt-id="' + escapeHtml(String(attemptId)) + '" data-student-name="' + escapeHtml(studentName) + '"' + (resetBusy ? ' disabled' : '') + '>' + renderSupervisorIcon('refresh') + '<span>' + escapeHtml(resetBusy ? 'Mereset...' : 'Reset') + '</span></button>'
            );
        }

        return '<div class="cbt-supervisor-row-actions">' + buttons.join('') + '</div>';
    }

    function renderPanelSkeleton() {
        return [
            '<div class="cbt-supervisor-skeleton-list" aria-live="polite" aria-busy="true">',
            '<span class="cbt-supervisor-skeleton-line is-wide"></span>',
            '<span class="cbt-supervisor-skeleton-line"></span>',
            '<span class="cbt-supervisor-skeleton-line is-short"></span>',
            '<span class="cbt-supervisor-skeleton-line is-wide"></span>',
            '</div>'
        ].join('');
    }

    function renderLoginView() {
        var studentLink = String(config.studentFrontendUrl || '').trim();
        var branding = getSupervisorBranding();
        var heroText = branding.motto !== ''
            ? branding.motto
            : 'Masuk sebagai guru atau admin untuk memantau roster live, must watch, attempts, dan status submit.';
        var alternateLink = studentLink !== ''
            ? '<p class="cbt-supervisor-login-help">Peserta ujian gunakan <a href="' + escapeHtml(studentLink) + '">halaman ujian siswa</a>.</p>'
            : '';

        return [
            '<div class="cbt-supervisor-login-shell">',
            '<header class="cbt-supervisor-topbar cbt-supervisor-topbar-login">',
            renderSupervisorBrandIdentity(),
            '<div class="cbt-supervisor-topbar-status">' + renderLiveDot() + '<span>Mode Pengawas</span></div>',
            '</header>',
            '<main class="cbt-supervisor-login-main">',
            '<section class="cbt-supervisor-login-copy">',
            '<div class="cbt-supervisor-login-program"><span>Program Ujian</span><strong>' + escapeHtml(branding.programName) + '</strong></div>',
            '<h1>' + escapeHtml(branding.schoolName) + '</h1>',
            '<p>' + escapeHtml(heroText) + '</p>',
            '<div class="cbt-supervisor-login-points">',
            '<span>' + renderSupervisorIcon('key') + '<strong>TOKEN & GATE</strong><small>Token global dan antrean start</small></span>',
            '<span>' + renderSupervisorIcon('calendar') + '<strong>DAFTAR HADIR</strong><small>Status peserta per exam</small></span>',
            '<span>' + renderSupervisorIcon('alert') + '<strong>BUTUH TINDAKAN</strong><small>Prioritas respons cepat</small></span>',
            '<span>' + renderSupervisorIcon('radio') + '<strong>LIVE ROSTER</strong><small>Status koneksi peserta</small></span>',
            '<span>' + renderSupervisorIcon('alert') + '<strong>MUST WATCH</strong><small>Prioritas risiko tertinggi</small></span>',
            '<span>' + renderSupervisorIcon('bell') + '<strong>SECURITY LOG</strong><small>Event keamanan real-time</small></span>',
            '<span>' + renderSupervisorIcon('clipboard') + '<strong>ATTEMPTS</strong><small>Progress dan finalisasi</small></span>',
            '<span>' + renderSupervisorIcon('activity') + '<strong>SUBMIT RECOVERY</strong><small>Watchlist submit bermasalah</small></span>',
            '</div>',
            '</section>',
            '<section class="cbt-supervisor-login-card">',
            '<div class="cbt-supervisor-login-kicker">' + escapeHtml(branding.programName) + '</div>',
            '<h2>MASUK KE DASHBOARD</h2>',
            '<p>' + escapeHtml(branding.schoolName) + '</p>',
            renderNoticeStack(),
            '<form class="cbt-supervisor-login-form" data-supervisor-login-form>',
            '<label class="cbt-supervisor-field">',
            '<span>IDENTIFIER</span>',
            '<input type="text" name="identifier" autocomplete="username" maxlength="191" placeholder="Username, email, atau NISN" required ' + (state.loginBusy ? 'disabled' : '') + ' />',
            '</label>',
            '<label class="cbt-supervisor-field">',
            '<span>PASSWORD</span>',
            '<input type="password" name="password" autocomplete="current-password" maxlength="1024" placeholder="Masukkan password" required ' + (state.loginBusy ? 'disabled' : '') + ' />',
            '</label>',
            '<div class="cbt-supervisor-login-actions">',
            '<button class="cbt-supervisor-button is-primary" type="submit" ' + (state.loginBusy ? 'disabled' : '') + '>' + escapeHtml(state.loginBusy ? 'MEMPROSES LOGIN...' : 'LOGIN PENGAWAS') + '</button>',
            '</div>',
            '</form>',
            alternateLink,
            '</section>',
            '</main>',
            '</div>'
        ].join('');
    }

    function renderStatusSnapshot() {
        var snapshot = state.dashboard && state.dashboard.status_snapshot ? state.dashboard.status_snapshot : {};
        var backlogCount = Math.max(0, Number(snapshot.backlog_count) || 0);
        var mode = String(snapshot.mode || '-');
        var statusLabel = String(snapshot.status_label || 'Status ingest belum tersedia.');

        return [
            '<section class="cbt-supervisor-status-card">',
            '<div class="cbt-supervisor-status-head">',
            '<div>',
            '<span class="cbt-supervisor-kicker">Security Ingest</span>',
            '<h2>' + escapeHtml(statusLabel) + '</h2>',
            '<p>' + escapeHtml(String(snapshot.live_label || 'Live telemetry')) + ' / ' + escapeHtml(String(snapshot.ingest_label || 'Ingest')) + ' / ' + escapeHtml(String(snapshot.persist_label || 'Persist')) + '</p>',
            '</div>',
            '<span class="cbt-supervisor-status-badge">' + renderLiveDot() + escapeHtml(mode.toUpperCase()) + '</span>',
            '</div>',
            '<div class="cbt-supervisor-status-grid">',
            '<div><span>Backlog</span><strong>' + escapeHtml(String(backlogCount)) + '</strong><small>Event menunggu proses</small></div>',
            '<div><span>Dead Letter</span><strong>' + escapeHtml(String(Math.max(0, Number(snapshot.dead_letter_count) || 0))) + '</strong><small>Butuh perhatian</small></div>',
            '<div><span>Last Flush</span><strong>' + escapeHtml(String(snapshot.last_flush_at || '-')) + '</strong><small>Batch terakhir</small></div>',
            '<div><span>Next Flush</span><strong>' + escapeHtml(String(snapshot.next_flush_at || '-')) + '</strong><small>Jadwal berikutnya</small></div>',
            '</div>',
            '</section>'
        ].join('');
    }

    function renderSummaryCards() {
        var cards = state.dashboard && Array.isArray(state.dashboard.summary_cards) ? state.dashboard.summary_cards : [];
        if (!cards.length) {
            return '';
        }

        return [
            '<section class="cbt-supervisor-summary-grid">',
            cards.map(function (card) {
                var key = String(card.key || '');
                var iconName = key === 'action_required'
                    ? 'alert'
                    : key === 'security_backlog'
                        ? 'activity'
                        : key === 'security_dead_letter'
                            ? 'bell'
                            : key === 'system_mode'
                                ? 'monitor'
                                : key === 'live_roster'
                                    ? 'users'
                                    : key === 'must_watch'
                                        ? 'alert'
                                        : key === 'submit_watchlist'
                                            ? 'radio'
                                            : 'clipboard';
                return [
                    '<article class="cbt-supervisor-summary-card">',
                    '<span class="cbt-supervisor-summary-icon">' + renderSupervisorIcon(iconName) + '</span>',
                    '<div>',
                    '<span>' + escapeHtml(String(card.label || '-')) + '</span>',
                    '<strong>' + escapeHtml(String(card.value || '0')) + '</strong>',
                    '<small>' + escapeHtml(String(card.meta || '')) + '</small>',
                    '</div>',
                    '</article>'
                ].join('');
            }).join(''),
            '</section>'
        ].join('');
    }

    function renderSelectOptions(items, selectedValue, valueKey, labelKey) {
        return items.map(function (item) {
            var value = typeof item === 'object' && item !== null ? String(item[valueKey] || '') : String(item || '');
            var label = typeof item === 'object' && item !== null ? String(item[labelKey] || value || '-') : value;
            return '<option value="' + escapeHtml(value) + '"' + (String(value) === String(selectedValue || '') ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
        }).join('');
    }

    function getOptionLabel(items, selectedValue, valueKey, labelKey) {
        var selected = String(selectedValue || '');
        var matched = (Array.isArray(items) ? items : []).find(function (item) {
            var value = typeof item === 'object' && item !== null ? String(item[valueKey] || '') : String(item || '');
            return value === selected;
        });

        if (!matched) {
            return selected;
        }

        return typeof matched === 'object' && matched !== null
            ? String(matched[labelKey] || matched[valueKey] || selected)
            : String(matched || selected);
    }

    function getStatusFilterLabel(value) {
        var labels = {
            in_progress: 'Berjalan',
            completed: 'Selesai',
            not_started: 'Belum mulai',
            info: 'Info',
            warning: 'Warning',
            critical: 'Critical'
        };

        return labels[String(value || '')] || String(value || '');
    }

    function renderExamFilterField(exams, emptyLabel) {
        return [
            '<label class="cbt-supervisor-field">',
            '<span>Exam</span>',
            '<select name="exam_id">',
            '<option value="0">' + escapeHtml(emptyLabel || 'Semua exam') + '</option>',
            exams.map(function (exam) {
                var examId = Number(exam.id) || 0;
                return '<option value="' + escapeHtml(String(examId)) + '"' + (examId === Number(state.filters.examId) ? ' selected' : '') + '>' + escapeHtml(String(exam.label || '-')) + '</option>';
            }).join(''),
            '</select>',
            '</label>'
        ].join('');
    }

    function getActiveFilterChips(mode, options, eventCatalog) {
        var chips = [];
        var exams = Array.isArray(options.exams) ? options.exams : [];
        var kelasOptions = Array.isArray(options.kelas) ? options.kelas : [];
        var ruangOptions = Array.isArray(options.ruang) ? options.ruang : [];
        var examId = Number(state.filters.examId) || 0;

        if (shouldSendExamFilter(mode) && examId > 0) {
            chips.push({
                label: 'Exam',
                value: getOptionLabel(exams, String(examId), 'id', 'label')
            });
        }
        if (shouldSendOperationalFilters(mode)) {
            if (String(state.filters.kelas || '') !== '') {
                chips.push({
                    label: 'Kelas',
                    value: getOptionLabel(kelasOptions, state.filters.kelas, 'value', 'label')
                });
            }
            if (String(state.filters.ruang || '') !== '') {
                chips.push({
                    label: 'Ruang',
                    value: getOptionLabel(ruangOptions, state.filters.ruang, 'value', 'label')
                });
            }
            if (String(state.filters.studentKeyword || '') !== '') {
                chips.push({
                    label: 'Cari',
                    value: String(state.filters.studentKeyword || '')
                });
            }
        }
        if (String(mode || '') === 'operational' && String(state.filters.status || '') !== '') {
            chips.push({
                label: 'Status',
                value: getStatusFilterLabel(state.filters.status)
            });
        }
        if (String(mode || '') === 'security') {
            if (String(state.filters.securitySeverity || 'all') !== 'all') {
                chips.push({
                    label: 'Severity',
                    value: getStatusFilterLabel(state.filters.securitySeverity)
                });
            }
            if (String(state.filters.securityEventType || 'all') !== 'all') {
                chips.push({
                    label: 'Event',
                    value: getOptionLabel(eventCatalog, state.filters.securityEventType, 'event_type', 'label')
                });
            }
            if (String(state.filters.securityDeviceType || 'all') !== 'all') {
                chips.push({
                    label: 'Device',
                    value: String(state.filters.securityDeviceType || '')
                });
            }
        }
        if (String(mode || '') === 'attendance' && String(state.filters.attendanceStatus || '') !== '') {
            chips.push({
                label: 'Hadir',
                value: getStatusFilterLabel(state.filters.attendanceStatus)
            });
        }

        return chips;
    }

    function renderActiveFilterChips(chips) {
        if (!Array.isArray(chips) || !chips.length) {
            return '';
        }

        return [
            '<div class="cbt-supervisor-active-filters">',
            '<span>Filter aktif</span>',
            chips.map(function (chip) {
                return '<span class="cbt-supervisor-filter-chip"><strong>' + escapeHtml(String(chip.label || '-')) + '</strong>' + escapeHtml(String(chip.value || '-')) + '</span>';
            }).join(''),
            '</div>'
        ].join('');
    }

    function renderFilterForm() {
        var options = state.dashboard && state.dashboard.filter_options ? state.dashboard.filter_options : {};
        var exams = Array.isArray(options.exams) ? options.exams : [];
        var kelasOptions = Array.isArray(options.kelas) ? options.kelas : [];
        var ruangOptions = Array.isArray(options.ruang) ? options.ruang : [];
        var securityLog = state.dashboard && state.dashboard.security_log ? state.dashboard.security_log : {};
        var eventCatalog = Array.isArray(securityLog.event_catalog) ? securityLog.event_catalog : [];
        var mode = getActiveFilterMode(state.activeTab);
        var fields = [];
        var chips;

        if (mode === 'token') {
            fields.push(renderExamFilterField(exams, 'Pilih exam'));
        } else if (mode === 'security') {
            fields.push([
                '<label class="cbt-supervisor-field">',
                '<span>Severity</span>',
                '<select name="security_severity">',
                '<option value="all"' + (String(state.filters.securitySeverity || 'all') === 'all' ? ' selected' : '') + '>Semua severity</option>',
                '<option value="info"' + (String(state.filters.securitySeverity || 'all') === 'info' ? ' selected' : '') + '>Info</option>',
                '<option value="warning"' + (String(state.filters.securitySeverity || 'all') === 'warning' ? ' selected' : '') + '>Warning</option>',
                '<option value="critical"' + (String(state.filters.securitySeverity || 'all') === 'critical' ? ' selected' : '') + '>Critical</option>',
                '</select>',
                '</label>',
                '<label class="cbt-supervisor-field">',
                '<span>Event</span>',
                '<select name="security_event_type">',
                '<option value="all">Semua event</option>',
                renderSelectOptions(eventCatalog, state.filters.securityEventType, 'event_type', 'label'),
                '</select>',
                '</label>',
                '<label class="cbt-supervisor-field">',
                '<span>Device</span>',
                '<select name="security_device_type">',
                '<option value="all"' + (String(state.filters.securityDeviceType || 'all') === 'all' ? ' selected' : '') + '>Semua device</option>',
                '<option value="desktop"' + (String(state.filters.securityDeviceType || 'all') === 'desktop' ? ' selected' : '') + '>Desktop</option>',
                '<option value="mobile"' + (String(state.filters.securityDeviceType || 'all') === 'mobile' ? ' selected' : '') + '>Mobile</option>',
                '<option value="tablet"' + (String(state.filters.securityDeviceType || 'all') === 'tablet' ? ' selected' : '') + '>Tablet</option>',
                '<option value="server"' + (String(state.filters.securityDeviceType || 'all') === 'server' ? ' selected' : '') + '>Server</option>',
                '<option value="unknown"' + (String(state.filters.securityDeviceType || 'all') === 'unknown' ? ' selected' : '') + '>Unknown</option>',
                '</select>',
                '</label>'
            ].join(''));
        } else {
            fields.push(renderExamFilterField(exams, mode === 'attendance' ? 'Pilih exam' : 'Semua exam'));

            if (shouldSendOperationalFilters(mode)) {
                fields.push([
                    '<label class="cbt-supervisor-field">',
                    '<span>Kelas</span>',
                    '<select name="kelas">',
                    '<option value="">Semua kelas</option>',
                    renderSelectOptions(kelasOptions, state.filters.kelas, 'value', 'label'),
                    '</select>',
                    '</label>',
                    '<label class="cbt-supervisor-field">',
                    '<span>Ruang</span>',
                    '<select name="ruang">',
                    '<option value="">Semua ruang</option>',
                    renderSelectOptions(ruangOptions, state.filters.ruang, 'value', 'label'),
                    '</select>',
                    '</label>',
                    '<label class="cbt-supervisor-field cbt-supervisor-field-search">',
                    '<span>Cari siswa</span>',
                    '<input type="text" name="student_keyword" value="' + escapeHtml(String(state.filters.studentKeyword || '')) + '" placeholder="Nama, username, NISN, exam" />',
                    '</label>'
                ].join(''));
            }
            if (mode === 'attendance') {
                fields.push([
                    '<label class="cbt-supervisor-field">',
                    '<span>Status hadir</span>',
                    '<select name="attendance_status">',
                    '<option value="">Semua status</option>',
                    '<option value="not_started"' + (String(state.filters.attendanceStatus || '') === 'not_started' ? ' selected' : '') + '>Belum Mulai</option>',
                    '<option value="in_progress"' + (String(state.filters.attendanceStatus || '') === 'in_progress' ? ' selected' : '') + '>Berjalan</option>',
                    '<option value="completed"' + (String(state.filters.attendanceStatus || '') === 'completed' ? ' selected' : '') + '>Selesai</option>',
                    '</select>',
                    '</label>'
                ].join(''));
            } else if (mode === 'operational') {
                fields.push([
                    '<label class="cbt-supervisor-field">',
                    '<span>Status</span>',
                    '<select name="status">',
                    '<option value="">Semua status</option>',
                    '<option value="in_progress"' + (String(state.filters.status || '') === 'in_progress' ? ' selected' : '') + '>Berjalan</option>',
                    '<option value="completed"' + (String(state.filters.status || '') === 'completed' ? ' selected' : '') + '>Selesai</option>',
                    '</select>',
                    '</label>'
                ].join(''));
            }
        }

        chips = getActiveFilterChips(mode, options, eventCatalog);

        return [
            '<form class="cbt-supervisor-filter-bar is-' + escapeHtml(mode) + '" data-supervisor-filter-form>',
            fields.join(''),
            '<div class="cbt-supervisor-filter-actions">',
            '<button class="cbt-supervisor-button is-primary" type="submit"' + (state.dashboardBusy ? ' disabled' : '') + '>' + renderSupervisorIcon('filter') + '<span>Terapkan</span></button>',
            (chips.length ? '<button class="cbt-supervisor-button" type="button" data-action="clear-filters"' + (state.dashboardBusy ? ' disabled' : '') + '>Reset</button>' : ''),
            '</div>',
            renderActiveFilterChips(chips),
            '</form>'
        ].join('');
    }

    function renderTabs() {
        var tabs = [
            { id: 'overview', label: 'RINGKASAN' },
            { id: 'token_gate', label: 'TOKEN & GATE' },
            { id: 'attendance', label: 'DAFTAR HADIR' },
            { id: 'action_required', label: 'BUTUH TINDAKAN' },
            { id: 'live_roster', label: 'LIVE ROSTER' },
            { id: 'must_watch', label: 'MUST WATCH' },
            { id: 'security_log', label: 'SECURITY LOG' },
            { id: 'monitoring_attempts', label: 'ATTEMPTS' },
            { id: 'submit_recovery', label: 'SUBMIT RECOVERY' }
        ];

        return [
            '<nav class="cbt-supervisor-tab-bar" role="tablist" aria-label="Supervisor Tabs">',
            tabs.map(function (tab) {
                var isActive = state.activeTab === tab.id;
                return '<button class="cbt-supervisor-tab' + (isActive ? ' is-active' : '') + '" type="button" role="tab" aria-selected="' + (isActive ? 'true' : 'false') + '" data-action="switch-tab" data-tab="' + escapeHtml(tab.id) + '">' + escapeHtml(tab.label) + '</button>';
            }).join(''),
            '</nav>'
        ].join('');
    }

    function renderActionRequiredPanel() {
        var section = state.dashboard && state.dashboard.action_required ? state.dashboard.action_required : null;
        if (!section) {
            return '<div class="cbt-supervisor-empty-state">Butuh Tindakan belum dimuat.</div>';
        }

        var items = Array.isArray(section.items) ? section.items : [];
        var counts = section.severity_counts && typeof section.severity_counts === 'object' ? section.severity_counts : {};
        var countMarkup = [
            '<div class="cbt-supervisor-action-counts">',
            '<span class="cbt-supervisor-pill is-critical">Critical ' + escapeHtml(String(Math.max(0, Number(counts.critical) || 0))) + '</span>',
            '<span class="cbt-supervisor-pill is-warning">Warning ' + escapeHtml(String(Math.max(0, Number(counts.warning) || 0))) + '</span>',
            '<span class="cbt-supervisor-pill is-info">Info ' + escapeHtml(String(Math.max(0, Number(counts.info) || 0))) + '</span>',
            '</div>'
        ].join('');

        if (!items.length) {
            return countMarkup + '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(section.note || 'Tidak ada tindakan prioritas pada filter aktif.')) + '</div>';
        }

        return [
            countMarkup,
            '<div class="cbt-supervisor-watch-list is-action-required">',
            items.map(function (item) {
                var severity = String(item.severity || 'info');
                return [
                    '<article class="cbt-supervisor-watch-row is-' + escapeHtml(severity) + '">',
                    '<div class="cbt-supervisor-watch-main">',
                    renderStudentCell(item.student_name, 'SW', [item.student_login, item.student_nisn, item.student_kelas, item.student_ruang]),
                    '<div class="cbt-supervisor-watch-detail">',
                    '<strong>' + escapeHtml(String(item.reason || 'Perlu dicek')) + '</strong>',
                    renderRowMeta([item.detail, item.exam_title, 'Attempt #' + String(item.attempt_id || 0), item.last_seen_at ? 'Last seen ' + String(item.last_seen_at) : '']),
                    '</div>',
                    '</div>',
                    '<div class="cbt-supervisor-watch-side">',
                    '<span class="cbt-supervisor-pill is-' + escapeHtml(severity) + '">' + escapeHtml(String(item.severity_label || severity.toUpperCase())) + '</span>',
                    '<span class="cbt-supervisor-pill is-' + escapeHtml(String(item.presence_status || 'unknown')) + '">' + escapeHtml(String(item.presence_label || '-')) + '</span>',
                    renderAttemptActions(item, { reset: true }),
                    '</div>',
                    '</article>'
                ].join('');
            }).join(''),
            '</div>',
            renderPagination(section.pagination, 'action')
        ].join('');
    }

    function renderLiveRosterPanel() {
        var section = state.dashboard && state.dashboard.live_roster ? state.dashboard.live_roster : null;
        if (!section) {
            return '<div class="cbt-supervisor-empty-state">Dashboard live roster belum dimuat.</div>';
        }
        if (!section.available) {
            return '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(section.note || 'Roster live belum tersedia.')) + '</div>';
        }

        var items = Array.isArray(section.items) ? section.items : [];
        if (!items.length) {
            return '<div class="cbt-supervisor-empty-state">Belum ada attempt aktif yang cocok dengan filter saat ini.</div>';
        }

        return [
            '<div class="cbt-supervisor-table-wrap">',
            '<table class="cbt-supervisor-table is-live-roster">',
            '<thead><tr><th>Siswa</th><th>Exam</th><th>Presence</th><th>Risk</th><th>Last Seen</th><th>Aksi</th></tr></thead>',
            '<tbody>',
            items.map(function (item) {
                return [
                    '<tr>',
                    '<td data-label="Siswa">' + renderStudentCell(item.student_name, 'SW', [item.student_login, item.student_kelas, item.student_ruang]) + '</td>',
                    '<td data-label="Exam"><strong>' + escapeHtml(String(item.exam_title || '-')) + '</strong>' + renderRowMeta(['Attempt #' + String(item.attempt_id || 0)]) + '</td>',
                    '<td data-label="Presence"><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.presence_status || 'unknown')) + '">' + escapeHtml(String(item.presence_label || '-')) + '</span>' + renderRowMeta([item.connection_status, item.visibility_state, item.heartbeat_lost_active ? 'heartbeat lost' : '']) + '</td>',
                    '<td data-label="Risk"><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.risk_tone || 'normal')) + '">' + escapeHtml(String(item.risk_label || 'Normal')) + '</span>' + renderRowMeta(['Skor ' + String(item.risk_score_label || '0')]) + '</td>',
                    '<td data-label="Last Seen"><strong>' + escapeHtml(String(item.last_seen_at || '-')) + '</strong>' + renderRowMeta(['Sync ' + String(Number(item.pending_sync_count) || 0)]) + '</td>',
                    '<td data-label="Aksi">' + renderAttemptActions(item, { reset: true }) + '</td>',
                    '</tr>'
                ].join('');
            }).join(''),
            '</tbody>',
            '</table>',
            '</div>',
            renderPagination(section.pagination, 'roster')
        ].join('');
    }

    function renderMustWatchPanel() {
        var section = state.dashboard && state.dashboard.must_watch ? state.dashboard.must_watch : null;
        if (!section) {
            return '<div class="cbt-supervisor-empty-state">Data must watch belum dimuat.</div>';
        }

        var items = Array.isArray(section.items) ? section.items : [];
        if (!items.length) {
            return '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(section.note || 'Belum ada must watch pada scope aktif.')) + '</div>';
        }

        return [
            '<div class="cbt-supervisor-watch-list">',
            items.map(function (item) {
                var indicators = Array.isArray(item.top_indicators) ? item.top_indicators : [];
                return [
                    '<article class="cbt-supervisor-watch-row">',
                    '<div class="cbt-supervisor-watch-main">',
                    renderStudentCell(item.student_name, 'SW', [item.student_login, item.student_kelas, item.student_ruang]),
                    '<div class="cbt-supervisor-watch-detail">',
                    '<strong>' + escapeHtml(String(item.primary_event_label || 'Aktivitas diamati.')) + '</strong>',
                    renderRowMeta([item.exam_title, 'Skor ' + String(item.risk_score_label || '0'), item.presence_label, item.last_event_at]),
                    indicators.length ? '<div class="cbt-supervisor-chip-row">' + indicators.map(function (label) {
                        return '<span class="cbt-supervisor-chip">' + escapeHtml(String(label || '')) + '</span>';
                    }).join('') + '</div>' : '',
                    '</div>',
                    '</div>',
                    '<div class="cbt-supervisor-watch-side">',
                    '<span class="cbt-supervisor-pill is-watch">' + escapeHtml(String(item.risk_label || 'Must Watch')) + '</span>',
                    renderAttemptActions(item, { reset: true }),
                    '</div>',
                    '</article>'
                ].join('');
            }).join(''),
            '</div>'
        ].join('');
    }

    function renderMonitoringAttemptsPanel() {
        var section = state.dashboard && state.dashboard.monitoring_attempts ? state.dashboard.monitoring_attempts : null;

        if (!section) {
            return '<div class="cbt-supervisor-empty-state">Data monitoring attempts belum dimuat.</div>';
        }

        var items = Array.isArray(section.items) ? section.items : [];
        return items.length
            ? [
                '<div class="cbt-supervisor-table-wrap">',
                '<table class="cbt-supervisor-table is-attempts">',
                '<thead><tr><th>Siswa</th><th>Exam</th><th>Status</th><th>Skor</th><th>Jawaban</th><th>Timeline</th><th>Aksi</th></tr></thead>',
                '<tbody>',
                items.map(function (item) {
                    return [
                        '<tr>',
                        '<td data-label="Siswa">' + renderStudentCell(item.student_name, 'SW', [item.student_username, item.student_nisn, item.student_kelas]) + '</td>',
                        '<td data-label="Exam"><strong>' + escapeHtml(String(item.exam_title || '-')) + '</strong>' + renderRowMeta(['Attempt #' + String(item.attempt_id || 0)]) + '</td>',
                        '<td data-label="Status"><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.status || 'completed')) + '">' + escapeHtml(String(item.status_label || '-')) + '</span>' + renderRowMeta([item.presence_label]) + '</td>',
                        '<td data-label="Skor"><strong>' + escapeHtml(String(item.score_percentage_label || '0%')) + '</strong>' + renderRowMeta(['Benar ' + String(item.earned_points || 0), 'Salah ' + String(item.wrong_points || 0)]) + '</td>',
                        '<td data-label="Jawaban" class="cbt-supervisor-answer-cell">' + renderAnswerProgressCell(item) + '</td>',
                        '<td data-label="Timeline"><strong>' + escapeHtml(String(item.started_at || '-')) + '</strong>' + renderRowMeta([item.finalize_pending ? 'Finalisasi background' : String(item.remaining_label || '-')]) + '</td>',
                        '<td data-label="Aksi">' + renderAttemptActions(item, { reset: true }) + '</td>',
                        '</tr>'
                    ].join('');
                }).join(''),
                '</tbody>',
                '</table>',
                '</div>',
                renderPagination(section.pagination, 'attempts')
            ].join('')
            : '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(section.note || 'Belum ada attempt yang cocok dengan filter aktif.')) + '</div>';
    }

    function renderSubmitRecoveryPanel() {
        var submitRecovery = state.dashboard && state.dashboard.submit_recovery ? state.dashboard.submit_recovery : {};
        var submitHealth = submitRecovery && submitRecovery.submit_health ? submitRecovery.submit_health : {};
        var submitWatchlist = submitRecovery && submitRecovery.submit_watchlist ? submitRecovery.submit_watchlist : {};
        var watchlistItems = Array.isArray(submitWatchlist.items) ? submitWatchlist.items : [];
        var watchlistMarkup = watchlistItems.length
            ? '<div class="cbt-supervisor-watch-list is-submit-watchlist">' + watchlistItems.map(function (item) {
                return [
                    '<article class="cbt-supervisor-watch-row">',
                    '<div class="cbt-supervisor-watch-main">',
                    renderStudentCell(item.student_name, 'SW', [item.student_username, item.student_nisn, item.student_kelas]),
                    '<div class="cbt-supervisor-watch-detail">',
                    '<strong>' + escapeHtml(String(item.detail || 'Status submit masih dipantau.')) + '</strong>',
                    renderRowMeta([item.exam_title]),
                    '</div>',
                    '</div>',
                    '<div class="cbt-supervisor-watch-side">',
                    '<span class="cbt-supervisor-pill is-watchlist">' + escapeHtml(String(item.state_label || 'Unknown')) + '</span>',
                    renderAttemptActions(item, { reset: false }),
                    '</div>',
                    '</article>'
                ].join('');
            }).join('') + '</div>'
            : '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(submitWatchlist.note || 'Belum ada unresolved submit yang perlu diawasi.')) + '</div>';

        return [
            '<section class="cbt-supervisor-submit-health">',
            '<div class="cbt-supervisor-section-head"><div><span class="cbt-supervisor-kicker">Submit Telemetry</span><h3>Submit Health</h3></div><p>' + escapeHtml(String(submitHealth.note || 'Telemetry submit belum tersedia.')) + '</p></div>',
            '<div class="cbt-supervisor-summary-grid">',
            '<article class="cbt-supervisor-summary-card is-compact-metric"><span>Finish Ack</span><strong>' + escapeHtml(String(submitHealth.finish_ack_total || 0)) + '</strong><small>15 menit terakhir</small></article>',
            '<article class="cbt-supervisor-summary-card is-compact-metric"><span>Result Ready</span><strong>' + escapeHtml(String(submitHealth.result_ready_total || 0)) + '</strong><small>Recovery hasil sukses</small></article>',
            '<article class="cbt-supervisor-summary-card is-compact-metric"><span>Recovery Failed</span><strong>' + escapeHtml(String(submitHealth.recovery_failed_total || 0)) + '</strong><small>Butuh perhatian operator</small></article>',
            '<article class="cbt-supervisor-summary-card is-compact-metric"><span>Ack → Result p95</span><strong>' + escapeHtml(String(submitHealth.ack_to_result_ready_p95_label || 'N/A')) + '</strong><small>Latency recovery hasil</small></article>',
            '</div>',
            '<div class="cbt-supervisor-section-head"><div><span class="cbt-supervisor-kicker">Watchlist</span><h3>Submit Watchlist</h3></div><p>' + escapeHtml(String(submitWatchlist.note || 'Pantau unresolved submit yang masih menunggu recovery.')) + '</p></div>',
            watchlistMarkup,
            '</section>'
        ].join('');
    }

    function renderSecurityLogPanel() {
        var section = state.dashboard && state.dashboard.security_log ? state.dashboard.security_log : null;
        if (!section) {
            return '<div class="cbt-supervisor-empty-state">Security log belum dimuat.</div>';
        }

        var items = Array.isArray(section.items) ? section.items : [];
        if (!items.length) {
            return '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(section.note || 'Belum ada security log sesuai filter aktif.')) + '</div>';
        }

        return [
            '<div class="cbt-supervisor-table-wrap">',
            '<table class="cbt-supervisor-table is-security">',
            '<thead><tr><th>Waktu</th><th>Siswa</th><th>Exam</th><th>Event</th><th>Device</th><th>Pesan</th><th>Aksi</th></tr></thead>',
            '<tbody>',
            items.map(function (item) {
                return [
                    '<tr>',
                    '<td data-label="Waktu"><strong>' + escapeHtml(String(item.occurred_at || item.created_at || '-')) + '</strong>' + renderRowMeta(['Log #' + String(item.id || 0)]) + '</td>',
                    '<td data-label="Siswa">' + renderStudentCell(item.student_name, 'SW', [item.student_login, item.student_kelas, item.student_ruang]) + '</td>',
                    '<td data-label="Exam"><strong>' + escapeHtml(String(item.exam_title || '-')) + '</strong>' + renderRowMeta(['Attempt #' + String(item.attempt_id || 0)]) + '</td>',
                    '<td data-label="Event"><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.severity || 'info')) + '">' + escapeHtml(String(item.severity || 'info')) + '</span>' + renderRowMeta([item.event_label || item.event_type || '-']) + '</td>',
                    '<td data-label="Device"><strong>' + escapeHtml(String(item.device_summary || '-')) + '</strong>' + renderRowMeta([item.device_type || 'unknown']) + '</td>',
                    '<td data-label="Pesan"><span class="cbt-supervisor-log-message">' + escapeHtml(String(item.message_display || '-')) + '</span></td>',
                    '<td data-label="Aksi">' + renderAttemptActions(item, { reset: false }) + '</td>',
                    '</tr>'
                ].join('');
            }).join(''),
            '</tbody>',
            '</table>',
            '</div>',
            renderPagination(section.pagination, 'security')
        ].join('');
    }

    function renderMetricGrid(items) {
        return '<div class="cbt-supervisor-status-grid">' + items.map(function (item) {
            return '<div><span>' + escapeHtml(String(item.label || '-')) + '</span><strong>' + escapeHtml(String(item.value || '-')) + '</strong><small>' + escapeHtml(String(item.meta || '')) + '</small></div>';
        }).join('') + '</div>';
    }

    function renderTokenGatePanel() {
        var section = state.dashboard && state.dashboard.token_gate ? state.dashboard.token_gate : null;
        if (!section) {
            return '<div class="cbt-supervisor-empty-state">Token & gate belum dimuat.</div>';
        }

        var token = section.token || {};
        var gate = section.gate || {};
        var autoWarm = section.auto_warm || {};
        var exam = section.selected_exam || null;

        return [
            '<div class="cbt-supervisor-token-grid">',
            '<article class="cbt-supervisor-info-card">',
            '<span class="cbt-supervisor-summary-icon">' + renderSupervisorIcon('key') + '</span>',
            '<div><span>Token Global</span><strong>' + escapeHtml(String(token.display || '------')) + '</strong><small>' + escapeHtml(String(token.frontend_auto_apply_label || 'Manual')) + ' · refresh ' + escapeHtml(String(token.remaining_label || '-')) + '</small></div>',
            '</article>',
            '<article class="cbt-supervisor-info-card">',
            '<span class="cbt-supervisor-summary-icon">' + renderSupervisorIcon('calendar') + '</span>',
            '<div><span>Next Refresh</span><strong>' + escapeHtml(String(token.next_refresh_label || '-')) + '</strong><small>Interval ' + escapeHtml(String(token.refresh_minutes || 0)) + ' menit</small></div>',
            '</article>',
            '</div>',
            exam ? '<div class="cbt-supervisor-empty-state">Exam aktif: <strong>' + escapeHtml(String(exam.title || '-')) + '</strong> · status ' + escapeHtml(String(exam.status || '-')) + '</div>' : '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(section.note || 'Pilih exam untuk membaca gate.')) + '</div>',
            renderMetricGrid([
                { label: 'Gate Status', value: gate.status_label || 'DISABLED', meta: gate.redis_available ? 'Redis gate tersedia' : (gate.redis_error || 'Redis gate belum tersedia') },
                { label: 'Queue Depth', value: gate.queue_depth || 0, meta: 'Antrian start_attempt' },
                { label: 'Bucket Tokens', value: gate.bucket_tokens || 0, meta: 'Kapasitas ' + String(gate.gate_capacity || 0) },
                { label: 'Release Rate', value: gate.release_rate_label || '-', meta: 'Oldest wait ' + String(gate.oldest_wait_seconds || 0) + ' detik' }
            ]),
            renderMetricGrid([
                { label: 'Auto Warm', value: autoWarm.status_label || 'INACTIVE', meta: autoWarm.last_message || '-' },
                { label: 'Target Siswa', value: autoWarm.target_student_count || 0, meta: Array.isArray(autoWarm.target_kelas) ? autoWarm.target_kelas.join(', ') : '-' },
                { label: 'Prepared', value: autoWarm.prepared_count || 0, meta: autoWarm.redis_available ? 'Availability Redis siap' : 'Availability Redis belum siap' },
                { label: 'Kontrol', value: 'Read-only', meta: 'Start/stop tetap di wp-admin' }
            ])
        ].join('');
    }

    function renderAttendancePanel() {
        var section = state.dashboard && state.dashboard.attendance ? state.dashboard.attendance : null;
        if (!section) {
            return '<div class="cbt-supervisor-empty-state">Daftar hadir belum dimuat.</div>';
        }
        if (!section.available) {
            return '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(section.note || 'Pilih exam untuk membuka daftar hadir.')) + '</div>';
        }

        var summary = section.summary || {};
        var items = Array.isArray(section.items) ? section.items : [];
        var summaryMarkup = renderMetricGrid([
            { label: 'Belum Mulai', value: summary.not_started || 0, meta: 'Peserta belum start' },
            { label: 'Berjalan', value: summary.in_progress || 0, meta: 'Sedang ujian' },
            { label: 'Selesai', value: summary.completed || 0, meta: 'Sudah submit' },
            { label: 'Total Filter', value: section.total || 0, meta: 'Sesuai filter aktif' }
        ]);
        if (!items.length) {
            return summaryMarkup + '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(section.note || 'Belum ada peserta sesuai filter aktif.')) + '</div>';
        }

        return [
            summaryMarkup,
            '<div class="cbt-supervisor-table-wrap">',
            '<table class="cbt-supervisor-table is-attendance">',
            '<thead><tr><th>Siswa</th><th>Lokasi</th><th>Status</th><th>Attempt</th><th>Timeline</th><th>Aksi</th></tr></thead>',
            '<tbody>',
            items.map(function (item) {
                return [
                    '<tr>',
                    '<td data-label="Siswa">' + renderStudentCell(item.student_name, 'SW', [item.student_username, item.student_nisn]) + '</td>',
                    '<td data-label="Lokasi"><strong>' + escapeHtml(String(item.student_kelas || '-')) + '</strong>' + renderRowMeta(['Ruang ' + String(item.student_ruang || '-')]) + '</td>',
                    '<td data-label="Status"><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.status || 'not_started')) + '">' + escapeHtml(String(item.status_label || '-')) + '</span></td>',
                    '<td data-label="Attempt"><strong>' + escapeHtml(item.attempt_id ? ('#' + String(item.attempt_id)) : '-') + '</strong></td>',
                    '<td data-label="Timeline"><strong>' + escapeHtml(String(item.started_at || '-')) + '</strong>' + renderRowMeta([String(item.finished_at || 'Belum selesai')]) + '</td>',
                    '<td data-label="Aksi">' + renderAttemptActions(item, { reset: false }) + '</td>',
                    '</tr>'
                ].join('');
            }).join(''),
            '</tbody>',
            '</table>',
            '</div>',
            renderPagination(section.pagination, 'attendance')
        ].join('');
    }

    function renderPagination(pagination, scope) {
        var pager = pagination && typeof pagination === 'object' ? pagination : {};
        var currentPage = Math.max(1, Number(pager.current_page) || 1);
        var totalPages = Math.max(1, Number(pager.total_pages) || 1);
        var disablePrev = currentPage <= 1;
        var disableNext = currentPage >= totalPages;

        return [
            '<div class="cbt-supervisor-pagination">',
            '<button class="cbt-supervisor-button is-small" type="button" data-action="page-prev" data-scope="' + escapeHtml(scope) + '"' + (disablePrev ? ' disabled' : '') + '>Prev</button>',
            '<span>Halaman ' + escapeHtml(String(currentPage)) + ' / ' + escapeHtml(String(totalPages)) + '</span>',
            '<button class="cbt-supervisor-button is-small" type="button" data-action="page-next" data-scope="' + escapeHtml(scope) + '"' + (disableNext ? ' disabled' : '') + '>Next</button>',
            '</div>'
        ].join('');
    }

    function renderActivePanel() {
        if (state.activeTab === 'action_required') {
            return renderActionRequiredPanel();
        }
        if (state.activeTab === 'must_watch') {
            return renderMustWatchPanel();
        }
        if (state.activeTab === 'monitoring_attempts') {
            return renderMonitoringAttemptsPanel();
        }
        if (state.activeTab === 'security_log') {
            return renderSecurityLogPanel();
        }
        if (state.activeTab === 'token_gate') {
            return renderTokenGatePanel();
        }
        if (state.activeTab === 'submit_recovery') {
            return renderSubmitRecoveryPanel();
        }
        if (state.activeTab === 'attendance') {
            return renderAttendancePanel();
        }

        return renderLiveRosterPanel();
    }

    function getActiveOperationalTitle() {
        if (state.activeTab === 'action_required') {
            return {
                title: 'Butuh Tindakan',
                description: 'Kasus prioritas dari koneksi, risiko, submit, dan finalisasi.'
            };
        }
        if (state.activeTab === 'must_watch') {
            return {
                title: 'Must Watch',
                description: 'Prioritas siswa berisiko dalam list compact.'
            };
        }
        if (state.activeTab === 'monitoring_attempts') {
            return {
                title: 'Attempts',
                description: 'Status, skor, progres jawaban, dan timeline attempt.'
            };
        }
        if (state.activeTab === 'security_log') {
            return {
                title: 'Security Log',
                description: 'Filter log berdasarkan severity, event, dan device.'
            };
        }
        if (state.activeTab === 'token_gate') {
            return {
                title: 'Token & Gate',
                description: 'Token global, start gate, dan readiness auto-warm exam.'
            };
        }
        if (state.activeTab === 'submit_recovery') {
            return {
                title: 'Submit Recovery',
                description: 'Telemetry submit dan watchlist recovery yang perlu diawasi.'
            };
        }
        if (state.activeTab === 'attendance') {
            return {
                title: 'Daftar Hadir',
                description: 'Pilih exam, lalu scan status hadir peserta.'
            };
        }
        return {
            title: 'Live Roster',
            description: 'Presence, koneksi, risk score, dan last seen attempt aktif.'
        };
    }

    function renderOperationalPanel() {
        var activeMeta = getActiveOperationalTitle();
        var panelContent = isActiveTabLoading() && !isDashboardForActiveTab()
            ? renderPanelSkeleton()
            : renderActivePanel();

        return [
            '<section class="cbt-supervisor-panel">',
            '<div class="cbt-supervisor-panel-head">',
            '<div><span class="cbt-supervisor-kicker">Operasional</span><h2>' + escapeHtml(activeMeta.title) + '</h2><p>' + escapeHtml(activeMeta.description) + '</p></div>',
            '<div class="cbt-supervisor-panel-actions">',
            '<button class="cbt-supervisor-button" type="button" data-action="refresh-dashboard"' + (state.dashboardBusy ? ' disabled' : '') + '>' + renderSupervisorIcon('refresh') + '<span>' + escapeHtml(state.dashboardBusy ? 'Memuat...' : 'Refresh') + '</span></button>',
            '</div>',
            '</div>',
            renderFilterForm(),
            '<div class="cbt-supervisor-tab-panel">' + panelContent + '</div>',
            '</section>'
        ].join('');
    }

    function renderDashboardBody() {
        if (state.activeTab === 'overview') {
            return [
                renderSummaryCards(),
                renderStatusSnapshot()
            ].join('');
        }

        return renderOperationalPanel();
    }

    function renderDetailMetric(label, value, meta) {
        return [
            '<div class="cbt-supervisor-detail-metric">',
            '<span>' + escapeHtml(String(label || '-')) + '</span>',
            '<strong>' + escapeHtml(String(value || '-')) + '</strong>',
            meta ? '<small>' + escapeHtml(String(meta || '')) + '</small>' : '',
            '</div>'
        ].join('');
    }

    function renderAttemptDetailDrawer() {
        var drawer = state.detailDrawer || {};
        if (!drawer.open) {
            return '';
        }

        var data = drawer.data && typeof drawer.data === 'object' ? drawer.data : {};
        var student = data.student && typeof data.student === 'object' ? data.student : {};
        var attempt = data.attempt && typeof data.attempt === 'object' ? data.attempt : {};
        var exam = data.exam && typeof data.exam === 'object' ? data.exam : {};
        var presence = data.presence && typeof data.presence === 'object' ? data.presence : {};
        var progress = data.answer_progress && typeof data.answer_progress === 'object' ? data.answer_progress : {};
        var submitStatus = data.submit_status && typeof data.submit_status === 'object' ? data.submit_status : {};
        var timeline = data.timeline && typeof data.timeline === 'object' ? data.timeline : {};
        var securityTimeline = data.security_timeline && typeof data.security_timeline === 'object' ? data.security_timeline : {};
        var events = Array.isArray(data.security_events) ? data.security_events : [];
        var body;

        if (drawer.busy && !data.ok) {
            body = renderPanelSkeleton();
        } else if (drawer.error) {
            body = '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(drawer.error || 'Detail attempt gagal dimuat.')) + '</div>';
        } else if (!data.ok) {
            body = '<div class="cbt-supervisor-empty-state">Detail attempt belum tersedia.</div>';
        } else {
            body = [
                '<div class="cbt-supervisor-detail-grid">',
                renderDetailMetric('Status', attempt.status_label || attempt.status || '-', 'Attempt #' + String(attempt.attempt_id || drawer.attemptId || 0)),
                renderDetailMetric('Skor', attempt.score_percentage_label || '0%', 'Durasi ' + String(attempt.duration_minutes || 0) + ' menit'),
                renderDetailMetric('Jawaban', String(progress.answer_count || 0) + ' / ' + String(progress.question_count || 0), String(progress.answered_percentage_label || '0%') + ' progress'),
                renderDetailMetric('Presence', presence.presence_label || '-', presence.last_seen_at ? 'Last seen ' + String(presence.last_seen_at) : 'Roster live'),
                '</div>',
                '<section class="cbt-supervisor-detail-section">',
                '<h3>Identitas</h3>',
                renderRowMeta([
                    student.username ? 'Username ' + String(student.username) : '',
                    student.nisn ? 'NISN ' + String(student.nisn) : '',
                    student.kelas,
                    student.ruang,
                    exam.title ? 'Exam ' + String(exam.title) : ''
                ]),
                '</section>',
                '<section class="cbt-supervisor-detail-section">',
                '<h3>Device & Submit</h3>',
                renderRowMeta([
                    presence.connection_status ? 'Koneksi ' + String(presence.connection_status) : '',
                    presence.visibility_state ? 'Visibility ' + String(presence.visibility_state) : '',
                    presence.heartbeat_lost_active ? 'Heartbeat lost aktif' : '',
                    submitStatus.state_label ? 'Submit ' + String(submitStatus.state_label) : '',
                    submitStatus.detail || ''
                ]),
                '</section>',
                '<section class="cbt-supervisor-detail-section">',
                '<h3>Timeline</h3>',
                renderRowMeta([
                    timeline.started_at ? 'Mulai ' + String(timeline.started_at) : '',
                    timeline.finished_at ? 'Selesai ' + String(timeline.finished_at) : '',
                    timeline.updated_at ? 'Update ' + String(timeline.updated_at) : '',
                    timeline.remaining_label || ''
                ]),
                '</section>',
                renderSupervisorSecurityTimelineSection(securityTimeline, events)
            ].join('');
        }

        return [
            '<div class="cbt-supervisor-detail-backdrop" role="presentation">',
            '<div class="cbt-supervisor-detail-scrim" data-action="close-attempt-detail"></div>',
            '<aside class="cbt-supervisor-detail-drawer" role="dialog" aria-modal="true" aria-label="Detail siswa">',
            '<header class="cbt-supervisor-detail-head">',
            '<div>',
            '<span class="cbt-supervisor-kicker">Detail Siswa</span>',
            '<h2>' + escapeHtml(String(student.name || 'Attempt #' + String(drawer.attemptId || 0))) + '</h2>',
            '<p>' + escapeHtml(String(exam.title || '-')) + '</p>',
            '</div>',
            '<button class="cbt-supervisor-icon-button" type="button" data-action="close-attempt-detail" aria-label="Tutup detail" title="Tutup detail">' + renderSupervisorIcon('x') + '</button>',
            '</header>',
            '<div class="cbt-supervisor-detail-body">' + body + '</div>',
            '</aside>',
            '</div>'
        ].join('');
    }

    function renderDashboardView() {
        var branding = getSupervisorBranding();
        var userName = state.user ? String(state.user.display_name || state.user.username || 'Pengawas') : 'Pengawas';
        var roleLabel = state.dashboard && state.dashboard.scope
            ? String(state.dashboard.scope.role_label || state.user.role || '')
            : String(state.user ? state.user.role || '' : '');
        var scopeLabel = state.dashboard && state.dashboard.scope ? String(state.dashboard.scope.scope_label || '') : '';
        var snapshot = state.dashboard && state.dashboard.status_snapshot ? state.dashboard.status_snapshot : {};
        var mode = String(snapshot.mode || 'online');
        var statusLabel = String(snapshot.status_label || 'Telemetry siap dipantau.');
        var scopeHeading = (scopeLabel !== '' ? scopeLabel : 'Semua exam').toUpperCase();

        return [
            '<div class="cbt-supervisor-shell">',
            '<header class="cbt-supervisor-topbar">',
            renderSupervisorBrandIdentity(),
            renderTabs(),
            '<div class="cbt-supervisor-topbar-actions">',
            '<span class="cbt-supervisor-topbar-status">' + renderLiveDot() + '<span>' + escapeHtml(mode.toUpperCase()) + '</span></span>',
            '<button class="cbt-supervisor-icon-button" type="button" data-action="refresh-dashboard"' + (state.dashboardBusy ? ' disabled' : '') + ' aria-label="Refresh dashboard" title="Refresh dashboard">' + renderSupervisorIcon('refresh') + '</button>',
            '<button class="cbt-supervisor-icon-button" type="button" data-action="logout-supervisor" aria-label="Logout" title="Logout">' + renderSupervisorIcon('logout') + '</button>',
            '<span class="cbt-supervisor-user-chip"><span><strong>' + escapeHtml(userName) + '</strong><small>' + escapeHtml(roleLabel) + '</small></span><span class="cbt-supervisor-avatar is-user">' + escapeHtml(getSupervisorInitials(state.user)) + '</span></span>',
            '</div>',
            '</header>',
            '<main class="cbt-supervisor-main">',
            renderNoticeStack(),
            '<section class="cbt-supervisor-command-strip">',
            '<div class="cbt-supervisor-command-title">',
            '<div class="cbt-supervisor-breadcrumb"><span>DASHBOARD</span><span>/</span><strong>' + escapeHtml(scopeHeading) + '</strong></div>',
            '<h1>DASHBOARD PENGAWAS</h1>',
            branding.motto !== '' ? '<p>' + escapeHtml(branding.motto) + '</p>' : '',
            '</div>',
            '<div class="cbt-supervisor-command-meta">',
            '<span class="cbt-supervisor-mini-stat"><span>Refresh</span><strong>' + escapeHtml(state.dashboardBusy ? 'Memuat' : '15 detik') + '</strong></span>',
            '<span class="cbt-supervisor-mini-stat"><span>Status</span><strong>' + escapeHtml(statusLabel) + '</strong></span>',
            '</div>',
            '</section>',
            renderDashboardBody(),
            '</main>',
            renderAttemptDetailDrawer(),
            '</div>'
        ].join('');
    }

    function render() {
        if (!state.user || !state.token) {
            root.innerHTML = renderLoginView();
            return;
        }

        root.innerHTML = renderDashboardView();
    }

    function resetFilters() {
        state.filters.examId = 0;
        state.filters.kelas = '';
        state.filters.ruang = '';
        state.filters.studentKeyword = '';
        state.filters.status = '';
        state.filters.rosterPage = 1;
        state.filters.attemptsPage = 1;
        state.filters.actionPage = 1;
        state.filters.securityPage = 1;
        state.filters.securitySeverity = 'all';
        state.filters.securityEventType = 'all';
        state.filters.securityDeviceType = 'all';
        state.filters.attendancePage = 1;
        state.filters.attendanceStatus = '';
    }

    root.addEventListener('submit', function (event) {
        var loginForm = event.target instanceof Element ? event.target.closest('[data-supervisor-login-form]') : null;
        if (loginForm) {
            event.preventDefault();
            if (state.loginBusy) {
                return;
            }

            var loginData = new window.FormData(loginForm);
            submitLogin(
                String(loginData.get('identifier') || '').trim(),
                String(loginData.get('password') || '')
            );
            return;
        }

        var filterForm = event.target instanceof Element ? event.target.closest('[data-supervisor-filter-form]') : null;
        if (filterForm) {
            event.preventDefault();
            applyFilterForm(filterForm);
            loadDashboard();
        }
    });

    root.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target.closest('[data-action]') : null;
        if (!target) {
            return;
        }

        var action = String(target.getAttribute('data-action') || '');
        if (action === 'logout-supervisor') {
            performLogout();
            return;
        }

        if (action === 'refresh-dashboard') {
            loadDashboard();
            return;
        }

        if (action === 'clear-filters') {
            resetFilters();
            loadDashboard();
            return;
        }

        if (action === 'close-attempt-detail') {
            closeAttemptDetail();
            return;
        }

        if (action === 'open-attempt-detail') {
            openAttemptDetail(Number(target.getAttribute('data-attempt-id')) || 0);
            return;
        }

        if (action === 'switch-tab') {
            var tab = String(target.getAttribute('data-tab') || 'live_roster');
            if (tab !== state.activeTab) {
                state.activeTab = tab;
                state.filters.rosterPage = 1;
                state.filters.attemptsPage = 1;
                state.filters.actionPage = 1;
                state.filters.securityPage = 1;
                state.filters.attendancePage = 1;
                render();
                loadDashboard({
                    silent: true,
                    keepNotice: true
                });
            }
            return;
        }

        if (action === 'page-prev' || action === 'page-next') {
            var direction = action === 'page-prev' ? -1 : 1;
            var scope = String(target.getAttribute('data-scope') || '');
            if (scope === 'roster') {
                state.filters.rosterPage = Math.max(1, Number(state.filters.rosterPage) + direction);
            } else if (scope === 'attempts') {
                state.filters.attemptsPage = Math.max(1, Number(state.filters.attemptsPage) + direction);
            } else if (scope === 'action') {
                state.filters.actionPage = Math.max(1, Number(state.filters.actionPage) + direction);
            } else if (scope === 'security') {
                state.filters.securityPage = Math.max(1, Number(state.filters.securityPage) + direction);
            } else if (scope === 'attendance') {
                state.filters.attendancePage = Math.max(1, Number(state.filters.attendancePage) + direction);
            }
            loadDashboard({
                silent: true,
                keepNotice: true
            });
            return;
        }

        if (action === 'reset-login') {
            var attemptId = Number(target.getAttribute('data-attempt-id')) || 0;
            if (attemptId <= 0) {
                return;
            }

            var studentName = String(target.getAttribute('data-student-name') || 'siswa ini');
            if (!window.confirm('Reset login ' + studentName + ' (attempt #' + String(attemptId) + ')? Semua browser aktif user ini akan diminta login ulang.')) {
                return;
            }

            performResetLogin(attemptId);
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden || !state.user || !state.token) {
            return;
        }

        loadDashboard({
            silent: true,
            keepNotice: true
        });
        scheduleAutoRefresh();
    });

    window.addEventListener('beforeunload', stopAutoRefresh);

    setBootProgress(22, 'Memuat konfigurasi pengawas', 'Loading supervisor runtime');
    var persisted = readPersistedAuthSession();
    if (persisted && persisted.user && persisted.token && isSupervisorRole(persisted.user.role)) {
        state.user = persisted.user;
        state.token = persisted.token;
        setBootProgress(54, 'Memulihkan sesi pengawas', 'Restoring supervisor session');
        render();
        loadDashboard().finally(scheduleAutoRefresh);
        return;
    }

    clearPersistedAuthSession();
    setBootProgress(68, 'Menyiapkan login pengawas', 'Supervisor login ready');
    render();
}
