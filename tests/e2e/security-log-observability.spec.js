const { test, expect } = require('@playwright/test');
const {
    clearE2ESecurityLogs,
    getE2ECatalog,
    getE2EFixture,
    resetE2EFixture,
    setE2ESecurityConfig,
} = require('./helpers/e2e-fixture');
const {
    loginAsStudent,
    startOrResumeAttempt,
} = require('./helpers/frontend-browser');
const {
    loginToWpAdmin,
    openSetupSecurityLogPage,
} = require('./helpers/admin-browser');
const { waitForCondition } = require('./helpers/flow-utils');

test.describe.configure({ mode: 'serial' });

async function prepareSecurityAttempt(page, fixture) {
    resetE2EFixture('security_log_observability', fixture.user.user_key || fixture.user.userKey || 'primary_student');
    await loginAsStudent(page, fixture.user);
    await startOrResumeAttempt(page, fixture);
}

async function triggerClipboardBlocked(page, count = 1) {
    for (let index = 0; index < count; index += 1) {
        const eventResponse = page.waitForResponse((response) => {
            return response.url().includes('/wp-json/cbt/v1/security_event') && response.request().method() === 'POST';
        }, {
            timeout: 20000,
        }).catch(() => null);

        await page.evaluate(() => {
            document.dispatchEvent(new Event('copy', {
                bubbles: true,
                cancelable: true,
            }));
        });

        const response = await eventResponse;
        expect(response).not.toBeNull();
        if (response) {
            expect(response.ok()).toBeTruthy();
        }
        await page.waitForTimeout(250);
    }
}

async function openAdminSecurityPanel(page, adminUser) {
    await loginToWpAdmin(page, adminUser);
    await openSetupSecurityLogPage(page);
}

async function waitForSecurityRow(page, textNeedle) {
    return waitForCondition(async () => {
        const row = page.locator('[data-security-log-row]').filter({ hasText: String(textNeedle || '') }).first();
        if (await row.count()) {
            const visible = await row.isVisible().catch(() => false);
            if (visible) {
                return row;
            }
        }

        await page.reload({ waitUntil: 'networkidle' });
        return null;
    }, {
        timeoutMs: 20000,
        intervalMs: 800,
        errorMessage: `Security log row "${textNeedle}" tidak muncul di panel observability.`,
    });
}

async function waitForMustWatchCard(page, studentName) {
    return waitForCondition(async () => {
        const card = page.locator('[data-security-log-focus-card]').filter({ hasText: String(studentName || '') }).first();
        if (await card.count()) {
            const visible = await card.isVisible().catch(() => false);
            if (visible) {
                return card;
            }
        }

        await page.reload({ waitUntil: 'networkidle' });
        return null;
    }, {
        timeoutMs: 20000,
        intervalMs: 800,
        errorMessage: `Must Watch card untuk "${studentName}" tidak muncul.`,
    });
}

