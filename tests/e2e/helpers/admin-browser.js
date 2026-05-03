const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
const { expect } = require('@playwright/test');

async function submitWpAdminLoginForm(page, adminUser) {
    await expect(page.locator('#user_login')).toBeVisible({ timeout: 20000 });
    await page.locator('#user_login').fill(String(adminUser.username || ''));
    await page.locator('#user_pass').fill(String(adminUser.password || ''));
    await page.locator('#wp-submit').click();
    await page.waitForURL((url) => !String(url || '').includes('/wp-login.php'), { timeout: 20000 }).catch(() => {});
    await page.waitForLoadState('networkidle').catch(() => {});
}

async function loginToWpAdmin(page, adminUser) {
    if (adminUser && typeof adminUser === 'object') {
        page.__cbtAdminUser = {
            username: String(adminUser.username || ''),
            password: String(adminUser.password || ''),
        };
    }

    await page.goto('/wp-admin/');
    if (await page.locator('#wpadminbar').count()) {
        await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 20000 });
        await page.waitForLoadState('networkidle').catch(() => {});
        return;
    }

    if (!await page.locator('#user_login').count()) {
        await page.goto('/wp-login.php');
    }

    if (await page.locator('#wpadminbar').count()) {
        await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 20000 });
        await page.waitForLoadState('networkidle').catch(() => {});
        return;
    }

    await submitWpAdminLoginForm(page, adminUser);
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 20000 });
    await page.waitForLoadState('networkidle').catch(() => {});
}

async function openResultsPage(page, examId) {
    const nextUrl = examId && Number(examId) > 0
        ? `/wp-admin/admin.php?page=cbt-results&cbt_exam_id=${Number(examId)}`
        : '/wp-admin/admin.php?page=cbt-results';
    const resultsShell = page.locator('.cbt-results-page, #cbt-results-filter-card, #cbt-results-tab-btn-monitoring').first();
    const loginForm = page.locator('#user_login');
    const rememberedAdminUser = page.__cbtAdminUser && typeof page.__cbtAdminUser === 'object'
        ? page.__cbtAdminUser
        : null;

    await page.goto(nextUrl, { waitUntil: 'domcontentloaded' });

    if (await loginForm.count()) {
        if (!rememberedAdminUser || !rememberedAdminUser.username || !rememberedAdminUser.password) {
            throw new Error('Admin login session hilang sebelum membuka CBT Results dan kredensial admin tidak tersedia untuk recovery.');
        }
        await submitWpAdminLoginForm(page, rememberedAdminUser);
    }

    try {
        await expect(resultsShell).toBeVisible({ timeout: 10000 });
    } catch (error) {
        await page.goto(nextUrl, { waitUntil: 'networkidle' }).catch(async () => {
            await page.reload({ waitUntil: 'networkidle' });
        });

        if (await loginForm.count()) {
            if (!rememberedAdminUser || !rememberedAdminUser.username || !rememberedAdminUser.password) {
                throw new Error('Admin login session hilang saat retry membuka CBT Results dan kredensial admin tidak tersedia untuk recovery.');
            }
            await submitWpAdminLoginForm(page, rememberedAdminUser);
        }

        await expect(resultsShell).toBeVisible({ timeout: 20000 });
    }
}

async function openResultsEssayTab(page, examId) {
    await openResultsPage(page, examId);
    const essayTab = page.locator('[data-cbt-results-tab="essay"], #cbt-results-tab-btn-essay').first();
    await expect(essayTab).toBeVisible({ timeout: 20000 });
    await essayTab.click({ force: true });
    await expect(page.locator('#cbt-results-tab-panel-essay')).toBeVisible({ timeout: 20000 });
}

async function openSetupSecurityLogPage(page) {
    await page.goto('/wp-admin/admin.php?page=cbt-security#security-log');
    const tabButton = page.locator('#cbt-setup-tab-security-log').first();
    await expect(tabButton).toBeVisible({ timeout: 20000 });
    await tabButton.click({ force: true });
    await expect(page.locator('#cbt-setup-panel-security-log')).toBeVisible({ timeout: 20000 });
}

