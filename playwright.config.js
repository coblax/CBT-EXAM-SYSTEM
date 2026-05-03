const { defineConfig } = require('@playwright/test');

const reporters = [
    ['list', { printSteps: true }],
];

if (process.env.PLAYWRIGHT_DISABLE_HTML_REPORT !== '1') {
    reporters.push([
        'html',
        {
            outputFolder: process.env.PLAYWRIGHT_HTML_REPORT_DIR || 'coverage/playwright-report',
            open: 'never'
        }
    ]);
}

module.exports = defineConfig({
    testDir: './tests/e2e',
    outputDir: process.env.PLAYWRIGHT_OUTPUT_DIR || 'test-results',
    timeout: 60000,
    expect: {
        timeout: 10000
    },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: reporters,
    use: {
        baseURL: process.env.CBT_E2E_WP_BASE_URL || process.env.CBT_E2E_BASE_URL || 'http://localhost/wordpress',
        headless: true,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure'
    },
    projects: [
        {
            name: 'chromium',
            use: {
                browserName: 'chromium'
            }
        }
    ]
});
