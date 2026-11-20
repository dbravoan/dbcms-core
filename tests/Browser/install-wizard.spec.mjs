import { test, expect } from '@playwright/test';

// Installation owns environment files and its lock: use an explicitly disposable,
// uninstalled server. Valid final submissions below are intercepted in the browser.
if (process.env.DBCMS_BROWSER_INSTALLER !== '1') throw new Error('Requires DBCMS_BROWSER_INSTALLER=1 and a disposable uninstalled application.');
test.use({ storageState: { cookies: [], origins: [] } });
let operatorState;

test.beforeAll(async ({ browser }) => {
    const installCode = process.env.DBCMS_BROWSER_INSTALL_TOKEN;
    expect(installCode, 'Supply the disposable installer code').toMatch(/^[a-f0-9]{64}$/);
    const context = await browser.newContext({ baseURL: process.env.DBCMS_BROWSER_BASE_URL });
    try {
        const page = await context.newPage();
        await page.goto('/install/access');
        await page.getByLabel('Código de instalación').fill(installCode);
        await Promise.all([
            page.waitForURL('**/install'),
            page.getByRole('button', { name: 'Abrir el asistente' }).click(),
        ]);
        operatorState = await context.storageState();
    } finally {
        await context.close();
    }
});

const panel = (page, step) => page.locator(`[data-install-step="${step}"]`);
const input = (page, name) => page.locator(`[name="${name}"]`).filter({ visible: true });
const flag = (page, name) => page.locator(`input[type="checkbox"][name="company[${name}]"]`);
const next = page => page.locator('#install-next').click();
const previous = page => page.locator('#install-previous').click();
const errors = new WeakMap();
const password = 'private-installer-test-password';

async function fillSite(page) {
    await input(page, 'site_name').fill('Sitio de prueba');
    await input(page, 'app_url').fill('https://installer.example.test');
    await input(page, 'admin_path').fill('gestion');
}

async function fillAdmin(page) {
    await input(page, 'name').fill('Persona administradora de prueba');
    await input(page, 'email').fill('installer@example.test');
    await input(page, 'password').fill(password);
    await input(page, 'password_confirmation').fill(password);
}

async function fillController(page) {
    for (const [name, value] of Object.entries({
        company_name: 'Persona titular de prueba', tax_id: '12345678Z', activity: 'Servicios de prueba',
        address: 'Calle de prueba 1, 28001 Madrid', country: 'ES',
        contact_email: 'contact@example.test', privacy_email: 'privacy@example.test',
    })) await input(page, `company[${name}]`).fill(value);
}

async function fillPrivacy(page) {
    await input(page, 'company[hosting_provider]').fill('Alojamiento de prueba');
    await input(page, 'company[hosting_country]').fill('ES');
    for (const [index, owner] of ['core', 'forms', 'executions'].entries()) {
        for (const [name, value] of Object.entries({
            owner, purpose: 'Entrega técnica del sitio solicitado', legal_basis: 'Interés legítimo en la seguridad técnica',
            data: 'Datos de sesión y seguridad necesarios', retention: 'Sesiones durante su vigencia; registros de seguridad durante 30 días',
            retention_days: '30', recipients: 'Titular y alojamiento de prueba', transfers: 'Sin transferencias en esta prueba',
        })) await input(page, `company[processing][${index}][${name}]`).fill(value);
    }
    await flag(page, 'reviewed').check();
}

async function atStep(page, step) {
    await expect(panel(page, step)).toBeVisible();
    for (const other of ['site', 'admin', 'controller', 'privacy'].filter(value => value !== step)) await expect(panel(page, other)).toBeHidden();
}

async function goToController(page) {
    await fillSite(page); await next(page); await fillAdmin(page); await next(page);
    await atStep(page, 'controller');
}

async function goToPrivacy(page) {
    await goToController(page); await fillController(page); await next(page);
    await atStep(page, 'privacy');
}

async function interceptFinish(page) {
    const submissions = [];
    await page.route('**/install/admin/store', async route => {
        submissions.push(new URLSearchParams(route.request().postData()));
        await route.fulfill({ contentType: 'text/html; charset=utf-8', body: '<!doctype html><meta charset="utf-8"><h1>Envío de instalación interceptado</h1>' });
    });
    return submissions;
}

