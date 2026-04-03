const { test, expect } = require('@playwright/test');
const {
    getE2EAttemptAnswers,
    getE2EFixture,
    getLatestE2EAttempt,
    resetE2EFixture,
} = require('./helpers/e2e-fixture');
const {
    answerCurrentSingleChoice,
    captureBrowserStorage,
    clickNextQuestion,
    fetchWithAuth,
    getCurrentQuestionNumber,
    jumpToLastQuestion,
    loginAsStudent,
    openRehydratedPage,
    startOrResumeAttempt,
    waitForAnswerSync,
    waitForResultShell,
} = require('./helpers/frontend-browser');
const { waitForCondition } = require('./helpers/flow-utils');

test.describe.configure({ mode: 'serial' });

async function prepareSyncAttempt(page, fixture) {
    await loginAsStudent(page, fixture.user);
    await startOrResumeAttempt(page, fixture);
    const attempt = getLatestE2EAttempt('sync_rest', 'primary_student');
    expect(attempt && attempt.id).toBeTruthy();
    return attempt;
}

async function waitForAttemptAnswerCount(attemptId, expectedMinimum) {
    return waitForCondition(() => {
        const answers = getE2EAttemptAnswers('sync_rest', attemptId, 'primary_student');
        if (Array.isArray(answers) && answers.length >= expectedMinimum) {
            return answers;
        }
        return null;
    }, {
        timeoutMs: 25000,
        intervalMs: 400,
        errorMessage: `Timed out waiting for ${expectedMinimum} synced answers.`,
    });
}

