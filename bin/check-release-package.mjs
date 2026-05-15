#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { join, relative, resolve, sep } from 'node:path';

const repoRoot = process.cwd();

function fail(message) {
  console.error(`[release:check] ${message}`);
  process.exit(1);
}

function log(message) {
  console.log(`[release:check] ${message}`);
}

function parseArgs(argv) {
  const args = {
    stagedRoot: '',
    zip: '',
    updateManifest: '',
  };

  for (let index = 0; index < argv.length; index++) {
    const arg = argv[index];
    if (arg === '--staged-root') {
      args.stagedRoot = argv[++index] || '';
    } else if (arg === '--zip') {
      args.zip = argv[++index] || '';
    } else if (arg === '--update-manifest') {
      args.updateManifest = argv[++index] || '';
    } else if (arg === '--help' || arg === '-h') {
      console.log(`Usage: node bin/check-release-package.mjs [--staged-root <path>] [--zip <path>] [--update-manifest <path>]`);
      process.exit(0);
    } else {
      fail(`Unknown argument: ${arg}`);
    }
  }

  return args;
}

function assertFile(root, relPath) {
  const path = join(root, relPath);
  if (!existsSync(path) || !statSync(path).isFile()) {
    fail(`Required file missing: ${formatPath(root, path)}`);
  }
  if (statSync(path).size <= 0) {
    fail(`Required file is empty: ${formatPath(root, path)}`);
  }
  return path;
}

function assertDirectory(root, relPath) {
  const path = join(root, relPath);
  if (!existsSync(path) || !statSync(path).isDirectory()) {
    fail(`Required directory missing: ${formatPath(root, path)}`);
  }
  return path;
}

function formatPath(root, path) {
  const rel = relative(root, path);
  return rel === '' ? '.' : rel.split(sep).join('/');
}

function readJson(path) {
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch (error) {
    fail(`Invalid JSON ${path}: ${error.message}`);
  }
}

function collectManifestFiles(manifest) {
  const files = new Set();
  const referencedEntries = new Set();

  for (const [key, entry] of Object.entries(manifest)) {
    if (!entry || typeof entry !== 'object') {
      fail(`Manifest entry ${key} is not an object.`);
    }

    if (typeof entry.file === 'string' && entry.file.trim() !== '') {
      files.add(entry.file);
    } else if (!key.startsWith('node_modules/')) {
      fail(`Manifest entry ${key} does not contain a file.`);
    }

    for (const listKey of ['css', 'assets']) {
      const list = entry[listKey];
      if (!Array.isArray(list)) {
        continue;
      }
      for (const file of list) {
        if (typeof file === 'string' && file.trim() !== '') {
          files.add(file);
        }
      }
    }

    for (const listKey of ['imports', 'dynamicImports']) {
      const list = entry[listKey];
      if (!Array.isArray(list)) {
        continue;
      }
      for (const importKey of list) {
        if (typeof importKey === 'string' && importKey.trim() !== '') {
          referencedEntries.add(importKey);
        }
      }
    }
  }

  for (const importKey of referencedEntries) {
    if (!Object.prototype.hasOwnProperty.call(manifest, importKey)) {
      fail(`Manifest references missing entry: ${importKey}`);
    }
  }

  return Array.from(files).sort();
}

function validateBuildManifest(root) {
  const manifestPath = assertFile(root, 'public/build/manifest.json');
  const manifest = readJson(manifestPath);
  const requiredEntries = [
    'src/frontend/main.js',
    'src/frontend/app/runtime.js',
    'src/frontend/app/supervisor/runtime.js',
  ];

  for (const entryKey of requiredEntries) {
    if (!manifest[entryKey]) {
      fail(`Required Vite manifest entry missing: ${entryKey}`);
    }
  }

  const allFiles = collectManifestFiles(manifest);
  for (const file of allFiles) {
    const assetPath = join(root, 'public/build', file);
    if (!existsSync(assetPath) || !statSync(assetPath).isFile()) {
      fail(`Manifest asset missing: public/build/${file}`);
    }
    if (statSync(assetPath).size <= 0) {
      fail(`Manifest asset is empty: public/build/${file}`);
    }
  }

  const entryValues = Object.values(manifest).filter((entry) => entry && typeof entry === 'object');
  const hasStageExamJs = entryValues.some((entry) => typeof entry.file === 'string' && /assets\/frontend-stage-exam-.+\.js$/.test(entry.file));
  const hasStageExamCss = entryValues.some((entry) => Array.isArray(entry.css) && entry.css.some((file) => /assets\/frontend-stage-exam-.+\.css$/.test(String(file))));
  const hasStageResultJs = entryValues.some((entry) => typeof entry.file === 'string' && /assets\/frontend-stage-result-.+\.js$/.test(entry.file));
  const hasStageResultCss = entryValues.some((entry) => Array.isArray(entry.css) && entry.css.some((file) => /assets\/(?:frontend-stage-result|frontend-result-renderer)-.+\.css$/.test(String(file))));

  if (!hasStageExamJs || !hasStageExamCss) {
    fail('Stage exam JS/CSS assets are missing from the Vite manifest.');
  }
  if (!hasStageResultJs || !hasStageResultCss) {
    fail('Stage result JS/CSS assets are missing from the Vite manifest.');
  }

  log(`Build manifest OK (${Object.keys(manifest).length} entries, ${allFiles.length} files).`);
}

