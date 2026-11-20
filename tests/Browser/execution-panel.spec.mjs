import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Manual acceptance lane. fixtures/bootstrap-executions.php refuses an unmarked application.
const enabled = process.env.DBCMS_BROWSER_EXECUTIONS === '1';
const fixture = enabled ? JSON.parse(readFileSync(process.env.DBCMS_BROWSER_FIXTURES, 'utf8')) : {};
const brands = enabled ? JSON.parse(process.env.DBCMS_BROWSER_BRANDS) : {};
const diagnostics = new WeakMap();
if (!enabled) throw new Error('Requires the marked disposable core fixture and its brand URLs.');
test.use({ storageState: { cookies: [], origins: [] } });

test.beforeAll(() => {
    for (const brand of ['dbcms', 'termocms']) {
        expect(brands[brand], `Supply the disposable ${brand} URL`).toBeTruthy();
        expect(fixture[brand]).toBeTruthy();
    }
});

test.beforeEach(async ({ page }) => {
    const errors = [];
    diagnostics.set(page, errors);
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => { if (response.status() >= 500) errors.push(`${response.status()} ${response.url()}`); });
    await page.addInitScript(() => { if (window.top === window) localStorage.setItem('dbcms_tour_completed', 'true'); });
});

test.afterEach(async ({ page }, testInfo) => {
    await testInfo.attach('browser-errors', { body: JSON.stringify(diagnostics.get(page)), contentType: 'application/json' });
    expect(diagnostics.get(page)).toEqual([]);
});

async function login(page, base) {
    await page.goto(`${base}/admin/login`);
    await page.locator('input[name=email]').fill(process.env.DBCMS_BROWSER_EMAIL || 'browser@example.test');
    await page.locator('input[name=password]').fill(process.env.DBCMS_BROWSER_PASSWORD || 'browser-only-password');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: /Iniciar sesión/ }).click()]);
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('main').first()).toBeVisible();
}

const snapshot = (page, testInfo, name) => page.screenshot({ path: testInfo.outputPath(`${name}.png`), fullPage: true });

for (const brand of ['dbcms', 'termocms']) {
    test.describe(brand, () => {
        test('core approval and cancellation use the live execution panel without AI', async ({ page }, testInfo) => {
            const base = brands[brand], data = fixture[brand];
            await login(page, base);
            const requests = [];
            page.on('request', request => { if (request.method() === 'POST') requests.push(request.url()); });
            await page.goto(`${base}/admin/executions/${data.executions.approve}`);
            await expect(page.getByText('Esperando tu aprobación', { exact: true }).first()).toBeVisible();
            await snapshot(page, testInfo, `${brand}-execution-review`);
            const approval = page.waitForResponse(response => response.url().endsWith(`/${data.executions.approve}/approve`));
            await page.getByRole('button', { name: 'Aprobar este cambio', exact: true }).click();
            expect((await approval).status()).toBe(200);
            await expect(page.getByText('Completado', { exact: true }).first()).toBeVisible();
            await page.goto(`${base}/admin/executions/${data.executions.cancel}`);
            await expect(page.getByRole('button', { name: 'Aprobar este cambio', exact: true })).toBeVisible();
            await page.getByRole('button', { name: 'Cancelar', exact: true }).click();
            await expect(page.getByText('Cancelado', { exact: true })).toBeVisible();
            const stale = page.waitForResponse(response => response.url().endsWith(`/${data.executions.cancel}/approve`));
            await page.getByRole('button', { name: 'Aprobar este cambio', exact: true }).click();
            expect((await stale).status()).toBe(409);
            await expect(page.getByText('Cancelado', { exact: true })).toBeVisible();
            expect(requests.some(url => url.includes('/orchestration/'))).toBe(false);
            await snapshot(page, testInfo, `${brand}-execution-cancelled`);
        });
    });
}
