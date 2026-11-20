import { test, expect } from '@playwright/test';
import { column, section, seedSections } from './fixtures/sections.mjs';

const browserErrors = new WeakMap();
const manager = page => page.locator('.admin-block-manager');
const state = async page => JSON.parse(await page.locator('input[name="sections_json"]').inputValue());
const sections = async page => Object.values(await state(page)).flat();
const firstBlock = async page => (await sections(page))[0].columns[0].blocks[0];
const chooseBlock = async (page, name, mode = 'En blanco') => {
    const item = page.locator('[data-add-block-dialog] div.group').filter({ has: page.getByText(name, { exact: true }) });
    await item.getByRole('button', { name: mode, exact: true }).click();
};
// The preview is an on-demand dialog opened from the canvas toolbar.
const openPreview = async page => {
    const response = page.waitForResponse(result => result.url().includes('/blocks/preview') && result.request().method() === 'POST');
    await manager(page).locator('[data-open-preview]').click();
    return response;
};
const expectSchemaLabels = async host => {
    const failures = await host.evaluate(root => [...root.querySelectorAll('label[for]')].flatMap(label => {
        const matches = [...document.querySelectorAll('[id]')].filter(node => node.id === label.htmlFor);
        return matches.length === 1 && label.control === matches[0] ? [] : [label.textContent];
    }));
    expect(failures, 'Schema labels must target one actual control, including cloned descendants').toEqual([]);
};

// Explicit caller-owned disposable composition and authentication; never pick a
// URL, account or existing content record implicitly.
test.beforeAll(() => {
    expect(process.env.DBCMS_BROWSER_BASE_URL, 'Supply the disposable application URL').toBeTruthy();
    expect(process.env.DBCMS_BROWSER_STORAGE_STATE, 'Supply an authenticated admin storage state').toBeTruthy();
});

test.beforeEach(async ({ page }) => {
    const errors = []; browserErrors.set(page, errors);
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error' && !message.text().includes('Blocked form submission')) errors.push(message.text()); });
    // Browser-generated favicon traffic is not a CMS block asset.
    await page.route('**/favicon.ico', route => route.fulfill({ status: 204 }));
    await page.addInitScript(() => {
        if (window === window.top) localStorage.setItem('dbcms_tour_completed', 'true');
    });
});

test.afterEach(async ({ page }) => { expect(browserErrors.get(page)).toEqual([]); });

