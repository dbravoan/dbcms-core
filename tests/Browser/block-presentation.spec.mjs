import { test, expect } from '@playwright/test';
import { readFileSync, statSync } from 'node:fs';

// These tests use real private PHP previews and the built public bundle. They
// never save an editor draft or replace the rendering/runtime implementation.
const frameId = 'presentation-acceptance';
const errors = new WeakMap();
const spacer = id => ({ id, type: 'spacer', height: 400 });
const heading = text => ({ id: 'motion-title', type: 'heading', text });
const accordion = (id, children, options = {}) => ({
    id, type: 'accordion', ...options, items: [{ title: 'Presentación', open: true, children }],
});
const composition = target => accordion('motion-composition', [
    spacer('before-one'), spacer('before-two'), spacer('before-three'), target,
    spacer('after-one'), spacer('after-two'), spacer('after-three'),
]);
const surface = (frame, id) => frame.locator(`[data-cms-presentation="block"][data-cms-node="${id}"]`);
const entrance = root => root.locator(':scope > [data-cms-presentation-entrance]');
const ownedLayer = root => root.locator(':scope > [data-cms-presentation-media] [data-cms-presentation-motion]');
const runningAnimations = target => target.evaluate(node => node.getAnimations().filter(animation => animation.playState === 'running').map(animation => animation.animationName));
const scrollTo = (root, offset = 200) => root.evaluate((node, top) => window.scrollTo({ top: window.scrollY + node.getBoundingClientRect().top - top, behavior: 'instant' }), offset);
const matrixY = target => target.evaluate(node => {
    const value = getComputedStyle(node).transform;
    return value === 'none' ? null : new DOMMatrixReadOnly(value).m42;
});
const authoredPlacement = target => target.evaluate(node => {
    const style = getComputedStyle(node), matrix = new DOMMatrixReadOnly(style.transform);
    return { angle: Math.atan2(matrix.b, matrix.a) * 180 / Math.PI, opacity: Number(style.opacity) };
});
function requireVideo() {
    const filename = process.env.DBCMS_PRESENTATION_VIDEO_FIXTURE;
    expect(filename, 'Supply a disposable tiny H264/yuv420p MP4 via DBCMS_PRESENTATION_VIDEO_FIXTURE').toBeTruthy();
    expect(statSync(filename).size).toBeGreaterThan(0);
    expect(statSync(filename).size).toBeLessThan(2 * 1024 * 1024);
    return readFileSync(filename);
}
async function preview(page, target) {
    const response = await page.evaluate(async payload => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const request = await fetch('/admin/blocks/preview', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ type: 'accordion', entity_type: 'page', market: 'es', locale: 'es', payload }) });
        return { status: request.status, body: await request.json() };
    }, composition(target));
    expect(response.status, JSON.stringify(response.body)).toBe(200);
    expect(response.body.html).toContain('data-cms-preview');
    await page.evaluate(({ html, id }) => {
        // srcdoc inherits the host CSP. Match the existing preview host's nonce
        // transport without weakening CSP or replacing any script/runtime.
        const nonce = document.querySelector('script[nonce]')?.nonce;
        if (!nonce) throw new Error('The authenticated preview host needs its normal CSP nonce.');
        html = html.replace(/(\snonce\s*=\s*)(["'])[^"']*\2/gi, (_attribute, prefix, quote) => `${prefix}${quote}${nonce}${quote}`);
        const frame = document.createElement('iframe'); frame.id = id; frame.title = 'Presentation acceptance';
        frame.setAttribute('sandbox', 'allow-scripts allow-same-origin');
        frame.style.cssText = 'position:fixed;inset:0;width:100%;height:100%;border:0;z-index:2147483647;background:white';
        frame.srcdoc = html; document.body.replaceChildren(frame);
    }, { html: response.body.html, id: frameId });
    const handle = await page.locator(`#${frameId}`).elementHandle();
    const frame = await handle.contentFrame();
    await expect(frame.locator('html')).toHaveAttribute('data-cms-preview', '');
    await expect(surface(frame, target.id)).toBeVisible();
    return frame;
}
const replay = page => page.evaluate(id => document.getElementById(id).contentWindow.postMessage({ source: 'cms-block-preview', type: 'replay' }, window.location.origin), frameId);
const videoTarget = () => accordion('background-video', [heading('Owned background video')], { bg_type: 'video', bg_video_url: '/__presentation-video.mp4', appearance: { base: { min_height: '360px' } } });

test.beforeAll(() => {
    expect(process.env.DBCMS_BROWSER_BASE_URL, 'Supply the disposable authenticated application URL').toBeTruthy();
    expect(process.env.DBCMS_BROWSER_STORAGE_STATE, 'Supply an existing disposable admin storage state').toBeTruthy();
});
test.beforeEach(async ({ page }) => {
    const messages = []; errors.set(page, messages);
    page.on('pageerror', error => messages.push(error.message));
    page.on('console', message => { if (message.type() === 'error' && !message.text().includes('Blocked form submission')) messages.push(message.text()); });
    await page.route('**/favicon.ico', route => route.fulfill({ status: 204 }));
    await page.route('**/__presentation-image.svg', route => route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100"><rect width="200" height="100" fill="navy"/></svg>' }));
    await page.route('**/__presentation-video.mp4', route => {
        const bytes = requireVideo();
        const range = /^bytes=(\d+)-(\d*)$/.exec(route.request().headers().range || '');
        const start = range ? Number(range[1]) : 0;
        const end = range && range[2] ? Math.min(Number(range[2]), bytes.length - 1) : bytes.length - 1;
        const body = bytes.subarray(start, end + 1);
        return route.fulfill({ status: range ? 206 : 200, contentType: 'video/mp4', headers: { 'Accept-Ranges': 'bytes', 'Content-Length': String(body.length), 'Cache-Control': 'no-store', ...(range ? { 'Content-Range': `bytes ${start}-${end}/${bytes.length}` } : {}) }, body });
    });
    await page.addInitScript(() => { if (window === window.top) localStorage.setItem('dbcms_tour_completed', 'true'); });
    const response = await page.goto('/admin');
    expect(response.status()).toBe(200);
    await expect(page.locator('meta[name="csrf-token"]')).toHaveCount(1);
});
test.afterEach(async ({ page }) => expect(errors.get(page)).toEqual([]));

test('parallax changes the computed owned background transform while nested image styles stay intact', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    const frame = await preview(page, accordion('parallax', [heading('Parallax'), { id: 'nested-image', type: 'image', image_url: '/__presentation-image.svg', image_alt: 'Unmodified nested image' }], { bg_type: 'image', bg_image_url: '/__presentation-image.svg', bg_behavior: 'parallax', appearance: { base: { min_height: '400px' } } }));
    const root = surface(frame, 'parallax'), layer = ownedLayer(root);
    const image = root.getByAltText('Unmodified nested image');
    await scrollTo(root, 250);
    await expect.poll(() => matrixY(layer)).not.toBeNull();
    const before = await matrixY(layer);
    const original = await image.evaluate(node => ({ transform: getComputedStyle(node).transform, style: node.getAttribute('style') }));
    await scrollTo(root, 30);
    await expect.poll(async () => Math.abs((await matrixY(layer)) - before)).toBeGreaterThan(1);
    expect(await image.evaluate(node => ({ transform: getComputedStyle(node).transform, style: node.getAttribute('style') }))).toEqual(original);
    expect(await layer.evaluate(node => getComputedStyle(node).backgroundImage)).toContain('__presentation-image.svg');
});

test('real flip keyframes replay in srcdoc while authored placement and opacity survive animation completion', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    const frame = await preview(page, accordion('flip', [heading('A real flip')], { animation: 'flipInX', animation_duration: '2s', appearance: { base: { transform: 'rotate(12deg)', opacity: '.4', min_height: '300px' } } }));
    const root = surface(frame, 'flip'), target = entrance(root);
    await scrollTo(root);
    await expect.poll(() => runningAnimations(target)).toContain('cms-flipInX');
    const frames = await target.evaluate(node => node.getAnimations().find(animation => animation.animationName === 'cms-flipInX').effect.getKeyframes().map(frame => frame.transform));
    expect(new Set(frames).size).toBeGreaterThan(2);
    expect(frames.some(value => /rotateX|matrix3d/.test(value))).toBe(true);
    const running = await authoredPlacement(root); expect(running.angle).toBeCloseTo(12, 3); expect(running.opacity).toBe(.4);
    await expect.poll(() => runningAnimations(target)).toEqual([]);
    const finished = await authoredPlacement(root); expect(finished.angle).toBeCloseTo(12, 3); expect(finished.opacity).toBe(.4);
    expect(await target.evaluate(node => getComputedStyle(node).transform)).toBe('none');
    // A message originating in the iframe is not an authorized parent replay.
    await frame.evaluate(() => window.postMessage({ source: 'cms-block-preview', type: 'replay' }, '*'));
    await frame.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    expect(await runningAnimations(target)).toEqual([]);
    await replay(page);
    await expect.poll(() => runningAnimations(target)).toContain('cms-flipInX');
    const replayed = await authoredPlacement(root); expect(replayed.angle).toBeCloseTo(12, 3); expect(replayed.opacity).toBe(.4);
});

test('reduced motion leaves computed media still and starts no entrance animation', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const frame = await preview(page, accordion('reduced', [heading('Readable without motion')], { bg_type: 'image', bg_image_url: '/__presentation-image.svg', bg_behavior: 'parallax', animation: 'bounceIn', animation_duration: '5s', appearance: { base: { min_height: '400px' } } }));
    const root = surface(frame, 'reduced'), layer = ownedLayer(root);
    await scrollTo(root, 250);
    expect(await layer.evaluate(node => getComputedStyle(node).transform)).toBe('none');
    expect(await runningAnimations(entrance(root))).toEqual([]);
    await scrollTo(root, 30);
    expect(await layer.evaluate(node => getComputedStyle(node).transform)).toBe('none');
    await replay(page);
    await frame.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    expect(await runningAnimations(entrance(root))).toEqual([]);
});