test.describe('Security Log & Observability flow check', () => {
    test.setTimeout(150000);

    test.beforeEach(() => {
        clearE2ESecurityLogs();
        setE2ESecurityConfig({
            block_copy_paste: 1,
            detect_idle_during_exam: 1,
            force_fullscreen: 0,
            idle_threshold_minutes: 5,
            log_security_events: 1,
        });
    });

    test('Security Flow: frontend clipboard event appears in observability panel', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('security_log_observability', 'primary_student');
        const catalog = getE2ECatalog();

        await test.step('Siswa membuka fixture security dan memicu clipboard blocked dari browser', async () => {
            await prepareSecurityAttempt(page, fixture);
            await triggerClipboardBlocked(page, 1);
        });

        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        try {
            await test.step('Panel observability admin menampilkan baris Clipboard diblokir untuk attempt aktif', async () => {
                await openAdminSecurityPanel(adminPage, catalog.users.admin_seed);
                const row = await waitForSecurityRow(adminPage, 'Clipboard diblokir');
                await expect(row).toContainText(fixture.user.display_name || fixture.user.username);
                await expect(row).toContainText(fixture.exam.title);
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Security Flow: admin reset login creates follow-up security log entry', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('security_log_observability', 'primary_student');
        const catalog = getE2ECatalog();

        await test.step('Siswa memicu beberapa clipboard blocked agar attempt masuk Must Watch', async () => {
            await prepareSecurityAttempt(page, fixture);
            await triggerClipboardBlocked(page, 3);
        });

        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        try {
            await test.step('Admin menjalankan Reset Login dari Must Watch card', async () => {
                await openAdminSecurityPanel(adminPage, catalog.users.admin_seed);
                const card = await waitForMustWatchCard(adminPage, fixture.user.display_name || fixture.user.username);
                adminPage.once('dialog', (dialog) => dialog.accept());
                await card.getByRole('button', { name: 'Reset Login' }).click({ force: true });
                await adminPage.waitForLoadState('networkidle');
            });

            await test.step('Histori observability mencatat follow-up event Reset login admin', async () => {
                const row = await waitForSecurityRow(adminPage, 'Reset login admin');
                await expect(row).toContainText(fixture.user.display_name || fixture.user.username);
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Security Flow: must-watch ordering prioritizes the higher-risk attempt', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const primaryFixture = getE2EFixture('security_log_observability', 'primary_student');
        const secondaryFixture = getE2EFixture('security_log_observability', 'secondary_student');
        const catalog = getE2ECatalog();

        await test.step('Siswa utama memicu skor risiko lebih tinggi daripada siswa kedua', async () => {
            await prepareSecurityAttempt(page, primaryFixture);
            await triggerClipboardBlocked(page, 4);
        });

        const secondaryContext = await browser.newContext();
        const secondaryPage = await secondaryContext.newPage();
        try {
            await test.step('Siswa kedua juga masuk Must Watch tetapi dengan skor lebih rendah', async () => {
                await prepareSecurityAttempt(secondaryPage, secondaryFixture);
                await triggerClipboardBlocked(secondaryPage, 3);
            });
        } finally {
            await secondaryContext.close();
        }

        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        try {
            await test.step('Urutan sort skor tertinggi menempatkan siswa utama di kartu pertama', async () => {
                await openAdminSecurityPanel(adminPage, catalog.users.admin_seed);
                await waitForMustWatchCard(adminPage, primaryFixture.user.display_name || primaryFixture.user.username);
                await adminPage.locator('[data-security-log-watch-sort="score"]').click({ force: true });
                const firstCard = adminPage.locator('[data-security-log-focus-card]').first();
                await expect(firstCard).toContainText(primaryFixture.user.display_name || primaryFixture.user.username);
                await expect(firstCard).toContainText('Skor 8');
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Security Flow: multiple events on one attempt stay aggregated with stable indicators', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('security_log_observability', 'primary_student');
        const catalog = getE2ECatalog();

        await test.step('Satu attempt memicu beberapa clipboard blocked berulang', async () => {
            await prepareSecurityAttempt(page, fixture);
            await triggerClipboardBlocked(page, 4);
        });

        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        try {
            await test.step('Must Watch card menampilkan agregasi indikator yang stabil untuk attempt yang sama', async () => {
                await openAdminSecurityPanel(adminPage, catalog.users.admin_seed);
                const card = await waitForMustWatchCard(adminPage, fixture.user.display_name || fixture.user.username);
                await expect(card).toContainText('4x Clipboard diblokir');
                await expect(card).toContainText(fixture.exam.title);
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Security Flow: frontend event remains visible after admin refresh', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('security_log_observability', 'primary_student');
        const catalog = getE2ECatalog();

        await test.step('Siswa memicu clipboard blocked lalu admin membuka panel log', async () => {
            await prepareSecurityAttempt(page, fixture);
            await triggerClipboardBlocked(page, 1);
        });

        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        try {
            await test.step('Baris log frontend tetap terlihat setelah admin me-refresh halaman setup', async () => {
                await openAdminSecurityPanel(adminPage, catalog.users.admin_seed);
                const row = await waitForSecurityRow(adminPage, 'Clipboard diblokir');
                await expect(row).toContainText(fixture.user.display_name || fixture.user.username);
                await adminPage.reload({ waitUntil: 'networkidle' });
                const refreshedRow = await waitForSecurityRow(adminPage, 'Clipboard diblokir');
                await expect(refreshedRow).toContainText(fixture.user.display_name || fixture.user.username);
            });
        } finally {
            await adminContext.close();
        }
    });
});
