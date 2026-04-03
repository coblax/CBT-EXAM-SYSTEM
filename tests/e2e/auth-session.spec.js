const { test, expect } = require('@playwright/test');
const {
    ageE2ELoginSession,
    getE2EFixture,
    getLatestE2EAttempt,
    resetE2EFixture,
} = require('./helpers/e2e-fixture');
const {
    answerCurrentSingleChoice,
    captureBrowserStorage,
    fetchWithAuth,
    loginAsStudent,
    logoutFromFrontend,
    openRehydratedPage,
    readPersistedAuthSession,
    startOrResumeAttempt,
    waitForAuthConfirm,
} = require('./helpers/frontend-browser');

test.describe.configure({ mode: 'serial' });

function decodeTokenSessionKey(token) {
    const rawToken = String(token || '').trim();
    if (rawToken === '') {
        return '';
    }

    const segments = rawToken.split('.');
    if (segments.length < 2 || !segments[1]) {
        return '';
    }

    try {
        const normalized = String(segments[1])
            .replace(/-/g, '+')
            .replace(/_/g, '/')
            .padEnd(Math.ceil(segments[1].length / 4) * 4, '=');
        const decoded = JSON.parse(Buffer.from(normalized, 'base64').toString('utf8'));
        return String(decoded?.data?.session_key || '');
    } catch (error) {
        return '';
    }
}

async function prepareAuthAttempt(page, fixture) {
    await loginAsStudent(page, fixture.user);
    await startOrResumeAttempt(page, fixture);
    await answerCurrentSingleChoice(page, 0);
    const attempt = getLatestE2EAttempt('auth_session', 'primary_student');
    expect(attempt && attempt.id).toBeTruthy();
    return attempt;
}