test('create context inserts recursive content, preserves undo history, previews and copies Blade', async ({ page }) => {
    const response = await page.goto('/admin/pages/create');
    await page.waitForFunction(() => typeof window.Alpine?.$data(document.querySelector('.admin-block-manager'))?.setBlocks === 'function');
    expect(response.status()).toBe(200);
    await manager(page).getByRole('button', { name: 'Añadir primera sección', exact: true }).click();
    expect((await sections(page))[0].columns[0].blocks).toEqual([]);
    await manager(page).locator('[data-block-index="0"]').getByRole('button', { name: /Añadir un bloque a esta columna$/ }).click();
    await chooseBlock(page, 'Acordeón', 'Con datos de ejemplo');
    const editor = manager(page).locator('[data-schema-editor]').first();
    const addHeading = async () => {
        await editor.getByLabel('Tipo de bloque: Contenido', { exact: true }).first().selectOption('heading');
        await editor.getByRole('button', { name: 'Añadir bloque', exact: true }).first().click();
    };
    await addHeading();
    await manager(page).getByRole('button', { name: 'Estructura', exact: true }).click();
    const structure = page.getByRole('navigation', { name: 'Estructura de bloques' });
    await structure.getByRole('button', { name: 'Título', exact: true }).last().click();
    await editor.getByLabel('Texto del título', { exact: true }).last().fill('Encabezado de aceptación');
    expect((await firstBlock(page)).items[0].children.at(-1).text).toBe('Encabezado de aceptación');
    await manager(page).getByRole('button', { name: 'Deshacer', exact: true }).click();
    await expect(editor.getByLabel('Texto del título', { exact: true }).last()).toHaveValue('');
    await manager(page).getByRole('button', { name: 'Rehacer', exact: true }).click();
    await expect(editor.getByLabel('Texto del título', { exact: true }).last()).toHaveValue('Encabezado de aceptación');
    await structure.getByRole('button', { name: 'Acordeón', exact: true }).click();
    await addHeading();
    const children = (await firstBlock(page)).items[0].children;
    await structure.getByRole('button', { name: 'Título', exact: true }).last().click();
    await editor.getByLabel('Texto del título', { exact: true }).last().fill('Segundo encabezado');
    await structure.getByRole('button', { name: 'Acordeón', exact: true }).click();
    const first = editor.locator(`[data-editor-node="${children.at(-2).id}"]`);
    const second = editor.locator(`[data-editor-node="${children.at(-1).id}"]`);
    await expectSchemaLabels(editor);
    await second.scrollIntoViewIfNeeded();
    const from = await first.locator('[data-node-drag]').boundingBox();
    const to = await second.boundingBox();
    await page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
    await page.mouse.down();
    await page.mouse.move(from.x + from.width / 2 + 10, from.y + from.height / 2 + 10, { steps: 5 });
    await page.mouse.move(to.x + 12, to.y + to.height - 12, { steps: 20 });
    await page.mouse.up();
    await expect.poll(async () => (await firstBlock(page)).items[0].children.slice(-2).map(node => node.text)).toEqual(['Segundo encabezado', 'Encabezado de aceptación']);
    await structure.getByRole('button', { name: 'Título', exact: true }).last().click();
    expect((await openPreview(page)).status()).toBe(200);
    const preview = page.frameLocator('[data-block-preview] iframe');
    const panel = preview.locator('[data-cms-accordion] details').first();
    if (!(await panel.evaluate(node => node.open))) await panel.locator('summary').click();
    await expect(preview.getByRole('heading', { name: 'Encabezado de aceptación', exact: true })).toBeVisible();
    await expect(page.locator('[data-block-preview] iframe')).toHaveAttribute('sandbox', 'allow-scripts allow-same-origin');
    await manager(page).locator('[data-preview-dialog] [data-admin-dialog-close]').click();
    await manager(page).locator('[data-block-index="0"] .admin-block-nested').getByRole('button', { name: /Abrir el diseño|Open section design/i }).first().click();
    await expect(manager(page).locator('[data-design-dialog]')).toBeVisible();
    await manager(page).getByRole('button', { name: 'Copiar Blade', exact: true }).click();
    await expect(manager(page).getByLabel('Código del bloque seleccionado', { exact: true })).toHaveValue(/\$cmsBlockPayload/);
});

