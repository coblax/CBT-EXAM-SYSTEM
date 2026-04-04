const { test, expect } = require('@playwright/test');
const {
    getE2ECatalog,
    getE2EFixture,
    resetE2EFixture,
} = require('./helpers/e2e-fixture');
const {
    answerCurrentSingleChoice,
    captureBrowserStorage,
    fillCurrentEssay,
    jumpToLastQuestion,
    loginAsStudent,
    openRehydratedPage,
    openResultFromConfirm,
    startOrResumeAttempt,
    waitForResultShell,
} = require('./helpers/frontend-browser');
const {
    loginToWpAdmin,
    openResultsPage,
    openResultsEssayTab,
} = require('./helpers/admin-browser');

test.describe.configure({ mode: 'serial' });

async function finishCurrentExam(page) {
    const finishButton = page.locator('[data-action="collect"], [data-action="finish"]').first();
    if (!(await finishButton.isVisible().catch(() => false))) {
        await jumpToLastQuestion(page);
    }
    await finishButton.click({ force: true });
    await page.locator('[data-action="finish-confirm-submit"]').first().click({ force: true });
    await waitForResultShell(page);
}

test.describe('Result & Scoring flow check', () => {
    test.setTimeout(150000);

    test.beforeEach(() => {
        resetE2EFixture('result_full', 'primary_student');
        resetE2EFixture('result_essay', 'primary_student');
        resetE2EFixture('result_restricted', 'primary_student');
        resetE2EFixture('security_log_observability', 'primary_student');
    });

    test('Result Flow: objective exam shows score percentage and pass label', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('result_full', 'primary_student');

        await test.step('Siswa menyelesaikan fixture objektif full result', async () => {
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);
            await answerCurrentSingleChoice(page, 0);
            await finishCurrentExam(page);
        });

        await test.step('Result shell menampilkan score, percentage, dan status pass/fail', async () => {
            await expect(page.locator('.cbt-result-wrap')).toContainText(/LULUS|TIDAK LULUS/i, { timeout: 20000 });
            await expect(page.locator('.cbt-score-value')).toBeVisible({ timeout: 20000 });
        });
    });

    test('Result Flow: essay pending shows temporary result state', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('result_essay', 'primary_student');

        await test.step('Siswa mengerjakan fixture essay dan langsung finish', async () => {
            resetE2EFixture('result_essay', 'primary_student');
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);
            await fillCurrentEssay(page, 'Jawaban essay untuk memicu pending manual scoring.');
            await finishCurrentExam(page);
        });

        await test.step('Result stage menandai hasil sebagai sementara', async () => {
            await expect(page.locator('.cbt-result-wrap')).toContainText(/Menunggu Koreksi/i, { timeout: 20000 });
            await expect(page.locator('.cbt-result-wrap')).toContainText(/masih sementara/i, { timeout: 20000 });
        });
    });

    test('Result Flow: restricted exam hides score and review', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('result_restricted', 'primary_student');

        await test.step('Siswa menyelesaikan fixture restricted result', async () => {
            resetE2EFixture('result_restricted', 'primary_student');
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);
            await answerCurrentSingleChoice(page, 0);
            await finishCurrentExam(page);
        });

        await test.step('Result wrap memakai mode restricted tanpa membocorkan score', async () => {
            await expect(page.locator('.cbt-result-card--restricted')).toBeVisible({ timeout: 20000 });
            await expect(page.locator('.cbt-result-wrap')).toContainText(/HASIL BELUM DITAMPILKAN|MENUNGGU KOREKSI/i, { timeout: 20000 });
            await expect(page.locator('.cbt-score-value')).toHaveCount(0);
        });
    });

    test('Result Flow: admin regrade updates essay result consistently', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('result_essay', 'primary_student');
        const catalog = getE2ECatalog();

        await test.step('Siswa menyelesaikan essay fixture dan membuka hasil sementara', async () => {
            resetE2EFixture('result_essay', 'primary_student');
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);
            await fillCurrentEssay(page, 'Jawaban essay untuk diuji regrade dari admin.');
            await finishCurrentExam(page);
            await expect(page.locator('.cbt-result-wrap')).toContainText(/Menunggu Koreksi/i, { timeout: 20000 });
        });

        const adminContext = await browser.newContext(baseURL ? { baseURL } : undefined);
        const adminPage = await adminContext.newPage();
        try {
            await test.step('Admin memberi score essay dari panel hasil', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await openResultsEssayTab(adminPage, fixture.exam.exam_id);
                const row = adminPage.locator('#cbt-results-tab-panel-essay tr').filter({ hasText: String(fixture.user.display_name || fixture.user.username) }).first();
                await expect(row).toBeVisible({ timeout: 20000 });
                const scoreInput = row.locator('input[name="score_awarded"]').first();
                await scoreInput.fill('5');
                await row.locator('button[type="submit"]').first().click({ force: true });
                await adminPage.waitForLoadState('networkidle');
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Siswa reopen hasil dan melihat state essay yang sudah diregrade', async () => {
            await page.goto('/');
            await openResultFromConfirm(page, fixture.exam.title);
            await expect(page.locator('.cbt-result-wrap')).not.toContainText(/Menunggu Koreksi/i, { timeout: 20000 });
            await expect(page.locator('.cbt-score-value')).toBeVisible({ timeout: 20000 });
        });
    });

    test('Result Flow: high-point essay pending does not imply final pass state', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('result_essay', 'primary_student');

        await test.step('Essay fixture selesai tetapi masih pending manual scoring', async () => {
            resetE2EFixture('result_essay', 'primary_student');
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);
            await fillCurrentEssay(page, 'Jawaban essay dengan poin tinggi tetapi belum dinilai.');
            await finishCurrentExam(page);
        });

        await test.step('Result tetap menekankan sifat sementara, bukan final score yang menyesatkan', async () => {
            await expect(page.locator('.cbt-result-pending-card')).toBeVisible({ timeout: 20000 });
            await expect(page.locator('.cbt-result-pending-card')).toContainText(/sementara/i, { timeout: 20000 });
        });
    });

    test('Result Flow: in-progress row shows live monitoring in student column', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('security_log_observability', 'primary_student');
        const catalog = getE2ECatalog();

        await test.step('Siswa membuka ujian hingga session heartbeat menulis presence snapshot', async () => {
            resetE2EFixture('security_log_observability', 'primary_student');
            await loginAsStudent(page, fixture.user);
            const sessionResponsePromise = page.waitForResponse((response) => {
                return response.url().includes('/wp-json/cbt/v1/session');
            }, {
                timeout: 25000,
            }).catch(() => null);
            await startOrResumeAttempt(page, fixture);

            const sessionResponse = await sessionResponsePromise;

            expect(sessionResponse).not.toBeNull();
            if (sessionResponse) {
                expect(sessionResponse.ok()).toBeTruthy();
            }
        });

        const adminContext = await browser.newContext(baseURL ? { baseURL } : undefined);
        const adminPage = await adminContext.newPage();
        try {
            await test.step('Admin melihat monitoring live compact di kolom Student pada Results', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await openResultsPage(adminPage, fixture.exam.exam_id);

                const row = adminPage.locator('.cbt-results-attempt-row').filter({ hasText: String(fixture.user.display_name || fixture.user.username) }).first();
                await expect(row).toBeVisible({ timeout: 20000 });

                const studentCell = row.locator('.cbt-results-student-cell').first();
                await expect(studentCell).toContainText(/Online|Stale|Offline/, { timeout: 20000 });
                await expect(studentCell).toContainText('Seen:', { timeout: 20000 });
                await expect(studentCell).not.toContainText(/Clipboard diblokir|Pindah tab \/ aplikasi|High Risk/i);
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Result Flow: full and restricted result stay consistent after refresh and reopen', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fullFixture = getE2EFixture('result_full', 'primary_student');

        await test.step('Selesaikan fixture full result lalu capture storage hasil', async () => {
            await loginAsStudent(page, fullFixture.user);
            await startOrResumeAttempt(page, fullFixture);
            await answerCurrentSingleChoice(page, 0);
            await finishCurrentExam(page);
            await expect(page.locator('.cbt-score-value')).toBeVisible({ timeout: 20000 });
        });

        const storageSnapshot = await captureBrowserStorage(page);
        const reopened = await openRehydratedPage(browser, baseURL, storageSnapshot);
        try {
            await test.step('Reopen context baru tetap bisa membuka hasil full yang sama', async () => {
                await openResultFromConfirm(reopened.page, fullFixture.exam.title);
                await expect(reopened.page.locator('.cbt-score-value')).toBeVisible({ timeout: 20000 });
            });
        } finally {
            await reopened.context.close();
        }

        await test.step('Fixture restricted tetap restricted setelah refresh dan reopen', async () => {
            const restrictedFixture = getE2EFixture('result_restricted', 'primary_student');
            resetE2EFixture('result_restricted', 'primary_student');
            await page.goto('/');
            await loginAsStudent(page, restrictedFixture.user);
            await startOrResumeAttempt(page, restrictedFixture);
            await answerCurrentSingleChoice(page, 0);
            await finishCurrentExam(page);
            await page.goto('/');
            await openResultFromConfirm(page, restrictedFixture.exam.title);
            await expect(page.locator('.cbt-result-card--restricted')).toBeVisible({ timeout: 20000 });
        });
    });
});
