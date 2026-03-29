import { defineConfig } from 'vite';
import { resolve } from 'node:path';

export default defineConfig(({ command }) => ({
    base: './',
    publicDir: false,
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
            },
            output: {
                entryFileNames: 'assets/frontend-core-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
                manualChunks(id) {
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
