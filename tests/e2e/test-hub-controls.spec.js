const { test, expect } = require('@playwright/test');
const {
    getE2ECatalog,
    getE2ETestHubJobs,
    resetE2ETestHub,
    seedE2ETestHubJob,
} = require('./helpers/e2e-fixture');
const {
    loginToWpAdmin,
    openTestHubPage,
} = require('./helpers/admin-browser');
const { waitForCondition } = require('./helpers/flow-utils');

test.describe.configure({ mode: 'serial' });

function smokePanel(page) {
    return page.locator('[data-checklist-panel="smoke_tests"].is-active').first();
}

function smokeItem(page, index = 0) {
    return smokePanel(page).locator('.cbt-test-hub-list > li').nth(index);
}

function latestJobForItem(jobs, itemIndex) {
    return (Array.isArray(jobs) ? jobs : [])
        .filter((job) => Number(job.item_index || 0) === Number(itemIndex))
        .sort((left, right) => Number(right.created_at || 0) - Number(left.created_at || 0))[0] || null;
}

async function waitForLatestJobStatus(itemIndex, status) {
    return waitForCondition(() => {
        const latest = latestJobForItem(getE2ETestHubJobs(), itemIndex);
        return latest && String(latest.status || '') === status ? latest : null;
    }, {
        timeoutMs: 8000,
        intervalMs: 250,
        errorMessage: `Timed out waiting for Test Hub item ${itemIndex} job status ${status}.`,
    });
}

async function submitButtonAndWait(page, button) {
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => {}),
        button.click({ force: true }),
    ]);
    await expect(page.locator('.cbt-test-hub-page')).toBeVisible({ timeout: 20000 });
}

