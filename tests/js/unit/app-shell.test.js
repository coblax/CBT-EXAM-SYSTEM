import { describe, expect, it } from 'vitest';
import { createAppShellManager } from '../../../src/frontend/app/core/app-shell.js';

function createFixture(overrides = {}) {
    var state = Object.assign({
        stage: 'exam',
        uiTheme: 'light',
        richZoomModalOpen: true,
        richZoomModalType: 'image',
        richZoomModalTitle: 'Gambar Soal',
        richZoomModalMarkup: '<img src="/soal.png" alt="Soal" />',
        richZoomModalGalleryIndex: 1,
        richZoomModalGalleryCount: 3,
        richZoomModalScaleMode: 'manual',
        richZoomModalScalePercent: 125,
        fontScale: 100,
        remainingSeconds: 120,
        finishConfirmOpen: false,
        isFinishing: false,
        userPhotoModalOpen: false,
        user: null
    }, overrides.state || {});

    return createAppShellManager({
        state: state,
        escapeHtml: function (value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },
        fontScaleMax: 150,
        fontScaleMin: 80,
        formatFontScaleLabel: function (value) {
            return String(Math.round(Number(value) || 100)) + '%';
        },
        formatScoreValue: function (value) {
            return String(Math.round(Number(value) || 0));
        },
        formatSeconds: function (seconds) {
            return String(seconds) + 's';
        },
        getConfiguredSchoolLogoUrl: function () {
            return '';
        },
        getConfiguredSchoolName: function () {
            return 'SMK';
        },
        getCurrentUserName: function () {
            return 'User';
        },
        getCurrentUserPhoto: function () {
            return '';
        },
        getExamProgressSummary: function () {
            return {
                answeredQuestions: 0,
                totalQuestions: 0,
                unansweredQuestions: 0
            };
        },
        getSelectedExam: function () {
            return null;
        },
        getUserInitial: function () {
            return 'U';
        },
        renderAlert: function () {
            return '';
        },
        renderConfirmStage: function () {
            return '';
        },
        renderExamStageShell: function () {
            return '';
        },
        renderLoginStage: function () {
            return '';
        },
        renderResultStageShell: function () {
            return '';
        }
    });
}

describe('createAppShellManager rich zoom modal', function () {
    it('renders zoom controls and gallery controls for image galleries', function () {
        var manager = createFixture();
        var html = manager.renderRichZoomModal();

        expect(html).toContain('data-action="rich-zoom-scale-out"');
        expect(html).toContain('data-action="rich-zoom-scale-in"');
        expect(html).toContain('data-action="rich-zoom-scale-reset"');
        expect(html).toContain('data-action="rich-zoom-scale-fit"');
        expect(html).toContain('125%');
        expect(html).toContain('2 / 3');
        expect(html).toContain('Gunakan tombol zoom untuk memperbesar detail gambar tanpa keluar dari fullscreen.');
    });

    it('renders table zoom controls without gallery nav and uses fit copy', function () {
        var manager = createFixture({
            state: {
                richZoomModalType: 'table',
                richZoomModalTitle: 'Tabel Soal',
                richZoomModalMarkup: '<div class="cbt-rich-table-wrap"><table class="cbt-rich-content-table"><tbody><tr><td>Isi</td></tr></tbody></table></div>',
                richZoomModalGalleryCount: 0,
                richZoomModalGalleryIndex: 0,
                richZoomModalScaleMode: 'fit',
                richZoomModalScalePercent: 100
            }
        });
        var html = manager.renderRichZoomModal();

        expect(html).toContain('data-action="rich-zoom-scale-out"');
        expect(html).toContain('data-action="rich-zoom-scale-fit"');
        expect(html).not.toContain('data-action="rich-zoom-prev"');
        expect(html).not.toContain('cbt-rich-zoom-scale-badge');
        expect(html).toContain('Gunakan Fit, 100%, atau tombol zoom lalu geser tabel untuk membaca kolom yang lebar.');
    });
});
