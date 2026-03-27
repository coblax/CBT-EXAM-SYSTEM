const { test, expect } = require('@playwright/test');
const {
    assertExamShellRestored,
    captureBrowserStorage,
    clearLocalQuestionCache,
    corruptLocalAttemptSnapshot,
    jumpToQuestion,
    openRehydratedRecoveryPage,
    prepareRecoveryAttempt,
} = require('./helpers/recovery-browser');
const {
    getLatestRecoveryAttempt,
    getRecoveryFixture,
    invalidateRecoveryAdminSideCache,
    invalidateRecoveryNonAttemptCache,
    saveRecoveryRemoteState,
} = require('./helpers/recovery-fixture');

test.describe.configure({ mode: 'serial' });

test.describe('Recovery & Persistence flow check', () => {
    test.setTimeout(120000);

    test('Recovery Flow: refresh restores current question, answer, and doubtful state', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getRecoveryFixture();
        const prepared = await test.step('Login, mulai attempt, dan siapkan progress recovery', async () => {
            return prepareRecoveryAttempt(page, fixture);
        });

        await test.step('Refresh browser untuk memicu jalur restore', async () => {
            await page.reload();
        });

        await test.step('Verifikasi soal aktif, jawaban, dan doubtful state pulih', async () => {
            await assertExamShellRestored(
                page,
                prepared.currentQuestionNumber,
                prepared.secondAnswerOptionId,
                true
            );
        });
    });

    test('Recovery Flow: close and reopen resumes the same attempt state', async ({ page, browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getRecoveryFixture();
        const prepared = await test.step('Login dan siapkan progress recovery yang akan diresume', async () => {
            return prepareRecoveryAttempt(page, fixture);
        });
        const storageSnapshot = await test.step('Tangkap storage browser aktif sebelum tab ditutup', async () => {
            return captureBrowserStorage(page);
        });

        await test.step('Tutup tab aktif lalu buka ulang context baru dengan storage yang sama', async () => {
            await page.close();
        });
        const reopened = await openRehydratedRecoveryPage(browser, baseURL, storageSnapshot);

        try {
            await test.step('Verifikasi attempt yang sama langsung pulih setelah reopen', async () => {
                await assertExamShellRestored(
                    reopened.page,
                    prepared.currentQuestionNumber,
                    prepared.secondAnswerOptionId,
                    true
                );
            });
        } finally {
            await reopened.context.close();
        }
    });

    test('Recovery Flow: non-attempt cache cleanup keeps active attempt state safe', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getRecoveryFixture();
        const prepared = await test.step('Siapkan attempt aktif sebelum cache non-attempt dibersihkan', async () => {
            return prepareRecoveryAttempt(page, fixture);
        });

        await test.step('Invalidate cache non-attempt dari helper backend', async () => {
            invalidateRecoveryNonAttemptCache();
        });
        await test.step('Reload browser setelah cache non-attempt dibersihkan', async () => {
            await page.reload();
        });

        await test.step('Pastikan current question, jawaban, dan doubtful state tetap aman', async () => {
            await assertExamShellRestored(
                page,
                prepared.currentQuestionNumber,
                prepared.secondAnswerOptionId,
                true
            );
        });
    });

    test('Recovery Flow: failed finish request keeps progress safe after reopen', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getRecoveryFixture();
        const prepared = await test.step('Siapkan attempt aktif sebelum finish digagalkan', async () => {
            return prepareRecoveryAttempt(page, fixture);
        });

        let finishIntercepted = false;
        await test.step('Pasang interceptor untuk menggagalkan finish request pertama', async () => {
            await page.route('**/wp-json/cbt/v1/finish_exam', async (route) => {
                if (finishIntercepted) {
                    await route.continue();
                    return;
                }

                finishIntercepted = true;
                await route.fulfill({
                    status: 503,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        code: 'recovery_finish_blocked',
                        message: 'Finish sengaja digagalkan untuk flow check recovery.',
                    }),
                });
            });
        });

        await test.step('Jalankan finish dan pastikan tetap tertahan di exam shell', async () => {
            const totalQuestions = await page.locator('[data-action="jump"]').count();
            await jumpToQuestion(page, totalQuestions > 0 ? totalQuestions : 1);
            await page.locator('[data-action="collect"], [data-action="finish"]').first().click({ force: true });
            await page.locator('[data-action="finish-confirm-submit"]').first().click({ force: true });
            await expect(page.getByText('Finish sengaja digagalkan untuk flow check recovery.')).toBeVisible({ timeout: 20000 });
            await expect(page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
            await expect(page.locator('.cbt-result-wrap')).toHaveCount(0);
            await expect(page.locator('[data-action="finish"]').first()).toBeEnabled({ timeout: 20000 });
        });

        await test.step('Lepas interceptor lalu reload untuk memicu restore ulang', async () => {
            await page.unroute('**/wp-json/cbt/v1/finish_exam');
            await page.reload();
        });

        await test.step('Pastikan progress tetap aman setelah reopen', async () => {
            await expect(page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
            await expect(page.locator('.cbt-result-wrap')).toHaveCount(0);
            await jumpToQuestion(page, prepared.currentQuestionNumber);
            await expect(page.locator(`[data-action="answer-single"][data-option-id="${prepared.secondAnswerOptionId}"]`)).toBeChecked({ timeout: 20000 });
            await expect(page.locator('[data-action="toggle-doubtful"]').first()).toHaveClass(/is-active/, { timeout: 20000 });
        });
    });

    test('Recovery Flow: corrupt local snapshot falls back without blank state', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getRecoveryFixture();
        const prepared = await test.step('Siapkan attempt aktif sebelum local snapshot dirusak', async () => {
            return prepareRecoveryAttempt(page, fixture);
        });
        const latestAttempt = await test.step('Ambil latest attempt dari helper backend', async () => {
            return getLatestRecoveryAttempt();
        });

        expect(latestAttempt && latestAttempt.id).toBeTruthy();
        await test.step('Simpan remote state valid sebagai sumber restore yang aman', async () => {
            saveRecoveryRemoteState({
                attempt_id: Number(latestAttempt.id),
                current_index: prepared.currentQuestionNumber - 1,
                doubtful_question_ids: [prepared.currentQuestionId],
            });
        });

        await test.step('Corrupt snapshot lokal lalu reload browser', async () => {
            await corruptLocalAttemptSnapshot(page, fixture, Number(latestAttempt.id), '{invalid-json');
            await page.reload();
        });

        await test.step('Pastikan app fallback ke restore aman tanpa blank state', async () => {
            await assertExamShellRestored(
                page,
                prepared.currentQuestionNumber,
                prepared.secondAnswerOptionId,
                true
            );
        });
    });

    test('Recovery Flow: admin-side cache invalidation outside attempt preserves active state', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getRecoveryFixture();
        const prepared = await test.step('Siapkan attempt aktif sebelum invalidasi cache dari sisi admin', async () => {
            return prepareRecoveryAttempt(page, fixture);
        });

        await test.step('Invalidate cache admin-side di luar namespace attempt', async () => {
            invalidateRecoveryAdminSideCache();
        });
        await test.step('Reload browser setelah invalidasi admin-side cache', async () => {
            await page.reload();
        });

        await test.step('Pastikan active attempt tetap pulih setelah invalidasi admin-side cache', async () => {
            await assertExamShellRestored(
                page,
                prepared.currentQuestionNumber,
                prepared.secondAnswerOptionId,
                true
            );
        });
    });

    test('Recovery Flow: remote snapshot wins when local snapshot becomes stale conflict', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getRecoveryFixture();
        const prepared = await test.step('Siapkan attempt aktif sebelum conflict local-vs-remote dibuat', async () => {
            return prepareRecoveryAttempt(page, fixture);
        });
        const latestAttempt = await test.step('Ambil latest attempt dari helper backend', async () => {
            return getLatestRecoveryAttempt();
        });

        expect(latestAttempt && latestAttempt.id).toBeTruthy();

        await test.step('Buat snapshot lokal stale dan kosongkan local question cache', async () => {
            await corruptLocalAttemptSnapshot(page, fixture, Number(latestAttempt.id), {
                attempt_id: Number(latestAttempt.id),
                current_index: 0,
                doubtful_question_ids: [],
                updated_at: Date.now() - 60000,
            });
            await clearLocalQuestionCache(page, fixture, Number(latestAttempt.id));
        });

        await test.step('Simpan remote state yang lebih aman dan lebih baru', async () => {
            saveRecoveryRemoteState({
                attempt_id: Number(latestAttempt.id),
                current_index: prepared.currentQuestionNumber - 1,
                doubtful_question_ids: [prepared.currentQuestionId],
            });
        });

        await test.step('Reload browser agar resolver memilih snapshot yang paling aman', async () => {
            await page.reload();
        });

        await test.step('Pastikan remote snapshot menang dan state recovery tetap pulih', async () => {
            await assertExamShellRestored(
                page,
                prepared.currentQuestionNumber,
                prepared.secondAnswerOptionId,
                true
            );
        });
    });
});
