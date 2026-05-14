import { existsSync, readFileSync, statSync } from 'node:fs';
import { dirname, extname, join, relative, resolve } from 'node:path';
import { gzipSync } from 'node:zlib';

const rootDir = process.cwd();
const buildDir = join(rootDir, 'public', 'build');
const manifestPath = join(buildDir, 'manifest.json');
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
const frontendEntryKey = 'src/frontend/main.js';
const studentRuntimeKey = 'src/frontend/app/runtime.js';
const frontendEntry = manifest[frontendEntryKey];
const studentRuntimeEntry = manifest[studentRuntimeKey];

const hardStudentLoginJsBudget = 60000;
const stretchStudentLoginJsBudget = 55000;
const hardStudentInitialCssBudget = 27000;
const hardRuntimeSourceLineBudget = 80;

if (!frontendEntry || typeof frontendEntry !== 'object') {
    throw new Error('Frontend entry src/frontend/main.js tidak ditemukan di manifest build.');
}
if (!studentRuntimeEntry || typeof studentRuntimeEntry !== 'object') {
    throw new Error('Student runtime src/frontend/app/runtime.js tidak ditemukan di manifest build.');
}

const frontendReachableKeys = collectReachableManifestKeys(frontendEntryKey);
const frontendAssets = collectFrontendAssets(frontendReachableKeys);
const studentRuntimeStaticKeys = collectStaticManifestKeys(studentRuntimeKey);
const loginStageKey = findManifestKeyByName('frontend-stage-login');
const loginStageStaticKeys = loginStageKey ? collectStaticManifestKeys(loginStageKey) : new Set();
const studentLoginInitialKeys = new Set([
    ...studentRuntimeStaticKeys,
    ...loginStageStaticKeys
]);
if (loginStageKey) {
    studentLoginInitialKeys.add(loginStageKey);
}

const studentLoginInitialJsGzip = sumEntryFileGzip(studentLoginInitialKeys, '.js');
const studentInitialCssGzip = sumEntryCssGzip(frontendEntry);
const runtimeLineCount = countSourceLines(join(rootDir, studentRuntimeKey));

reportFrontendAssets(frontendAssets);

if (!loginStageKey) {
    throw new Error('frontend-stage-login tidak ditemukan sebagai dynamic stage chunk.');
}

if (studentLoginInitialJsGzip > hardStudentLoginJsBudget) {
    throw new Error(
        'Initial student login JS melebihi budget: '
        + String(studentLoginInitialJsGzip)
        + ' gzip bytes > '
        + String(hardStudentLoginJsBudget)
        + ' gzip bytes.'
    );
}

if (studentInitialCssGzip > hardStudentInitialCssBudget) {
    throw new Error(
        'Initial student CSS melebihi budget: '
        + String(studentInitialCssGzip)
        + ' gzip bytes > '
        + String(hardStudentInitialCssBudget)
        + ' gzip bytes.'
    );
}

if (runtimeLineCount > hardRuntimeSourceLineBudget) {
    throw new Error(
        'src/frontend/app/runtime.js melebihi budget: '
        + String(runtimeLineCount)
        + ' lines > '
        + String(hardRuntimeSourceLineBudget)
        + ' lines.'
    );
}

assertForbiddenStaticSourceImports([
    'src/frontend/app/runtime.js',
    'src/frontend/app/shell/bootstrap-student-shell.js',
    'src/frontend/app/stages/login-runtime.js'
]);
assertForbiddenManifestStaticImports(studentRuntimeStaticKeys);
assertRequiredDynamicChunks(frontendEntry);
assertLegacyRuntimeIsDynamic(frontendEntry);

console.log(
    '[frontend-budget] initial student login JS:',
    formatBytes(studentLoginInitialJsGzip),
    'gzip',
    studentLoginInitialJsGzip <= stretchStudentLoginJsBudget ? '(stretch target met)' : '(hard target met)'
);
console.log('[frontend-budget] initial student CSS:', formatBytes(studentInitialCssGzip), 'gzip');
console.log('[frontend-budget] runtime.js:', String(runtimeLineCount), 'lines');

function collectReachableManifestKeys(startKey, seen = new Set()) {
    if (seen.has(startKey)) {
        return seen;
    }
    seen.add(startKey);
    const entry = manifest[startKey];
    if (!entry || typeof entry !== 'object') {
        return seen;
    }
    for (const key of [...(entry.imports || []), ...(entry.dynamicImports || [])]) {
        collectReachableManifestKeys(key, seen);
    }
    return seen;
}

