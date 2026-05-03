const { expect } = require('@playwright/test');
const { configuredE2EBaseUrl, configuredE2EFrontendUrl } = require('./e2e-url');

async function currentPageDiagnostic(page) {
    const title = await page.title().catch(() => '');
    const bodyText = await page.locator('body').innerText({ timeout: 1200 }).catch(() => '');
    const excerpt = String(bodyText || '').replace(/\s+/g, ' ').trim().slice(0, 500);

    return [
        `URL aktif: ${page.url() || '-'}`,
        `CBT_E2E_BASE_URL: ${configuredE2EBaseUrl()}`,
        `CBT_E2E_FRONTEND_URL: ${configuredE2EFrontendUrl()}`,
        `Title: ${title || '-'}`,
        `Body: ${excerpt || '-'}`,
    ].join('\n');
}

async function expectVisibleWithE2EDiagnostic(page, locator, label, options = {}) {
    const timeout = Number(options.timeout) > 0 ? Number(options.timeout) : 20000;

    try {
        await expect(locator).toBeVisible({ timeout });
    } catch (error) {
        const diagnostic = await currentPageDiagnostic(page);
        throw new Error([
            `${label} tidak terlihat dalam ${timeout}ms.`,
            diagnostic,
            `Original error: ${String(error && error.message ? error.message : error)}`,
        ].join('\n'));
    }
}

module.exports = {
    currentPageDiagnostic,
    expectVisibleWithE2EDiagnostic,
};