async function openSetupSecurityNativePage(page) {
    await page.goto('/wp-admin/admin.php?page=cbt-security#native');
    const tabButton = page.locator('#cbt-setup-tab-native').first();
    await expect(tabButton).toBeVisible({ timeout: 20000 });
    await tabButton.click({ force: true });
    await expect(page.locator('#cbt-setup-panel-native')).toBeVisible({ timeout: 20000 });
}

async function openQuestionsImportPage(page) {
    await page.goto('/wp-admin/admin.php?page=cbt-question-bank');
    const importTab = page.locator('[data-cbt-questions-tab="import"]').first();
    await expect(importTab).toBeVisible({ timeout: 20000 });
    await importTab.click({ force: true });
    await expect(page.locator('#cbt-import-subject-id')).toBeVisible({ timeout: 20000 });
}

async function openQuestionsListPage(page) {
    await page.goto('/wp-admin/admin.php?page=cbt-question-bank');
    const listTab = page.locator('[data-cbt-questions-tab="list"]').first();
    await expect(listTab).toBeVisible({ timeout: 20000 });
    await listTab.click({ force: true });
    await expect(page.locator('[data-cbt-questions-list-shell]').first()).toBeVisible({ timeout: 20000 });
}

async function openQuestionsFormPage(page) {
    await page.goto('/wp-admin/admin.php?page=cbt-question-bank');
    const formTab = page.locator('[data-cbt-questions-tab="form"]').first();
    await expect(formTab).toBeVisible({ timeout: 20000 });
    await formTab.click({ force: true });
    await expect(page.locator('#cbt-question-manual-form')).toBeVisible({ timeout: 20000 });
}

async function openQuestionEditPage(page, questionId) {
    const safeQuestionId = Number(questionId) || 0;
    if (safeQuestionId <= 0) {
        throw new Error('questionId tidak valid untuk openQuestionEditPage().');
    }

    await page.goto(`/wp-admin/admin.php?page=cbt-question-bank&edit=${safeQuestionId}`);
    await expect(page.locator('#cbt-question-manual-form')).toBeVisible({ timeout: 20000 });
    await expect(page.locator('input[name="id"]').first()).toHaveValue(String(safeQuestionId), { timeout: 20000 });
}

async function findQuestionRowByMarker(page, markerText) {
    const safeMarkerText = String(markerText || '').trim();
    if (safeMarkerText === '') {
        throw new Error('markerText tidak valid untuk findQuestionRowByMarker().');
    }

    await openQuestionsListPage(page);
    const row = page.locator('tr[id^="cbt-question-row-"]').filter({ hasText: safeMarkerText }).first();
    await expect(row).toBeVisible({ timeout: 20000 });
    return row;
}

async function getQuestionIdFromRow(rowLocator) {
    const checkbox = rowLocator.locator('.cbt-question-checkbox').first();
    await expect(checkbox).toBeVisible({ timeout: 20000 });
    const value = await checkbox.getAttribute('value');
    return Number(value) || 0;
}

async function getQuestionIdByMarker(page, markerText) {
    const row = await findQuestionRowByMarker(page, markerText);
    return getQuestionIdFromRow(row);
}

async function deleteQuestionRowByMarker(page, markerText) {
    const row = await findQuestionRowByMarker(page, markerText);
    const questionId = await getQuestionIdFromRow(row);
    const deleteLink = row.locator('.cbt-questions-row-action--delete').first();
    await expect(deleteLink).toBeVisible({ timeout: 20000 });
    await deleteLink.scrollIntoViewIfNeeded();

    page.once('dialog', async (dialog) => {
        await dialog.accept();
    });

    await Promise.all([
        page.waitForURL((url) => decodeURIComponent(String(url)).includes('cbt_msg=Question deleted'), { timeout: 20000 }),
        deleteLink.click({ force: true }),
    ]);

    await expect(page.locator('.notice.notice-success, .updated.notice, .notice-info').first()).toBeVisible({ timeout: 20000 });
    return questionId;
}

async function setWpEditorContent(page, editorId, html) {
    await page.evaluate(({ editorId: targetEditorId, html: nextHtml }) => {
        const normalizedHtml = String(nextHtml || '');
        const tinyMceGlobal = window.tinymce || window.tinyMCE;
        const editor = tinyMceGlobal && typeof tinyMceGlobal.get === 'function'
            ? tinyMceGlobal.get(targetEditorId)
            : null;

        if (editor && typeof editor.setContent === 'function') {
            editor.setContent(normalizedHtml);
        }

        const textarea = document.getElementById(targetEditorId);
        if (textarea) {
            textarea.value = normalizedHtml;
        }
    }, { editorId, html });
}

