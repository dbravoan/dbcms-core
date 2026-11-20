import { expect, test } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

const captureRoot = process.env.DBCMS_RICH_TEXT_CAPTURES;
const token = process.env.DBCMS_BROWSER_MCP_TOKEN;
const template = 'blade:editor-qa';
const manager = page => page.locator('.admin-block-manager');
const card = (page, index) => manager(page).locator('[data-block-index="' + index + '"]');
const source = scope => scope.getByLabel('Código fuente', { exact: true });
const rich = scope => scope.locator('[data-rich-text-editor]:visible');
const titleInput = page => page.locator('[name="translations[es][title]"]');
const copy = 'Un ciclo de talleres para convertir una idea en un prototipo.';
const outcome = 'Prueba tu prototipo con usuarios y sal con un plan de mejora.';
const closing = 'Consulta las próximas fechas';
const top = 'Taller Horizonte';

test.skip(!captureRoot, 'Activa los recorridos con DBCMS_RICH_TEXT_CAPTURES.');
test.use({ trace: 'off' });
test.beforeEach(async ({ page }) => {
    await page.goto('/admin/pages');
    const spanish = page.getByRole('button', { name: 'ES', exact: true });
    if ((await spanish.getAttribute('aria-pressed')) !== 'true') {
        await spanish.click();
        await expect(spanish).toHaveAttribute('aria-pressed', 'true');
    }
});

async function capture(page, info, step, target) {
    const dir = resolve(captureRoot, info.title.split(' ')[0]);
    await mkdir(dir, { recursive: true });
    if (target) await target.scrollIntoViewIfNeeded();
    await page.evaluate(() => document.fonts.ready);
    const path = resolve(dir, step + '.png');
    await page.screenshot({ path, animations: 'disabled', caret: 'hide' });
    await info.attach(step, { path, contentType: 'image/png' });
}

async function createPage(page, title) {
    expect((await page.goto('/admin/pages/create')).ok()).toBe(true);
    await page.waitForFunction(() => typeof window.Alpine?.$data(document.querySelector('form[data-cms-translation-editor]'))?.switchLocale === 'function');
    await titleInput(page).fill(title);
}

async function saveDraft(page, title) {
    const form = page.locator('form').filter({ has: titleInput(page) });
    const response = page.waitForResponse(result => result.request().method() === 'POST' && result.url().endsWith('/admin/pages'));
    await form.locator('button[type=submit]').click();
    expect((await response).status()).toBe(302);
    await page.waitForURL('**/admin/pages');
    const row = page.getByRole('row').filter({ hasText: title });
    const link = row.locator('a[href$="/edit"]:visible').first();
    await expect(link).toBeVisible();
    await link.click();
    await expect(titleInput(page)).toHaveValue(title);
}

async function addBlock(page, section, column, name, mode = 'En blanco') {
    await card(page, section).locator('.admin-block-column').nth(column).getByRole('button', { name: /Añadir un bloque a esta columna$/ }).click();
    const item = page.locator('[data-add-block-dialog] div.group').filter({ has: page.getByText(name, { exact: true }) });
    await item.getByRole('button', { name: mode, exact: true }).click();
}

async function pressMode(editor, name) {
    await editor.getByRole('button', { name, exact: true }).click();
}

async function previewComposition(page) {
    const toolbar = manager(page).locator('.cms-inspector-toolbar');
    await toolbar.locator('[data-open-preview]').click();
    const dialog = manager(page).locator('[data-preview-dialog]');
    return { dialog, frame: dialog.frameLocator('iframe') };
}

function bladeEditor(page, locale = 'es') {
    return rich(page).filter({ has: page.locator('textarea[name="translations[' + locale + '][template_data][body][content]"]') });
}

