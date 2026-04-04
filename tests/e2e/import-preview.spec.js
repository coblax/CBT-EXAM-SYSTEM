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
    jumpToLastQuestion,
    loginAsStudent,
    startOrResumeAttempt,
    waitForResultShell,
} = require('./helpers/frontend-browser');
const {
    loginToWpAdmin,
    insertEquationIntoTfMatrixStatement,
    insertEquationIntoWpEditor,
    openExamPreviewPage,
    prepareManualQuestion,
    setWpEditorContent,
    submitManualQuestionExpectDialog,
    submitManualQuestionExpectSuccess,
    uploadQuestionsDocx,
} = require('./helpers/admin-browser');

const fixtureDirectory = path.resolve(__dirname, 'fixtures', 'import-preview');
const richMarker = 'FLOW IMPORT RICH 20260324: marker soal rich preview unik.';
const richOptionMarker = 'FLOW_RICH_OPSI_A';
const essayNoExplanationMarker = 'FLOW IMPORT ESSAY NO PEMBAHASAN 20260328: marker essay valid tanpa pembahasan.';
const linebreakMarkerOne = 'FLOW IMPORT LINEBREAK 20260324 BARIS 1';
const linebreakMarkerTwo = 'BARIS 2';
const invalidImportMarker = 'FLOW IMPORT INVALID HARDENING 20260326';
const nativeListMcMarker = 'FLOW IMPORT LIST MC 20260328: marker rich list multiple choice.';
const nativeListMcQuestionItemOne = 'FLOW LIST MC SOAL BUTIR 1';
const nativeListMcQuestionItemTwo = 'FLOW LIST MC SOAL BUTIR 2';
const nativeListMcOptionItemOne = 'FLOW LIST MC OPSI LANGKAH 1';
const nativeListMcOptionItemTwo = 'FLOW LIST MC OPSI LANGKAH 2';
const nativeListMcExplanationOne = 'FLOW LIST MC PEMBAHASAN BUTIR 1';
const nativeListMcExplanationTwo = 'FLOW LIST MC PEMBAHASAN BUTIR 2';
const nativeListTfmMarker = 'FLOW IMPORT LIST TFM 20260328: marker pernyataan rich list.';
const nativeListTfmStatementOne = 'FLOW LIST TFM STATEMENT 1 BUTIR A';
const nativeListTfmStatementTwo = 'FLOW LIST TFM STATEMENT 1 BUTIR B';
const nativeListEssayMarker = 'FLOW IMPORT LIST ESSAY 20260328: marker rubrik rich list.';
const nativeListEssayRubricOne = 'FLOW LIST ESSAY RUBRIK LANGKAH 1';
const nativeListEssayRubricTwo = 'FLOW LIST ESSAY RUBRIK LANGKAH 2';
const nativeEquationMcMarker = 'FLOW IMPORT EQUATION MC 20260328: marker equation multiple choice.';
const nativeEquationEssayMarker = 'FLOW IMPORT EQUATION ESSAY 20260328: marker equation rubric essay.';
const manualEquationMcMarker = 'FLOW MANUAL EQUATION MC 20260328: marker equation authoring manual.';
const manualEquationEssayMarker = 'FLOW MANUAL EQUATION ESSAY 20260328: marker equation essay authoring manual.';
const manualEquationTfmMarker = 'FLOW MANUAL EQUATION TFM 20260328: marker equation tf matrix authoring.';

test.describe.configure({ mode: 'serial' });

async function importDocxAndSync(adminPage, fixture, fileName, questionType) {
    await uploadQuestionsDocx(
        adminPage,
        Number(fixture.exam.subject_id),
        path.join(fixtureDirectory, fileName),
        questionType
    );
    await expect(
        adminPage.getByText(/Import (ke Bank Soal selesai diproses\.|soal ke Bank Soal selesai\.)/i).first()
    ).toBeVisible({ timeout: 20000 });
    const syncResult = syncE2ESubjectBankQuestionsToFixture('import_preview', {
        subject_id: Number(fixture.exam.subject_id),
    });
    expect(Number(syncResult.synced_count || 0)).toBeGreaterThan(0);
    updateE2EExamFixture('import_preview', {
        show_student_result: 1,
        status: 'published',
    });
}

async function syncFixtureQuestionBank() {
    const syncResult = syncE2ESubjectBankQuestionsToFixture('import_preview', {
        subject_id: Number(getE2EFixture('import_preview', 'primary_student').exam.subject_id),
    });
    expect(Number(syncResult.synced_count || 0)).toBeGreaterThan(0);
    updateE2EExamFixture('import_preview', {
        show_student_result: 1,
        status: 'published',
    });
}

async function importDocxExpectBatchPanel(adminPage, fixture, fileName, questionType) {
    await uploadQuestionsDocx(
        adminPage,
        Number(fixture.exam.subject_id),
        path.join(fixtureDirectory, fileName),
        questionType
    );
    await expect(adminPage.getByText('Hasil Import Batch Ini')).toBeVisible({ timeout: 60000 });
}