test('a real background video plays only in view and pauses offscreen, on reduced motion and after removal', async ({ page }) => {
    requireVideo();
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    const frame = await preview(page, videoTarget());
    const root = surface(frame, 'background-video'), video = ownedLayer(root);
    expect(await video.evaluate(node => node.paused)).toBe(true);
    await scrollTo(root);
    await expect.poll(() => video.evaluate(node => node.readyState)).toBeGreaterThanOrEqual(2);
    await expect.poll(() => video.evaluate(node => node.paused)).toBe(false);
    await expect.poll(() => video.evaluate(node => node.currentTime)).toBeGreaterThan(.08);
    await frame.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await expect.poll(() => video.evaluate(node => node.paused)).toBe(true);
    const stopped = await video.evaluate(node => node.currentTime);
    await page.waitForTimeout(300);
    expect(Math.abs((await video.evaluate(node => node.currentTime)) - stopped)).toBeLessThan(.02);
    await scrollTo(root);
    await expect.poll(() => video.evaluate(node => node.paused)).toBe(false);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect.poll(() => video.evaluate(node => node.paused)).toBe(true);
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await expect.poll(() => video.evaluate(node => node.paused)).toBe(false);
    await video.evaluate(node => { window.removedBackgroundForAcceptance = node; node.closest('[data-cms-node="background-video"]').remove(); });
    await expect.poll(() => frame.evaluate(() => window.removedBackgroundForAcceptance.paused)).toBe(true);
    expect(await frame.evaluate(() => window.removedBackgroundForAcceptance.isConnected)).toBe(false);
});

