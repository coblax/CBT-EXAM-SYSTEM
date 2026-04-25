import { getFrontendConfig } from '../core/config.js';
import { createBrowserStorageAccess } from '../core/browser-storage.js';
import { createApiClient } from '../core/api.js';
import { escapeHtml } from '../core/html.js';

var SUPERVISOR_AUTH_STORAGE_KEY = 'cbt_exam_frontend_supervisor_auth_v1';
var SUPERVISOR_AUTO_REFRESH_MS = 15000;
var LOGIN_IDENTIFIER_MAX_LENGTH = 191;
var LOGIN_PASSWORD_MAX_LENGTH = 1024;

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
            securityPage: 1,
            securitySeverity: 'all',
            securityEventType: 'all',
            securityDeviceType: 'all',
            attendancePage: 1,
            attendanceStatus: ''
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

    function buildDashboardQuery() {
        return {
            tab: state.activeTab,
            exam_id: Number(state.filters.examId) || 0,
            kelas: String(state.filters.kelas || ''),
            ruang: String(state.filters.ruang || ''),
            student_keyword: String(state.filters.studentKeyword || ''),
            status: String(state.filters.status || ''),
            roster_page: Number(state.filters.rosterPage) || 1,
            attempts_page: Number(state.filters.attemptsPage) || 1,
            security_page: Number(state.filters.securityPage) || 1,
            security_severity: String(state.filters.securitySeverity || 'all'),
            security_event_type: String(state.filters.securityEventType || 'all'),
            security_device_type: String(state.filters.securityDeviceType || 'all'),
            attendance_page: Number(state.filters.attendancePage) || 1,
            attendance_status: String(state.filters.attendanceStatus || '')
        };
    }

    async function loadDashboard(options) {
        options = options || {};
        if (!state.token || !state.user) {
            return null;
        }

        state.dashboardBusy = options.silent !== true;
        if (options.keepNotice !== true) {
            state.notice = '';
        }
        state.error = '';
        if (options.silent !== true) {
            render();
        }

        var requestId = ++requestCounter;
        try {
            var payload = await apiClient.api('supervisor_dashboard', {
                method: 'GET',
                query: buildDashboardQuery()
            });
            if (requestId !== requestCounter) {
                return payload;
            }

            state.dashboard = payload && typeof payload === 'object' ? payload : null;
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

    function applyFilterForm(form) {
        var data = new window.FormData(form);
        state.filters.examId = Number(data.get('exam_id')) || 0;
        state.filters.kelas = String(data.get('kelas') || '');
        state.filters.ruang = String(data.get('ruang') || '');
        state.filters.studentKeyword = String(data.get('student_keyword') || '').trim();
        state.filters.status = String(data.get('status') || '');
        state.filters.securitySeverity = String(data.get('security_severity') || 'all');
        state.filters.securityEventType = String(data.get('security_event_type') || 'all');
        state.filters.securityDeviceType = String(data.get('security_device_type') || 'all');
        state.filters.attendanceStatus = String(data.get('attendance_status') || '');
        state.filters.rosterPage = 1;
        state.filters.attemptsPage = 1;
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
            filter: '<path d="M3 5h18"></path><path d="M7 12h10"></path><path d="M10 19h4"></path>',
            key: '<path d="M21 2l-2 2"></path><path d="M15 8l-2 2"></path><path d="m7 16 3-3"></path><circle cx="7.5" cy="16.5" r="4.5"></circle><path d="m11 13 8-8 2 2-8 8"></path>',
            logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path>',
            monitor: '<rect x="3" y="4" width="18" height="12" rx="2"></rect><path d="M8 20h8"></path><path d="M12 16v4"></path>',
            radio: '<path d="M4.9 19.1a10 10 0 0 1 0-14.2"></path><path d="M7.8 16.2a6 6 0 0 1 0-8.5"></path><circle cx="12" cy="12" r="2"></circle><path d="M16.2 7.8a6 6 0 0 1 0 8.5"></path><path d="M19.1 4.9a10 10 0 0 1 0 14.2"></path>',
            refresh: '<path d="M20 11a8.1 8.1 0 0 0-15.5-2M4 5v4h4"></path><path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4"></path>',
            search: '<circle cx="11" cy="11" r="7"></circle><path d="m21 21-4.3-4.3"></path>',
            users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.9"></path><path d="M16 3.1a4 4 0 0 1 0 7.8"></path>'
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

    function renderProgressBar(percent) {
        var numericPercent = Math.max(0, Math.min(100, Number(percent) || 0));
        return '<span class="cbt-supervisor-progress" aria-hidden="true"><span style="width:' + escapeHtml(String(numericPercent)) + '%"></span></span>';
    }

    function renderLoginView() {
        var studentLink = String(config.studentFrontendUrl || '').trim();
        var alternateLink = studentLink !== ''
            ? '<p class="cbt-supervisor-login-help">Peserta ujian gunakan <a href="' + escapeHtml(studentLink) + '">halaman ujian siswa</a>.</p>'
            : '';

        return [
            '<div class="cbt-supervisor-login-shell">',
            '<header class="cbt-supervisor-topbar cbt-supervisor-topbar-login">',
            '<div class="cbt-supervisor-brand">',
            '<span class="cbt-supervisor-brand-mark">' + renderSupervisorIcon('monitor') + '</span>',
            '<span><strong>ExamCommand</strong><small>Supervisor Frontend</small></span>',
            '</div>',
            '<div class="cbt-supervisor-topbar-status">' + renderLiveDot() + '<span>Light blue mode</span></div>',
            '</header>',
            '<main class="cbt-supervisor-login-main">',
            '<section class="cbt-supervisor-login-copy">',
            '<span class="cbt-supervisor-kicker">Panel Pengawas</span>',
            '<h1>Monitoring ujian yang terang, cepat, dan rapi.</h1>',
            '<p>Masuk sebagai guru atau admin untuk melihat roster live, must watch, monitoring attempts, dan status submit tanpa masuk ke wp-admin.</p>',
            '<div class="cbt-supervisor-login-points">',
            '<span>' + renderSupervisorIcon('radio') + '<strong>Live roster</strong><small>Status koneksi peserta</small></span>',
            '<span>' + renderSupervisorIcon('alert') + '<strong>Must watch</strong><small>Prioritas risiko tertinggi</small></span>',
            '<span>' + renderSupervisorIcon('clipboard') + '<strong>Attempts</strong><small>Progress dan finalisasi</small></span>',
            '</div>',
            '</section>',
            '<section class="cbt-supervisor-login-card">',
            '<div class="cbt-supervisor-login-kicker">Login Pengawas</div>',
            '<h2>Masuk ke dashboard</h2>',
            '<p>Gunakan akun guru atau admin yang sudah terdaftar di WordPress.</p>',
            renderNoticeStack(),
            '<form class="cbt-supervisor-login-form" data-supervisor-login-form>',
            '<label class="cbt-supervisor-field">',
            '<span>Identifier</span>',
            '<input type="text" name="identifier" autocomplete="username" maxlength="191" placeholder="Username, email, atau NISN" required ' + (state.loginBusy ? 'disabled' : '') + ' />',
            '</label>',
            '<label class="cbt-supervisor-field">',
            '<span>Password</span>',
            '<input type="password" name="password" autocomplete="current-password" maxlength="1024" placeholder="Masukkan password" required ' + (state.loginBusy ? 'disabled' : '') + ' />',
            '</label>',
            '<div class="cbt-supervisor-login-actions">',
            '<button class="cbt-supervisor-button is-primary" type="submit" ' + (state.loginBusy ? 'disabled' : '') + '>' + escapeHtml(state.loginBusy ? 'Memproses Login...' : 'Login Pengawas') + '</button>',
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
                var iconName = String(card.key || '') === 'live_roster'
                    ? 'users'
                    : String(card.key || '') === 'must_watch'
                        ? 'alert'
                        : String(card.key || '') === 'submit_watchlist'
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

    function renderFilterForm() {
        var options = state.dashboard && state.dashboard.filter_options ? state.dashboard.filter_options : {};
        var exams = Array.isArray(options.exams) ? options.exams : [];
        var kelasOptions = Array.isArray(options.kelas) ? options.kelas : [];
        var ruangOptions = Array.isArray(options.ruang) ? options.ruang : [];
        var securityLog = state.dashboard && state.dashboard.security_log ? state.dashboard.security_log : {};
        var eventCatalog = Array.isArray(securityLog.event_catalog) ? securityLog.event_catalog : [];
        var fields = [];

        fields.push([
            '<label class="cbt-supervisor-field">',
            '<span>Exam</span>',
            '<select name="exam_id">',
            '<option value="0">' + escapeHtml(state.activeTab === 'attendance' || state.activeTab === 'token_gate' ? 'Pilih exam' : 'Semua exam') + '</option>',
            exams.map(function (exam) {
                var examId = Number(exam.id) || 0;
                return '<option value="' + escapeHtml(String(examId)) + '"' + (examId === Number(state.filters.examId) ? ' selected' : '') + '>' + escapeHtml(String(exam.label || '-')) + '</option>';
            }).join(''),
            '</select>',
            '</label>'
        ].join(''));

        if (state.activeTab !== 'token_gate') {
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

        if (state.activeTab === 'security_log') {
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
        } else if (state.activeTab === 'attendance') {
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
        } else if (state.activeTab !== 'token_gate') {
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

        return [
            '<form class="cbt-supervisor-filter-bar" data-supervisor-filter-form>',
            fields.join(''),
            '<div class="cbt-supervisor-filter-actions">',
            '<button class="cbt-supervisor-button is-primary" type="submit"' + (state.dashboardBusy ? ' disabled' : '') + '>' + renderSupervisorIcon('filter') + '<span>Terapkan</span></button>',
            '<button class="cbt-supervisor-button" type="button" data-action="clear-filters"' + (state.dashboardBusy ? ' disabled' : '') + '>Reset Filter</button>',
            '</div>',
            '</form>'
        ].join('');
    }

    function renderTabs() {
        var tabs = [
            { id: 'overview', label: 'Ringkasan' },
            { id: 'live_roster', label: 'Live Roster' },
            { id: 'must_watch', label: 'Must Watch' },
            { id: 'monitoring_attempts', label: 'Attempts' },
            { id: 'security_log', label: 'Security Log' },
            { id: 'token_gate', label: 'Token & Gate' },
            { id: 'submit_recovery', label: 'Submit Recovery' },
            { id: 'attendance', label: 'Daftar Hadir' }
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
            '<table class="cbt-supervisor-table">',
            '<thead><tr><th>Siswa</th><th>Exam</th><th>Presence</th><th>Risk</th><th>Last Seen</th><th>Aksi</th></tr></thead>',
            '<tbody>',
            items.map(function (item) {
                var resetBusy = Number(state.activeResetAttemptId) === Number(item.attempt_id);
                return [
                    '<tr>',
                    '<td><div class="cbt-supervisor-student-cell"><span class="cbt-supervisor-avatar">' + escapeHtml(getInitialsFromText(item.student_name, 'SW')) + '</span><span><strong>' + escapeHtml(String(item.student_name || '-')) + '</strong><small>' + escapeHtml([item.student_login, item.student_kelas, item.student_ruang].filter(Boolean).join(' · ')) + '</small></span></div></td>',
                    '<td><strong>' + escapeHtml(String(item.exam_title || '-')) + '</strong><small>Attempt #' + escapeHtml(String(item.attempt_id || 0)) + '</small></td>',
                    '<td><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.presence_status || 'unknown')) + '">' + escapeHtml(String(item.presence_label || '-')) + '</span><small>' + escapeHtml([item.connection_status, item.visibility_state, item.heartbeat_lost_active ? 'heartbeat lost' : ''].filter(Boolean).join(' · ')) + '</small></td>',
                    '<td><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.risk_tone || 'normal')) + '">' + escapeHtml(String(item.risk_label || 'Normal')) + '</span><small>Skor ' + escapeHtml(String(item.risk_score_label || '0')) + '</small></td>',
                    '<td><strong>' + escapeHtml(String(item.last_seen_at || '-')) + '</strong><small>Pending sync ' + escapeHtml(String(Number(item.pending_sync_count) || 0)) + '</small></td>',
                    '<td><button class="cbt-supervisor-button is-small" type="button" data-action="reset-login" data-attempt-id="' + escapeHtml(String(item.attempt_id || 0)) + '"' + (resetBusy ? ' disabled' : '') + '>' + renderSupervisorIcon('refresh') + '<span>' + escapeHtml(resetBusy ? 'Mereset...' : 'Reset Login') + '</span></button></td>',
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
            '<div class="cbt-supervisor-watch-grid">',
            items.map(function (item) {
                var resetBusy = Number(state.activeResetAttemptId) === Number(item.attempt_id);
                return [
                    '<article class="cbt-supervisor-watch-card">',
                    '<div class="cbt-supervisor-watch-head">',
                    '<div class="cbt-supervisor-student-cell"><span class="cbt-supervisor-avatar">' + escapeHtml(getInitialsFromText(item.student_name, 'SW')) + '</span><span><strong>' + escapeHtml(String(item.student_name || '-')) + '</strong><span>' + escapeHtml([item.student_login, item.student_kelas, item.student_ruang].filter(Boolean).join(' · ')) + '</span></span></div>',
                    '<span class="cbt-supervisor-pill is-watch">' + escapeHtml(String(item.risk_label || 'Must Watch')) + '</span>',
                    '</div>',
                    '<div class="cbt-supervisor-watch-meta">',
                    '<span>' + escapeHtml(String(item.exam_title || '-')) + '</span>',
                    '<span>Skor ' + escapeHtml(String(item.risk_score_label || '0')) + '</span>',
                    '<span>' + escapeHtml(String(item.presence_label || '-')) + '</span>',
                    '</div>',
                    '<p>' + escapeHtml(String(item.primary_event_label || 'Aktivitas diamati.')) + '</p>',
                    '<small>' + escapeHtml(String(item.last_event_at || '-')) + '</small>',
                    '<div class="cbt-supervisor-chip-row">' + (Array.isArray(item.top_indicators) ? item.top_indicators.map(function (label) {
                        return '<span class="cbt-supervisor-chip">' + escapeHtml(String(label || '')) + '</span>';
                    }).join('') : '') + '</div>',
                    '<div class="cbt-supervisor-watch-actions">',
                    '<button class="cbt-supervisor-button is-small" type="button" data-action="reset-login" data-attempt-id="' + escapeHtml(String(item.attempt_id || 0)) + '"' + (resetBusy ? ' disabled' : '') + '>' + renderSupervisorIcon('refresh') + '<span>' + escapeHtml(resetBusy ? 'Mereset...' : 'Reset Login') + '</span></button>',
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
                '<table class="cbt-supervisor-table">',
                '<thead><tr><th>Siswa</th><th>Exam</th><th>Status</th><th>Skor</th><th>Jawaban</th><th>Timeline</th><th>Aksi</th></tr></thead>',
                '<tbody>',
                items.map(function (item) {
                    var resetBusy = Number(state.activeResetAttemptId) === Number(item.attempt_id);
                    var answeredPercent = Number(String(item.answered_percentage_label || '0').replace('%', '')) || 0;
                    return [
                        '<tr>',
                        '<td><div class="cbt-supervisor-student-cell"><span class="cbt-supervisor-avatar">' + escapeHtml(getInitialsFromText(item.student_name, 'SW')) + '</span><span><strong>' + escapeHtml(String(item.student_name || '-')) + '</strong><small>' + escapeHtml([item.student_username, item.student_nisn, item.student_kelas].filter(Boolean).join(' · ')) + '</small></span></div></td>',
                        '<td><strong>' + escapeHtml(String(item.exam_title || '-')) + '</strong><small>Attempt #' + escapeHtml(String(item.attempt_id || 0)) + '</small></td>',
                        '<td><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.status || 'completed')) + '">' + escapeHtml(String(item.status_label || '-')) + '</span><small>' + escapeHtml(String(item.presence_label || '-')) + '</small></td>',
                        '<td><strong>' + escapeHtml(String(item.score_percentage_label || '0%')) + '</strong><small>' + escapeHtml('Benar ' + String(item.earned_points || 0) + ' · Salah ' + String(item.wrong_points || 0)) + '</small></td>',
                        '<td><strong>' + escapeHtml(String(item.answer_count || 0)) + ' / ' + escapeHtml(String(item.question_count || 0)) + '</strong>' + renderProgressBar(answeredPercent) + '<small>' + escapeHtml(String(item.answered_percentage_label || '0%') + ' progress') + '</small></td>',
                        '<td><strong>' + escapeHtml(String(item.started_at || '-')) + '</strong><small>' + escapeHtml(item.finalize_pending ? 'Waktu habis, finalisasi background aktif.' : String(item.remaining_label || '-')) + '</small></td>',
                        '<td><button class="cbt-supervisor-button is-small" type="button" data-action="reset-login" data-attempt-id="' + escapeHtml(String(item.attempt_id || 0)) + '"' + (resetBusy ? ' disabled' : '') + '>' + renderSupervisorIcon('refresh') + '<span>' + escapeHtml(resetBusy ? 'Mereset...' : 'Reset Login') + '</span></button></td>',
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
            ? '<div class="cbt-supervisor-watchlist-list">' + watchlistItems.map(function (item) {
                return [
                    '<article class="cbt-supervisor-watchlist-item">',
                    '<div class="cbt-supervisor-student-cell"><span class="cbt-supervisor-avatar">' + escapeHtml(getInitialsFromText(item.student_name, 'SW')) + '</span><span><strong>' + escapeHtml(String(item.student_name || '-')) + '</strong><span>' + escapeHtml([item.student_username, item.student_nisn, item.student_kelas].filter(Boolean).join(' · ')) + '</span></span></div>',
                    '<div><span class="cbt-supervisor-pill is-watchlist">' + escapeHtml(String(item.state_label || 'Unknown')) + '</span><small>' + escapeHtml(String(item.exam_title || '-')) + '</small></div>',
                    '<p>' + escapeHtml(String(item.detail || 'Status submit masih dipantau.')) + '</p>',
                    '</article>'
                ].join('');
            }).join('') + '</div>'
            : '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(submitWatchlist.note || 'Belum ada unresolved submit yang perlu diawasi.')) + '</div>';

        return [
            '<section class="cbt-supervisor-submit-health">',
            '<div class="cbt-supervisor-section-head"><div><span class="cbt-supervisor-kicker">Submit Telemetry</span><h3>Submit Health</h3></div><p>' + escapeHtml(String(submitHealth.note || 'Telemetry submit belum tersedia.')) + '</p></div>',
            '<div class="cbt-supervisor-summary-grid">',
            '<article class="cbt-supervisor-summary-card"><span>Finish Ack</span><strong>' + escapeHtml(String(submitHealth.finish_ack_total || 0)) + '</strong><small>15 menit terakhir</small></article>',
            '<article class="cbt-supervisor-summary-card"><span>Result Ready</span><strong>' + escapeHtml(String(submitHealth.result_ready_total || 0)) + '</strong><small>Recovery hasil sukses</small></article>',
            '<article class="cbt-supervisor-summary-card"><span>Recovery Failed</span><strong>' + escapeHtml(String(submitHealth.recovery_failed_total || 0)) + '</strong><small>Butuh perhatian operator</small></article>',
            '<article class="cbt-supervisor-summary-card"><span>Ack → Result p95</span><strong>' + escapeHtml(String(submitHealth.ack_to_result_ready_p95_label || 'N/A')) + '</strong><small>Latency recovery hasil</small></article>',
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
            '<table class="cbt-supervisor-table">',
            '<thead><tr><th>Waktu</th><th>Siswa</th><th>Exam</th><th>Event</th><th>Device</th><th>Pesan</th></tr></thead>',
            '<tbody>',
            items.map(function (item) {
                return [
                    '<tr>',
                    '<td><strong>' + escapeHtml(String(item.occurred_at || item.created_at || '-')) + '</strong><small>Log #' + escapeHtml(String(item.id || 0)) + '</small></td>',
                    '<td><div class="cbt-supervisor-student-cell"><span class="cbt-supervisor-avatar">' + escapeHtml(getInitialsFromText(item.student_name, 'SW')) + '</span><span><strong>' + escapeHtml(String(item.student_name || '-')) + '</strong><small>' + escapeHtml([item.student_login, item.student_kelas, item.student_ruang].filter(Boolean).join(' · ')) + '</small></span></div></td>',
                    '<td><strong>' + escapeHtml(String(item.exam_title || '-')) + '</strong><small>Attempt #' + escapeHtml(String(item.attempt_id || 0)) + '</small></td>',
                    '<td><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.severity || 'info')) + '">' + escapeHtml(String(item.severity || 'info')) + '</span><small>' + escapeHtml(String(item.event_label || item.event_type || '-')) + '</small></td>',
                    '<td><strong>' + escapeHtml(String(item.device_summary || '-')) + '</strong><small>' + escapeHtml(String(item.device_type || 'unknown')) + '</small></td>',
                    '<td><span class="cbt-supervisor-log-message">' + escapeHtml(String(item.message_display || '-')) + '</span></td>',
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
            '<table class="cbt-supervisor-table">',
            '<thead><tr><th>Siswa</th><th>Kelas</th><th>Ruang</th><th>Status</th><th>Attempt</th><th>Timeline</th></tr></thead>',
            '<tbody>',
            items.map(function (item) {
                return [
                    '<tr>',
                    '<td><div class="cbt-supervisor-student-cell"><span class="cbt-supervisor-avatar">' + escapeHtml(getInitialsFromText(item.student_name, 'SW')) + '</span><span><strong>' + escapeHtml(String(item.student_name || '-')) + '</strong><small>' + escapeHtml([item.student_username, item.student_nisn].filter(Boolean).join(' · ')) + '</small></span></div></td>',
                    '<td><strong>' + escapeHtml(String(item.student_kelas || '-')) + '</strong></td>',
                    '<td><strong>' + escapeHtml(String(item.student_ruang || '-')) + '</strong></td>',
                    '<td><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.status || 'not_started')) + '">' + escapeHtml(String(item.status_label || '-')) + '</span></td>',
                    '<td><strong>' + escapeHtml(item.attempt_id ? ('#' + String(item.attempt_id)) : '-') + '</strong></td>',
                    '<td><strong>' + escapeHtml(String(item.started_at || '-')) + '</strong><small>' + escapeHtml(String(item.finished_at || 'Belum selesai')) + '</small></td>',
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
        if (state.activeTab === 'must_watch') {
            return {
                title: 'Must Watch',
                description: 'Prioritaskan siswa dengan risiko tertinggi dan reset login jika perlu.'
            };
        }
        if (state.activeTab === 'monitoring_attempts') {
            return {
                title: 'Attempts',
                description: 'Pantau status, skor, progress jawaban, timeline, dan reset login.'
            };
        }
        if (state.activeTab === 'security_log') {
            return {
                title: 'Security Log',
                description: 'Baca log keamanan secara read-only dengan filter event, severity, device, kelas, dan ruang.'
            };
        }
        if (state.activeTab === 'token_gate') {
            return {
                title: 'Token & Gate',
                description: 'Lihat token global, jadwal refresh, start gate, dan readiness auto-warm untuk exam terpilih.'
            };
        }
        if (state.activeTab === 'submit_recovery') {
            return {
                title: 'Submit Recovery',
                description: 'Pisahkan telemetry submit dan watchlist recovery agar operasional lebih pendek.'
            };
        }
        if (state.activeTab === 'attendance') {
            return {
                title: 'Daftar Hadir',
                description: 'Lihat peserta target exam beserta status belum mulai, berjalan, atau selesai.'
            };
        }
        return {
            title: 'Live Roster',
            description: 'Pantau presence, koneksi, risk score, dan last seen attempt aktif.'
        };
    }

    function renderOperationalPanel() {
        var activeMeta = getActiveOperationalTitle();

        return [
            '<section class="cbt-supervisor-panel">',
            '<div class="cbt-supervisor-panel-head">',
            '<div><span class="cbt-supervisor-kicker">Operasional</span><h2>' + escapeHtml(activeMeta.title) + '</h2><p>' + escapeHtml(activeMeta.description) + '</p></div>',
            '<div class="cbt-supervisor-panel-actions">',
            '<button class="cbt-supervisor-button" type="button" data-action="refresh-dashboard"' + (state.dashboardBusy ? ' disabled' : '') + '>' + renderSupervisorIcon('refresh') + '<span>' + escapeHtml(state.dashboardBusy ? 'Memuat...' : 'Refresh') + '</span></button>',
            '</div>',
            '</div>',
            renderFilterForm(),
            '<div class="cbt-supervisor-tab-panel">' + renderActivePanel() + '</div>',
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

    function renderDashboardView() {
        var userName = state.user ? String(state.user.display_name || state.user.username || 'Pengawas') : 'Pengawas';
        var roleLabel = state.dashboard && state.dashboard.scope
            ? String(state.dashboard.scope.role_label || state.user.role || '')
            : String(state.user ? state.user.role || '' : '');
        var scopeLabel = state.dashboard && state.dashboard.scope ? String(state.dashboard.scope.scope_label || '') : '';
        var snapshot = state.dashboard && state.dashboard.status_snapshot ? state.dashboard.status_snapshot : {};
        var mode = String(snapshot.mode || 'online');

        return [
            '<div class="cbt-supervisor-shell">',
            '<header class="cbt-supervisor-topbar">',
            '<div class="cbt-supervisor-brand">',
            '<span class="cbt-supervisor-brand-mark">' + renderSupervisorIcon('monitor') + '</span>',
            '<span><strong>ExamCommand</strong><small>Supervisor Frontend</small></span>',
            '</div>',
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
            '<section class="cbt-supervisor-page-head">',
            '<div>',
            '<div class="cbt-supervisor-breadcrumb"><span>Dashboard</span><span>/</span><strong>' + escapeHtml(scopeLabel !== '' ? scopeLabel : 'Semua exam') + '</strong></div>',
            '<h1>Dashboard Pengawas</h1>',
            '<p>Pantau roster live, siswa berisiko, progress attempts, dan status submit dari satu layar operasional.</p>',
            '</div>',
            '<aside class="cbt-supervisor-page-health">',
            '<span class="cbt-supervisor-kicker">Auto refresh</span>',
            '<strong>' + escapeHtml(state.dashboardBusy ? 'Memuat data' : 'Setiap 15 detik') + '</strong>',
            '<small>' + escapeHtml(String(snapshot.status_label || 'Telemetry siap dipantau.')) + '</small>',
            '</aside>',
            '</section>',
            renderDashboardBody(),
            '</main>',
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

        if (action === 'switch-tab') {
            var tab = String(target.getAttribute('data-tab') || 'live_roster');
            if (tab !== state.activeTab) {
                state.activeTab = tab;
                state.filters.rosterPage = 1;
                state.filters.attemptsPage = 1;
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

            if (!window.confirm('Reset login siswa ini? Semua browser aktif user ini akan diminta login ulang.')) {
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
