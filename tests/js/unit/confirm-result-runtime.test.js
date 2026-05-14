import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AUTH_SESSION_STORAGE_KEY, getFrontendConfig, createInitialState } from '../../../src/frontend/app/core/config.js';
import { createAuthSessionManager } from '../../../src/frontend/app/core/auth-session.js';
import { createBrowserStorageAccess } from '../../../src/frontend/app/core/browser-storage.js';
import { LEGACY_HANDOFF_STORAGE_KEY } from '../../../src/frontend/app/core/legacy-handoff.js';

describe('confirm runtime', function () {
    beforeEach(function () {
        vi.resetModules();
        document.body.innerHTML = '<div id="cbt-exam-app"></div>';
        window.CBTExamFrontendConfig = {
            frontendMode: 'student',
            restBasePath: '/wp-json/cbt/v1/',
            securityLogEvents: 0
        };
    });

    it('loads exams for an authenticated session and renders the confirm list', async function () {
        var fixture = createRuntimeFixture();
        mockFetchRoutes({
            exams: [buildExamsPayload([
                buildExam(15, { title: 'Matematika' }),
                buildExam(16, { title: 'Bahasa Indonesia' })
            ])]
        });

        var module = await import('../../../src/frontend/app/stages/confirm-runtime.js');
        await module.mountConfirmStage(fixture.context, {});

        expect(fixture.state.exams).toHaveLength(2);
        expect(fixture.root.textContent).toContain('Matematika');
        expect(fixture.root.textContent).toContain('Bahasa Indonesia');
        expect(fixture.state.selectedExamId).toBe(0);
    });

    it('reloads exams and keeps failures visible without clearing local auth', async function () {
        var fixture = createRuntimeFixture();
        var fetchMock = mockFetchRoutes({
            exams: [
                buildExamsPayload([buildExam(15, { title: 'Matematika' })]),
                {
                    error: true,
                    message: 'Server daftar ujian gagal.'
                }
            ]
        });

        var module = await import('../../../src/frontend/app/stages/confirm-runtime.js');
        await module.mountConfirmStage(fixture.context, {});

        clickAction(fixture.root, 'reload-exams');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fixture.state.token).toBe('token-123');
        expect(fixture.root.textContent).toContain('Server daftar ujian gagal.');
    });

    it('selects exams from desktop/mobile actions and persists the selected id', async function () {
        var fixture = createRuntimeFixture();
        mockFetchRoutes({
            exams: [buildExamsPayload([
                buildExam(15, { title: 'Matematika' }),
                buildExam(16, { title: 'Fisika' })
            ])]
        });

        var module = await import('../../../src/frontend/app/stages/confirm-runtime.js');
        await module.mountConfirmStage(fixture.context, {});

        clickAction(fixture.root, 'select-exam', '16');
        await flushPromises();

        expect(fixture.state.selectedExamId).toBe(16);
        var persisted = JSON.parse(sessionStorage.getItem(AUTH_SESSION_STORAGE_KEY));
        expect(persisted.selected_exam_id).toBe(16);
    });

    it('hands start exam to legacy with a one-shot intent payload', async function () {
        var fixture = createRuntimeFixture();
        fixture.context.loadLegacyRuntime = vi.fn(function (options) {
            sessionStorage.setItem(LEGACY_HANDOFF_STORAGE_KEY, JSON.stringify(options.handoffIntent));
            return Promise.resolve();
        });
        mockFetchRoutes({
            exams: [buildExamsPayload([buildExam(15, {
                requires_token: 1,
                title: 'Matematika'
            })])]
        });

        var module = await import('../../../src/frontend/app/stages/confirm-runtime.js');
        await module.mountConfirmStage(fixture.context, {});

        var tokenInput = fixture.root.querySelector('[name="exam_token"]');
        tokenInput.value = 'AB12CD';
        tokenInput.dispatchEvent(new Event('input', { bubbles: true }));
        clickAction(fixture.root, 'start-exam');
        await flushPromises();

        expect(fixture.context.loadLegacyRuntime).toHaveBeenCalledTimes(1);
        expect(fixture.context.loadLegacyRuntime.mock.calls[0][0]).toMatchObject({
            reason: 'start-exam',
            handoffIntent: {
                action: 'start-exam',
                exam_token: 'AB12CD',
                selected_exam_id: 15
            }
        });
        expect(JSON.parse(sessionStorage.getItem(LEGACY_HANDOFF_STORAGE_KEY)).selected_exam_id).toBe(15);
    });

    it('routes view result to the result runtime without loading legacy', async function () {
        var fixture = createRuntimeFixture();
        mockFetchRoutes({
            exams: [buildExamsPayload([buildExam(15, {
                latest_attempt_id: 501,
                latest_attempt_status: 'completed',
                title: 'Matematika'
            })])]
        });

        var module = await import('../../../src/frontend/app/stages/confirm-runtime.js');
        await module.mountConfirmStage(fixture.context, {});

        clickAction(fixture.root, 'view-result');
        await flushPromises();

        expect(fixture.context.transitionTo).toHaveBeenCalledWith('result', expect.objectContaining({
            reason: 'view-result',
            selectedExamId: 15
        }));
        expect(fixture.context.loadLegacyRuntime).not.toHaveBeenCalled();
    });

    it('logs out only after the server accepts logout', async function () {
        var fixture = createRuntimeFixture();
        mockFetchRoutes({
            exams: [buildExamsPayload([buildExam(15)])],
            logout: { ok: true }
        });

        var module = await import('../../../src/frontend/app/stages/confirm-runtime.js');
        await module.mountConfirmStage(fixture.context, {});

        fixture.context.authSession.persistAuthSession();
        clickAction(fixture.root, 'logout');
        await flushPromises();

        expect(fixture.context.transitionTo).toHaveBeenCalledWith('login', expect.objectContaining({ reason: 'logout' }));
        expect(sessionStorage.getItem(AUTH_SESSION_STORAGE_KEY)).toBe(null);
    });

    it('keeps local auth when logout fails', async function () {
        var fixture = createRuntimeFixture();
        mockFetchRoutes({
            exams: [buildExamsPayload([buildExam(15)])],
            logout: {
                error: true,
                message: 'Logout ditolak server.'
            }
        });

        var module = await import('../../../src/frontend/app/stages/confirm-runtime.js');
        await module.mountConfirmStage(fixture.context, {});

        fixture.context.authSession.persistAuthSession();
        clickAction(fixture.root, 'logout');
        await flushPromises();

        expect(fixture.context.transitionTo).not.toHaveBeenCalledWith('login', expect.anything());
        expect(JSON.parse(sessionStorage.getItem(AUTH_SESSION_STORAGE_KEY)).token).toBe('token-123');
        expect(fixture.root.textContent).toContain('Logout ditolak server.');
    });
});

