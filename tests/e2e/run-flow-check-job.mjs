import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn } from 'node:child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..', '..');
const workerScriptPath = path.resolve(__filename);

function getArgValue(name) {
    const prefix = `${name}=`;
    const direct = process.argv.find((entry) => entry.startsWith(prefix));
    if (direct) {
        return direct.slice(prefix.length);
    }

    const index = process.argv.indexOf(name);
    if (index >= 0 && process.argv[index + 1]) {
        return process.argv[index + 1];
    }

    return '';
}

function normalizeJobStatus(status) {
    const safe = String(status || '').trim().toLowerCase();
    if (['queued', 'running', 'passed', 'failed'].includes(safe)) {
        return safe;
    }

    return 'queued';
}

function readJobFile(jobFilePath) {
    if (!fs.existsSync(jobFilePath)) {
        return null;
    }

    try {
        const raw = fs.readFileSync(jobFilePath, 'utf8');
        return JSON.parse(raw);
    } catch (error) {
        return null;
    }
}

function writeJobFile(jobFilePath, payload) {
    fs.mkdirSync(path.dirname(jobFilePath), { recursive: true, mode: 0o777 });
    fs.writeFileSync(jobFilePath, JSON.stringify(payload, null, 2));
    try {
        fs.chmodSync(jobFilePath, 0o666);
    } catch (error) {
        // Best effort only.
    }
}

function currentUnixTime() {
    return Math.floor(Date.now() / 1000);
}

