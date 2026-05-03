const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { test, expect } = require('@playwright/test');
const {
    getE2EAttemptAnswers,
    getE2ECatalog,
    getE2EExamQuestions,
    getE2EFixture,
    getLatestE2EAttempt,
    resetE2EFixture,
    setE2ESecurityConfig,
    syncE2ESubjectBankQuestionsToFixture,
    updateE2EExamFixture,
} = require('./helpers/e2e-fixture');
const {
    deleteQuestionRowByMarker,
    getQuestionIdByMarker,
    loginToWpAdmin,
    openQuestionEditPage,
    openQuestionsImportPage,
    prepareManualQuestion,
    setWpEditorContent,
    submitManualQuestionExpectSuccess,
    uploadQuestionsDocx,
} = require('./helpers/admin-browser');
const {
    answerCurrentCategorization,
    answerCurrentClozeDropdown,
    answerCurrentMatching,
    answerCurrentTableCompletion,
    jumpToQuestion,
    loginAsStudent,
    startOrResumeAttempt,
    waitForAnswerSync,
} = require('./helpers/frontend-browser');
const { waitForCondition } = require('./helpers/flow-utils');

test.describe.configure({ mode: 'serial' });

const newQuestionTypes = ['matching', 'cloze_dropdown', 'categorization', 'table_completion'];

function escapeXml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

function createDocxFixture(lines, fileSlug) {
    const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), `cbt-${fileSlug}-`));
    const wordDir = path.join(tempDir, 'word');
    const relsDir = path.join(tempDir, '_rels');
    const wordRelsDir = path.join(wordDir, '_rels');
    fs.mkdirSync(wordRelsDir, { recursive: true });
    fs.mkdirSync(relsDir, { recursive: true });

    const paragraphXml = lines.map((line) => {
        return `<w:p><w:r><w:t xml:space="preserve">${escapeXml(line)}</w:t></w:r></w:p>`;
    }).join('');

    fs.writeFileSync(path.join(tempDir, '[Content_Types].xml'), [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">',
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>',
        '<Default Extension="xml" ContentType="application/xml"/>',
        '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>',
        '</Types>',
    ].join(''));
    fs.writeFileSync(path.join(relsDir, '.rels'), [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">',
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>',
        '</Relationships>',
    ].join(''));
    fs.writeFileSync(path.join(wordRelsDir, 'document.xml.rels'), [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
    ].join(''));
    fs.writeFileSync(path.join(wordDir, 'document.xml'), [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">',
        '<w:body>',
        paragraphXml,
        '<w:sectPr/>',
        '</w:body>',
        '</w:document>',
    ].join(''));

    const outputPath = path.join(os.tmpdir(), `${fileSlug}-${Date.now()}-${Math.random().toString(16).slice(2)}.docx`);
    execFileSync('python3', [
        '-c',
        [
            'import os, sys, zipfile',
            'source_dir, target_zip = sys.argv[1], sys.argv[2]',
            'with zipfile.ZipFile(target_zip, "w", zipfile.ZIP_DEFLATED) as archive:',
            '    for root, _, files in os.walk(source_dir):',
            '        for file_name in files:',
            '            file_path = os.path.join(root, file_name)',
            '            archive.write(file_path, os.path.relpath(file_path, source_dir))',
        ].join('\n'),
        tempDir,
        outputPath,
    ]);

    return outputPath;
}