describe('result runtime', function () {
    beforeEach(function () {
        vi.resetModules();
        document.body.innerHTML = '<div id="cbt-exam-app"></div>';
        window.CBTExamFrontendConfig = {
            frontendMode: 'student',
            restBasePath: '/wp-json/cbt/v1/',
            securityLogEvents: 0
        };
    });

    it('fetches a full result payload and renders the result stage', async function () {
        var completedExam = buildExam(15, {
            latest_attempt_id: 501,
            latest_attempt_status: 'completed',
            title: 'Matematika'
        });
        var fixture = createRuntimeFixture({
            exams: [completedExam],
            selectedExamId: 15
        });
        mockFetchRoutes({
            exams: [buildExamsPayload([completedExam])],
            result: buildResultPayload({
                attemptId: 501,
                score: 80,
                maxScore: 100
            })
        });

        var module = await import('../../../src/frontend/app/stages/result-runtime.js');
        await module.mountResultStage(fixture.context, {});

        expect(fixture.state.result).toMatchObject({
            attempt_id: 501,
            score: 80,
            max_score: 100,
            show_student_result: 1
        });
        expect(fixture.root.textContent).toContain('Matematika');
        expect(fixture.root.textContent).toContain('80');
    });

    it('renders restricted result payloads without score or review details', async function () {
        var completedExam = buildExam(15, {
            latest_attempt_id: 502,
            latest_attempt_status: 'completed',
            show_student_result: 0,
            title: 'Kimia'
        });
        var fixture = createRuntimeFixture({
            exams: [completedExam],
            selectedExamId: 15
        });
        mockFetchRoutes({
            exams: [buildExamsPayload([completedExam])],
            result: buildResultPayload({
                attemptId: 502,
                maxScore: 100,
                score: 95,
                showStudentResult: 0
            })
        });

        var module = await import('../../../src/frontend/app/stages/result-runtime.js');
        await module.mountResultStage(fixture.context, {});

        expect(fixture.state.result).toMatchObject({
            score: 0,
            max_score: 0,
            show_student_result: 0
        });
        expect(fixture.root.textContent).toContain('HASIL BELUM DITAMPILKAN');
    });

    it('shows a safe error when the selected exam has no result attempt', async function () {
        var fixture = createRuntimeFixture({
            exams: [buildExam(15, { latest_attempt_id: 0, latest_attempt_status: 'completed' })],
            selectedExamId: 15
        });
        mockFetchRoutes({});

        var module = await import('../../../src/frontend/app/stages/result-runtime.js');
        await module.mountResultStage(fixture.context, { skipExamRefresh: true });

        expect(fixture.root.textContent).toContain('Hasil ujian untuk exam ini belum tersedia.');
    });

    it('returns to confirm without loading legacy', async function () {
        var completedExam = buildExam(15, {
            latest_attempt_id: 501,
            latest_attempt_status: 'completed'
        });
        var fixture = createRuntimeFixture({
            exams: [completedExam],
            selectedExamId: 15
        });
        mockFetchRoutes({
            result: buildResultPayload({ attemptId: 501 })
        });

        var module = await import('../../../src/frontend/app/stages/result-runtime.js');
        await module.mountResultStage(fixture.context, { skipExamRefresh: true });

        clickAction(fixture.root, 'back-confirm');
        await flushPromises();

        expect(fixture.context.transitionTo).toHaveBeenCalledWith('confirm', expect.objectContaining({
            reason: 'back-confirm'
        }));
        expect(fixture.context.loadLegacyRuntime).not.toHaveBeenCalled();
    });

    it('hands off to legacy when refreshed status returns to in progress', async function () {
        var completedExam = buildExam(15, {
            latest_attempt_id: 501,
            latest_attempt_status: 'completed'
        });
        var activeExam = buildExam(15, {
            latest_attempt_id: 501,
            latest_attempt_status: 'in_progress'
        });
        var fixture = createRuntimeFixture({
            exams: [completedExam],
            selectedExamId: 15
        });
        mockFetchRoutes({
            exams: [buildExamsPayload([activeExam])]
        });

        var module = await import('../../../src/frontend/app/stages/result-runtime.js');
        await module.mountResultStage(fixture.context, {});

        expect(fixture.context.loadLegacyRuntime).toHaveBeenCalledWith(expect.objectContaining({
            reason: 'result-reroute-start-exam',
            handoffIntent: expect.objectContaining({
                action: 'start-exam',
                selected_exam_id: 15,
                skip_exam_refresh: true
            })
        }));
    });

    it('shows fallback and retry affordance when the result renderer chunk fails', async function () {
        vi.doMock('../../../src/frontend/app/core/dynamic-loader.js', async function (importOriginal) {
            var actual = await importOriginal();
            return Object.assign({}, actual, {
                loadResultRendererModule: vi.fn(function () {
                    return Promise.reject(new Error('chunk boom'));
                })
            });
        });

        var completedExam = buildExam(15, {
            latest_attempt_id: 501,
            latest_attempt_status: 'completed',
            title: 'Matematika'
        });
        var fixture = createRuntimeFixture({
            exams: [completedExam],
            selectedExamId: 15
        });
        mockFetchRoutes({
            result: buildResultPayload({ attemptId: 501, score: 70, maxScore: 100 })
        });

        var module = await import('../../../src/frontend/app/stages/result-runtime.js');
        await module.mountResultStage(fixture.context, { skipExamRefresh: true });

        expect(fixture.state.result.score).toBe(70);
        expect(fixture.root.textContent).toContain('chunk boom');
        expect(fixture.root.querySelector('[data-action="retry-load-result-stage"]')).not.toBe(null);
    });
});

