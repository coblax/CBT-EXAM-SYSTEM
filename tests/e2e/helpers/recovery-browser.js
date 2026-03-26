const { expect } = require('@playwright/test');

const AUTH_SESSION_STORAGE_KEY = 'cbt_exam_frontend_auth_v1';
const ATTEMPT_UI_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_attempt_ui_v1_';
const QUESTION_CACHE_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_v2_';
const QUESTION_CACHE_META_LOCAL_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_meta_v2_';
const QUESTION_CACHE_ITEM_LOCAL_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_item_v2_';
const QUESTION_CACHE_INDEXED_DB_NAME = 'cbt_exam_frontend_cache_v2';
const DOUBTFUL_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_doubtful_v1_';
const RESOLVED_EXAM_TOKEN = String(process.env.CBT_E2E_EXAM_TOKEN || '').trim().toUpperCase();

async function waitForFrontendRoot(page) {
    await page.goto('/');
    await expect(page.locator('#cbt-login-form')).toBeVisible({ timeout: 20000 });
}

async function loginAsRecoveryStudent(page, fixture) {
    await waitForFrontendRoot(page);
    await page.locator('#cbt-identifier').fill(String(fixture.username || ''));
    await page.locator('#cbt-password').fill(String(fixture.password || ''));
    const submitButton = page.locator('#cbt-login-form button[type="submit"], #cbt-login-form .cbt-button-login').first();
    const loginResponse = page.waitForResponse((response) => {
        return response.url().includes('/wp-json/cbt/v1/login');
    }, { timeout: 20000 }).catch(() => null);

    await expect(submitButton).toBeVisible({ timeout: 20000 });
    await expect(submitButton).toBeEnabled({ timeout: 20000 });
    await submitButton.scrollIntoViewIfNeeded();
    await submitButton.click({ force: true });
    await loginResponse;
    await expect(page.locator('[data-action="select-exam"]').filter({ hasText: String(fixture.exam_title || '') }).first()).toBeVisible({ timeout: 20000 });
    await expect(page.locator('[data-action="start-exam"]')).toBeVisible({ timeout: 20000 });
}

async function selectRecoveryExam(page, fixture) {
    const examCard = page.locator('[data-action="select-exam"]').filter({ hasText: String(fixture.exam_title || '') }).first();
    await expect(examCard).toBeVisible({ timeout: 20000 });
    await examCard.click();
}

async function startOrResumeRecoveryAttempt(page, fixture) {
    await selectRecoveryExam(page, fixture);
    const shell = page.locator('[data-cbt-exam-shell="1"]');
    const tokenInput = page.locator('#cbt-exam-token');

    if (RESOLVED_EXAM_TOKEN !== '' && await tokenInput.count()) {
        const tokenVisible = await tokenInput.isVisible().catch(() => false);
        const tokenDisabled = await tokenInput.isDisabled().catch(() => false);
        if (tokenVisible && !tokenDisabled) {
            await tokenInput.fill(RESOLVED_EXAM_TOKEN);
        }
    }

    const startResponse = page.waitForResponse((response) => {
        return response.url().includes('/wp-json/cbt/v1/start_attempt');
    }, { timeout: 20000 }).catch(() => null);

    await page.locator('[data-action="start-exam"]').first().click({ force: true });
    await startResponse;
    await expect(shell).toBeVisible({ timeout: 20000 });
    await expect(page.locator('.cbt-chip-question-index .cbt-chip-value')).toBeVisible({ timeout: 20000 });
}

async function waitForAttemptUiSync(page, timeoutMs) {
    const waitMs = typeof timeoutMs === 'number' ? timeoutMs : 2200;
    const uiStateResponse = page.waitForResponse((response) => {
        return response.url().includes('/wp-json/cbt/v1/ui_state') && response.request().method() === 'POST';
    }, { timeout: waitMs }).catch(() => null);

    await Promise.allSettled([
        uiStateResponse,
        page.waitForTimeout(waitMs),
    ]);
}

async function waitForAnswerSync(page, timeoutMs) {
    const waitMs = typeof timeoutMs === 'number' ? timeoutMs : 3200;
    const answerResponse = page.waitForResponse((response) => {
        const url = response.url();
        return url.includes('/wp-json/cbt/v1/submit_answer') || url.includes('/wp-json/cbt/v1/submit_answers_batch');
    }, { timeout: waitMs }).catch(() => null);

    await Promise.allSettled([
        answerResponse,
        waitForAttemptUiSync(page, waitMs),
    ]);
}