test('HTML source round trips retain tables images and formatting without a destructive visual conversion', async ({ page }) => {
    await page.route('**/__richtext-image.svg', route => route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"/>' }));
    await page.goto('/admin/pages/create');
    await page.waitForFunction(() => typeof window.Alpine?.$data(document.querySelector('.admin-block-manager'))?.setBlocks === 'function');
    await seedSections(page, [section('richtext-section', [{ id: 'richtext-roundtrip', type: 'rich_text', format: 'html', content: '<p>Antes del cambio</p>' }])], [0, 'columns', 0, 'blocks', 0]);
    const visual = manager(page).getByRole('textbox', { name: /Contenido|Contenido de la sección/ });
    await expect(visual).toBeVisible();
    await visual.fill('Edición visual compatible');
    await expect.poll(async () => (await firstBlock(page)).content).toBe('<p>Edición visual compatible</p>');

    const original = '<table class="datos"><tbody><tr><th scope="col">Dato</th></tr><tr><td>Valor intacto</td></tr></tbody></table><p>Otro párrafo</p><img src="/__richtext-image.svg" alt="Imagen conservada" width="20">';
    const source = manager(page).getByLabel('Código fuente', { exact: true });
    const surface = manager(page).locator('[data-rich-text-editor]:visible');
    await surface.getByRole('button', { name: 'HTML', exact: true }).click();
    await source.fill(original);
    await surface.getByRole('button', { name: 'Visual', exact: true }).click();
    const fallback = manager(page).locator('[data-rich-source-fallback]');
    await expect(fallback).toContainText('Edita el código fuente');
    await expect(visual).toHaveCount(0);
    await expect.poll(async () => (await firstBlock(page)).content).toBe(original);
    const edited = original.replace('Otro párrafo', 'Párrafo editado sin perder la tabla');
    await source.fill(edited);
    await expect(source).toHaveValue(edited);
    await expect.poll(async () => (await firstBlock(page)).content).toBe(edited);
    await manager(page).locator('.cms-inspector-toolbar').getByRole('button', { name: 'Deshacer', exact: true }).click();
    await manager(page).locator('.cms-inspector-toolbar').getByRole('button', { name: 'Rehacer', exact: true }).click();
    await expect(source).toHaveValue(edited);
    expect((await openPreview(page)).status()).toBe(200);
    const preview = page.frameLocator('[data-block-preview] iframe');
    await expect(preview.getByRole('cell', { name: 'Valor intacto', exact: true })).toBeVisible();
    await expect(preview.getByText('Párrafo editado sin perder la tabla', { exact: true })).toBeVisible();
    await expect(preview.getByAltText('Imagen conservada', { exact: true })).toHaveAttribute('width', '20');
    await manager(page).locator('[data-preview-dialog] [data-admin-dialog-close]').click();

    // Allowed attributes are also content: StarterKit must not silently discard
    // language, direction, classes or image/table metadata on another edit.
    const attributes = '<p class="intro" lang="es" dir="ltr">Formato conservado</p>';
    await source.fill(attributes);
    await surface.getByRole('button', { name: 'Visual', exact: true }).click();
    await expect(fallback).toBeVisible();
    await expect.poll(async () => (await firstBlock(page)).content).toBe(attributes);
    await seedSections(page, [section('markdown-section', [{ id: 'markdown-roundtrip', type: 'rich_text', format: 'markdown', content: '**Markdown original**\n\n| A | B |\n|---|---|\n| 1 | 2 |' }])], [0, 'columns', 0, 'blocks', 0]);
    await expect(source).toHaveValue(/\*\*Markdown original\*\*/);
    await expect(visual).toHaveCount(0);
    await source.fill('**Markdown editado**\n\n| A | B |\n|---|---|\n| 1 | 2 |');
    expect(await firstBlock(page)).toMatchObject({ format: 'markdown', content: '**Markdown editado**\n\n| A | B |\n|---|---|\n| 1 | 2 |' });
});

test('real themed renderer runs accordion, tabs, carousel and shared drawer lifecycle', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error' && !message.text().includes('Blocked form submission')) errors.push(message.text()); });
    await page.goto('/admin/pages/create');
    await page.waitForFunction(() => typeof window.Alpine?.$data(document.querySelector('.admin-block-manager'))?.setBlocks === 'function');
    // Fixture setup uses the registered editor API; all rendering and runtime
    // assertions below go through the authenticated endpoint and actual assets.
    await seedSections(page, [section('acceptance-root', [
            { id: 'acceptance-accordion', type: 'accordion', items: [{ title: 'Pregunta de aceptación', children: [{ id: 'answer', type: 'rich_text', format: 'markdown', content: 'Respuesta de aceptación' }] }] },
            { id: 'acceptance-tabs', type: 'tabs', label: 'Pestañas de aceptación', items: [{ title: 'Uno', children: [{ id: 'one', type: 'heading', text: 'Panel uno' }] }, { title: 'Dos', children: [{ id: 'two', type: 'heading', text: 'Panel dos' }] }] },
            { id: 'acceptance-carousel', type: 'carousel', label: 'Carrusel de aceptación', autoplay: true, items: [{ children: [{ id: 'slide-one', type: 'heading', text: 'Diapositiva primera' }] }, { children: [{ id: 'slide-two', type: 'heading', text: 'Diapositiva segunda' }] }] },
            { id: 'acceptance-drawer', type: 'drawer', overlay_id: 'acceptance-drawer', title: 'Panel manual', trigger_label: 'Abrir panel manual', children: [{ id: 'drawer-text', type: 'rich_text', content: 'Contenido del panel manual', format: 'markdown' }] },
    ])]);
    expect((await openPreview(page)).status()).toBe(200);
    const frame = page.frameLocator('[data-block-preview] iframe');
    await frame.getByText('Pregunta de aceptación', { exact: true }).click();
    await expect(frame.getByText('Respuesta de aceptación', { exact: true })).toBeVisible();
    await frame.getByRole('tab', { name: 'Dos', exact: true }).click();
    await expect(frame.getByRole('heading', { name: 'Panel dos', exact: true })).toBeVisible();
    await frame.getByRole('tab', { name: 'Dos', exact: true }).press('Home');
    await expect(frame.getByRole('tab', { name: 'Uno', exact: true })).toHaveAttribute('aria-selected', 'true');
    const carousel = frame.locator('[data-cms-carousel]');
    await carousel.scrollIntoViewIfNeeded();
    await expect(carousel).toHaveClass(/is-ready/);
    expect(await carousel.locator('[data-carousel-track]').evaluate(node => node.swiper.autoplay.running)).toBe(false);
    await carousel.getByRole('button', { name: 'Diapositiva siguiente', exact: true }).click();
    await expect(carousel.locator('.swiper-slide-active')).toContainText('Diapositiva segunda');
    const trigger = frame.getByRole('button', { name: 'Abrir panel manual', exact: true });
    await trigger.click();
    const drawer = frame.getByRole('dialog', { name: 'Panel manual', exact: true });
    await expect(drawer).toBeVisible();
    expect(await frame.locator('body').evaluate(body => body.style.overflow)).toBe('hidden');
    await drawer.getByRole('button', { name: 'Cerrar panel', exact: true }).click();
    await expect(drawer).toBeHidden();
    expect(await trigger.evaluate(button => document.activeElement === button)).toBe(true);
    expect(await frame.locator('body').evaluate(body => body.style.overflow)).not.toBe('hidden');
    const submission = page.waitForRequest(request => request.url().includes('__dbcms_browser_forbidden_submission'), { timeout: 500 }).catch(() => null);
    await frame.locator('body').evaluate(body => {
        const form = document.createElement('form'); form.method = 'POST'; form.action = '/__dbcms_browser_forbidden_submission'; body.append(form); form.submit();
    });
    expect(await submission).toBeNull();
    const permissions = await page.locator('[data-block-preview] iframe').getAttribute('sandbox');
    expect(permissions).not.toMatch(/allow-forms|allow-top-navigation|allow-popups/);
    expect(errors).toEqual([]);
});