async function openBatchImportResultList(adminPage) {
    const listButton = adminPage.getByRole('link', { name: 'LIHAT SEMUA HASIL IMPORT INI' }).first();
    await listButton.scrollIntoViewIfNeeded();
    await expect(listButton).toBeVisible({ timeout: 20000 });
    const popupPromise = adminPage.waitForEvent('popup');
    await listButton.click({ force: true });
    const batchPage = await popupPromise;
    await batchPage.waitForLoadState('domcontentloaded');
    await expect(batchPage).toHaveURL(/cbt_question_import_scope=created/, { timeout: 20000 });
    await expect(batchPage.getByText('Hasil Import Batch', { exact: true })).toBeVisible({ timeout: 20000 });
    return batchPage;
}

async function waitForQuestionDeleteBatchCompletion(adminPage) {
    await adminPage.waitForURL((url) => {
        const currentUrl = String(url || '');
        return currentUrl.includes('/wp-admin/admin.php?page=cbt-question-bank')
            && !currentUrl.includes('cbt_question_delete_token=');
    }, { timeout: 60000 });
    await expect(adminPage.locator('.notice.notice-success, .notice.notice-info, .updated.notice').first()).toBeVisible({ timeout: 20000 });
}

async function pagePromiseClickAndWaitForDeleteSelected(adminPage) {
    await adminPage.getByRole('button', { name: 'Delete Selected' }).first().click({ force: true });
    await waitForQuestionDeleteBatchCompletion(adminPage);
}

async function openPreviewCard(adminPage, examId, marker) {
    await openExamPreviewPage(adminPage, examId);
    const card = adminPage.locator('.cbt-admin-student-preview-card').filter({ hasText: String(marker || '') }).last();
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
    const reviewQuestion = page.locator('.cbt-review-question').filter({ hasText: String(marker || '') }).last();
    await expect(reviewQuestion).toBeVisible({ timeout: 20000 });
    return reviewQuestion;
}

async function openReviewItem(page, marker) {
    const reviewQuestion = await openReviewQuestion(page, marker);
    const reviewItem = reviewQuestion.locator('xpath=ancestor::article[contains(@class,"cbt-review-item")]').first();
    await expect(reviewItem).toBeVisible({ timeout: 20000 });
    return reviewItem;
}

function expectHtmlContainsList(html, expectedSnippets = [], expectedTags = ['ul']) {
    const normalizedHtml = String(html || '');
    expectedSnippets.forEach((snippet) => {
        expect(normalizedHtml).toContain(String(snippet));
    });
    expectedTags.forEach((tag) => {
        expect(new RegExp(`<${String(tag)}(?:\\s|>)`, 'i').test(normalizedHtml)).toBeTruthy();
    });
}

function normalizeMathSignatureEntries(entries = []) {
    return (Array.isArray(entries) ? entries : [])
        .map((entry) => ({
            source: String(entry && entry.source ? entry.source : '').trim(),
            display: String(entry && entry.display ? entry.display : '').trim().toLowerCase() === 'block' ? 'block' : 'inline',
            rendered: !!(entry && entry.rendered),
        }))
        .sort((left, right) => {
            const leftKey = `${left.display}::${left.source}`;
            const rightKey = `${right.display}::${right.source}`;
            return leftKey.localeCompare(rightKey);
        });
}

async function readMathSignature(locator) {
    const entries = await locator.locator('.cbt-math[data-cbt-math]').evaluateAll((nodes) => {
        return nodes.map((node) => ({
            source: String(node.getAttribute('data-cbt-math') || '').trim(),
            display: String(node.getAttribute('data-cbt-math-display') || '').trim(),
            rendered: node.classList.contains('is-katex-rendered') || !!node.querySelector('.katex'),
        }));
    });

    return normalizeMathSignatureEntries(entries);
}

function expectMathSignatureParity(referenceEntries, currentEntries) {
    expect(Array.isArray(referenceEntries)).toBeTruthy();
    expect(Array.isArray(currentEntries)).toBeTruthy();
    expect(referenceEntries.length).toBeGreaterThan(0);
    expect(currentEntries.length).toBeGreaterThan(0);
    expect(referenceEntries.every((entry) => entry.rendered)).toBeTruthy();
    expect(currentEntries.every((entry) => entry.rendered)).toBeTruthy();
    expect(currentEntries).toEqual(referenceEntries);
}