async function answerCurrentSingleChoice(page, optionIndex) {
    const choiceIndex = Number.isFinite(optionIndex) ? Number(optionIndex) : 0;
    const options = page.locator('[data-action="answer-single"]');
    await expect(options.first()).toBeVisible({ timeout: 20000 });
    const target = options.nth(choiceIndex);
    const optionId = await target.getAttribute('data-option-id');
    await target.check();
    await waitForAnswerSync(page, 3600);
    return Number(optionId) || 0;
}

async function clickNextQuestion(page) {
    const currentNumber = await getCurrentQuestionNumber(page);
    await page.locator('[data-action="next"]').first().click({ force: true });
    await expect(page.locator('.cbt-chip-question-index .cbt-chip-value')).toHaveText(String(currentNumber + 1), { timeout: 20000 });
    await waitForAttemptUiSync(page, 2200);
}

async function jumpToQuestion(page, questionNumber) {
    const targetNumber = Number(questionNumber) || 1;
    const targetIndex = Math.max(0, targetNumber - 1);
    const targetButton = page.locator(`[data-action="jump"][data-index="${targetIndex}"]`).first();

    await expect(targetButton).toBeVisible({ timeout: 20000 });
    await targetButton.click({ force: true });
    await expect(page.locator('.cbt-chip-question-index .cbt-chip-value')).toHaveText(String(targetNumber), { timeout: 20000 });
    await waitForAttemptUiSync(page, 2200);
}

async function toggleCurrentQuestionDoubtful(page) {
    const button = page.locator('[data-action="toggle-doubtful"]').first();
    await expect(button).toBeVisible({ timeout: 20000 });
    await button.click({ force: true });
    await expect(button).toHaveClass(/is-active/, { timeout: 20000 });
    await waitForAttemptUiSync(page, 2200);
    return getCurrentQuestionId(page);
}

async function getCurrentQuestionNumber(page) {
    const value = await page.locator('.cbt-chip-question-index .cbt-chip-value').textContent();
    return Number(String(value || '').trim()) || 0;
}

async function getCurrentQuestionId(page) {
    const raw = await page.locator('[data-action="toggle-doubtful"]').first().getAttribute('data-qid');
    return Number(raw) || 0;
}

async function getCheckedSingleChoiceOptionId(page) {
    const checked = page.locator('[data-action="answer-single"]:checked').first();
    await expect(checked).toBeVisible({ timeout: 20000 });
    const optionId = await checked.getAttribute('data-option-id');
    return Number(optionId) || 0;
}

