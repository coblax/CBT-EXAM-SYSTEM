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
                ? '<button class="cbt-user-chip-photo-button" data-action="open-user-photo" type="button" aria-label="Lihat foto profil ukuran besar"><img class="cbt-user-chip-photo" src="' + escapeHtml(userPhoto) + '" alt="' + escapeHtml(userName) + '" loading="lazy" decoding="async" /></button>'
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

    function renderFinishConfirmModal() {
        if (!state.finishConfirmOpen || state.stage !== 'exam') {
            return '';
        }

        var summary = state.finishConfirmSummary || deps.getExamProgressSummary();
        var totalQuestions = Number(summary.totalQuestions) || 0;
        var answeredQuestions = Number(summary.answeredQuestions) || 0;
        var unansweredQuestions = Number(summary.unansweredQuestions) || 0;
        var answeredPercentage = totalQuestions > 0 ? (answeredQuestions / totalQuestions) * 100 : 0;
        var progressWidth = Math.max(0, Math.min(100, answeredPercentage)).toFixed(2);
        var progressLabel = formatScoreValue(answeredPercentage);
        var warningMarkup = unansweredQuestions > 0
            ? '<div class="cbt-finish-modal-warning">Masih ada <strong>' + escapeHtml(unansweredQuestions) + '</strong> soal belum terjawab.</div>'
            : '<div class="cbt-finish-modal-ok">Semua soal sudah terjawab. Anda bisa lanjut kumpulkan ujian.</div>';

        return [
            '<div class="cbt-finish-modal-overlay">',
            '<section class="cbt-finish-modal" role="dialog" aria-modal="true" aria-labelledby="cbt-finish-modal-title">',
            '<h3 id="cbt-finish-modal-title">Konfirmasi Pengumpulan Ujian</h3>',
            '<p class="cbt-subtitle">Periksa jumlah jawaban sebelum ujian dikumpulkan.</p>',
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
            '<div class="cbt-actions cbt-finish-modal-actions">',
            '<button class="cbt-button cbt-button-secondary" data-action="finish-confirm-cancel" type="button"' + (state.isFinishing || state.examLockedForPendingFinish ? ' disabled' : '') + '>Kembali Kerjakan</button>',
            '<button class="cbt-button cbt-button-primary" data-action="finish-confirm-submit" type="button"' + (state.isFinishing || state.examLockedForPendingFinish ? ' disabled' : '') + '>' + (state.isFinishing ? 'Mengirim...' : 'Tetap Kumpulkan') + '</button>',
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
        var userKelas = state.user && state.user.kode_kelas ? String(state.user.kode_kelas) : '-';
        var userRuang = state.user && state.user.kode_ruang ? String(state.user.kode_ruang) : '-';
        var userAgama = state.user && state.user.agama ? String(state.user.agama) : '-';
        var activeExamTitle = selectedExam && selectedExam.title ? String(selectedExam.title) : '-';

        return [
            '<div class="cbt-user-photo-modal-overlay" data-action="close-user-photo">',
            '<section class="cbt-user-photo-modal" data-action="user-photo-modal-panel" role="dialog" aria-modal="true" aria-labelledby="cbt-user-photo-title">',
            '<button class="cbt-user-photo-modal-close" data-action="close-user-photo" type="button" aria-label="Tutup foto peserta">&times;</button>',
            '<div>',
            '<img class="cbt-user-photo-modal-image" src="' + escapeHtml(userPhoto) + '" alt="' + escapeHtml(userName) + '" loading="eager" decoding="async" />',
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

    return {
        renderBody: renderBody,
        renderFinishConfirmModal: renderFinishConfirmModal,
        renderQuestionFontControls: renderQuestionFontControls,
        renderThemeToggleControl: renderThemeToggleControl,
        renderTopbar: renderTopbar,
        renderUserPhotoModal: renderUserPhotoModal
    };
}
