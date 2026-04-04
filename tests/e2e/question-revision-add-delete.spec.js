const { test, expect } = require('@playwright/test');
const {
    getE2ECatalog,
    getE2EExamQuestions,
    getE2EFixture,
    resetE2EFixture,
    syncE2ESubjectBankQuestionsToFixture,
} = require('./helpers/e2e-fixture');
const {
    answerCurrentSingleChoice,
    getCurrentQuestionId,
    getCheckedSingleChoiceOptionId,
    getQuestionNavCount,
    jumpToLastQuestion,
    loginAsStudent,
    startOrResumeAttempt,
} = require('./helpers/frontend-browser');
const {
    deleteQuestionRowByMarker,
    getQuestionIdByMarker,
    loginToWpAdmin,
    prepareManualQuestion,
    setWpEditorContent,
    submitManualQuestionExpectSuccess,
} = require('./helpers/admin-browser');
const { waitForCondition } = require('./helpers/flow-utils');

test.describe.configure({ mode: 'serial' });

async function waitForRevisionNoticeText(page, expectedText, timeoutMs = 35000) {
    await page.waitForFunction((text) => {
        const notice = document.querySelector('.cbt-exam-revision-notice');
        return !!(notice && String(notice.textContent || '').includes(String(text || '')));
    }, expectedText, { timeout: timeoutMs });
}

function syncQuestionRuntimeFixture(fixture, fixtureKey, userKey) {
    const syncResult = syncE2ESubjectBankQuestionsToFixture(fixtureKey, {
        subject_id: Number(fixture.exam.subject_id),
    }, userKey);

    expect(Number(syncResult && syncResult.synced_count || 0)).toBeGreaterThan(0);
    return syncResult;
}

async function createTemporaryMultipleChoiceQuestion(adminPage, subjectId, markerText) {
    await prepareManualQuestion(adminPage, {
        subjectId: Number(subjectId),
        questionType: 'multiple_choice',
        questionHtml: `<p>${markerText}</p>`,
    });
    await setWpEditorContent(adminPage, 'cbt_mc_option_1', `<p>${markerText} OPSI A</p>`);
    await setWpEditorContent(adminPage, 'cbt_mc_option_2', `<p>${markerText} OPSI B</p>`);
    await setWpEditorContent(adminPage, 'cbt_mc_option_3', `<p>${markerText} OPSI C</p>`);
    await adminPage.selectOption('#cbt-correct-mc-index', '1');
    await submitManualQuestionExpectSuccess(adminPage);
    return getQuestionIdByMarker(adminPage, markerText);
}

function isMatchedExamQuestion(question, lookup = {}) {
    const sourceQuestionId = Number(lookup.sourceQuestionId) || 0;
    const markerText = String(lookup.markerText || '').trim();

    if (sourceQuestionId > 0 && Number(question && question.source_question_id) === sourceQuestionId) {
        return true;
    }

    if (markerText !== '' && String(question && question.question_text || '').includes(markerText)) {
        return true;
    }

    return false;
}

async function waitForExamQuestionMatch(fixtureKey, userKey, lookup, expectedCount) {
    return waitForCondition(() => {
        const questions = getE2EExamQuestions(fixtureKey, userKey);
        const matchedQuestion = questions.find((question) => isMatchedExamQuestion(question, lookup)) || null;
        if (!matchedQuestion) {
            return null;
        }

        if (Number(expectedCount) > 0 && questions.length !== Number(expectedCount)) {
            return null;
        }

        return {
            matchedQuestion,
            questions,
        };
    }, {
        timeoutMs: 20000,
        intervalMs: 500,
        errorMessage: 'Row exam turunan baru belum muncul setelah sinkronisasi bank.',
    });
}

async function waitForExamQuestionRemoval(fixtureKey, userKey, lookup, expectedCount) {
    return waitForCondition(() => {
        const questions = getE2EExamQuestions(fixtureKey, userKey);
        const stillExists = questions.some((question) => isMatchedExamQuestion(question, lookup));
        if (stillExists) {
            return null;
        }

        if (Number(expectedCount) > 0 && questions.length !== Number(expectedCount)) {
            return null;
        }

        return questions;
    }, {
        timeoutMs: 20000,
        intervalMs: 500,
        errorMessage: 'Row exam turunan lama belum hilang setelah sinkronisasi delete.',
    });
}

