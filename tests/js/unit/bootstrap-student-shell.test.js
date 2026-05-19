import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const authStorageKey = 'cbt_exam_frontend_auth_v1';

describe('bootstrapStudentShell', function () {
    beforeEach(function () {
        vi.resetModules();
        document.body.innerHTML = '<div id="cbt-exam-app"></div>';
        window.CBTExamFrontendConfig = {
            frontendMode: 'student',
            restBasePath: '/wp-json/cbt/v1/',
            securityLogEvents: 0
        };
    });

    it('mounts the login stage when no auth session is persisted', async function () {
        var mountLoginStage = vi.fn(function () {
            return {
                render: vi.fn(),
                unmount: vi.fn()
            };
        });
        mockStageModules({
            mountLoginStage: mountLoginStage
        });

        var module = await import('../../../src/frontend/app/shell/bootstrap-student-shell.js');
        module.bootstrapStudentShell();
        await flushPromises();

        expect(mountLoginStage).toHaveBeenCalledTimes(1);
        expect(mountLoginStage.mock.calls[0][0].state.stage).toBe('login');
        expect(document.getElementById('cbt-exam-app').getAttribute('data-cbt-student-shell-mounted')).toBe('1');
    });

    it('routes a persisted confirm session to the confirm stage adapter', async function () {
        var mountConfirmStage = vi.fn(function () {
            return {
                render: vi.fn(),
                unmount: vi.fn()
            };
        });
        mockStageModules({
            mountConfirmStage: mountConfirmStage
        });
        sessionStorage.setItem(authStorageKey, JSON.stringify({
            token: 'token-123',
            user: {
                user_id: 11,
                role: 'student',
                display_name: 'Ayu',
                username: 'ayu'
            },
            selected_exam_id: 42,
            last_stage: 'confirm'
        }));

        var module = await import('../../../src/frontend/app/shell/bootstrap-student-shell.js');
        module.bootstrapStudentShell();
        await flushPromises();

        var context = mountConfirmStage.mock.calls[0][0];
        expect(mountConfirmStage).toHaveBeenCalledTimes(1);
        expect(context.state.stage).toBe('confirm');
        expect(context.state.token).toBe('token-123');
        expect(context.state.selectedExamId).toBe(42);
    });

    it('renders the fatal shell when a lazy stage import fails', async function () {
        mockStageModules({
            mountLoginStage: vi.fn(function () {
                throw new Error('stage boom');
            })
        });

        var module = await import('../../../src/frontend/app/shell/bootstrap-student-shell.js');
        module.bootstrapStudentShell();
        await flushPromises();

        expect(document.getElementById('cbt-exam-app').textContent).toContain('Frontend tidak bisa dimuat');
        expect(document.getElementById('cbt-exam-app').textContent).toContain('stage boom');
    });

    it('unmounts a pending stage controller when mount fails after registration', async function () {
        var loginController = createNoopController();
        mockStageModules({
            mountLoginStage: vi.fn(function (context) {
                context.registerStageController(loginController);
                throw new Error('registered stage boom');
            })
        });

        var module = await import('../../../src/frontend/app/shell/bootstrap-student-shell.js');
        module.bootstrapStudentShell();
        await flushPromises();

        expect(loginController.unmount).toHaveBeenCalledTimes(1);
        expect(document.getElementById('cbt-exam-app').textContent).toContain('registered stage boom');
    });

    it('unmounts a pending async stage controller when a newer transition starts', async function () {
        var confirmDeferred = createDeferred();
        var confirmController = createNoopController();
        var loginController = createNoopController();
        var mountConfirmStage = vi.fn(function (context) {
            context.registerStageController(confirmController);
            context.root.innerHTML = '<button type="button">Loading result...</button>';
            return confirmDeferred.promise.then(function () {
                return confirmController;
            });
        });
        var mountLoginStage = vi.fn(function () {
            return loginController;
        });
        mockStageModules({
            mountConfirmStage: mountConfirmStage,
            mountLoginStage: mountLoginStage
        });
        sessionStorage.setItem(authStorageKey, JSON.stringify({
            token: 'token-123',
            user: {
                user_id: 11,
                role: 'student',
                display_name: 'Ayu',
                username: 'ayu'
            },
            selected_exam_id: 42,
            last_stage: 'confirm'
        }));

        var module = await import('../../../src/frontend/app/shell/bootstrap-student-shell.js');
        var shell = module.bootstrapStudentShell();
        await flushPromises();

        expect(mountConfirmStage).toHaveBeenCalledTimes(1);

        await shell.transitionTo('login', { reason: 'newer-transition' });

        expect(confirmController.unmount).toHaveBeenCalledTimes(1);
        expect(mountLoginStage).toHaveBeenCalledTimes(1);

        confirmDeferred.resolve(confirmController);
        await flushPromises();

        expect(confirmController.unmount.mock.calls.length).toBeGreaterThanOrEqual(1);
        expect(document.getElementById('cbt-exam-app').textContent).not.toContain('Loading result...');
    });
});

