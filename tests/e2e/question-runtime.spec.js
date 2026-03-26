const { test, expect } = require('@playwright/test');
const {
    getE2EAttemptAnswers,
    getE2EExamQuestions,
    getE2EFixture,
    getLatestE2EAttempt,
} = require('./helpers/e2e-fixture');
const {
    answerCurrentMultipleChoice,
    answerCurrentSingleChoice,
    clickNextQuestion,
    clickPreviousQuestion,
    fillCurrentEssay,
    fillCurrentShortAnswer,
    getCheckedSingleChoiceOptionId,
    jumpToLastQuestion,
    jumpToQuestion,
    loginAsStudent,
    startOrResumeAttempt,
    toggleCurrentQuestionDoubtful,
    waitForAnswerSync,
} = require('./helpers/frontend-browser');
const { waitForCondition } = require('./helpers/flow-utils');

test.describe.configure({ mode: 'serial' });

function findQuestionIndexByType(questions, questionType) {
    const index = questions.findIndex((row) => String(row && row.question_type ? row.question_type : '') === String(questionType));
    return index >= 0 ? (index + 1) : 1;
}

async function prepareRuntimeAttempt(page, fixture) {
    await loginAsStudent(page, fixture.user);
    await startOrResumeAttempt(page, fixture);
    const attempt = getLatestE2EAttempt('question_runtime', 'primary_student');
    expect(attempt && attempt.id).toBeTruthy();
    const questions = getE2EExamQuestions('question_runtime', 'primary_student');
    expect(Array.isArray(questions) && questions.length > 0).toBeTruthy();
    return { attempt, questions };
}