async function assertExamShellRestored(page, expectedQuestionNumber, expectedOptionId, expectedDoubtful) {
    await expect(page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
    await expect(page.locator('.cbt-chip-question-index .cbt-chip-value')).toHaveText(String(expectedQuestionNumber), { timeout: 20000 });
    await expect(page.locator(`[data-action="answer-single"][data-option-id="${expectedOptionId}"]`)).toBeChecked({ timeout: 20000 });

    const doubtfulButton = page.locator('[data-action="toggle-doubtful"]').first();
    if (expectedDoubtful) {
        await expect(doubtfulButton).toHaveClass(/is-active/, { timeout: 20000 });
    } else {
        await expect(doubtfulButton).not.toHaveClass(/is-active/, { timeout: 20000 });
    }
}

async function captureBrowserStorage(page) {
    return page.evaluate(() => {
        const session = {};
        const local = {};

        for (let index = 0; index < window.sessionStorage.length; index += 1) {
            const key = window.sessionStorage.key(index);
            if (!key) {
                continue;
            }
            session[key] = window.sessionStorage.getItem(key) || '';
        }

        for (let index = 0; index < window.localStorage.length; index += 1) {
            const key = window.localStorage.key(index);
            if (!key) {
                continue;
            }
            local[key] = window.localStorage.getItem(key) || '';
        }

        return {
            session,
            local,
        };
    });
}

async function openRehydratedRecoveryPage(browser, baseURL, storageSnapshot) {
    const context = await browser.newContext();
    await context.addInitScript((snapshot) => {
        Object.keys(snapshot.local || {}).forEach((key) => {
            window.localStorage.setItem(key, String(snapshot.local[key] || ''));
        });
        Object.keys(snapshot.session || {}).forEach((key) => {
            window.sessionStorage.setItem(key, String(snapshot.session[key] || ''));
        });
    }, storageSnapshot);

    const page = await context.newPage();
    await page.goto(new URL('/', String(baseURL || 'http://localhost/')).toString());
    return {
        context,
        page,
    };
}

async function corruptLocalAttemptSnapshot(page, fixture, attemptId, snapshot) {
    await page.evaluate((payload) => {
        const storageKey = String(payload.attemptUiPrefix) + String(payload.userId) + '_' + String(payload.attemptId);
        window.sessionStorage.setItem(storageKey, payload.rawValue);
    }, {
        attemptUiPrefix: ATTEMPT_UI_SESSION_STORAGE_KEY_PREFIX,
        userId: Number(fixture.user_id) || 0,
        attemptId: Number(attemptId) || 0,
        rawValue: typeof snapshot === 'string' ? snapshot : JSON.stringify(snapshot),
    });
}

async function clearLocalQuestionCache(page, fixture, attemptId) {
    await page.evaluate(async (payload) => {
        const safeUserId = Number(payload.userId) || 0;
        const safeAttemptId = Number(payload.attemptId) || 0;
        const questionCacheSessionKey = String(payload.questionCacheSessionPrefix) + String(safeUserId) + '_' + String(safeAttemptId);
        const questionCacheMetaLocalKey = String(payload.questionCacheMetaPrefix) + String(safeUserId) + '_' + String(safeAttemptId);
        const questionCacheLocalItemPrefix = String(payload.questionCacheItemPrefix) + String(safeUserId) + '_' + String(safeAttemptId) + '_';
        const doubtfulKey = String(payload.doubtfulPrefix) + String(safeUserId) + '_' + String(safeAttemptId);

        window.sessionStorage.removeItem(questionCacheSessionKey);
        window.sessionStorage.removeItem(questionCacheSessionKey + '__meta');
        window.sessionStorage.removeItem(doubtfulKey);

        const sessionKeys = [];
        for (let index = 0; index < window.sessionStorage.length; index += 1) {
            const key = window.sessionStorage.key(index);
            if (key && key.indexOf(questionCacheSessionKey + '__item_') === 0) {
                sessionKeys.push(key);
            }
        }
        sessionKeys.forEach((key) => window.sessionStorage.removeItem(key));

        window.localStorage.removeItem(questionCacheMetaLocalKey);
        const localKeys = [];
        for (let index = 0; index < window.localStorage.length; index += 1) {
            const key = window.localStorage.key(index);
            if (key && key.indexOf(questionCacheLocalItemPrefix) === 0) {
                localKeys.push(key);
            }
        }
        localKeys.forEach((key) => window.localStorage.removeItem(key));

        await new Promise((resolve) => {
            const request = window.indexedDB.deleteDatabase(String(payload.indexedDbName));
            request.onsuccess = function () { resolve(); };
            request.onerror = function () { resolve(); };
            request.onblocked = function () { resolve(); };
        });
    }, {
        doubtfulPrefix: DOUBTFUL_SESSION_STORAGE_KEY_PREFIX,
        indexedDbName: QUESTION_CACHE_INDEXED_DB_NAME,
        questionCacheItemPrefix: QUESTION_CACHE_ITEM_LOCAL_STORAGE_KEY_PREFIX,
        questionCacheMetaPrefix: QUESTION_CACHE_META_LOCAL_STORAGE_KEY_PREFIX,
        questionCacheSessionPrefix: QUESTION_CACHE_SESSION_STORAGE_KEY_PREFIX,
        userId: Number(fixture.user_id) || 0,
        attemptId: Number(attemptId) || 0,
    });
}

async function prepareRecoveryAttempt(page, fixture) {
    await loginAsRecoveryStudent(page, fixture);
    await startOrResumeRecoveryAttempt(page, fixture);
    const firstAnswerOptionId = await answerCurrentSingleChoice(page, 0);
    await clickNextQuestion(page);
    const secondAnswerOptionId = await answerCurrentSingleChoice(page, 0);
    const currentQuestionId = await toggleCurrentQuestionDoubtful(page);

    return {
        currentQuestionId,
        currentQuestionNumber: await getCurrentQuestionNumber(page),
        firstAnswerOptionId,
        secondAnswerOptionId,
    };
}

module.exports = {
    AUTH_SESSION_STORAGE_KEY,
    assertExamShellRestored,
    captureBrowserStorage,
    clearLocalQuestionCache,
    corruptLocalAttemptSnapshot,
    getCheckedSingleChoiceOptionId,
    getCurrentQuestionId,
    getCurrentQuestionNumber,
    jumpToQuestion,
    loginAsRecoveryStudent,
    openRehydratedRecoveryPage,
    prepareRecoveryAttempt,
    selectRecoveryExam,
    startOrResumeRecoveryAttempt,
    toggleCurrentQuestionDoubtful,
    waitForAttemptUiSync,
    waitForFrontendRoot,
};