async function mcp(api, baseURL) {
    let id = 0;
    const headers = { Authorization: 'Bearer ' + token, Accept: 'application/json, text/event-stream' };
    const rpc = async (method, params = {}, notify = false) => {
        const data = { jsonrpc: '2.0', method, params, ...(notify ? {} : { id: ++id }) };
        // Avoid echoing the authorization header in assertions or evidence.
        const response = await api.post(new URL('/mcp/cms', baseURL).href, { headers, data });
        expect(response.status(), method).toBeLessThan(300);
        if (response.headers()['mcp-session-id']) headers['Mcp-Session-Id'] = response.headers()['mcp-session-id'];
        if (notify) return;
        const body = await response.text();
        const payload = JSON.parse(body.startsWith('{') ? body : body.split('\n').find(line => line.startsWith('data:')).slice(5));
        expect(payload.error, method).toBeUndefined();
        return payload.result;
    };
    const initialization = await rpc('initialize', {
        protocolVersion: '2025-11-25', capabilities: {}, clientInfo: { name: 'dbcms-rich-text-guide', version: '1' },
    });
    headers['MCP-Protocol-Version'] = initialization.protocolVersion;
    await rpc('notifications/initialized', {}, true);
    return async (name, args = {}) => {
        const result = await rpc('tools/call', { name, arguments: args });
        const data = result.structuredContent ?? JSON.parse(result.content.find(item => item.type === 'text').text);
        expect(result.isError ?? false, JSON.stringify(data)).toBe(false);
        return data;
    };
}

async function evidence(browser, info, step, called, args, result) {
    const page = await browser.newPage({ viewport: { width: 1480, height: 1050 } });
    try {
        await page.setContent('<meta charset="utf-8"><title>Resultado MCP</title><style>body{background:#10191e;color:#eaeff2;font:18px ui-monospace,monospace;padding:42px}h1{color:#81f5c3;font-size:30px}main{display:grid;grid-template-columns:1fr 1fr;gap:22px}pre{background:#19262f;border:1px solid #3a4952;border-radius:8px;padding:22px;white-space:pre-wrap;overflow-wrap:anywhere;font-size:15px;line-height:1.35}</style><h1></h1><p>Llamada real al servidor de prueba. Credencial omitida.</p><main><section><h2>Argumentos</h2><pre id="args"></pre></section><section><h2>Respuesta</h2><pre id="result"></pre></section></main>');
        await page.evaluate(({ called, args, result }) => {
            document.querySelector('h1').textContent = called;
            document.querySelector('#args').textContent = JSON.stringify(args, null, 2);
            document.querySelector('#result').textContent = JSON.stringify(result, null, 2);
        }, { called, args, result });
        await capture(page, info, step);
    } finally {
        await page.close();
    }
}

test('01-bloques-backoffice maqueta y guarda tres secciones', async ({ page }, info) => {
    const title = top + ' · Backoffice ' + Date.now();
    await createPage(page, title);
    await manager(page).getByRole('button', { name: 'Añadir primera sección', exact: true }).click();
    await card(page, 0).getByTitle('2 Columnas (33/66)').click();
    await expect(card(page, 0).locator('.admin-block-column')).toHaveCount(2);
    await capture(page, info, '01-reticula', card(page, 0));
    await addBlock(page, 0, 0, 'Título');
    await card(page, 0).getByLabel('Texto del título', { exact: true }).fill(top);
    await addBlock(page, 0, 1, 'Rich Text');
    const intro = rich(card(page, 0));
    await expect(intro.locator('.tiptap')).toBeVisible();
    await intro.locator('.tiptap').fill(copy);
    await intro.locator('.tiptap').press('Control+a');
    await intro.getByRole('button', { name: 'Negrita', exact: true }).click();
    await expect(intro.locator('.tiptap strong')).toHaveText(copy);
    await pressMode(intro, 'Vista previa');
    await expect(intro.locator('[data-preview]')).toContainText(copy);
    await capture(page, info, '02-rich-text-visual', intro);

    await manager(page).getByRole('button', { name: 'Añadir sección al final', exact: true }).last().click();
    await addBlock(page, 1, 0, 'Acordeón', 'Con datos de ejemplo');
    const faqs = [
        ['¿Qué resultado me llevo?', '**Objetivo**\n\n' + outcome],
        ['¿Necesito experiencia previa?', 'Trae una idea. El taller empieza con un ejemplo guiado.'],
        ['¿Qué debo traer?', 'Un portátil y ganas de compartir el trabajo con el grupo.'],
    ];
    for (const [index, [question, answer]] of faqs.entries()) {
        await card(page, 1).getByLabel('Título del panel', { exact: true }).nth(index).fill(question);
        await source(rich(card(page, 1)).nth(index)).fill(answer);
    }
    await capture(page, info, '03-rich-text-anidado', rich(card(page, 1)).first());

    await manager(page).getByRole('button', { name: 'Añadir sección al final', exact: true }).last().click();
    await addBlock(page, 2, 0, 'Botón');
    await card(page, 2).getByLabel('Texto accesible', { exact: true }).fill(closing);
    await card(page, 2).getByLabel('Destino', { exact: true }).fill('/contacto');
    await saveDraft(page, title);
    await expect(card(page, 2)).toBeAttached();
    const { frame, dialog } = await previewComposition(page);
    await expect(frame.getByText(copy, { exact: true })).toBeVisible();
    const panel = frame.locator('details').filter({ hasText: '¿Qué resultado me llevo?' });
    if (!(await panel.evaluate(node => node.open))) await panel.locator('summary').click();
    await expect(panel.getByText(outcome, { exact: true })).toBeVisible();
    await expect(frame.getByRole('link', { name: closing })).toHaveAttribute('href', /\/contacto$/);
    await capture(page, info, '04-borrador-previsualizado', dialog);
});

