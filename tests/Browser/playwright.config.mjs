import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    testMatch: '*.spec.mjs',
    fullyParallel: false,
    workers: 1,
    timeout: 30000,
    outputDir: process.env.DBCMS_BROWSER_RESULTS || '/tmp/dbcms-browser-results',
    reporter: [['list']],
    use: {
        baseURL: process.env.DBCMS_BROWSER_BASE_URL,
        storageState: process.env.DBCMS_BROWSER_STORAGE_STATE,
        viewport: { width: 1480, height: 1050 },
        reducedMotion: 'reduce',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        launchOptions: process.env.DBCMS_BROWSER_CHROME ? { executablePath: process.env.DBCMS_BROWSER_CHROME } : {},
    },
});