async function expectImportFailureEntry(page, options = {}) {
    const blockNumber = Number(options.blockNumber || 0);
    const typeLabel = String(options.typeLabel || '');
    const preview = String(options.preview || '');
    const message = String(options.message || '');
    let row = page.locator('.notice-warning li').first();
    if (blockNumber > 0) {
        row = page.locator('.notice-warning li').filter({
            hasText: `Blok #${blockNumber}`,
        }).first();
    } else if (preview !== '') {
        row = page.locator('.notice-warning li').filter({
            hasText: preview,
        }).first();
    } else if (message !== '') {
        row = page.locator('.notice-warning li').filter({
            hasText: message,
        }).first();
    }
    await expect(row).toBeVisible({ timeout: 20000 });
    if (blockNumber > 0) {
        await expect(row).toContainText(`Blok #${blockNumber}`);
    }
    if (typeLabel !== '') {
        await expect(row).toContainText(typeLabel);
    }
    if (preview !== '') {
        await expect(row).toContainText(preview);
    }
    if (message !== '') {
        await expect(row).toContainText(message);
    }
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

    test('Import Flow: DOCX v2 without explanation still imports successfully', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengimpor fixture v2 tanpa PEMBAHASAN eksplisit', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'essay-no-pembahasan-v2.docx', 'essay');
            });

            await test.step('Preview card tetap muncul tanpa section Pembahasan kosong', async () => {
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, essayNoExplanationMarker);
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

    test('Import Flow: batch analysis master-detail shows per-question diagnostics after DOCX import', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await loginToWpAdmin(adminPage, catalog.users.admin_seed);
            await importDocxExpectBatchPanel(adminPage, fixture, 'rich-content.docx', 'multiple_choice');

            const batchPanel = adminPage.locator('[data-cbt-import-batch-analysis]');
            await expect(batchPanel).toBeVisible({ timeout: 20000 });
            await expect(batchPanel.getByText(/Created:\s+\d+/)).toBeVisible();
            await expect(batchPanel.getByText(/Preserved:\s+\d+/)).toBeVisible();
            await expect(batchPanel.getByText(/Fallback:\s+\d+/)).toBeVisible();
            await expect(batchPanel.getByText(/Unsupported:\s+\d+/)).toBeVisible();

            const navItems = batchPanel.locator('[data-cbt-import-batch-analysis-nav-item]');
            await expect(navItems.first()).toBeVisible({ timeout: 20000 });

            const activePanel = batchPanel.locator('[data-cbt-import-batch-analysis-panel].is-active');
            await expect(activePanel).toBeVisible({ timeout: 20000 });
            await expect(activePanel.getByRole('button', { name: 'Perlu Dicek' })).toHaveClass(/is-active/);
            await expect(activePanel.getByRole('link', { name: 'Lihat Soal di Question List' })).toBeVisible();

            const visibleNeedsReviewItems = activePanel.locator('.cbt-import-batch-analysis-item:not([hidden])');
            const visibleNeedsReviewCount = await visibleNeedsReviewItems.count();
            if (visibleNeedsReviewCount === 0) {
                await expect(activePanel.getByText('Soal ini tidak punya catatan yang perlu dicek.')).toBeVisible();
            }

            const navCount = await navItems.count();
            if (navCount > 1) {
                const secondNav = navItems.nth(1);
                const secondQuestionId = String(await secondNav.getAttribute('data-question-id') || '');
                await secondNav.click();
                await expect(batchPanel.locator(`[data-cbt-import-batch-analysis-panel][data-question-id="${secondQuestionId}"].is-active`)).toBeVisible({ timeout: 20000 });
            }
        } finally {
            await adminContext.close();
        }
    });

    test('Import Flow: batch result panel opens batch-only list and hides edit action', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        let batchPage = null;

        try {
            await test.step('Admin mengimpor fixture lalu membuka list hasil batch import saja', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxExpectBatchPanel(adminPage, fixture, 'native-list-mc.docx', 'multiple_choice');
                batchPage = await openBatchImportResultList(adminPage);
                await expect(batchPage.locator('.cbt-questions-row-action--edit')).toHaveCount(0);
                await expect(batchPage.locator('.cbt-questions-list-toolbar')).toHaveCount(0);
            });

            await test.step('Preview inline tetap bisa dibuka dari mode batch', async () => {
                const batchRow = batchPage.locator('tbody tr')
                    .filter({ has: batchPage.locator('input.cbt-question-checkbox') })
                    .filter({ hasText: 'FLOW IMPORT LIST MC 20260328' })
                    .first();
                await expect(batchRow).toBeVisible({ timeout: 20000 });
                await batchRow.locator('.cbt-questions-row-action--view').click({ force: true });
                await expect(batchPage.locator('.cbt-question-inline-preview').filter({ hasText: nativeListMcMarker }).first()).toBeVisible({ timeout: 20000 });
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Import Flow: batch result row delete and delete selected stay inside batch scope', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        let batchPage = null;

        try {
            await loginToWpAdmin(adminPage, catalog.users.admin_seed);

            await test.step('Delete row tunggal dari mode batch kembali ke scope batch yang sama', async () => {
                await importDocxExpectBatchPanel(adminPage, fixture, 'image-linebreak.docx', 'multiple_choice');
                batchPage = await openBatchImportResultList(adminPage);
                const row = batchPage.locator('tbody tr')
                    .filter({ has: batchPage.locator('input.cbt-question-checkbox') })
                    .filter({ hasText: 'FLOW IMPORT LINEBREAK 20260324' })
                    .first();
                await expect(row).toBeVisible({ timeout: 20000 });
                batchPage.once('dialog', (dialog) => dialog.accept());
                await row.locator('.cbt-questions-row-action--delete').click({ force: true });
                await expect(batchPage).toHaveURL(/cbt_msg=/, { timeout: 20000 });
                await expect(batchPage).toHaveURL(/cbt_question_import_scope=created/);
                await expect(batchPage.locator('tbody tr').filter({ hasText: 'FLOW IMPORT LINEBREAK 20260324' })).toHaveCount(0);
            });

            await test.step('Delete Selected hanya menghapus soal batch terpilih dan tetap kembali ke batch scope', async () => {
                await importDocxExpectBatchPanel(adminPage, fixture, 'native-list-mc.docx', 'multiple_choice');
                batchPage = await openBatchImportResultList(adminPage);
                const row = batchPage.locator('tbody tr')
                    .filter({ has: batchPage.locator('input.cbt-question-checkbox') })
                    .filter({ hasText: 'FLOW IMPORT LIST MC 20260328' })
                    .first();
                await expect(row).toBeVisible({ timeout: 20000 });
                await row.locator('input.cbt-question-checkbox').check({ force: true });
                batchPage.once('dialog', (dialog) => dialog.accept());
                await pagePromiseClickAndWaitForDeleteSelected(batchPage);
                await expect(batchPage).toHaveURL(/cbt_question_import_scope=created/);
                await expect(batchPage.locator('tbody tr').filter({ hasText: 'FLOW IMPORT LIST MC 20260328' })).toHaveCount(0);
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Import Flow: delete all batch clears import result scope and shows empty state', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        let batchPage = null;

        try {
            await loginToWpAdmin(adminPage, catalog.users.admin_seed);
            await importDocxExpectBatchPanel(adminPage, fixture, 'native-list-mc.docx', 'multiple_choice');
            batchPage = await openBatchImportResultList(adminPage);

            await test.step('Delete All Batch mengosongkan seluruh hasil batch dan mempertahankan empty state batch', async () => {
                batchPage.once('dialog', (dialog) => dialog.accept());
                await batchPage.getByRole('link', { name: 'Delete All Batch' }).first().click({ force: true });
                await waitForQuestionDeleteBatchCompletion(batchPage);
                await expect(batchPage).toHaveURL(/cbt_question_import_scope=created/);
                await expect(batchPage.getByText('Batch hasil import ini sudah kosong atau belum memiliki soal sukses.')).toBeVisible({ timeout: 20000 });
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Import Flow: DOCX native list multiple choice stays readable in admin preview and student review', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengimpor fixture list native multiple choice dan sinkron ke exam preview', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'native-list-mc.docx', 'multiple_choice');
            });

            await test.step('Preview admin mempertahankan list pada soal, opsi, dan pembahasan', async () => {
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, nativeListMcMarker);
                const previewHtml = await card.innerHTML();
                expectHtmlContainsList(previewHtml, [
                    nativeListMcQuestionItemOne,
                    nativeListMcQuestionItemTwo,
                    nativeListMcOptionItemOne,
                    nativeListMcOptionItemTwo,
                    nativeListMcExplanationOne,
                    nativeListMcExplanationTwo,
                ], ['ul', 'ol']);
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Review siswa tetap mempertahankan list yang sama setelah finish', async () => {
            await finishImportPreviewExam(page, fixture);
            const reviewItem = await openReviewItem(page, nativeListMcMarker);
            const reviewHtml = await reviewItem.innerHTML();
            expectHtmlContainsList(reviewHtml, [
                nativeListMcQuestionItemOne,
                nativeListMcQuestionItemTwo,
                nativeListMcOptionItemOne,
                nativeListMcOptionItemTwo,
                nativeListMcExplanationOne,
                nativeListMcExplanationTwo,
            ], ['ul', 'ol']);
        });
    });

    test('Import Flow: DOCX native list TF matrix stays readable in admin preview and student review', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengimpor fixture list native TF matrix dan sinkron ke exam preview', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'native-list-tfmatrix.docx', 'true_false_matrix');
            });

            await test.step('Preview admin mempertahankan list pada statement TF matrix', async () => {
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, nativeListTfmMarker);
                const previewHtml = await card.innerHTML();
                expectHtmlContainsList(previewHtml, [
                    nativeListTfmStatementOne,
                    nativeListTfmStatementTwo,
                ], ['ul']);
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Review siswa tetap mempertahankan list pada statement TF matrix', async () => {
            await finishImportPreviewExam(page, fixture);
            const reviewItem = await openReviewItem(page, nativeListTfmMarker);
            const reviewHtml = await reviewItem.innerHTML();
            expectHtmlContainsList(reviewHtml, [
                nativeListTfmStatementOne,
                nativeListTfmStatementTwo,
            ], ['ul']);
        });
    });

    test('Import Flow: DOCX native list essay rubric stays readable in admin preview and student review', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengimpor fixture list native essay dan sinkron ke exam preview', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'native-list-essay.docx', 'essay');
            });

            await test.step('Preview admin mempertahankan list pada rubrik essay', async () => {
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, nativeListEssayMarker);
                const previewHtml = await card.innerHTML();
                expectHtmlContainsList(previewHtml, [
                    nativeListEssayRubricOne,
                    nativeListEssayRubricTwo,
                ], ['ol']);
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Review siswa tetap mempertahankan list pada rubrik essay', async () => {
            await finishImportPreviewExam(page, fixture);
            const reviewItem = await openReviewItem(page, nativeListEssayMarker);
            const reviewHtml = await reviewItem.innerHTML();
            expectHtmlContainsList(reviewHtml, [
                nativeListEssayRubricOne,
                nativeListEssayRubricTwo,
            ], ['ol']);
        });
    });

    test('Import Flow: DOCX equation multiple choice renders as KaTeX in admin preview, exam, and review', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengimpor fixture equation multiple choice dan preview menampilkan KaTeX', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'native-equation-mc.docx', 'multiple_choice');
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, nativeEquationMcMarker);
                await expect(card.locator('.katex').first()).toBeVisible({ timeout: 20000 });
                await expect(card).toContainText(nativeEquationMcMarker);
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Runtime exam siswa merender equation stem dan opsi sebagai KaTeX', async () => {
            resetE2EFixture('import_preview', 'primary_student');
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);
            await jumpToLastQuestion(page);
            await expect(page.locator('.cbt-question-stem')).toContainText(nativeEquationMcMarker);
            await expect(page.locator('.cbt-question-stem .katex').first()).toBeVisible({ timeout: 20000 });
            await expect(page.locator('.cbt-option-label .katex').first()).toBeVisible({ timeout: 20000 });
            await collectAndFinish(page);
            await waitForResultShell(page);
        });

        await test.step('Review hasil siswa tetap menampilkan equation soal, opsi, dan pembahasan sebagai KaTeX', async () => {
            const reviewItem = await openReviewItem(page, nativeEquationMcMarker);
            await expect(reviewItem.locator('.cbt-review-question .katex').first()).toBeVisible({ timeout: 20000 });
            await expect(reviewItem.locator('.cbt-review-option .katex').first()).toBeVisible({ timeout: 20000 });
            await expect(reviewItem.locator('.cbt-review-explanation .katex').first()).toBeVisible({ timeout: 20000 });
        });
    });

    test('Import Flow: DOCX equation multiple choice keeps the same math signature in admin preview, exam, and review', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        let adminExamSignature = [];
        let adminReviewSignature = [];

        try {
            await test.step('Admin preview menghasilkan signature math yang stabil untuk soal equation multiple choice', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'native-equation-mc.docx', 'multiple_choice');
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, nativeEquationMcMarker);
                adminExamSignature = await readMathSignature(
                    card.locator('.cbt-admin-student-preview-question, .cbt-admin-student-preview-options')
                );
                adminReviewSignature = await readMathSignature(card);
                expectMathSignatureParity(adminExamSignature, adminExamSignature);
                expectMathSignatureParity(adminReviewSignature, adminReviewSignature);
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Runtime exam siswa mempertahankan signature math yang sama', async () => {
            resetE2EFixture('import_preview', 'primary_student');
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);
            await jumpToLastQuestion(page);
            await expect(page.locator('.cbt-question-stem')).toContainText(nativeEquationMcMarker);

            const examSignature = await readMathSignature(page.locator('[data-cbt-exam-shell="1"]'));
            expectMathSignatureParity(adminExamSignature, examSignature);

            await collectAndFinish(page);
            await waitForResultShell(page);
        });

        await test.step('Review hasil siswa mempertahankan signature math yang sama', async () => {
            const reviewItem = await openReviewItem(page, nativeEquationMcMarker);
            const reviewSignature = await readMathSignature(reviewItem);
            expectMathSignatureParity(adminReviewSignature, reviewSignature);
        });
    });

    test('Import Flow: DOCX equation essay rubric renders as KaTeX in admin preview and student review', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengimpor fixture equation essay dan preview rubrik menampilkan KaTeX', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'native-equation-essay.docx', 'essay');
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, nativeEquationEssayMarker);
                await expect(card.locator('.katex').first()).toBeVisible({ timeout: 20000 });
                await expect(card).toContainText(nativeEquationEssayMarker);
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Review siswa tetap menampilkan KaTeX pada rubrik essay setelah finish', async () => {
            await finishImportPreviewExam(page, fixture);
            const reviewItem = await openReviewItem(page, nativeEquationEssayMarker);
            const rubricPair = reviewItem.locator('.cbt-review-pair').filter({ hasText: 'Acuan/Rubrik:' }).first();
            await expect(rubricPair.locator('.katex').first()).toBeVisible({ timeout: 20000 });
        });
    });

    test('Import Flow: DOCX equation essay rubric keeps the same math signature in admin preview and student review', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        let adminSignature = [];

        try {
            await test.step('Admin preview menghasilkan signature math yang stabil untuk rubrik equation essay', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await importDocxAndSync(adminPage, fixture, 'native-equation-essay.docx', 'essay');
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, nativeEquationEssayMarker);
                adminSignature = await readMathSignature(card);
                expectMathSignatureParity(adminSignature, adminSignature);
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Review siswa mempertahankan signature math yang sama pada rubrik essay', async () => {
            await finishImportPreviewExam(page, fixture);
            const reviewItem = await openReviewItem(page, nativeEquationEssayMarker);
            const reviewSignature = await readMathSignature(reviewItem);
            expectMathSignatureParity(adminSignature, reviewSignature);
        });
    });

    test('Import Flow: invalid DOCX import shows precise failure list', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengunggah DOCX invalid yang memuat beberapa blok hardening failure', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await uploadQuestionsDocx(adminPage, Number(fixture.exam.subject_id), path.join(fixtureDirectory, 'invalid-hardening.docx'), 'all');
                await expect(
                    adminPage.getByText(/Import (ke Bank Soal selesai diproses\.|soal ke Bank Soal selesai\.)/i).first()
                ).toBeVisible({ timeout: 20000 });
            });

            await test.step('Panel import menampilkan daftar failure dengan blok, tipe, preview, dan pesan spesifik', async () => {
                await expectImportFailureEntry(adminPage, {
                    blockNumber: 1,
                    typeLabel: 'Multiple Choice',
                    preview: invalidImportMarker,
                    message: 'Jawaban benar menunjuk ke pilihan yang kosong atau tidak ada.',
                });
                await expectImportFailureEntry(adminPage, {
                    blockNumber: 2,
                    preview: 'Lengkapi [INPUT_A] dan [INPUT_C].',
                    message: 'Key placeholder Short Answer harus cocok dengan key jawaban yang diisi.',
                });
                await expectImportFailureEntry(adminPage, {
                    blockNumber: 3,
                    preview: 'Tentukan Benar/Salah untuk tiap pernyataan.',
                    message: 'PERNYATAAN_n dan KUNCI_n harus diisi berurutan tanpa nomor yang loncat.',
                });
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Import Flow: manual MC save blocks empty correct option', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await loginToWpAdmin(adminPage, catalog.users.admin_seed);
            await prepareManualQuestion(adminPage, {
                subjectId: Number(fixture.exam.subject_id),
                questionType: 'multiple_choice',
                questionHtml: '<p>FLOW MANUAL MC INVALID 20260326</p>',
            });
            await setWpEditorContent(adminPage, 'cbt_mc_option_1', '<p>Jakarta</p>');
            await setWpEditorContent(adminPage, 'cbt_mc_option_3', '<p>Bandung</p>');
            await setWpEditorContent(adminPage, 'cbt_mc_option_4', '<p>Surabaya</p>');
            await adminPage.selectOption('#cbt-correct-mc-index', '2');

            await test.step('Save manual ditahan dengan alert spesifik saat jawaban benar menunjuk pilihan kosong', async () => {
                const dialog = await submitManualQuestionExpectDialog(adminPage);
                expect(dialog.message()).toContain('Jawaban benar Multiple Choice tidak boleh menunjuk pilihan kosong.');
                await dialog.dismiss();
                await expect(adminPage.locator('#cbt-question-manual-form')).toBeVisible({ timeout: 20000 });
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Import Flow: manual MA save blocks checked empty option', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await loginToWpAdmin(adminPage, catalog.users.admin_seed);
            await prepareManualQuestion(adminPage, {
                subjectId: Number(fixture.exam.subject_id),
                questionType: 'multiple_answer',
                questionHtml: '<p>FLOW MANUAL MA INVALID 20260326</p>',
            });
            await setWpEditorContent(adminPage, 'cbt_ma_option_1', '<p>2</p>');
            await setWpEditorContent(adminPage, 'cbt_ma_option_2', '<p>4</p>');
            await setWpEditorContent(adminPage, 'cbt_ma_option_4', '<p>6</p>');
            await adminPage.locator('#cbt-ma-correct-1').check({ force: true });
            await adminPage.locator('#cbt-ma-correct-3').check({ force: true });

            await test.step('Save manual ditahan dengan alert spesifik saat checkbox benar menandai pilihan kosong', async () => {
                const dialog = await submitManualQuestionExpectDialog(adminPage);
                expect(dialog.message()).toContain('Multiple Answer tidak boleh menandai jawaban benar pada pilihan yang kosong.');
                await dialog.dismiss();
                await expect(adminPage.locator('#cbt-question-manual-form')).toBeVisible({ timeout: 20000 });
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Import Flow: manual TF matrix save blocks numbering gap and duplicate statement', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await loginToWpAdmin(adminPage, catalog.users.admin_seed);
            await prepareManualQuestion(adminPage, {
                subjectId: Number(fixture.exam.subject_id),
                questionType: 'true_false_matrix',
                questionHtml: '<p>FLOW MANUAL TFM INVALID 20260326</p>',
            });

            await test.step('Gap numbering diblokir saat statement diisi loncat', async () => {
                await adminPage.locator('#cbt-tfm-statement-1').fill('Air membeku pada 0C.');
                await adminPage.locator('#cbt-tfm-statement-3').fill('Matahari adalah bintang.');
                const dialog = await submitManualQuestionExpectDialog(adminPage);
                expect(dialog.message()).toContain('Pernyataan True/False Matrix harus diisi berurutan tanpa nomor yang loncat.');
                await dialog.dismiss();
            });

            await test.step('Duplicate statement diblokir setelah numbering dibuat kontigu', async () => {
                await adminPage.locator('#cbt-tfm-statement-2').fill(' air membeku pada 0c. ');
                const dialog = await submitManualQuestionExpectDialog(adminPage);
                expect(dialog.message()).toContain('True/False Matrix tidak boleh punya pernyataan duplikat.');
                await dialog.dismiss();
                await expect(adminPage.locator('#cbt-question-manual-form')).toBeVisible({ timeout: 20000 });
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Import Flow: manual equation multiple choice stays consistent in preview, exam, and review', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin membuat soal MC manual dengan equation pada stem, opsi, dan pembahasan lalu mengedit equation stem yang sudah ada', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await prepareManualQuestion(adminPage, {
                    subjectId: Number(fixture.exam.subject_id),
                    questionType: 'multiple_choice',
                    questionHtml: `<p>${manualEquationMcMarker}</p>`,
                });
                await insertEquationIntoWpEditor(adminPage, 'cbt_question_text_editor', {
                    categoryKey: 'basic',
                    templateKey: 'fraction',
                    mode: 'visual',
                    displayMode: 'inline',
                });
                await insertEquationIntoWpEditor(adminPage, 'cbt_question_text_editor', {
                    mode: 'visual',
                    editExisting: true,
                    source: 'x^{2} + ',
                    symbolKeys: ['theta'],
                    displayMode: 'inline',
                });
                await setWpEditorContent(adminPage, 'cbt_mc_option_1', '<p>Jawaban benar</p>');
                await insertEquationIntoWpEditor(adminPage, 'cbt_mc_option_1', {
                    mode: 'visual',
                    categoryKey: 'statistics',
                    templateKey: 'mean',
                    useSuggestedDisplay: true,
                });
                await setWpEditorContent(adminPage, 'cbt_mc_option_2', '<p>Distractor satu</p>');
                await setWpEditorContent(adminPage, 'cbt_mc_option_3', '<p>Distractor dua</p>');
                await adminPage.selectOption('#cbt-correct-mc-index', '1');
                await setWpEditorContent(adminPage, 'cbt_question_explanation_editor', '<p>Pembahasan equation</p>');
                await insertEquationIntoWpEditor(adminPage, 'cbt_question_explanation_editor', {
                    mode: 'visual',
                    categoryKey: 'calculus',
                    templateKey: 'integral',
                    useSuggestedDisplay: true,
                });
                await expect.poll(async () => adminPage.evaluate(() => String(document.getElementById('cbt_question_text_editor')?.value || ''))).toContain('\\theta');
                await expect.poll(async () => adminPage.evaluate(() => String(document.getElementById('cbt_mc_option_1')?.value || ''))).toContain('\\bar{x}');
                await expect.poll(async () => adminPage.evaluate(() => String(document.getElementById('cbt_question_explanation_editor')?.value || ''))).toContain('data-cbt-math-display="block"');
                await submitManualQuestionExpectSuccess(adminPage);
                await syncFixtureQuestionBank();
            });

            await test.step('Admin preview menampilkan KaTeX untuk soal MC manual', async () => {
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, manualEquationMcMarker);
                await expect(card.locator('.katex').first()).toBeVisible({ timeout: 20000 });
                await expect(card.locator('.cbt-admin-student-preview-question .katex').first()).toBeVisible({ timeout: 20000 });
                await expect(card.locator('.cbt-admin-student-preview-option-text .katex').first()).toBeVisible({ timeout: 20000 });
                await expect(card.locator('.cbt-admin-student-preview-section .katex').last()).toBeVisible({ timeout: 20000 });
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Runtime exam dan review hasil tetap menampilkan equation MC manual yang sama', async () => {
            resetE2EFixture('import_preview', 'primary_student');
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);
            await jumpToLastQuestion(page);
            await expect(page.locator('.cbt-question-stem')).toContainText(manualEquationMcMarker);
            await expect(page.locator('.cbt-question-stem .katex').first()).toBeVisible({ timeout: 20000 });
            await expect(page.locator('.cbt-option-label .katex').first()).toBeVisible({ timeout: 20000 });
            await answerCurrentSingleChoice(page, 0);
            await collectAndFinish(page);
            await waitForResultShell(page);
            const reviewItem = await openReviewItem(page, manualEquationMcMarker);
            await expect(reviewItem.locator('.cbt-review-question .katex').first()).toBeVisible({ timeout: 20000 });
            await expect(reviewItem.locator('.cbt-review-option .katex').first()).toBeVisible({ timeout: 20000 });
            await expect(reviewItem.locator('.cbt-review-explanation .katex').first()).toBeVisible({ timeout: 20000 });
        });
    });

    test('Import Flow: manual equation essay rubric supports quicktags path and stays consistent in preview and review', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin membuat soal essay manual dengan equation pada rubric lewat mode Text/Quicktags', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await prepareManualQuestion(adminPage, {
                    subjectId: Number(fixture.exam.subject_id),
                    questionType: 'essay',
                    questionHtml: `<p>${manualEquationEssayMarker}</p>`,
                });
                await setWpEditorContent(adminPage, 'cbt_essay_answer_editor', '<p>Rubrik essay</p>');
                await insertEquationIntoWpEditor(adminPage, 'cbt_essay_answer_editor', {
                    mode: 'text',
                    categoryKey: 'linear-algebra',
                    templateKey: 'vector-2d',
                    useSuggestedDisplay: true,
                });
                await expect.poll(async () => adminPage.evaluate(() => String(document.getElementById('cbt_essay_answer_editor')?.value || ''))).toContain('\\vec{v}');
                await submitManualQuestionExpectSuccess(adminPage);
                await syncFixtureQuestionBank();
            });

            await test.step('Admin preview menampilkan KaTeX pada rubrik essay manual', async () => {
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, manualEquationEssayMarker);
                await expect(card.locator('.katex').first()).toBeVisible({ timeout: 20000 });
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Review hasil siswa tetap menampilkan KaTeX pada rubric essay manual', async () => {
            resetE2EFixture('import_preview', 'primary_student');
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);
            await jumpToLastQuestion(page);
            await fillCurrentEssay(page, 'Jawaban essay flow equation');
            await collectAndFinish(page);
            await waitForResultShell(page);
            const reviewItem = await openReviewItem(page, manualEquationEssayMarker);
            const rubricPair = reviewItem.locator('.cbt-review-pair').filter({ hasText: 'Acuan/Rubrik:' }).first();
            await expect(rubricPair.locator('.katex').first()).toBeVisible({ timeout: 20000 });
        });
    });

    test('Import Flow: manual TF matrix equation and quick template stay consistent in preview and review', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin membuat TF Matrix manual dengan statement equation dari template cepat', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await prepareManualQuestion(adminPage, {
                    subjectId: Number(fixture.exam.subject_id),
                    questionType: 'true_false_matrix',
                    questionHtml: `<p>${manualEquationTfmMarker}</p>`,
                });
                await adminPage.locator('#cbt-tfm-statement-1').fill('Hitung nilai berikut ');
                await insertEquationIntoTfMatrixStatement(adminPage, 1, {
                    categoryKey: 'basic',
                    templateKey: 'fraction',
                    useSuggestedDisplay: true,
                });
                await adminPage.locator('#cbt-tfm-statement-2').fill('Pernyataan kontrol tanpa equation');
                await adminPage.selectOption('#cbt-tfm-answer-1', 'true');
                await adminPage.selectOption('#cbt-tfm-answer-2', 'false');
                await expect.poll(async () => adminPage.evaluate(() => String(document.getElementById('cbt-tfm-statement-1')?.value || ''))).toContain('data-cbt-math-display="block"');
                await submitManualQuestionExpectSuccess(adminPage);
                await syncFixtureQuestionBank();
            });

            await test.step('Admin preview menampilkan KaTeX pada statement TF Matrix manual', async () => {
                const card = await openPreviewCard(adminPage, fixture.exam.exam_id, manualEquationTfmMarker);
                await expect(card.locator('.cbt-admin-student-preview-matrix .katex').first()).toBeVisible({ timeout: 20000 });
            });
        } finally {
            await adminContext.close();
        }

        await test.step('Review hasil siswa tetap menampilkan KaTeX pada statement TF Matrix manual', async () => {
            resetE2EFixture('import_preview', 'primary_student');
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);
            await jumpToLastQuestion(page);
            await page.locator('[data-action="answer-tf-matrix"][data-key="1"][data-value="true"]').first().check({ force: true });
            await page.locator('[data-action="answer-tf-matrix"][data-key="2"][data-value="false"]').first().check({ force: true });
            await collectAndFinish(page);
            await waitForResultShell(page);
            const reviewItem = await openReviewItem(page, manualEquationTfmMarker);
            await expect(reviewItem.locator('.cbt-tf-matrix-statement .katex').first()).toBeVisible({ timeout: 20000 });
        });
    });
});
