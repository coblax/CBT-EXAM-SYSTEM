import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');
const rawArgs = process.argv.slice(2);
const skipReadiness = rawArgs.includes('--skip-readiness') || process.env.CBT_E2E_SKIP_READINESS_CHECK === '1';
const playwrightArgs = rawArgs.filter((arg) => arg !== '--skip-readiness');
const playwrightBin = process.platform === 'win32'
    ? path.join(projectRoot, 'node_modules', '.bin', 'playwright.cmd')
    : path.join(projectRoot, 'node_modules', '.bin', 'playwright');

const env = {
    ...process.env,
    PLAYWRIGHT_BROWSERS_PATH: process.env.PLAYWRIGHT_BROWSERS_PATH || '.playwright-browsers',
    PLAYWRIGHT_OUTPUT_DIR: process.env.PLAYWRIGHT_OUTPUT_DIR || 'playwright-results/cli',
};

function run(command, args, options = {}) {
    const result = spawnSync(command, args, {
        cwd: projectRoot,
        stdio: 'inherit',
        env,
        ...options,
    });

    if (result.error) {
        console.error(String(result.error && result.error.message ? result.error.message : result.error));
        process.exit(1);
    }

    if (result.signal) {
        console.error(`Command stopped by signal ${result.signal}.`);
        process.exit(1);
    }

    return Number(result.status || 0);
}

if (!skipReadiness) {
    const readinessStatus = run(process.execPath, [path.join(projectRoot, 'bin', 'check-e2e-readiness.mjs')]);
    if (readinessStatus !== 0) {
        console.error('[e2e] Readiness check gagal. Set CBT_E2E_SKIP_READINESS_CHECK=1 atau pakai --skip-readiness hanya saat debugging sadar.');
        process.exit(readinessStatus);
    }
} else {
    console.log('[e2e] Readiness check dilewati.');
}

const status = run(playwrightBin, ['test', ...playwrightArgs]);
process.exit(status);