test('countdown date controls keep an explicit UTC offset and preview expiry cannot navigate', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('/admin/pages/create');
    await page.waitForFunction(() => typeof window.Alpine?.$data(document.querySelector('.admin-block-manager'))?.setBlocks === 'function');
    await seedSections(page, [section('countdown-section', [{ id: 'acceptance-countdown', type: 'countdown', mode: 'absolute', deadline: '', duration: 1, finished_text: 'Fin de prueba', persistence: 'memory' }])], [0, 'columns', 0, 'blocks', 0]);
    const fields = manager(page).locator('[data-schema-editor]').first();
    await fields.getByLabel('Fecha ISO 8601 con zona horaria', { exact: true }).fill('2030-01-01T12:00:00');
    await fields.getByLabel('Desplazamiento UTC de la fecha', { exact: true }).selectOption('+02:00');
    expect((await firstBlock(page)).deadline).toMatch(/^2030-01-01T12:00(?::00)?\+02:00$/);
    await fields.getByLabel('Modo', { exact: true }).selectOption('visit');
    await fields.getByLabel('Al finalizar', { exact: true }).selectOption('redirect');
    await fields.getByLabel('Destino al finalizar', { exact: true }).fill('/__dbcms_browser_expired_redirect');
    expect((await openPreview(page)).status()).toBe(200);
    const navigation = page.waitForRequest(request => request.url().includes('__dbcms_browser_expired_redirect'), { timeout: 1800 }).catch(() => null);
    const preview = page.frameLocator('[data-block-preview] iframe');
    await expect(preview.locator('[data-countdown-finished]')).toBeVisible();
    await expect(preview.locator('[data-countdown-finished]')).toHaveText('Fin de prueba');
    expect(await navigation).toBeNull();
    await expect(preview.locator('html')).toHaveAttribute('data-cms-preview', '');
    expect(errors).toEqual([]);
});

