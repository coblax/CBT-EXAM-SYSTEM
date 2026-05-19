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
        examListFilter: 'all',
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
            role: 'student',
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
    var exams = Array.isArray(overrides.exams) ? overrides.exams.slice() : [selectedExam];
    var selectedExamId = Number(
        overrides.selectedExamId !== undefined
            ? overrides.selectedExamId
            : ((overrides.selectedExam || exams[0] || {}).id)
    ) || 0;

    state.exams = exams;
    state.selectedExamId = selectedExamId;

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
            for (var index = 0; index < state.exams.length; index++) {
                if (Number(state.exams[index] && state.exams[index].id) === Number(state.selectedExamId)) {
                    return state.exams[index];
                }
            }

            return state.exams.length ? state.exams[0] : null;
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

    it('renders matching desktop and mobile exam option counts', function () {
        var exams = [
            {
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
            },
            {
                id: 56,
                title: 'TOBK Kimia',
                subject_name: 'Kimia',
                starts_at: '2026-04-09 10:00:00',
                duration_minutes: 60,
                is_available_now: 0,
                is_class_allowed: 1,
                is_within_schedule: 0,
                availability_reason: 'not_started',
                latest_attempt_id: 0,
                latest_attempt_status: '',
                requires_token: 0,
                show_student_result: 1
            }
        ];
        var manager = createFixture({
            exams: exams,
            selectedExamId: 55,
            state: {
                examPickerMobileOpen: true
            }
        });

        var html = manager.renderConfirmStage();

        expect((html.match(/data-action="select-exam"/g) || []).length).toBe(2);
        expect((html.match(/data-action="select-exam-mobile"/g) || []).length).toBe(2);
        expect(html).toContain('2 ujian');
    });

    it('renders exam filter tabs with category counts', function () {
        var manager = createFixture({
            exams: buildFilterExamList(),
            selectedExamId: 101
        });

        var html = manager.renderConfirmStage();

        expect(html).toContain('data-action="set-exam-filter" data-filter="all" type="button" role="tab" aria-selected="true" aria-pressed="true"><span>Semua</span><strong>7</strong>');
        expect(html).toContain('data-action="set-exam-filter" data-filter="active" type="button" role="tab" aria-selected="false" aria-pressed="false"><span>Aktif</span><strong>2</strong>');
        expect(html).toContain('data-action="set-exam-filter" data-filter="upcoming" type="button" role="tab" aria-selected="false" aria-pressed="false"><span>Akan Datang</span><strong>1</strong>');
        expect(html).toContain('data-action="set-exam-filter" data-filter="completed" type="button" role="tab" aria-selected="false" aria-pressed="false"><span>Selesai</span><strong>3</strong>');
    });

    it('filters active exams in both desktop list and mobile dropdown', function () {
        var manager = createFixture({
            exams: buildFilterExamList(),
            selectedExamId: 101,
            state: {
                examListFilter: 'active',
                examPickerMobileOpen: true
            }
        });

        var html = manager.renderConfirmStage();

        expect((html.match(/data-action="select-exam"/g) || []).length).toBe(2);
        expect((html.match(/data-action="select-exam-mobile"/g) || []).length).toBe(2);
        expect(html).toContain('Ready Now');
        expect(html).toContain('Continue Attempt');
        expect(html).not.toContain('Future Exam');
        expect(html).not.toContain('Completed Exam');
        expect(html).not.toContain('Finalizing Exam');
        expect(html).not.toContain('Ended Schedule');
    });

    it('filters upcoming exams by not_started availability reason', function () {
        var manager = createFixture({
            exams: buildFilterExamList(),
            selectedExamId: 103,
            state: {
                examListFilter: 'upcoming',
                examPickerMobileOpen: true
            }
        });

        var html = manager.renderConfirmStage();

        expect((html.match(/data-action="select-exam"/g) || []).length).toBe(1);
        expect((html.match(/data-action="select-exam-mobile"/g) || []).length).toBe(1);
        expect(html).toContain('Future Exam');
        expect(html).not.toContain('Ready Now');
        expect(html).not.toContain('Completed Exam');
    });

    it('filters completed exams including finalizing and ended schedules', function () {
        var manager = createFixture({
            exams: buildFilterExamList(),
            selectedExamId: 104,
            state: {
                examListFilter: 'completed',
                examPickerMobileOpen: true
            }
        });

        var html = manager.renderConfirmStage();

        expect((html.match(/data-action="select-exam"/g) || []).length).toBe(3);
        expect((html.match(/data-action="select-exam-mobile"/g) || []).length).toBe(3);
        expect(html).toContain('Completed Exam');
        expect(html).toContain('Finalizing Exam');
        expect(html).toContain('Ended Schedule');
        expect(html).not.toContain('Ready Now');
        expect(html).not.toContain('Future Exam');
    });

    it('auto-selects the first exam inside a newly selected non-empty filter', function () {
        var manager = createFixture({
            exams: buildFilterExamList(),
            selectedExamId: 101
        });

        manager.updateExamListFilter('upcoming');
        var html = manager.renderConfirmStage();

        expect(html).toContain('aria-selected="true" aria-pressed="true"><span>Akan Datang</span><strong>1</strong>');
        expect(html).toContain('<p class="cbt-confirm-selected-title">Future Exam</p>');
    });

    it('renders an empty filter state without clearing the selected exam', function () {
        var manager = createFixture({
            exams: [
                buildFilterExam({
                    id: 201,
                    title: 'Locked Exam',
                    is_available_now: 0,
                    is_class_allowed: 0,
                    is_within_schedule: 1
                })
            ],
            selectedExamId: 201,
            state: {
                examListFilter: 'upcoming'
            }
        });

        var html = manager.renderConfirmStage();

        expect(html).toContain('Tidak ada ujian pada filter Akan Datang.');
        expect((html.match(/data-action="select-exam"/g) || []).length).toBe(0);
        expect(html).toContain('<p class="cbt-confirm-selected-title">Locked Exam</p>');
    });

    it('renders the updated empty-state copy when no exam remains visible', function () {
        var manager = createFixture({
            exams: [],
            selectedExamId: 0
        });

        var html = manager.renderConfirmStage();

        expect(html).toContain('Belum Ada Exam Aktif');
        expect(html).toContain('Akun ini belum memiliki ujian yang bisa dikerjakan atau dilihat saat ini.');
    });

    it('renders full technical Redis diagnostics for administrators', function () {
        var manager = createFixture({
            state: {
                adminDiagnosticResult: buildDiagnosticResult({
                    diagnostics: {
                        redis_host: '/var/run/redis/redis.sock',
                        redis_database: 5,
                        storage_key: 'cbt_start_snapshot:exam:55:rev:12:abcdefghijklmnopqrstuvwxyz:v2:index',
                        snapshot_item_count: 330,
                        snapshot_payload_bytes: 14520,
                        snapshot_ttl_seconds: 43100,
                        storage_shape: 'start_per_question_v2',
                        v2_index_status: 'ready',
                        v2_fragment_count: 330,
                        v2_missing_fragment_count: 0,
                        fallback_reason: '',
                        revision_meta: {
                            version: 12,
                            invalidated_at: '2026-05-19 20:00:00',
                            signature: 'abcdefghijklmnopqrstuvwxyz0123456789'
                        },
                        repair_status: 'clean',
                        repair_message: ''
                    }
                }),
                user: {
                    username: 'admin',
                    role: 'administrator',
                    kode_kelas: 'STAFF',
                    kode_ruang: 'OPS'
                }
            }
        });

        var html = manager.renderConfirmStage();

        expect(html).toContain('Admin Diagnostic: Redis Preflight');
        expect(html).toContain('READY | v2 fragmented | 330 soal | TTL 11j 58m | payload index 14.18 KB');
        expect(html).toContain('Koneksi Redis');
        expect(html).toContain('/var/run/redis/redis.sock');
        expect(html).toContain('Snapshot');
        expect(html).toContain('YA');
        expect(html).toContain('Storage Key');
        expect(html).toContain('cbt_start_snapshot:exa...');
        expect(html).toContain('title="cbt_start_snapshot:exam:55:rev:12:abcdefghijklmnopqrstuvwxyz:v2:index"');
        expect(html).toContain('V2 Fragment');
        expect(html).toContain('330');
        expect(html).toContain('Revision &amp; Repair');
        expect(html).toContain('abcdef');
        expect(html).toContain('14520 bytes (14.18 KB)');
    });

    it('formats Redis diagnostic TTL edge cases for admins', function () {
        [
            [-2, 'missing'],
            [-1, 'no expiry'],
            [0, 'expired'],
            [3552, '59 menit 12 detik']
        ].forEach(function (entry) {
            var manager = createFixture({
                state: {
                    adminDiagnosticResult: buildDiagnosticResult({
                        diagnostics: {
                            snapshot_ttl_seconds: entry[0]
                        }
                    }),
                    user: {
                        username: 'admin',
                        role: 'administrator',
                        kode_kelas: 'STAFF',
                        kode_ruang: 'OPS'
                    }
                }
            });

            expect(manager.renderConfirmStage()).toContain('TTL ' + entry[1]);
        });
    });

    it('renders Redis unavailable diagnostics with connection errors', function () {
        var manager = createFixture({
            state: {
                adminDiagnosticResult: buildDiagnosticResult({
                    ping_success: false,
                    redis_status: 'disconnected',
                    snapshot_status: 'unavailable',
                    diagnostics: {
                        redis_available: false,
                        redis_error: 'Permission denied',
                        redis_host: '/var/run/redis/redis.sock',
                        snapshot_status: 'unavailable',
                        snapshot_message: 'Redis start snapshot tidak tersedia.',
                        snapshot_ttl_seconds: -2
                    }
                }),
                user: {
                    username: 'admin',
                    role: 'administrator',
                    kode_kelas: 'STAFF',
                    kode_ruang: 'OPS'
                }
            }
        });

        var html = manager.renderConfirmStage();

        expect(html).toContain('GAGAL/MATI');
        expect(html).toContain('UNAVAILABLE');
        expect(html).toContain('Permission denied');
        expect(html).toContain('Redis start snapshot tidak tersedia.');
    });

    it('renders snapshot miss reason and repair messages when cache is not ready', function () {
        var manager = createFixture({
            state: {
                adminDiagnosticResult: buildDiagnosticResult({
                    snapshot_status: 'miss',
                    warmup_error: 'Warmup belum selesai.',
                    diagnostics: {
                        snapshot_exists: false,
                        snapshot_valid: false,
                        snapshot_status: 'miss',
                        snapshot_miss_reason: 'revision_unavailable',
                        snapshot_miss_reason_label: 'Revision belum tersedia',
                        snapshot_message: 'Start snapshot belum ditemukan.',
                        repair_status: 'queued_auto_heal',
                        repair_message: 'Auto-heal sudah diantrikan.',
                        snapshot_ttl_seconds: -2
                    }
                }),
                user: {
                    username: 'admin',
                    role: 'administrator',
                    kode_kelas: 'STAFF',
                    kode_ruang: 'OPS'
                }
            }
        });

        var html = manager.renderConfirmStage();

        expect(html).toContain('Revision belum tersedia');
        expect(html).toContain('Reason: revision_unavailable');
        expect(html).toContain('Start snapshot belum ditemukan.');
        expect(html).toContain('Auto-heal sudah diantrikan.');
        expect(html).toContain('Warmup belum selesai.');
    });

    it('does not render Redis diagnostics for non-admin users', function () {
        var manager = createFixture({
            state: {
                adminDiagnosticResult: buildDiagnosticResult()
            }
        });

        var html = manager.renderConfirmStage();

        expect(html).not.toContain('Admin Diagnostic: Redis Preflight');
        expect(html).not.toContain('Koneksi Redis');
    });
});