async function cleanupTemporaryQuestion(adminPage, adminUser, fixture, fixtureKey, userKey, markerText, sourceQuestionId) {
    if (Number(sourceQuestionId) <= 0) {
        return;
    }

    await loginToWpAdmin(adminPage, adminUser);

    try {
        await deleteQuestionRowByMarker(adminPage, markerText);
    } catch (error) {
        // Ignore cleanup miss when the row was already deleted during the test flow.
    }

    syncQuestionRuntimeFixture(fixture, fixtureKey, userKey);
    await waitForExamQuestionRemoval(fixtureKey, userKey, {
        markerText,
        sourceQuestionId,
    }, 0);
}

test.describe('Question revision add/delete live flow', () => {
    test.setTimeout(240000);

    test('Heartbeat detects added questions without displacing the current answered question', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixtureKey = 'question_runtime';
        const userKey = 'primary_student';
        const fixture = getE2EFixture(fixtureKey, userKey);
        const catalog = getE2ECatalog();
        const addMarker = `FLOW LIVE ADD ${Date.now()}`;
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        let createdSourceQuestionId = 0;
        let initialQuestionCount = 0;
        let currentQuestionId = 0;
        let selectedOptionId = 0;

        try {
            resetE2EFixture(fixtureKey, userKey);

            await test.step('Siswa membuka attempt aktif dan menjawab soal sekarang', async () => {
                await loginAsStudent(page, fixture.user);
                await startOrResumeAttempt(page, fixture);

                initialQuestionCount = await getQuestionNavCount(page);
                currentQuestionId = await getCurrentQuestionId(page);
                expect(initialQuestionCount).toBeGreaterThan(0);
                expect(currentQuestionId).toBeGreaterThan(0);

                selectedOptionId = await answerCurrentSingleChoice(page, 0);
                expect(selectedOptionId).toBeGreaterThan(0);
                await expect.poll(async () => getCheckedSingleChoiceOptionId(page), { timeout: 20000 }).toBe(selectedOptionId);
            });

            await test.step('Admin menambah source bank question baru lalu sinkron ke fixture', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                createdSourceQuestionId = await createTemporaryMultipleChoiceQuestion(
                    adminPage,
                    Number(fixture.exam.subject_id),
                    addMarker
                );
                expect(createdSourceQuestionId).toBeGreaterThan(0);

                syncQuestionRuntimeFixture(fixture, fixtureKey, userKey);
                await waitForExamQuestionMatch(fixtureKey, userKey, {
                    markerText: addMarker,
                    sourceQuestionId: createdSourceQuestionId,
                }, initialQuestionCount + 1);
            });

            await test.step('Heartbeat siswa mendeteksi soal baru dan current answer tetap aman', async () => {
                await page.bringToFront();
                await waitForRevisionNoticeText(page, 'soal baru ditambahkan', 35000);
                await expect(page.locator('.cbt-exam-revision-notice')).toContainText('soal baru ditambahkan');
                await expect.poll(async () => getQuestionNavCount(page), { timeout: 35000 }).toBe(initialQuestionCount + 1);
                await expect.poll(async () => getCurrentQuestionId(page), { timeout: 10000 }).toBe(currentQuestionId);
                await expect.poll(async () => getCheckedSingleChoiceOptionId(page), { timeout: 35000 }).toBe(selectedOptionId);

                const lastQuestionNumber = await jumpToLastQuestion(page);
                expect(lastQuestionNumber).toBe(initialQuestionCount + 1);
                await expect(page.locator('.cbt-question-stem')).toContainText(addMarker, { timeout: 10000 });
            });
        } finally {
            try {
                await cleanupTemporaryQuestion(
                    adminPage,
                    catalog.users.admin_seed,
                    fixture,
                    fixtureKey,
                    userKey,
                    addMarker,
                    createdSourceQuestionId
                );
            } finally {
                resetE2EFixture(fixtureKey, userKey);
                await adminContext.close();
            }
        }
    });

    test('Heartbeat displaces the current question safely after the active source question is deleted', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixtureKey = 'question_runtime';
        const userKey = 'primary_student';
        const fixture = getE2EFixture(fixtureKey, userKey);
        const catalog = getE2ECatalog();
        const deleteMarker = `FLOW LIVE DELETE ${Date.now()}`;
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        let createdSourceQuestionId = 0;
        let initialQuestionCount = 0;
        let deletedExamQuestionId = 0;

        try {
            resetE2EFixture(fixtureKey, userKey);

            await test.step('Siswa membuka attempt dan admin menambah soal sementara yang nanti akan dihapus', async () => {
                await loginAsStudent(page, fixture.user);
                await startOrResumeAttempt(page, fixture);
                initialQuestionCount = await getQuestionNavCount(page);
                expect(initialQuestionCount).toBeGreaterThan(0);

                await answerCurrentSingleChoice(page, 0);

                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                createdSourceQuestionId = await createTemporaryMultipleChoiceQuestion(
                    adminPage,
                    Number(fixture.exam.subject_id),
                    deleteMarker
                );
                expect(createdSourceQuestionId).toBeGreaterThan(0);

                syncQuestionRuntimeFixture(fixture, fixtureKey, userKey);
                await waitForExamQuestionMatch(fixtureKey, userKey, {
                    markerText: deleteMarker,
                    sourceQuestionId: createdSourceQuestionId,
                }, initialQuestionCount + 1);
            });

            await test.step('Siswa berpindah ke soal baru itu hingga menjadi current question aktif', async () => {
                await page.bringToFront();
                await waitForRevisionNoticeText(page, 'soal baru ditambahkan', 35000);
                await expect.poll(async () => getQuestionNavCount(page), { timeout: 35000 }).toBe(initialQuestionCount + 1);

                const lastQuestionNumber = await jumpToLastQuestion(page);
                expect(lastQuestionNumber).toBe(initialQuestionCount + 1);
                await expect(page.locator('.cbt-question-stem')).toContainText(deleteMarker, { timeout: 10000 });

                deletedExamQuestionId = await getCurrentQuestionId(page);
                expect(deletedExamQuestionId).toBeGreaterThan(0);
                await answerCurrentSingleChoice(page, 0);
            });

            await test.step('Admin menghapus source question aktif lalu sinkron ke fixture', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                const deletedSourceQuestionId = await deleteQuestionRowByMarker(adminPage, deleteMarker);
                expect(deletedSourceQuestionId).toBe(createdSourceQuestionId);

                syncQuestionRuntimeFixture(fixture, fixtureKey, userKey);
                await waitForExamQuestionRemoval(fixtureKey, userKey, {
                    markerText: deleteMarker,
                    sourceQuestionId: createdSourceQuestionId,
                }, initialQuestionCount);
            });

            await test.step('Heartbeat siswa memindahkan runtime ke soal valid berikutnya tanpa keluar dari exam shell', async () => {
                await page.bringToFront();
                await waitForRevisionNoticeText(page, 'Soal aktif berubah karena revisi exam.', 35000);
                await expect(page.locator('.cbt-exam-revision-notice')).toContainText('Soal aktif berubah karena revisi exam.');
                await expect(page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 10000 });
                await expect(page.locator('#cbt-login-form')).toHaveCount(0);
                await expect.poll(async () => getQuestionNavCount(page), { timeout: 35000 }).toBe(initialQuestionCount);
                await expect(page.locator('.cbt-chip-question-index .cbt-chip-value')).toHaveText(String(initialQuestionCount), { timeout: 10000 });
                await expect(page.locator('.cbt-question-stem')).not.toContainText(deleteMarker);

                const displacedQuestionId = await waitForCondition(async () => {
                    const currentQuestionId = await getCurrentQuestionId(page);
                    return currentQuestionId > 0 && currentQuestionId !== deletedExamQuestionId
                        ? currentQuestionId
                        : null;
                }, {
                    timeoutMs: 10000,
                    intervalMs: 300,
                    errorMessage: 'Current question tidak berpindah ke row valid setelah source question aktif dihapus.',
                });

                expect(displacedQuestionId).not.toBe(deletedExamQuestionId);
            });
        } finally {
            try {
                await cleanupTemporaryQuestion(
                    adminPage,
                    catalog.users.admin_seed,
                    fixture,
                    fixtureKey,
                    userKey,
                    deleteMarker,
                    createdSourceQuestionId
                );
            } finally {
                resetE2EFixture(fixtureKey, userKey);
                await adminContext.close();
            }
        }
    });
});
