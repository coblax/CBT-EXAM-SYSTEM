import { defineConfig } from 'vite';
import { resolve } from 'node:path';

const runtimeCacheDir = process.env.CBT_VITE_CACHE_DIR && process.env.CBT_VITE_CACHE_DIR !== ''
    ? resolve(process.env.CBT_VITE_CACHE_DIR)
    : resolve(__dirname, 'node_modules/.vite');

export default defineConfig(({ command }) => ({
    base: './',
    publicDir: false,
    cacheDir: runtimeCacheDir,
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: [resolve(__dirname, 'tests/js/setup/vitest.setup.js')],
        include: ['tests/js/**/*.test.js'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html'],
            reportsDirectory: resolve(__dirname, 'coverage/js')
        }
    },
    build: {
        emptyOutDir: true,
        manifest: 'manifest.json',
        outDir: resolve(__dirname, 'public/build'),
        sourcemap: command === 'serve',
        cssCodeSplit: true,
        rollupOptions: {
            input: {
                frontend: resolve(__dirname, 'src/frontend/main.js'),
                adminMath: resolve(__dirname, 'src/admin/math-main.js'),
                adminAnalytics: resolve(__dirname, 'src/admin/analytics-main.js'),
            },
            output: {
                entryFileNames(chunkInfo) {
                    var facadePath = String(chunkInfo.facadeModuleId || '');

                    if (facadePath.includes('/src/frontend/main.js')) {
                        return 'assets/frontend-core-[hash].js';
                    }
                    if (facadePath.includes('/src/frontend/app/runtime.js')) {
                        return 'assets/frontend-runtime-[hash].js';
                    }
                    if (facadePath.includes('/src/frontend/app/supervisor/runtime.js')) {
                        return 'assets/frontend-supervisor-runtime-[hash].js';
                    }

                    return 'assets/[name]-[hash].js';
                },
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
                manualChunks(id) {
                    if (
                        id.includes('/src/frontend/app/core/config')
                        || id.includes('/src/frontend/app/core/browser-storage')
                        || id.includes('/src/frontend/app/core/auth-session')
                        || id.includes('/src/frontend/app/core/html')
                    ) {
                        return 'frontend-shell-auth-core';
                    }
                    if (
                        id.includes('/src/frontend/app/core/api')
                        || id.includes('/src/frontend/app/core/app-meta')
                        || id.includes('/src/frontend/app/core/app-shell')
                        || id.includes('/src/frontend/app/core/auth-stages')
                        || id.includes('/src/frontend/app/core/format')
                        || id.includes('/src/frontend/app/core/ui-preferences')
                    ) {
                        return 'frontend-stage-auth-core';
                    }
                    if (id.includes('/src/frontend/app/shell/')) {
                        return 'frontend-student-shell';
                    }
                    if (id.includes('/src/frontend/app/stages/login-runtime')) {
                        return 'frontend-stage-login';
                    }
                    if (id.includes('/src/frontend/app/stages/confirm-runtime')) {
                        return 'frontend-stage-confirm';
                    }
                    if (id.includes('/src/frontend/app/stages/exam-runtime')) {
                        return 'frontend-stage-exam';
                    }
                    if (id.includes('/src/frontend/app/stages/result-runtime')) {
                        return 'frontend-stage-result';
                    }
                    if (
                        id.includes('/src/frontend/app/core/security-logging')
                        || id.includes('/src/frontend/app/core/idle-detection')
                        || id.includes('/src/frontend/app/exam/security')
                        || id.includes('/src/frontend/app/core/fullscreen-state')
                    ) {
                        return 'frontend-exam-security';
                    }
                    if (
                        id.includes('/src/frontend/app/core/session-heartbeat')
                        || id.includes('/src/frontend/app/core/session-lifecycle')
                        || id.includes('/src/frontend/app/core/sync-lifecycle-bridge')
                        || id.includes('/src/frontend/app/core/attempt-ui-sync')
                    ) {
                        return 'frontend-exam-session';
                    }
                    if (id.includes('/src/frontend/app/exam/question-helpers')) {
                        return 'frontend-exam-shared-helpers';
                    }
                    if (
                        id.includes('/src/frontend/app/exam/question-render')
                        || id.includes('/src/frontend/app/exam/question-input')
                        || id.includes('/src/frontend/app/exam/question-stem')
                    ) {
                        return 'frontend-exam-question-runtime';
                    }
                    if (id.includes('/src/frontend/app/exam/runtime-bundle')) {
                        return 'frontend-exam-runtime';
                    }
                    if (id.includes('/src/frontend/app/features/calculator')) {
                        return 'frontend-feature-calculator';
                    }
                    if (id.includes('/src/frontend/app/stages/review')) {
                        return 'frontend-shared-review';
                    }
                    if (id.includes('/src/frontend/app/stages/exam')) {
                        return 'frontend-stage-exam';
                    }
                    if (id.includes('/src/frontend/app/stages/result')) {
                        return 'frontend-stage-result';
                    }
                    return undefined;
                }
            }
        }
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: true
    }
}));
