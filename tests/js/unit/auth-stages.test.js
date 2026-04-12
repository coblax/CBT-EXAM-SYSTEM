import { describe, expect, it } from 'vitest';
import { createAuthStageManager } from '../../../src/frontend/app/core/auth-stages.js';

function createFixture(overrides = {}) {
    var state = Object.assign({
        authProgressVisible: false,
        authProgressMode: '',
        authProgressPercent: 0,
        authProgressStepIndex: 0,
        authProgressStepTotal: 0,
        authProgressStatus: '',
        authProgressDetail: '',
        busy: false,
        examPickerMobileOpen: false,
        examToken: '',
        exams: [],
        loginIdentifier: '',
        loginPassword: '',
        loginPasswordVisible: false,
        selectedExamId: 0,
        stage: 'confirm',
        user: {
            username: 'ayu',
            kode_kelas: 'XII IPA 1',
            kode_ruang: 'R-3'
        }
    }, overrides.state || {});

    var selectedExam = overrides.selectedExam || {
        id: 55,
        title: 'TOBK Biologi',
        subject_name: 'Biologi',
        starts_at: '2026-04-09 08:00:00',
        duration_minutes: 90,
        is_available_now: 1,
        is_class_allowed: 1,
        is_within_schedule: 1,
        latest_attempt_id: 0,
        latest_attempt_status: '',
        requires_token: 0,
        show_student_result: 1
    };

    state.exams = [selectedExam];
    state.selectedExamId = Number(selectedExam.id) || 0;

    return createAuthStageManager({
        clearMessages: function () {},
        escapeHtml: function (value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },
        formatDateTime: function (value) {
            return String(value || '-');
        },
        formatDateTimeCompact: function (value) {
            return String(value || '-');
        },
        formatScoreValue: function (value) {
            return String(Math.round(Number(value) || 0));
        },
        getConfiguredPluginAuthor: function () {
            return '';
        },
        getConfiguredPluginVersion: function () {
            return '';
        },
        getConfiguredSchoolLogoUrl: function () {
            return '';
        },
        getConfiguredSchoolMotto: function () {
            return '';
        },
        getConfiguredSchoolName: function () {
            return 'SMK';
        },
        getCurrentUserName: overrides.getCurrentUserName || function () {
            return 'Ayu';
        },
        getCurrentUserPhoto: overrides.getCurrentUserPhoto || function () {
            return '';
        },
        getLoginHeroSchoolBranding: function () {
            return {
                tag: '',
                title: 'SMK'
            };
        },
        getSelectedExam: function () {
            return selectedExam;
        },
        getUserInitial: overrides.getUserInitial || function () {
            return 'A';
        },
        persistAuthSession: function () {},
        recordTimeline: function () {},
        render: function () {},
        renderAlert: function () {
            return '';
        },
        state: state
    });
}

describe('createAuthStageManager', function () {
    it('renders inline refresh progress on confirm stage while exams are being reloaded', function () {
        var manager = createFixture({
            state: {
                busy: true
            }
        });

        var html = manager.renderConfirmStage();

        expect(html).toContain('data-action="reload-exams"');
        expect(html).toContain('MENYEGARKAN...');
        expect(html).toContain('Status terbaru sedang dicek.');
        expect(html).toContain('aria-label="Progress refresh ujian"');
    });

    it('renders expired in-progress attempt as finalizing instead of continue exam', function () {
        var manager = createFixture({
            selectedExam: {
                id: 55,
                title: 'TOBK Biologi',
                subject_name: 'Biologi',
                starts_at: '2026-04-09 08:00:00',
                duration_minutes: 90,
                is_available_now: 1,
                is_class_allowed: 1,
                is_within_schedule: 1,
                latest_attempt_id: 88,
                latest_attempt_status: 'in_progress',
                latest_attempt_finalize_pending: 1,
                latest_attempt_ui_state: 'finalizing',
                requires_token: 0,
                show_student_result: 1
            }
        });

        var html = manager.renderConfirmStage();

        expect(html).toContain('DIPROSES');
        expect(html).toContain('Memproses...');
        expect(html).toContain('Hasil sedang diproses.');
        expect(html).not.toContain('Lanjutkan Ujian');
        expect(html).toContain('data-action="start-exam" type="button" disabled');
    });

    it('renders confirm profile photo with a fallback for failed image loads', function () {
        var manager = createFixture({
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

        var html = manager.renderConfirmStage();

        expect(html).toContain('data-cbt-profile-photo="confirm"');
        expect(html).toContain('data-cbt-profile-photo-fallback hidden');
    });
});