function docxLinesForType(questionType, marker) {
    const base = [
        'CBT_TEMPLATE: question_import_v2',
        'CATATAN_VALIDATOR: E2E generated structured question type fixture.',
        '---',
        `JENIS_SOAL: ${questionType}`,
    ];

    if (questionType === 'matching') {
        return base.concat([
            `SOAL: ${marker} Cocokkan negara dan ibu kota.`,
            'KIRI_1: Indonesia',
            'KANAN_1: Jakarta',
            'KIRI_2: Jepang',
            'KANAN_2: Tokyo',
            'POIN: 1',
            'PEMBAHASAN: Import matching berhasil.',
            '---',
        ]);
    }

    if (questionType === 'cloze_dropdown') {
        return base.concat([
            `SOAL: ${marker} Ibu kota Jepang adalah [DROPDOWN_1].`,
            'DROPDOWN_1_OPSI_1: Seoul',
            'DROPDOWN_1_OPSI_2: Tokyo',
            'DROPDOWN_1_JAWABAN: 2',
            'POIN: 1',
            'PEMBAHASAN: Import cloze berhasil.',
            '---',
        ]);
    }

    if (questionType === 'categorization') {
        return base.concat([
            `SOAL: ${marker} Kelompokkan item berikut.`,
            'KATEGORI_1: Mamalia',
            'KATEGORI_2: Reptil',
            'ITEM_1: Kucing',
            'KUNCI_1: 1',
            'ITEM_2: Ular',
            'KUNCI_2: Reptil',
            'POIN: 1',
            'PEMBAHASAN: Import categorization berhasil.',
            '---',
        ]);
    }

    return base.concat([
        `SOAL: ${marker} Lengkapi tabel berikut.`,
        'TABLE_ROWS: 2',
        'TABLE_COLS: 2',
        'CELL_A1_TYPE: static',
        'CELL_A1_TEXT: Negara',
        'CELL_B1_TYPE: static',
        'CELL_B1_TEXT: Ibu kota',
        'CELL_A2_TYPE: static',
        'CELL_A2_TEXT: Jepang',
        'CELL_B2_TYPE: dropdown',
        'CELL_B2_OPSI_1: Seoul',
        'CELL_B2_OPSI_2: Tokyo',
        'CELL_B2_JAWABAN: 2',
        'POIN: 1',
        'PEMBAHASAN: Import table completion berhasil.',
        '---',
    ]);
}

function findQuestionByType(questions, questionType) {
    const index = questions.findIndex((question) => String(question && question.question_type || '') === questionType);
    expect(index, `Fixture harus punya soal ${questionType}`).toBeGreaterThanOrEqual(0);
    return {
        index: index + 1,
        row: questions[index],
    };
}

function parseAnswerText(row) {
    try {
        return JSON.parse(String(row && row.answer_text || '{}'));
    } catch (error) {
        return {};
    }
}

async function expectDropdownMapRestored(page, selector, keyAttribute, expectedMap) {
    const entries = Object.entries(expectedMap || {});
    expect(entries.length).toBeGreaterThan(0);
    for (const [key, value] of entries) {
        const control = page.locator(`${selector}[${keyAttribute}="${key}"]`).first();
        await expect(control).toBeVisible({ timeout: 20000 });
        await expect(control).toHaveValue(String(value), { timeout: 20000 });
    }
}

async function enterFullscreenIfPrompted(page) {
    const fullscreenButton = page.locator('[data-action="enter-fullscreen"]').first();
    if (!(await fullscreenButton.isVisible().catch(() => false))) {
        return;
    }

    await fullscreenButton.click({ force: true });
    await expect(fullscreenButton).toBeHidden({ timeout: 20000 });
}

