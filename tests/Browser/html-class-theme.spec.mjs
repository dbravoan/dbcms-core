import { expect, test } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const captureRoot = process.env.DBCMS_HTML_CLASS_CAPTURES;
const token = process.env.DBCMS_BROWSER_MCP_TOKEN;

test.skip(!captureRoot, 'Activa las capturas con DBCMS_HTML_CLASS_CAPTURES.');
test.use({ trace: 'off' });

async function capture(page, info, step, target) {
    const dir = resolve(captureRoot, 'html-class-theme');
    await mkdir(dir, { recursive: true });
    if (target) await target.scrollIntoViewIfNeeded();
    await page.evaluate(() => document.fonts.ready);
    const path = resolve(dir, step + '.png');
    await page.screenshot({ path, animations: 'disabled', caret: 'hide' });
    await info.attach(step, { path, contentType: 'image/png' });
}

async function mcp(request, baseURL) {
    let id = 0;
    return async (name, args = {}) => {
        const response = await request.post(new URL('/mcp', baseURL).toString(), {
            headers: { Authorization: 'Bearer ' + token, Accept: 'application/json', 'Content-Type': 'application/json' },
            data: { jsonrpc: '2.0', id: ++id, method: 'tools/call', params: { name, arguments: args } },
        });
        expect(response.ok(), await response.text()).toBeTruthy();
        const body = await response.json();
        expect(body.error, JSON.stringify(body)).toBeUndefined();
        const text = body.result?.content?.find((part) => part.type === 'text')?.text;
        return text ? JSON.parse(text) : body.result;
    };
}

test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('dbcms_tour_completed', 'true'));
    expect((await page.goto('/admin/pages')).ok()).toBeTruthy();
});

test('backoffice inspector and public class hook', async ({ page }, info) => {
    const title = 'HTML class theme ' + Date.now();
    expect((await page.goto('/admin/pages/create')).ok()).toBeTruthy();
    await page.waitForFunction(() => typeof window.Alpine?.$data(document.querySelector('form[data-cms-translation-editor]'))?.switchLocale === 'function');
    await page.locator('[name="translations[es][title]"]').fill(title);

    const manager = page.locator('.admin-block-manager');
    await expect(manager).toBeVisible();
    const addSection = manager.getByRole('button', { name: /Añadir primera sección|Añadir sección/i }).first();
    if (await addSection.isVisible()) {
        await addSection.click();
        const layout = page.locator('[data-add-section-dialog], [data-add-block-dialog]').locator('button, [role="button"]').filter({ hasText: /^1$/ }).first();
        if (await layout.count()) await layout.click();
    }

    await manager.locator('[data-block-index="0"]').click();
    const design = manager.getByRole('button', { name: /Diseño|Avanzado/i }).first();
    if (await design.count()) await design.click();
    const advanced = manager.getByRole('button', { name: 'Avanzado', exact: true }).or(manager.getByText('Avanzado', { exact: true }));
    if (await advanced.count()) await advanced.first().click();

    const htmlClass = manager.getByLabel('Clases CSS del tema');
    await expect(htmlClass).toBeVisible({ timeout: 15000 });
    await htmlClass.fill('folio-hero intent');
    await htmlClass.blur();
    await capture(page, info, '01-inspector-avanzado-html-class', htmlClass);

    const form = page.locator('form').filter({ has: page.locator('[name="translations[es][title]"]') });
    const response = page.waitForResponse((result) => result.request().method() === 'POST' && result.url().includes('/admin/pages'));
    await form.locator('button[type=submit]').click();
    expect((await response).status()).toBe(302);

    await page.goto('/admin/pages');
    const row = page.getByRole('row').filter({ hasText: title });
    await row.locator('a[href$="/edit"]:visible').first().click();
    await expect(page.locator('[name="translations[es][title]"]')).toHaveValue(title);

    const previewBtn = page.locator('.admin-block-manager [data-open-preview]');
    if (await previewBtn.count()) {
        await previewBtn.click();
        const dialog = page.locator('.admin-block-manager [data-preview-dialog]');
        await expect(dialog).toBeVisible();
        const frame = dialog.frameLocator('iframe');
        await expect(frame.locator('.folio-hero, [class*="folio-hero"]').first()).toBeVisible({ timeout: 15000 });
        await capture(page, info, '02-vista-previa-clase-html', dialog);
    }
});

test('mcp schema and patch document html_class', async ({ request, baseURL }, info) => {
    test.skip(!token, 'Define DBCMS_BROWSER_MCP_TOKEN para el recorrido MCP.');
    const call = await mcp(request, baseURL);
    const detail = await call('cms_block_types', { entity_type: 'page', locale: 'es', market: 'es', view: 'detail', block_type: 'heading' });
    expect(detail.appearance.html_class.pattern).toContain('a-z');
    expect(detail.appearance.classes).toEqual(expect.arrayContaining(['stack', 'row', 'card']));

    const dir = resolve(captureRoot, 'html-class-theme');
    await mkdir(dir, { recursive: true });
    const schemaPath = resolve(dir, '03-mcp-appearance-html-class.json');
    await writeFile(schemaPath, JSON.stringify({
        html_class: detail.appearance.html_class,
        classes: detail.appearance.classes,
        example: { appearance: { html_class: ['folio-hero'], classes: ['stack'] } },
    }, null, 2));
    await info.attach('03-mcp-appearance-html-class', { path: schemaPath, contentType: 'application/json' });

    const created = await call('cms_entity_create', {
        type: 'page',
        translations: {
            es: {
                title: 'MCP html_class ' + Date.now(),
                slug: 'mcp-html-class-' + Date.now(),
            },
        },
        sections_json: {
            es: [{
                type: 'text',
                columns_layout: '1',
                appearance: { html_class: ['folio-hero'] },
                columns: [{ blocks: [{ id: 't1', type: 'heading', text: 'Clase del tema', appearance: { html_class: ['intent'] } }] }],
            }],
        },
    });
    expect(created.id || created.entity?.id).toBeTruthy();
    const id = created.id || created.entity.id;
    const sections = await call('cms_content_sections_get', { type: 'page', id, locale: 'es', market: 'es', view: 'summary' });
    const payloadPath = resolve(dir, '04-mcp-sections-with-html-class.json');
    await writeFile(payloadPath, JSON.stringify(sections, null, 2));
    await info.attach('04-mcp-sections-with-html-class', { path: payloadPath, contentType: 'application/json' });
});
