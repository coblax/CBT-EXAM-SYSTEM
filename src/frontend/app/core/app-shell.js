export function createAppShellManager(deps) {
    var state = deps.state;
    var escapeHtml = deps.escapeHtml;
    var formatFontScaleLabel = deps.formatFontScaleLabel;
    var formatScoreValue = deps.formatScoreValue;
    var formatSeconds = deps.formatSeconds;
    var getConfiguredSchoolLogoUrl = deps.getConfiguredSchoolLogoUrl;
    var getConfiguredSchoolName = deps.getConfiguredSchoolName;
    var getCurrentUserName = deps.getCurrentUserName;
    var getCurrentUserPhoto = deps.getCurrentUserPhoto;
    var getSelectedExam = deps.getSelectedExam;
    var getUserInitial = deps.getUserInitial;
    var renderAlert = deps.renderAlert;
    var renderConfirmStage = deps.renderConfirmStage;
    var renderExamStageShell = deps.renderExamStageShell;
    var renderLoginStage = deps.renderLoginStage;
    var renderResultStageShell = deps.renderResultStageShell;
    var RESULT_PROGRESS_STEP_TOTAL = 4;

    function renderThemeToggleControl() {
        var isDark = state.uiTheme === 'dark';
        var themeLabel = isDark ? 'Tema Terang' : 'Tema Gelap';
        var themeIcon = isDark ? '\u2600' : '\u263E';

        return [
            '<button class="cbt-icon-button cbt-theme-toggle" data-action="toggle-theme" type="button" aria-label="' + escapeHtml(themeLabel) + '" title="' + escapeHtml(themeLabel) + '">',
            '<span class="cbt-theme-toggle-icon" aria-hidden="true">' + escapeHtml(themeIcon) + '</span>',
            '</button>'
        ].join('');
    }

    function renderQuestionFontControls() {
        var canDecrease = state.fontScale > (deps.fontScaleMin + 0.001);
        var canIncrease = state.fontScale < (deps.fontScaleMax - 0.001);
        var scaleLabel = formatFontScaleLabel(state.fontScale);

        return [
            '<div class="cbt-access-group cbt-question-font-controls" role="group" aria-label="Ukuran Font Soal">',
            '<button class="cbt-icon-button cbt-font-btn cbt-font-btn-dec" data-action="font-dec" type="button" title="Perkecil font" aria-label="Perkecil font"' + (canDecrease ? '' : ' disabled') + '><span class="cbt-font-btn-icon" aria-hidden="true">\u2212</span><span class="cbt-font-btn-label">A-</span></button>',
            '<button class="cbt-icon-button cbt-icon-button-value cbt-font-btn cbt-font-btn-reset" data-action="font-reset" type="button" title="Reset ukuran font (' + escapeHtml(scaleLabel) + ')" aria-label="Reset ukuran font (' + escapeHtml(scaleLabel) + ')"><span class="cbt-font-btn-icon" aria-hidden="true">A</span><span class="cbt-font-btn-label">' + escapeHtml(scaleLabel) + '</span></button>',
            '<button class="cbt-icon-button cbt-font-btn cbt-font-btn-inc" data-action="font-inc" type="button" title="Perbesar font" aria-label="Perbesar font"' + (canIncrease ? '' : ' disabled') + '><span class="cbt-font-btn-icon" aria-hidden="true">+</span><span class="cbt-font-btn-label">A+</span></button>',
            '</div>'
        ].join('');
    }

    function renderTopbar() {
        var userName = getCurrentUserName();
        var userPhoto = getCurrentUserPhoto();
        var userInitial = getUserInitial(userName);
        var schoolName = getConfiguredSchoolName();
        var schoolLogoUrl = getConfiguredSchoolLogoUrl();
        var brandBadge = schoolLogoUrl !== ''
            ? '<img class="cbt-brand-badge-logo" src="' + escapeHtml(schoolLogoUrl) + '" alt="' + escapeHtml(schoolName) + '" loading="lazy" decoding="async" />'
            : 'CBT';
        var userChip = [
            '<span class="cbt-chip cbt-user-chip">',
            userPhoto !== ''
                ? '<button class="cbt-user-chip-photo-button" data-action="open-user-photo" type="button" aria-label="Lihat foto profil ukuran besar"><img class="cbt-user-chip-photo" src="' + escapeHtml(userPhoto) + '" alt="' + escapeHtml(userName) + '" loading="lazy" decoding="async" data-cbt-profile-photo="user-chip" /><span class="cbt-user-chip-fallback" data-cbt-profile-photo-fallback hidden aria-hidden="true">' + escapeHtml(userInitial) + '</span></button>'
                : '<span class="cbt-user-chip-fallback" aria-hidden="true">' + escapeHtml(userInitial) + '</span>',
            '<button class="cbt-user-chip-name-button" data-action="open-user-photo" type="button" aria-label="Lihat informasi peserta">' + escapeHtml(userName) + '</button>',
            '</span>'
        ].join('');

        var stageLabel = 'Login';
        if (state.stage === 'confirm') {
            stageLabel = 'Konfirmasi Ujian';
        } else if (state.stage === 'exam') {
            stageLabel = 'Sedang Ujian';
        } else if (state.stage === 'result') {
            stageLabel = 'Hasil Ujian';
        }

        var timerChip = '';
        if (state.stage === 'exam') {
            timerChip = [
                '<span class="cbt-chip cbt-timer-chip" aria-live="polite" aria-label="Sisa waktu ujian: ' + escapeHtml(formatSeconds(state.remainingSeconds)) + '">',
                '<span class="cbt-timer-chip-icon" aria-hidden="true">\u23f1</span>',
                '<span data-cbt-timer>' + formatSeconds(state.remainingSeconds) + '</span>',
                '</span>'
            ].join('');
        }

        return [
            '<header class="cbt-topbar">',
            '<div class="cbt-brand">',
            '<span class="cbt-brand-badge">' + brandBadge + '</span>',
            '<div><h2>' + escapeHtml(schoolName) + '</h2><small>' + escapeHtml(stageLabel) + '</small></div>',
            '</div>',
            '<div class="cbt-topbar-right">',
            renderThemeToggleControl(),
            userChip,
            timerChip,
            state.user
                ? '<button class="cbt-button cbt-button-secondary cbt-logout-button" data-action="logout" type="button" aria-label="Logout" title="Logout"><span class="cbt-logout-icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 3v8"></path><path d="M7.05 5.05A9 9 0 1 0 16.95 5.05"></path></svg></span><span class="cbt-logout-label">LOGOUT</span></button>'
                : '',
            '</div>',
            '</header>'
        ].join('');
    }

    function renderBody() {
        if (state.stage === 'login') {
            return renderLoginStage();
        }

        if (state.stage === 'confirm') {
            return renderConfirmStage();
        }

        if (state.stage === 'exam') {
            return renderExamStageShell();
        }

        if (state.stage === 'result') {
            return renderResultStageShell();
        }

        return '<section class="cbt-card"><p class="cbt-subtitle">Stage tidak dikenali.</p></section>';
    }

    function renderAuthProgressOverlay() {
        if (!state.authProgressVisible) {
            return '';
        }

        var mode = String(state.authProgressMode || 'login').toLowerCase() === 'logout' ? 'logout' : 'login';
        var progressPercent = Math.max(0, Math.min(100, Number(state.authProgressPercent) || 0));
        var progressWidth = progressPercent.toFixed(2);
        var progressLabel = formatScoreValue(progressPercent);
        var stepIndex = Math.max(1, Number(state.authProgressStepIndex) || 1);
        var stepTotal = Math.max(4, Number(state.authProgressStepTotal) || 4);
        var status = String(state.authProgressStatus || (mode === 'logout' ? 'Menutup sesi...' : 'Memverifikasi login...'));
        var detail = String(state.authProgressDetail || (mode === 'logout'
            ? 'Mohon tunggu sebentar, kami sedang memastikan sesi Anda ditutup dengan aman.'
            : 'Mohon tunggu sebentar, kami sedang memverifikasi akun dan menyiapkan sesi Anda.'
        ));
        var title = mode === 'logout' ? 'Logout Sedang Diproses' : 'Login Sedang Diproses';
        var subtitle = mode === 'logout'
            ? 'Mohon jangan tutup halaman sampai proses logout selesai.'
            : 'Mohon jangan refresh halaman saat proses login berjalan.';

        return [
            '<div class="cbt-auth-progress-overlay" aria-live="polite" aria-busy="true">',
            '<section class="cbt-auth-progress-shell" role="status" aria-label="' + escapeHtml(title) + '">',
            '<div class="cbt-auth-progress-card cbt-auth-progress-card--' + escapeHtml(mode) + '">',
            '<div class="cbt-auth-progress-kicker">' + escapeHtml(mode === 'logout' ? 'Sesi Aman' : 'Auth Progress') + '</div>',
            '<h3 class="cbt-auth-progress-title">' + escapeHtml(title) + '</h3>',
            '<p class="cbt-auth-progress-subtitle">' + escapeHtml(subtitle) + '</p>',
            '<div class="cbt-finish-live-card">',
            '<div class="cbt-finish-live-head">',
            '<span class="cbt-finish-live-spinner" aria-hidden="true"></span>',
            '<div class="cbt-finish-live-copy">',
            '<strong>' + escapeHtml(status) + '</strong>',
            '<span>' + escapeHtml(detail) + '</span>',
            '</div>',
            '<span class="cbt-finish-live-step">Langkah ' + escapeHtml(stepIndex) + '/' + escapeHtml(stepTotal) + '</span>',
            '</div>',
            '<div class="cbt-finish-live-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(progressWidth) + '" aria-label="Progress autentikasi">',
            '<span class="cbt-finish-live-progress-fill" style="width: ' + escapeHtml(progressWidth) + '%;"></span>',
            '</div>',
            '<p class="cbt-muted">Progress auth: ' + escapeHtml(progressLabel) + '%</p>',
            '</div>',
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function renderResultProgressOverlay() {
        if (!state.resultProgressVisible) {
            return '';
        }

        var progressPercent = Math.max(0, Math.min(100, Number(state.resultProgressPercent) || 0));
        var progressWidth = progressPercent.toFixed(2);
        var progressLabel = formatScoreValue(progressPercent);
        var stepIndex = Math.max(1, Number(state.resultProgressStepIndex) || 1);
        var stepTotal = Math.max(RESULT_PROGRESS_STEP_TOTAL, Number(state.resultProgressStepTotal) || RESULT_PROGRESS_STEP_TOTAL);
        var status = String(state.resultProgressStatus || 'Mengambil hasil attempt');
        var detail = String(state.resultProgressDetail || 'Mohon tunggu sebentar, kami sedang menyiapkan nilai dan review ujian Anda.');

        return [
            '<div class="cbt-auth-progress-overlay cbt-result-progress-overlay" aria-live="polite" aria-busy="true">',
            '<section class="cbt-auth-progress-shell cbt-result-progress-shell" role="status" aria-label="Menyiapkan Hasil Ujian">',
            '<div class="cbt-auth-progress-card cbt-auth-progress-card--result">',
            '<div class="cbt-auth-progress-kicker">Result Progress</div>',
            '<h3 class="cbt-auth-progress-title">Menyiapkan Hasil Ujian</h3>',
            '<p class="cbt-auth-progress-subtitle">Mohon jangan refresh halaman. Sistem sedang mengambil nilai dan review ujian Anda.</p>',
            '<div class="cbt-finish-live-card">',
            '<div class="cbt-finish-live-head">',
            '<span class="cbt-finish-live-spinner" aria-hidden="true"></span>',
            '<div class="cbt-finish-live-copy">',
            '<strong>' + escapeHtml(status) + '</strong>',
            '<span>' + escapeHtml(detail) + '</span>',
            '</div>',
            '<span class="cbt-finish-live-step">Langkah ' + escapeHtml(stepIndex) + '/' + escapeHtml(stepTotal) + '</span>',
            '</div>',
            '<div class="cbt-finish-live-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(progressWidth) + '" aria-label="Progress lihat nilai">',
            '<span class="cbt-finish-live-progress-fill" style="width: ' + escapeHtml(progressWidth) + '%;"></span>',
            '</div>',
            '<p class="cbt-muted">Progress hasil: ' + escapeHtml(progressLabel) + '%</p>',
            '</div>',
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function renderSessionRecoveryOverlay() {
        if (!state.sessionRecoveryVisible) {
            return '';
        }

        var mode = String(state.sessionRecoveryMode || 'confirm_restore').toLowerCase() === 'exam_restore'
            ? 'exam_restore'
            : 'confirm_restore';
        var progressPercent = Math.max(0, Math.min(100, Number(state.sessionRecoveryPercent) || 0));
        var progressWidth = progressPercent.toFixed(2);
        var progressLabel = formatScoreValue(progressPercent);
        var stepTotal = Math.max(mode === 'exam_restore' ? 7 : 4, Number(state.sessionRecoveryStepTotal) || 0);
        var stepIndex = Math.max(1, Math.min(stepTotal, Number(state.sessionRecoveryStepIndex) || 1));
        var status = String(state.sessionRecoveryStatus || (mode === 'exam_restore'
            ? 'Menyambung attempt ujian'
            : 'Memulihkan sesi login'
        ));
        var detail = String(state.sessionRecoveryDetail || (mode === 'exam_restore'
            ? 'Kami sedang menyambungkan kembali sesi ujian terakhir Anda.'
            : 'Kami sedang memulihkan token login dan daftar ujian Anda.'
        ));
        var title = mode === 'exam_restore'
            ? 'Menyambung Sesi Ujian'
            : 'Memulihkan Konfirmasi Ujian';
        var slowStage = String(state.sessionRecoverySlowStage || 'normal');
        var slowCopy = 'Sedang memulihkan sesi Anda';
        if (slowStage === 'busy') {
            slowCopy = 'Server sedang padat, kami masih mencoba otomatis';
        } else if (slowStage === 'hold') {
            slowCopy = 'Jangan refresh lagi. Sesi masih dipulihkan.';
        }
        var retryCount = Math.max(0, Number(state.sessionRecoveryRetryCount) || 0);
        var retryMarkup = state.sessionRecoveryCanRetry
            ? [
                '<div class="cbt-session-recovery-actions">',
                '<button class="cbt-button cbt-button-primary" data-action="retry-session-recovery" type="button">Coba Sambung Lagi</button>',
                retryCount > 0
                    ? '<span class="cbt-session-recovery-retry-meta">Percobaan sambung ulang: ' + escapeHtml(retryCount) + '</span>'
                    : '',
                '</div>'
            ].join('')
            : '';
        var examNoteMarkup = mode === 'exam_restore'
            ? '<p class="cbt-session-recovery-note">Jawaban lokal tetap aman dan akan disinkronkan setelah sesi pulih.</p>'
            : '';

        return [
            '<div class="cbt-auth-progress-overlay cbt-session-recovery-overlay" aria-live="polite" aria-busy="true">',
            '<section class="cbt-auth-progress-shell cbt-session-recovery-shell" role="status" aria-label="' + escapeHtml(title) + '">',
            '<div class="cbt-auth-progress-card cbt-auth-progress-card--recovery">',
            '<div class="cbt-auth-progress-kicker">Session Recovery</div>',
            '<h3 class="cbt-auth-progress-title">' + escapeHtml(title) + '</h3>',
            '<p class="cbt-auth-progress-subtitle">Jangan refresh lagi. Sistem sedang menyambung sesi Anda.</p>',
            '<div class="cbt-finish-live-card">',
            '<div class="cbt-finish-live-head">',
            '<span class="cbt-finish-live-spinner" aria-hidden="true"></span>',
            '<div class="cbt-finish-live-copy">',
            '<strong>' + escapeHtml(status) + '</strong>',
            '<span>' + escapeHtml(detail) + '</span>',
            '</div>',
            '<span class="cbt-finish-live-step">Langkah ' + escapeHtml(stepIndex) + '/' + escapeHtml(stepTotal) + '</span>',
            '</div>',
            '<div class="cbt-finish-live-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(progressWidth) + '" aria-label="Progress pemulihan sesi">',
            '<span class="cbt-finish-live-progress-fill" style="width: ' + escapeHtml(progressWidth) + '%;"></span>',
            '</div>',
            '<p class="cbt-muted">Progress pemulihan: ' + escapeHtml(progressLabel) + '%</p>',
            '</div>',
            '<p class="cbt-session-recovery-slow-copy">' + escapeHtml(slowCopy) + '</p>',
            examNoteMarkup,
            retryMarkup,
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function renderFinishConfirmModal() {
        if ((!state.finishConfirmOpen && !state.isFinishing && !(Number(state.finishProgressStepIndex) > 0)) || state.stage !== 'exam') {
            return '';
        }

        var summary = state.finishConfirmSummary || deps.getExamProgressSummary();
        var totalQuestions = Number(summary.totalQuestions) || 0;
        var answeredQuestions = Number(summary.answeredQuestions) || 0;
        var unansweredQuestions = Number(summary.unansweredQuestions) || 0;
        var answeredPercentage = totalQuestions > 0 ? (answeredQuestions / totalQuestions) * 100 : 0;
        var progressWidth = Math.max(0, Math.min(100, answeredPercentage)).toFixed(2);
        var progressLabel = formatScoreValue(answeredPercentage);
        var finishProgressPercent = Math.max(0, Math.min(100, Number(state.finishProgressPercent) || 0));
        var finishProgressWidth = finishProgressPercent.toFixed(2);
        var finishProgressLabel = formatScoreValue(finishProgressPercent);
        var finishProgressStepIndex = Math.max(0, Number(state.finishProgressStepIndex) || 0);
        var finishProgressStepTotal = Math.max(4, Number(state.finishProgressStepTotal) || 4);
        var finishProgressStatus = String(state.finishProgressStatus || 'Menyelesaikan ujian...');
        var finishProgressDetail = String(state.finishProgressDetail || 'Mohon tunggu sebentar, kami sedang memastikan hasil ujian Anda tersimpan.');
        var showFinishLiveProgress = state.isFinishing || finishProgressStepIndex > 0 || state.examLockedForPendingFinish;
        var finishTitle = showFinishLiveProgress
            ? 'Proses'
            : 'Konfirmasi Pengumpulan Ujian';
        var finishSubtitle = state.isFinishing
            ? 'Finalisasi sedang berjalan. Jangan tutup halaman ini sampai hasil muncul.'
            : (showFinishLiveProgress
                ? 'Siap'
                : 'Periksa jumlah jawaban sebelum ujian dikumpulkan.');
        var finishSubmitLabel = showFinishLiveProgress ? 'Proses...' : 'Tetap Kumpulkan';
        var warningMarkup = unansweredQuestions > 0
            ? '<div class="cbt-finish-modal-warning">Masih ada <strong>' + escapeHtml(unansweredQuestions) + '</strong> soal belum terjawab.</div>'
            : '<div class="cbt-finish-modal-ok">Semua soal sudah terjawab. Anda bisa lanjut kumpulkan ujian.</div>';
        var finishLiveMarkup = showFinishLiveProgress
            ? [
                '<div class="cbt-finish-live-card" aria-live="polite">',
                '<div class="cbt-finish-live-head">',
                '<span class="cbt-finish-live-spinner" aria-hidden="true"></span>',
                '<div class="cbt-finish-live-copy">',
                '<strong>' + escapeHtml(finishProgressStatus) + '</strong>',
                '<span>' + escapeHtml(finishProgressDetail) + '</span>',
                '</div>',
                '<span class="cbt-finish-live-step">Langkah ' + escapeHtml(finishProgressStepIndex || 1) + '/' + escapeHtml(finishProgressStepTotal) + '</span>',
                '</div>',
                '<div class="cbt-finish-live-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(finishProgressWidth) + '" aria-label="Progress pengumpulan ujian">',
                '<span class="cbt-finish-live-progress-fill" style="width: ' + escapeHtml(finishProgressWidth) + '%;"></span>',
                '</div>',
                '<p class="cbt-muted">Progress finalisasi: ' + escapeHtml(finishProgressLabel) + '%</p>',
                '</div>'
            ].join('')
            : '';

        return [
            '<div class="cbt-finish-modal-overlay">',
            '<section class="cbt-finish-modal" role="dialog" aria-modal="true" aria-labelledby="cbt-finish-modal-title">',
            '<h3 id="cbt-finish-modal-title">' + escapeHtml(finishTitle) + '</h3>',
            '<p class="cbt-subtitle">' + escapeHtml(finishSubtitle) + '</p>',
            '<div class="cbt-finish-stat-grid">',
            '<div class="cbt-finish-stat"><span>Total Soal</span><strong>' + escapeHtml(totalQuestions) + '</strong></div>',
            '<div class="cbt-finish-stat is-answered"><span>Terjawab</span><strong>' + escapeHtml(answeredQuestions) + '</strong></div>',
            '<div class="cbt-finish-stat is-unanswered"><span>Belum Terjawab</span><strong>' + escapeHtml(unansweredQuestions) + '</strong></div>',
            '</div>',
            '<div class="cbt-finish-modal-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + escapeHtml(progressWidth) + '">',
            '<span class="cbt-finish-modal-progress-fill" style="width: ' + escapeHtml(progressWidth) + '%;"></span>',
            '</div>',
            '<p class="cbt-muted">Progress jawaban: ' + escapeHtml(progressLabel) + '%</p>',
            warningMarkup,
            finishLiveMarkup,
            '<div class="cbt-actions cbt-finish-modal-actions">',
            '<button class="cbt-button cbt-button-secondary" data-action="finish-confirm-cancel" type="button"' + (state.isFinishing || state.examLockedForPendingFinish ? ' disabled' : '') + '>Kembali Kerjakan</button>',
            '<button class="cbt-button cbt-button-primary" data-action="finish-confirm-submit" type="button"' + (state.isFinishing || state.examLockedForPendingFinish ? ' disabled' : '') + '>' + escapeHtml(finishSubmitLabel) + '</button>',
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function renderUserPhotoModal() {
        if (!state.userPhotoModalOpen) {
            return '';
        }

        var userPhoto = getCurrentUserPhoto();
        if (userPhoto === '') {
            return '';
        }

        var selectedExam = getSelectedExam();
        var userName = getCurrentUserName();
        var userInitial = getUserInitial(userName);
        var userKelas = state.user && state.user.kode_kelas ? String(state.user.kode_kelas) : '-';
        var userRuang = state.user && state.user.kode_ruang ? String(state.user.kode_ruang) : '-';
        var userAgama = state.user && state.user.agama ? String(state.user.agama) : '-';
        var activeExamTitle = selectedExam && selectedExam.title ? String(selectedExam.title) : '-';

        return [
            '<div class="cbt-user-photo-modal-overlay" data-action="close-user-photo">',
            '<section class="cbt-user-photo-modal" data-action="user-photo-modal-panel" role="dialog" aria-modal="true" aria-labelledby="cbt-user-photo-title">',
            '<button class="cbt-user-photo-modal-close" data-action="close-user-photo" type="button" aria-label="Tutup foto peserta">&times;</button>',
            '<div>',
            '<img class="cbt-user-photo-modal-image" src="' + escapeHtml(userPhoto) + '" alt="' + escapeHtml(userName) + '" loading="eager" decoding="async" data-cbt-profile-photo="modal" />',
            '<div class="cbt-user-photo-modal-image cbt-user-photo-modal-image-fallback" data-cbt-profile-photo-fallback hidden aria-hidden="true">' + escapeHtml(userInitial) + '</div>',
            '</div>',
            '<div class="cbt-user-photo-modal-info">',
            '<h3 id="cbt-user-photo-title">' + escapeHtml(userName || '-') + '</h3>',
            '<div class="cbt-user-photo-modal-row"><dt>Kelas</dt><dd>' + escapeHtml(userKelas) + '</dd></div>',
            '<div class="cbt-user-photo-modal-row"><dt>Ruangan</dt><dd>' + escapeHtml(userRuang) + '</dd></div>',
            '<div class="cbt-user-photo-modal-row"><dt>Agama</dt><dd>' + escapeHtml(userAgama) + '</dd></div>',
            '<div class="cbt-user-photo-modal-row"><dt>Ujian Aktif</dt><dd>' + escapeHtml(activeExamTitle) + '</dd></div>',
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function renderRichZoomModal() {
        if (!state.richZoomModalOpen || state.stage !== 'exam' || String(state.richZoomModalMarkup || '').trim() === '') {
            return '';
        }

        var modalType = String(state.richZoomModalType || 'image').toLowerCase() === 'table' ? 'table' : 'image';
        var modalTitle = String(state.richZoomModalTitle || (modalType === 'table' ? 'Tabel Soal' : 'Gambar Soal')).trim();
        var galleryCount = Math.max(0, Number(state.richZoomModalGalleryCount) || 0);
        var galleryIndex = Math.max(0, Number(state.richZoomModalGalleryIndex) || 0);
        var scaleMode = state.richZoomModalScaleMode === 'manual' ? 'manual' : 'fit';
        var scalePercent = Math.max(75, Number(state.richZoomModalScalePercent) || 100);
        var scaleLabel = scaleMode === 'manual' ? String(scalePercent) + '%' : 'FIT';
        var minScale = modalType === 'table' ? 75 : 75;
        var maxScale = modalType === 'table' ? 200 : 250;
        var imageGalleryActive = modalType === 'image' && galleryCount > 1;
        var galleryCounterMarkup = imageGalleryActive
            ? [
                '<div class="cbt-rich-zoom-gallery-nav" aria-label="Navigasi galeri gambar">',
                '<button class="cbt-rich-zoom-gallery-btn" data-action="rich-zoom-prev" type="button" aria-label="Gambar sebelumnya" title="Gambar sebelumnya"' + (galleryIndex <= 0 ? ' disabled' : '') + '>&lsaquo;</button>',
                '<span class="cbt-rich-zoom-gallery-counter">' + escapeHtml(galleryIndex + 1) + ' / ' + escapeHtml(galleryCount) + '</span>',
                '<button class="cbt-rich-zoom-gallery-btn" data-action="rich-zoom-next" type="button" aria-label="Gambar berikutnya" title="Gambar berikutnya"' + (galleryIndex >= (galleryCount - 1) ? ' disabled' : '') + '>&rsaquo;</button>',
                '</div>'
            ].join('')
            : '';
        var zoomToolbarMarkup = [
            '<div class="cbt-rich-zoom-controls" aria-label="Kontrol zoom konten">',
            '<button class="cbt-rich-zoom-control-btn" data-action="rich-zoom-scale-out" type="button" aria-label="Perkecil tampilan" title="Perkecil tampilan"' + ((scaleMode === 'manual' && scalePercent <= minScale) ? ' disabled' : '') + '>&minus;</button>',
            '<button class="cbt-rich-zoom-control-btn" data-action="rich-zoom-scale-in" type="button" aria-label="Perbesar tampilan" title="Perbesar tampilan"' + ((scaleMode === 'manual' && scalePercent >= maxScale) ? ' disabled' : '') + '>+</button>',
            '<button class="cbt-rich-zoom-control-chip' + (scaleMode === 'manual' && scalePercent === 100 ? ' is-active' : '') + '" data-action="rich-zoom-scale-reset" type="button" aria-label="Reset ke 100 persen" title="Reset ke 100 persen">100%</button>',
            '<button class="cbt-rich-zoom-control-chip' + (scaleMode === 'fit' ? ' is-active' : '') + '" data-action="rich-zoom-scale-fit" type="button" aria-label="Mode pas layar" title="Mode pas layar">Fit</button>',
            scaleMode === 'manual'
                ? '<span class="cbt-rich-zoom-scale-badge" aria-live="polite">' + escapeHtml(scaleLabel) + '</span>'
                : '',
            '</div>'
        ].join('');
        var subtitle = modalType === 'table'
            ? 'Gunakan Fit, 100%, atau tombol zoom lalu geser tabel untuk membaca kolom yang lebar.'
            : 'Gunakan tombol zoom untuk memperbesar detail gambar tanpa keluar dari fullscreen.';
        var modalBodyClass = 'cbt-rich-zoom-modal-body cbt-rich-zoom-modal-body--' + escapeHtml(modalType) + ' is-' + escapeHtml(scaleMode);
        var canvasClass = 'cbt-rich-zoom-canvas cbt-rich-zoom-canvas--' + escapeHtml(modalType) + ' is-' + escapeHtml(scaleMode);
        var canvasStyle = '';
        if (scaleMode === 'manual') {
            if (modalType === 'image') {
                canvasStyle = ' style="width: ' + escapeHtml(scalePercent) + '%;"';
            } else {
                canvasStyle = ' style="--cbt-rich-zoom-scale: ' + escapeHtml((scalePercent / 100).toFixed(2)) + ';"';
            }
        }

        return [
            '<div class="cbt-rich-zoom-modal-overlay" data-action="close-rich-zoom">',
            '<section class="cbt-rich-zoom-modal cbt-rich-zoom-modal--' + escapeHtml(modalType) + '" data-action="rich-zoom-modal-panel" role="dialog" aria-modal="true" aria-labelledby="cbt-rich-zoom-title">',
            '<button class="cbt-rich-zoom-modal-close" data-action="close-rich-zoom" type="button" aria-label="Tutup tampilan perbesar">&times;</button>',
            '<div class="cbt-rich-zoom-modal-head">',
            '<h3 id="cbt-rich-zoom-title">' + escapeHtml(modalTitle) + '</h3>',
            '<p class="cbt-subtitle">' + escapeHtml(subtitle) + '</p>',
            galleryCounterMarkup,
            zoomToolbarMarkup,
            '</div>',
            '<div class="' + modalBodyClass + '">',
            '<div class="' + canvasClass + '"' + canvasStyle + '>',
            state.richZoomModalMarkup,
            '</div>',
            '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    return {
        renderBody: renderBody,
        renderAuthProgressOverlay: renderAuthProgressOverlay,
        renderFinishConfirmModal: renderFinishConfirmModal,
        renderQuestionFontControls: renderQuestionFontControls,
        renderRichZoomModal: renderRichZoomModal,
        renderResultProgressOverlay: renderResultProgressOverlay,
        renderSessionRecoveryOverlay: renderSessionRecoveryOverlay,
        renderThemeToggleControl: renderThemeToggleControl,
        renderTopbar: renderTopbar,
        renderUserPhotoModal: renderUserPhotoModal
    };
}