test.describe('New question types flow check', () => {
    test.setTimeout(240000);

    test('Admin manual authoring saves and reopens compact structured forms', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const catalog = getE2ECatalog();
        const fixture = getE2EFixture('import_preview', 'primary_student');
        const adminUser = catalog.users.admin_seed;
        const markerBase = `E2E MANUAL NEW TYPES ${Date.now()}`;
        const createdMarkers = [];

        await loginToWpAdmin(page, adminUser);

        try {
            const matchingMarker = `${markerBase} MATCHING`;
            createdMarkers.push(matchingMarker);
            await prepareManualQuestion(page, {
                subjectId: Number(fixture.exam.subject_id),
                questionType: 'matching',
                questionHtml: `<p>${matchingMarker} Cocokkan pasangan.</p>`,
            });
            await page.selectOption('#cbt_matching_pair_count', '2');
            await setWpEditorContent(page, 'cbt_matching_left_1', '<p>Indonesia</p>');
            await setWpEditorContent(page, 'cbt_matching_right_1', '<p>Jakarta</p>');
            await setWpEditorContent(page, 'cbt_matching_left_2', '<p>Jepang</p>');
            await setWpEditorContent(page, 'cbt_matching_right_2', '<p>Tokyo</p>');
            await submitManualQuestionExpectSuccess(page);
            const matchingQuestionId = await getQuestionIdByMarker(page, matchingMarker);
            await openQuestionEditPage(page, matchingQuestionId);
            await expect(page.locator('#cbt-question-type-hidden')).toHaveValue('matching', { timeout: 20000 });
            await expect(page.locator('#cbt_matching_pair_count')).toHaveValue('2', { timeout: 20000 });

            const clozeMarker = `${markerBase} CLOZE`;
            createdMarkers.push(clozeMarker);
            await prepareManualQuestion(page, {
                subjectId: Number(fixture.exam.subject_id),
                questionType: 'cloze_dropdown',
                questionHtml: `<p>${clozeMarker} Ibu kota Jepang adalah [DROPDOWN_1].</p>`,
            });
            await page.selectOption('#cbt_cloze_dropdown_count', '1');
            await page.selectOption('#cbt_cloze_option_count', '2');
            await page.locator('#cbt_cloze_1_option_1').fill('Seoul');
            await page.locator('#cbt_cloze_1_option_2').fill('Tokyo');
            await page.selectOption('#cbt_cloze_correct_1', '2');
            await submitManualQuestionExpectSuccess(page);
            const clozeQuestionId = await getQuestionIdByMarker(page, clozeMarker);
            await openQuestionEditPage(page, clozeQuestionId);
            await expect(page.locator('#cbt-question-type-hidden')).toHaveValue('cloze_dropdown', { timeout: 20000 });
            await expect(page.locator('#cbt_cloze_dropdown_count')).toHaveValue('1', { timeout: 20000 });
            await expect(page.locator('#cbt_cloze_option_count')).toHaveValue('2', { timeout: 20000 });

            const categorizationMarker = `${markerBase} CAT`;
            createdMarkers.push(categorizationMarker);
            await prepareManualQuestion(page, {
                subjectId: Number(fixture.exam.subject_id),
                questionType: 'categorization',
                questionHtml: `<p>${categorizationMarker} Kelompokkan item.</p>`,
            });
            await page.selectOption('#cbt_cat_category_count', '2');
            await page.selectOption('#cbt_cat_item_count', '2');
            await page.locator('#cbt_cat_category_1').fill('Mamalia');
            await page.locator('#cbt_cat_category_2').fill('Reptil');
            await page.locator('#cbt_cat_item_1').fill('Kucing');
            await page.selectOption('#cbt_cat_correct_1', '1');
            await page.locator('#cbt_cat_item_2').fill('Ular');
            await page.selectOption('#cbt_cat_correct_2', '2');
            await submitManualQuestionExpectSuccess(page);
            const categorizationQuestionId = await getQuestionIdByMarker(page, categorizationMarker);
            await openQuestionEditPage(page, categorizationQuestionId);
            await expect(page.locator('#cbt-question-type-hidden')).toHaveValue('categorization', { timeout: 20000 });
            await expect(page.locator('#cbt_cat_category_count')).toHaveValue('2', { timeout: 20000 });
            await expect(page.locator('#cbt_cat_item_count')).toHaveValue('2', { timeout: 20000 });

            const tableMarker = `${markerBase} TABLE`;
            createdMarkers.push(tableMarker);
            await prepareManualQuestion(page, {
                subjectId: Number(fixture.exam.subject_id),
                questionType: 'table_completion',
                questionHtml: `<p>${tableMarker} Lengkapi tabel.</p>`,
            });
            await page.selectOption('#cbt_table_rows', '2');
            await page.selectOption('#cbt_table_cols', '3');
            await page.selectOption('#cbt_table_A1_type', 'static');
            await page.locator('#cbt_table_A1_text').fill('Negara');
            await page.selectOption('#cbt_table_B1_type', 'static');
            await page.locator('#cbt_table_B1_text').fill('Ibu kota');
            await page.selectOption('#cbt_table_C1_type', 'static');
            await page.locator('#cbt_table_C1_text').fill('Benua');
            await page.selectOption('#cbt_table_A2_type', 'static');
            await page.locator('#cbt_table_A2_text').fill('Jepang');
            await page.selectOption('#cbt_table_B2_type', 'text');
            await page.locator('#cbt_table_B2_answer').fill('Tokyo');
            await page.selectOption('#cbt_table_C2_type', 'dropdown');
            await page.locator('#cbt_table_C2_option_1').fill('Asia');
            await page.locator('#cbt_table_C2_option_2').fill('Eropa');
            await page.selectOption('#cbt_table_C2_correct', '1');
            await submitManualQuestionExpectSuccess(page);
            const tableQuestionId = await getQuestionIdByMarker(page, tableMarker);
            await openQuestionEditPage(page, tableQuestionId);
            await expect(page.locator('#cbt-question-type-hidden')).toHaveValue('table_completion', { timeout: 20000 });
            await expect(page.locator('#cbt_table_rows')).toHaveValue('2', { timeout: 20000 });
            await expect(page.locator('#cbt_table_cols')).toHaveValue('3', { timeout: 20000 });
        } finally {
            for (const marker of createdMarkers.reverse()) {
                await deleteQuestionRowByMarker(page, marker).catch(() => {});
            }
        }
    });

    test('DOCX import accepts all new structured question types', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const catalog = getE2ECatalog();
        const fixture = getE2EFixture('import_preview', 'primary_student');
        const adminUser = catalog.users.admin_seed;
        const markerBase = `E2E IMPORT NEW TYPES ${Date.now()}`;
        const importedMarkers = [];

        await loginToWpAdmin(page, adminUser);

        try {
            for (const questionType of newQuestionTypes) {
                const marker = `${markerBase} ${questionType}`;
                importedMarkers.push(marker);
                const docxPath = createDocxFixture(docxLinesForType(questionType, marker), `new-${questionType}`);
                await uploadQuestionsDocx(page, Number(fixture.exam.subject_id), docxPath, questionType);
                await expect(page.getByText('Hasil Import Batch Ini')).toBeVisible({ timeout: 60000 });
                await expect(page.getByText(marker).first()).toBeVisible({ timeout: 20000 });
            }
        } finally {
            for (const marker of importedMarkers.reverse()) {
                await deleteQuestionRowByMarker(page, marker).catch(() => {});
            }
        }
    });

    test('Student runtime restores object-map answers for new question types', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('question_runtime', 'primary_student');
        const syncResult = syncE2ESubjectBankQuestionsToFixture('question_runtime', {
            subject_id: Number(fixture.exam.subject_id),
        });
        expect(Number(syncResult.synced_count || 0)).toBeGreaterThan(0);
        updateE2EExamFixture('question_runtime', {
            show_student_result: 1,
            status: 'published',
        });
        resetE2EFixture('question_runtime', 'primary_student');
        const previousSecurity = setE2ESecurityConfig({});
        setE2ESecurityConfig({ force_fullscreen: 0 });

        try {
            await loginAsStudent(page, fixture.user);
            await startOrResumeAttempt(page, fixture);

            const attempt = getLatestE2EAttempt('question_runtime', 'primary_student');
            const questions = getE2EExamQuestions('question_runtime', 'primary_student');
            const matching = findQuestionByType(questions, 'matching');
            const cloze = findQuestionByType(questions, 'cloze_dropdown');
            const categorization = findQuestionByType(questions, 'categorization');
            const table = findQuestionByType(questions, 'table_completion');

            await jumpToQuestion(page, matching.index);
            const matchingAnswer = await answerCurrentMatching(page);
            await jumpToQuestion(page, cloze.index);
            const clozeAnswer = await answerCurrentClozeDropdown(page);
            await jumpToQuestion(page, categorization.index);
            const categorizationAnswer = await answerCurrentCategorization(page);
            await jumpToQuestion(page, table.index);
            const tableAnswer = await answerCurrentTableCompletion(page, 'runtime-table');

            await waitForAnswerSync(page, 5000);

            const answerRows = await waitForCondition(() => {
                const rows = getE2EAttemptAnswers('question_runtime', Number(attempt.id), 'primary_student');
                return Array.isArray(rows) && rows.length >= 4 ? rows : null;
            }, {
                timeoutMs: 30000,
                intervalMs: 500,
                errorMessage: 'Jawaban object-map tipe soal baru belum tersimpan lengkap di backend.',
            });
            const rowsByQuestionId = Object.fromEntries(answerRows.map((row) => [Number(row.question_id), row]));
            expect(parseAnswerText(rowsByQuestionId[Number(matching.row.id)])).toMatchObject(matchingAnswer);
            expect(parseAnswerText(rowsByQuestionId[Number(cloze.row.id)])).toMatchObject(clozeAnswer);
            expect(parseAnswerText(rowsByQuestionId[Number(categorization.row.id)])).toMatchObject(categorizationAnswer);
            expect(parseAnswerText(rowsByQuestionId[Number(table.row.id)])).toMatchObject(tableAnswer);

            await page.reload({ waitUntil: 'domcontentloaded' });
            await expect(page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
            await enterFullscreenIfPrompted(page);

            await jumpToQuestion(page, matching.index);
            await expectDropdownMapRestored(page, '[data-action="answer-matching"]', 'data-matching-key', matchingAnswer);
            await jumpToQuestion(page, cloze.index);
            await expectDropdownMapRestored(page, '[data-action="answer-cloze-dropdown"]', 'data-cloze-key', clozeAnswer);
            await jumpToQuestion(page, categorization.index);
            await expectDropdownMapRestored(page, '[data-action="answer-categorization"]', 'data-categorization-key', categorizationAnswer);
            await jumpToQuestion(page, table.index);
            await expectDropdownMapRestored(page, '[data-action="answer-table-completion-dropdown"]', 'data-table-key', Object.fromEntries(
                Object.entries(tableAnswer).filter(([, value]) => Number(value) > 0)
            ));
            for (const [key, value] of Object.entries(tableAnswer).filter(([, value]) => typeof value === 'string')) {
                await expect(page.locator(`[data-action="answer-table-completion-text"][data-table-key="${key}"]`).first()).toHaveValue(value, { timeout: 20000 });
            }
        } finally {
            setE2ESecurityConfig({
                force_fullscreen: Number(previousSecurity && previousSecurity.force_fullscreen || 0) === 1,
            });
            resetE2EFixture('question_runtime', 'primary_student');
        }
    });

    test('Import template controls expose structured parameters for new types', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const catalog = getE2ECatalog();
        await loginToWpAdmin(page, catalog.users.admin_seed);
        await openQuestionsImportPage(page);

        const expectations = {
            matching: ['pair_count'],
            cloze_dropdown: ['dropdown_count', 'dropdown_option_count'],
            categorization: ['category_count', 'categorization_item_count'],
            table_completion: ['table_rows', 'table_cols'],
        };
        const actionNames = {
            matching: 'cbt_download_question_template_word_matching',
            cloze_dropdown: 'cbt_download_question_template_word_cloze',
            categorization: 'cbt_download_question_template_word_categorization',
            table_completion: 'cbt_download_question_template_word_table_completion',
        };

        for (const [questionType, params] of Object.entries(expectations)) {
            await page.locator(`[data-import-type="${questionType}"]`).first().click({ force: true });
            const download = page.locator('#cbt-download-word-template').first();
            await expect(download).toBeVisible({ timeout: 20000 });
            const href = String(await download.getAttribute('href') || '');
            expect(href).toContain(`action=${actionNames[questionType]}`);
            expect(href).toContain('question_count=');
            params.forEach((param) => {
                expect(href).toContain(`${param}=`);
                expect(page.locator(`[data-template-control="${param}"]`)).toBeVisible({ timeout: 20000 });
            });
        }
    });
});
