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
    await page.goto('/wp-admin/admin.php?page=cbt-setup#security-log');
    const tabButton = page.locator('#cbt-setup-tab-security-log').first();
    await expect(tabButton).toBeVisible({ timeout: 20000 });
    await tabButton.click({ force: true });
    await expect(page.locator('#cbt-setup-panel-security-log')).toBeVisible({ timeout: 20000 });
}

async function openQuestionsImportPage(page) {
    await page.goto('/wp-admin/admin.php?page=cbt-question-bank');
    const importTab = page.locator('[data-cbt-questions-tab="import"]').first();
    await expect(importTab).toBeVisible({ timeout: 20000 });
    await importTab.click({ force: true });
    await expect(page.locator('#cbt-import-subject-id')).toBeVisible({ timeout: 20000 });
}

async function uploadQuestionsDocx(page, subjectId, filePath, questionType = 'multiple_choice') {
    await openQuestionsImportPage(page);
    await page.selectOption('#cbt-import-subject-id', String(subjectId));
    const typeSelect = page.locator('select[name="question_type"], #cbt-import-question-type').first();
    if (await typeSelect.count()) {
        await typeSelect.selectOption(String(questionType));
    }
    await page.locator('#cbt-question-file').setInputFiles(path.resolve(filePath));
    await page.locator('[data-cbt-questions-tab-submit="import"], input[type="submit"][value="Import Questions"]').first().click({ force: true });
    await page.waitForLoadState('networkidle');
}

async function openExamPreviewPage(page, examId) {
    await page.goto(`/wp-admin/admin.php?page=cbt-exams&preview_exam_id=${Number(examId)}`);
    await expect(page.locator('.cbt-admin-exam-preview-wrap')).toBeVisible({ timeout: 20000 });
}

module.exports = {
    loginToWpAdmin,
    openExamPreviewPage,
    openQuestionsImportPage,
    openResultsEssayTab,
    openResultsPage,
    openSetupSecurityLogPage,
    uploadQuestionsDocx,
};