function createRuntimeFixture(options) {
    options = options || {};
    var root = document.getElementById('cbt-exam-app');
    var config = getFrontendConfig(window);
    var browserStorage = createBrowserStorageAccess(window);
    var state = createInitialState(window);
    state.token = 'token-123';
    state.user = {
        agama: 'Islam',
        display_name: 'Ayu Sari',
        email: 'ayu@example.test',
        foto: '',
        kode_kelas: 'XII-A',
        kode_ruang: 'R1',
        role: 'student',
        user_id: 7,
        username: 'ayu'
    };
    state.exams = options.exams || [];
    state.selectedExamId = Number(options.selectedExamId) || 0;
    state.stage = options.stage || 'confirm';

    var authSession = createAuthSessionManager({
        getSessionStorage: browserStorage.getSessionStorage,
        state: state,
        storageKey: AUTH_SESSION_STORAGE_KEY
    });

    var context = {
        api: null,
        authSession: authSession,
        browserStorage: browserStorage,
        config: config,
        debugManager: { enabled: false, log: function () {}, logEvent: function () {}, refresh: function () {} },
        diagnosticsManager: null,
        loadLegacyRuntime: vi.fn(function () {
            return Promise.resolve();
        }),
        recordActionTrail: vi.fn(),
        recordTimeline: vi.fn(),
        renderFatalError: vi.fn(),
        renderShell: vi.fn(),
        root: root,
        state: state,
        transitionTo: vi.fn(function () {
            return Promise.resolve();
        })
    };

    return {
        authSession: authSession,
        context: context,
        root: root,
        state: state
    };
}