test('responsive panels, heading icons, reading progress and single-player playlist use real assets', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/__fixture-animation.json', route => route.fulfill({ json: { v: '5.12.2', fr: 30, ip: 0, op: 30, w: 100, h: 100, assets: [], layers: [{ ddd: 0, ind: 1, ty: 1, sr: 1, sw: 40, sh: 40, sc: '#ff0000', ip: 0, op: 30, st: 0, ks: { o: { a: 0, k: 100 }, r: { a: 0, k: 0 }, p: { a: 0, k: [20, 20, 0] }, a: { a: 0, k: [0, 0, 0] }, s: { a: 0, k: [100, 100, 100] } } }] } }));
    await page.route('**/__fixture-poster-*.svg', route => route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100"><rect width="200" height="100" fill="navy"/></svg>' }));
    await page.route('**/__fixture-*.vtt', route => route.fulfill({ contentType: 'text/vtt', body: 'WEBVTT\n\n00:00.000 --> 00:01.000\nSubtítulo de prueba\n' }));
    await page.route('**/__fixture-track-*.mp4', route => route.fulfill({ contentType: 'video/mp4', body: '' }));
    await page.goto('/admin/pages/create');
    await page.waitForFunction(() => typeof window.Alpine?.$data(document.querySelector('.admin-block-manager'))?.setBlocks === 'function');
    await seedSections(page, [section('responsive-root', [
            { id: 'reading', type: 'progress', mode: 'reading', label: 'Lectura de prueba' },
            { id: 'heading-accordion', type: 'accordion', heading_level: 'h4', icon_style: 'chevron', items: [{ title: 'Ficha extensible', children: [{ id: 'detail', type: 'rich_text', format: 'markdown', content: 'Detalle visible' }] }] },
            { id: 'responsive-tabs', type: 'tabs', label: 'Paneles adaptables', initial_index: 1, responsive_mode: 'accordion', breakpoint: 767, items: [{ title: 'Adaptable uno', children: [{ id: 'tab-one', type: 'heading', text: 'Contenido adaptable uno' }] }, { title: 'Adaptable dos', children: [{ id: 'tab-two', type: 'heading', text: 'Contenido adaptable dos' }] }] },
            { id: 'reverse-animation', type: 'lottie', animation_url: '/__fixture-animation.json', label: 'Animación en sentido inverso', direction: 'reverse', autoplay: false },
            { id: 'playlist', type: 'playlist', kind: 'video', items: [{ title: 'Pista primera', video_url: '/__fixture-track-one.mp4', poster_url: '/__fixture-poster-one.svg', subtitle_url: '/__fixture-one.vtt', subtitle_language: 'es', subtitle_label: 'Español' }, { title: 'Pista segunda', video_url: '/__fixture-track-two.mp4', poster_url: '/__fixture-poster-two.svg', subtitle_url: '/__fixture-two.vtt', subtitle_language: 'en', subtitle_label: 'English' }] },
            ...Array.from({ length: 4 }, (_, index) => ({ id: `reading-space-${index}`, type: 'spacer', height: 400 })),
    ])]);
    expect((await openPreview(page)).status()).toBe(200);
    const preview = page.frameLocator('[data-block-preview] iframe');
    const accordion = preview.locator('[data-cms-accordion]');
    await expect(accordion.locator('summary h4')).toHaveText('Ficha extensible›');
    await accordion.locator('summary').click();
    await expect(accordion.locator('[data-accordion-icon]')).toHaveText('⌄');
    await expect(preview.getByRole('tab', { name: 'Adaptable dos', exact: true })).toHaveAttribute('aria-selected', 'true');
    const content = preview.locator('[data-cms-tabs] [data-tab-content]').first();
    await content.evaluate(node => { node.dataset.acceptanceRetained = 'same-node'; });
    await manager(page).getByRole('group', { name: 'Tamaño de vista previa' }).getByRole('button', { name: 'Móvil', exact: true }).click();
    const first = preview.getByRole('button', { name: 'Adaptable uno', exact: true });
    await expect(first).toBeVisible(); await first.click();
    await expect(preview.getByRole('heading', { name: 'Contenido adaptable uno', exact: true })).toBeVisible();
    await expect(content).toHaveAttribute('data-acceptance-retained', 'same-node');
    await manager(page).getByRole('group', { name: 'Tamaño de vista previa' }).getByRole('button', { name: 'Escritorio', exact: true }).click();
    await expect(preview.getByRole('tab', { name: 'Adaptable uno', exact: true })).toHaveAttribute('aria-selected', 'true');
    const animation = preview.locator('[data-cms-lottie]'); await animation.scrollIntoViewIfNeeded(); await expect(animation.locator('svg')).toBeVisible(); await expect(animation).toHaveAttribute('data-direction', 'reverse'); await animation.locator('[data-lottie-toggle]').click(); await expect(animation.locator('[data-lottie-toggle]')).toHaveAttribute('aria-pressed', 'true');
    const playlist = preview.locator('[data-cms-playlist]');
    await expect(playlist.locator('video')).toHaveCount(1);
    await expect(playlist.locator('video')).not.toHaveAttribute('autoplay', '');
    await playlist.getByRole('button', { name: 'Pista siguiente', exact: true }).click();
    await expect(playlist.locator('video')).toHaveAttribute('src', '/__fixture-track-two.mp4');
    await expect(playlist.locator('video')).toHaveAttribute('poster', '/__fixture-poster-two.svg');
    await expect(playlist.locator('track')).toHaveAttribute('srclang', 'en');
    await playlist.getByRole('button', { name: 'Pista primera', exact: true }).click();
    await expect(playlist.locator('video')).toHaveAttribute('src', '/__fixture-track-one.mp4');
    await preview.locator('body').evaluate(body => window.scrollTo(0, body.scrollHeight));
    await expect.poll(async () => Number(await preview.locator('[data-cms-reading-progress] progress').getAttribute('aria-valuenow'))).toBe(100);
    expect(errors).toEqual([]);
});