async function switchWpEditorMode(page, editorId, mode = 'visual') {
    const normalizedMode = String(mode || 'visual').toLowerCase() === 'text' ? 'text' : 'visual';
    const wrap = page.locator(`#wp-${editorId}-wrap`).first();
    await expect(wrap).toBeVisible({ timeout: 20000 });

    if (normalizedMode === 'text') {
        const button = wrap.locator('.switch-html, #'+ editorId +'-html').first();
        await expect(button).toBeVisible({ timeout: 20000 });
        await button.click({ force: true });
        await expect(wrap).toHaveClass(/html-active/, { timeout: 20000 });
        return;
    }

    const button = wrap.locator('.switch-tmce, #'+ editorId +'-tmce').first();
    await expect(button).toBeVisible({ timeout: 20000 });
    await button.click({ force: true });
    await expect(wrap).toHaveClass(/tmce-active/, { timeout: 20000 });
}

async function insertEquationIntoWpEditor(page, editorId, options = {}) {
    const source = String(options.source || '');
    const displayMode = String(options.displayMode || 'inline').toLowerCase() === 'block' ? 'block' : 'inline';
    const mode = String(options.mode || 'visual').toLowerCase() === 'text' ? 'text' : 'visual';
    const existing = !!options.editExisting;
    const templateKey = String(options.templateKey || '').trim();
    const categoryKey = String(options.categoryKey || '').trim();
    const symbolKeys = Array.isArray(options.symbolKeys) ? options.symbolKeys.map((item) => String(item || '').trim()).filter(Boolean) : [];
    const useSuggestedDisplay = !!options.useSuggestedDisplay;

    await switchWpEditorMode(page, editorId, mode);
    await page.evaluate(({ editorId: targetEditorId, editorMode, existingWrapper }) => {
        const normalizedMode = String(editorMode || 'visual');
        const tinyMceGlobal = window.tinymce || window.tinyMCE;
        const textarea = document.getElementById(targetEditorId);
        if (normalizedMode === 'text') {
            if (textarea) {
                const rawValue = String(textarea.value || '');
                if (existingWrapper) {
                    const match = rawValue.match(/<(span|div)\b[\s\S]*?class=(["'])[^"']*\bcbt-math\b[^"']*\2[\s\S]*?<\/\1>/i);
                    if (match && typeof textarea.setSelectionRange === 'function') {
                        const start = rawValue.indexOf(match[0]);
                        const caret = start >= 0 ? start + Math.floor(match[0].length / 2) : rawValue.length;
                        textarea.focus();
                        textarea.setSelectionRange(caret, caret);
                    } else {
                        textarea.focus();
                        textarea.setSelectionRange(rawValue.length, rawValue.length);
                    }
                } else {
                    textarea.focus();
                    textarea.setSelectionRange(rawValue.length, rawValue.length);
                }
            }
            return;
        }

        const editor = tinyMceGlobal && typeof tinyMceGlobal.get === 'function'
            ? tinyMceGlobal.get(targetEditorId)
            : null;
        if (!editor) {
            return;
        }

        editor.focus();
        if (existingWrapper) {
            const wrapperNode = editor.getBody().querySelector('.cbt-math[data-cbt-math]');
            if (wrapperNode && editor.selection && typeof editor.selection.select === 'function') {
                editor.selection.select(wrapperNode, true);
                return;
            }
        }

        if (editor.selection && typeof editor.selection.select === 'function') {
            const body = editor.getBody();
            editor.selection.select(body, true);
            editor.selection.collapse(false);
        }
    }, { editorId, editorMode: mode, existingWrapper: existing });

    const trigger = page.locator(
        `[data-cbt-equation-trigger="editor"][data-cbt-equation-target="${editorId}"][data-cbt-equation-mode="${mode === 'text' ? 'text' : 'visual'}"]`
    ).first();
    await expect(trigger).toBeVisible({ timeout: 20000 });
    await trigger.click({ force: true });

    const modal = page.locator('#cbt-admin-equation-modal').first();
    await expect(modal).toBeVisible({ timeout: 20000 });

    if (categoryKey !== '') {
        await modal.locator(`[data-cbt-equation-category="${categoryKey}"]`).first().click({ force: true });
    }
    if (templateKey !== '') {
        await modal.locator(`[data-cbt-equation-template="${templateKey}"]`).first().click({ force: true });
    }
    if (source !== '') {
        await modal.locator('#cbt-admin-equation-source').fill(source);
    }
    for (const symbolKey of symbolKeys) {
        await modal.locator(`[data-cbt-equation-symbol="${symbolKey}"]`).first().click({ force: true });
    }
    if (useSuggestedDisplay) {
        const suggestionButton = modal.locator('[data-cbt-equation-use-suggestion="1"]').first();
        await expect(suggestionButton).toBeEnabled({ timeout: 20000 });
        await suggestionButton.click({ force: true });
    } else {
        await modal.locator(`input[name="cbt-admin-equation-display"][value="${displayMode}"]`).check({ force: true });
    }
    const applyButton = modal.locator('#cbt-admin-equation-apply').first();
    await expect(applyButton).toBeEnabled({ timeout: 20000 });
    await applyButton.scrollIntoViewIfNeeded();
    await applyButton.click({ force: true });
    await expect(modal).toBeHidden({ timeout: 20000 });
}

async function insertEquationIntoTfMatrixStatement(page, index, options = {}) {
    const targetIndex = Number(index || 1);
    await insertEquationIntoWpEditor(page, `cbt-tfm-statement-${targetIndex}`, options);
}

async function prepareManualQuestion(page, options = {}) {
    const subjectId = Number(options.subjectId || 0);
    const questionType = String(options.questionType || 'multiple_choice');
    const questionHtml = String(options.questionHtml || '');

    await openQuestionsFormPage(page);
    if (subjectId > 0) {
        await page.selectOption('#cbt-subject-id', String(subjectId));
    }

    const questionTypeButton = page.locator(`[data-qtype="${questionType}"]`).first();
    if (await questionTypeButton.count()) {
        await questionTypeButton.click({ force: true });
    }

    await expect(page.locator('#cbt-question-type-hidden')).toHaveValue(questionType, { timeout: 20000 });
    await setWpEditorContent(page, 'cbt_question_text_editor', questionHtml);
}

async function submitManualQuestionExpectDialog(page) {
    const manualForm = page.locator('#cbt-question-manual-form').first();
    const submitButton = manualForm.locator('input[type="submit"], button[type="submit"]').first();
    await expect(submitButton).toBeVisible({ timeout: 20000 });
    let dialogMessage = '';
    page.once('dialog', async (dialog) => {
        dialogMessage = String(dialog.message() || '');
        await dialog.dismiss();
    });

    await submitButton.click({ force: true });

    const startedAt = Date.now();
    while (dialogMessage === '' && (Date.now() - startedAt) < 5000) {
        await page.waitForTimeout(100);
    }

    if (dialogMessage === '') {
        throw new Error('Dialog validasi manual tidak muncul setelah submit.');
    }

    return {
        message() {
            return dialogMessage;
        },
        async dismiss() {
            return undefined;
        },
    };
}

async function submitManualQuestionExpectSuccess(page) {
    const manualForm = page.locator('#cbt-question-manual-form').first();
    const submitButton = manualForm.locator('input[type="submit"], button[type="submit"]').first();
    await expect(submitButton).toBeVisible({ timeout: 20000 });

    await Promise.all([
        page.waitForURL((url) => String(url).includes('cbt_msg='), { timeout: 20000 }),
        submitButton.click({ force: true }),
    ]);

    await expect(page.locator('.notice.notice-success, .updated.notice, .notice-info').first()).toBeVisible({ timeout: 20000 });
}

async function uploadQuestionsDocx(page, subjectId, filePath, questionType = 'multiple_choice') {
    const uploadPath = await ensureOfficialDocxTemplateMarker(path.resolve(filePath), questionType);
    await openQuestionsImportPage(page);
    await page.selectOption('#cbt-import-subject-id', String(subjectId));
    const normalizedQuestionType = String(questionType || 'multiple_choice');
    const typeButton = page.locator(`[data-import-type="${normalizedQuestionType}"]`).first();
    if (normalizedQuestionType === 'all') {
        await page.evaluate(() => {
            const importTypeHidden = document.getElementById('cbt-import-question-type');
            if (importTypeHidden) {
                importTypeHidden.value = 'all';
            }
        });
    } else if (await typeButton.count()) {
        await typeButton.click({ force: true });
    }
    await expect(page.locator('#cbt-import-question-type')).toHaveValue(normalizedQuestionType, { timeout: 20000 });
    await page.locator('#cbt-question-file').setInputFiles(uploadPath);
    const importForm = page.locator('form[data-cbt-questions-tab-submit="import"]').first();
    const submitButton = importForm.locator('input[type="submit"], button[type="submit"]').first();
    await expect(submitButton).toBeVisible({ timeout: 20000 });
    await Promise.all([
        page.waitForLoadState('networkidle'),
        submitButton.click({ force: true }),
    ]);
}

async function ensureOfficialDocxTemplateMarker(filePath, questionType = 'multiple_choice') {
    const resolvedPath = path.resolve(String(filePath || ''));
    if (resolvedPath === '' || path.extname(resolvedPath).toLowerCase() !== '.docx') {
        return resolvedPath;
    }

    const fixtureName = path.basename(resolvedPath).toLowerCase();
    if (fixtureName !== 'rich-content.docx' && fixtureName !== 'invalid-hardening.docx') {
        return resolvedPath;
    }

    const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'cbt-docx-marker-'));
    execFileSync('unzip', ['-qq', resolvedPath, '-d', tempDir]);

    const documentPath = path.join(tempDir, 'word', 'document.xml');
    if (!fs.existsSync(documentPath)) {
        return resolvedPath;
    }

    const originalXml = fs.readFileSync(documentPath, 'utf8');
    if (originalXml.includes('CBT_TEMPLATE: question_import_v2')) {
        return resolvedPath;
    }

    let patchedXml = '';
    if (fixtureName === 'rich-content.docx') {
        const legacyLines = extractDocxParagraphLines(originalXml);
        const convertedLines = convertLegacyFieldValueDocxToOfficialLines(legacyLines, questionType);
        if (!Array.isArray(convertedLines) || convertedLines.length === 0) {
            return resolvedPath;
        }
        patchedXml = buildSimpleDocxDocumentXml(convertedLines, originalXml);
    } else {
        const legacyLines = extractDocxParagraphLines(originalXml);
        const convertedLines = [
            'CBT_TEMPLATE: question_import_v2',
            'CATATAN_VALIDATOR: autogenerated by E2E invalid-docx compatibility shim.',
            '---',
            ...legacyLines,
        ];
        patchedXml = buildSimpleDocxDocumentXml(convertedLines, originalXml);
    }

    fs.writeFileSync(documentPath, patchedXml, 'utf8');

    const tempDocxPath = path.join(os.tmpdir(), `cbt-docx-marker-${Date.now()}-${Math.random().toString(16).slice(2)}.docx`);
    execFileSync('python3', [
        '-c',
        [
            'import os, sys, zipfile',
            'source_dir = sys.argv[1]',
            'target_zip = sys.argv[2]',
            'with zipfile.ZipFile(target_zip, "w", zipfile.ZIP_DEFLATED) as archive:',
            '    for root, _, files in os.walk(source_dir):',
            '        for file_name in files:',
            '            file_path = os.path.join(root, file_name)',
            '            archive.write(file_path, os.path.relpath(file_path, source_dir))',
        ].join('\n'),
        tempDir,
        tempDocxPath,
    ]);

    return tempDocxPath;
}

