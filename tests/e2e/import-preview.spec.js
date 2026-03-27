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
    prepareManualQuestion,
    setWpEditorContent,
    submitManualQuestionExpectDialog,
    uploadQuestionsDocx,
} = require('./helpers/admin-browser');

const fixtureDirectory = path.resolve(__dirname, 'fixtures', 'import-preview');
const richMarker = 'FLOW IMPORT RICH 20260324: marker soal rich preview unik.';
const richOptionMarker = 'FLOW_RICH_OPSI_A';
const legacyMarker = 'FLOW IMPORT LEGACY 20260324: marker essay legacy tanpa pembahasan.';
const linebreakMarkerOne = 'FLOW IMPORT LINEBREAK 20260324 BARIS 1';
const linebreakMarkerTwo = 'BARIS 2';
const invalidImportMarker = 'FLOW IMPORT INVALID HARDENING 20260326';

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

    test('Import Flow: invalid DOCX import shows precise failure list', async ({ browser, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('import_preview', 'primary_student');
        const catalog = getE2ECatalog();
        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();

        try {
            await test.step('Admin mengunggah DOCX invalid yang memuat beberapa blok hardening failure', async () => {
                await loginToWpAdmin(adminPage, catalog.users.admin_seed);
                await uploadQuestionsDocx(adminPage, Number(fixture.exam.subject_id), path.join(fixtureDirectory, 'invalid-hardening.docx'), 'multiple_choice');
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
});
