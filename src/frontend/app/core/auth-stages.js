export function createAuthStageManager(deps) {
    var recordTimeline = deps.recordTimeline;
    var state = deps.state;
    var clearMessages = deps.clearMessages;
    var escapeHtml = deps.escapeHtml;
    var formatDateTime = deps.formatDateTime;
    var formatDateTimeCompact = deps.formatDateTimeCompact;
    var formatScoreValue = deps.formatScoreValue;
    var getConfiguredPluginAuthor = deps.getConfiguredPluginAuthor;
    var getConfiguredPluginVersion = deps.getConfiguredPluginVersion;
    var getConfiguredSchoolLogoUrl = deps.getConfiguredSchoolLogoUrl;
    var getConfiguredSchoolMotto = deps.getConfiguredSchoolMotto;
    var getConfiguredSchoolName = deps.getConfiguredSchoolName;
    var getCurrentUserName = deps.getCurrentUserName;
    var getCurrentUserPhoto = deps.getCurrentUserPhoto;
    var getLoginHeroSchoolBranding = deps.getLoginHeroSchoolBranding;
    var getSelectedExam = deps.getSelectedExam;
    var getUserInitial = deps.getUserInitial;
    var persistAuthSession = deps.persistAuthSession;
    var render = deps.render;
    var renderAlert = deps.renderAlert;

    function renderConfirmStatusPill(label, tone) {
        var classes = ['cbt-confirm-pill'];
        if (tone) {
            classes.push(tone);
        }

        return '<span class="' + classes.join(' ') + '">' + escapeHtml(label || '-') + '</span>';
    }

    function renderRefreshButton(disabled, extraClass) {
        var classes = ['cbt-button', 'cbt-button-secondary', 'cbt-button-refresh'];
        var label = disabled ? 'MENYEGARKAN...' : 'REFRESH';

        if (extraClass) {
            classes.push(extraClass);
        }

        return [
            '<button class="' + classes.join(' ') + '" data-action="reload-exams" type="button"' + (disabled ? ' disabled' : '') + '>',
            '<span class="cbt-button-refresh-icon" aria-hidden="true">',
            '<svg viewBox="0 0 24 24" focusable="false">',
            '<path d="M20 11a8 8 0 1 0 2 5.3"></path>',
            '<path d="M20 4v7h-7"></path>',
            '</svg>',
            '</span>',
            escapeHtml(label),
            '</button>'
        ].join('');
    }

    function renderConfirmRefreshProgressCard() {
        if (!state.busy) {
            return '';
        }

        return [
            '<div class="cbt-finish-live-card">',
            '<div class="cbt-finish-live-head">',
            '<span class="cbt-finish-live-spinner" aria-hidden="true"></span>',
            '<div class="cbt-finish-live-copy">',
            '<strong>Menyegarkan ujian</strong>',
            '<span>Status terbaru sedang dicek.</span>',
            '</div>',
            '</div>',
            '<div class="cbt-finish-live-progress cbt-confirm-refresh-progress" aria-label="Progress refresh ujian">',
            '<span class="cbt-finish-live-progress-fill"></span>',
            '</div>',
            '</div>'
        ].join('');
    }

    function formatExamStatusBadgeLabel(status) {
        var normalized = String(status || '').replace(/[_-]+/g, ' ').trim().toLowerCase();
        if (normalized === '') {
            return '-';
        }

        return normalized.toUpperCase();
    }

    function renderExamCardChip(label, iconName, tone) {
        var classes = ['cbt-exam-card-chip'];
        if (tone) {
            classes.push(tone);
        }

        var iconMarkup = '';
        if (iconName === 'calendar') {
            iconMarkup = '<svg viewBox="0 0 24 24" focusable="false"><path d="M7 3v3"></path><path d="M17 3v3"></path><rect x="4" y="5.5" width="16" height="14" rx="2"></rect><path d="M4 9.5h16"></path></svg>';
        } else if (iconName === 'clock') {
            iconMarkup = '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4l2.5 2"></path></svg>';
        } else if (iconName === 'access') {
            iconMarkup = '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8"></circle><path d="M9 12.5l2 2 4-5"></path></svg>';
        } else {
            iconMarkup = '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg>';
        }

        return [
            '<span class="' + classes.join(' ') + '">',
            '<span class="cbt-exam-card-chip-icon" aria-hidden="true">' + iconMarkup + '</span>',
            '<span class="cbt-exam-card-chip-label">' + escapeHtml(label || '-') + '</span>',
            '</span>'
        ].join('');
    }

    function formatExamPickerOptionLabel(exam) {
        var examId = Number(exam && exam.id) || 0;
        var title = String(exam && exam.title ? exam.title : '').trim();
        var subject = String(exam && exam.subject_name ? exam.subject_name : '').trim();
        var titleNormalized = title.toLowerCase();
        var subjectNormalized = subject.toLowerCase();
        var label = title;

        if (label === '') {
            label = subject !== '' ? subject : ('Ujian #' + String(examId || '-'));
        } else if (subject !== '' && subjectNormalized !== '' && titleNormalized.indexOf(subjectNormalized) === -1) {
            var combinedLabel = title + ' - ' + subject;
            label = combinedLabel.length <= 44 ? combinedLabel : title;
        }

        if (label.length > 44) {
            label = label.slice(0, 41).trim() + '...';
        }

        return label;
    }

    function canShowStudentResult(exam) {
        return Number(exam && exam.show_student_result !== undefined ? exam.show_student_result : 1) === 1;
    }

    function isExamLatestAttemptFinalizing(exam) {
        return Number(exam && exam.latest_attempt_finalize_pending) === 1;
    }

    var EXAM_LIST_FILTERS = [
        { key: 'all', label: 'Semua' },
        { key: 'active', label: 'Aktif' },
        { key: 'upcoming', label: 'Akan Datang' },
        { key: 'completed', label: 'Selesai' }
    ];

    function normalizeExamListFilter(value) {
        var filter = String(value || 'all').toLowerCase();
        for (var index = 0; index < EXAM_LIST_FILTERS.length; index++) {
            if (EXAM_LIST_FILTERS[index].key === filter) {
                return filter;
            }
        }

        return 'all';
    }

    function isExamCompletedFilterMatch(exam) {
        var latestAttemptStatus = String(exam && exam.latest_attempt_status ? exam.latest_attempt_status : '').toLowerCase();
        var availabilityReason = String(exam && exam.availability_reason ? exam.availability_reason : '').toLowerCase();
        return latestAttemptStatus === 'completed'
            || isExamLatestAttemptFinalizing(exam)
            || availabilityReason === 'ended';
    }

    function isExamListFilterMatch(exam, filter) {
        var normalizedFilter = normalizeExamListFilter(filter);
        if (normalizedFilter === 'all') {
            return true;
        }

        var latestAttemptStatus = String(exam && exam.latest_attempt_status ? exam.latest_attempt_status : '').toLowerCase();
        var availabilityReason = String(exam && exam.availability_reason ? exam.availability_reason : '').toLowerCase();
        var availableNow = Number(exam && exam.is_available_now ? exam.is_available_now : 0) === 1;
        var completedMatch = isExamCompletedFilterMatch(exam);

        if (normalizedFilter === 'completed') {
            return completedMatch;
        }
        if (completedMatch) {
            return false;
        }
        if (normalizedFilter === 'active') {
            return latestAttemptStatus === 'in_progress' || availableNow;
        }
        if (normalizedFilter === 'upcoming') {
            return availabilityReason === 'not_started';
        }

        return false;
    }

    function getFilteredExams(filter) {
        var normalizedFilter = normalizeExamListFilter(filter);
        return state.exams.filter(function (exam) {
            return isExamListFilterMatch(exam, normalizedFilter);
        });
    }

    function getExamListFilterCounts() {
        var counts = {
            all: state.exams.length,
            active: 0,
            upcoming: 0,
            completed: 0
        };

        state.exams.forEach(function (exam) {
            if (isExamListFilterMatch(exam, 'active')) {
                counts.active += 1;
            }
            if (isExamListFilterMatch(exam, 'upcoming')) {
                counts.upcoming += 1;
            }
            if (isExamListFilterMatch(exam, 'completed')) {
                counts.completed += 1;
            }
        });

        return counts;
    }

    function getExamListFilterLabel(filter) {
        var normalizedFilter = normalizeExamListFilter(filter);
        for (var index = 0; index < EXAM_LIST_FILTERS.length; index++) {
            if (EXAM_LIST_FILTERS[index].key === normalizedFilter) {
                return EXAM_LIST_FILTERS[index].label;
            }
        }

        return 'Semua';
    }

    function renderExamListFilterControl(activeFilter, counts) {
        return [
            '<div class="cbt-exam-filter" role="tablist" aria-label="Filter ujian">',
            EXAM_LIST_FILTERS.map(function (filter) {
                var isActive = filter.key === activeFilter;
                var classes = ['cbt-exam-filter-button'];
                if (isActive) {
                    classes.push('is-active');
                }

                return [
                    '<button class="' + classes.join(' ') + '" data-action="set-exam-filter" data-filter="' + escapeHtml(filter.key) + '" type="button" role="tab" aria-selected="' + (isActive ? 'true' : 'false') + '" aria-pressed="' + (isActive ? 'true' : 'false') + '"' + (state.busy ? ' disabled' : '') + '>',
                    '<span>' + escapeHtml(filter.label) + '</span>',
                    '<strong>' + escapeHtml(String(counts[filter.key] || 0)) + '</strong>',
                    '</button>'
                ].join('');
            }).join(''),
            '</div>'
        ].join('');
    }

    function renderExamListEmptyState(filter) {
        return [
            '<div class="cbt-exam-list-empty">',
            '<strong>Tidak ada ujian pada filter ' + escapeHtml(getExamListFilterLabel(filter)) + '.</strong>',
            '<span>Gunakan filter Semua untuk melihat daftar lengkap.</span>',
            '</div>'
        ].join('');
    }

    function renderExamPickerMobileOption(exam) {
        var optionId = Number(exam && exam.id) || 0;
        var isActive = optionId === Number(state.selectedExamId);
        var durationMinutes = Number(exam && exam.duration_minutes) || 0;
        var startsAtLabel = formatDateTimeCompact(exam && exam.starts_at ? exam.starts_at : '');
        var latestAttemptStatus = String(exam && exam.latest_attempt_status ? exam.latest_attempt_status : '').toLowerCase();
        var latestAttemptFinalizing = isExamLatestAttemptFinalizing(exam);
        var availableNow = Number(exam && exam.is_available_now ? exam.is_available_now : 0) === 1;
        var classAllowed = Number(exam && exam.is_class_allowed ? exam.is_class_allowed : 0) === 1;
        var withinSchedule = Number(exam && exam.is_within_schedule ? exam.is_within_schedule : 0) === 1;
        var availabilityReason = String(exam && exam.availability_reason ? exam.availability_reason : '');
        var availabilityLabel = 'SIAP';
        var availabilityTone = 'is-ready';
        var classes = ['cbt-exam-picker-option'];

        if (isActive) {
            classes.push('is-active');
        }

        if (latestAttemptFinalizing) {
            availabilityLabel = 'DIPROSES';
            availabilityTone = 'is-warn';
        } else if (latestAttemptStatus === 'completed') {
            availabilityLabel = 'SELESAI';
            availabilityTone = 'is-completed';
        } else if (latestAttemptStatus === 'in_progress') {
            availabilityLabel = 'LANJUTKAN';
            availabilityTone = 'is-progress';
        } else if (!availableNow) {
            availabilityTone = 'is-warn';
            if (!classAllowed) {
                availabilityLabel = 'KELAS';
            } else if (!withinSchedule) {
                if (availabilityReason === 'not_started') {
                    availabilityLabel = 'BELUM MULAI';
                } else if (availabilityReason === 'ended') {
                    availabilityLabel = 'BERAKHIR';
                } else {
                    availabilityLabel = 'JADWAL';
                }
            } else {
                availabilityLabel = 'TUTUP';
            }
        }

        return [
            '<button type="button" class="' + classes.join(' ') + '" data-action="select-exam-mobile" data-id="' + escapeHtml(optionId) + '" role="option" aria-selected="' + (isActive ? 'true' : 'false') + '"' + (state.busy ? ' disabled' : '') + '>',
            '<span class="cbt-exam-picker-option-main">',
            '<span class="cbt-exam-picker-option-title">' + escapeHtml(formatExamPickerOptionLabel(exam)) + '</span>',
            '<span class="cbt-exam-picker-option-meta">',
            '<span class="cbt-exam-picker-option-chip">' + escapeHtml(startsAtLabel) + '</span>',
            '<span class="cbt-exam-picker-option-chip">' + escapeHtml(String(durationMinutes) + ' menit') + '</span>',
            '<span class="cbt-exam-picker-option-chip ' + availabilityTone + '">' + escapeHtml(availabilityLabel) + '</span>',
            '</span>',
            '</span>',
            '<span class="cbt-exam-picker-option-indicator" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M20 7 10 17l-5-5"></path></svg></span>',
            '</button>'
        ].join('');
    }

    function updateSelectedExam(examId) {
        var normalizedExamId = Number(examId) || 0;
        if (normalizedExamId <= 0) {
            return;
        }

        state.examPickerMobileOpen = false;
        state.selectedExamId = normalizedExamId;
        state.examToken = '';
        clearMessages();
        persistAuthSession();
        if (typeof recordTimeline === 'function') {
            recordTimeline('exam:selected', 'Exam dipilih dari daftar.', {
                attemptId: Number(state.attemptId) || 0,
                selectedExamId: normalizedExamId,
                stage: String(state.stage || '')
            });
        }
        render();
    }

    function updateExamListFilter(filter) {
        var normalizedFilter = normalizeExamListFilter(filter);
        var filteredExams = getFilteredExams(normalizedFilter);
        var selectedInFilter = filteredExams.some(function (exam) {
            return Number(exam && exam.id) === Number(state.selectedExamId);
        });

        state.examListFilter = normalizedFilter;
        state.examPickerMobileOpen = false;

        if (!selectedInFilter && filteredExams.length > 0) {
            state.selectedExamId = Number(filteredExams[0] && filteredExams[0].id) || 0;
            state.examToken = '';
        }

        persistAuthSession();
        if (typeof recordTimeline === 'function') {
            recordTimeline('exam:filter', 'Filter daftar ujian diganti.', {
                filter: normalizedFilter,
                filteredCount: filteredExams.length,
                selectedExamId: Number(state.selectedExamId) || 0,
                stage: String(state.stage || '')
            });
        }
        render();
    }

    function renderLoginStage() {
        var schoolNameRaw = getConfiguredSchoolName();
        var schoolName = escapeHtml(schoolNameRaw);
        var schoolBranding = getLoginHeroSchoolBranding(schoolNameRaw);
        var schoolBrandTag = schoolBranding.tag ? escapeHtml(schoolBranding.tag) : '';
        var schoolBrandTitle = escapeHtml(schoolNameRaw || schoolBranding.title || 'CBT Exam');
        var loginPanelTitle = schoolNameRaw !== ''
            ? escapeHtml(schoolNameRaw)
            : 'Masuk ke CBT';
        var schoolMottoRaw = getConfiguredSchoolMotto();
        var schoolMotto = schoolMottoRaw !== '' ? escapeHtml(schoolMottoRaw) : '';
        var heroMottoBlock = schoolMotto !== ''
            ? '<div class="cbt-login-hero-motto-row"><span class="cbt-login-hero-motto-line" aria-hidden="true"></span><p class="cbt-login-hero-motto">' + schoolMotto + '</p></div>'
            : '';
        var heroDescription = schoolMotto === ''
            ? 'Masuk dengan akun resmi, pilih ujian yang aktif, lalu kerjakan dengan autosave dan timer yang sinkron dari server.'
            : '';
        var schoolLogoUrl = getConfiguredSchoolLogoUrl();
        var heroLogoBlock = schoolLogoUrl !== ''
            ? '<div class="cbt-login-hero-logo-wrap"><img class="cbt-login-hero-logo" src="' + escapeHtml(schoolLogoUrl) + '" alt="' + schoolName + '" loading="lazy" decoding="async" /></div>'
            : '<div class="cbt-login-hero-logo-wrap is-fallback" aria-hidden="true"><span class="cbt-login-hero-logo-fallback"><svg viewBox="0 0 64 64" focusable="false"><path d="M12 26 32 14l20 12" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path><path d="M18 26v22h28V26" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"></path><path d="M26 48V36h12v12" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"></path><path d="M24 32h16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg></span></div>';
        var mobilePanelLogoBlock = schoolLogoUrl !== ''
            ? '<div class="cbt-login-panel-brand-mobile"><img class="cbt-login-panel-brand-mobile-logo" src="' + escapeHtml(schoolLogoUrl) + '" alt="' + schoolName + '" loading="lazy" decoding="async" /></div>'
            : '';
        var passwordType = state.loginPasswordVisible ? 'text' : 'password';
        var limitRemaining = Number(state.loginRateLimitRemaining) || 0;
        var loginButtonClass = state.busy || limitRemaining > 0 ? 'cbt-button cbt-button-primary cbt-button-login is-loading' : 'cbt-button cbt-button-primary cbt-button-login';
        var loginButtonLabel = limitRemaining > 0 
            ? 'Coba lagi dalam 0' + Math.floor(limitRemaining / 60) + ':' + ('0' + (limitRemaining % 60)).slice(-2) + '...'
            : (state.busy ? 'Memverifikasi...' : 'LOGIN');
        var isSubmitDisabled = state.busy || limitRemaining > 0;
        var togglePasswordLabel = state.loginPasswordVisible ? 'Sembunyikan' : 'Tampilkan';
        var pluginAuthorRaw = getConfiguredPluginAuthor();
        var pluginVersionRaw = getConfiguredPluginVersion();
        var loginMetaItems = [];

        if (pluginAuthorRaw !== '') {
            loginMetaItems.push('<span class="cbt-login-meta-item cbt-login-meta-copy">&copy; ' + escapeHtml(pluginAuthorRaw) + '</span>');
        }
        if (pluginVersionRaw !== '') {
            loginMetaItems.push('<span class="cbt-login-meta-item cbt-login-meta-version">Versi ' + escapeHtml(pluginVersionRaw) + '</span>');
        }

        var loginMetaBlock = loginMetaItems.length
            ? '<div class="cbt-login-meta" aria-label="Informasi sistem">' + loginMetaItems.join('') + '</div>'
            : '';

        return [
            '<section class="cbt-login-shell">',
            '<div class="cbt-login-hero">',
            '<div class="cbt-login-hero-heading">',
            heroLogoBlock,
            '<div class="cbt-login-hero-title">',
            schoolBrandTag !== '' ? '<span class="cbt-login-hero-school-tag">' + schoolBrandTag + '</span>' : '',
            '<h1>' + schoolBrandTitle + '</h1>',
            heroMottoBlock,
            '</div>',
            '</div>',
            heroDescription !== '' ? '<p class="cbt-login-description">' + heroDescription + '</p>' : '',
            '<p class="cbt-login-flow-label">Alur masuk ujian</p>',
            '<div class="cbt-login-steps" aria-label="Alur masuk ke ujian CBT">',
            '<article class="cbt-login-flow-card is-login"><div class="cbt-login-flow-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" focusable="false"><path d="M14 6h2a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M10 8l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg></div><div class="cbt-login-flow-content"><div class="cbt-login-flow-title-row"><span class="cbt-login-flow-number">01</span><strong class="cbt-login-flow-title">Masuk dengan akun resmi</strong></div><p class="cbt-login-flow-desc">Masuk memakai email, username, atau NISN yang terdaftar.</p><div class="cbt-login-flow-tags"><div class="cbt-login-flow-tag">Email / Username / NISN</div><div class="cbt-login-flow-tag">1 akun = 1 sesi</div></div></div></article>',
            '<article class="cbt-login-flow-card is-verify"><div class="cbt-login-flow-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" focusable="false"><rect x="6" y="4.5" width="12" height="15" rx="2.5" stroke="currentColor" stroke-width="1.8"></rect><path d="M9.2 4.5h5.6v2.2H9.2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"></path><path d="m9.5 12.3 1.7 1.7 3.4-3.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg></div><div class="cbt-login-flow-content"><div class="cbt-login-flow-title-row"><span class="cbt-login-flow-number">02</span><strong class="cbt-login-flow-title">Pilih ujian dan verifikasi token</strong></div><p class="cbt-login-flow-desc">Pilih ujian, lalu isi token hanya jika memang diwajibkan.</p><div class="cbt-login-flow-tags"><div class="cbt-login-flow-tag">Token global / per ujian</div><div class="cbt-login-flow-tag">Sesi aktif bisa dilanjutkan</div></div></div></article>',
            '<article class="cbt-login-flow-card is-submit"><div class="cbt-login-flow-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" focusable="false"><path d="M20 4 10 14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="m20 4-6 16-4-6-6-4 16-6Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg></div><div class="cbt-login-flow-content"><div class="cbt-login-flow-title-row"><span class="cbt-login-flow-number">03</span><strong class="cbt-login-flow-title">Kerjakan, review, lalu kumpulkan</strong></div><p class="cbt-login-flow-desc">Jawaban autosave aktif; review sebentar lalu kumpulkan.</p><div class="cbt-login-flow-tags"><div class="cbt-login-flow-tag">Autosave aktif</div><div class="cbt-login-flow-tag">Timer sinkron server</div></div></div></article>',
            '</div>',
            '</div>',
            '<div class="cbt-login-panel">',
            '<h3 class="cbt-login-panel-title">' + loginPanelTitle + '</h3>',
            mobilePanelLogoBlock,
            '<form id="cbt-login-form" class="cbt-form-grid">',
            '<div class="cbt-field"><label for="cbt-identifier">EMAIL / USERNAME / NISN</label><input id="cbt-identifier" class="cbt-input" name="identifier" autocomplete="username" maxlength="191" value="' + escapeHtml(state.loginIdentifier) + '" placeholder="Contoh: 231045 atau siswa@smkn1tpd.sch.id" required /></div>',
            '<div class="cbt-field"><label for="cbt-password">PASSWORD</label><div class="cbt-password-field"><input id="cbt-password" class="cbt-input" name="password" type="' + passwordType + '" autocomplete="current-password" maxlength="1024" value="' + escapeHtml(state.loginPassword) + '" placeholder="Masukkan password akun" required /><button class="cbt-password-toggle' + (state.loginPasswordVisible ? ' is-visible' : '') + '" data-action="toggle-password" type="button" aria-label="' + togglePasswordLabel + '" title="' + togglePasswordLabel + '"' + (state.busy ? ' disabled' : '') + '><span class="cbt-password-toggle-icon" aria-hidden="true"><span class="cbt-password-toggle-icon-eye"><svg viewBox="0 0 24 24" focusable="false"><path d="M1.5 12S5.5 5.5 12 5.5 22.5 12 22.5 12 18.5 18.5 12 18.5 1.5 12 1.5 12Z"></path><circle cx="12" cy="12" r="3.2"></circle></svg></span><span class="cbt-password-toggle-icon-eye-off"><svg viewBox="0 0 24 24" focusable="false"><path d="M3.2 3.2 20.8 20.8"></path><path d="M9.9 5.9A12.2 12.2 0 0 1 12 5.5c6.5 0 10.5 6.5 10.5 6.5a18.9 18.9 0 0 1-3.4 4.2"></path><path d="M6.4 8A18.3 18.3 0 0 0 1.5 12s4 6.5 10.5 6.5a11.6 11.6 0 0 0 4-.7"></path><path d="M14.3 14.3A3.2 3.2 0 0 1 9.7 9.7"></path></svg></span></span><span class="cbt-password-toggle-label">' + escapeHtml(togglePasswordLabel) + '</span></button></div></div>',
            '<div class="cbt-actions"><button class="' + loginButtonClass + '" type="submit"' + (isSubmitDisabled ? ' disabled' : '') + '><span class="cbt-button-spinner" aria-hidden="true"></span><span>' + loginButtonLabel + '</span></button></div>',
            '</form>',
            renderAlert(),
            '<p class="cbt-login-help">Jika gagal login, hubungi admin sekolah atau pengawas ujian.</p>',
            loginMetaBlock,
            '</div>',
            '</section>'
        ].join('');
    }

    function formatDiagnosticUptime(seconds) {
        var uptimeSeconds = Number(seconds);
        if (!Number.isFinite(uptimeSeconds) || uptimeSeconds <= 0) {
            return '-';
        }

        uptimeSeconds = Math.floor(uptimeSeconds);
        var days = Math.floor(uptimeSeconds / 86400);
        var hours = Math.floor((uptimeSeconds % 86400) / 3600);
        var minutes = Math.floor((uptimeSeconds % 3600) / 60);

        var result = [];
        if (days > 0) {
            result.push(days + ' hari');
        }
        if (hours > 0) {
            result.push(hours + ' jam');
        }
        if (minutes > 0 || (days === 0 && hours === 0)) {
            result.push(minutes + ' menit');
        }

        return result.join(', ');
    }

    function formatDiagnosticMaxMemory(bytes) {
        var maxBytes = Number(bytes);
        if (!Number.isFinite(maxBytes) || maxBytes <= 0) {
            return 'Unlimited';
        }
        return formatDiagnosticBytes(maxBytes);
    }

    function formatDiagnosticTtl(seconds) {
        var ttlSeconds = Number(seconds);
        if (!Number.isFinite(ttlSeconds)) {
            return '-';
        }

        ttlSeconds = Math.floor(ttlSeconds);
        if (ttlSeconds === -2) {
            return 'missing';
        }
        if (ttlSeconds === -1) {
            return 'no expiry';
        }
        if (ttlSeconds <= 0) {
            return 'expired';
        }

        var hours = Math.floor(ttlSeconds / 3600);
        var minutes = Math.floor((ttlSeconds % 3600) / 60);
        var secondsLeft = ttlSeconds % 60;
        if (hours > 0) {
            return String(hours) + 'j ' + String(minutes) + 'm';
        }
        if (minutes > 0) {
            return String(minutes) + ' menit ' + String(secondsLeft) + ' detik';
        }

        return String(secondsLeft) + ' detik';
    }

    function formatDiagnosticBytes(bytes, compact) {
        var byteCount = Number(bytes);
        if (!Number.isFinite(byteCount) || byteCount < 0) {
            byteCount = 0;
        }
        byteCount = Math.round(byteCount);

        if (byteCount >= 1024 * 1024) {
            var mb = (byteCount / (1024 * 1024)).toFixed(2) + ' MB';
            return compact ? mb : String(byteCount) + ' bytes (' + mb + ')';
        }
        if (byteCount >= 1024) {
            var kb = (byteCount / 1024).toFixed(2) + ' KB';
            return compact ? kb : String(byteCount) + ' bytes (' + kb + ')';
        }

        return String(byteCount) + ' bytes';
    }

    function formatDiagnosticBool(value) {
        if (value === true || value === 1 || value === '1' || String(value).toLowerCase() === 'true') {
            return 'YA';
        }
        return 'TIDAK';
    }

    function truncateDiagnosticValue(value, head, tail) {
        var text = String(value === null || value === undefined ? '' : value);
        var headLength = Math.max(1, Number(head) || 14);
        var tailLength = Math.max(1, Number(tail) || 8);
        if (text.length <= headLength + tailLength + 3) {
            return text;
        }

        return text.slice(0, headLength) + '...' + text.slice(text.length - tailLength);
    }

    function readDiagnosticObject(diagnosticResult) {
        return diagnosticResult && diagnosticResult.diagnostics && typeof diagnosticResult.diagnostics === 'object'
            ? diagnosticResult.diagnostics
            : {};
    }

    function diagnosticText(value, fallback) {
        if (value === null || value === undefined || value === '') {
            return fallback !== undefined ? String(fallback) : '-';
        }

        return String(value);
    }

    function formatDiagnosticStorageShape(value) {
        var shape = String(value || '').trim();
        if (shape === 'start_per_question_v2') {
            return 'v2 fragmented';
        }
        if (shape === 'legacy_monolith') {
            return 'legacy monolith';
        }
        if (shape === 'none' || shape === '') {
            return 'none';
        }

        return shape.replace(/_/g, ' ');
    }

    function renderDiagnosticRow(label, value, title) {
        var fullTitle = title !== undefined ? String(title || '') : String(value || '');
        var titleAttr = fullTitle !== '' ? ' title="' + escapeHtml(fullTitle) + '"' : '';

        return [
            '<div class="cbt-admin-diagnostic-row" style="min-width: 0;">',
            '<span style="display: block; color: #475569; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;">' + escapeHtml(label) + '</span>',
            '<strong' + titleAttr + ' style="display: block; margin-top: 2px; color: #0f172a; font-size: 12px; overflow-wrap: anywhere;">' + escapeHtml(value) + '</strong>',
            '</div>'
        ].join('');
    }

    function renderDiagnosticSection(title, rows) {
        return [
            '<section class="cbt-admin-diagnostic-section" style="border-top: 1px solid rgba(88,80,236,.18); padding-top: 12px;">',
            '<h5 style="margin: 0 0 8px 0; color: #334155; font-size: 12px; text-transform: uppercase;">' + escapeHtml(title) + '</h5>',
            '<div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 12px;">',
            rows.join(''),
            '</div>',
            '</section>'
        ].join('');
    }

    function renderAdminDiagnosticPanel(hasSelectedExam, selectedExam) {
        var userRole = String(state.user && state.user.role ? state.user.role : '').toLowerCase();
        if (userRole !== 'administrator' && userRole !== 'admin') {
            return '';
        }

        var panelContent = '';
        if (!hasSelectedExam) {
            panelContent = '<p class="cbt-muted">Pilih ujian untuk melihat diagnostik cache Redis.</p>';
        } else {
            var diagnosticResult = state.adminDiagnosticResult;
            if (state.adminDiagnosticBusy) {
                panelContent = [
                    '<div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px 16px; text-align: center;">',
                    '  <span class="cbt-finish-live-spinner" style="width: 28px; height: 28px; margin-bottom: 12px; border-width: 3px;" aria-hidden="true"></span>',
                    '  <strong style="display: block; font-size: 13.5px; color: #4f46e5; margin-bottom: 4px;">Memindai Infrastruktur Redis...</strong>',
                    '  <p class="cbt-muted" style="margin: 0; font-size: 12px; line-height: 1.4;">Menghubungi engine memori server dan memverifikasi integritas data cache ujian...</p>',
                    '</div>'
                ].join('');
            } else if (diagnosticResult && Number(diagnosticResult.exam_id) === Number(selectedExam.id)) {
                var diagnostics = readDiagnosticObject(diagnosticResult);
                var revisionMeta = diagnostics.revision_meta && typeof diagnostics.revision_meta === 'object'
                    ? diagnostics.revision_meta
                    : {};
                var redisStatus = diagnosticText(diagnosticResult.redis_status, 'unknown');
                var isConnected = redisStatus === 'connected';
                var isPingSuccess = diagnosticResult.ping_success;
                var snapshotStatus = String(diagnosticResult.snapshot_status || 'unknown');
                var latency = Number(diagnosticResult.latency_ms) || 0;
                var itemCount = Number(diagnosticResult.item_count) || 0;
                var snapshotMessage = diagnosticText(diagnosticResult.snapshot_message || diagnostics.snapshot_message, '');
                var repairMessage = diagnosticText(diagnostics.repair_message, '');
                var warmupError = diagnosticText(diagnosticResult.warmup_error, '');
                var storageShape = diagnosticText(diagnostics.storage_shape, 'none');
                var storageShapeLabel = formatDiagnosticStorageShape(storageShape);
                var ttlSeconds = diagnostics.snapshot_ttl_seconds !== undefined
                    ? diagnostics.snapshot_ttl_seconds
                    : diagnosticResult.ttl_seconds;
                var payloadBytes = diagnostics.snapshot_payload_bytes !== undefined
                    ? diagnostics.snapshot_payload_bytes
                    : diagnosticResult.payload_bytes;
                var ttlLabel = formatDiagnosticTtl(ttlSeconds);
                var payloadLabel = formatDiagnosticBytes(payloadBytes);
                var payloadCompact = formatDiagnosticBytes(payloadBytes, true);
                var snapshotMissLabel = diagnosticText(diagnosticResult.snapshot_miss_reason_label || diagnostics.snapshot_miss_reason_label, '');
                var snapshotMissReason = diagnosticText(diagnosticResult.snapshot_miss_reason || diagnostics.snapshot_miss_reason, '');
                var storageKey = diagnosticText(diagnostics.storage_key, '');
                var revisionSignature = diagnosticText(revisionMeta.signature, '');

                var readinessPercentage = 0;
                var readinessLabel = '';
                var readinessDesc = '';
                var readinessGradient = '';
                var readinessIcon = '';
                var loadCapacityText = '';
                var capacityColor = '';

                if (isConnected && isPingSuccess) {
                    if (snapshotStatus === 'ready') {
                        readinessPercentage = 100;
                        readinessLabel = '100% SIAP DIGUNAKAN';
                        readinessDesc = 'Soal ujian telah disalin ke RAM. Kecepatan akses siswa maksimal!';
                        readinessGradient = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                        readinessIcon = '🚀';
                        loadCapacityText = '🟢 Sangat Siap melayani > 1.000 siswa masuk bersamaan secara instan.';
                        capacityColor = '#0e9f6e';
                    } else {
                        readinessPercentage = 50;
                        readinessLabel = '50% SIAP (PERLU WARMUP)';
                        readinessDesc = 'Koneksi Redis aktif, tetapi cache soal belum dibuat. Klik Warmup di bawah.';
                        readinessGradient = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
                        readinessIcon = '⚠️';
                        loadCapacityText = '🟡 Direkomendasikan hanya untuk < 50 siswa. Harap klik "Uji Ulang / Warmup" terlebih dahulu agar database MySQL tidak overload.';
                        capacityColor = '#d97706';
                    }
                } else {
                    readinessPercentage = 0;
                    readinessLabel = '0% KONEKSI TERPUTUS';
                    readinessDesc = 'Redis mati/error. Ujian membebani database biasa langsung, berisiko lambat.';
                    readinessGradient = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                    readinessIcon = '❌';
                    loadCapacityText = '🔴 Hanya aman untuk < 20 siswa. Risiko database crash sangat tinggi jika diakses oleh lebih banyak siswa.';
                    capacityColor = '#c53030';
                }

                var checkExt = isConnected ? '✔' : '❌';
                var checkExtColor = isConnected ? '#0e9f6e' : '#c53030';
                var checkExtText = isConnected ? 'Driver PHP Redis Terdeteksi' : 'Driver PHP Redis Tidak Terdeteksi';

                var checkConn = (isConnected && isPingSuccess) ? '✔' : '❌';
                var checkConnColor = (isConnected && isPingSuccess) ? '#0e9f6e' : '#c53030';
                var checkConnText = (isConnected && isPingSuccess) ? 'Server Redis Terhubung (' + latency.toFixed(1) + ' ms)' : 'Koneksi Server Redis Gagal';

                var checkCache = (snapshotStatus === 'ready') ? '✔' : '○';
                var checkCacheColor = (snapshotStatus === 'ready') ? '#0e9f6e' : 'var(--cbt-muted)';
                var checkCacheText = (snapshotStatus === 'ready')
                    ? 'Data Ujian Tersimpan di RAM (' + itemCount + ' soal)'
                    : 'Cache Soal Ujian Kosong / Perlu Warmup';

                var pingStatusText = latency < 1.5 ? 'Instan' : (latency < 5 ? 'Sangat Cepat' : 'Normal');
                var pingStatusColor = latency < 5 ? '#0e9f6e' : '#f59e0b';

                var soalStatusText = snapshotStatus === 'ready' ? 'Lengkap (100%)' : 'Belum Ada';
                var soalStatusColor = snapshotStatus === 'ready' ? '#0e9f6e' : 'var(--cbt-muted)';

                var payloadStatusText = payloadBytes > 0 ? 'Optimal' : '-';
                var payloadStatusColor = payloadBytes > 0 ? '#0e9f6e' : 'var(--cbt-muted)';

                var ttlStatusText = ttlSeconds > 3600 ? 'Sangat Aman' : (ttlSeconds > 0 ? 'Cukup' : 'Kosong');
                var ttlStatusColor = ttlSeconds > 3600 ? '#0e9f6e' : (ttlSeconds > 0 ? '#f59e0b' : 'var(--cbt-muted)');

                var detailMessages = [];
                if (snapshotStatus !== 'ready') {
                    if (snapshotMissLabel !== '') {
                        detailMessages.push(snapshotMissLabel);
                    }
                    if (snapshotMissReason !== '') {
                        detailMessages.push('Reason: ' + snapshotMissReason);
                    }
                    if (snapshotMessage !== '') {
                        detailMessages.push(snapshotMessage);
                    }
                    if (repairMessage !== '') {
                        detailMessages.push(repairMessage);
                    }
                    if (warmupError !== '') {
                        detailMessages.push(warmupError);
                    }
                }

                var detailBlock = '';
                if (detailMessages.length > 0) {
                    detailBlock = [
                        '<div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; font-size: 12px; color: #b91c1c; line-height: 1.45;">',
                        '  <strong>Catatan Diagnostik:</strong> ' + escapeHtml(detailMessages.join(' | ')),
                        '</div>'
                    ].join('');
                }

                panelContent = [
                    '<!-- Readiness Score Card -->',
                    '<div style="background: ' + readinessGradient + '; color: #ffffff; border-radius: 10px; padding: 14px 16px; margin-bottom: 14px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 12px rgba(15, 76, 129, 0.05);">',
                    '  <div style="min-width: 0;">',
                    '    <div style="font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.9;">Tingkat Kesiapan Cache</div>',
                    '    <div style="font-size: 16px; font-weight: 900; margin-top: 4px; line-height: 1.2;">' + readinessLabel + '</div>',
                    '    <div style="font-size: 11.5px; margin-top: 6px; opacity: 0.85; line-height: 1.4;">' + readinessDesc + '</div>',
                    '  </div>',
                    '  <div style="font-size: 32px; font-weight: 900; opacity: 0.3; padding-left: 10px;">' + readinessIcon + '</div>',
                    '</div>',

                    '<!-- Checking Checklist -->',
                    '<div style="display: grid; gap: 8px; margin-bottom: 14px; background: var(--cbt-white); border: 1px solid var(--cbt-border); border-radius: 8px; padding: 12px 14px;">',
                    '  <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--cbt-text); font-weight: 600;">',
                    '    <span style="color: ' + checkExtColor + '; font-size: 13px; font-weight: 900;">' + checkExt + '</span> ' + checkExtText,
                    '  </div>',
                    '  <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--cbt-text); font-weight: 600;">',
                    '    <span style="color: ' + checkConnColor + '; font-size: 13px; font-weight: 900;">' + checkConn + '</span> ' + checkConnText,
                    '  </div>',
                    '  <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--cbt-text); font-weight: 600;">',
                    '    <span style="color: ' + checkCacheColor + '; font-size: 13px; font-weight: 900;">' + checkCache + '</span> ' + checkCacheText,
                    '  </div>',
                    '</div>',

                    detailBlock,

                    '<!-- Metrics Grid -->',
                    '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 14px;">',
                    '  <div style="background: var(--cbt-white); border: 1px solid var(--cbt-border); border-radius: 8px; padding: 10px 12px;">',
                    '    <span style="display: block; color: var(--cbt-muted); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Respon Ping</span>',
                    '    <strong style="display: block; font-size: 15px; color: var(--cbt-text); margin-top: 4px; display: flex; align-items: baseline; gap: 2px;">' + latency.toFixed(1) + ' <span style="font-size: 10px; font-weight: 500; color: var(--cbt-muted);">ms</span></strong>',
                    '    <span style="font-size: 10px; color: ' + pingStatusColor + '; font-weight: 600;">' + pingStatusText + '</span>',
                    '  </div>',
                    '  <div style="background: var(--cbt-white); border: 1px solid var(--cbt-border); border-radius: 8px; padding: 10px 12px;">',
                    '    <span style="display: block; color: var(--cbt-muted); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Soal Ter-cache</span>',
                    '    <strong style="display: block; font-size: 15px; color: var(--cbt-text); margin-top: 4px; display: flex; align-items: baseline; gap: 2px;">' + itemCount + ' <span style="font-size: 10px; font-weight: 500; color: var(--cbt-muted);">soal</span></strong>',
                    '    <span style="font-size: 10px; color: ' + soalStatusColor + '; font-weight: 600;">' + soalStatusText + '</span>',
                    '  </div>',
                    '  <div style="background: var(--cbt-white); border: 1px solid var(--cbt-border); border-radius: 8px; padding: 10px 12px;">',
                    '    <span style="display: block; color: var(--cbt-muted); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Ukuran Cache</span>',
                    '    <strong style="display: block; font-size: 15px; color: var(--cbt-text); margin-top: 4px;">' + payloadCompact + '</strong>',
                    '    <span style="font-size: 10px; color: ' + payloadStatusColor + '; font-weight: 600;">' + payloadStatusText + '</span>',
                    '  </div>',
                    '  <div style="background: var(--cbt-white); border: 1px solid var(--cbt-border); border-radius: 8px; padding: 10px 12px;">',
                    '    <span style="display: block; color: var(--cbt-muted); font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Masa Aktif (TTL)</span>',
                    '    <strong style="display: block; font-size: 15px; color: var(--cbt-text); margin-top: 4px;">' + ttlLabel + '</strong>',
                    '    <span style="font-size: 10px; color: ' + ttlStatusColor + '; font-weight: 600;">' + ttlStatusText + '</span>',
                    '  </div>',
                    '</div>',
                    
                    '<!-- Estimasi Kapasitas Beban -->',
                    '<div style="background: var(--cbt-white); border: 1px solid var(--cbt-border); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; font-size: 11.5px;">',
                    '  <span style="display: block; color: var(--cbt-muted); font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px;">Estimasi Kapasitas Beban Siswa</span>',
                    '  <strong style="color: ' + capacityColor + '; font-weight: 700; line-height: 1.4; display: block;">' + loadCapacityText + '</strong>',
                    '</div>',
                    
                    '<!-- Kotak Edukasi Ringkas -->',
                    '<div style="background: rgba(99, 102, 241, 0.04); border: 1px dashed rgba(99, 102, 241, 0.25); border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; font-size: 11px; line-height: 1.45; color: var(--cbt-text);">',
                    '  <strong style="display: block; color: #4f46e5; margin-bottom: 4px; font-size: 11.5px;">💡 Mengapa RAM Cache Sangat Penting?</strong>',
                    '  Mengambil soal dari database biasa (MySQL) membutuhkan waktu ±50ms. Dengan menyalin soal ke RAM (Redis), kecepatan respons naik drastis menjadi &lt; 1ms, memastikan server tidak overload saat siswa mulai masuk secara bersamaan.',
                    '</div>',

                    '<!-- Technical Details Accordion -->',
                    '<details style="margin-bottom: 14px; font-size: 12px; border: 1px solid var(--cbt-border); border-radius: 8px; padding: 10px 12px; background: var(--cbt-white);">',
                    '  <summary style="font-weight: 700; color: #4f46e5; cursor: pointer; outline: none; display: flex; align-items: center; gap: 6px;">',
                    '    Detail Parameter Teknis',
                    '  </summary>',
                    '  <div style="margin-top: 10px; display: grid; gap: 12px; border-top: 1px solid var(--cbt-border); padding-top: 10px;">',
                    '    ' + renderDiagnosticSection('Koneksi Redis', [
                              renderDiagnosticRow('Status', redisStatus.toUpperCase()),
                              renderDiagnosticRow('Host', diagnosticText(diagnostics.redis_host, '-')),
                              renderDiagnosticRow('Database', diagnosticText(diagnostics.redis_database, '-')),
                              renderDiagnosticRow('Error', diagnosticText(diagnostics.redis_error, '-'))
                          ]),
                    '    ' + renderDiagnosticSection('Engine & Memori Redis', [
                              renderDiagnosticRow('Versi Redis', diagnosticText(diagnostics.redis_version, '-')),
                              renderDiagnosticRow('Memori Digunakan', diagnostics.redis_used_memory ? formatDiagnosticBytes(diagnostics.redis_used_memory) : '-'),
                              renderDiagnosticRow('Puncak Memori', diagnostics.redis_used_memory_peak ? formatDiagnosticBytes(diagnostics.redis_used_memory_peak) : '-'),
                              renderDiagnosticRow('Batas Memori (Max)', diagnostics.redis_maxmemory ? formatDiagnosticMaxMemory(diagnostics.redis_maxmemory) : '-'),
                              renderDiagnosticRow('Rasio Fragmentasi', diagnostics.redis_mem_fragmentation_ratio ? Number(diagnostics.redis_mem_fragmentation_ratio).toFixed(2) : '-'),
                              renderDiagnosticRow('Klien Terhubung', diagnostics.redis_connected_clients !== undefined ? String(diagnostics.redis_connected_clients) : '-'),
                              renderDiagnosticRow('Uptime Sistem', diagnostics.redis_uptime_in_seconds ? formatDiagnosticUptime(diagnostics.redis_uptime_in_seconds) : '-'),
                              renderDiagnosticRow('Total Kunci (DB)', diagnostics.redis_db_keys !== undefined ? String(diagnostics.redis_db_keys) : '-')
                          ]),
                    '    ' + renderDiagnosticSection('Snapshot', [
                              renderDiagnosticRow('Status', snapshotStatus.toUpperCase()),
                              renderDiagnosticRow('Exists', formatDiagnosticBool(diagnostics.snapshot_exists)),
                              renderDiagnosticRow('Valid', formatDiagnosticBool(diagnostics.snapshot_valid)),
                              renderDiagnosticRow('Storage Shape', storageShapeLabel, storageShape),
                              renderDiagnosticRow('Storage Key', storageKey !== '' ? truncateDiagnosticValue(storageKey, 22, 12) : '-', storageKey),
                              renderDiagnosticRow('TTL', ttlLabel),
                              renderDiagnosticRow('Payload Size', payloadLabel),
                              renderDiagnosticRow('Jumlah Soal', String(itemCount))
                          ]),
                    '    ' + renderDiagnosticSection('V2 Fragment', [
                              renderDiagnosticRow('Index Status', diagnosticText(diagnostics.v2_index_status, '-')),
                              renderDiagnosticRow('Fragment Count', diagnosticText(diagnostics.v2_fragment_count, '0')),
                              renderDiagnosticRow('Missing Fragment', diagnosticText(diagnostics.v2_missing_fragment_count, '0')),
                              renderDiagnosticRow('Fallback Reason', diagnosticText(diagnostics.fallback_reason, '-'))
                          ]),
                    '    ' + renderDiagnosticSection('Revision & Repair', [
                              renderDiagnosticRow('Revision Version', diagnosticText(revisionMeta.version, '-')),
                              renderDiagnosticRow('Invalidated At', diagnosticText(revisionMeta.invalidated_at, '-')),
                              renderDiagnosticRow('Signature', revisionSignature !== '' ? truncateDiagnosticValue(revisionSignature, 16, 10) : '-', revisionSignature),
                              renderDiagnosticRow('Repair Status', diagnosticText(diagnostics.repair_status, '-')),
                              renderDiagnosticRow('Repair Message', repairMessage || '-'),
                              renderDiagnosticRow('Warmup', formatDiagnosticBool(diagnosticResult.warmup_attempted)),
                              renderDiagnosticRow('Warmup Error', warmupError || '-'),
                              renderDiagnosticRow('Snapshot Message', snapshotMessage || '-')
                          ]),
                    '  </div>',
                    '</details>',

                    '<button class="cbt-button cbt-button-secondary cbt-button-small" data-action="test-redis-cache" type="button" style="width: 100%; justify-content: center; padding: 6px 12px; font-size: 12px; font-weight: 700;">Uji Ulang / Warmup Cache</button>'
                ].join('');
            } else {
                panelContent = [
                    '<p style="margin: 0 0 14px 0; font-size: 12.5px; line-height: 1.5; color: var(--cbt-muted);">',
                    '  Gunakan fitur ini untuk memastikan semua soal ujian telah disalin ke memori RAM server agar akses siswa menjadi instan dan bebas lag.',
                    '</p>',
                    '<div style="display: grid; gap: 10px; margin-bottom: 16px; background: var(--cbt-white); border: 1px solid var(--cbt-border); border-radius: 8px; padding: 12px 14px;">',
                    '  <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--cbt-text); font-weight: 600;">',
                    '    <span style="color: var(--cbt-muted); font-size: 14px; font-weight: 400; line-height: 1;">○</span> Ekstensi PHP Redis (Menunggu pemindaian...)',
                    '  </div>',
                    '  <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--cbt-text); font-weight: 600;">',
                    '    <span style="color: var(--cbt-muted); font-size: 14px; font-weight: 400; line-height: 1;">○</span> Server Redis Connection (Menunggu pemindaian...)',
                    '  </div>',
                    '  <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--cbt-text); font-weight: 600;">',
                    '    <span style="color: var(--cbt-muted); font-size: 14px; font-weight: 400; line-height: 1;">○</span> Caching Soal Ujian di RAM (Menunggu pemindaian...)',
                    '  </div>',
                    '</div>',
                    '<button class="cbt-button cbt-button-primary cbt-button-small" data-action="test-redis-cache" type="button" style="width: 100%; justify-content: center; padding: 8px 12px; font-size: 12px; font-weight: 800; letter-spacing: .04em;">',
                    '  PINDAI & HANGATKAN CACHE',
                    '</button>'
                ].join('');
            }
        }

        return [
            '<div class="cbt-card cbt-admin-diagnostic-panel" style="margin-top: 20px; border: 1px solid rgba(99, 102, 241, 0.25); background: rgba(99, 102, 241, 0.05); border-radius: 12px; padding: 18px; color: var(--cbt-text); box-shadow: 0 4px 12px rgba(15, 76, 129, 0.03);">',
            '  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">',
            '    <h4 style="margin: 0; color: #4f46e5; display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 700;">',
            '      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
            '      Status Kesiapan Redis',
            '    </h4>',
            '    <div style="display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: ' + (hasSelectedExam && state.adminDiagnosticResult && Number(state.adminDiagnosticResult.exam_id) === Number(selectedExam.id) && state.adminDiagnosticResult.redis_status === 'connected' && state.adminDiagnosticResult.ping_success ? '#0e9f6e' : 'var(--cbt-muted)') + '; text-transform: uppercase;">',
            (hasSelectedExam && state.adminDiagnosticResult && Number(state.adminDiagnosticResult.exam_id) === Number(selectedExam.id) && state.adminDiagnosticResult.redis_status === 'connected' && state.adminDiagnosticResult.ping_success
                ? '      <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></span> Redis Online'
                : '      Redis Preflight'),
            '    </div>',
            '  </div>',
            panelContent,
            '</div>'
        ].join('');
    }

    function renderConfirmStage() {
        if (!state.exams.length) {
            return [
                '<section class="cbt-card">',
                '<h3>Belum Ada Exam Aktif</h3>',
                '<p class="cbt-subtitle">Akun ini belum memiliki ujian yang bisa dikerjakan atau dilihat saat ini.</p>',
                '<div class="cbt-actions">',
                renderRefreshButton(state.busy),
                '<button class="cbt-button cbt-button-danger" data-action="logout" type="button">Logout</button>',
                '</div>',
                renderAlert(),
                '</section>'
            ].join('');
        }

        var selectedExam = getSelectedExam();
        var userName = getCurrentUserName();
        var userPhoto = getCurrentUserPhoto();
        var userInitial = getUserInitial(userName);
        var hasSelectedExam = !!selectedExam;
        var selectedAttemptStatus = String(selectedExam && selectedExam.latest_attempt_status ? selectedExam.latest_attempt_status : '').toLowerCase();
        var selectedAttemptFinalizing = isExamLatestAttemptFinalizing(selectedExam);
        var selectedExamCompleted = selectedAttemptStatus === 'completed';
        var selectedExamShowsResult = canShowStudentResult(selectedExam);
        var selectedExamAttemptId = Number(selectedExam && selectedExam.latest_attempt_id ? selectedExam.latest_attempt_id : 0);
        var selectedExamRequiresToken = Number(selectedExam && selectedExam.requires_token ? selectedExam.requires_token : 0) === 1;
        var selectedExamAutoToken = Number(selectedExam && selectedExam.token_frontend_auto_apply ? selectedExam.token_frontend_auto_apply : 0) === 1;
        var selectedExamAutoTokenValue = String(selectedExam && selectedExam.token_auto_value ? selectedExam.token_auto_value : '').trim().toUpperCase();
        var tokenInputRequired = Number(
            selectedExam && selectedExam.token_input_required !== undefined
                ? selectedExam.token_input_required
                : (selectedExamRequiresToken ? 1 : 0)
        ) === 1;
        var tokenRefreshMinutes = Number(selectedExam && selectedExam.token_refresh_minutes ? selectedExam.token_refresh_minutes : 0);
        var tokenInfoText = hasSelectedExam
            ? (selectedExamCompleted
                ? (selectedExamShowsResult
                    ? 'Ujian ini sudah selesai. Anda bisa melihat hasil nilai dari attempt terakhir.'
                    : 'Ujian ini sudah selesai. Admin menyembunyikan nilai dan review, tetapi Anda masih bisa melihat status penyimpanan jawaban.')
                : (selectedExamRequiresToken
                    ? (
                        tokenInputRequired
                            ? ('Ujian ini membutuhkan token.' + (tokenRefreshMinutes > 0 ? (' Refresh setiap ' + tokenRefreshMinutes + ' menit.') : ''))
                            : ('Token ujian diisi otomatis oleh sistem.' + (tokenRefreshMinutes > 0 ? (' Refresh setiap ' + tokenRefreshMinutes + ' menit.') : ''))
                    )
                    : 'Ujian ini tidak membutuhkan token.'))
            : 'Pilih ujian terlebih dahulu dari daftar di kiri.';
        if (selectedAttemptFinalizing) {
            tokenInfoText = 'Hasil sedang diproses.';
        }
        var userUsername = String(state.user && state.user.username ? state.user.username : '-');
        var userClassCode = String(state.user && state.user.kode_kelas ? state.user.kode_kelas : '-');
        var userRoomCode = String(state.user && state.user.kode_ruang ? state.user.kode_ruang : '-');
        var selectedExamTitle = hasSelectedExam ? String(selectedExam.title || '-') : 'Belum ada ujian dipilih';
        var selectedExamSubject = hasSelectedExam ? String(selectedExam.subject_name || '-') : 'Pilih ujian dari daftar kiri';
        var selectedExamStartsAt = hasSelectedExam ? formatDateTime(selectedExam.starts_at) : '-';
        var selectedExamDurationMinutes = hasSelectedExam ? (Number(selectedExam.duration_minutes) || 0) : 0;
        var selectedExamDurationLabel = hasSelectedExam
            ? (selectedExamDurationMinutes > 0 ? (selectedExamDurationMinutes + ' menit') : 'Durasi belum diatur')
            : 'Menunggu pilihan';
        var selectedExamLatestPercentage = Number(selectedExam && selectedExam.latest_attempt_percentage);
        var selectedAccessLabel = 'Siap dikerjakan';
        var selectedAccessTone = 'is-ready';
        if (!hasSelectedExam) {
            selectedAccessLabel = 'Belum pilih ujian';
            selectedAccessTone = 'is-muted';
        } else if (selectedAttemptFinalizing) {
            selectedAccessLabel = 'Memproses hasil';
            selectedAccessTone = 'is-warn';
        } else if (selectedExamCompleted) {
            selectedAccessLabel = selectedExamShowsResult ? 'Hasil tersedia' : 'Status tersedia';
            selectedAccessTone = 'is-done';
        } else if (selectedAttemptStatus === 'in_progress') {
            selectedAccessLabel = 'Lanjutkan ujian';
            selectedAccessTone = 'is-ready';
        } else if (Number(selectedExam && selectedExam.is_available_now ? selectedExam.is_available_now : 0) !== 1) {
            selectedAccessTone = 'is-warn';
            if (Number(selectedExam && selectedExam.is_class_allowed ? selectedExam.is_class_allowed : 0) !== 1) {
                selectedAccessLabel = 'Kelas tidak sesuai';
            } else if (Number(selectedExam && selectedExam.is_within_schedule ? selectedExam.is_within_schedule : 0) !== 1) {
                var selectedAvailabilityReason = String(selectedExam && selectedExam.availability_reason ? selectedExam.availability_reason : '');
                if (selectedAvailabilityReason === 'not_started') {
                    selectedAccessLabel = 'Belum mulai';
                } else if (selectedAvailabilityReason === 'ended') {
                    selectedAccessLabel = 'Jadwal selesai';
                } else {
                    selectedAccessLabel = 'Di luar jadwal';
                }
            } else {
                selectedAccessLabel = 'Belum tersedia';
            }
        }

        var selectedAttemptLabel = 'Belum mulai';
        var selectedAttemptTone = 'is-neutral';
        if (!hasSelectedExam) {
            selectedAttemptLabel = 'Menunggu pilihan';
            selectedAttemptTone = 'is-muted';
        } else if (selectedAttemptFinalizing) {
            selectedAttemptLabel = 'Diproses finalisasi';
            selectedAttemptTone = 'is-warn';
        } else if (selectedExamCompleted) {
            selectedAttemptLabel = 'Sudah selesai';
            selectedAttemptTone = 'is-done';
        } else if (selectedAttemptStatus === 'in_progress') {
            selectedAttemptLabel = 'Sesi aktif';
            selectedAttemptTone = 'is-ready';
        }

        var selectedTokenLabel = 'Menunggu pilihan';
        var selectedTokenTone = 'is-muted';
        if (hasSelectedExam) {
            if (tokenInputRequired) {
                selectedTokenLabel = 'Token manual';
                selectedTokenTone = 'is-warn';
            } else if (selectedExamRequiresToken || selectedExamAutoToken) {
                selectedTokenLabel = 'Token otomatis';
                selectedTokenTone = 'is-ready';
            } else {
                selectedTokenLabel = 'Tanpa token';
                selectedTokenTone = 'is-neutral';
            }
        }

        var tokenFieldValue = !hasSelectedExam
            ? 'Pilih ujian dahulu'
            : (selectedExamAutoTokenValue !== ''
                ? selectedExamAutoTokenValue
                : ((selectedExamRequiresToken || selectedExamAutoToken) ? 'Otomatis oleh sistem' : 'Tidak diperlukan'));
        var tokenFieldHelpText = !hasSelectedExam
            ? 'Pilih ujian dari daftar kiri untuk melihat kebutuhan token.'
            : (selectedAttemptFinalizing
                ? 'Waktu habis. Hasil diproses di background.'
                : (selectedExamCompleted
                ? (selectedExamShowsResult
                    ? 'Attempt terakhir untuk ujian ini sudah selesai. Anda masih bisa membuka hasil nilainya.'
                    : 'Attempt terakhir untuk ujian ini sudah selesai. Admin menyembunyikan nilai dan review, tetapi status hasil tetap bisa dibuka.')
                : (tokenInputRequired
                    ? 'Masukkan token 6 karakter sebelum memulai atau melanjutkan ujian.'
                    : ((selectedExamRequiresToken || selectedExamAutoToken)
                        ? 'Token tidak perlu diketik manual karena akan diisi oleh sistem.'
                        : 'Ujian ini dapat dimulai tanpa token.'))));
        if (tokenRefreshMinutes > 0 && hasSelectedExam && !selectedExamCompleted) {
            tokenFieldHelpText += ' Gunakan token terbaru karena sistem dapat memperbaruinya setiap ' + tokenRefreshMinutes + ' menit.';
        }

        var confirmQuickText = !hasSelectedExam
            ? 'Pilih salah satu ujian dari daftar kiri untuk mengaktifkan detail dan tombol aksi.'
            : (
                selectedAttemptFinalizing
                    ? 'Finalisasi background berjalan. Halaman diperbarui otomatis.'
                    : (selectedAttemptStatus === 'in_progress'
                    ? 'Sesi sebelumnya masih aktif. Anda akan melanjutkan dari progres terakhir.'
                    : (
                        selectedExamCompleted
                            ? (selectedExamShowsResult
                                ? 'Attempt terakhir sudah selesai. Gunakan tombol lihat nilai untuk membuka hasilnya.'
                                : 'Attempt terakhir sudah selesai. Gunakan tombol lihat status untuk membuka informasi penyimpanan jawaban.')
                            : 'Pastikan jadwal, token, dan data peserta sudah benar sebelum menekan mulai.'
                    ))
            );
        var confirmSupportText = tokenFieldHelpText;
        if (confirmQuickText && tokenFieldHelpText.indexOf(confirmQuickText) === -1) {
            confirmSupportText += ' ' + confirmQuickText;
        }

        var primaryActionLabel = state.busy
            ? (selectedAttemptFinalizing ? 'Memproses...' : (selectedExamCompleted ? 'Memuat...' : (selectedAttemptStatus === 'in_progress' ? 'Membuka...' : 'Memulai...')))
            : (selectedAttemptFinalizing ? 'Memproses...' : (selectedExamCompleted ? (selectedExamShowsResult ? 'Lihat Nilai' : 'Lihat Status') : (selectedAttemptStatus === 'in_progress' ? 'Lanjutkan Ujian' : 'Mulai Ujian')));
        var activeExamListFilter = normalizeExamListFilter(state.examListFilter);
        var examListFilterCounts = getExamListFilterCounts();
        var filteredExams = getFilteredExams(activeExamListFilter);
        var examItems = filteredExams.map(function (exam) {
            var isActive = Number(exam.id) === Number(state.selectedExamId);
            var status = String(exam.status || '-');
            var duration = Number(exam.duration_minutes) || 0;
            var withinSchedule = Number(exam.is_within_schedule) === 1;
            var classAllowed = Number(exam.is_class_allowed) === 1;
            var availableNow = Number(exam.is_available_now) === 1;
            var availabilityReason = String(exam.availability_reason || '');
            var latestAttemptStatus = String(exam.latest_attempt_status || '').toLowerCase();
            var latestAttemptFinalizing = isExamLatestAttemptFinalizing(exam);
            var latestAttemptPercentage = Number(exam.latest_attempt_percentage);
            var showStudentResult = canShowStudentResult(exam);
            var examAttemptCompact = 'BELUM';
            var accessLabel = 'Akses: SIAP';
            var itemClasses = ['cbt-exam-item'];

            if (isActive) {
                itemClasses.push('is-active');
            }
            if (latestAttemptFinalizing) {
                itemClasses.push('is-in-progress');
                examAttemptCompact = 'DIPROSES';
            } else if (latestAttemptStatus === 'completed') {
                itemClasses.push('is-completed');
                examAttemptCompact = 'SELESAI';
                if (showStudentResult && Number.isFinite(latestAttemptPercentage)) {
                    examAttemptCompact += ' | ' + formatScoreValue(latestAttemptPercentage);
                }
            } else if (latestAttemptStatus === 'in_progress') {
                itemClasses.push('is-in-progress');
                examAttemptCompact = 'DIKERJAKAN';
            } else {
                itemClasses.push('is-not-started');
            }

            if (!availableNow) {
                if (!classAllowed) {
                    accessLabel = 'Akses: KELAS TIDAK SESUAI';
                } else if (!withinSchedule) {
                    if (availabilityReason === 'not_started') {
                        accessLabel = 'Akses: BELUM MULAI';
                    } else if (availabilityReason === 'ended') {
                        accessLabel = 'Akses: JADWAL SUDAH SELESAI';
                    } else {
                        accessLabel = 'Akses: DI LUAR JADWAL';
                    }
                } else {
                    accessLabel = 'Akses: BELUM TERSEDIA';
                }
            }

            var accessCompactLabel = accessLabel.replace(/^Akses:\s*/i, '');
            var startsAtCompact = formatDateTimeCompact(exam.starts_at);
            var statusBadgeLabel = formatExamStatusBadgeLabel(status);
            var statusBadgeClass = 'is-neutral';
            var accessChipClass = availableNow ? 'is-ready' : 'is-warn';
            var attemptChipClass = 'is-warn';

            itemClasses.push('cbt-exam-item-modern');

            if (String(status || '').toLowerCase().indexOf('publish') >= 0 || String(status || '').toLowerCase().indexOf('aktif') >= 0) {
                statusBadgeClass = 'is-published';
            } else if (String(status || '').toLowerCase().indexOf('draft') >= 0 || String(status || '').toLowerCase().indexOf('arsip') >= 0) {
                statusBadgeClass = 'is-muted';
            }

            if (latestAttemptFinalizing) {
                attemptChipClass = 'is-warn';
            } else if (latestAttemptStatus === 'completed') {
                attemptChipClass = 'is-success';
            } else if (latestAttemptStatus === 'in_progress') {
                attemptChipClass = 'is-ready';
            }

            return [
                '<button type="button" class="' + itemClasses.join(' ') + '" data-action="select-exam" data-id="' + escapeHtml(exam.id) + '" aria-pressed="' + (isActive ? 'true' : 'false') + '">',
                '<span class="cbt-exam-card-head">',
                '<span class="cbt-exam-title cbt-exam-card-title" title="' + escapeHtml(exam.title || '-') + '">' + escapeHtml(exam.title || '-') + '</span>',
                '<span class="cbt-exam-card-status ' + statusBadgeClass + '">' + escapeHtml(statusBadgeLabel) + '</span>',
                '</span>',
                '<span class="cbt-exam-card-chips">',
                renderExamCardChip(startsAtCompact, 'calendar', 'is-accent'),
                renderExamCardChip(String(duration) + ' menit', 'clock', 'is-soft'),
                renderExamCardChip(accessCompactLabel, 'access', accessChipClass),
                renderExamCardChip(examAttemptCompact, 'attempt', attemptChipClass),
                '</span>',
                '</button>'
            ].join('');
        }).join('');
        var examListContent = filteredExams.length > 0
            ? examItems
            : renderExamListEmptyState(activeExamListFilter);
        var selectedExamPickerLabel = hasSelectedExam
            ? formatExamPickerOptionLabel(selectedExam)
            : 'Pilih salah satu ujian';
        var selectedExamPickerStartsAt = hasSelectedExam ? formatDateTimeCompact(selectedExam.starts_at) : '';
        var selectedExamPickerDuration = hasSelectedExam ? (Number(selectedExam.duration_minutes) || 0) : 0;
        var selectedExamPickerNote = hasSelectedExam
            ? (selectedExamPickerStartsAt + ' | ' + (selectedExamPickerDuration > 0 ? (String(selectedExamPickerDuration) + ' menit') : 'Durasi belum diatur'))
            : (String(filteredExams.length) + ' dari ' + String(state.exams.length) + ' ujian tersedia');
        var filteredExamCountLabel = filteredExams.length === state.exams.length
            ? String(state.exams.length) + ' ujian'
            : String(filteredExams.length) + ' dari ' + String(state.exams.length) + ' ujian';
        var examPickerDropdownClass = 'cbt-exam-picker-dropdown' + (state.examPickerMobileOpen ? ' is-open' : '');
        var examPickerOptions = filteredExams.map(function (exam) {
            return renderExamPickerMobileOption(exam);
        }).join('');
        if (examPickerOptions === '') {
            examPickerOptions = renderExamListEmptyState(activeExamListFilter);
        }
        var examListFilterControl = renderExamListFilterControl(activeExamListFilter, examListFilterCounts);

        return [
            '<div class="cbt-grid-2 cbt-confirm-stage-grid">',
            '<section class="cbt-card cbt-exam-picker-card">',
            '<h3 class="cbt-exam-picker-title">Pilih Ujian</h3>',
            '<p class="cbt-subtitle">Daftar ujian sesuai hak akses akun yang login.</p>',
            examListFilterControl,
            '<div class="cbt-exam-picker-mobile">',
            '<div class="cbt-exam-picker-mobile-head">',
            '<p class="cbt-exam-picker-mobile-kicker">Pilih Cepat</p>',
            '<span class="cbt-exam-picker-mobile-count">' + escapeHtml(filteredExamCountLabel) + '</span>',
            '</div>',
            '<div class="cbt-field">',
            '<label id="cbt-exam-picker-mobile-label">Pilih Ujian</label>',
            '<div class="' + examPickerDropdownClass + '">',
            '<button id="cbt-exam-picker-trigger" class="cbt-exam-picker-trigger" data-action="toggle-exam-picker-mobile" type="button" aria-haspopup="listbox" aria-expanded="' + (state.examPickerMobileOpen ? 'true' : 'false') + '" aria-controls="cbt-exam-picker-menu" aria-labelledby="cbt-exam-picker-mobile-label cbt-exam-picker-trigger-value"' + (state.busy ? ' disabled' : '') + '>',
            '<span class="cbt-exam-picker-trigger-copy">',
            '<span class="cbt-exam-picker-trigger-label">Ujian Dipilih</span>',
            '<strong id="cbt-exam-picker-trigger-value" class="cbt-exam-picker-trigger-value">' + escapeHtml(selectedExamPickerLabel) + '</strong>',
            '<small class="cbt-exam-picker-trigger-note">' + escapeHtml(selectedExamPickerNote) + '</small>',
            '</span>',
            '<span class="cbt-exam-picker-trigger-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M6 9l6 6 6-6"></path></svg></span>',
            '</button>',
            (state.examPickerMobileOpen
                ? ('<div id="cbt-exam-picker-menu" class="cbt-exam-picker-menu" role="listbox" aria-labelledby="cbt-exam-picker-mobile-label">' + examPickerOptions + '</div>')
                : ''),
            '</div>',
            '</div>',
            '<p class="cbt-exam-picker-mobile-help">Pilih ujian dari panel ini untuk melihat ringkasan dan tombol aksi yang sesuai.</p>',
            '</div>',
            '<div class="cbt-exam-list">' + examListContent + '</div>',
            '</section>',
            '<section class="cbt-card cbt-confirm-card cbt-confirm-card-simple">',
            '<p class="cbt-confirm-kicker">Siap Ujian</p>',
            '<h3>Konfirmasi Ujian</h3>',
            '<p class="cbt-confirm-selected-title">' + escapeHtml(selectedExamTitle) + '</p>',
            '<p class="cbt-subtitle">' + escapeHtml(tokenInfoText) + '</p>',
            '<div class="cbt-confirm-status-list">',
            renderConfirmStatusPill(selectedAccessLabel, selectedAccessTone),
            renderConfirmStatusPill(selectedTokenLabel, selectedTokenTone),
            renderConfirmStatusPill(selectedAttemptLabel, selectedAttemptTone),
            '</div>',
            '<div class="cbt-confirm-profile">',
            (
                userPhoto !== ''
                    ? '<button class="cbt-confirm-profile-avatar-button" data-action="open-user-photo" type="button" aria-label="Lihat foto peserta ukuran besar"><img class="cbt-confirm-profile-avatar" src="' + escapeHtml(userPhoto) + '" alt="' + escapeHtml(userName) + '" loading="lazy" decoding="async" data-cbt-profile-photo="confirm" /><span class="cbt-confirm-profile-avatar cbt-confirm-profile-avatar-fallback" data-cbt-profile-photo-fallback hidden aria-hidden="true">' + escapeHtml(userInitial) + '</span></button>'
                    : '<div class="cbt-confirm-profile-avatar cbt-confirm-profile-avatar-fallback" aria-hidden="true">' + escapeHtml(userInitial) + '</div>'
            ),
            '</div>',
            '<div class="cbt-form-grid cbt-confirm-form-grid">',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Username</label><input class="cbt-input" value="' + escapeHtml(userUsername) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field"><label>Nama Peserta</label><input class="cbt-input" value="' + escapeHtml(userName || '-') + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Kelas</label><input class="cbt-input" value="' + escapeHtml(userClassCode) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Ruangan</label><input class="cbt-input" value="' + escapeHtml(userRoomCode) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field"><label>Ujian</label><input class="cbt-input" value="' + escapeHtml(selectedExamTitle) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field"><label>Mata Pelajaran</label><input class="cbt-input" value="' + escapeHtml(selectedExamSubject) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Mulai</label><input class="cbt-input" value="' + escapeHtml(selectedExamStartsAt) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Durasi</label><input class="cbt-input" value="' + escapeHtml(selectedExamDurationLabel) + '" readonly /></div>',
            (
                tokenInputRequired
                    ? '<div class="cbt-field cbt-confirm-field cbt-confirm-field-token"><label for="cbt-exam-token">Token Ujian</label><input id="cbt-exam-token" class="cbt-input cbt-input-token" name="exam_token" maxlength="6" value="' + escapeHtml(state.examToken) + '" placeholder="6 karakter (tanpa 0 O I L)"' + (hasSelectedExam && !selectedExamCompleted ? '' : ' disabled') + ' /></div>'
                    : '<div class="cbt-field cbt-confirm-field cbt-confirm-field-token"><label>Token Ujian</label><input class="cbt-input" value="' + escapeHtml(tokenFieldValue) + '" readonly /></div>'
            ),
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-note"><p class="cbt-confirm-token-note">' + escapeHtml(confirmSupportText) + '</p></div>',
            '</div>',
            renderAdminDiagnosticPanel(hasSelectedExam, selectedExam),
            renderAlert(),
            renderConfirmRefreshProgressCard(),
            '<div class="cbt-actions cbt-confirm-actions">',
            (
                selectedExamCompleted
                    ? '<button class="cbt-button cbt-button-primary" data-action="view-result" type="button"' + (state.busy || !hasSelectedExam || selectedExamAttemptId <= 0 ? ' disabled' : '') + '>' + primaryActionLabel + '</button>'
                    : '<button class="cbt-button cbt-button-primary" data-action="start-exam" type="button"' + (state.busy || !hasSelectedExam || selectedAttemptFinalizing ? ' disabled' : '') + '>' + primaryActionLabel + '</button>'
            ),
            renderRefreshButton(state.busy),
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    return {
        renderConfirmStage: renderConfirmStage,
        renderLoginStage: renderLoginStage,
        updateExamListFilter: updateExamListFilter,
        updateSelectedExam: updateSelectedExam
    };
}