test.describe('Question Runtime flow check', () => {
    test.setTimeout(150000);

    test('Runtime Flow: mixed question answers stay isolated', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('question_runtime', 'primary_student');
        const prepared = await prepareRuntimeAttempt(page, fixture);
        const multipleAnswerIndex = findQuestionIndexByType(prepared.questions, 'multiple_answer');
        const shortAnswerIndex = findQuestionIndexByType(prepared.questions, 'short_answer');
        const essayIndex = findQuestionIndexByType(prepared.questions, 'essay');

        await test.step('Isi tiga tipe soal berbeda secara bergantian', async () => {
            await jumpToQuestion(page, multipleAnswerIndex);
            await answerCurrentMultipleChoice(page, [0, 1]);
            await jumpToQuestion(page, shortAnswerIndex);
            await fillCurrentShortAnswer(page, { A: 'isolated-short' });
            await jumpToQuestion(page, essayIndex);
            await fillCurrentEssay(page, 'Essay runtime isolation.');
        });

        await test.step('Backend menyimpan row jawaban terpisah per question id', async () => {
            const answers = await waitForCondition(() => {
                const rows = getE2EAttemptAnswers('question_runtime', Number(prepared.attempt.id), 'primary_student');
                return Array.isArray(rows) && rows.length >= 3 ? rows : null;
            }, {
                timeoutMs: 25000,
                intervalMs: 400,
                errorMessage: 'Mixed runtime answers tidak muncul lengkap di backend.',
            });
            const answeredQuestionIds = answers.map((row) => Number(row.question_id) || 0).filter(Boolean);
            expect(new Set(answeredQuestionIds).size).toBeGreaterThanOrEqual(3);
        });
    });

    test('Runtime Flow: doubtful persists across navigation', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('question_runtime', 'primary_student');
        await prepareRuntimeAttempt(page, fixture);

        await test.step('Toggle doubtful pada soal aktif lalu pindah dan kembali', async () => {
            await answerCurrentSingleChoice(page, 0);
            await toggleCurrentQuestionDoubtful(page);
            await clickNextQuestion(page);
            await clickPreviousQuestion(page);
        });

        await test.step('Flag doubtful dan pilihan jawaban tetap utuh', async () => {
            await expect(page.locator('[data-action="toggle-doubtful"]').first()).toHaveClass(/is-active/, { timeout: 20000 });
            await expect(page.locator('[data-action="answer-single"]:checked').first()).toBeVisible({ timeout: 20000 });
        });
    });

    test('Runtime Flow: boundary navigation clamps safely', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('question_runtime', 'primary_student');
        await prepareRuntimeAttempt(page, fixture);

        await test.step('Navigasi ke soal terakhir lalu kembali ke awal', async () => {
            const lastQuestionNumber = await jumpToLastQuestion(page);
            expect(lastQuestionNumber).toBeGreaterThan(1);
            await jumpToQuestion(page, 1);
        });

        await test.step('UI tetap berada pada indeks valid tanpa overflow', async () => {
            await expect(page.locator('.cbt-chip-question-index .cbt-chip-value')).toHaveText('1', { timeout: 20000 });
        });
    });

    test('Runtime Flow: randomize options keeps mapped answer after refresh', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('question_runtime', 'primary_student');
        await prepareRuntimeAttempt(page, fixture);
        let selectedOptionId = 0;

        await test.step('Jawab opsi acak pada soal pertama', async () => {
            selectedOptionId = await answerCurrentSingleChoice(page, 1);
            expect(selectedOptionId).toBeGreaterThan(0);
        });

        await test.step('Refresh halaman lalu pastikan option id yang sama tetap tercentang', async () => {
            await page.reload();
            await expect(page.locator(`[data-action="answer-single"][data-option-id="${selectedOptionId}"]`)).toBeChecked({ timeout: 20000 });
        });
    });

    test('Runtime Flow: doubtful and answer revisions remain consistent', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('question_runtime', 'primary_student');
        await prepareRuntimeAttempt(page, fixture);

        await test.step('Pilih jawaban awal, aktifkan doubtful, lalu revisi jawaban setelah maju-mundur', async () => {
            await answerCurrentSingleChoice(page, 0);
            await toggleCurrentQuestionDoubtful(page);
            await clickNextQuestion(page);
            await clickPreviousQuestion(page);
            await answerCurrentSingleChoice(page, 2);
        });

        await test.step('Pilihan terbaru tersimpan bersama doubtful state aktif', async () => {
            const currentOptionId = await getCheckedSingleChoiceOptionId(page);
            expect(currentOptionId).toBeGreaterThan(0);
            await expect(page.locator('[data-action="toggle-doubtful"]').first()).toHaveClass(/is-active/, { timeout: 20000 });
        });
    });

    test('Runtime Flow: rapid navigation does not swap adjacent payloads', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('question_runtime', 'primary_student');
        const prepared = await prepareRuntimeAttempt(page, fixture);
        const firstQuestionId = Number(prepared.questions[0] && prepared.questions[0].id ? prepared.questions[0].id : 0);
        const secondQuestionId = Number(prepared.questions[1] && prepared.questions[1].id ? prepared.questions[1].id : 0);

        await test.step('Jawab dua soal berdekatan dengan navigasi cepat maju mundur', async () => {
            await answerCurrentSingleChoice(page, 0);
            await clickNextQuestion(page);
            await answerCurrentSingleChoice(page, 1);
            await clickPreviousQuestion(page);
            await clickNextQuestion(page);
            await waitForAnswerSync(page, 3000);
        });

        await test.step('Row backend tetap terikat ke question_id yang benar', async () => {
            const answers = await waitForCondition(() => {
                const rows = getE2EAttemptAnswers('question_runtime', Number(prepared.attempt.id), 'primary_student');
                return Array.isArray(rows) && rows.length >= 2 ? rows : null;
            }, {
                timeoutMs: 25000,
                intervalMs: 400,
                errorMessage: 'Adjacent answers tidak tersimpan lengkap di backend.',
            });
            const answeredIds = new Set(answers.map((row) => Number(row.question_id) || 0));
            expect(answeredIds.has(firstQuestionId)).toBe(true);
            expect(answeredIds.has(secondQuestionId)).toBe(true);
        });
    });
});
