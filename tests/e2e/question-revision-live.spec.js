const { test, expect } = require('@playwright/test');
const {
    getE2ECatalog,
    getE2EExamQuestions,
    getE2EFixture,
    getLatestE2EAttempt,
    resetE2EFixture,
} = require('./helpers/e2e-fixture');
const {
    answerCurrentSingleChoice,
    getCurrentQuestionId,
    loginAsStudent,
    startOrResumeAttempt,
} = require('./helpers/frontend-browser');
const {
    loginToWpAdmin,
    openQuestionEditPage,
    setWpEditorContent,
    submitManualQuestionExpectSuccess,
} = require('./helpers/admin-browser');
const { waitForCondition } = require('./helpers/flow-utils');

test.describe.configure({ mode: 'serial' });

test.describe('Question revision live flow', () => {
    test.setTimeout(180000);

    test('Heartbeat patches the active question after admin updates the bank source question', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixtureKey = 'question_runtime';
        const userKey = 'primary_student';
        const fixture = getE2EFixture(fixtureKey, userKey);
        const catalog = getE2ECatalog();
        const revisionMarker = `FLOW LIVE REVISION ${Date.now()}`;
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        let sourceQuestionId = 0;
        let activeQuestionId = 0;
        let originalQuestionText = '';
        let revisionNoticePromise = null;

        try {
            resetE2EFixture(fixtureKey, userKey);

            await test.step('Siswa membuka attempt aktif dan menjawab soal pertama', async () => {
                await loginAsStudent(page, fixture.user);
                await startOrResumeAttempt(page, fixture);

                const attempt = getLatestE2EAttempt(fixtureKey, userKey);
                activeQuestionId = await getCurrentQuestionId(page);
                expect(Number(attempt && attempt.question_order && attempt.question_order[0]) || 0).toBe(activeQuestionId);

                const examQuestions = getE2EExamQuestions(fixtureKey, userKey);
                const activeQuestion = examQuestions.find((question) => Number(question && question.id) === activeQuestionId) || null;
                expect(activeQuestion).toBeTruthy();

                sourceQuestionId = Number(activeQuestion && activeQuestion.source_question_id) || 0;
                originalQuestionText = String(activeQuestion && activeQuestion.question_text || '');
                expect(sourceQuestionId).toBeGreaterThan(0);
                expect(originalQuestionText).not.toContain(revisionMarker);

                const selectedOptionId = await answerCurrentSingleChoice(page, 1);
                expect(selectedOptionId).toBeGreaterThan(0);
                await expect(page.locator(`[data-action="answer-single"][data-option-id="${selectedOptionId}"]`)).toBeChecked({ timeout: 20000 });
            });

            await test.step('Admin mengubah bank source question untuk row aktif', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await openQuestionEditPage(adminPage, sourceQuestionId);
                await setWpEditorContent(
                    adminPage,
                    'cbt_question_text_editor',
                    `${originalQuestionText}<p>${revisionMarker}</p>`
                );
                await submitManualQuestionExpectSuccess(adminPage);
                await page.bringToFront();
                revisionNoticePromise = page.waitForFunction(() => {
                    const notice = document.querySelector('.cbt-exam-revision-notice');
                    return !!(notice && String(notice.textContent || '').includes('1 soal berubah.'));
                }, null, { timeout: 35000 });

                await waitForCondition(() => {
                    const questions = getE2EExamQuestions(fixtureKey, userKey);
                    const activeQuestion = questions.find((question) => Number(question && question.id) === activeQuestionId) || null;
                    return activeQuestion && String(activeQuestion.question_text || '').includes(revisionMarker)
                        ? activeQuestion
                        : null;
                }, {
                    timeoutMs: 20000,
                    intervalMs: 500,
                    errorMessage: 'Row exam turunan belum ikut berubah setelah source bank question disimpan.',
                });
            });

            await test.step('Heartbeat siswa mendeteksi revisi dan mem-patch UI tanpa kehilangan jawaban', async () => {
                await page.bringToFront();
                await (revisionNoticePromise || page.waitForFunction(() => {
                    const notice = document.querySelector('.cbt-exam-revision-notice');
                    return !!(notice && String(notice.textContent || '').includes('1 soal berubah.'));
                }, null, { timeout: 35000 }));
                await expect(page.locator('.cbt-exam-revision-notice')).toContainText('1 soal berubah.');
                await expect(page.locator('.cbt-question-stem')).toContainText(revisionMarker, { timeout: 35000 });
                await expect(page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 10000 });
                await expect(page.locator('#cbt-login-form')).toHaveCount(0);

                const checkedOption = page.locator('[data-action="answer-single"]:checked').first();
                await expect(checkedOption).toBeVisible({ timeout: 10000 });
            });
        } finally {
            try {
                if (sourceQuestionId > 0 && originalQuestionText !== '') {
                    await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                    await openQuestionEditPage(adminPage, sourceQuestionId);
                    await setWpEditorContent(adminPage, 'cbt_question_text_editor', originalQuestionText);
                    await submitManualQuestionExpectSuccess(adminPage);

                    await waitForCondition(() => {
                        const questions = getE2EExamQuestions(fixtureKey, userKey);
                        const activeQuestion = questions.find((question) => Number(question && question.id) === activeQuestionId) || null;
                        return activeQuestion && !String(activeQuestion.question_text || '').includes(revisionMarker)
                            ? activeQuestion
                            : null;
                    }, {
                        timeoutMs: 20000,
                        intervalMs: 500,
                        errorMessage: 'Cleanup source bank question belum selesai menghapus marker revisi live.',
                    });
                }
            } finally {
                resetE2EFixture(fixtureKey, userKey);
                await adminContext.close();
            }
        }
    });
});