function summarizeFailure(stdout, stderr) {
    const combined = `${String(stderr || '')}\n${String(stdout || '')}`
        .replace(/\r\n/g, '\n')
        .split('\n')
        .map((line) => line.replace(/\x1B\[[0-9;]*[A-Za-z]/g, '').trim())
        .filter(Boolean);

    const preferred = combined.find((line) => /^Error:/i.test(line))
        || combined.find((line) => /\bTimed out\b/i.test(line))
        || combined.find((line) => /\bfailed\b/i.test(line))
        || combined.find((line) => /\bpermission denied\b/i.test(line))
        || combined.find((line) => /\bskipped\b/i.test(line))
        || combined[0];

    return preferred ? preferred.slice(0, 220) : 'Flow check gagal tanpa ringkasan error yang jelas.';
}

function writeJobLog(job) {
    const logPath = String(job.log_path || '').trim();
    if (logPath === '') {
        return;
    }

    const sections = [];
    const results = Array.isArray(job.results) ? job.results : [];
    results.forEach((result) => {
        sections.push(`### ${String(result.label || 'Flow Check Command')}`);
        sections.push(String(result.command || ''));
        sections.push('');
        sections.push('STDOUT');
        sections.push(String(result.stdout || ''));
        sections.push('');
        sections.push('STDERR');
        sections.push(String(result.stderr || ''));
        sections.push('');
    });

    fs.mkdirSync(path.dirname(logPath), { recursive: true, mode: 0o777 });
    fs.writeFileSync(logPath, sections.join('\n'));
    try {
        fs.chmodSync(logPath, 0o666);
    } catch (error) {
        // Best effort only.
    }
}

function buildCommandEnvironment() {
    return {
        ...process.env,
        PLAYWRIGHT_OUTPUT_DIR: path.join(jobDirectory, `${jobId}-artifacts`),
    };
}

function runCommand(commandDefinition) {
    const label = String(commandDefinition && commandDefinition.label ? commandDefinition.label : 'Flow Check Command');
    const command = String(commandDefinition && commandDefinition.command ? commandDefinition.command : '').trim();
    if (command === '') {
        return Promise.resolve({
            label,
            command,
            success: false,
            exit_code: 1,
            stdout: '',
            stderr: 'Command flow check kosong.',
            failure_summary: 'Command flow check kosong.',
        });
    }

    return new Promise((resolve) => {
        const child = spawn('/bin/bash', ['-lc', command], {
            cwd: projectRoot,
            env: buildCommandEnvironment(),
            stdio: ['ignore', 'pipe', 'pipe'],
        });

        let stdout = '';
        let stderr = '';
        let settled = false;

        function resolveOnce(payload) {
            if (settled) {
                return;
            }

            settled = true;
            resolve(payload);
        }

        if (child.stdout) {
            child.stdout.on('data', (chunk) => {
                stdout += String(chunk);
            });
        }

        if (child.stderr) {
            child.stderr.on('data', (chunk) => {
                stderr += String(chunk);
            });
        }

        child.on('error', (error) => {
            const safeError = error instanceof Error ? error.message : String(error || 'Unknown process error.');
            resolveOnce({
                label,
                command,
                success: false,
                exit_code: 1,
                stdout: stdout.trim(),
                stderr: `${stderr}\n${safeError}`.trim(),
                failure_summary: summarizeFailure(stdout, `${stderr}\n${safeError}`),
            });
        });

        child.on('close', (code) => {
            const cleanStdout = stdout.trim();
            const cleanStderr = stderr.trim();
            const exitCode = typeof code === 'number' ? code : 1;
            const skipped = cleanStdout.includes('PLAYWRIGHT RECOVERY FLOW SKIPPED') || cleanStderr.includes('PLAYWRIGHT RECOVERY FLOW SKIPPED');
            const success = exitCode === 0 && !skipped;

            resolveOnce({
                label,
                command,
                success,
                exit_code: success ? 0 : (exitCode === 0 && skipped ? 1 : exitCode),
                stdout: cleanStdout,
                stderr: cleanStderr,
                failure_summary: success ? '' : summarizeFailure(cleanStdout, cleanStderr),
            });
        });
    });
}

function readAllJobs(jobDirectory) {
    if (!fs.existsSync(jobDirectory)) {
        return [];
    }

    return fs.readdirSync(jobDirectory)
        .filter((entry) => entry.endsWith('.json'))
        .map((entry) => readJobFile(path.join(jobDirectory, entry)))
        .filter((entry) => entry && typeof entry === 'object');
}

function findNextQueuedJob(jobDirectory, currentJobId) {
    const jobs = readAllJobs(jobDirectory)
        .sort((left, right) => Number(left.created_at || 0) - Number(right.created_at || 0));

    const hasOtherRunning = jobs.some((job) => {
        const status = normalizeJobStatus(job.status);
        return status === 'running' && String(job.job_id || '') !== String(currentJobId || '');
    });
    if (hasOtherRunning) {
        return null;
    }

    return jobs.find((job) => normalizeJobStatus(job.status) === 'queued') || null;
}

function launchNextQueuedJob(jobDirectory, currentJobId) {
    const nextJob = findNextQueuedJob(jobDirectory, currentJobId);
    if (!nextJob || !nextJob.job_id) {
        return;
    }

    const child = spawn(process.execPath, [workerScriptPath, `--job-id=${String(nextJob.job_id)}`], {
        cwd: projectRoot,
        detached: true,
        stdio: 'ignore',
        env: {
            ...process.env,
            CBT_FLOW_JOB_ID: String(nextJob.job_id),
            CBT_FLOW_JOB_FILE: path.join(jobDirectory, `${String(nextJob.job_id)}.json`),
        },
    });
    child.unref();
}

const jobId = String(process.env.CBT_FLOW_JOB_ID || getArgValue('--job-id') || '').trim();
const jobFilePath = String(process.env.CBT_FLOW_JOB_FILE || '').trim() || path.join(projectRoot, 'playwright-results', 'admin-jobs', `${jobId}.json`);
const jobDirectory = path.dirname(jobFilePath);

if (jobId === '') {
    process.exit(1);
}

const job = readJobFile(jobFilePath);
if (!job || String(job.job_id || '') !== jobId) {
    process.exit(1);
}

job.status = 'running';
job.started_at = currentUnixTime();
job.heartbeat_at = currentUnixTime();
job.worker_pid = process.pid;
writeJobFile(jobFilePath, job);

const heartbeatTimer = setInterval(() => {
    job.heartbeat_at = currentUnixTime();
    job.worker_pid = process.pid;
    writeJobFile(jobFilePath, job);
}, 5_000);
heartbeatTimer.unref();

const commandDefinitions = Array.isArray(job.commands) ? job.commands : [];
const results = [];
let success = true;

try {
    for (const commandDefinition of commandDefinitions) {
        const result = await runCommand(commandDefinition);
        results.push(result);
        if (!result.success) {
            success = false;
        }
    }

    job.results = results;
    job.stdout = results.map((result) => String(result.stdout || '')).filter(Boolean).join('\n\n');
    job.stderr = results.map((result) => String(result.stderr || '')).filter(Boolean).join('\n\n');
    job.exit_code = success ? 0 : Number((results.find((result) => !result.success) || {}).exit_code || 1);
    job.failure_summary = success ? '' : summarizeFailure(job.stdout, job.stderr);
    job.status = success ? 'passed' : 'failed';
    job.finished_at = currentUnixTime();
    job.heartbeat_at = currentUnixTime();
} finally {
    clearInterval(heartbeatTimer);
    writeJobLog(job);
    writeJobFile(jobFilePath, job);
    launchNextQueuedJob(jobDirectory, jobId);
}

process.exit(success ? 0 : 1);