test('authored drawer aliases stay scoped and support bottom panels without backdrop dismissal', async ({ page }) => {
    await page.goto('/admin/pages/create');
    await page.waitForFunction(() => typeof window.Alpine?.$data(document.querySelector('.admin-block-manager'))?.setBlocks === 'function');
    await seedSections(page, [section('alias-root', [], {
        columns_layout: '2',
        columns: ['A', 'B'].map(name => column(`scope-${name}`, [
            { id: `open-${name}`, type: 'button', action: 'open_overlay', overlay_id: 'shared-alias', label: `Abrir alias ${name}` },
            { id: `drawer-${name}`, type: 'drawer', overlay_id: 'shared-alias', title: `Panel alias ${name}`, trigger_label: `Abrir propio ${name}`, side: 'bottom', backdrop: false, backdrop_close: false, children: [{ id: `close-${name}`, type: 'button', action: 'close_overlay', overlay_id: 'shared-alias', label: `Cerrar alias ${name}` }] },
        ])),
    })]);
    expect((await openPreview(page)).status()).toBe(200);
    const preview = page.frameLocator('[data-block-preview] iframe');
    const identifiers = await preview.locator('[data-cms-overlay]').evaluateAll(nodes => nodes.map(node => node.dataset.overlayId));
    expect(new Set(identifiers).size).toBe(2);
    await manager(page).getByRole('button', { name: 'Ampliar editor', exact: true }).click();
    await preview.getByRole('button', { name: 'Abrir alias B', exact: true }).click();
    const second = preview.getByRole('dialog', { name: 'Panel alias B', exact: true }); await expect(second).toBeVisible();
    await expect(second).toHaveClass(/cms-drawer-bottom/);
    await second.click({ position: { x: 5, y: 5 } }); await expect(second).toBeVisible();
    await second.getByRole('button', { name: 'Cerrar alias B', exact: true }).click(); await expect(second).toBeHidden();
    await preview.getByRole('button', { name: 'Abrir alias A', exact: true }).click();
    const first = preview.getByRole('dialog', { name: 'Panel alias A', exact: true }); await expect(first).toBeVisible();
    await expect(second).toBeHidden();
    await first.getByRole('button', { name: 'Cerrar alias A', exact: true }).click(); await expect(first).toBeHidden();
});

