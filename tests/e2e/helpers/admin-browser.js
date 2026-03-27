const path = require('path');
const { expect } = require('@playwright/test');

async function loginToWpAdmin(page, adminUser) {
    await page.goto('/wp-login.php');
    if (await page.locator('#wpadminbar').count()) {
        return;
    }

    await expect(page.locator('#user_login')).toBeVisible({ timeout: 20000 });
    await page.locator('#user_login').fill(String(adminUser.username || ''));
    await page.locator('#user_pass').fill(String(adminUser.password || ''));
    await page.locator('#wp-submit').click();
    await expect(page.locator('#wpadminbar')).toBeVisible({ timeout: 20000 });
}

async function openResultsPage(page, examId) {
    const nextUrl = examId && Number(examId) > 0
        ? `/wp-admin/admin.php?page=cbt-results&cbt_exam_id=${Number(examId)}`
        : '/wp-admin/admin.php?page=cbt-results';
    await page.goto(nextUrl);
    await expect(page.locator('#cbt-results-attempts-card')).toBeVisible({ timeout: 20000 });
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

async function openQuestionsFormPage(page) {
    await page.goto('/wp-admin/admin.php?page=cbt-question-bank');
    const formTab = page.locator('[data-cbt-questions-tab="form"]').first();
    await expect(formTab).toBeVisible({ timeout: 20000 });
    await formTab.click({ force: true });
    await expect(page.locator('#cbt-question-manual-form')).toBeVisible({ timeout: 20000 });
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

async function uploadQuestionsDocx(page, subjectId, filePath, questionType = 'multiple_choice') {
    await openQuestionsImportPage(page);
    await page.selectOption('#cbt-import-subject-id', String(subjectId));
    const typeButton = page.locator(`[data-import-type="${String(questionType)}"]`).first();
    if (await typeButton.count()) {
        await typeButton.click({ force: true });
    }
    await expect(page.locator('#cbt-import-question-type')).toHaveValue(String(questionType), { timeout: 20000 });
    await page.locator('#cbt-question-file').setInputFiles(path.resolve(filePath));
    const importForm = page.locator('form[data-cbt-questions-tab-submit="import"]').first();
    const submitButton = importForm.locator('input[type="submit"], button[type="submit"]').first();
    await expect(submitButton).toBeVisible({ timeout: 20000 });
    await Promise.all([
        page.waitForLoadState('networkidle'),
        submitButton.click({ force: true }),
    ]);
}

async function openExamPreviewPage(page, examId) {
    await page.goto(`/wp-admin/admin.php?page=cbt-exams&preview_exam_id=${Number(examId)}`);
    await expect(page.locator('.cbt-admin-exam-preview-wrap')).toBeVisible({ timeout: 20000 });
}

module.exports = {
    loginToWpAdmin,
    openExamPreviewPage,
    openQuestionsFormPage,
    openQuestionsImportPage,
    openResultsEssayTab,
    openResultsPage,
    openSetupSecurityLogPage,
    openSetupSecurityNativePage,
    prepareManualQuestion,
    setWpEditorContent,
    submitManualQuestionExpectDialog,
    uploadQuestionsDocx,
};