test.beforeEach(async ({ page }) => {
    const messages = []; errors.set(page, messages);
    page.on('pageerror', error => messages.push(error.message));
    await page.route('**/favicon.ico', route => route.fulfill({ status: 204 }));
    await page.context().addCookies(operatorState.cookies);
    await page.goto('/install/admin');
    await expect(page.locator('#install-next')).toBeVisible();
    await atStep(page, 'site');
});
test.afterEach(async ({ page }) => expect(errors.get(page)).toEqual([]));

test('installer validates each panel preserves live values and never submits Enter before the final panel', async ({ page }) => {
    const submissions = await interceptFinish(page);
    await input(page, 'site_name').fill(''); await next(page);
    await atStep(page, 'site'); await expect(input(page, 'site_name')).toBeFocused();
    await input(page, 'site_name').press('Enter'); expect(submissions).toHaveLength(0);
    await fillSite(page); await next(page);
    await atStep(page, 'admin'); await expect(panel(page, 'admin').getByRole('heading').first()).toBeFocused();
    await expect(input(page, 'name')).toHaveValue(''); await expect(input(page, 'email')).toHaveValue('');
    await next(page); await atStep(page, 'admin'); await expect(input(page, 'name')).toBeFocused();
    await fillAdmin(page); await input(page, 'password_confirmation').fill('different-test-password');
    await next(page); await atStep(page, 'admin'); await expect(input(page, 'password_confirmation')).toBeFocused();
    await input(page, 'password_confirmation').fill(password); await previous(page); await atStep(page, 'site');
    await expect(input(page, 'site_name')).toHaveValue('Sitio de prueba');
    await expect(input(page, 'admin_path')).toHaveValue('gestion');
    await next(page); await expect(input(page, 'password')).toHaveValue(password);
    await expect(input(page, 'password_confirmation')).toHaveValue(password);
    await next(page); await atStep(page, 'controller'); await previous(page);
    await input(page, 'email').press('Enter'); expect(submissions).toHaveLength(0);
    const stored = await page.evaluate(() => [localStorage, sessionStorage].flatMap(storage => Object.keys(storage).map(key => storage.getItem(key))));
    expect(stored.join('\n')).not.toContain(password);
    await page.reload(); await expect(page.locator('#install-next')).toBeVisible();
    await fillSite(page); await next(page); await expect(input(page, 'password')).toHaveValue('');
});

test('individual controller details require registry and DPO only when their flags are selected', async ({ page }) => {
    await goToController(page); await fillController(page);
    const registry = page.locator('[name="company[commercial_registry]"]');
    const dpo = page.locator('[name="company[dpo_contact]"]');
    const nif = input(page, 'company[tax_id]');
    await expect(registry).toBeHidden(); await expect(dpo).toBeHidden();
    await expect(registry).not.toHaveAttribute('required', ''); await expect(dpo).not.toHaveAttribute('required', '');
    await flag(page, 'commercial_activity').check(); await expect(nif).toHaveJSProperty('required', true);
    await nif.fill(''); await next(page); await atStep(page, 'controller'); await expect(nif).toBeFocused(); await nif.fill('12345678Z');
    await panel(page, 'controller').locator('summary').click();
    await flag(page, 'registry_required').check(); await expect(registry).toBeVisible(); await expect(registry).toHaveJSProperty('required', true);
    await next(page); await expect(registry).toBeFocused(); await registry.fill('Inscripción de prueba conservada');
    await flag(page, 'dpo_required').check(); await expect(dpo).toBeVisible(); await expect(dpo).toHaveJSProperty('required', true);
    await next(page); await expect(dpo).toBeFocused(); await dpo.fill('dpo@example.test');
    await flag(page, 'registry_required').uncheck(); await flag(page, 'dpo_required').uncheck();
    await expect(registry).toBeHidden(); await expect(dpo).toBeHidden();
    await expect(registry).toHaveJSProperty('required', false); await expect(dpo).toHaveJSProperty('required', false);
    await next(page); await atStep(page, 'privacy'); await previous(page);
    await flag(page, 'registry_required').check(); await flag(page, 'dpo_required').check();
    await expect(registry).toHaveValue('Inscripción de prueba conservada'); await expect(dpo).toHaveValue('dpo@example.test');
});

