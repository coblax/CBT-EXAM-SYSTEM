const { test, expect } = require('@playwright/test');
const {
    clearE2ESecurityLogs,
    clearE2ESecurityLiveState,
    getE2ECatalog,
    getE2EFixture,
    resetE2EFixture,
    setE2ESecurityConfig,
    updateE2EExamFixture,
} = require('./helpers/e2e-fixture');
const {
    loginAsStudent,
    startOrResumeAttempt,
} = require('./helpers/frontend-browser');
const {
    loginToWpAdmin,
    openSetupSecurityLogPage,
    openSetupSecurityNativePage,
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
        if (index < (count - 1)) {
            await page.waitForTimeout(1700);
        }
    }
}

async function openAdminSecurityPanel(page, adminUser) {
    await loginToWpAdmin(page, adminUser);
    await openSetupSecurityLogPage(page);
}

async function openAdminSecurityNativePanel(page, adminUser) {
    await loginToWpAdmin(page, adminUser);
    await openSetupSecurityNativePage(page);
}

async function readNativeSecuritySnapshot(page) {
    return page.evaluate(() => {
        if (!window.CBTNativeBridge || typeof window.CBTNativeBridge.getSecuritySnapshot !== 'function') {
            return null;
        }

        return window.CBTNativeBridge.getSecuritySnapshot();
    });
}

async function postNativeSecurityEvent(page, payload) {
    return page.evaluate(async (nativePayload) => {
        const response = await fetch(String(nativePayload.endpoint || ''), {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + String(nativePayload.token || ''),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                attempt_id: Number(nativePayload.attemptId || 0),
                event_type: String(nativePayload.eventType || ''),
                native_app: String(nativePayload.nativeApp || ''),
                native_version: String(nativePayload.nativeVersion || '1.0.0'),
                warning_code: String(nativePayload.warningCode || ''),
                warning_message: String(nativePayload.warningMessage || ''),
                occurred_at_client: '2026-03-26T21:31:02+07:00',
                context: {
                    has_focus: 0,
                    device_platform: nativePayload.nativeApp === 'android_webview' ? 'android' : 'windows',
                    device_type: nativePayload.nativeApp === 'android_webview' ? 'mobile' : 'desktop',
                    native_event_name: String(nativePayload.nativeEventName || 'NativeWarning'),
                },
            }),
        });
        const data = await response.json().catch(() => ({}));

        return {
            ok: response.ok,
            status: response.status,
            data,
        };
    }, payload);
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

async function waitForLiveRosterRow(page, studentName) {
    return waitForCondition(async () => {
        const row = page.locator('[data-security-log-roster-row]').filter({ hasText: String(studentName || '') }).first();
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
        errorMessage: `Live roster row untuk "${studentName}" tidak muncul.`,
    });
}

