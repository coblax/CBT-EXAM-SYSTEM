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
        getCurrentUserName: function () {
            return 'Ayu';
        },
        getCurrentUserPhoto: function () {
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
        getUserInitial: function () {
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
});
