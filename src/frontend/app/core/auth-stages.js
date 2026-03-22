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
            '<span class="cbt-button-refresh-label">REFRESH</span>',
            '</button>'
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

    function renderExamPickerMobileOption(exam) {
        var optionId = Number(exam && exam.id) || 0;
        var isActive = optionId === Number(state.selectedExamId);
        var durationMinutes = Number(exam && exam.duration_minutes) || 0;
        var startsAtLabel = formatDateTimeCompact(exam && exam.starts_at ? exam.starts_at : '');
        var latestAttemptStatus = String(exam && exam.latest_attempt_status ? exam.latest_attempt_status : '').toLowerCase();
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

        if (latestAttemptStatus === 'completed') {
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

    function renderLoginStage() {
        var schoolNameRaw = getConfiguredSchoolName();
        var schoolName = escapeHtml(schoolNameRaw);
        var schoolBranding = getLoginHeroSchoolBranding(schoolNameRaw);
        var schoolBrandTag = schoolBranding.tag ? escapeHtml(schoolBranding.tag) : '';
        var schoolBrandTitle = escapeHtml(schoolNameRaw || schoolBranding.title || 'CBT Exam');
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
        var loginButtonClass = state.busy ? 'cbt-button cbt-button-primary cbt-button-login is-loading' : 'cbt-button cbt-button-primary cbt-button-login';
        var loginButtonLabel = state.busy ? 'Memverifikasi...' : 'LOGIN';
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
            '<h3>Masuk ke CBT</h3>',
            mobilePanelLogoBlock,
            '<form id="cbt-login-form" class="cbt-form-grid">',
            '<div class="cbt-field"><label for="cbt-identifier">EMAIL / USERNAME / NISN</label><input id="cbt-identifier" class="cbt-input" name="identifier" autocomplete="username" value="' + escapeHtml(state.loginIdentifier) + '" placeholder="Contoh: 231045 atau siswa@smkn1tpd.sch.id" required /></div>',
            '<div class="cbt-field"><label for="cbt-password">PASSWORD</label><div class="cbt-password-field"><input id="cbt-password" class="cbt-input" name="password" type="' + passwordType + '" autocomplete="current-password" value="' + escapeHtml(state.loginPassword) + '" placeholder="Masukkan password akun" required /><button class="cbt-password-toggle' + (state.loginPasswordVisible ? ' is-visible' : '') + '" data-action="toggle-password" type="button" aria-label="' + togglePasswordLabel + '" title="' + togglePasswordLabel + '"' + (state.busy ? ' disabled' : '') + '><span class="cbt-password-toggle-icon" aria-hidden="true"><span class="cbt-password-toggle-icon-eye"><svg viewBox="0 0 24 24" focusable="false"><path d="M1.5 12S5.5 5.5 12 5.5 22.5 12 22.5 12 18.5 18.5 12 18.5 1.5 12 1.5 12Z"></path><circle cx="12" cy="12" r="3.2"></circle></svg></span><span class="cbt-password-toggle-icon-eye-off"><svg viewBox="0 0 24 24" focusable="false"><path d="M3.2 3.2 20.8 20.8"></path><path d="M9.9 5.9A12.2 12.2 0 0 1 12 5.5c6.5 0 10.5 6.5 10.5 6.5a18.9 18.9 0 0 1-3.4 4.2"></path><path d="M6.4 8A18.3 18.3 0 0 0 1.5 12s4 6.5 10.5 6.5a11.6 11.6 0 0 0 4-.7"></path><path d="M14.3 14.3A3.2 3.2 0 0 1 9.7 9.7"></path></svg></span></span><span class="cbt-password-toggle-label">' + escapeHtml(togglePasswordLabel) + '</span></button></div></div>',
            '<div class="cbt-actions"><button class="' + loginButtonClass + '" type="submit"' + (state.busy ? ' disabled' : '') + '><span class="cbt-button-spinner" aria-hidden="true"></span><span>' + loginButtonLabel + '</span></button></div>',
            '</form>',
            renderAlert(),
            '<p class="cbt-login-help">Jika gagal login, hubungi admin sekolah atau pengawas ujian.</p>',
            loginMetaBlock,
            '</div>',
            '</section>'
        ].join('');
    }

    function renderConfirmStage() {
        if (!state.exams.length) {
            return [
                '<section class="cbt-card">',
                '<h3>Belum Ada Exam Aktif</h3>',
                '<p class="cbt-subtitle">Akun ini belum memiliki exam yang tersedia saat ini.</p>',
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
        var selectedExamCompleted = selectedAttemptStatus === 'completed';
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
                ? 'Ujian ini sudah selesai. Anda bisa melihat hasil nilai dari attempt terakhir.'
                : (selectedExamRequiresToken
                    ? (
                        tokenInputRequired
                            ? ('Ujian ini membutuhkan token.' + (tokenRefreshMinutes > 0 ? (' Refresh setiap ' + tokenRefreshMinutes + ' menit.') : ''))
                            : ('Token ujian diisi otomatis oleh sistem.' + (tokenRefreshMinutes > 0 ? (' Refresh setiap ' + tokenRefreshMinutes + ' menit.') : ''))
                    )
                    : 'Ujian ini tidak membutuhkan token.'))
            : 'Pilih ujian terlebih dahulu dari daftar di kiri.';
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
        } else if (selectedExamCompleted) {
            selectedAccessLabel = 'Hasil tersedia';
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
            : (selectedExamCompleted
                ? 'Attempt terakhir untuk ujian ini sudah selesai. Anda masih bisa membuka hasil nilainya.'
                : (tokenInputRequired
                    ? 'Masukkan token 6 karakter sebelum memulai atau melanjutkan ujian.'
                    : ((selectedExamRequiresToken || selectedExamAutoToken)
                        ? 'Token tidak perlu diketik manual karena akan diisi oleh sistem.'
                        : 'Ujian ini dapat dimulai tanpa token.')));
        if (tokenRefreshMinutes > 0 && hasSelectedExam && !selectedExamCompleted) {
            tokenFieldHelpText += ' Gunakan token terbaru karena sistem dapat memperbaruinya setiap ' + tokenRefreshMinutes + ' menit.';
        }

        var confirmQuickText = !hasSelectedExam
            ? 'Pilih salah satu ujian dari daftar kiri untuk mengaktifkan detail dan tombol aksi.'
            : (
                selectedAttemptStatus === 'in_progress'
                    ? 'Sesi sebelumnya masih aktif. Anda akan melanjutkan dari progres terakhir.'
                    : (
                        selectedExamCompleted
                            ? 'Attempt terakhir sudah selesai. Gunakan tombol lihat nilai untuk membuka hasilnya.'
                            : 'Pastikan jadwal, token, dan data peserta sudah benar sebelum menekan mulai.'
                    )
            );
        var confirmSupportText = tokenFieldHelpText;
        if (confirmQuickText && tokenFieldHelpText.indexOf(confirmQuickText) === -1) {
            confirmSupportText += ' ' + confirmQuickText;
        }

        var primaryActionLabel = state.busy
            ? (selectedExamCompleted ? 'Memuat...' : (selectedAttemptStatus === 'in_progress' ? 'Membuka...' : 'Memulai...'))
            : (selectedExamCompleted ? 'Lihat Nilai' : (selectedAttemptStatus === 'in_progress' ? 'Lanjutkan Ujian' : 'Mulai Ujian'));
        var examItems = state.exams.map(function (exam) {
            var isActive = Number(exam.id) === Number(state.selectedExamId);
            var status = String(exam.status || '-');
            var duration = Number(exam.duration_minutes) || 0;
            var withinSchedule = Number(exam.is_within_schedule) === 1;
            var classAllowed = Number(exam.is_class_allowed) === 1;
            var availableNow = Number(exam.is_available_now) === 1;
            var availabilityReason = String(exam.availability_reason || '');
            var latestAttemptStatus = String(exam.latest_attempt_status || '').toLowerCase();
            var latestAttemptPercentage = Number(exam.latest_attempt_percentage);
            var examAttemptCompact = 'BELUM';
            var accessLabel = 'Akses: SIAP';
            var itemClasses = ['cbt-exam-item'];

            if (isActive) {
                itemClasses.push('is-active');
            }
            if (latestAttemptStatus === 'completed') {
                itemClasses.push('is-completed');
                examAttemptCompact = 'SELESAI';
                if (Number.isFinite(latestAttemptPercentage)) {
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

            if (latestAttemptStatus === 'completed') {
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
        var selectedExamPickerLabel = hasSelectedExam
            ? formatExamPickerOptionLabel(selectedExam)
            : 'Pilih salah satu ujian';
        var selectedExamPickerStartsAt = hasSelectedExam ? formatDateTimeCompact(selectedExam.starts_at) : '';
        var selectedExamPickerDuration = hasSelectedExam ? (Number(selectedExam.duration_minutes) || 0) : 0;
        var selectedExamPickerNote = hasSelectedExam
            ? (selectedExamPickerStartsAt + ' | ' + (selectedExamPickerDuration > 0 ? (String(selectedExamPickerDuration) + ' menit') : 'Durasi belum diatur'))
            : (String(state.exams.length) + ' ujian tersedia');
        var examPickerDropdownClass = 'cbt-exam-picker-dropdown' + (state.examPickerMobileOpen ? ' is-open' : '');
        var examPickerOptions = state.exams.map(function (exam) {
            return renderExamPickerMobileOption(exam);
        }).join('');

        return [
            '<div class="cbt-grid-2 cbt-confirm-stage-grid">',
            '<section class="cbt-card cbt-exam-picker-card">',
            '<h3 class="cbt-exam-picker-title">Pilih Ujian</h3>',
            '<p class="cbt-subtitle">Daftar ujian sesuai hak akses akun yang login.</p>',
            '<div class="cbt-exam-picker-mobile">',
            '<div class="cbt-exam-picker-mobile-head">',
            '<p class="cbt-exam-picker-mobile-kicker">Pilih Cepat</p>',
            '<span class="cbt-exam-picker-mobile-count">' + escapeHtml(String(state.exams.length)) + ' ujian</span>',
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
            '<div class="cbt-exam-list">' + examItems + '</div>',
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
                    ? '<button class="cbt-confirm-profile-avatar-button" data-action="open-user-photo" type="button" aria-label="Lihat foto peserta ukuran besar"><img class="cbt-confirm-profile-avatar" src="' + escapeHtml(userPhoto) + '" alt="' + escapeHtml(userName) + '" loading="lazy" decoding="async" /></button>'
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
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Status Akses</label><input class="cbt-input" value="' + escapeHtml(selectedAccessLabel) + '" readonly /></div>',
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-compact"><label>Status Attempt</label><input class="cbt-input" value="' + escapeHtml(selectedAttemptLabel) + '" readonly /></div>',
            (
                tokenInputRequired
                    ? '<div class="cbt-field cbt-confirm-field cbt-confirm-field-token"><label for="cbt-exam-token">Token Ujian</label><input id="cbt-exam-token" class="cbt-input cbt-input-token" name="exam_token" maxlength="6" value="' + escapeHtml(state.examToken) + '" placeholder="6 karakter (tanpa 0 O I L)"' + (hasSelectedExam && !selectedExamCompleted ? '' : ' disabled') + ' /></div>'
                    : '<div class="cbt-field cbt-confirm-field cbt-confirm-field-token"><label>Token Ujian</label><input class="cbt-input" value="' + escapeHtml(tokenFieldValue) + '" readonly /></div>'
            ),
            '<div class="cbt-field cbt-confirm-field cbt-confirm-field-note"><p class="cbt-confirm-token-note">' + escapeHtml(confirmSupportText) + '</p></div>',
            '</div>',
            renderAlert(),
            '<div class="cbt-actions cbt-confirm-actions">',
            (
                selectedExamCompleted
                    ? '<button class="cbt-button cbt-button-primary" data-action="view-result" type="button"' + (state.busy || !hasSelectedExam || selectedExamAttemptId <= 0 ? ' disabled' : '') + '>' + primaryActionLabel + '</button>'
                    : '<button class="cbt-button cbt-button-primary" data-action="start-exam" type="button"' + (state.busy || !hasSelectedExam ? ' disabled' : '') + '>' + primaryActionLabel + '</button>'
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
        updateSelectedExam: updateSelectedExam
    };
}