function buildFilterExam(overrides = {}) {
    var id = Number(overrides.id) || 101;
    return Object.assign({
        availability_reason: '',
        duration_minutes: 90,
        id: id,
        is_available_now: 1,
        is_class_allowed: 1,
        is_within_schedule: 1,
        latest_attempt_finalize_pending: 0,
        latest_attempt_id: 0,
        latest_attempt_percentage: 0,
        latest_attempt_status: '',
        requires_token: 0,
        show_student_result: 1,
        starts_at: '2026-04-09 08:00:00',
        status: 'publish',
        subject_name: 'Biologi',
        title: 'Exam ' + String(id)
    }, overrides);
}

function buildFilterExamList() {
    return [
        buildFilterExam({
            id: 101,
            title: 'Ready Now'
        }),
        buildFilterExam({
            id: 102,
            title: 'Continue Attempt',
            is_available_now: 0,
            latest_attempt_id: 501,
            latest_attempt_status: 'in_progress'
        }),
        buildFilterExam({
            id: 103,
            title: 'Future Exam',
            availability_reason: 'not_started',
            is_available_now: 0,
            is_within_schedule: 0
        }),
        buildFilterExam({
            id: 104,
            title: 'Completed Exam',
            is_available_now: 1,
            latest_attempt_id: 502,
            latest_attempt_status: 'completed'
        }),
        buildFilterExam({
            id: 105,
            title: 'Finalizing Exam',
            is_available_now: 1,
            latest_attempt_finalize_pending: 1,
            latest_attempt_id: 503,
            latest_attempt_status: 'in_progress'
        }),
        buildFilterExam({
            id: 106,
            title: 'Ended Schedule',
            availability_reason: 'ended',
            is_available_now: 0,
            is_within_schedule: 0
        }),
        buildFilterExam({
            id: 107,
            title: 'Class Locked',
            availability_reason: 'class_denied',
            is_available_now: 0,
            is_class_allowed: 0
        })
    ];
}

