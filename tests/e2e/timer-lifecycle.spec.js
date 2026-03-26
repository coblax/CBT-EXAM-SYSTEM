const { test, expect } = require('@playwright/test');
const {
    getE2EFixture,
    shiftLatestE2EAttemptStart,
} = require('./helpers/e2e-fixture');
const {
    answerCurrentSingleChoice,
    captureBrowserStorage,
    loginAsStudent,
    logoutFromFrontend,
    openRehydratedPage,
    openResultFromConfirm,
    startOrResumeAttempt,
    waitForResultShell,
} = require('./helpers/frontend-browser');
const { waitForCondition } = require('./helpers/flow-utils');

test.describe.configure({ mode: 'serial' });

async function prepareTimerAttempt(page, fixture) {
    await loginAsStudent(page, fixture.user);
    await startOrResumeAttempt(page, fixture);
    await answerCurrentSingleChoice(page, 0);
}

test.describe('Timer & Lifecycle flow check', () => {
    test.setTimeout(180000);

    test('Timer Flow: near-timeout countdown transitions cleanly to result', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('timer_lifecycle', 'primary_student');
        await test.step('Buat attempt aktif lalu geser start time hingga sisa waktu tinggal beberapa detik', async () => {
            await prepareTimerAttempt(page, fixture);
            shiftLatestE2EAttemptStart('timer_lifecycle', { remaining_seconds: 8 }, 'primary_student');
            await page.reload();
        });

        await test.step('Countdown bergerak ke nol lalu otomatis masuk ke result', async () => {
            await expect(page.locator('[data-cbt-timer]')).toBeVisible({ timeout: 20000 });
            await waitForResultShell(page);
        });
    });

    test('Timer Flow: resume keeps timer synced after extra time update', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('timer_lifecycle', 'primary_student');
        await test.step('Siapkan attempt timer lalu update remaining time dari helper backend', async () => {
            await prepareTimerAttempt(page, fixture);
            shiftLatestE2EAttemptStart('timer_lifecycle', {
                remaining_seconds: 540,
                extra_time_minutes: 5,
            }, 'primary_student');
            await page.reload();
        });

        const storageSnapshot = await captureBrowserStorage(page);
        await page.close();
        const reopened = await openRehydratedPage(browser, baseURL, storageSnapshot);
        try {
            await test.step('Context baru menampilkan timer yang tersinkron ulang', async () => {
                await expect(reopened.page.locator('[data-cbt-timer]')).toBeVisible({ timeout: 20000 });
                await expect(reopened.page.locator('[data-cbt-timer]')).toContainText(':', { timeout: 20000 });
            });
        } finally {
            await reopened.context.close();
        }
    });

    test('Timer Flow: heartbeat keeps exam stage stable', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('timer_lifecycle', 'primary_student');
        await test.step('Buka attempt aktif dan biarkan heartbeat jalan setidaknya satu siklus', async () => {
            await prepareTimerAttempt(page, fixture);
            await page.waitForTimeout(22000);
        });

        await test.step('Stage exam tetap stabil setelah heartbeat', async () => {
            await expect(page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
            await expect(page.locator('[data-cbt-timer]')).toBeVisible({ timeout: 20000 });
        });
    });

    test('Timer Flow: natural timeout leaves no timer zombie on reopen', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('timer_lifecycle', 'primary_student');
        await test.step('Geser timer ke ambang timeout lalu biarkan result muncul', async () => {
            await prepareTimerAttempt(page, fixture);
            shiftLatestE2EAttemptStart('timer_lifecycle', { remaining_seconds: 6 }, 'primary_student');
            await page.reload();
            await waitForResultShell(page);
        });

        const storageSnapshot = await captureBrowserStorage(page);
        await page.close();
        const reopened = await openRehydratedPage(browser, baseURL, storageSnapshot);
        try {
            await test.step('Reopen setelah timeout tidak lagi menampilkan timer exam aktif', async () => {
                await waitForCondition(async () => {
                    const text = await reopened.page.locator('body').textContent().catch(() => '');
                    return String(text || '').includes('Hasil Ujian')
                        || String(text || '').includes('Konfirmasi Ujian')
                        || String(text || '').includes('REVIEW JAWABAN')
                        || String(text || '').includes('Kembali ke Daftar Exam');
                }, {
                    timeoutMs: 120000,
                    intervalMs: 400,
                    errorMessage: 'Result shell tidak muncul setelah reopen timeout.',
                });

                const viewResultButton = reopened.page.locator('[data-action="view-result"]').first();
                if (await viewResultButton.isVisible().catch(() => false)) {
                    await openResultFromConfirm(reopened.page, fixture.exam_title || fixture.exam?.title || '');
                }

                await expect(reopened.page.locator('[data-cbt-exam-shell="1"]').first()).toBeHidden({ timeout: 10000 });
                await expect(reopened.page.locator('[data-cbt-timer]').first()).toBeHidden({ timeout: 10000 });
            });
        } finally {
            await reopened.context.close().catch(() => null);
        }
    });

    test('Timer Flow: logout is safe during active and loading exam lifecycle', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('timer_lifecycle', 'primary_student');

        await test.step('Logout dari shell exam aktif mengembalikan app ke login tanpa state tertinggal', async () => {
            await prepareTimerAttempt(page, fixture);
            await logoutFromFrontend(page);
            await expect(page.locator('#cbt-login-form')).toBeVisible({ timeout: 20000 });
        });

        await test.step('Login ulang dan buka exam lagi untuk memastikan lifecycle tetap bersih', async () => {
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);
            await expect(page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
        });
    });
});
