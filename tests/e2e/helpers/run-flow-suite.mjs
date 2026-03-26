import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..', '..', '..');
const sharedBrowsersPath = process.env.PLAYWRIGHT_BROWSERS_PATH || path.join(projectRoot, '.playwright-browsers');
const defaultOutputDir = typeof process.getuid === 'function'
    ? path.join(projectRoot, 'playwright-results', `uid-${process.getuid()}`, `run-${process.pid}`)
    : path.join(projectRoot, 'playwright-results', 'default', `run-${process.pid}`);
const sharedOutputDir = process.env.PLAYWRIGHT_OUTPUT_DIR || defaultOutputDir;
const e2eHelperPath = path.join(projectRoot, 'tests', 'e2e', 'helpers', 'e2e-fixture.php');
const phpBinary = process.env.CBT_E2E_PHP_BINARY || 'php';

const requiredLinuxLibraries = [
    'libnspr4.so',
    'libnss3.so',
    'libasound.so.2',
    'libatk-1.0.so.0',
    'libatk-bridge-2.0.so.0',
];

function readLinuxLibraryCatalog() {
    const result = spawnSync('ldconfig', ['-p'], { encoding: 'utf8' });
    if (result.error || result.status !== 0) {
        return '';
    }
    return String(result.stdout || '');
}

function detectMissingLinuxLibraries() {
    if (process.platform !== 'linux') {
        return [];
    }
    const catalog = readLinuxLibraryCatalog();
    if (catalog === '') {
        return [];
    }
    return requiredLinuxLibraries.filter((libraryName) => !catalog.includes(libraryName));
}

function printSkip(title, messageLines) {
    console.log(`PLAYWRIGHT ${title.toUpperCase()} FLOW SKIPPED`);
    messageLines.forEach((line) => console.log(line));
}

function printFlowHeader(title, detailLines = []) {
    console.log(`=== ${title} ===`);
    detailLines.forEach((line) => console.log(`- ${line}`));
    console.log('');
}

function hasInstalledChromiumBrowser() {
    if (!fs.existsSync(sharedBrowsersPath)) {
        return false;
    }
    const entries = fs.readdirSync(sharedBrowsersPath, { withFileTypes: true });
    return entries.some((entry) => entry.isDirectory() && (entry.name.startsWith('chromium-') || entry.name.startsWith('chromium_headless_shell-')));
}

function callE2EHelper(action, payload) {
    const args = [e2eHelperPath, String(action || 'fixture')];
    if (payload && Object.keys(payload).length > 0) {
        args.push(JSON.stringify(payload));
    }

    const result = spawnSync(phpBinary, args, {
        cwd: projectRoot,
        encoding: 'utf8',
        env: {
            ...process.env,
        },
    });

    if (result.error || result.status !== 0) {
        const stderr = String(result.stderr || '').trim();
        const stdout = String(result.stdout || '').trim();
        const detail = stderr || stdout || String(result.error && result.error.message ? result.error.message : 'Helper E2E gagal dijalankan.');
        console.error(detail);
        process.exit(1);
    }

    try {
        return JSON.parse(String(result.stdout || '{}'));
    } catch (error) {
        console.error('Gagal membaca payload helper E2E: ' + String(error && error.message ? error.message : error));
        process.exit(1);
    }
}

function ensureSharedOutputDir() {
    fs.mkdirSync(sharedOutputDir, { recursive: true, mode: 0o777 });
    try {
        fs.chmodSync(sharedOutputDir, 0o777);
    } catch (error) {
        // Best effort only.
    }
}

function relaxSharedOutputPermissions() {
    if (!fs.existsSync(sharedOutputDir)) {
        return;
    }

    const queue = [sharedOutputDir];
    while (queue.length > 0) {
        const currentPath = queue.shift();
        if (!currentPath) {
            continue;
        }

        let stat;
        try {
            stat = fs.statSync(currentPath);
        } catch (error) {
            continue;
        }

        try {
            fs.chmodSync(currentPath, stat.isDirectory() ? 0o777 : 0o666);
        } catch (error) {
            // Best effort only.
        }

        if (!stat.isDirectory()) {
            continue;
        }

        let entries = [];
        try {
            entries = fs.readdirSync(currentPath, { withFileTypes: true });
        } catch (error) {
            continue;
        }

        entries.forEach((entry) => {
            queue.push(path.join(currentPath, entry.name));
        });
    }
}