test.describe('Security Log & Observability flow check', () => {
    test.setTimeout(150000);

    test.beforeEach(() => {
        clearE2ESecurityLogs();
        clearE2ESecurityLiveState();
        setE2ESecurityConfig({
            block_copy_paste: 1,
            detect_idle_during_exam: 1,
            force_fullscreen: 0,
            idle_threshold_minutes: 5,
            log_security_events: 1,
        });
        updateE2EExamFixture('security_log_observability', {
            target_kelas: 'KELAS_TEST_01,KELAS_TEST_02',
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
                const mustWatchCards = adminPage.locator('[data-security-log-focus-card]');
                const firstCard = mustWatchCards.first();
                const secondCard = mustWatchCards.nth(1);
                await expect(firstCard).toContainText(primaryFixture.user.display_name || primaryFixture.user.username);
                await expect(secondCard).toContainText(secondaryFixture.user.display_name || secondaryFixture.user.username);

                const firstScore = Number(await firstCard.getAttribute('data-sort-score') || '0');
                const secondScore = Number(await secondCard.getAttribute('data-sort-score') || '0');
                expect(firstScore).toBeGreaterThan(secondScore);
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
                await expect(card).toContainText('Status Live');
                await expect(card).toContainText('Pelanggaran Dominan');
                await expect(card).toContainText(/Clipboard diblokir/);
                await expect(card).toContainText(fixture.exam.title);
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Security Flow: live roster shows active attempt grouped by exam kelas dan ruang', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('security_log_observability', 'primary_student');
        const catalog = getE2ECatalog();

        await test.step('Siswa memulai attempt aktif agar masuk ke live roster', async () => {
            await prepareSecurityAttempt(page, fixture);
        });

        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        try {
            await test.step('Admin melihat section Live Roster di atas Must Watch dengan row attempt aktif', async () => {
                await openAdminSecurityPanel(adminPage, catalog.users.admin_seed);
                await expect(adminPage.locator('[data-security-log-live-roster]')).toContainText('Live Roster');
                await expect(adminPage.locator('[data-security-log-live-roster]')).toContainText(fixture.exam.title);
                await expect(adminPage.locator('[data-security-log-live-roster]')).toContainText(fixture.user.kode_kelas || '');
                await expect(adminPage.locator('[data-security-log-live-roster]')).toContainText(fixture.user.kode_ruang || '');

                const row = await waitForLiveRosterRow(adminPage, fixture.user.display_name || fixture.user.username);
                await expect(row).toContainText('Online');
                await expect(row).toContainText('Seen:');
                await expect(row).toContainText('Buka Results');
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

    test('Security Flow: native direct API event appears in observability panel', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('security_log_observability', 'primary_student');
        const catalog = getE2ECatalog();

        await test.step('Siswa memulai attempt lalu native shell mengirim event CBT existing langsung ke endpoint native', async () => {
            await prepareSecurityAttempt(page, fixture);
            const snapshot = await readNativeSecuritySnapshot(page);
            expect(snapshot).not.toBeNull();
            expect(snapshot && snapshot.ok).toBe(1);
            expect(snapshot && snapshot.endpoints && snapshot.endpoints.nativeSecurityEvent).toBeTruthy();

            const response = await postNativeSecurityEvent(page, {
                attemptId: snapshot.attemptId,
                endpoint: snapshot.endpoints.nativeSecurityEvent,
                eventType: 'tab_hidden',
                nativeApp: 'windows_cefsharp',
                nativeEventName: 'TaskSwitchWarning',
                nativeVersion: '1.0.0',
                token: snapshot.token,
                warningCode: 'task_switch',
                warningMessage: 'Window ujian kehilangan fokus karena task switch',
            });

            expect(response.ok).toBeTruthy();
            expect(response.status).toBe(200);
            expect(response.data && response.data.logged).toBe(1);
        });

        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        try {
            await test.step('Panel observability admin menampilkan row event existing dengan detail native yang benar', async () => {
                await openAdminSecurityPanel(adminPage, catalog.users.admin_seed);
                const row = await waitForSecurityRow(adminPage, 'Pindah tab / aplikasi');
                await expect(row).toContainText(fixture.user.display_name || fixture.user.username);
                await expect(row).toContainText('task switch');
                await expect(row).toContainText('Windows CEFSharp');
            });
        } finally {
            await adminContext.close();
        }
    });

    test('Security Flow: native tab sample request and simulate tool create visible native log', async ({ browser, page, baseURL }) => {
        test.skip(!baseURL, 'Set CBT_E2E_BASE_URL untuk mengaktifkan flow check Playwright ini.');

        const fixture = getE2EFixture('security_log_observability', 'primary_student');
        const catalog = getE2ECatalog();

        await test.step('Siswa memulai attempt dan memicu clipboard blocked ringan sebagai baseline skor observability', async () => {
            await prepareSecurityAttempt(page, fixture);
            await triggerClipboardBlocked(page, 1);
        });

        const snapshot = await readNativeSecuritySnapshot(page);
        expect(snapshot).not.toBeNull();
        expect(snapshot && snapshot.attemptId).toBeGreaterThan(0);

        const adminContext = await browser.newContext();
        const adminPage = await adminContext.newPage();
        try {
            await test.step('Tab Native menampilkan sample request dan snippet untuk Android/Windows', async () => {
                await openAdminSecurityNativePanel(adminPage, catalog.users.admin_seed);
                await expect(adminPage.locator('#cbt-native-sample-request-json')).toContainText('tab_hidden');
                await expect(adminPage.locator('#cbt-native-sample-curl')).toContainText('/wp-json/cbt/v1/native_security_event');
                await expect(adminPage.locator('#cbt-native-sample-android')).toContainText('CBTNativeBridge.getSecuritySnapshot');
                await expect(adminPage.locator('#cbt-native-sample-cefsharp')).toContainText('EvaluateScriptAsync');
            });

            await test.step('Simulasi native event dari tab Native membuat row log dan attempt masuk Must Watch', async () => {
                await adminPage.locator('#cbt-native-simulate-attempt-id').fill(String(snapshot.attemptId));
                await adminPage.selectOption('#cbt-native-simulate-app', 'windows_cefsharp');
                await adminPage.selectOption('#cbt-native-simulate-event-type', 'fullscreen_exit');
                await adminPage.locator('#cbt-native-simulate-warning-code').fill('kiosk_escape');
                await adminPage.locator('#cbt-native-simulate-warning-message').fill('Shell native terlepas dari mode kiosk');
                await adminPage.locator('#cbt-native-generate-sample-request').click({ force: true });
                await expect(adminPage.locator('#cbt-native-sample-request-json')).toContainText('fullscreen_exit');
                await expect(adminPage.locator('#cbt-native-sample-request-json')).toContainText('windows_cefsharp');
                await Promise.all([
                    adminPage.waitForLoadState('networkidle'),
                    adminPage.locator('#cbt-native-simulate-form button[type="submit"]').click({ force: true }),
                ]);

                const row = await waitForSecurityRow(adminPage, 'Keluar fullscreen');
                await expect(row).toContainText('kiosk_escape');
                const mustWatchCard = await waitForMustWatchCard(adminPage, fixture.user.display_name || fixture.user.username);
                await expect(mustWatchCard).toContainText('Skor 6');
            });
        } finally {
            await adminContext.close();
        }
    });
});
