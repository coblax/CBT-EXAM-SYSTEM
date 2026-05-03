import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const {
    configuredE2EBaseUrl,
    configuredE2EFrontendUrl,
    e2eFrontendUrl,
    e2eUrl,
} = require('../tests/e2e/helpers/e2e-url.js');

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');
const fixtureHelperPath = path.resolve(projectRoot, 'tests/e2e/helpers/e2e-fixture.php');

const timeoutMs = Math.max(1000, Number(process.env.CBT_E2E_CHECK_TIMEOUT_MS || 8000));
const phpBinary = process.env.CBT_E2E_PHP_BINARY || 'php';
const skipFixtureCheck = process.argv.includes('--skip-fixture') || process.env.CBT_E2E_SKIP_FIXTURE_CHECK === '1';

function stripText(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
}

function excerpt(value) {
    return stripText(value).slice(0, 260) || '-';
}

async function fetchText(url) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const response = await fetch(url, {
            redirect: 'follow',
            signal: controller.signal,
        });
        const text = await response.text();
        return {
            ok: response.ok,
            status: response.status,
            url: response.url,
            text,
        };
    } finally {
        clearTimeout(timeout);
    }
}

async function checkPage(label, pathName, marker, hint) {
    const targetUrl = e2eUrl(pathName);
    return checkAbsolutePage(label, targetUrl, marker, hint);
}

async function checkAbsolutePage(label, targetUrl, marker, hint) {
    try {
        const result = await fetchText(targetUrl);
        const hasMarker = result.text.includes(marker);
        if (result.ok && hasMarker) {
            return {
                ok: true,
                message: `${label} OK (${result.status}) ${result.url}`,
            };
        }

        return {
            ok: false,
            message: [
                `${label} belum siap.`,
                `Target: ${targetUrl}`,
                `Final URL: ${result.url || '-'}`,
                `HTTP: ${result.status}`,
                `Marker dicari: ${marker}`,
                `Body: ${excerpt(result.text)}`,
                hint,
            ].filter(Boolean).join('\n'),
        };
    } catch (error) {
        return {
            ok: false,
            message: [
                `${label} tidak bisa diakses.`,
                `Target: ${targetUrl}`,
                `Error: ${String(error && error.message ? error.message : error)}`,
                hint,
            ].filter(Boolean).join('\n'),
        };
    }
}

function checkFixtureCatalog() {
    if (skipFixtureCheck) {
        return {
            ok: true,
            message: 'Fixture catalog check dilewati (--skip-fixture / CBT_E2E_SKIP_FIXTURE_CHECK=1).',
        };
    }

    try {
        const stdout = execFileSync(phpBinary, [fixtureHelperPath, 'catalog'], {
            cwd: projectRoot,
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        });
        const parsed = JSON.parse(stdout || '{}');
        const catalog = parsed && parsed.catalog && typeof parsed.catalog === 'object' ? parsed.catalog : {};
        const users = catalog.users && typeof catalog.users === 'object' ? catalog.users : {};
        const fixtures = catalog.fixtures && typeof catalog.fixtures === 'object' ? catalog.fixtures : {};
        const requiredUsers = ['primary_student', 'admin_seed'];
        const requiredFixtures = ['import_preview', 'question_runtime', 'result_full', 'result_essay', 'result_restricted'];
        const missingUsers = requiredUsers.filter((key) => !users[key]);
        const missingFixtures = requiredFixtures.filter((key) => !fixtures[key]);

        if (parsed.ok === true && missingUsers.length === 0 && missingFixtures.length === 0) {
            return {
                ok: true,
                message: `Fixture catalog OK (${Object.keys(fixtures).length} fixture, ${Object.keys(users).length} user).`,
            };
        }

        return {
            ok: false,
            message: [
                'Fixture catalog belum lengkap.',
                `Missing users: ${missingUsers.length ? missingUsers.join(', ') : '-'}`,
                `Missing fixtures: ${missingFixtures.length ? missingFixtures.join(', ') : '-'}`,
                'Jalankan CBT Maintenance > Generate Data Uji sebelum menjalankan E2E penuh.',
            ].join('\n'),
        };
    } catch (error) {
        const stderr = error && typeof error.stderr === 'string' ? error.stderr.trim() : '';
        const stdout = error && typeof error.stdout === 'string' ? error.stdout.trim() : '';
        return {
            ok: false,
            message: [
                'Fixture catalog check gagal.',
                `Command: ${phpBinary} ${path.relative(projectRoot, fixtureHelperPath)} catalog`,
                `Output: ${excerpt(stderr || stdout || String(error && error.message ? error.message : error))}`,
                'Pastikan WordPress bisa dibootstrap dari repo plugin dan Bulk Test Data sudah dibuat.',
            ].join('\n'),
        };
    }
}

const checks = [
    await checkPage(
        'WordPress login',
        'wp-login.php',
        'id="user_login"',
        'Set CBT_E2E_BASE_URL atau CBT_E2E_WP_BASE_URL ke root WordPress yang benar, contoh: http://localhost atau http://localhost/wordpress.'
    ),
    await checkAbsolutePage(
        'CBT frontend',
        e2eFrontendUrl(),
        'id="cbt-login-form"',
        'Jika frontend CBT bukan homepage WordPress, set CBT_E2E_FRONTEND_URL ke halaman yang memuat shortcode/frontend CBT.'
    ),
    checkFixtureCatalog(),
];

console.log(`[e2e:check] Base URL: ${configuredE2EBaseUrl()}`);
console.log(`[e2e:check] Frontend URL: ${configuredE2EFrontendUrl()}`);
checks.forEach((check) => {
    console.log(`${check.ok ? '[OK]' : '[FAIL]'} ${check.message}`);
});

const failed = checks.filter((check) => !check.ok);
if (failed.length > 0) {
    console.error(`[e2e:check] ${failed.length} check gagal. Perbaiki environment sebelum menjalankan Playwright E2E.`);
    process.exit(1);
}

console.log('[e2e:check] E2E environment siap.');
