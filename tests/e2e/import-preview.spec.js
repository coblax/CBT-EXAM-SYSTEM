const path = require('path');
const { test, expect } = require('@playwright/test');
const {
    getE2ECatalog,
    getE2EFixture,
    resetE2EFixture,
    syncE2ESubjectBankQuestionsToFixture,
    updateE2EExamFixture,
} = require('./helpers/e2e-fixture');
const {
    answerCurrentMultipleChoice,
    answerCurrentSingleChoice,
    collectAndFinish,
    fillCurrentEssay,
    fillCurrentShortAnswer,
    loginAsStudent,
    startOrResumeAttempt,
    waitForResultShell,
} = require('./helpers/frontend-browser');
const {
    loginToWpAdmin,
    openExamPreviewPage,
    uploadQuestionsDocx,
} = require('./helpers/admin-browser');

const fixtureDirectory = path.resolve(__dirname, 'fixtures', 'import-preview');
const richMarker = 'FLOW IMPORT RICH 20260324: marker soal rich preview unik.';
const richOptionMarker = 'FLOW_RICH_OPSI_A';
const legacyMarker = 'FLOW IMPORT LEGACY 20260324: marker essay legacy tanpa pembahasan.';
const linebreakMarkerOne = 'FLOW IMPORT LINEBREAK 20260324 BARIS 1';
const linebreakMarkerTwo = 'BARIS 2';

test.describe.configure({ mode: 'serial' });

async function importDocxAndSync(adminPage, fixture, fileName, questionType) {
    await uploadQuestionsDocx(
        adminPage,
        Number(fixture.exam.subject_id),
        path.join(fixtureDirectory, fileName),
        questionType
    );
    await expect(adminPage.getByText('Import ke Bank Soal selesai diproses.')).toBeVisible({ timeout: 20000 });
    const syncResult = syncE2ESubjectBankQuestionsToFixture('import_preview', {
        subject_id: Number(fixture.exam.subject_id),
    });
    expect(Number(syncResult.synced_count || 0)).toBeGreaterThan(0);
    updateE2EExamFixture('import_preview', {
        show_student_result: 1,
        status: 'published',
    });
}

async function openPreviewCard(adminPage, examId, marker) {
    await openExamPreviewPage(adminPage, examId);
    const card = adminPage.locator('.cbt-admin-student-preview-card').filter({ hasText: String(marker || '') }).first();
    await expect(card).toBeVisible({ timeout: 20000 });
    return card;
}

async function answerFirstVisibleQuestion(page) {
    if (await page.locator('[data-action="answer-single"]').count()) {
        await answerCurrentSingleChoice(page, 0);
        return;
    }

    if (await page.locator('[data-action="answer-multi"]').count()) {
        await answerCurrentMultipleChoice(page, [0]);
        return;
    }

    if (await page.locator('[data-action="answer-short"]').count()) {
        await fillCurrentShortAnswer(page, { A: 'FLOW IMPORT SHORT' });
        return;
    }

    if (await page.locator('[data-action="answer-text"]').count()) {
        await fillCurrentEssay(page, 'FLOW IMPORT ESSAY');
    }
}

async function finishImportPreviewExam(page, fixture) {
    resetE2EFixture('import_preview', 'primary_student');
    await loginAsStudent(page, fixture.user);
    await startOrResumeAttempt(page, fixture);
    await answerFirstVisibleQuestion(page);
    await collectAndFinish(page);
    await waitForResultShell(page);
}

async function openReviewQuestion(page, marker) {
    const reviewQuestion = page.locator('.cbt-review-question').filter({ hasText: String(marker || '') }).first();
    await expect(reviewQuestion).toBeVisible({ timeout: 20000 });
    return reviewQuestion;
}

test.describe('Import & Preview flow check', () => {
    test.setTimeout(180000);

    test('Import Flow: rich DOCX import renders in admin preview', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengimpor fixture rich-content lalu sinkron ke exam preview', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'rich-content.docx', 'multiple_choice');
            });

            await test.step('Admin preview membaca marker rich-content dan opsi unik dari hasil import', async () => {
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, richMarker);
                await expect(card).toContainText(richOptionMarker);
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Import Flow: legacy DOCX without explanation still imports successfully', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengimpor fixture legacy tanpa PEMBAHASAN eksplisit', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'legacy-no-pembahasan.docx', 'essay');
            });

            await test.step('Preview card legacy tetap muncul tanpa section Pembahasan kosong', async () => {
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, legacyMarker);
                await expect(card).not.toContainText('Pembahasan');
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Import Flow: admin preview and student review show the same imported rich question', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Fixture rich-content diimpor lalu diverifikasi pada admin preview', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'rich-content.docx', 'multiple_choice');
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, richMarker);
                await expect(card).toContainText(richOptionMarker);
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Siswa membuka review hasil dan melihat marker rich-content yang sama', async () => {
            await finishImportPreviewExam(page, fixture);
            const reviewQuestion = await openReviewQuestion(page, richMarker);
            await expect(reviewQuestion).toContainText(richMarker);
        });
    });

    test('Import Flow: line-break DOCX stays readable in admin preview', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengimpor fixture line-break dan sinkron ke exam preview', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'image-linebreak.docx', 'multiple_choice');
            });

            await test.step('Preview admin mempertahankan dua baris marker sebagai konten yang mudah dibaca', async () => {
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, linebreakMarkerOne);
                const questionHtml = await card.locator('.cbt-admin-student-preview-question').first().innerHTML();
                expect(questionHtml).toContain(linebreakMarkerOne);
                expect(questionHtml).toContain(linebreakMarkerTwo);
                expect(/<br\s*\/?>/i.test(questionHtml)).toBeTruthy();
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Import Flow: rich import stays consistent between admin preview and student review', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin preview memverifikasi marker rich-content sebelum siswa membuka review', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'rich-content.docx', 'multiple_choice');
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, richMarker);
                await expect(card).toContainText(richOptionMarker);
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Review siswa tetap memuat marker rich-content dan opsi unik setelah finish', async () => {
            await finishImportPreviewExam(page, fixture);
            const reviewQuestion = await openReviewQuestion(page, richMarker);
            await expect(reviewQuestion).toContainText(richMarker);
            await expect(page.locator('.cbt-result-wrap')).toContainText(richOptionMarker);
        });
    });

    test('Import Flow: line-break review stays consistent after finish', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengimpor fixture line-break lalu menyinkronkannya ke exam review siswa', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'image-linebreak.docx', 'multiple_choice');
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Review siswa tetap menampilkan dua marker line-break dengan pemisah baris yang aman', async () => {
            await finishImportPreviewExam(page, fixture);
            const reviewQuestion = await openReviewQuestion(page, linebreakMarkerOne);
            const reviewHtml = await reviewQuestion.innerHTML();
            expect(reviewHtml).toContain(linebreakMarkerOne);
            expect(reviewHtml).toContain(linebreakMarkerTwo);
            expect(/<br\s*\/?>/i.test(reviewHtml)).toBeTruthy();
        });
    });
});
