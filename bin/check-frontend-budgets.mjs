import { readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const manifestPath = join(process.cwd(), 'public', 'build', 'manifest.json');
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
const frontendEntry = manifest['src/frontend/main.js'];

if (!frontendEntry || typeof frontendEntry !== 'object') {
    throw new Error('Frontend entry src/frontend/main.js tidak ditemukan di manifest build.');
}

const frontendEntryPath = join(process.cwd(), 'public', 'build', String(frontendEntry.file || ''));
const frontendEntrySize = statSync(frontendEntryPath).size;
const frontendEntryBudget = 295000;

if (frontendEntrySize > frontendEntryBudget) {
    throw new Error(
        'Frontend entry melebihi budget: '
        + String(frontendEntrySize)
        + ' bytes > '
        + String(frontendEntryBudget)
        + ' bytes.'
    );
}

const staticImportNames = (frontendEntry.imports || []).map((key) => {
    const entry = manifest[key];
    return entry && entry.name ? String(entry.name) : '';
});

if (staticImportNames.includes('math-render')) {
    throw new Error('math-render masih ikut sebagai static import pada frontend entry.');
}

if (staticImportNames.includes('frontend-exam-runtime')) {
    throw new Error('frontend-exam-runtime masih ikut sebagai static import pada frontend entry.');
}

const dynamicImportNames = (frontendEntry.dynamicImports || []).map((key) => {
    const entry = manifest[key];
    return entry && entry.name ? String(entry.name) : '';
});

for (const requiredName of ['frontend-exam-runtime', 'frontend-stage-exam', 'frontend-stage-result']) {
    if (!dynamicImportNames.includes(requiredName)) {
        throw new Error('Dynamic import wajib hilang dari frontend entry: ' + requiredName);
    }
}

console.log(
    '[frontend-budget] frontend entry:',
    String(frontendEntrySize) + ' bytes',
    '| dynamic:',
    dynamicImportNames.join(', ') || '-'
);
