import { afterEach, describe, expect, it } from 'vitest';
import fs from 'node:fs';
import fsp from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn } from 'node:child_process';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..', '..', '..');
const tempDirs = [];

function currentUnixTime() {
  return Math.floor(Date.now() / 1000);
}

function readJob(jobFile) {
  return JSON.parse(fs.readFileSync(jobFile, 'utf8'));
}

function writeJob(jobFile, payload) {
  fs.writeFileSync(jobFile, JSON.stringify(payload, null, 2));
}

function wait(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

async function waitFor(predicate, timeoutMs = 4000) {
  const startedAt = Date.now();
  while (Date.now() - startedAt <= timeoutMs) {
    const result = await predicate();
    if (result) {
      return result;
    }
    await wait(50);
  }

  throw new Error('Timed out waiting for flow worker condition.');
}

afterEach(async () => {
  while (tempDirs.length > 0) {
    const tempDir = tempDirs.pop();
    await fsp.rm(tempDir, { recursive: true, force: true });
  }
});

describe('flow check worker', () => {
  it('fails malformed jobs that have no command definitions instead of passing empty work', async () => {
    const jobDir = await fsp.mkdtemp(path.join(os.tmpdir(), 'cbt-flow-worker-'));
    tempDirs.push(jobDir);

    const jobId = 'flow-worker-empty-command-list';
    const jobFile = path.join(jobDir, `${jobId}.json`);
    const job = {
      job_id: jobId,
      tab: 'sync_rest',
      scope: 'smoke_tests',
      item_index: 0,
      item_label: 'Empty command smoke',
      status: 'queued',
      created_at: currentUnixTime(),
      started_at: 0,
      finished_at: 0,
      heartbeat_at: 0,
      worker_pid: 0,
      active_child_pid: 0,
      cancel_requested_at: 0,
      command: '',
      commands: [],
      results: [],
      stdout: '',
      stderr: '',
      exit_code: 0,
      failure_kind: '',
      failure_summary: '',
      log_path: path.join(jobDir, `${jobId}.log`),
    };
    writeJob(jobFile, job);

    const worker = spawn(process.execPath, ['tests/e2e/run-flow-check-job.mjs', `--job-id=${jobId}`], {
      cwd: projectRoot,
      env: {
        ...process.env,
        CBT_FLOW_JOB_ID: jobId,
        CBT_FLOW_JOB_FILE: jobFile,
      },
      stdio: 'ignore',
    });

    const exitCode = await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        worker.kill('SIGTERM');
        reject(new Error('Worker did not exit after empty command list.'));
      }, 5000);
      worker.on('exit', (code) => {
        clearTimeout(timeout);
        resolve(typeof code === 'number' ? code : 1);
      });
    });

    expect(exitCode).not.toBe(0);

    const failedJob = await waitFor(() => {
      const current = readJob(jobFile);
      return current.status === 'failed' ? current : null;
    });

    expect(failedJob.status).toBe('failed');
    expect(failedJob.exit_code).toBe(1);
    expect(failedJob.failure_summary).toBe('Command flow check kosong.');
    expect(failedJob.results).toHaveLength(1);
    expect(failedJob.results[0].success).toBe(false);
  });

  it('marks any Playwright flow skipped output as failed instead of passed', async () => {
    const jobDir = await fsp.mkdtemp(path.join(os.tmpdir(), 'cbt-flow-worker-'));
    tempDirs.push(jobDir);

    const jobId = 'flow-worker-generic-skip';
    const jobFile = path.join(jobDir, `${jobId}.json`);
    const job = {
      job_id: jobId,
      tab: 'result_scoring',
      scope: 'smoke_tests',
      item_index: 0,
      item_label: 'Result skip smoke',
      status: 'queued',
      created_at: currentUnixTime(),
      started_at: 0,
      finished_at: 0,
      heartbeat_at: 0,
      worker_pid: 0,
      active_child_pid: 0,
      cancel_requested_at: 0,
      command: 'node -e "console.log(\'PLAYWRIGHT RESULT & EXPORT FLOW SKIPPED\')"',
      commands: [
        {
          label: 'Skipped flow command',
          command: 'node -e "console.log(\'PLAYWRIGHT RESULT & EXPORT FLOW SKIPPED\')"',
        },
      ],
      results: [],
      stdout: '',
      stderr: '',
      exit_code: 0,
      failure_kind: '',
      failure_summary: '',
      log_path: path.join(jobDir, `${jobId}.log`),
    };
    writeJob(jobFile, job);

    const worker = spawn(process.execPath, ['tests/e2e/run-flow-check-job.mjs', `--job-id=${jobId}`], {
      cwd: projectRoot,
      env: {
        ...process.env,
        CBT_FLOW_JOB_ID: jobId,
        CBT_FLOW_JOB_FILE: jobFile,
      },
      stdio: 'ignore',
    });

    const exitCode = await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        worker.kill('SIGTERM');
        reject(new Error('Worker did not exit after skipped flow command.'));
      }, 5000);
      worker.on('exit', (code) => {
        clearTimeout(timeout);
        resolve(typeof code === 'number' ? code : 1);
      });
    });

    expect(exitCode).not.toBe(0);

    const failedJob = await waitFor(() => {
      const current = readJob(jobFile);
      return current.status === 'failed' ? current : null;
    });

    expect(failedJob.status).toBe('failed');
    expect(failedJob.exit_code).toBe(1);
    expect(failedJob.failure_summary).toContain('PLAYWRIGHT RESULT & EXPORT FLOW SKIPPED');
    expect(failedJob.results[0].success).toBe(false);
    expect(failedJob.results[0].exit_code).toBe(1);
  });

  it('keeps job logs inside the flow job directory even when job payload has an outside log path', async () => {
    const jobDir = await fsp.mkdtemp(path.join(os.tmpdir(), 'cbt-flow-worker-'));
    const outsideDir = await fsp.mkdtemp(path.join(os.tmpdir(), 'cbt-flow-worker-outside-'));
    tempDirs.push(jobDir, outsideDir);

    const jobId = 'flow-worker-safe-log-path';
    const jobFile = path.join(jobDir, `${jobId}.json`);
    const outsideLogPath = path.join(outsideDir, 'outside.log');
    const job = {
      job_id: jobId,
      tab: 'sync_rest',
      scope: 'smoke_tests',
      item_index: 0,
      item_label: 'Safe log path smoke',
      status: 'queued',
      created_at: currentUnixTime(),
      started_at: 0,
      finished_at: 0,
      heartbeat_at: 0,
      worker_pid: 0,
      active_child_pid: 0,
      cancel_requested_at: 0,
      command: 'node -e "console.log(\'safe-log-ok\')"',
      commands: [
        {
          label: 'Safe log command',
          command: 'node -e "console.log(\'safe-log-ok\')"',
        },
      ],
      results: [],
      stdout: '',
      stderr: '',
      exit_code: 0,
      failure_kind: '',
      failure_summary: '',
      log_path: outsideLogPath,
    };
    writeJob(jobFile, job);
    expect(fs.existsSync(outsideLogPath)).toBe(false);

    const worker = spawn(process.execPath, ['tests/e2e/run-flow-check-job.mjs', `--job-id=${jobId}`], {
      cwd: projectRoot,
      env: {
        ...process.env,
        CBT_FLOW_JOB_ID: jobId,
        CBT_FLOW_JOB_FILE: jobFile,
      },
      stdio: 'ignore',
    });

    const exitCode = await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        worker.kill('SIGTERM');
        reject(new Error('Worker did not exit after safe log path test.'));
      }, 5000);
      worker.on('exit', (code) => {
        clearTimeout(timeout);
        resolve(typeof code === 'number' ? code : 1);
      });
    });

    expect(exitCode).toBe(0);

    const passedJob = await waitFor(() => {
      const current = readJob(jobFile);
      return current.status === 'passed' ? current : null;
    });
    const resolvedLogPath = path.resolve(passedJob.log_path);

    expect(fs.existsSync(outsideLogPath)).toBe(false);
    expect(resolvedLogPath.startsWith(`${path.resolve(jobDir)}${path.sep}`)).toBe(true);
    expect(fs.readFileSync(resolvedLogPath, 'utf8')).toContain('safe-log-ok');
  });

  it('launches the next queued job after completing the current one', async () => {
    const jobDir = await fsp.mkdtemp(path.join(os.tmpdir(), 'cbt-flow-worker-'));
    tempDirs.push(jobDir);

    const firstJobId = 'flow-worker-chain-first';
    const secondJobId = 'flow-worker-chain-second';
    const firstJobFile = path.join(jobDir, `${firstJobId}.json`);
    const secondJobFile = path.join(jobDir, `${secondJobId}.json`);
    const now = currentUnixTime();
    const baseJob = {
      tab: 'sync_rest',
      scope: 'smoke_tests',
      item_index: 0,
      status: 'queued',
      started_at: 0,
      finished_at: 0,
      heartbeat_at: 0,
      worker_pid: 0,
      active_child_pid: 0,
      cancel_requested_at: 0,
      results: [],
      stdout: '',
      stderr: '',
      exit_code: 0,
      failure_kind: '',
      failure_summary: '',
    };

    writeJob(firstJobFile, {
      ...baseJob,
      job_id: firstJobId,
      item_label: 'First chained smoke',
      created_at: now,
      command: 'node -e "console.log(\'first-ok\')"',
      commands: [
        {
          label: 'First command',
          command: 'node -e "console.log(\'first-ok\')"',
        },
      ],
      log_path: path.join(jobDir, `${firstJobId}.log`),
    });
    writeJob(secondJobFile, {
      ...baseJob,
      job_id: secondJobId,
      item_index: 1,
      item_label: 'Second chained smoke',
      created_at: now + 1,
      command: 'node -e "console.log(\'second-ok\')"',
      commands: [
        {
          label: 'Second command',
          command: 'node -e "console.log(\'second-ok\')"',
        },
      ],
      log_path: path.join(jobDir, `${secondJobId}.log`),
    });

    const worker = spawn(process.execPath, ['tests/e2e/run-flow-check-job.mjs', `--job-id=${firstJobId}`], {
      cwd: projectRoot,
      env: {
        ...process.env,
        CBT_FLOW_JOB_ID: firstJobId,
        CBT_FLOW_JOB_FILE: firstJobFile,
      },
      stdio: 'ignore',
    });

    const exitCode = await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        worker.kill('SIGTERM');
        reject(new Error('First worker did not finish chained queue test.'));
      }, 5000);
      worker.on('exit', (code) => {
        clearTimeout(timeout);
        resolve(typeof code === 'number' ? code : 1);
      });
    });

    expect(exitCode).toBe(0);

    const firstJob = await waitFor(() => {
      const current = readJob(firstJobFile);
      return current.status === 'passed' ? current : null;
    });
    const secondJob = await waitFor(() => {
      const current = readJob(secondJobFile);
      return current.status === 'passed' ? current : null;
    }, 8000);

    expect(firstJob.stdout).toContain('first-ok');
    expect(secondJob.stdout).toContain('second-ok');
    expect(secondJob.worker_pid).toBeGreaterThan(0);
  });

  it('skips malformed queued job files and launches the next valid queued job', async () => {
    const jobDir = await fsp.mkdtemp(path.join(os.tmpdir(), 'cbt-flow-worker-'));
    tempDirs.push(jobDir);

    const firstJobId = 'flow-worker-skip-malformed-first';
    const validNextJobId = 'flow-worker-skip-malformed-next';
    const firstJobFile = path.join(jobDir, `${firstJobId}.json`);
    const validNextJobFile = path.join(jobDir, `${validNextJobId}.json`);
    const now = currentUnixTime();
    const baseJob = {
      tab: 'sync_rest',
      scope: 'smoke_tests',
      item_index: 0,
      status: 'queued',
      started_at: 0,
      finished_at: 0,
      heartbeat_at: 0,
      worker_pid: 0,
      active_child_pid: 0,
      cancel_requested_at: 0,
      results: [],
      stdout: '',
      stderr: '',
      exit_code: 0,
      failure_kind: '',
      failure_summary: '',
    };

    writeJob(firstJobFile, {
      ...baseJob,
      job_id: firstJobId,
      item_label: 'First skip malformed smoke',
      created_at: now,
      command: 'node -e "console.log(\'first-ok\')"',
      commands: [
        {
          label: 'First command',
          command: 'node -e "console.log(\'first-ok\')"',
        },
      ],
      log_path: path.join(jobDir, `${firstJobId}.log`),
    });
    writeJob(path.join(jobDir, 'malformed-blank-id.json'), {
      ...baseJob,
      job_id: '',
      item_label: 'Malformed blank id smoke',
      created_at: now + 1,
      command: 'node -e "console.log(\'malformed-should-not-block\')"',
      commands: [
        {
          label: 'Malformed command',
          command: 'node -e "console.log(\'malformed-should-not-block\')"',
        },
      ],
      log_path: path.join(jobDir, 'malformed-blank-id.log'),
    });
    writeJob(validNextJobFile, {
      ...baseJob,
      job_id: validNextJobId,
      item_index: 2,
      item_label: 'Valid next smoke',
      created_at: now + 2,
      command: 'node -e "console.log(\'valid-next-ok\')"',
      commands: [
        {
          label: 'Valid next command',
          command: 'node -e "console.log(\'valid-next-ok\')"',
        },
      ],
      log_path: path.join(jobDir, `${validNextJobId}.log`),
    });

    const worker = spawn(process.execPath, ['tests/e2e/run-flow-check-job.mjs', `--job-id=${firstJobId}`], {
      cwd: projectRoot,
      env: {
        ...process.env,
        CBT_FLOW_JOB_ID: firstJobId,
        CBT_FLOW_JOB_FILE: firstJobFile,
      },
      stdio: 'ignore',
    });

    const exitCode = await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        worker.kill('SIGTERM');
        reject(new Error('First worker did not finish malformed queued job test.'));
      }, 5000);
      worker.on('exit', (code) => {
        clearTimeout(timeout);
        resolve(typeof code === 'number' ? code : 1);
      });
    });

    expect(exitCode).toBe(0);

    const validNextJob = await waitFor(() => {
      const current = readJob(validNextJobFile);
      return current.status === 'passed' ? current : null;
    }, 8000);
    const malformedJob = readJob(path.join(jobDir, 'malformed-blank-id.json'));

    expect(validNextJob.stdout).toContain('valid-next-ok');
    expect(validNextJob.worker_pid).toBeGreaterThan(0);
    expect(malformedJob.status).toBe('queued');
    expect(malformedJob.stdout).toBe('');
  });

  it('skips unknown-status job files instead of treating them as queued work', async () => {
    const jobDir = await fsp.mkdtemp(path.join(os.tmpdir(), 'cbt-flow-worker-'));
    tempDirs.push(jobDir);

    const firstJobId = 'flow-worker-skip-unknown-status-first';
    const unknownJobId = 'flow-worker-skip-unknown-status';
    const validNextJobId = 'flow-worker-skip-unknown-status-next';
    const firstJobFile = path.join(jobDir, `${firstJobId}.json`);
    const unknownJobFile = path.join(jobDir, `${unknownJobId}.json`);
    const validNextJobFile = path.join(jobDir, `${validNextJobId}.json`);
    const now = currentUnixTime();
    const baseJob = {
      tab: 'sync_rest',
      scope: 'smoke_tests',
      item_index: 0,
      started_at: 0,
      finished_at: 0,
      heartbeat_at: 0,
      worker_pid: 0,
      active_child_pid: 0,
      cancel_requested_at: 0,
      results: [],
      stdout: '',
      stderr: '',
      exit_code: 0,
      failure_kind: '',
      failure_summary: '',
    };

    writeJob(firstJobFile, {
      ...baseJob,
      job_id: firstJobId,
      item_label: 'First skip unknown status smoke',
      status: 'queued',
      created_at: now,
      command: 'node -e "console.log(\'first-ok\')"',
      commands: [
        {
          label: 'First command',
          command: 'node -e "console.log(\'first-ok\')"',
        },
      ],
      log_path: path.join(jobDir, `${firstJobId}.log`),
    });
    writeJob(unknownJobFile, {
      ...baseJob,
      job_id: unknownJobId,
      item_label: 'Unknown status smoke',
      status: 'mystery',
      created_at: now + 1,
      command: 'node -e "console.log(\'unknown-should-not-run\')"',
      commands: [
        {
          label: 'Unknown command',
          command: 'node -e "console.log(\'unknown-should-not-run\')"',
        },
      ],
      log_path: path.join(jobDir, `${unknownJobId}.log`),
    });
    writeJob(validNextJobFile, {
      ...baseJob,
      job_id: validNextJobId,
      item_index: 2,
      item_label: 'Valid next after unknown smoke',
      status: 'queued',
      created_at: now + 2,
      command: 'node -e "console.log(\'valid-next-ok\')"',
      commands: [
        {
          label: 'Valid next command',
          command: 'node -e "console.log(\'valid-next-ok\')"',
        },
      ],
      log_path: path.join(jobDir, `${validNextJobId}.log`),
    });

    const worker = spawn(process.execPath, ['tests/e2e/run-flow-check-job.mjs', `--job-id=${firstJobId}`], {
      cwd: projectRoot,
      env: {
        ...process.env,
        CBT_FLOW_JOB_ID: firstJobId,
        CBT_FLOW_JOB_FILE: firstJobFile,
      },
      stdio: 'ignore',
    });

    const exitCode = await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        worker.kill('SIGTERM');
        reject(new Error('First worker did not finish unknown-status queued job test.'));
      }, 5000);
      worker.on('exit', (code) => {
        clearTimeout(timeout);
        resolve(typeof code === 'number' ? code : 1);
      });
    });

    expect(exitCode).toBe(0);

    const validNextJob = await waitFor(() => {
      const current = readJob(validNextJobFile);
      return current.status === 'passed' ? current : null;
    }, 8000);
    const unknownJob = readJob(unknownJobFile);

    expect(validNextJob.stdout).toContain('valid-next-ok');
    expect(unknownJob.status).toBe('mystery');
    expect(unknownJob.stdout).toBe('');
  });

  it('does not launch the next queued job while another flow job is still running', async () => {
    const jobDir = await fsp.mkdtemp(path.join(os.tmpdir(), 'cbt-flow-worker-'));
    tempDirs.push(jobDir);

    const currentJobId = 'flow-worker-no-parallel-current';
    const runningJobId = 'flow-worker-no-parallel-running';
    const queuedJobId = 'flow-worker-no-parallel-queued';
    const currentJobFile = path.join(jobDir, `${currentJobId}.json`);
    const runningJobFile = path.join(jobDir, `${runningJobId}.json`);
    const queuedJobFile = path.join(jobDir, `${queuedJobId}.json`);
    const now = currentUnixTime();
    const baseJob = {
      tab: 'sync_rest',
      scope: 'smoke_tests',
      item_index: 0,
      started_at: 0,
      finished_at: 0,
      heartbeat_at: 0,
      worker_pid: 0,
      active_child_pid: 0,
      cancel_requested_at: 0,
      results: [],
      stdout: '',
      stderr: '',
      exit_code: 0,
      failure_kind: '',
      failure_summary: '',
    };

    writeJob(currentJobFile, {
      ...baseJob,
      job_id: currentJobId,
      item_label: 'Current no parallel smoke',
      status: 'queued',
      created_at: now,
      command: 'node -e "console.log(\'current-ok\')"',
      commands: [
        {
          label: 'Current command',
          command: 'node -e "console.log(\'current-ok\')"',
        },
      ],
      log_path: path.join(jobDir, `${currentJobId}.log`),
    });
    writeJob(runningJobFile, {
      ...baseJob,
      job_id: runningJobId,
      item_index: 1,
      item_label: 'Other running smoke',
      status: 'running',
      created_at: now + 1,
      started_at: now,
      heartbeat_at: now,
      worker_pid: 999999,
      command: 'node -e "setTimeout(() => {}, 2000)"',
      commands: [
        {
          label: 'Other running command',
          command: 'node -e "setTimeout(() => {}, 2000)"',
        },
      ],
      log_path: path.join(jobDir, `${runningJobId}.log`),
    });
    writeJob(queuedJobFile, {
      ...baseJob,
      job_id: queuedJobId,
      item_index: 2,
      item_label: 'Queued blocked smoke',
      status: 'queued',
      created_at: now + 2,
      command: 'node -e "console.log(\'queued-should-not-run\')"',
      commands: [
        {
          label: 'Queued command',
          command: 'node -e "console.log(\'queued-should-not-run\')"',
        },
      ],
      log_path: path.join(jobDir, `${queuedJobId}.log`),
    });

    const worker = spawn(process.execPath, ['tests/e2e/run-flow-check-job.mjs', `--job-id=${currentJobId}`], {
      cwd: projectRoot,
      env: {
        ...process.env,
        CBT_FLOW_JOB_ID: currentJobId,
        CBT_FLOW_JOB_FILE: currentJobFile,
      },
      stdio: 'ignore',
    });

    const exitCode = await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        worker.kill('SIGTERM');
        reject(new Error('Current worker did not finish no-parallel test.'));
      }, 5000);
      worker.on('exit', (code) => {
        clearTimeout(timeout);
        resolve(typeof code === 'number' ? code : 1);
      });
    });

    expect(exitCode).toBe(0);

    const currentJob = await waitFor(() => {
      const current = readJob(currentJobFile);
      return current.status === 'passed' ? current : null;
    });
    await wait(700);
    const runningJob = readJob(runningJobFile);
    const queuedJob = readJob(queuedJobFile);

    expect(currentJob.stdout).toContain('current-ok');
    expect(runningJob.status).toBe('running');
    expect(queuedJob.status).toBe('queued');
    expect(queuedJob.worker_pid).toBe(0);
    expect(queuedJob.stdout).toBe('');
  });

  it('honors a cancelling job file and persists cancelled status without passing the job', async () => {
    const jobDir = await fsp.mkdtemp(path.join(os.tmpdir(), 'cbt-flow-worker-'));
    tempDirs.push(jobDir);

    const jobId = 'flow-worker-cancel';
    const jobFile = path.join(jobDir, `${jobId}.json`);
    const job = {
      job_id: jobId,
      tab: 'sync_rest',
      scope: 'smoke_tests',
      item_index: 0,
      item_label: 'Worker cancel smoke',
      status: 'queued',
      created_at: currentUnixTime(),
      started_at: 0,
      finished_at: 0,
      heartbeat_at: 0,
      worker_pid: 0,
      active_child_pid: 0,
      cancel_requested_at: 0,
      command: 'node -e "setTimeout(() => {}, 800)"',
      commands: [
        {
          label: 'Cancelable command',
          command: 'node -e "setTimeout(() => {}, 800)"',
        },
      ],
      results: [],
      stdout: '',
      stderr: '',
      exit_code: 0,
      failure_kind: '',
      failure_summary: '',
      log_path: path.join(jobDir, `${jobId}.log`),
    };
    writeJob(jobFile, job);

    const worker = spawn(process.execPath, ['tests/e2e/run-flow-check-job.mjs', `--job-id=${jobId}`], {
      cwd: projectRoot,
      env: {
        ...process.env,
        CBT_FLOW_JOB_ID: jobId,
        CBT_FLOW_JOB_FILE: jobFile,
      },
      stdio: 'ignore',
    });

    await waitFor(() => {
      const current = readJob(jobFile);
      return current.status === 'running' && Number(current.active_child_pid || 0) > 0 ? current : null;
    });

    const cancellingJob = readJob(jobFile);
    cancellingJob.status = 'cancelling';
    cancellingJob.cancel_requested_at = currentUnixTime();
    writeJob(jobFile, cancellingJob);

    const exitCode = await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        worker.kill('SIGTERM');
        reject(new Error('Worker did not exit after cancellation.'));
      }, 5000);
      worker.on('exit', (code) => {
        clearTimeout(timeout);
        resolve(typeof code === 'number' ? code : 1);
      });
    });

    expect(exitCode).not.toBe(0);

    const cancelledJob = await waitFor(() => {
      const current = readJob(jobFile);
      return current.status === 'cancelled' ? current : null;
    });

    expect(cancelledJob.failure_kind).toBe('cancelled');
    expect(cancelledJob.failure_summary).toContain('dibatalkan');
    expect(cancelledJob.active_child_pid).toBe(0);
    expect(cancelledJob.status).not.toBe('passed');
  });
});