test.describe('Sync & REST flow check', () => {
    test.setTimeout(150000);

    test.beforeEach(() => {
        resetE2EFixture('sync_rest', 'primary_student');
        resetE2EFixture('sync_rest', 'secondary_student');
    });

    test('Sync Flow: start load submit finish result end to end', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('sync_rest', 'primary_student');
        const attempt = await test.step('Login, mulai attempt, dan kirim beberapa jawaban', async () => {
            const currentAttempt = await prepareSyncAttempt(page, fixture);
            await answerCurrentSingleChoice(page, 0);
            await clickNextQuestion(page);
            await answerCurrentSingleChoice(page, 1);
            return currentAttempt;
        });

        await test.step('Sinkronisasi jawaban tercatat di backend sebelum finish', async () => {
            const answers = await waitForAttemptAnswerCount(Number(attempt.id), 2);
            expect(answers.length).toBeGreaterThanOrEqual(2);
        });

        await test.step('Finish exam lalu buka result shell', async () => {
            await jumpToLastQuestion(page);
            await page.locator('[data-action="collect"], [data-action="finish"]').first().click({ force: true });
            await page.locator('[data-action="finish-confirm-submit"]').first().click({ force: true });
            await waitForResultShell(page);
        });
    });

    test('Sync Flow: offline answers retry automatically when back online', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('sync_rest', 'primary_student');
        const attempt = await test.step('Login dan mulai attempt sync', async () => {
            return prepareSyncAttempt(page, fixture);
        });

        await test.step('Matikan koneksi, jawab satu soal, lalu verifikasi footer sync menjadi pending offline', async () => {
            await page.context().setOffline(true);
            await answerCurrentSingleChoice(page, 0);
            await expect(page.locator('.cbt-question-exam-footer-meta-sync')).toContainText(/pending|offline/i, { timeout: 20000 });
        });

        await test.step('Aktifkan koneksi kembali dan tunggu retry otomatis flush ke backend', async () => {
            await page.context().setOffline(false);
            await waitForAttemptAnswerCount(Number(attempt.id), 1);
        });
    });

    test('Sync Flow: finish remains locked informatively while pending sync exists', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('sync_rest', 'primary_student');
        await test.step('Mulai attempt lalu buat pending sync dengan mematikan koneksi setelah jawaban diubah', async () => {
            await prepareSyncAttempt(page, fixture);
            await jumpToLastQuestion(page);
            await page.context().setOffline(true);
            await answerCurrentSingleChoice(page, 0);
        });

        await test.step('Finish tidak memindahkan UI ke result dan footer sync tetap menunjukkan kondisi tertahan', async () => {
            await page.locator('[data-action="collect"], [data-action="finish"]').first().click({ force: true });
            await page.locator('[data-action="finish-confirm-submit"]').first().click({ force: true });
            await expect(page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
            await expect(page.locator('.cbt-result-wrap')).toHaveCount(0);
            await expect(page.locator('.cbt-question-exam-footer-meta-sync')).toContainText(/offline|tertahan|pending/i, { timeout: 20000 });
            await page.context().setOffline(false);
        });
    });

    test('Sync Flow: cross-user attempt request is forbidden', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const primaryFixture = getE2EFixture('sync_rest', 'primary_student');
        const secondaryFixture = getE2EFixture('sync_rest', 'secondary_student');
        const attempt = await test.step('Siswa utama menyiapkan attempt aktif untuk fixture sync', async () => {
            return prepareSyncAttempt(page, primaryFixture);
        });

        const secondContext = await browser.newContext();
        const secondPage = await secondContext.newPage();
        try {
            await test.step('Siswa kedua login dan request questions ke attempt siswa utama ditolak', async () => {
                await loginAsStudent(secondPage, secondaryFixture.user);
                const response = await fetchWithAuth(
                    secondPage,
                    `/wp-json/cbt/v1/questions?exam_id=${Number(primaryFixture.exam.exam_id)}&attempt_id=${Number(attempt.id)}`
                );
                expect(response.status).toBe(403);
                expect(String(response.data && response.data.code ? response.data.code : '')).toBe('forbidden');
            });
        } finally {
            await secondContext.close();
        }
    });

    test('Sync Flow: pending sync flushes after reopen', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('sync_rest', 'primary_student');
        const attempt = await test.step('Buat pending sync lalu tangkap storage browser', async () => {
            const currentAttempt = await prepareSyncAttempt(page, fixture);
            await page.context().setOffline(true);
            await answerCurrentSingleChoice(page, 0);
            return currentAttempt;
        });

        const storageSnapshot = await captureBrowserStorage(page);
        await page.context().setOffline(false);

        const reopened = await openRehydratedPage(browser, baseURL, storageSnapshot);
        try {
            await test.step('Reopen context baru lalu tunggu flush otomatis menyelesaikan pending sync', async () => {
                await expect(reopened.page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
                await waitForAttemptAnswerCount(Number(attempt.id), 1);
            });
        } finally {
            await reopened.context.close();
        }
    });

    test('Sync Flow: batch fallback and normal submit produce equivalent answer rows', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('sync_rest', 'primary_student');

        await test.step('Run pertama memakai path normal untuk dua jawaban', async () => {
            const normalAttempt = await prepareSyncAttempt(page, fixture);
            await answerCurrentSingleChoice(page, 0);
            await clickNextQuestion(page);
            await answerCurrentSingleChoice(page, 0);
            const normalAnswers = await waitForAttemptAnswerCount(Number(normalAttempt.id), 2);
            expect(normalAnswers.length).toBe(2);
        });

        await test.step('Reset fixture lalu paksa batch submit gagal agar fallback legacy aktif', async () => {
            resetE2EFixture('sync_rest', 'primary_student');
            await page.reload();
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);

            await page.route('**/wp-json/cbt/v1/submit_answers_batch', async (route) => {
                await route.fulfill({
                    status: 503,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        code: 'runtime_buffer_unavailable',
                        message: 'Force fallback ke submit legacy.',
                    }),
                });
            });

            const fallbackAttempt = getLatestE2EAttempt('sync_rest', 'primary_student');
            expect(fallbackAttempt && fallbackAttempt.id).toBeTruthy();

            await answerCurrentSingleChoice(page, 0);
            await clickNextQuestion(page);
            await answerCurrentSingleChoice(page, 0);
            await waitForCondition(() => {
                const answers = getE2EAttemptAnswers('sync_rest', Number(fallbackAttempt.id), 'primary_student');
                if (Array.isArray(answers) && answers.length === 2) {
                    return answers;
                }
                return null;
            }, {
                timeoutMs: 25000,
                intervalMs: 400,
                errorMessage: 'Fallback legacy submit tidak menghasilkan dua answer row.',
            });
            await page.unroute('**/wp-json/cbt/v1/submit_answers_batch');
        });
    });

    test('Sync Flow: finish with pending sync resolves to correct result after retry', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('sync_rest', 'primary_student');
        const attempt = await test.step('Siapkan pending sync pada attempt aktif', async () => {
            const currentAttempt = await prepareSyncAttempt(page, fixture);
            await jumpToLastQuestion(page);
            await page.context().setOffline(true);
            await answerCurrentSingleChoice(page, 0);
            return currentAttempt;
        });

        await test.step('Finish tertahan saat offline lalu berhasil setelah koneksi kembali', async () => {
            await page.locator('[data-action="collect"], [data-action="finish"]').first().click({ force: true });
            await page.locator('[data-action="finish-confirm-submit"]').first().click({ force: true });
            await expect(page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
            await page.context().setOffline(false);
            await waitForAttemptAnswerCount(Number(attempt.id), 1);
            const postRetryStage = await waitForCondition(async () => {
                const resultShellVisible = await page.locator('.cbt-result-wrap').first().isVisible().catch(() => false);
                if (resultShellVisible) {
                    return 'result';
                }

                const currentQuestion = await getCurrentQuestionNumber(page).catch(() => 0);
                if (currentQuestion > 0) {
                    return 'exam';
                }

                return null;
            }, {
                timeoutMs: 60000,
                intervalMs: 400,
                errorMessage: 'Stage exam/result tidak kembali stabil setelah retry online.',
            });

            if (postRetryStage === 'exam') {
                await page.locator('[data-action="collect"], [data-action="finish"]').first().click({ force: true });
                const postCollectStage = await waitForCondition(async () => {
                    const resultShellVisible = await page.locator('.cbt-result-wrap').first().isVisible().catch(() => false);
                    if (resultShellVisible) {
                        return 'result';
                    }

                    const finishConfirmVisible = await page.locator('[data-action="finish-confirm-submit"]').first().isVisible().catch(() => false);
                    if (finishConfirmVisible) {
                        return 'confirm';
                    }

                    const examShellVisible = await page.locator('[data-cbt-exam-shell="1"]').first().isVisible().catch(() => false);
                    if (examShellVisible) {
                        return 'exam';
                    }

                    return null;
                }, {
                    timeoutMs: 20000,
                    intervalMs: 250,
                    errorMessage: 'Exam tidak kembali ke modal confirm atau result setelah retry finish.',
                });

                if (postCollectStage === 'confirm') {
                    await page.locator('[data-action="finish-confirm-submit"]').first().click({ force: true });
                }
            }

            await waitForCondition(async () => {
                return page.locator('.cbt-result-wrap').first().isVisible().catch(() => false);
            }, {
                timeoutMs: 60000,
                intervalMs: 400,
                errorMessage: 'Result shell tidak muncul stabil setelah retry finish selesai.',
            });
            await waitForResultShell(page);
        });
    });
});
