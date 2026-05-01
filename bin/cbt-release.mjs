#!/usr/bin/env node

import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const scriptDir = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(scriptDir, '..');
const pluginFile = join(repoRoot, 'cbt-exam-system.php');
const packageFile = join(repoRoot, 'package.json');
const packageLockFile = join(repoRoot, 'package-lock.json');

function printUsage() {
  console.log(`Usage:
  npm run release:plugin -- <version> [options]

Examples:
  npm run release:plugin -- 3.2.0
  npm run release:plugin -- 3.2.0 --notes "Release notes"
  npm run release:plugin -- 3.2.0 --notes-file CHANGELOG.md
  npm run release:plugin -- 3.2.0 --dry-run

Options:
  --branch <name>       Required release branch. Default: main
  --remote <name>       Git remote to push. Default: origin
  --notes <text>        Annotated tag/release notes.
  --notes-file <path>   Read annotated tag/release notes from a file.
  --dry-run             Validate and print actions without changing files.
  --help                Show this help.
`);
}

function fail(message) {
  console.error(`\n[release] ${message}`);
  process.exit(1);
}

function parseArgs(argv) {
  const options = {
    branch: 'main',
    remote: 'origin',
    notes: '',
    notesFile: '',
    dryRun: false,
  };
  const positional = [];

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];

    if (arg === '--help' || arg === '-h') {
      printUsage();
      process.exit(0);
    }

    if (arg === '--dry-run') {
      options.dryRun = true;
      continue;
    }

    const optionMatch = arg.match(/^--([^=]+)=(.*)$/);
    if (optionMatch) {
      setOption(options, optionMatch[1], optionMatch[2]);
      continue;
    }

    if (arg.startsWith('--')) {
      const name = arg.slice(2);
      if (!['branch', 'remote', 'notes', 'notes-file'].includes(name)) {
        fail(`Unknown option: ${arg}`);
      }
      const value = argv[index + 1];
      if (!value || value.startsWith('--')) {
        fail(`Option ${arg} requires a value.`);
      }
      setOption(options, name, value);
      index += 1;
      continue;
    }

    positional.push(arg);
  }

  if (positional.length !== 1) {
    printUsage();
    fail('Provide exactly one release version, for example 3.2.0.');
  }

  if (options.notes && options.notesFile) {
    fail('Use either --notes or --notes-file, not both.');
  }

  return {
    version: positional[0],
    options,
  };
}

function setOption(options, name, value) {
  switch (name) {
    case 'branch':
      options.branch = value;
      break;
    case 'remote':
      options.remote = value;
      break;
    case 'notes':
      options.notes = value;
      break;
    case 'notes-file':
      options.notesFile = value;
      break;
    default:
      fail(`Unknown option: --${name}`);
  }
}

function run(command, args, options = {}) {
  const result = spawnSync(command, args, {
    cwd: repoRoot,
    encoding: 'utf8',
    stdio: options.stdio || 'pipe',
  });

  if (result.error) {
    fail(`Unable to run ${command}: ${result.error.message}`);
  }

  if (result.status !== 0 && !options.allowFailure) {
    const detail = [result.stdout, result.stderr].filter(Boolean).join('\n').trim();
    fail(`Command failed: ${command} ${args.join(' ')}${detail ? `\n${detail}` : ''}`);
  }

  return result;
}

function git(args, options = {}) {
  const stdout = run('git', args, options).stdout;
  return typeof stdout === 'string' ? stdout.trim() : '';
}

function assertSemver(version) {
  if (!/^\d+\.\d+\.\d+$/.test(version)) {
    fail(`Invalid version "${version}". Use MAJOR.MINOR.PATCH, for example 3.2.0.`);
  }
}

function compareVersions(left, right) {
  const leftParts = left.split('.').map(Number);
  const rightParts = right.split('.').map(Number);

  for (let index = 0; index < 3; index += 1) {
    if (leftParts[index] > rightParts[index]) {
      return 1;
    }
    if (leftParts[index] < rightParts[index]) {
      return -1;
    }
  }

  return 0;
}