test('02-bloques-mcp descubre, crea y corrige una sección mediante patch', async ({ page, request, baseURL, browser }, info) => {
    test.skip(!token, 'Indica DBCMS_BROWSER_MCP_TOKEN para este recorrido.');
    const call = await mcp(request, baseURL);
    const query = { entity_type: 'page', locale: 'es', market: 'es' };
    const catalog = await call('cms_block_types', query);
    expect(catalog.blocks.rich_text.name).toBe('Rich Text');
    expect(catalog.composition.layouts.find(layout => layout.id === '1-2').proportions).toEqual([1, 2]);
    await evidence(browser, info, '01-descubrimiento', 'cms_block_types', query, {
        layouts: catalog.composition.layouts, block: catalog.blocks.rich_text.type, name: catalog.blocks.rich_text.name,
    });
    const container = catalog.composition.example.sections_json.es[0].type;
    const sections = [
        { type: container, columns_layout: '1-2', columns: [
            { blocks: [{ type: 'heading', text: top, level: 'h1' }] },
            { blocks: [{ id: 'horizonte-intro', type: 'rich_text', format: 'markdown', content: '**Programa**\n\n' + copy }] },
        ] },
        { type: container, columns_layout: '1', columns: [{ blocks: [{
            type: 'accordion', label: 'Preguntas del taller', items: [{ title: '¿Qué resultado me llevo?', open: true, children: [
                { type: 'rich_text', format: 'html', content: '<p>' + outcome + '</p>' },
            ] }],
        }] }] },
        { type: container, columns_layout: '1', columns: [{ blocks: [{ type: 'button', label: closing, url: '/contacto' }] }] },
    ];
    const create = { type: 'page', translations: { es: { title: top + ' · MCP ' + Date.now() } }, sections_json: { es: sections } };
    const created = await call('cms_entity_create', create);
    expect(created.status).toBe('draft');
    await evidence(browser, info, '02-borrador-creado', 'cms_entity_create', {
        type: create.type, translations: create.translations, sections_json: { es: [sections[0]] },
    }, { id: created.id, status: created.status });
    const selector = { type: 'page', id: created.id, locale: 'es' };
    const summary = await call('cms_content_sections_get', { ...selector, view: 'summary' });
    expect(summary.layout.es).toHaveLength(3);
    const intro = summary.layout.es[0].columns[1].blocks[0];
    const updated = copy + ' Inscripción abierta.';
    const patch = { ...selector, version: summary.version, operations: [
        { op: 'update', node_id: intro.id, values: { format: 'markdown', content: '**Programa**\n\n' + updated } },
    ] };
    const result = await call('cms_content_sections_patch', patch);
    expect(result.changed).toBe(1);
    expect(result.version).not.toBe(summary.version);
    await evidence(browser, info, '03-parche-versionado', 'cms_content_sections_patch', patch, result);
    const read = await call('cms_content_sections_get', { ...selector, node_id: intro.id });
    expect(read.nodes.es[0].content).toContain(updated);
    await page.goto('/admin/pages/' + created.id + '/edit');
    const { frame, dialog } = await previewComposition(page);
    await expect(frame.getByText(updated, { exact: true })).toBeVisible();
    await expect(frame.getByRole('link', { name: closing })).toBeVisible();
    await capture(page, info, '04-composicion-en-backoffice', dialog);
});