test.describe('CBT Test Hub UI controls', () => {
    test.afterEach(() => {
        resetE2ETestHub();
    });

    test('Runner Health refresh and flow job cancel retry clear controls work from admin UI', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const catalog = getE2ECatalog();
        const adminUser = catalog.users.admin_seed;
        resetE2ETestHub({
            e2e_base_url: baseURL,
            e2e_frontend_url: baseURL,
        });

        const now = Math.floor(Date.now() / 1000);
        seedE2ETestHubJob({
            job_id: 'flow-e2e-worker-blocker',
            tab: 'sync_rest',
            scope: 'smoke_tests',
            item_index: 1,
            item_label: 'E2E worker blocker',
            status: 'running',
            created_at: now,
            started_at: now,
            heartbeat_at: now,
            worker_pid: 0,
        });

        await loginToWpAdmin(page, adminUser);
        await openTestHubPage(page, 'sync_rest', 'smoke_tests');

        const healthBox = page.locator('.cbt-test-hub-health-box').first();
        await expect(healthBox).toBeVisible({ timeout: 20000 });
        await page.locator('#cbt-test-hub-e2e-base-url').fill(String(baseURL));
        await page.locator('#cbt-test-hub-e2e-frontend-url').fill(String(baseURL));
        await submitButtonAndWait(page, page.getByRole('button', { name: 'Simpan Playwright Settings' }));
        await expect(page.getByText('Pengaturan Playwright E2E berhasil disimpan.')).toBeVisible({ timeout: 20000 });

        await submitButtonAndWait(page, page.getByRole('button', { name: 'Refresh Runner Health' }));
        await expect(healthBox).toContainText(/Ready|Warning|Blocked/, { timeout: 20000 });
        await expect(healthBox).toContainText(/Node\.js|PHP proc_open|Job Directory/, { timeout: 20000 });

        const firstItem = smokeItem(page, 0);
        const runButton = firstItem.getByRole('button', { name: /^Run Task$/ }).first();
        await expect(runButton).toBeVisible({ timeout: 20000 });
        test.skip(await runButton.isDisabled(), 'Runner Health blocked atau runner flow-check tidak tersedia di environment ini.');

        await submitButtonAndWait(page, runButton);
        const queuedJob = await waitForLatestJobStatus(0, 'queued');
        await expect(smokeItem(page, 0)).toContainText('Queued', { timeout: 20000 });
        await expect(smokeItem(page, 0).getByRole('button', { name: /^Cancel$/ })).toBeVisible({ timeout: 20000 });

        await submitButtonAndWait(page, smokeItem(page, 0).getByRole('button', { name: /^Cancel$/ }).first());
        const cancelledJob = await waitForLatestJobStatus(0, 'cancelled');
        expect(cancelledJob.job_id).toBe(queuedJob.job_id);
        await expect(smokeItem(page, 0)).toContainText('Cancelled', { timeout: 20000 });
        await expect(smokeItem(page, 0).getByRole('button', { name: /^Retry$/ })).toBeVisible({ timeout: 20000 });
        await expect(smokeItem(page, 0).getByRole('button', { name: /^Clear$/ })).toBeVisible({ timeout: 20000 });

        await submitButtonAndWait(page, smokeItem(page, 0).getByRole('button', { name: /^Retry$/ }).first());
        const retryJob = await waitForLatestJobStatus(0, 'queued');
        expect(retryJob.job_id).not.toBe(cancelledJob.job_id);
        expect(String(retryJob.retry_of_job_id || '')).toBe(cancelledJob.job_id);
        await expect(smokeItem(page, 0)).toContainText('Queued', { timeout: 20000 });

        await submitButtonAndWait(page, smokeItem(page, 0).getByRole('button', { name: /^Cancel$/ }).first());
        await waitForLatestJobStatus(0, 'cancelled');

        await submitButtonAndWait(page, smokeItem(page, 0).getByRole('button', { name: /^Clear$/ }).first());
        await waitForCondition(() => {
            return latestJobForItem(getE2ETestHubJobs(), 0) === null;
        }, {
            timeoutMs: 8000,
            intervalMs: 250,
            errorMessage: 'Timed out waiting for Test Hub item jobs to be cleared.',
        });

        await expect(smokeItem(page, 0).getByRole('button', { name: /^Run Task$/ })).toBeVisible({ timeout: 20000 });
        await expect(smokeItem(page, 0)).not.toContainText('Cancelled');
    });

    test('Artifact viewer and repair stuck jobs are available from admin UI', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const catalog = getE2ECatalog();
        const adminUser = catalog.users.admin_seed;
        const now = Math.floor(Date.now() / 1000);
        resetE2ETestHub({
            e2e_base_url: baseURL,
            e2e_frontend_url: baseURL,
        });
        seedE2ETestHubJob({
            job_id: 'flow-e2e-artifacts',
            tab: 'sync_rest',
            scope: 'smoke_tests',
            item_index: 0,
            item_label: 'E2E artifact viewer',
            status: 'failed',
            created_at: now + 1,
            finished_at: now,
            failure_summary: '<script>alert(1)</script> expected failure',
            stdout: '<script>alert(2)</script> stdout',
            log_content: 'first log line\n<script>alert(3)</script> log',
            artifacts: {
                'trace.zip': 'trace',
            },
        });
        seedE2ETestHubJob({
            job_id: 'flow-e2e-stuck-no-pid',
            tab: 'sync_rest',
            scope: 'smoke_tests',
            item_index: 1,
            item_label: 'E2E stuck no pid',
            status: 'running',
            created_at: now,
            started_at: now - 60,
            heartbeat_at: now,
            worker_pid: 0,
        });

        await loginToWpAdmin(page, adminUser);
        await openTestHubPage(page, 'sync_rest', 'smoke_tests');

        const firstItem = smokeItem(page, 0);
        await expect(firstItem).toContainText('Log & Artifacts', { timeout: 20000 });
        await expect(firstItem.getByRole('link', { name: 'Download Log' })).toBeVisible({ timeout: 20000 });
        await expect(firstItem.getByRole('link', { name: 'trace.zip' })).toBeVisible({ timeout: 20000 });
        await expect(firstItem).toContainText('first log line', { timeout: 20000 });
        await expect(firstItem.locator('script')).toHaveCount(0);

        const repairButton = page.getByRole('button', { name: 'Repair Stuck Jobs' }).first();
        await expect(repairButton).toBeEnabled({ timeout: 20000 });
        await submitButtonAndWait(page, repairButton);
        await expect(page.getByText(/Repair Stuck Jobs selesai:/)).toBeVisible({ timeout: 20000 });

        const repaired = latestJobForItem(getE2ETestHubJobs(), 1);
        expect(repaired).toBeTruthy();
        expect(String(repaired.status || '')).toBe('failed');
        expect(String(repaired.failure_kind || '')).toBe('interrupted');
        await expect(smokeItem(page, 1)).toContainText('Interrupted', { timeout: 20000 });
    });
});