test('final submit validates every panel focuses the first invalid one and submits all original fields once', async ({ page }) => {
    const submissions = await interceptFinish(page);
    await goToPrivacy(page); await fillPrivacy(page);
    // A final submission must inspect earlier panels too, even if their values
    // changed while hidden (for example through browser autofill).
    await page.locator('[name="name"]').evaluate(element => { element.value = ''; });
    await page.locator('#install-finish').click();
    await atStep(page, 'admin'); await expect(input(page, 'name')).toBeFocused(); expect(submissions).toHaveLength(0);
    await input(page, 'name').fill('Persona administradora corregida'); await next(page); await next(page);
    await expect(flag(page, 'reviewed')).toBeChecked(); await page.locator('#install-finish').click();
    await expect(page.getByRole('heading', { name: 'Envío de instalación interceptado' })).toBeVisible();
    expect(submissions).toHaveLength(1);
    const values = submissions[0];
    expect(values.get('name')).toBe('Persona administradora corregida'); expect(values.get('password')).toBe(password);
    expect(values.get('site_name')).toBe('Sitio de prueba'); expect(values.get('admin_path')).toBe('gestion');
    expect(values.get('company[tax_id]')).toBe('12345678Z'); expect(values.get('company[reviewed]')).toBe('1');
    expect(values.get('company[processing][0][owner]')).toBe('core'); expect(values.get('company[processing][0][retention_days]')).toBe('30');
    expect(values.get('company[processing][1][owner]')).toBe('forms'); expect(values.get('company[processing][2][owner]')).toBe('executions');
    expect(values.get('brand')).toBeTruthy(); expect(values.get('front_theme')).toBeTruthy(); expect(values.get('_token')).toBeTruthy();
});

test('server DTO validation returns to the responsible controller panel without repopulating passwords', async ({ page }) => {
    await goToPrivacy(page); await fillPrivacy(page);
    const action = await page.locator('#install-form').getAttribute('action');
    const values = await page.locator('#install-form').evaluate(form => Object.fromEntries(new FormData(form)));
    values['company[registry_required]'] = '1'; values['company[commercial_registry]'] = '';
    const response = await page.request.post(action, { form: values, maxRedirects: 0, headers: { Referer: page.url() } });
    expect(response.status()).toBe(302);
    await page.goto('/install/admin'); await atStep(page, 'controller');
    await expect(flag(page, 'registry_required')).toBeChecked();
    await expect(input(page, 'company[company_name]')).toHaveValue('Persona titular de prueba');
    await expect(input(page, 'company[commercial_registry]')).toBeVisible();
    await previous(page); await expect(input(page, 'password')).toHaveValue(''); await expect(input(page, 'password_confirmation')).toHaveValue('');
});

test('without JavaScript all four panels and privacy processing fields support one normal form submission', async ({ browser, baseURL }) => {
    const context = await browser.newContext({ baseURL, javaScriptEnabled: false, storageState: operatorState });
    try {
        const page = await context.newPage(); const submissions = await interceptFinish(page);
        await page.goto('/install/admin');
        for (const step of ['site', 'admin', 'controller', 'privacy']) await expect(panel(page, step)).toBeVisible();
        await fillSite(page); await fillAdmin(page); await fillController(page); await fillPrivacy(page);
        await page.locator('#install-finish').click();
        await expect(page.getByRole('heading', { name: 'Envío de instalación interceptado' })).toBeVisible();
        expect(submissions).toHaveLength(1);
        expect(submissions[0].get('company[processing][0][purpose]')).toBe('Entrega técnica del sitio solicitado');
        expect(submissions[0].get('company[reviewed]')).toBe('1');
    } finally { await context.close(); }
});

test('small screens retain labelled step navigation and fit within the viewport', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    for (const name of ['Sitio', 'Administrador', 'Titular', 'Privacidad']) await expect(page.getByRole('button', { name: new RegExp(`(?:^|\\s)${name}$`) })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    const overflowingLabels = await page.getByRole('navigation', { name: 'Progreso de la instalación' }).locator('.grid > div').evaluateAll(cells => cells.flatMap(cell => {
        const bounds = cell.getBoundingClientRect(); const range = document.createRange(); range.selectNodeContents(cell);
        return [...range.getClientRects()].some(rect => rect.left < bounds.left - 1 || rect.right > bounds.right + 1) ? [cell.textContent.trim()] : [];
    }));
    expect(overflowingLabels).toEqual([]);
    await fillSite(page); await next(page); await atStep(page, 'admin');
    await expect(panel(page, 'admin').getByRole('heading').first()).toBeFocused();
    await previous(page); await expect(panel(page, 'site').getByRole('heading').first()).toBeFocused();
});
