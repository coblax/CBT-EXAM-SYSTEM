import { describe, expect, it } from 'vitest';
import { createAppShellManager } from '../../../src/frontend/app/core/app-shell.js';

function createFixture(overrides = {}) {
    var state = Object.assign({
        authProgressVisible: false,
        authProgressMode: '',
        authProgressPercent: 0,
        authProgressStepIndex: 0,
        authProgressStepTotal: 0,
        authProgressStatus: '',
        authProgressDetail: '',
        resultProgressVisible: false,
        resultProgressPercent: 0,
        resultProgressStepIndex: 0,
        resultProgressStepTotal: 0,
        resultProgressStatus: '',
        resultProgressDetail: '',
        sessionRecoveryVisible: false,
        sessionRecoveryMode: '',
        sessionRecoveryPercent: 0,
        sessionRecoveryStepIndex: 0,
        sessionRecoveryStepTotal: 0,
        sessionRecoveryStatus: '',
        sessionRecoveryDetail: '',
        sessionRecoveryCanRetry: false,
        sessionRecoveryRetryCount: 0,
        sessionRecoverySlowStage: '',
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
        connectionStatus: 'online',
        finishConfirmOpen: false,
        lastSyncError: '',
        pendingSyncCount: 0,
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
        getCurrentUserName: overrides.getCurrentUserName || function () {
            return 'User';
        },
        getCurrentUserPhoto: overrides.getCurrentUserPhoto || function () {
            return '';
        },
        getExamProgressSummary: overrides.getExamProgressSummary || function () {
            return {
                answeredQuestions: 0,
                doubtfulQuestions: 0,
                doubtfulQuestionItems: [],
                totalQuestions: 0,
                unansweredQuestions: 0,
                unansweredQuestionItems: []
            };
        },
        getSelectedExam: function () {
            return null;
        },
        getUserInitial: overrides.getUserInitial || function () {
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

    it('renders profile photo markup with fallback for stale image domains', function () {
        var manager = createFixture({
            state: {
                userPhotoModalOpen: true,
                user: {
                    agama: 'Islam',
                    kode_kelas: 'XII IPA 1',
                    kode_ruang: 'R-3'
                }
            },
            getCurrentUserPhoto: function () {
                return 'https://exam.example.test/wp-content/uploads/cbt-user-import-photos/siswa-a.jpg';
            },
            getCurrentUserName: function () {
                return 'Ayu';
            },
            getUserInitial: function () {
                return 'A';
            }
        });

        expect(manager.renderTopbar()).toContain('data-cbt-profile-photo="user-chip"');
        expect(manager.renderTopbar()).toContain('data-cbt-profile-photo-fallback hidden');
        expect(manager.renderUserPhotoModal()).toContain('data-cbt-profile-photo="modal"');
        expect(manager.renderUserPhotoModal()).toContain('cbt-user-photo-modal-image-fallback');
    });

    it('renders auth progress overlay for login with staged progress copy', function () {
        var manager = createFixture({
            state: {
                authProgressVisible: true,
                authProgressMode: 'login',
                authProgressPercent: 72,
                authProgressStepIndex: 3,
                authProgressStepTotal: 4,
                authProgressStatus: 'Memuat daftar ujian',
                authProgressDetail: 'Kami sedang mengambil daftar exam untuk akun ini.'
            }
        });
        var html = manager.renderAuthProgressOverlay();

        expect(html).toContain('Login Sedang Diproses');
        expect(html).toContain('Mohon jangan refresh halaman saat proses login berjalan.');
        expect(html).toContain('Langkah 3/4');
        expect(html).toContain('Progress auth: 72%');
        expect(html).toContain('aria-label="Progress autentikasi"');
    });

    it('switches auth progress overlay copy for logout mode', function () {
        var manager = createFixture({
            state: {
                authProgressVisible: true,
                authProgressMode: 'logout',
                authProgressPercent: 82,
                authProgressStepIndex: 4,
                authProgressStepTotal: 4,
                authProgressStatus: 'Menutup sesi server',
                authProgressDetail: 'Token login sedang dinonaktifkan.'
            }
        });
        var html = manager.renderAuthProgressOverlay();

        expect(html).toContain('Logout Sedang Diproses');
        expect(html).toContain('Mohon jangan tutup halaman sampai proses logout selesai.');
        expect(html).toContain('Sesi Aman');
        expect(html).toContain('Progress auth: 82%');
        expect(html).toContain('cbt-auth-progress-card--logout');
    });

    it('renders a progress overlay while view-result is preparing the result screen', function () {
        var manager = createFixture({
            state: {
                resultProgressVisible: true,
                resultProgressPercent: 74,
                resultProgressStepIndex: 3,
                resultProgressStepTotal: 4,
                resultProgressStatus: 'Menyusun ringkasan nilai',
                resultProgressDetail: 'Kami sedang menyiapkan skor, status lulus, dan review jawaban.'
            }
        });
        var html = manager.renderResultProgressOverlay();

        expect(html).toContain('Menyiapkan Hasil Ujian');
        expect(html).toContain('Mohon jangan refresh halaman. Sistem sedang mengambil nilai dan review ujian Anda.');
        expect(html).toContain('Langkah 3/4');
        expect(html).toContain('Progress hasil: 74%');
        expect(html).toContain('aria-label="Progress lihat nilai"');
    });

    it('renders a recovery overlay for confirm restore without exam-only notes', function () {
        var manager = createFixture({
            state: {
                sessionRecoveryVisible: true,
                sessionRecoveryMode: 'confirm_restore',
                sessionRecoveryPercent: 58,
                sessionRecoveryStepIndex: 3,
                sessionRecoveryStepTotal: 4,
                sessionRecoveryStatus: 'Mengecek attempt aktif',
                sessionRecoveryDetail: 'Sistem sedang mencari sesi ujian yang perlu disambungkan lagi.',
                sessionRecoverySlowStage: 'normal'
            }
        });
        var html = manager.renderSessionRecoveryOverlay();

        expect(html).toContain('Memulihkan Konfirmasi Ujian');
        expect(html).toContain('Jangan refresh lagi. Sistem sedang menyambung sesi Anda.');
        expect(html).toContain('Langkah 3/4');
        expect(html).toContain('Progress pemulihan: 58%');
        expect(html).toContain('Sedang memulihkan sesi Anda');
        expect(html).not.toContain('Jawaban lokal tetap aman');
    });

    it('renders slow recovery retry controls for exam restore mode', function () {
        var manager = createFixture({
            state: {
                sessionRecoveryVisible: true,
                sessionRecoveryMode: 'exam_restore',
                sessionRecoveryPercent: 84,
                sessionRecoveryStepIndex: 6,
                sessionRecoveryStepTotal: 7,
                sessionRecoveryStatus: 'Memulihkan jawaban lokal',
                sessionRecoveryDetail: 'Jawaban tersimpan sedang dikembalikan ke tampilan ujian.',
                sessionRecoveryCanRetry: true,
                sessionRecoveryRetryCount: 2,
                sessionRecoverySlowStage: 'hold'
            }
        });
        var html = manager.renderSessionRecoveryOverlay();

        expect(html).toContain('Menyambung Sesi Ujian');
        expect(html).toContain('Jangan refresh lagi. Sesi masih dipulihkan.');
        expect(html).toContain('Jawaban lokal tetap aman dan akan disinkronkan setelah sesi pulih.');
        expect(html).toContain('data-action="retry-session-recovery"');
        expect(html).toContain('Percobaan sambung ulang: 2');
    });

    it('renders login ulang action when session recovery retry limit is reached', function () {
        var manager = createFixture({
            state: {
                sessionRecoveryVisible: true,
                sessionRecoveryMode: 'confirm_restore',
                sessionRecoveryStatus: 'Pemulihan sesi gagal',
                sessionRecoveryDetail: 'Batas percobaan sambung ulang tercapai.',
                sessionRecoveryCanRetry: false,
                sessionRecoveryRetryCount: 5,
                sessionRecoverySlowStage: 'failed'
            }
        });
        var html = manager.renderSessionRecoveryOverlay();

        expect(html).toContain('Login Ulang');
        expect(html).toContain('data-action="logout"');
        expect(html).not.toContain('data-action="retry-session-recovery"');
    });

    it('renders a final review modal with safe sync status when all questions are ready', function () {
        var manager = createFixture({
            state: {
                finishConfirmOpen: true
            },
            getExamProgressSummary: function () {
                return {
                    answeredQuestions: 3,
                    doubtfulQuestions: 0,
                    doubtfulQuestionItems: [],
                    totalQuestions: 3,
                    unansweredQuestions: 0,
                    unansweredQuestionItems: []
                };
            }
        });
        var html = manager.renderFinishConfirmModal();

        expect(html).toContain('REVIEW SEBELUM KUMPULKAN');
        expect(html).toContain('Online / aman');
        expect(html).toContain('Semua soal sudah terjawab, tidak ada tanda ragu-ragu, dan sinkronisasi aman.');
        expect(html).toContain('Saya Yakin Kumpulkan');
        expect(html).not.toContain('data-action="finish-review-unanswered"');
        expect(html).not.toContain('data-action="finish-review-doubtful"');
    });

    it('renders finish recovery retry and exit actions after the finish lock escape opens', function () {
        var manager = createFixture({
            state: {
                stage: 'exam',
                examLockedForPendingFinish: true,
                finishRecoveryCanExit: true,
                finishProgressPercent: 90,
                finishProgressStepIndex: 4,
                finishProgressStepTotal: 4,
                finishProgressStatus: 'Pemulihan hasil belum selesai',
                finishProgressDetail: 'Hasil belum bisa dimuat.'
            }
        });
        var html = manager.renderFinishConfirmModal();

        expect(html).toContain('data-action="retry-finish-recovery"');
        expect(html).toContain('Coba Pulihkan Lagi');
        expect(html).toContain('Keluar ke Login');
    });

    it('renders unanswered, doubtful, and pending sync warnings without blocking final submit', function () {
        var manager = createFixture({
            state: {
                connectionStatus: 'offline',
                finishConfirmOpen: true,
                lastSyncError: 'Timeout unit test',
                pendingSyncCount: 2
            },
            getExamProgressSummary: function () {
                return {
                    answeredQuestions: 3,
                    doubtfulQuestions: 1,
                    doubtfulQuestionItems: [
                        { index: 1, label: '2', questionId: 102 }
                    ],
                    partialQuestionItems: [
                        { index: 0, label: '1', progressLabel: '2/4', questionId: 101, status: 'partial' },
                        { index: 2, label: '3', progressLabel: '1/3', questionId: 103, status: 'partial' }
                    ],
                    partialQuestionNumbers: ['1', '3'],
                    totalQuestions: 5,
                    unansweredQuestions: 2,
                    unansweredQuestionItems: [
                        { index: 3, label: '4', questionId: 104 },
                        { index: 4, label: '5', questionId: 105 }
                    ],
                    pendingSyncQuestionItems: [
                        { index: 0, label: '1', questionId: 101 },
                        { index: 2, label: '3', questionId: 103 }
                    ],
                    pendingSyncQuestionNumbers: ['1', '3']
                };
            }
        });
        var html = manager.renderFinishConfirmModal();

        expect(html).toContain('Belum Terjawab');
        expect(html).toContain('Ragu-Ragu');
        expect(html).toContain('Offline / lokal aman');
        expect(html).toContain('2 jawaban menunggu koneksi.');
        expect(html).toContain('Masih ada 2 soal belum dijawab.');
        expect(html).toContain('Ada 2 soal dengan jawaban parsial.');
        expect(html).toContain('Ada 1 soal ditandai ragu-ragu.');
        expect(html).toContain('Soal 1, 3 masih menunggu sinkronisasi.');
        expect(html).toContain('data-action="finish-review-unanswered"');
        expect(html).toContain('data-action="finish-review-partial"');
        expect(html).toContain('data-action="finish-review-doubtful"');
        expect(html).toContain('data-action="finish-review-pending-sync"');
        expect(html).toContain('Cek Belum Dijawab');
        expect(html).toContain('Jawaban Parsial');
        expect(html).toContain('Cek Jawaban Parsial');
        expect(html).toContain('<small>2/4</small>');
        expect(html).toContain('<small>1/3</small>');
        expect(html).toContain('Cek Ragu-Ragu');
        expect(html).toContain('Belum Sinkron');
        expect(html).toContain('Cek Belum Sinkron');
        expect(html).toContain('Saya Yakin Kumpulkan');
        expect(html).not.toContain('data-action="finish-confirm-submit" type="button" disabled');
    });

    it('renders early finish progress feedback before the final submit enters the finishing stage', function () {
        var manager = createFixture({
            state: {
                finishConfirmOpen: false,
                finishProgressPercent: 12,
                finishProgressStepIndex: 1,
                finishProgressStepTotal: 4,
                finishProgressStatus: 'Mengecek jawaban terakhir',
                finishProgressDetail: 'Menyimpan posisi terakhir dan memastikan semua jawaban ikut tersinkron.',
                examLockedForPendingFinish: true
            },
            getExamProgressSummary: function () {
                return {
                    answeredQuestions: 1,
                    doubtfulQuestions: 0,
                    doubtfulQuestionItems: [],
                    partialQuestionItems: [
                        { index: 0, label: '1', progressLabel: '1/2', questionId: 101, status: 'partial' }
                    ],
                    totalQuestions: 1,
                    unansweredQuestions: 0,
                    unansweredQuestionItems: []
                };
            }
        });
        var html = manager.renderFinishConfirmModal();

        expect(html).toContain('Proses');
        expect(html).toContain('Siap');
        expect(html).toContain('Langkah 1/4');
        expect(html).toContain('Progress finalisasi: 12%');
        expect(html).toContain('Proses...');
        expect(html).toContain('aria-label="Progress pengumpulan ujian"');
        expect(html).toContain('data-action="finish-review-partial" type="button" disabled');
    });
});