function collectStaticManifestKeys(startKey, seen = new Set()) {
    if (seen.has(startKey)) {
        return seen;
    }
    seen.add(startKey);
    const entry = manifest[startKey];
    if (!entry || typeof entry !== 'object') {
        return seen;
    }
    for (const key of entry.imports || []) {
        collectStaticManifestKeys(key, seen);
    }
    return seen;
}

function collectFrontendAssets(keys) {
    const files = new Set();
    for (const key of keys) {
        const entry = manifest[key];
        if (!entry || typeof entry !== 'object') {
            continue;
        }
        if (entry.file) {
            files.add(String(entry.file));
        }
        for (const cssFile of entry.css || []) {
            files.add(String(cssFile));
        }
    }

    return [...files]
        .filter((file) => /\.(?:js|css)$/i.test(file))
        .sort()
        .map((file) => Object.assign({ file }, assetStats(file)));
}

function assetStats(file) {
    const path = join(buildDir, file);
    const source = readFileSync(path);
    return {
        gzip: gzipSync(source).length,
        raw: statSync(path).size,
        type: extname(file).replace('.', '').toLowerCase()
    };
}

function reportFrontendAssets(assets) {
    console.log('[frontend-budget] frontend JS/CSS assets:');
    for (const asset of assets) {
        console.log(
            '  -',
            asset.file,
            'raw',
            formatBytes(asset.raw),
            '| gzip',
            formatBytes(asset.gzip)
        );
    }
}

function sumEntryFileGzip(keys, extension) {
    let total = 0;
    for (const key of keys) {
        const entry = manifest[key];
        if (!entry || typeof entry !== 'object' || !entry.file) {
            continue;
        }
        if (extname(String(entry.file)).toLowerCase() !== extension) {
            continue;
        }
        total += assetStats(String(entry.file)).gzip;
    }
    return total;
}

function sumEntryCssGzip(entry) {
    let total = 0;
    for (const cssFile of entry.css || []) {
        total += assetStats(String(cssFile)).gzip;
    }
    return total;
}

function countSourceLines(path) {
    return readFileSync(path, 'utf8').split(/\r?\n/).filter((line) => line.trim() !== '').length;
}

function findManifestKeyByName(name) {
    for (const [key, entry] of Object.entries(manifest)) {
        if (entry && typeof entry === 'object' && String(entry.name || '') === name) {
            return key;
        }
    }
    return '';
}

function assertRequiredDynamicChunks(entry) {
    const dynamicNames = collectDynamicImportNames(entry);
    const requiredNames = [
        'frontend-stage-login',
        'frontend-stage-confirm',
        'frontend-stage-exam',
        'frontend-stage-result',
        'frontend-exam-runtime'
    ];
    for (const requiredName of requiredNames) {
        if (!dynamicNames.includes(requiredName)) {
            throw new Error('Dynamic chunk wajib tidak ditemukan dari frontend entry: ' + requiredName);
        }
    }
}

function assertLegacyRuntimeIsDynamic(entry) {
    const dynamicKeys = collectDynamicImportKeys(entry);
    const hasLegacyRuntime = dynamicKeys.some((key) => {
        const dynamicEntry = manifest[key];
        return key === 'src/frontend/app/legacy-runtime.js'
            || (dynamicEntry && typeof dynamicEntry === 'object' && String(dynamicEntry.file || '').includes('legacy-runtime'));
    });
    if (!hasLegacyRuntime) {
        throw new Error('legacy-runtime tidak ditemukan sebagai dynamic import dari frontend entry.');
    }
}

function collectDynamicImportKeys(entry, seen = new Set()) {
    const keys = [];
    for (const key of entry.dynamicImports || []) {
        if (seen.has(key)) {
            continue;
        }
        seen.add(key);
        keys.push(key);
        const dynamicEntry = manifest[key];
        if (dynamicEntry && typeof dynamicEntry === 'object') {
            keys.push(...collectDynamicImportKeys(dynamicEntry, seen));
        }
    }
    for (const key of entry.imports || []) {
        const importEntry = manifest[key];
        if (importEntry && typeof importEntry === 'object') {
            keys.push(...collectDynamicImportKeys(importEntry, seen));
        }
    }
    return keys;
}