test.describe('Auth & Session flow check', () => {
    test.setTimeout(120000);

    test.beforeEach(() => {
        resetE2EFixture('auth_session', 'primary_student');
        resetE2EFixture('auth_session', 'secondary_student');
    });

    test('Auth Flow: second browser login revokes previous session', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('auth_session', 'primary_student');
        await test.step('Login browser pertama dengan akun siswa seed', async () => {
            await loginAsStudent(page, fixture.user);
            await waitForAuthConfirm(page);
        });

        await test.step('Age session aktif agar login baru bisa melakukan rotasi sesi secara deterministik', async () => {
            ageE2ELoginSession('primary_student', 180);
        });

        const secondContext = await browser.newContext();
        const secondPage = await secondContext.newPage();

        try {
            await test.step('Login dari browser kedua lalu biarkan token baru menjadi sesi aktif', async () => {
                await loginAsStudent(secondPage, fixture.user);
                await waitForAuthConfirm(secondPage);
            });

            await test.step('Browser pertama tidak bisa lagi memakai sesi lama', async () => {
                const sessionResponse = await fetchWithAuth(page, '/wp-json/cbt/v1/session');
                expect(sessionResponse.status).toBe(401);
                expect(String(sessionResponse.data && sessionResponse.data.code ? sessionResponse.data.code : '')).toBe('session_revoked');
            });
        } finally {
            await secondContext.close();
        }
    });

    test('Auth Flow: logout then login rotates session token', async ({ page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('auth_session', 'primary_student');
        let firstSession = null;

        await test.step('Login pertama menghasilkan auth payload persisted', async () => {
            await loginAsStudent(page, fixture.user);
            firstSession = await readPersistedAuthSession(page);
            expect(firstSession && firstSession.token).toBeTruthy();
        });

        await test.step('Logout dari frontend membersihkan sesi aktif', async () => {
            await logoutFromFrontend(page);
        });

        await test.step('Login ulang menghasilkan token dan session key baru', async () => {
            await loginAsStudent(page, fixture.user);
            const secondSession = await readPersistedAuthSession(page);
            expect(secondSession && secondSession.token).toBeTruthy();
            const firstToken = String(firstSession && firstSession.token ? firstSession.token : '');
            const secondToken = String(secondSession && secondSession.token ? secondSession.token : '');
            expect(secondToken).not.toBe(firstToken);
            expect(decodeTokenSessionKey(secondToken)).toBeTruthy();
            expect(decodeTokenSessionKey(secondToken)).not.toBe(decodeTokenSessionKey(firstToken));
        });
    });

    test('Auth Flow: cross-user attempt access is forbidden', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const primaryFixture = getE2EFixture('auth_session', 'primary_student');
        const secondaryFixture = getE2EFixture('auth_session', 'secondary_student');
        const attempt = await test.step('Siswa utama membuat attempt aktif pada fixture session', async () => {
            return prepareAuthAttempt(page, primaryFixture);
        });

        const secondContext = await browser.newContext();
        const secondPage = await secondContext.newPage();

        try {
            await test.step('Siswa kedua login dan mencoba mengakses attempt milik siswa utama', async () => {
                await loginAsStudent(secondPage, secondaryFixture.user);
                const forbiddenResponse = await fetchWithAuth(
                    secondPage,
                    `/wp-json/cbt/v1/questions?exam_id=${Number(primaryFixture.exam.exam_id)}&attempt_id=${Number(attempt.id)}`
                );
                expect(forbiddenResponse.status).toBe(403);
                expect(String(forbiddenResponse.data && forbiddenResponse.data.code ? forbiddenResponse.data.code : '')).toBe('forbidden');
            });
        } finally {
            await secondContext.close();
        }
    });

    test('Auth Flow: reopen resumes attempt with valid session', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('auth_session', 'primary_student');
        await test.step('Login dan siapkan attempt aktif yang valid', async () => {
            await prepareAuthAttempt(page, fixture);
        });
        const storageSnapshot = await test.step('Tangkap storage auth dan exam sebelum reopen', async () => {
            return captureBrowserStorage(page);
        });

        const reopened = await openRehydratedPage(browser, baseURL, storageSnapshot);
        try {
            await test.step('Context baru langsung bootstrap ke shell exam yang sama', async () => {
                await expect(reopened.page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
            });
        } finally {
            await reopened.context.close();
        }
    });

    test('Auth Flow: dual browser relogin invalidates old session deterministically', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('auth_session', 'primary_student');
        await test.step('Browser pertama login dan membuka daftar exam', async () => {
            await loginAsStudent(page, fixture.user);
        });
        await test.step('Session pertama dibuat stale agar login kedua mengganti active session meta', async () => {
            ageE2ELoginSession('primary_student', 180);
        });

        const secondContext = await browser.newContext();
        const secondPage = await secondContext.newPage();
        try {
            await test.step('Browser kedua login lalu browser pertama kehilangan validitas token', async () => {
                await loginAsStudent(secondPage, fixture.user);
                const sessionResponse = await fetchWithAuth(page, '/wp-json/cbt/v1/session');
                expect(sessionResponse.status).toBe(401);
                expect(String(sessionResponse.data && sessionResponse.data.code ? sessionResponse.data.code : '')).toBe('session_revoked');
            });
        } finally {
            await secondContext.close();
        }
    });

    test('Auth Flow: valid session bootstrap can resume without auth guard', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('auth_session', 'primary_student');
        const attempt = await test.step('Siswa login dan membuat attempt aktif untuk bootstrap', async () => {
            return prepareAuthAttempt(page, fixture);
        });
        const storageSnapshot = await captureBrowserStorage(page);

        const reopened = await openRehydratedPage(browser, baseURL, storageSnapshot);
        try {
            await test.step('Session endpoint dan shell exam tetap bisa diakses dengan token yang valid', async () => {
                const sessionResponse = await fetchWithAuth(reopened.page, `/wp-json/cbt/v1/session?attempt_id=${Number(attempt.id)}`);
                expect(sessionResponse.status).toBe(200);
                await expect(reopened.page.locator('[data-cbt-exam-shell="1"]')).toBeVisible({ timeout: 20000 });
            });
        } finally {
            await reopened.context.close();
        }
    });
});