function buildDiagnosticResult(overrides = {}) {
    var diagnostics = Object.assign({
        redis_available: true,
        redis_error: '',
        redis_host: '127.0.0.1',
        redis_database: 2,
        revision_meta: {
            version: 1,
            invalidated_at: '',
            signature: 'sig-ready'
        },
        storage_key: 'cbt_start_snapshot:exam:55:rev:1:sig-ready:v2:index',
        snapshot_exists: true,
        snapshot_valid: true,
        snapshot_status: 'ready',
        snapshot_miss_reason: '',
        snapshot_miss_reason_label: '',
        snapshot_message: 'Start snapshot Redis siap dipakai untuk kontrak start_attempt.',
        repair_status: '',
        repair_message: '',
        storage_shape: 'start_per_question_v2',
        v2_index_status: 'ready',
        v2_fragment_count: 330,
        v2_missing_fragment_count: 0,
        fallback_reason: '',
        snapshot_item_count: 330,
        snapshot_payload_bytes: 14520,
        snapshot_ttl_seconds: 43100,
        question_count: 330
    }, overrides.diagnostics || {});
    var resultOverrides = Object.assign({}, overrides);
    delete resultOverrides.diagnostics;

    return Object.assign({
        diagnostics: diagnostics,
        exam_id: 55,
        item_count: diagnostics.snapshot_item_count || diagnostics.question_count || 0,
        latency_ms: 113.07,
        payload_bytes: diagnostics.snapshot_payload_bytes || 0,
        ping_success: true,
        question_count: diagnostics.question_count || 0,
        redis_status: diagnostics.redis_available ? 'connected' : 'disconnected',
        snapshot_message: diagnostics.snapshot_message || '',
        snapshot_miss_reason: diagnostics.snapshot_miss_reason || '',
        snapshot_miss_reason_label: diagnostics.snapshot_miss_reason_label || '',
        snapshot_status: diagnostics.snapshot_status || 'ready',
        ttl_seconds: diagnostics.snapshot_ttl_seconds,
        warmup_attempted: true,
        warmup_error: ''
    }, resultOverrides);
}