test('live consent withdrawal stops an actual owned player and a current grant resumes it', async ({ page }) => {
    requireVideo();
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    const frame = await preview(page, videoTarget());
    const root = surface(frame, 'background-video'), video = ownedLayer(root);
    await scrollTo(root);
    await expect.poll(() => video.evaluate(node => node.paused)).toBe(false);
    // Use a local finite file: PHP tests cover withholding undeclared external
    // sources. Here the shared bridge exercises live player authorization only.
    await video.evaluate(node => {
        window.backgroundGrantForAcceptance = false;
        window.dbcmsConsent = { allows: category => category === 'external' && window.backgroundGrantForAcceptance };
        const gate = document.createElement('div'); gate.dataset.cmsConsent = 'external'; node.before(gate); gate.append(node);
        window.dispatchEvent(new CustomEvent('cookie-consent-updated'));
    });
    await expect.poll(() => video.evaluate(node => node.paused)).toBe(true);
    await frame.evaluate(() => { window.backgroundGrantForAcceptance = true; window.dispatchEvent(new CustomEvent('cookie-consent-updated')); });
    await expect.poll(() => video.evaluate(node => node.paused)).toBe(false);
    await expect.poll(() => video.evaluate(node => node.currentTime)).toBeGreaterThan(.08);
    await frame.evaluate(() => { window.backgroundGrantForAcceptance = false; window.dispatchEvent(new CustomEvent('cookie-consent-updated')); });
    await expect.poll(() => video.evaluate(node => node.paused)).toBe(true);
    const stopped = await video.evaluate(node => node.currentTime);
    await page.waitForTimeout(300);
    expect(Math.abs((await video.evaluate(node => node.currentTime)) - stopped)).toBeLessThan(.02);
});