function collectDynamicImportNames(entry, seen = new Set()) {
    const names = [];
    for (const key of entry.dynamicImports || []) {
        if (seen.has(key)) {
            continue;
        }
        seen.add(key);
        const dynamicEntry = manifest[key];
        if (!dynamicEntry || typeof dynamicEntry !== 'object') {
            continue;
        }
        if (dynamicEntry.name) {
            names.push(String(dynamicEntry.name));
        }
        names.push(...collectDynamicImportNames(dynamicEntry, seen));
    }
    for (const key of entry.imports || []) {
        const importEntry = manifest[key];
        if (importEntry && typeof importEntry === 'object') {
            names.push(...collectDynamicImportNames(importEntry, seen));
        }
    }
    return names;
}

function assertForbiddenManifestStaticImports(keys) {
    const forbiddenNames = [
        'frontend-exam-runtime',
        'frontend-exam-security',
        'frontend-exam-session',
        'frontend-exam-question-runtime',
        'frontend-feature-calculator',
        'frontend-stage-exam',
        'frontend-stage-result'
    ];
    const staticNames = [...keys].map((key) => {
        const entry = manifest[key];
        return entry && typeof entry === 'object' ? String(entry.name || '') : '';
    });
    const staticFiles = [...keys].map((key) => {
        const entry = manifest[key];
        return entry && typeof entry === 'object' ? String(entry.file || '') : '';
    });
    if ([...keys].includes('src/frontend/app/legacy-runtime.js') || staticFiles.some((file) => file.includes('legacy-runtime'))) {
        throw new Error('legacy-runtime masih ikut sebagai static import pada student shell.');
    }
    for (const forbiddenName of forbiddenNames) {
        if (staticNames.includes(forbiddenName)) {
            throw new Error(forbiddenName + ' masih ikut sebagai static import pada student shell.');
        }
    }
}

function assertForbiddenStaticSourceImports(startFiles) {
    const visited = new Set();
    for (const startFile of startFiles) {
        scanStaticSourceGraph(join(rootDir, startFile), visited);
    }

    const forbiddenPatterns = [
        '/src/frontend/app/legacy-runtime.js',
        '/src/frontend/app/core/app-events',
        '/src/frontend/app/core/attempt-ui-sync',
        '/src/frontend/app/core/bootstrap-session',
        '/src/frontend/app/core/exam-session',
        '/src/frontend/app/core/fullscreen-state',
        '/src/frontend/app/core/idle-detection',
        '/src/frontend/app/core/security-logging',
        '/src/frontend/app/core/session-heartbeat',
        '/src/frontend/app/core/session-lifecycle',
        '/src/frontend/app/core/sync-lifecycle-bridge',
        '/src/frontend/app/exam/question-render',
        '/src/frontend/app/exam/runtime-bundle',
        '/src/frontend/app/exam/security',
        '/src/frontend/app/features/calculator',
        '/src/frontend/app/stages/exam.js',
        '/src/frontend/app/stages/result.js'
    ];

    for (const path of visited) {
        const normalized = '/' + relative(rootDir, path).replace(/\\/g, '/');
        for (const pattern of forbiddenPatterns) {
            if (normalized.includes(pattern)) {
                throw new Error('Import statis terlarang pada login shell: ' + normalized);
            }
        }
    }
}

function scanStaticSourceGraph(path, visited) {
    const normalizedPath = resolve(path);
    if (visited.has(normalizedPath) || !existsSync(normalizedPath)) {
        return;
    }
    visited.add(normalizedPath);
    if (extname(normalizedPath) !== '.js') {
        return;
    }

    const source = readFileSync(normalizedPath, 'utf8');
    const importPattern = /import\s+(?:[^'"()]*?\s+from\s+)?['"]([^'"]+)['"]/g;
    let match;
    while ((match = importPattern.exec(source)) !== null) {
        const specifier = String(match[1] || '');
        if (!specifier.startsWith('.')) {
            continue;
        }
        const resolvedImport = resolveSourceImport(dirname(normalizedPath), specifier);
        if (resolvedImport) {
            scanStaticSourceGraph(resolvedImport, visited);
        }
    }
}

function resolveSourceImport(baseDir, specifier) {
    const candidate = resolve(baseDir, specifier);
    const candidates = extname(candidate)
        ? [candidate]
        : [candidate + '.js', join(candidate, 'index.js')];
    for (const path of candidates) {
        if (existsSync(path)) {
            return path;
        }
    }
    return '';
}

function formatBytes(value) {
    return String(Math.round(Number(value) || 0)) + ' B';
}