function extractDocxParagraphLines(documentXml) {
    const paragraphs = String(documentXml || '').match(/<w:p\b[\s\S]*?<\/w:p>/gi) || [];
    return paragraphs
        .map((paragraph) => {
            const text = (paragraph.match(/<w:t[^>]*>([\s\S]*?)<\/w:t>/gi) || [])
                .map((token) => {
                    const inner = String(token || '').replace(/^<w:t[^>]*>/i, '').replace(/<\/w:t>$/i, '');
                    return inner
                        .replace(/&amp;/g, '&')
                        .replace(/&lt;/g, '<')
                        .replace(/&gt;/g, '>')
                        .replace(/&quot;/g, '"')
                        .replace(/&#39;/g, '\'');
                })
                .join('')
                .trim();
            return text;
        })
        .filter((line) => line !== '');
}

function convertLegacyFieldValueDocxToOfficialLines(lines, questionType = 'multiple_choice') {
    const normalizedLines = Array.isArray(lines)
        ? lines.map((line) => String(line || '').trim()).filter((line) => line !== '')
        : [];

    if (
        normalizedLines.length === 0
        || !normalizedLines.includes('FIELD')
        || !normalizedLines.includes('VALUE')
        || !normalizedLines.includes('SOAL')
        || !normalizedLines.includes('JAWABAN')
    ) {
        return [];
    }

    const blocks = [];
    let currentBlock = [];
    let seenFirstSeparator = false;
    normalizedLines.forEach((line) => {
        if (line === '---') {
            if (seenFirstSeparator && currentBlock.length > 0) {
                blocks.push(currentBlock);
                currentBlock = [];
            }
            seenFirstSeparator = true;
            return;
        }

        if (!seenFirstSeparator) {
            return;
        }

        currentBlock.push(line);
    });

    if (currentBlock.length > 0) {
        blocks.push(currentBlock);
    }

    if (blocks.length === 0) {
        return [];
    }

    const normalizedQuestionType = String(questionType || 'multiple_choice').trim() || 'multiple_choice';
    const outputLines = [
        'CBT_TEMPLATE: question_import_v2',
        'CATATAN_VALIDATOR: autogenerated by E2E legacy DOCX compatibility shim.',
        '---',
    ];

    blocks.forEach((block) => {
        const mappedPairs = [];
        for (let index = 0; index < block.length; index += 2) {
            const rawKey = String(block[index] || '').trim();
            const rawValue = String(block[index + 1] || '').trim();
            if (rawKey === '' || rawValue === '') {
                continue;
            }
            mappedPairs.push(`${rawKey}: ${rawValue}`);
        }

        if (!mappedPairs.some((line) => /^SOAL\s*:/i.test(line))) {
            return;
        }

        outputLines.push(`JENIS_SOAL: ${normalizedQuestionType}`);
        outputLines.push(...mappedPairs);
        outputLines.push('---');
    });

    if (outputLines.length <= 3) {
        return [];
    }

    return outputLines;
}

function buildSimpleDocxDocumentXml(lines, originalXml) {
    const paragraphXml = (Array.isArray(lines) ? lines : []).map((line) => {
        const escapedLine = escapeDocxXmlText(String(line || ''));
        return `<w:p><w:r><w:t xml:space="preserve">${escapedLine}</w:t></w:r></w:p>`;
    }).join('');

    const bodyMatch = String(originalXml || '').match(/^([\s\S]*?<w:body>)[\s\S]*?(<w:sectPr\b[\s\S]*?<\/w:sectPr>\s*<\/w:body>\s*<\/w:document>\s*)$/i);
    if (bodyMatch) {
        return `${bodyMatch[1]}${paragraphXml}${bodyMatch[2]}`;
    }

    return [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">',
        '<w:body>',
        paragraphXml,
        '<w:sectPr/>',
        '</w:body>',
        '</w:document>',
    ].join('');
}

function escapeDocxXmlText(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

async function openExamPreviewPage(page, examId) {
    await page.goto(`/wp-admin/admin.php?page=cbt-exams&preview_exam_id=${Number(examId)}`);
    await expect(page.locator('.cbt-admin-exam-preview-wrap')).toBeVisible({ timeout: 20000 });
}

module.exports = {
    deleteQuestionRowByMarker,
    findQuestionRowByMarker,
    getQuestionIdByMarker,
    loginToWpAdmin,
    openExamPreviewPage,
    openQuestionEditPage,
    openQuestionsFormPage,
    openQuestionsImportPage,
    openQuestionsListPage,
    openResultsEssayTab,
    openResultsPage,
    openSetupSecurityLogPage,
    openSetupSecurityNativePage,
    prepareManualQuestion,
    insertEquationIntoTfMatrixStatement,
    insertEquationIntoWpEditor,
    setWpEditorContent,
    submitManualQuestionExpectDialog,
    submitManualQuestionExpectSuccess,
    uploadQuestionsDocx,
};