test('03-blade-backoffice guarda un input y un cuerpo visual', async ({ page }, info) => {
    const title = top + ' · Blade desde el panel ' + Date.now();
    await createPage(page, title);
    await page.locator('select[name=template]').selectOption(template);
    const input = page.getByRole('textbox', { name: 'Titular de la plantilla' });
    const editor = bladeEditor(page);
    await input.fill(top);
    await expect(editor.locator('.tiptap')).toBeVisible();
    await editor.locator('.tiptap').fill(copy);
    const alignment = editor.locator('[data-choice="text-align"]');
    await alignment.locator('summary').click();
    await alignment.getByRole('button', { name: 'Centro', exact: true }).click();
    await pressMode(editor, 'Vista previa');
    await expect(editor.locator('[data-preview] p')).toHaveAttribute('style', 'text-align: center');
    await capture(page, info, '01-plantilla-y-edicion-visual', editor);
    await saveDraft(page, title);
    await expect(input).toHaveValue(top);
    await expect(editor.locator('.tiptap')).toContainText(copy);
    await pressMode(editor, 'HTML');
    await expect(source(editor)).toHaveValue(/text-align: center/);
    await capture(page, info, '02-guardado-y-codigo', editor);
    await pressMode(editor, 'Visual');
    await expect(editor.locator('.tiptap')).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    const headings = editor.locator('[data-choice="heading"]');
    await headings.locator('summary').click();
    await expect(headings.locator('.cms-rich-menu-panel')).toHaveCSS('position', 'absolute');
    await expect(headings.getByRole('button', { name: 'Encabezado 2', exact: true })).toBeVisible();
    await capture(page, info, '03-encabezados-en-movil', headings);
    await headings.getByRole('button', { name: 'Encabezado 2', exact: true }).click();
    await expect(editor.locator('.tiptap h2')).toHaveText(copy);
});

test('04-blade-mcp descubre la plantilla y escribe dos traducciones', async ({ page, request, baseURL, browser }, info) => {
    test.skip(!token, 'Indica DBCMS_BROWSER_MCP_TOKEN para este recorrido.');
    const call = await mcp(request, baseURL);
    const schema = (await call('cms_entity_types')).types.find(item => item.type === 'page');
    const fields = schema.blade_templates[template].fields;
    expect(fields.headline.type).toBe('text');
    expect(fields.body.formats).toEqual(['html', 'markdown']);
    await evidence(browser, info, '01-esquema-de-la-plantilla', 'cms_entity_types', {}, { template, fields });
    const english = 'A workshop series for testing your next prototype.';
    const args = {
        type: 'page', attributes: { template }, translations: {
            es: { title: top + ' · Blade desde MCP ' + Date.now(), template_data: { headline: top, body: { format: 'markdown', content: '**Programa**\n\n' + copy } } },
            en: { title: 'Horizon Workshop ' + Date.now(), template_data: { headline: 'Horizon Workshop', body: { format: 'html', content: '<p>' + english + '</p>' } } },
        },
    };
    const created = await call('cms_entity_create', args);
    expect(created.status).toBe('draft');
    const read = await call('cms_entity_get', { type: 'page', id: created.id });
    expect(read.translations.es.template_data).toEqual(args.translations.es.template_data);
    expect(read.translations.en.template_data).toEqual(args.translations.en.template_data);
    await evidence(browser, info, '02-borrador-bilingue', 'cms_entity_create', args, { id: created.id, status: created.status });
    await page.goto('/admin/pages/' + created.id + '/edit');
    const editor = bladeEditor(page);
    await expect(source(editor)).toHaveValue(/Programa/);
    await pressMode(editor, 'Vista previa');
    await expect(editor.locator('[data-preview] strong')).toHaveText('Programa');
    await capture(page, info, '03-traduccion-en-backoffice', editor);
    await page.getByRole('button', { name: /\(EN\)$/ }).click();
    const alternate = bladeEditor(page, 'en');
    await expect(alternate.locator('.tiptap')).toContainText(english);
    await capture(page, info, '04-traduccion-en-ingles', alternate);
});