function readPluginVersion() {
  const contents = readFileSync(pluginFile, 'utf8');
  const headerMatch = contents.match(/^\s*(?:\*\s*)?Version:\s*([^\r\n]+)/mi);
  const constantMatch = contents.match(/define\(\s*['"]CBT_EXAM_SYSTEM_VERSION['"]\s*,\s*['"]([^'"]+)['"]\s*\);/);

  if (!headerMatch) {
    fail('Unable to read Version header from cbt-exam-system.php.');
  }
  if (!constantMatch) {
    fail('Unable to read CBT_EXAM_SYSTEM_VERSION constant from cbt-exam-system.php.');
  }

  const headerVersion = headerMatch[1].trim();
  const constantVersion = constantMatch[1].trim();

  if (headerVersion !== constantVersion) {
    fail(`Version header (${headerVersion}) does not match CBT_EXAM_SYSTEM_VERSION (${constantVersion}).`);
  }

  assertSemver(headerVersion);

  return {
    contents,
    version: headerVersion,
  };
}

function readPackageVersion() {
  if (!existsSync(packageFile)) {
    return '';
  }

  const contents = readFileSync(packageFile, 'utf8');
  const pkg = JSON.parse(contents);

  return typeof pkg.version === 'string' ? pkg.version.trim() : '';
}

function writePluginVersion(contents, version) {
  let replacements = 0;
  const nextContents = contents
    .replace(/^(\s*(?:\*\s*)?Version:\s*)[^\r\n]+/mi, (_match, prefix) => {
      replacements += 1;
      return `${prefix}${version}`;
    })
    .replace(
      /(define\(\s*['"]CBT_EXAM_SYSTEM_VERSION['"]\s*,\s*['"])([^'"]+)(['"]\s*\);)/,
      (_match, prefix, _oldVersion, suffix) => {
        replacements += 1;
        return `${prefix}${version}${suffix}`;
      },
    );

  if (replacements !== 2) {
    fail('Unable to update both plugin version locations safely.');
  }

  writeFileSync(pluginFile, nextContents, 'utf8');
}

function writePackageVersion(version) {
  if (!existsSync(packageFile)) {
    return [];
  }

  const updatedFiles = ['package.json'];
  const pkg = JSON.parse(readFileSync(packageFile, 'utf8'));
  pkg.version = version;
  writeFileSync(packageFile, `${JSON.stringify(pkg, null, 2)}\n`, 'utf8');

  if (existsSync(packageLockFile)) {
    const lock = JSON.parse(readFileSync(packageLockFile, 'utf8'));
    lock.version = version;
    if (lock.packages && lock.packages['']) {
      lock.packages[''].version = version;
    }
    writeFileSync(packageLockFile, `${JSON.stringify(lock, null, 2)}\n`, 'utf8');
    updatedFiles.push('package-lock.json');
  }

  return updatedFiles;
}

function assertCleanWorktree() {
  const status = git(['status', '--porcelain']);
  if (status !== '') {
    fail(`Worktree is dirty. Commit or stash changes before release:\n${status}`);
  }
}

function assertBranch(expectedBranch) {
  const branch = git(['branch', '--show-current']);
  if (branch !== expectedBranch) {
    fail(`Release must run on branch "${expectedBranch}". Current branch: "${branch || '(detached)'}".`);
  }
}

function assertRemote(remote) {
  git(['remote', 'get-url', remote]);
}

function assertTagAvailable(remote, tagName) {
  const localTag = run('git', ['rev-parse', '-q', '--verify', `refs/tags/${tagName}`], { allowFailure: true });
  if (localTag.status === 0) {
    fail(`Local tag ${tagName} already exists.`);
  }

  const remoteTag = git(['ls-remote', '--tags', remote, `refs/tags/${tagName}`]);
  if (remoteTag !== '') {
    fail(`Remote tag ${tagName} already exists on ${remote}.`);
  }
}

function readNotesFile(notesFile) {
  const path = resolve(repoRoot, notesFile);
  if (!existsSync(path)) {
    fail(`Notes file does not exist: ${notesFile}`);
  }

  return readFileSync(path, 'utf8').trim();
}

function getPreviousReleaseTag(nextVersion) {
  const tags = git(['tag', '--list', 'v[0-9]*.[0-9]*.[0-9]*', '--sort=-version:refname'])
    .split('\n')
    .map((tag) => tag.trim())
    .filter(Boolean);

  return tags.find((tag) => compareVersions(tag.slice(1), nextVersion) < 0) || '';
}

function buildReleaseNotes(version, options) {
  if (options.notes) {
    return options.notes.trim();
  }

  if (options.notesFile) {
    return readNotesFile(options.notesFile);
  }

  const previousTag = getPreviousReleaseTag(version);
  const range = previousTag ? `${previousTag}..HEAD` : 'HEAD';
  const log = git(['log', '--no-merges', '--pretty=format:- %s', range]);

  return log || `Release ${version}`;
}

function printStep(message) {
  console.log(`[release] ${message}`);
}

function runRelease(version, options) {
  assertSemver(version);

  git(['rev-parse', '--is-inside-work-tree']);
  assertBranch(options.branch);
  assertCleanWorktree();
  assertRemote(options.remote);

  const tagName = `v${version}`;
  const plugin = readPluginVersion();
  const packageVersion = readPackageVersion();

  if (packageVersion !== '' && packageVersion !== plugin.version) {
    fail(`package.json version (${packageVersion}) does not match plugin version (${plugin.version}).`);
  }

  if (compareVersions(version, plugin.version) <= 0) {
    fail(`New version ${version} must be greater than current version ${plugin.version}.`);
  }

  assertTagAvailable(options.remote, tagName);

  const releaseNotes = buildReleaseNotes(version, options);
  if (!releaseNotes.trim()) {
    fail('Release notes are empty.');
  }

  printStep(`Current version: ${plugin.version}`);
  printStep(`Next version: ${version}`);
  printStep(`Tag: ${tagName}`);
  printStep(`Branch: ${options.branch}`);
  printStep(`Remote: ${options.remote}`);

  if (options.dryRun) {
    printStep('Dry run enabled. No files, commits, tags, or pushes will be created.');
    printStep(`Would update ${pluginFile}`);
    if (existsSync(packageFile)) {
      printStep(`Would update ${packageFile}`);
    }
    if (existsSync(packageLockFile)) {
      printStep(`Would update ${packageLockFile}`);
    }
    printStep(`Would commit: chore(release): ${tagName}`);
    printStep(`Would create annotated tag ${tagName}`);
    printStep(`Would push ${options.branch} and ${tagName} to ${options.remote}`);
    return;
  }

  writePluginVersion(plugin.contents, version);
  const packageFiles = writePackageVersion(version);
  git(['add', 'cbt-exam-system.php', ...packageFiles]);
  git(['commit', '-m', `chore(release): ${tagName}`], { stdio: 'inherit' });

  const tempDir = mkdtempSync(join(tmpdir(), 'cbt-release-'));
  const notesPath = join(tempDir, 'release-notes.md');

  try {
    writeFileSync(notesPath, `${releaseNotes.trim()}\n`, 'utf8');
    git(['tag', '-a', tagName, '-F', notesPath]);
  } finally {
    rmSync(tempDir, { recursive: true, force: true });
  }

  git(['push', options.remote, options.branch], { stdio: 'inherit' });
  git(['push', options.remote, tagName], { stdio: 'inherit' });

  printStep('Release tag pushed. GitHub Actions will build the ZIP, manifest, and GitHub Release.');
  printStep(`Actions: https://github.com/coblax/CBT-EXAM-SYSTEM/actions`);
  printStep(`Release: https://github.com/coblax/CBT-EXAM-SYSTEM/releases/tag/${tagName}`);
}

const { version, options } = parseArgs(process.argv.slice(2));
runRelease(version, options);