function mockFetchRoutes(routes) {
    var examsQueue = Array.isArray(routes.exams) ? routes.exams.slice() : [];
    var fetchMock = vi.fn(function (url) {
        var parsed = new URL(String(url), window.location.origin);
        var path = parsed.pathname;
        if (path.endsWith('/exams')) {
            return Promise.resolve(toResponse(examsQueue.length ? examsQueue.shift() : buildExamsPayload([])));
        }
        if (path.endsWith('/result')) {
            return Promise.resolve(toResponse(routes.result || buildResultPayload({ attemptId: Number(parsed.searchParams.get('attempt_id')) || 0 })));
        }
        if (path.endsWith('/logout')) {
            return Promise.resolve(toResponse(routes.logout || { ok: true }));
        }
        return Promise.resolve(toResponse({ ok: true }));
    });

    Object.defineProperty(window, 'fetch', {
        configurable: true,
        value: fetchMock
    });
    vi.stubGlobal('fetch', fetchMock);
    return fetchMock;
}

function toResponse(payload) {
    var status = payload && payload.error ? (payload.status || 500) : 200;
    var body = payload && payload.error
        ? {
            code: payload.code || 'unit_test_error',
            message: payload.message || 'Request gagal.'
        }
        : payload;
    return new Response(JSON.stringify(body), {
        headers: {
            'Content-Type': 'application/json'
        },
        status: status
    });
}

function buildExamsPayload(items) {
    return {
        current_user: {
            agama: 'Islam',
            display_name: 'Ayu Sari',
            email: 'ayu@example.test',
            foto: '',
            kode_kelas: 'XII-A',
            kode_ruang: 'R1',
            role: 'student',
            user_id: 7,
            username: 'ayu'
        },
        items: items
    };
}

function buildExam(id, overrides) {
    return Object.assign({
        availability_reason: '',
        duration_minutes: 90,
        id: id,
        is_available_now: 1,
        is_class_allowed: 1,
        is_within_schedule: 1,
        latest_attempt_finalize_pending: 0,
        latest_attempt_id: 0,
        latest_attempt_is_passed: 0,
        latest_attempt_max_score: 0,
        latest_attempt_pass_label: '',
        latest_attempt_percentage: 0,
        latest_attempt_result_tone: '',
        latest_attempt_score: 0,
        latest_attempt_status: '',
        requires_token: 0,
        show_student_result: 1,
        starts_at: '2026-05-14 08:00:00',
        status: 'publish',
        subject_name: 'Matematika',
        title: 'Ujian #' + String(id)
    }, overrides || {});
}

function buildResultPayload(options) {
    options = options || {};
    var attemptId = Number(options.attemptId) || 501;
    var score = Number(options.score !== undefined ? options.score : 80);
    var maxScore = Number(options.maxScore !== undefined ? options.maxScore : 100);
    var showStudentResult = Number(options.showStudentResult !== undefined ? options.showStudentResult : 1);

    return {
        answers: [],
        attempt: {
            id: attemptId,
            max_score: maxScore,
            score: score,
            status: 'completed'
        },
        exam: {
            id: 15,
            title: 'Matematika'
        },
        is_passed: score >= 75 ? 1 : 0,
        kkm_percentage: 75,
        pass_label: score >= 75 ? 'LULUS' : 'TIDAK LULUS',
        result_tone: score >= 75 ? 'pass' : 'fail',
        result_view_mode: showStudentResult === 1 ? 'full' : 'restricted',
        review_items: [],
        review_summary: {
            correct_questions: 1,
            total_questions: 1
        },
        show_student_result: showStudentResult,
        submission_summary: {
            answered_questions: 1,
            total_questions: 1
        }
    };
}

function clickAction(root, action, id) {
    var selector = '[data-action="' + action + '"]';
    if (id !== undefined) {
        selector += '[data-id="' + String(id) + '"]';
    }
    var target = root.querySelector(selector);
    expect(target).not.toBe(null);
    target.dispatchEvent(new MouseEvent('click', {
        bubbles: true,
        cancelable: true
    }));
}

async function flushPromises() {
    for (var i = 0; i < 5; i++) {
        await new Promise(function (resolve) {
            setTimeout(resolve, 0);
        });
    }
}
