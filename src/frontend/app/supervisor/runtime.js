import { getFrontendConfig } from '../core/config.js';
import { createBrowserStorageAccess } from '../core/browser-storage.js';
import { createApiClient } from '../core/api.js';
import { escapeHtml } from '../core/html.js';

var SUPERVISOR_AUTH_STORAGE_KEY = 'cbt_exam_frontend_supervisor_auth_v1';
var SUPERVISOR_AUTO_REFRESH_MS = 15000;

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
        activeTab: 'live_roster',
        filters: {
            examId: 0,
            kelas: '',
            studentKeyword: '',
            status: '',
            rosterPage: 1,
            attemptsPage: 1
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
            student_keyword: String(state.filters.studentKeyword || ''),
            status: String(state.filters.status || ''),
            roster_page: Number(state.filters.rosterPage) || 1,
            attempts_page: Number(state.filters.attemptsPage) || 1
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
        state.filters.studentKeyword = String(data.get('student_keyword') || '').trim();
        state.filters.status = String(data.get('status') || '');
        state.filters.rosterPage = 1;
        state.filters.attemptsPage = 1;
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

    function renderLoginView() {
        var studentLink = String(config.studentFrontendUrl || '').trim();
        var alternateLink = studentLink !== ''
            ? '<p class="cbt-supervisor-login-help">Peserta ujian gunakan <a href="' + escapeHtml(studentLink) + '">halaman ujian siswa</a>.</p>'
            : '';

        return [
            '<div class="cbt-frontpage__shell">',
            '<div class="cbt-supervisor-login-layout">',
            '<section class="cbt-supervisor-login-card">',
            '<div class="cbt-supervisor-login-kicker">Supervisor Frontend</div>',
            '<h1>Login Pengawas</h1>',
            '<p>Masuk dengan akun guru atau admin untuk memantau roster live, must watch, dan monitoring attempts.</p>',
            renderNoticeStack(),
            '<form class="cbt-supervisor-login-form" data-supervisor-login-form>',
            '<label class="cbt-supervisor-field">',
            '<span>Identifier</span>',
            '<input type="text" name="identifier" autocomplete="username" placeholder="Username, email, atau NISN" required ' + (state.loginBusy ? 'disabled' : '') + ' />',
            '</label>',
            '<label class="cbt-supervisor-field">',
            '<span>Password</span>',
            '<input type="password" name="password" autocomplete="current-password" placeholder="Masukkan password" required ' + (state.loginBusy ? 'disabled' : '') + ' />',
            '</label>',
            '<div class="cbt-supervisor-login-actions">',
            '<button class="cbt-supervisor-button is-primary" type="submit" ' + (state.loginBusy ? 'disabled' : '') + '>' + escapeHtml(state.loginBusy ? 'Memproses Login...' : 'Login Pengawas') + '</button>',
            '</div>',
            '</form>',
            alternateLink,
            '</section>',
            '</div>',
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
            '</div>',
            '<span class="cbt-supervisor-status-badge">' + escapeHtml(mode.toUpperCase()) + '</span>',
            '</div>',
            '<div class="cbt-supervisor-status-grid">',
            '<div><span>Backlog</span><strong>' + escapeHtml(String(backlogCount)) + '</strong></div>',
            '<div><span>Dead Letter</span><strong>' + escapeHtml(String(Math.max(0, Number(snapshot.dead_letter_count) || 0))) + '</strong></div>',
            '<div><span>Last Flush</span><strong>' + escapeHtml(String(snapshot.last_flush_at || '-')) + '</strong></div>',
            '<div><span>Next Flush</span><strong>' + escapeHtml(String(snapshot.next_flush_at || '-')) + '</strong></div>',
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
                return [
                    '<article class="cbt-supervisor-summary-card">',
                    '<span>' + escapeHtml(String(card.label || '-')) + '</span>',
                    '<strong>' + escapeHtml(String(card.value || '0')) + '</strong>',
                    '<small>' + escapeHtml(String(card.meta || '')) + '</small>',
                    '</article>'
                ].join('');
            }).join(''),
            '</section>'
        ].join('');
    }

    function renderFilterForm() {
        var options = state.dashboard && state.dashboard.filter_options ? state.dashboard.filter_options : {};
        var exams = Array.isArray(options.exams) ? options.exams : [];
        var kelasOptions = Array.isArray(options.kelas) ? options.kelas : [];

        return [
            '<form class="cbt-supervisor-filter-bar" data-supervisor-filter-form>',
            '<label class="cbt-supervisor-field">',
            '<span>Exam</span>',
            '<select name="exam_id">',
            '<option value="0">Semua exam</option>',
            exams.map(function (exam) {
                var examId = Number(exam.id) || 0;
                return '<option value="' + escapeHtml(String(examId)) + '"' + (examId === Number(state.filters.examId) ? ' selected' : '') + '>' + escapeHtml(String(exam.label || '-')) + '</option>';
            }).join(''),
            '</select>',
            '</label>',
            '<label class="cbt-supervisor-field">',
            '<span>Kelas</span>',
            '<select name="kelas">',
            '<option value="">Semua kelas</option>',
            kelasOptions.map(function (kelas) {
                return '<option value="' + escapeHtml(String(kelas)) + '"' + (String(kelas) === String(state.filters.kelas || '') ? ' selected' : '') + '>' + escapeHtml(String(kelas)) + '</option>';
            }).join(''),
            '</select>',
            '</label>',
            '<label class="cbt-supervisor-field cbt-supervisor-field-search">',
            '<span>Cari siswa</span>',
            '<input type="text" name="student_keyword" value="' + escapeHtml(String(state.filters.studentKeyword || '')) + '" placeholder="Nama, username, NISN, exam" />',
            '</label>',
            '<label class="cbt-supervisor-field">',
            '<span>Status</span>',
            '<select name="status">',
            '<option value="">Semua status</option>',
            '<option value="in_progress"' + (String(state.filters.status || '') === 'in_progress' ? ' selected' : '') + '>Berjalan</option>',
            '<option value="completed"' + (String(state.filters.status || '') === 'completed' ? ' selected' : '') + '>Selesai</option>',
            '</select>',
            '</label>',
            '<div class="cbt-supervisor-filter-actions">',
            '<button class="cbt-supervisor-button is-primary" type="submit"' + (state.dashboardBusy ? ' disabled' : '') + '>Terapkan</button>',
            '<button class="cbt-supervisor-button" type="button" data-action="clear-filters"' + (state.dashboardBusy ? ' disabled' : '') + '>Reset Filter</button>',
            '</div>',
            '</form>'
        ].join('');
    }

    function renderTabs() {
        var tabs = [
            { id: 'live_roster', label: 'Live Roster' },
            { id: 'must_watch', label: 'Must Watch' },
            { id: 'monitoring_attempts', label: 'Monitoring Attempts' }
        ];

        return [
            '<div class="cbt-supervisor-tab-bar" role="tablist" aria-label="Supervisor Tabs">',
            tabs.map(function (tab) {
                var isActive = state.activeTab === tab.id;
                return '<button class="cbt-supervisor-tab' + (isActive ? ' is-active' : '') + '" type="button" role="tab" aria-selected="' + (isActive ? 'true' : 'false') + '" data-action="switch-tab" data-tab="' + escapeHtml(tab.id) + '">' + escapeHtml(tab.label) + '</button>';
            }).join(''),
            '</div>'
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
                    '<td><strong>' + escapeHtml(String(item.student_name || '-')) + '</strong><small>' + escapeHtml([item.student_login, item.student_kelas, item.student_ruang].filter(Boolean).join(' · ')) + '</small></td>',
                    '<td><strong>' + escapeHtml(String(item.exam_title || '-')) + '</strong><small>Attempt #' + escapeHtml(String(item.attempt_id || 0)) + '</small></td>',
                    '<td><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.presence_status || 'unknown')) + '">' + escapeHtml(String(item.presence_label || '-')) + '</span><small>' + escapeHtml([item.connection_status, item.visibility_state, item.heartbeat_lost_active ? 'heartbeat lost' : ''].filter(Boolean).join(' · ')) + '</small></td>',
                    '<td><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.risk_tone || 'normal')) + '">' + escapeHtml(String(item.risk_label || 'Normal')) + '</span><small>Skor ' + escapeHtml(String(item.risk_score_label || '0')) + '</small></td>',
                    '<td><strong>' + escapeHtml(String(item.last_seen_at || '-')) + '</strong><small>Pending sync ' + escapeHtml(String(Number(item.pending_sync_count) || 0)) + '</small></td>',
                    '<td><button class="cbt-supervisor-button is-small" type="button" data-action="reset-login" data-attempt-id="' + escapeHtml(String(item.attempt_id || 0)) + '"' + (resetBusy ? ' disabled' : '') + '>' + escapeHtml(resetBusy ? 'Mereset...' : 'Reset Login') + '</button></td>',
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
                    '<div><strong>' + escapeHtml(String(item.student_name || '-')) + '</strong><span>' + escapeHtml([item.student_login, item.student_kelas, item.student_ruang].filter(Boolean).join(' · ')) + '</span></div>',
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
                    '<button class="cbt-supervisor-button is-small" type="button" data-action="reset-login" data-attempt-id="' + escapeHtml(String(item.attempt_id || 0)) + '"' + (resetBusy ? ' disabled' : '') + '>' + escapeHtml(resetBusy ? 'Mereset...' : 'Reset Login') + '</button>',
                    '</div>',
                    '</article>'
                ].join('');
            }).join(''),
            '</div>'
        ].join('');
    }

    function renderMonitoringAttemptsPanel() {
        var section = state.dashboard && state.dashboard.monitoring_attempts ? state.dashboard.monitoring_attempts : null;
        var submitHealth = state.dashboard && state.dashboard.submit_health ? state.dashboard.submit_health : {};
        var submitWatchlist = state.dashboard && state.dashboard.submit_watchlist ? state.dashboard.submit_watchlist : {};

        if (!section) {
            return '<div class="cbt-supervisor-empty-state">Data monitoring attempts belum dimuat.</div>';
        }

        var items = Array.isArray(section.items) ? section.items : [];
        var attemptsMarkup = items.length
            ? [
                '<div class="cbt-supervisor-table-wrap">',
                '<table class="cbt-supervisor-table">',
                '<thead><tr><th>Siswa</th><th>Exam</th><th>Status</th><th>Skor</th><th>Jawaban</th><th>Timeline</th><th>Aksi</th></tr></thead>',
                '<tbody>',
                items.map(function (item) {
                    var resetBusy = Number(state.activeResetAttemptId) === Number(item.attempt_id);
                    return [
                        '<tr>',
                        '<td><strong>' + escapeHtml(String(item.student_name || '-')) + '</strong><small>' + escapeHtml([item.student_username, item.student_nisn, item.student_kelas].filter(Boolean).join(' · ')) + '</small></td>',
                        '<td><strong>' + escapeHtml(String(item.exam_title || '-')) + '</strong><small>Attempt #' + escapeHtml(String(item.attempt_id || 0)) + '</small></td>',
                        '<td><span class="cbt-supervisor-pill is-' + escapeHtml(String(item.status || 'completed')) + '">' + escapeHtml(String(item.status_label || '-')) + '</span><small>' + escapeHtml(String(item.presence_label || '-')) + '</small></td>',
                        '<td><strong>' + escapeHtml(String(item.score_percentage_label || '0%')) + '</strong><small>' + escapeHtml('Benar ' + String(item.earned_points || 0) + ' · Salah ' + String(item.wrong_points || 0)) + '</small></td>',
                        '<td><strong>' + escapeHtml(String(item.answer_count || 0)) + ' / ' + escapeHtml(String(item.question_count || 0)) + '</strong><small>' + escapeHtml(String(item.answered_percentage_label || '0%') + ' progress') + '</small></td>',
                        '<td><strong>' + escapeHtml(String(item.started_at || '-')) + '</strong><small>' + escapeHtml(item.finalize_pending ? 'Waktu habis, finalisasi background aktif.' : String(item.remaining_label || '-')) + '</small></td>',
                        '<td><button class="cbt-supervisor-button is-small" type="button" data-action="reset-login" data-attempt-id="' + escapeHtml(String(item.attempt_id || 0)) + '"' + (resetBusy ? ' disabled' : '') + '>' + escapeHtml(resetBusy ? 'Mereset...' : 'Reset Login') + '</button></td>',
                        '</tr>'
                    ].join('');
                }).join(''),
                '</tbody>',
                '</table>',
                '</div>',
                renderPagination(section.pagination, 'attempts')
            ].join('')
            : '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(section.note || 'Belum ada attempt yang cocok dengan filter aktif.')) + '</div>';

        var watchlistItems = Array.isArray(submitWatchlist.items) ? submitWatchlist.items : [];
        var watchlistMarkup = watchlistItems.length
            ? '<div class="cbt-supervisor-watchlist-list">' + watchlistItems.map(function (item) {
                return [
                    '<article class="cbt-supervisor-watchlist-item">',
                    '<div><strong>' + escapeHtml(String(item.student_name || '-')) + '</strong><span>' + escapeHtml([item.student_username, item.student_nisn, item.student_kelas].filter(Boolean).join(' · ')) + '</span></div>',
                    '<div><span class="cbt-supervisor-pill is-watchlist">' + escapeHtml(String(item.state_label || 'Unknown')) + '</span><small>' + escapeHtml(String(item.exam_title || '-')) + '</small></div>',
                    '<p>' + escapeHtml(String(item.detail || 'Status submit masih dipantau.')) + '</p>',
                    '</article>'
                ].join('');
            }).join('') + '</div>'
            : '<div class="cbt-supervisor-empty-state">' + escapeHtml(String(submitWatchlist.note || 'Belum ada unresolved submit yang perlu diawasi.')) + '</div>';

        return [
            attemptsMarkup,
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

        return renderLiveRosterPanel();
    }

    function renderDashboardView() {
        var userName = state.user ? String(state.user.display_name || state.user.username || 'Pengawas') : 'Pengawas';
        var scopeLabel = state.dashboard && state.dashboard.scope ? String(state.dashboard.scope.scope_label || '') : '';

        return [
            '<div class="cbt-frontpage__shell">',
            '<div class="cbt-supervisor-dashboard">',
            '<section class="cbt-supervisor-hero">',
            '<div>',
            '<span class="cbt-supervisor-kicker">Supervisor Frontend</span>',
            '<h1>Dashboard Pengawas</h1>',
            '<p>' + escapeHtml(scopeLabel !== '' ? scopeLabel : 'Pantau live roster, must watch, dan monitoring attempts.') + '</p>',
            '</div>',
            '<div class="cbt-supervisor-hero-meta">',
            '<strong>' + escapeHtml(userName) + '</strong>',
            '<small>' + escapeHtml(state.user ? String(state.user.role || '') : '') + '</small>',
            '</div>',
            '</section>',
            renderNoticeStack(),
            renderStatusSnapshot(),
            renderSummaryCards(),
            '<section class="cbt-supervisor-panel">',
            '<div class="cbt-supervisor-panel-head">',
            '<div><span class="cbt-supervisor-kicker">Operasional</span><h2>Monitoring Ujian</h2></div>',
            '<div class="cbt-supervisor-panel-actions">',
            '<button class="cbt-supervisor-button" type="button" data-action="refresh-dashboard"' + (state.dashboardBusy ? ' disabled' : '') + '>' + escapeHtml(state.dashboardBusy ? 'Memuat...' : 'Refresh') + '</button>',
            '<button class="cbt-supervisor-button" type="button" data-action="logout-supervisor">Logout</button>',
            '</div>',
            '</div>',
            renderFilterForm(),
            renderTabs(),
            '<div class="cbt-supervisor-tab-panel">' + renderActivePanel() + '</div>',
            '</section>',
            '</div>',
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
        state.filters.studentKeyword = '';
        state.filters.status = '';
        state.filters.rosterPage = 1;
        state.filters.attemptsPage = 1;
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