test('automatic overlays wait for real consent readiness and visible privacy surfaces', async ({ page }) => {
    await page.addInitScript(() => {
        if (window !== window.top) return;
        window.dbcmsConsent = { allows: () => false, ready: new Promise(resolve => { window.__acceptanceConsentReady = resolve; }) };
    });
    await page.goto('/admin/pages/create');
    await page.waitForFunction(() => typeof window.Alpine?.$data(document.querySelector('.admin-block-manager'))?.setBlocks === 'function');
    await page.evaluate(() => {
        const host = document.createElement('div');
        host.innerHTML = '<button id="auto-return">Retorno</button><div id="privacy-parent" style="display:none"><div data-consent-dialog role="dialog">Preferencias</div></div><div data-cms-overlay data-overlay-id="automatic-acceptance" hidden aria-label="Aviso automático"><button data-cms-overlay-close>Cerrar automático</button></div>';
        document.body.append(host); document.getElementById('auto-return').focus();
        window.__acceptanceAutomatic = () => window.dispatchEvent(new CustomEvent('cms-overlay-open', { detail: { id: 'automatic-acceptance', automatic: true } }));
        window.addEventListener('cms-overlays-ready', window.__acceptanceAutomatic);
        window.__acceptanceAutomatic();
    });
    const overlay = page.locator('[data-overlay-id="automatic-acceptance"]'); await expect(overlay).toBeHidden();
    await page.evaluate(() => window.__acceptanceConsentReady({ preferences: { necessary: true } }));
    await expect(overlay).toBeVisible();
    await page.evaluate(() => window.removeEventListener('cms-overlays-ready', window.__acceptanceAutomatic));
    await page.getByRole('button', { name: 'Cerrar automático', exact: true }).click();
    await page.evaluate(() => { document.getElementById('privacy-parent').style.display = 'block'; window.addEventListener('cms-overlays-ready', window.__acceptanceAutomatic); window.__acceptanceAutomatic(); });
    await expect(overlay).toBeHidden();
    await page.evaluate(() => { document.getElementById('privacy-parent').style.display = 'none'; });
    await expect(overlay).toBeVisible();
    await page.evaluate(() => window.removeEventListener('cms-overlays-ready', window.__acceptanceAutomatic));
    await page.getByRole('button', { name: 'Cerrar automático', exact: true }).click(); await expect(overlay).toBeHidden();
    await expect(page.locator('#auto-return')).toBeFocused();
    const wakeups = await page.evaluate(async () => {
        let count = 0; const listener = () => count++;
        window.addEventListener('cms-overlays-ready', listener);
        const animated = document.createElement('div'); document.body.append(animated);
        for (let index = 0; index < 100; index++) animated.style.transform = `translateX(${index}px)`;
        await new Promise(resolve => requestAnimationFrame(resolve));
        animated.remove(); window.removeEventListener('cms-overlays-ready', listener);
        return count;
    });
    expect(wakeups).toBe(0);

});