export function runFlowSuite({
    suiteTitle,
    specRelativePath,
    fixtureKey,
    userKey = 'primary_student',
}) {
    process.env.PLAYWRIGHT_BROWSERS_PATH = sharedBrowsersPath;
    process.env.PLAYWRIGHT_OUTPUT_DIR = sharedOutputDir;
    process.env.PLAYWRIGHT_DISABLE_HTML_REPORT = '1';

    const missingLibraries = detectMissingLinuxLibraries();
    if (missingLibraries.length > 0) {
        printSkip(suiteTitle, [
            'Host belum punya dependency browser yang dibutuhkan Playwright Chromium.',
            'Missing library: ' + missingLibraries.join(', '),
            `Pasang dependency OS browser terlebih dahulu, lalu jalankan ulang flow check ${suiteTitle}.`,
        ]);
        process.exit(0);
    }

    if (!hasInstalledChromiumBrowser()) {
        printSkip(suiteTitle, [
            'Browser Playwright Chromium belum terpasang di shared path project.',
            'Shared browser path: ' + sharedBrowsersPath,
            'Jalankan: PLAYWRIGHT_BROWSERS_PATH=.playwright-browsers npx playwright install chromium',
        ]);
        process.exit(0);
    }

    ensureSharedOutputDir();
    relaxSharedOutputPermissions();

    printFlowHeader(`${suiteTitle} Flow Check Bootstrap`, [
        'Base URL: ' + String(process.env.CBT_E2E_BASE_URL || '(belum diisi)'),
        'Browser Path: ' + sharedBrowsersPath,
        'Output Dir: ' + sharedOutputDir,
        'Fixture Key: ' + String(fixtureKey || ''),
        'Scenario Filter: ' + (process.argv.slice(2).join(' ') || `semua skenario ${suiteTitle}`),
    ]);

    const resetPayload = callE2EHelper('reset', {
        fixture_key: fixtureKey,
        user_key: userKey,
    });
    if (!resetPayload || resetPayload.ok !== true) {
        console.error(`Preflight reset ${suiteTitle} gagal.`);
        process.exit(1);
    }

    printFlowHeader('Preflight Reset', [
        `Reset login session ${userKey}: selesai`,
        `Reset attempt/ui_state/runtime/security/cache fixture ${fixtureKey}: selesai`,
        'Deleted attempt count: ' + String(resetPayload.deleted_attempt_count || 0),
        'Fixture: ' + String(resetPayload.fixture && resetPayload.fixture.exam && resetPayload.fixture.exam.title ? resetPayload.fixture.exam.title : fixtureKey),
    ]);

    const tokenPayload = callE2EHelper('global_token', {
        fixture_key: fixtureKey,
        user_key: userKey,
    });
    if (!tokenPayload || tokenPayload.ok !== true) {
        console.error(`Preflight token ${suiteTitle} gagal.`);
        process.exit(1);
    }

    const resolvedExamToken = String(tokenPayload.token_meta && tokenPayload.token_meta.token ? tokenPayload.token_meta.token : '').trim().toUpperCase();
    let pinnedTokenMeta = tokenPayload.token_meta || {};
    if (resolvedExamToken !== '') {
        const pinnedTokenPayload = callE2EHelper('set_global_token', {
            token: resolvedExamToken,
            refresh_minutes: Number(tokenPayload.token_meta && tokenPayload.token_meta.refresh_minutes ? tokenPayload.token_meta.refresh_minutes : 15) || 15,
            frontend_auto_apply: Number(tokenPayload.token_meta && tokenPayload.token_meta.frontend_auto_apply ? tokenPayload.token_meta.frontend_auto_apply : 0) === 1,
        });
        if (!pinnedTokenPayload || pinnedTokenPayload.ok !== true) {
            console.error(`Preflight token pin ${suiteTitle} gagal.`);
            process.exit(1);
        }
        pinnedTokenMeta = pinnedTokenPayload.token_meta || pinnedTokenMeta;
    }

    if (resolvedExamToken !== '') {
        process.env.CBT_E2E_EXAM_TOKEN = resolvedExamToken;
    } else {
        delete process.env.CBT_E2E_EXAM_TOKEN;
    }

    printFlowHeader('Preflight Token Resolve', [
        resolvedExamToken !== ''
            ? 'Token aktif terdeteksi, dipin ulang, dan akan diisikan eksplisit oleh runner.'
            : 'Token global tidak aktif. Flow akan berjalan tanpa token eksplisit.',
        'Frontend auto apply: ' + ((Number(pinnedTokenMeta && pinnedTokenMeta.frontend_auto_apply ? pinnedTokenMeta.frontend_auto_apply : 0) === 1) ? 'ON' : 'OFF'),
    ]);

    const previousUmask = process.umask(0o000);
    const result = spawnSync(
        './node_modules/.bin/playwright',
        ['test', specRelativePath, ...process.argv.slice(2)],
        {
            cwd: projectRoot,
            stdio: 'inherit',
            env: {
                ...process.env,
                PLAYWRIGHT_BROWSERS_PATH: sharedBrowsersPath,
                PLAYWRIGHT_OUTPUT_DIR: sharedOutputDir,
                PLAYWRIGHT_DISABLE_HTML_REPORT: '1',
            },
        }
    );

    process.umask(previousUmask);
    relaxSharedOutputPermissions();

    if (result.error) {
        console.error(result.error.message);
        process.exit(1);
    }

    process.exit(typeof result.status === 'number' ? result.status : 1);
}