describe('student runtime static import guard', function () {
    it('keeps runtime.js as a thin wrapper and leaves exam-only modules out of the login shell graph', function () {
        var runtimeSource = readSource('src/frontend/app/runtime.js');
        var runtimeLines = runtimeSource.split(/\r?\n/).filter(function (line) {
            return line.trim() !== '';
        });

        expect(runtimeLines.length).toBeLessThanOrEqual(80);
        expect(runtimeSource).toContain('bootstrapStudentShell');

        var checkedSources = [
            'src/frontend/app/runtime.js',
            'src/frontend/app/shell/bootstrap-student-shell.js',
            'src/frontend/app/stages/login-runtime.js',
            'src/frontend/app/stages/confirm-runtime.js',
            'src/frontend/app/stages/result-runtime.js',
            'src/frontend/app/stages/authenticated-runtime.js'
        ].map(readSource).join('\n');
        var forbiddenStaticImports = [
            '../legacy-runtime.js',
            './legacy-runtime.js',
            '../core/exam-session',
            '../core/security-logging',
            '../core/idle-detection',
            '../core/session-heartbeat',
            '../core/fullscreen-state',
            '../core/attempt-ui-sync',
            '../core/sync-lifecycle-bridge',
            '../exam/runtime-bundle',
            '../exam/security',
            '../features/calculator',
            './result.js',
            './exam.js'
        ];

        forbiddenStaticImports.forEach(function (specifier) {
            expect(hasStaticImport(checkedSources, specifier)).toBe(false);
        });
    });
});

function mockStageModules(overrides) {
    vi.doMock('../../../src/frontend/app/stages/login-runtime.js', function () {
        return {
            mountLoginStage: overrides.mountLoginStage || vi.fn(function () {
                return createNoopController();
            })
        };
    });
    vi.doMock('../../../src/frontend/app/stages/confirm-runtime.js', function () {
        return {
            mountConfirmStage: overrides.mountConfirmStage || vi.fn(function () {
                return createNoopController();
            })
        };
    });
    vi.doMock('../../../src/frontend/app/stages/exam-runtime.js', function () {
        return {
            mountExamStage: overrides.mountExamStage || vi.fn(function () {
                return createNoopController();
            })
        };
    });
    vi.doMock('../../../src/frontend/app/stages/result-runtime.js', function () {
        return {
            mountResultStage: overrides.mountResultStage || vi.fn(function () {
                return createNoopController();
            })
        };
    });
}

function createNoopController() {
    return {
        render: vi.fn(),
        unmount: vi.fn()
    };
}

function createDeferred() {
    var resolvePromise;
    var rejectPromise;
    var promise = new Promise(function (resolve, reject) {
        resolvePromise = resolve;
        rejectPromise = reject;
    });

    return {
        promise,
        reject: rejectPromise,
        resolve: resolvePromise
    };
}

async function flushPromises() {
    for (var i = 0; i < 5; i++) {
        await new Promise(function (resolve) {
            setTimeout(resolve, 0);
        });
    }
}

function readSource(path) {
    return readFileSync(join(process.cwd(), path), 'utf8');
}

function hasStaticImport(source, specifier) {
    var escaped = specifier.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    var pattern = new RegExp("import\\s+(?:[^'\"()]*?\\s+from\\s+)?['\"]" + escaped + "['\"]");
    return pattern.test(source);
}
