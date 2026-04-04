const { expect } = require('@playwright/test');

const AUTH_SESSION_STORAGE_KEY = 'cbt_exam_frontend_auth_v1';
const ATTEMPT_UI_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_attempt_ui_v1_';
const QUESTION_CACHE_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_v2_';
const QUESTION_CACHE_META_LOCAL_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_meta_v2_';
const QUESTION_CACHE_ITEM_LOCAL_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_question_cache_item_v2_';
const QUESTION_CACHE_INDEXED_DB_NAME = 'cbt_exam_frontend_cache_v2';
const DOUBTFUL_SESSION_STORAGE_KEY_PREFIX = 'cbt_exam_frontend_doubtful_v1_';

function resolvedExamToken() {
    return String(process.env.CBT_E2E_EXAM_TOKEN || '').trim().toUpperCase();
}

async function waitForFrontendRoot(page) {
    await page.goto('/');
    await expect(page.locator('#cbt-login-form')).toBeVisible({ timeout: 20000 });
}

function examCardLocator(page) {
    return page.locator('[data-action="select-exam"], .cbt-exam-list .cbt-exam-item-modern, .cbt-exam-list button');
}

function escapeRegex(text) {
    return String(text || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

async function waitForAuthConfirm(page) {
    await expect(page.locator('.cbt-exam-picker-title, .cbt-confirm-stage-grid h3').first()).toBeVisible({ timeout: 20000 });
    await expect(examCardLocator(page).first()).toBeVisible({ timeout: 20000 });
    await expect(page.locator('[data-action="start-exam"], [data-action="view-result"]').first()).toBeVisible({ timeout: 20000 });
}

async function loginAsStudent(page, user) {
    await waitForFrontendRoot(page);
    await page.locator('#cbt-identifier').fill(String(user.username || ''));
    await page.locator('#cbt-password').fill(String(user.password || ''));

    const submitButton = page.locator('#cbt-login-form button[type="submit"], #cbt-login-form .cbt-button-login').first();
    const loginResponse = page.waitForResponse((response) => response.url().includes('/wp-json/cbt/v1/login'), {
        timeout: 20000,
    }).catch(() => null);

    await expect(submitButton).toBeVisible({ timeout: 20000 });
    await expect(submitButton).toBeEnabled({ timeout: 20000 });
    await submitButton.scrollIntoViewIfNeeded();
    await submitButton.click({ force: true });
    await loginResponse;
    await waitForAuthConfirm(page);
}

async function logoutFromFrontend(page) {
    const logoutButton = page.locator('[data-action="logout"]').first();
    const logoutResponse = page.waitForResponse((response) => {
        return response.url().includes('/wp-json/cbt/v1/logout');
    }, {
        timeout: 20000,
    }).catch(() => null);
    await expect(logoutButton).toBeVisible({ timeout: 20000 });
    await logoutButton.click({ force: true });
    await logoutResponse;
    await expect(page.locator('#cbt-login-form')).toBeVisible({ timeout: 20000 });
}

async function selectExamByTitle(page, examTitle) {
    const examCard = examCardLocator(page).filter({ hasText: String(examTitle || '') }).first();
    await expect(examCard).toBeVisible({ timeout: 20000 });
    await examCard.click({ force: true });
    return examCard;
}

async function fillExamTokenIfNeeded(page) {
    const token = resolvedExamToken();
    if (token === '') {
        return;
    }

    const tokenInput = page.locator('#cbt-exam-token');
    if (await tokenInput.count()) {
        await expect(tokenInput).toBeVisible({ timeout: 20000 });
        await expect(tokenInput).toBeEnabled({ timeout: 20000 });
        await tokenInput.fill(token);
        await expect(tokenInput).toHaveValue(token, { timeout: 20000 });
    }
}

async function startOrResumeAttempt(page, fixture) {
    const examTitle = fixture.exam_title || fixture.exam?.title || '';
    await selectExamByTitle(page, examTitle);
    await expect(page.locator('.cbt-confirm-selected-title').first()).toHaveText(new RegExp(escapeRegex(examTitle), 'i'), { timeout: 20000 });
    await fillExamTokenIfNeeded(page);

    const startButton = page.locator('[data-action="start-exam"]').first();
    const startResponse = page.waitForResponse((response) => response.url().includes('/wp-json/cbt/v1/start_attempt'), {
        timeout: 20000,
    }).catch(() => null);

    await expect(startButton).toBeVisible({ timeout: 20000 });
    await startButton.click({ force: true });
    await startResponse;
    await expect(page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
    await expect(page.locator('.cbt-chip-question-index .cbt-chip-value')).toBeVisible({ timeout: 20000 });
}

async function openResultFromConfirm(page, examTitle) {
    await selectExamByTitle(page, examTitle);
    const button = page.locator('[data-action="view-result"]').first();
    const response = page.waitForResponse((res) => res.url().includes('/wp-json/cbt/v1/result'), {
        timeout: 20000,
    }).catch(() => null);
    await expect(button).toBeVisible({ timeout: 20000 });
    await button.click({ force: true });
    await response;
    await expect(page.locator('.cbt-result-wrap')).toBeVisible({ timeout: 20000 });
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
    const waitMs = typeof timeoutMs === 'number' ? timeoutMs : 3600;
    const answerResponse = page.waitForResponse((response) => {
        const url = response.url();
        return url.includes('/wp-json/cbt/v1/submit_answer') || url.includes('/wp-json/cbt/v1/submit_answers_batch');
    }, { timeout: waitMs }).catch(() => null);

    await Promise.allSettled([
        answerResponse,
        waitForAttemptUiSync(page, waitMs),
    ]);
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

async function answerCurrentSingleChoice(page, optionIndex = 0) {
    const options = page.locator('[data-action="answer-single"]');
    const targetIndex = Number.isFinite(optionIndex) ? Number(optionIndex) : 0;
    const target = options.nth(targetIndex);
    await expect(target).toBeVisible({ timeout: 20000 });
    const optionId = await target.getAttribute('data-option-id');
    await target.check();
    await waitForAnswerSync(page, 3600);
    return Number(optionId) || 0;
}

async function answerCurrentMultipleChoice(page, optionIndexes = [0, 1]) {
    const normalizedIndexes = Array.from(new Set((Array.isArray(optionIndexes) ? optionIndexes : [0]).map((value) => Number(value)).filter((value) => Number.isFinite(value) && value >= 0)));
    const selectedOptionIds = [];
    for (const index of normalizedIndexes) {
        const option = page.locator('[data-action="answer-multi"]').nth(index);
        await expect(option).toBeVisible({ timeout: 20000 });
        const optionId = await option.getAttribute('data-option-id');
        await option.check();
        selectedOptionIds.push(Number(optionId) || 0);
    }
    await waitForAnswerSync(page, 3600);
    return selectedOptionIds;
}

async function fillCurrentShortAnswer(page, valueMap) {
    const values = valueMap && typeof valueMap === 'object' ? valueMap : { A: 'jawaban' };
    const entries = Object.entries(values);
    for (const [shortKey, value] of entries) {
        const field = page.locator(`[data-action="answer-short"][data-short-key="${String(shortKey)}"]`).first();
        await expect(field).toBeVisible({ timeout: 20000 });
        await field.fill(String(value));
    }
    await waitForAnswerSync(page, 3600);
}

async function fillCurrentEssay(page, text) {
    const field = page.locator('[data-action="answer-text"]').first();
    await expect(field).toBeVisible({ timeout: 20000 });
    await field.fill(String(text || 'Jawaban essay flow check'));
    await waitForAnswerSync(page, 3600);
}

async function clickNextQuestion(page) {
    const currentNumber = await getCurrentQuestionNumber(page);
    await page.locator('[data-action="next"]').first().click({ force: true });
    await expect(page.locator('.cbt-chip-question-index .cbt-chip-value')).toHaveText(String(currentNumber + 1), { timeout: 20000 });
    await waitForAttemptUiSync(page, 2200);
}

async function clickPreviousQuestion(page) {
    const currentNumber = await getCurrentQuestionNumber(page);
    await page.locator('[data-action="prev"]').first().click({ force: true });
    await expect(page.locator('.cbt-chip-question-index .cbt-chip-value')).toHaveText(String(Math.max(1, currentNumber - 1)), { timeout: 20000 });
    await waitForAttemptUiSync(page, 2200);
}

async function jumpToQuestion(page, questionNumber) {
    const targetNumber = Number(questionNumber) || 1;
    const targetIndex = Math.max(0, targetNumber - 1);
    const targetButton = page.locator(`[data-action="jump"][data-index="${targetIndex}"]`).first();
    await expect(targetButton).toBeVisible({ timeout: 20000 });
    await targetButton.scrollIntoViewIfNeeded();
    await targetButton.click({ force: true });
    await expect(page.locator('.cbt-chip-question-index .cbt-chip-value')).toHaveText(String(targetNumber), { timeout: 20000 });
    await waitForAttemptUiSync(page, 2200);
}

async function getQuestionNavCount(page) {
    return await page.locator('[data-action="jump"]').count();
}

async function jumpToLastQuestion(page) {
    const navCount = await getQuestionNavCount(page);
    const targetNumber = Math.max(1, navCount);
    await jumpToQuestion(page, targetNumber);
    return targetNumber;
}

async function toggleCurrentQuestionDoubtful(page) {
    const button = page.locator('[data-action="toggle-doubtful"]').first();
    await expect(button).toBeVisible({ timeout: 20000 });
    await button.click({ force: true });
    await waitForAttemptUiSync(page, 2200);
    return getCurrentQuestionId(page);
}

async function collectAndFinish(page) {
    const collectButton = page.locator('[data-action="collect"], [data-action="finish"]').first();

    if (!(await collectButton.isVisible().catch(() => false))) {
        await jumpToLastQuestion(page);
    }

    await expect(collectButton).toBeVisible({ timeout: 20000 });
    await collectButton.click({ force: true });
    await page.locator('[data-action="finish-confirm-submit"]').first().click({ force: true });
}

async function waitForResultShell(page) {
    await expect(page.locator('.cbt-result-wrap')).toBeVisible({ timeout: 20000 });
}

async function readPersistedAuthSession(page) {
    return page.evaluate((storageKey) => {
        try {
            const raw = window.sessionStorage.getItem(storageKey) || window.localStorage.getItem(storageKey);
            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return null;
        }
    }, AUTH_SESSION_STORAGE_KEY);
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

        return { local, session };
    });
}

async function openRehydratedPage(browser, baseURL, storageSnapshot) {
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
    return { context, page };
}

async function fetchWithAuth(page, route, options = {}) {
    return page.evaluate(async (payload) => {
        const raw = window.sessionStorage.getItem(payload.storageKey) || window.localStorage.getItem(payload.storageKey);
        const parsed = raw ? JSON.parse(raw) : null;
        const token = parsed && parsed.token ? String(parsed.token) : '';
        const requestInit = {
            method: String(payload.method || 'GET'),
            headers: {
                'Content-Type': 'application/json',
            },
        };

        if (token) {
            requestInit.headers.Authorization = `Bearer ${token}`;
        }

        if (payload.body !== undefined && payload.body !== null) {
            requestInit.body = JSON.stringify(payload.body);
        }

        const response = await window.fetch(payload.route, requestInit);
        let data = null;
        try {
            data = await response.json();
        } catch (error) {
            data = null;
        }

        return {
            ok: response.ok,
            status: response.status,
            data,
        };
    }, {
        storageKey: AUTH_SESSION_STORAGE_KEY,
        route,
        method: options.method || 'GET',
        body: Object.prototype.hasOwnProperty.call(options, 'body') ? options.body : null,
    });
}

async function corruptLocalAttemptSnapshot(page, fixture, attemptId, snapshot) {
    await page.evaluate((payload) => {
        const storageKey = String(payload.attemptUiPrefix) + String(payload.userId) + '_' + String(payload.attemptId);
        window.sessionStorage.setItem(storageKey, payload.rawValue);
    }, {
        attemptUiPrefix: ATTEMPT_UI_SESSION_STORAGE_KEY_PREFIX,
        userId: Number(fixture.user_id || fixture.user?.user_id) || 0,
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
        userId: Number(fixture.user_id || fixture.user?.user_id) || 0,
        attemptId: Number(attemptId) || 0,
    });
}

module.exports = {
    AUTH_SESSION_STORAGE_KEY,
    answerCurrentMultipleChoice,
    answerCurrentSingleChoice,
    captureBrowserStorage,
    clearLocalQuestionCache,
    clickNextQuestion,
    clickPreviousQuestion,
    collectAndFinish,
    corruptLocalAttemptSnapshot,
    fetchWithAuth,
    fillCurrentEssay,
    fillCurrentShortAnswer,
    getCheckedSingleChoiceOptionId,
    getCurrentQuestionId,
    getCurrentQuestionNumber,
    getQuestionNavCount,
    jumpToLastQuestion,
    jumpToQuestion,
    loginAsStudent,
    logoutFromFrontend,
    openRehydratedPage,
    openResultFromConfirm,
    readPersistedAuthSession,
    selectExamByTitle,
    startOrResumeAttempt,
    toggleCurrentQuestionDoubtful,
    waitForAnswerSync,
    waitForAttemptUiSync,
    waitForAuthConfirm,
    waitForFrontendRoot,
    waitForResultShell,
};
