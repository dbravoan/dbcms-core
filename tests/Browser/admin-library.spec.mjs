import { test, expect } from '@playwright/test';

const library = page => page.locator('[data-media-manager="library"]');
const picker = page => page.locator('[data-media-manager="picker"]');
const fixtures = new WeakMap();
const errors = new WeakMap();

test.beforeAll(() => {
    expect(process.env.DBCMS_BROWSER_BASE_URL, 'Supply the disposable application URL').toBeTruthy();
    expect(process.env.DBCMS_BROWSER_STORAGE_STATE, 'Supply the disposable admin state').toBeTruthy();
});

test.beforeEach(async ({ page }) => {
    const messages = [];
    errors.set(page, messages);
    page.on('pageerror', error => messages.push(error.message));
    await page.addInitScript(() => localStorage.setItem('dbcms_tour_completed', 'true'));
    await page.goto('/admin/media');
    await page.waitForFunction(() => typeof window.Alpine?.$data(document.querySelector('[data-media-manager="library"]'))?.uploadFile === 'function');
    const uploaded = page.waitForResponse(response => response.url().endsWith('/media/upload') && response.request().method() === 'POST');
    await library(page).locator('input[type=file]').setInputFiles({ name: `library-fixture-${Date.now()}.svg`, mimeType: 'image/svg+xml', buffer: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100"><rect width="200" height="100" fill="navy"/></svg>') });
    const response = await uploaded;
    expect(response.ok()).toBe(true);
    const { file } = await response.json();
    fixtures.set(page, { filename: file.filename, token: await page.locator('meta[name="csrf-token"]').getAttribute('content') });
    await expect(library(page).getByRole('button', { name: file.title, exact: true })).toBeVisible();
});

test.afterEach(async ({ page }) => {
    const fixture = fixtures.get(page);
    if (fixture) {
        const response = await page.request.delete(`/admin/media/${encodeURIComponent(fixture.filename)}`, { headers: { 'X-CSRF-TOKEN': fixture.token, Accept: 'application/json' } });
        expect(response.ok()).toBe(true);
    }
    expect(errors.get(page)).toEqual([]);
});

test('media navigation serves HTML and the separate feed serves paginated JSON', async ({ page }) => {
    const response = await page.goto('/admin/media');
    expect(response.headers()['content-type']).toContain('text/html');
    await expect(page.getByRole('heading', { name: /Biblioteca multimedia/ }).first()).toBeVisible();
    await expect(library(page).locator('.admin-media-tile').first()).toBeVisible();
    const feed = await page.request.get('/admin/media/files?type=image&per_page=10');
    expect(feed.headers()['content-type']).toContain('application/json');
    const data = await feed.json();
    expect(data.pagination.per_page).toBe(10);
    expect(data.files.every(file => file.is_image)).toBe(true);
});

test('uploads edits searches and deletes a disposable file only after confirmation', async ({ page }) => {
    await page.goto('/admin/media');
    const host = library(page);
    const title = `Aceptación multimedia ${Date.now()}`;
    await host.locator('input[type=file]').setInputFiles({ name: 'acceptance.svg', mimeType: 'image/svg+xml', buffer: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><rect width="20" height="20" fill="navy"/></svg>') });
    await expect(host.getByLabel('Título de la imagen (SEO)', { exact: true })).toBeVisible();
    await host.getByLabel('Título de la imagen (SEO)', { exact: true }).fill(title);
    await host.getByLabel('Texto alternativo (Alt SEO)', { exact: true }).fill('Imagen de aceptación');
    await host.getByRole('button', { name: 'Guardar cambios', exact: true }).click();
    await expect(host.getByRole('button', { name: title, exact: true })).toBeVisible();
    await host.locator('input[name=search]').fill(title);
    await expect(host.locator('.admin-media-tile')).toHaveCount(1);
    await host.getByRole('button', { name: title, exact: true }).click();
    const deletions = [];
    page.on('request', request => { if (request.method() === 'DELETE') deletions.push(request.url()); });
    await host.getByRole('button', { name: 'Eliminar', exact: true }).click();
    const confirmation = page.getByRole('dialog', { name: /Eliminar archivo/ });
    await expect(confirmation).toBeVisible();
    await confirmation.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await expect(confirmation).toBeHidden();
    expect(deletions).toHaveLength(0);
    await expect(host.getByRole('button', { name: title, exact: true })).toBeVisible();
    await host.getByRole('button', { name: 'Eliminar', exact: true }).click();
    await confirmation.getByRole('button', { name: 'Eliminar', exact: true }).click();
    await expect(host.locator('.admin-media-tile')).toHaveCount(0);
    expect(deletions).toHaveLength(1);
});

test('picker keeps its target and closes only the top confirmation before returning focus', async ({ page }) => {
    await page.goto('/admin/media');
    await expect(library(page).locator('.admin-media-tile').first()).toBeVisible();
    const returnButton = library(page).getByRole('button', { name: 'Recargar lista', exact: true });
    await expect(returnButton).toBeEnabled();
    await returnButton.focus();
    await expect(returnButton).toBeFocused();
    await page.evaluate(() => {
        window.acceptanceSelected = null;
        window.addEventListener('file-selected', event => { window.acceptanceSelected = event.detail; }, { once: true });
        window.dispatchEvent(new CustomEvent('open-filemanager', { detail: { target: 'acceptance-image', kind: 'image' } }));
    });
    const host = picker(page);
    await host.locator('.admin-media-tile').first().click();
    await host.getByRole('button', { name: 'Eliminar', exact: true }).click();
    await expect(page.getByRole('dialog', { name: /Eliminar archivo/ })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog', { name: /Eliminar archivo/ })).toBeHidden();
    await expect(host.locator('[data-admin-dialog-panel]').first()).toBeVisible();
    await expect(host.getByRole('button', { name: 'Eliminar', exact: true })).toBeFocused();
    await host.getByRole('button', { name: 'Seleccionar para el Bloque', exact: true }).click();
    await expect(host.locator('[data-admin-dialog-panel]').first()).toBeHidden();
    await expect(returnButton).toBeFocused();
    const selected = await page.evaluate(() => window.acceptanceSelected);
    expect(selected.target).toBe('acceptance-image');
    expect(selected.kind).toBe('image');
    expect(selected.file.is_image).toBe(true);
});

test('library reports failed loads and imports without discarding the input', async ({ page }) => {
    await page.goto('/admin/media');
    const host = library(page);
    await expect(host.locator('.admin-media-tile').first()).toBeVisible();
    await page.route('**/media/files?**', route => route.fulfill({ status: 503, json: { message: 'Biblioteca temporalmente no disponible' } }));
    await host.getByRole('button', { name: 'Recargar lista', exact: true }).click();
    await expect(host.getByRole('alert')).toContainText('Biblioteca temporalmente no disponible');
    await page.unroute('**/media/files?**');
    await host.getByRole('button', { name: 'Recargar lista', exact: true }).click();
    await expect(host.locator('.admin-media-tile').first()).toBeVisible();
    await page.route('**/media/import', route => route.fulfill({ status: 422, json: { message: 'Importación rechazada' } }));
    await host.getByLabel('Importar imagen desde URL pública', { exact: true }).fill('https://example.invalid/acceptance.webp');
    await host.getByRole('button', { name: 'Importar', exact: true }).click();
    await expect(host.getByRole('alert')).toContainText('Importación rechazada');
    await expect(host.getByLabel('Importar imagen desde URL pública', { exact: true })).toHaveValue('https://example.invalid/acceptance.webp');
});

test('templates retain search and pagination and use accessible confirmed actions', async ({ page }) => {
    await page.goto('/admin/block-components');
    await page.locator('form.admin-list-toolbar select[name=per_page]').selectOption('10');
    await expect(page).toHaveURL(/per_page=10/);
    await expect(page.locator('tbody > tr')).toHaveCount(10);
    const next = page.locator('a[href*="page=2"]').first();
    await next.click();
    await expect(page).toHaveURL(/page=2/);
    await expect(page.locator('form.admin-list-toolbar select[name=per_page]')).toHaveValue('10');
    await page.locator('form.admin-list-toolbar input[name=search]').fill('Hero');
    await page.getByRole('button', { name: 'Filtrar', exact: true }).click();
    await expect(page.locator('tbody > tr')).toHaveCount(1);
    await expect(page.getByRole('link', { name: 'Editar', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Previsualizar', exact: true })).toBeVisible();
    const deletions = [];
    page.on('request', request => { if (request.method() === 'DELETE' || request.postData()?.includes('_method=DELETE')) deletions.push(request.url()); });
    const button = page.getByRole('button', { name: 'Eliminar', exact: true });
    await button.click();
    const dialog = page.getByRole('dialog', { name: /Eliminar plantilla/ });
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(button).toBeFocused();
    expect(deletions).toHaveLength(0);
});

for (const width of [1440, 390]) {
    test(`library and templates fit a ${width}px viewport`, async ({ page }, testInfo) => {
        await page.setViewportSize({ width, height: 1000 });
        for (const [path, name] of [['/admin/media', 'multimedia'], ['/admin/block-components', 'plantillas']]) {
            await page.goto(path);
            await expect(page.locator('main')).toBeVisible();
            if (name === 'multimedia') {
                await expect(library(page).locator('.admin-media-tile').first()).toBeVisible();
                await expect.poll(() => library(page).locator('.admin-media-grid img').first().evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
                if (width < 640) {
                    const field = await library(page).getByLabel('Importar imagen desde URL pública', { exact: true }).boundingBox();
                    expect(field.width).toBeGreaterThan(200);
                }
            }
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
            await page.screenshot({ path: testInfo.outputPath(`${name}-${width}.png`), fullPage: true });
        }
    });
}
