import { test, expect } from '@playwright/test';
import { readFileSync, existsSync } from 'node:fs';
import { resolve, extname } from 'node:path';

// ContentRegionTest can export real full-theme PHP responses into a disposable
// directory. Serve those responses and the actual Vite build without a database
// or runtime route. This suite checks layout; scripts belong to runtime suites.
const themes = ['fixture-theme', 'fixture-alternate'];
const box = locator => locator.evaluate(node => {
    const rect = node.getBoundingClientRect();
    return { x: rect.x, right: rect.right, width: rect.width };
});
const expectedWidth = (_theme, viewport) => Math.min(viewport, 1280) - 32;

test.beforeAll(() => {
    expect(process.env.DBCMS_REGION_FIXTURE_DIR, 'Export disposable PHP content-region fixtures first').toBeTruthy();
    expect(process.env.DBCMS_REGION_ASSET_ROOT, 'Supply the candidate public directory containing the real Vite build').toBeTruthy();
});

test.beforeEach(async ({ page }) => {
    await page.route('**/*', async route => {
        const pathname = new URL(route.request().url()).pathname;
        if (pathname.startsWith('/__region/')) {
            const path = resolve(process.env.DBCMS_REGION_FIXTURE_DIR, pathname.slice('/__region/'.length));
            expect(existsSync(path), `Missing exported fixture ${path}`).toBeTruthy();
            const html = readFileSync(path, 'utf8')
                .replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '')
                .replace(/<link\b[^>]*rel=["']modulepreload["'][^>]*>/gi, '');
            return route.fulfill({ contentType: 'text/html', body: html });
        }
        if (pathname.startsWith('/build/')) {
            const path = resolve(process.env.DBCMS_REGION_ASSET_ROOT, `.${pathname}`);
            expect(existsSync(path), `Missing built asset ${path}`).toBeTruthy();
            const contentType = { '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff', '.ttf': 'font/ttf', '.svg': 'image/svg+xml' }[extname(path)] ?? 'application/octet-stream';
            return route.fulfill({ contentType, headers: { 'Access-Control-Allow-Origin': '*' }, body: readFileSync(path) });
        }
        return route.fulfill({ status: 204, body: '' });
    });
});

for (const theme of themes) {
    for (const viewport of [390, 800, 1480]) {
        test(`${theme} preserves content widths and contains nested viewport nodes at ${viewport}px`, async ({ page }) => {
            await page.setViewportSize({ width: viewport, height: 900 });
            await page.goto(`http://dbcms-region.test/__region/${theme}-page.html`);
            const region = page.locator('main > [data-cms-content-region]');
            await expect(region).toHaveCount(1);
            await expect.poll(() => region.evaluate(node => getComputedStyle(node).display)).toBe('grid');
            await page.addStyleTag({ content: 'html { overflow-y: scroll; scrollbar-gutter: stable; } body { min-height: 200vh; }' });
            // With stable gutters Chromium can include the reserved scrollbar
            // strip in documentElement.clientWidth. The body is the available
            // layout viewport; fullbleed must fit that width rather than 100vw.
            const client = await page.evaluate(() => document.body.getBoundingClientRect().width);
            const roots = region.locator(':scope > [data-cms-section]');
            await expect(roots).toHaveCount(3);
            const full = await box(roots.nth(0));
            const contained = await box(roots.nth(1));
            const columns = await box(roots.nth(2));
            expect(full.x).toBeCloseTo(0, 0);
            expect(full.width).toBeCloseTo(client, 0);
            expect(columns.width).toBeCloseTo(client, 0);
            expect(contained.width).toBeCloseTo(expectedWidth(theme, client), 0);
            const nested = await box(page.locator('[data-cms-node="nested-viewport"]'));
            expect(nested.x).toBeGreaterThanOrEqual(contained.x - 1);
            expect(nested.right).toBeLessThanOrEqual(contained.right + 1);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);

            await page.goto(`http://dbcms-region.test/__region/${theme}-module.html`);
            await page.addStyleTag({ content: 'html { overflow-y: scroll; scrollbar-gutter: stable; } body { min-height: 200vh; }' });
            const moduleClient = await page.evaluate(() => document.body.getBoundingClientRect().width);
            const module = await box(page.locator('#module-content'));
            expect(module.width).toBeCloseTo(moduleClient, 0);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
            const padding = await page.locator('main').evaluate(node => Number.parseFloat(getComputedStyle(node).paddingTop));
            expect(padding).toBe(0);
        });
    }
}