function validatePluginHeader(root) {
  const pluginPath = assertFile(root, 'cbt-exam-system.php');
  const contents = readFileSync(pluginPath, 'utf8');
  const requiredHeaders = ['Version', 'Requires at least', 'Requires PHP'];

  for (const header of requiredHeaders) {
    const pattern = new RegExp(`^\\s*\\*?\\s*${header}:\\s*.+$`, 'mi');
    if (!pattern.test(contents)) {
      fail(`Plugin header is missing "${header}".`);
    }
  }

  log('Plugin header OK.');
}

function validateRequiredPackageFiles(root) {
  const requiredFiles = [
    'cbt-exam-system.php',
    'composer.json',
    'composer.lock',
    'includes/class-cbt-rest.php',
    'admin/class-cbt-admin.php',
    'public/build/manifest.json',
    'vendor/autoload.php',
  ];
  const requiredDirs = [
    'admin',
    'includes',
    'performance',
    'public/build/assets',
    'tests',
    'vendor/composer',
    'vendor/firebase',
    'vendor/phpoffice',
  ];

  for (const file of requiredFiles) {
    assertFile(root, file);
  }
  for (const dir of requiredDirs) {
    assertDirectory(root, dir);
  }
}

function validateStagedRoot(root) {
  validateRequiredPackageFiles(root);

  const forbiddenRoots = [
    '.git',
    '.github',
    'node_modules',
    'coverage',
    'playwright-results',
    'test-results',
    '.phpunit.cache',
    '.playwright-browsers',
    '.scannerwork',
  ];
  const forbiddenVendorDevDirs = [
    'vendor/phpunit',
    'vendor/brain',
    'vendor/mockery',
    'vendor/sebastian',
    'vendor/phar-io',
    'vendor/theseer',
    'vendor/hamcrest',
    'vendor/antecedent',
  ];

  for (const relPath of [...forbiddenRoots, ...forbiddenVendorDevDirs]) {
    const path = join(root, relPath);
    if (existsSync(path)) {
      fail(`Forbidden release package path exists: ${relPath}`);
    }
  }

  log('Staged package tree OK.');
}

function validateZip(zipPath) {
  if (zipPath === '') {
    return '';
  }
  const resolved = resolve(repoRoot, zipPath);
  assertFile('/', resolved.slice(1));
  const bytes = readFileSync(resolved);
  if (bytes.length < 1024) {
    fail(`Release ZIP looks too small: ${zipPath}`);
  }
  const sha256 = createHash('sha256').update(bytes).digest('hex');
  log(`Release ZIP OK (${bytes.length} bytes).`);
  return sha256;
}

function validateUpdateManifest(manifestPath, expectedSha256) {
  if (manifestPath === '') {
    return;
  }

  const resolved = resolve(repoRoot, manifestPath);
  assertFile('/', resolved.slice(1));
  const manifest = readJson(resolved);
  const requiredKeys = ['version', 'tag', 'published_at', 'download_url', 'sha256', 'requires_php', 'requires_wp', 'tested_up_to', 'changelog'];

  for (const key of requiredKeys) {
    if (typeof manifest[key] !== 'string' || manifest[key].trim() === '') {
      fail(`Update manifest missing string key: ${key}`);
    }
  }

  if (!/^v\d+\.\d+\.\d+$/.test(manifest.tag)) {
    fail(`Update manifest tag is invalid: ${manifest.tag}`);
  }
  if (!/^[a-f0-9]{64}$/.test(manifest.sha256)) {
    fail('Update manifest sha256 is not a 64-character hex digest.');
  }
  if (expectedSha256 !== '' && manifest.sha256 !== expectedSha256) {
    fail('Update manifest sha256 does not match release ZIP.');
  }
  if (!String(manifest.download_url).includes(String(manifest.tag))) {
    fail('Update manifest download_url does not include the release tag.');
  }

  log('Update manifest OK.');
}

const args = parseArgs(process.argv.slice(2));
const packageRoot = args.stagedRoot ? resolve(repoRoot, args.stagedRoot) : repoRoot;

if (!existsSync(packageRoot) || !statSync(packageRoot).isDirectory()) {
  fail(`Package root does not exist: ${packageRoot}`);
}

validatePluginHeader(packageRoot);
validateRequiredPackageFiles(packageRoot);
validateBuildManifest(packageRoot);

if (args.stagedRoot) {
  validateStagedRoot(packageRoot);
}

const zipSha256 = validateZip(args.zip);
validateUpdateManifest(args.updateManifest, zipSha256);
log(`Release package check passed for ${formatPath(repoRoot, packageRoot)}.`);
