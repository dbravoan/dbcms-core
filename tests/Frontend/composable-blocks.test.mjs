import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { clamp, counterValue, countdownDeadline, remainingTime, validAnimationData, carouselBreakpoints, countdownExpiry, visitCountdownState, readingProgress, playlistOrder, nextTrackIndex, lottieDirection, initialize, destroy } from '../../resources/js/native-blocks.js';

test('counters have deterministic final values, including negative and fractional metrics', () => {
    assert.equal(counterValue(0, 42, 0), 0);
    assert.equal(counterValue(0, 42, 1), 42);
    assert.equal(counterValue(10, -2.5, 1), -2.5);
    assert.ok(counterValue(0, 42, .5) > 21);
    assert.equal(clamp('unsafe', 0, 100, 50), 50);
});

test('absolute countdowns require an explicit timezone and expire without negative units', () => {
    const utc = countdownDeadline({ mode: 'absolute', deadline: '2030-01-01T12:00:00Z' });
    const offset = countdownDeadline({ mode: 'absolute', deadline: '2030-01-01T13:00:00+01:00' });
    assert.equal(utc, offset);
    assert.equal(countdownDeadline({ mode: 'absolute', deadline: '2030-01-01T12:00:00' }), null);
    assert.equal(countdownDeadline({ mode: 'absolute', deadline: 'not a date' }), null);
    assert.equal(countdownDeadline({ mode: 'absolute', deadline: '2030-02-31T12:00:00Z' }), null);
    assert.equal(countdownDeadline({ mode: 'absolute', deadline: '2030-01-01T24:00:00Z' }), null);
    assert.deepEqual(remainingTime(1000, 2000), { total: 0, days: 0, hours: 0, minutes: 0, seconds: 0 });
    assert.deepEqual(remainingTime(90061000, 0), { total: 90061, days: 1, hours: 1, minutes: 1, seconds: 1 });
});

test('visit countdowns restart from the provided visit clock without persisted visitor state', () => {
    assert.equal(countdownDeadline({ mode: 'visit', duration: 90 }, 1000), 91000);
    assert.equal(countdownDeadline({ mode: 'visit', duration: 90 }, 10000), 100000);
});

test('Lottie accepts self-contained vectors and refuses expressions, linked assets and fonts', () => {
    const vector = { v: '5.12.2', fr: 30, layers: [{ ty: 4, shapes: [{ ty: 'el', p: { k: [0, 0] } }] }] };
    assert.equal(validAnimationData(vector), true);
    assert.equal(validAnimationData({ ...vector, assets: [{ p: 'image.png', u: 'https://third-party.test/' }] }), false);
    assert.equal(validAnimationData({ ...vector, layers: [{ ks: { p: { x: 'fetch("https://third-party.test")' } } }] }), false);
    assert.equal(validAnimationData({ ...vector, fonts: { list: [{ fPath: 'https://third-party.test/font.woff2' }] } }), false);
    assert.equal(validAnimationData({ ...vector, assets: [{ p: 'data:image/png;base64,abc' }] }), true);
    assert.equal(validAnimationData({ ...vector, assets: [{ p: 'data:image/svg+xml;base64,abc' }] }), false);
    assert.equal(validAnimationData(null), false);
});

test('package runtime preserves lazy loading, lifecycle and consent boundaries', async () => {
    const source = await readFile(new URL('../../resources/js/native-blocks.js', import.meta.url), 'utf8');
    assert.match(source, /import\('swiper'\)/);
    assert.match(source, /import\('swiper\/modules'\)/);
    assert.match(source, /lottie_light\.js/);
    assert.match(source, /instance\.destroy\(true, true\)/);
    assert.match(source, /redirect: 'error'/);
    assert.match(source, /source\.origin !== documentOrigin/);
    assert.match(source, /authorizedStorage = mode === 'session' \? window\.sessionStorage : window\.localStorage/);
    assert.doesNotMatch(source, /document\.cookie\s*=/);
});

class Element {
    constructor(attributes = {}, queries = {}) {
        this.attributes = new Map(Object.entries(attributes)); this.queries = queries; this.handlers = new Map(); this.classList = { toggle() {}, remove() {} }; this.dataset = {}; this.hidden = false; this.tabIndex = 0;
    }
    hasAttribute(key) { return this.attributes.has(key); }
    setAttribute(key, value) { this.attributes.set(key, value); }
    getAttribute(key) { return this.attributes.get(key); }
    removeAttribute(key) { this.attributes.delete(key); }
    matches() { return this.hasAttribute('data-cms-tabs') || this.hasAttribute('data-cms-accordion'); }
    querySelector(key) { return this.queries[key]?.[0] ?? null; }
    querySelectorAll(key) { return this.queries[key] ?? []; }
    addEventListener(key, callback) { this.handlers.set(key, [...(this.handlers.get(key) ?? []), callback]); }
    removeEventListener(key, callback) { this.handlers.set(key, (this.handlers.get(key) ?? []).filter(value => value !== callback)); }
    dispatch(key, values = {}) { const event = { preventDefault() {}, ...values }; for (const callback of this.handlers.get(key) ?? []) callback(event); }
    focus() { this.focused = true; }
}

test('tabs support arrow, Home and End navigation and restore readable panels on destruction', () => {
    const buttons = [0, 1, 2].map(index => Object.assign(new Element(), { id: `tab-${index}` }));
    const panels = buttons.map(() => new Element());
    const titles = buttons.map(() => new Element());
    const list = new Element({}, { '[role="tab"]': buttons }); list.hidden = true;
    const root = new Element({ 'data-cms-tabs': '' }, { ':scope > [data-tab-list]': [list], ':scope > [data-tab-panel]': panels, ':scope > [data-tab-panel] > [data-tab-fallback-title]': titles });
    initialize(root);
    assert.equal(list.hidden, false);
    assert.deepEqual(panels.map(panel => panel.hidden), [false, true, true]);
    buttons[0].dispatch('keydown', { key: 'ArrowRight' });
    assert.deepEqual(panels.map(panel => panel.hidden), [true, false, true]);
    assert.equal(buttons[1].focused, true);
    assert.equal(buttons[1].getAttribute('aria-selected'), 'true');
    buttons[1].dispatch('keydown', { key: 'End' });
    assert.equal(buttons[2].getAttribute('aria-selected'), 'true');
    buttons[2].dispatch('keydown', { key: 'Home' });
    assert.equal(buttons[0].getAttribute('aria-selected'), 'true');
    destroy(root);
    assert.equal(list.hidden, true);
    assert.deepEqual(panels.map(panel => panel.hidden), [false, false, false]);
    assert.deepEqual(titles.map(title => title.hidden), [false, false, false]);
    assert.equal(buttons[0].handlers.get('keydown').length, 0);
});

test('accordion maintains one open panel and supports keyboard navigation', () => {
    const summaries = [new Element(), new Element()];
    const panels = summaries.map(summary => Object.assign(new Element({}, { summary: [summary] }), { tagName: 'DETAILS', open: true }));
    const root = Object.assign(new Element({ 'data-cms-accordion': '' }), { children: panels });
    initialize(root);
    assert.deepEqual(panels.map(panel => panel.open), [true, false]);
    panels[1].open = true; panels[1].dispatch('toggle');
    assert.deepEqual(panels.map(panel => panel.open), [false, true]);
    summaries[1].dispatch('keydown', { key: 'ArrowDown' });
    assert.equal(summaries[0].focused, true);
    destroy(root);
    assert.equal(summaries[0].handlers.get('keydown').length, 0);
});


test('carousel visibility and advance are independently configured per device', () => {
    const options = carouselBreakpoints({ slidesPerViewMobile: 1, slidesPerGroupMobile: 1, slidesPerViewTablet: 2, slidesPerGroupTablet: 1, slidesPerView: 4, slidesPerGroup: 3 });
    assert.equal(options.slidesPerView, 1);
    assert.equal(options.slidesPerGroup, 1);
    assert.deepEqual(options.breakpoints[768], { slidesPerView: 2, slidesPerGroup: 1 });
    assert.deepEqual(options.breakpoints[1024], { slidesPerView: 4, slidesPerGroup: 3 });
});

test('countdown expiry supports hide, message and safe redirects with preview suppression', () => {
    assert.deepEqual(countdownExpiry({ expiredAction: 'hide' }), { action: 'hide', url: null });
    assert.deepEqual(countdownExpiry({ expiredAction: 'redirect', redirectUrl: '/finished' }, { base: 'https://site.test/path' }), { action: 'redirect', url: 'https://site.test/finished' });
    assert.deepEqual(countdownExpiry({ expiredAction: 'redirect', redirectUrl: '/finished' }, { preview: true }), { action: 'message', url: null });
    for (const redirectUrl of ['javascript:alert(1)', '//tracker.test', 'https://user:pass@tracker.test']) assert.equal(countdownExpiry({ expiredAction: 'redirect', redirectUrl }).action, 'message');
});


test('optional visit persistence never touches Storage before explicit project enablement and shared authorization', () => {
    let accesses = 0;
    const storage = () => { accesses++; throw new Error('denied by browser'); };
    const data = { mode: 'visit', duration: 90, persistence: 'local', storageEnabled: 'true', storagePurpose: 'preferences', instanceId: 'instance', definitionRevision: 'revision', storageDays: 2 };
    assert.equal(visitCountdownState({ data, bridge: { allows: () => false }, storage, key: 'key' }, 1000).status, 'denied');
    assert.equal(visitCountdownState({ data: { ...data, storageEnabled: 'false' }, bridge: { allows: () => true }, storage, key: 'key' }, 1000).status, 'unavailable');
    assert.equal(accesses, 0);
    assert.equal(visitCountdownState({ data, bridge: { allows: () => true }, storage, key: 'key' }, 1000).status, 'unavailable');
    assert.equal(accesses, 1);
});

test('authorized persistence preserves the deadline, bounds expiry and invalidates definition changes', () => {
    const entries = new Map(); let reads = 0; let writes = 0;
    const storage = () => ({ getItem(key) { reads++; return entries.get(key); }, setItem(key, value) { writes++; entries.set(key, value); }, removeItem(key) { entries.delete(key); } });
    const data = { duration: 90, persistence: 'session', storageEnabled: 'true', storagePurpose: 'preferences', instanceId: 'instance', definitionRevision: 'revision', storageDays: 2 };
    const options = { data, bridge: { allows: purpose => purpose === 'preferences' }, storage, key: 'instance:revision' };
    assert.equal(visitCountdownState(options, 1000).deadline, 91000);
    assert.equal(visitCountdownState(options, 10000).deadline, 91000);
    assert.equal(JSON.parse(entries.get(options.key)).expiresAt, 1000 + 2 * 86400000);
    assert.equal(writes, 1); assert.equal(reads, 2);
    const changed = { ...options, data: { ...data, duration: 120, definitionRevision: 'next' }, key: 'instance:next' };
    assert.equal(visitCountdownState(changed, 20000).deadline, 140000);
    assert.equal(visitCountdownState(options, 3 * 86400000).deadline, 3 * 86400000 + 90000);
});


test('authorized storage removes expired and superseded countdown keys while leaving other owners untouched', () => {
    const entries = new Map([
        ['dbcms.countdown.v1.page.instance.old', JSON.stringify({ expiresAt: 100000, deadline: 90000 })],
        ['dbcms.countdown.v1.other.instance.old', JSON.stringify({ expiresAt: 10, deadline: 0 })],
        ['another.owner', 'retained'],
    ]);
    const destination = { get length() { return entries.size; }, key(index) { return [...entries.keys()][index]; }, getItem(key) { return entries.get(key); }, setItem(key, value) { entries.set(key, value); }, removeItem(key) { entries.delete(key); } };
    visitCountdownState({ data: { duration: 60, persistence: 'local', storageEnabled: 'true', storagePurpose: 'preferences', instanceId: 'instance', definitionRevision: 'new', storageDays: 2 }, bridge: { allows: () => true }, storage: () => destination, key: 'dbcms.countdown.v1.page.instance.new' }, 1000);
    assert.deepEqual([...entries.keys()], ['another.owner', 'dbcms.countdown.v1.page.instance.new']);
});

test('responsive tabs retain initial selection and reuse panels through accordion and tab layouts', () => {
    const previous = globalThis.matchMedia;
    const media = Object.assign(new Element(), { matches: true });
    globalThis.matchMedia = () => media;
    try {
        const buttons = [0, 1].map(index => Object.assign(new Element(), { id: `tab-${index}` }));
        const contents = buttons.map(() => new Element());
        const triggers = buttons.map((_, index) => Object.assign(new Element(), { id: `accordion-${index}` }));
        const titles = buttons.map(() => new Element());
        const headings = buttons.map((_, index) => new Element({}, { '[data-tab-accordion-trigger]': [triggers[index]], '[data-tab-static-title]': [titles[index]] }));
        const panels = buttons.map((_, index) => new Element({}, { '[data-tab-content]': [contents[index]] }));
        const list = new Element({}, { '[role="tab"]': buttons });
        const root = new Element({ 'data-cms-tabs': '' }, { ':scope > [data-tab-list]': [list], ':scope > [data-tab-panel]': panels, ':scope > [data-tab-panel] > [data-tab-fallback-title]': headings });
        root.dataset = { initialIndex: '1', responsiveMode: 'accordion', breakpoint: '767' };
        initialize(root);
        assert.equal(list.hidden, true);
        assert.deepEqual(contents.map(node => node.hidden), [true, false]);
        triggers[0].dispatch('click');
        assert.deepEqual(contents.map(node => node.hidden), [false, true]);
        media.matches = false; media.dispatch('change');
        assert.equal(list.hidden, false);
        assert.deepEqual(panels.map(node => node.hidden), [false, true]);
        assert.equal(buttons[0].getAttribute('aria-selected'), 'true');
        destroy(root);
        assert.deepEqual(contents.map(node => node.hidden), [false, false]);
        assert.equal(media.handlers.get('change').length, 0);
    } finally { globalThis.matchMedia = previous; }
});

test('playlist selects one track without autoplay and applies repeat and next/previous after playback', () => {
    const player = Object.assign(new Element(), { tagName: 'AUDIO', paused: true, plays: 0, loads: 0, pause() { this.paused = true; }, load() { this.loads++; }, play() { this.paused = false; this.plays++; return Promise.resolve(); } });
    const controls = new Element(); const previous = new Element(); const next = new Element();
    const buttons = [0, 1].map(index => { const node = new Element(); node.dataset.playlistSelect = String(index); return node; });
    const root = new Element({ 'data-cms-playlist': '' }, { '[data-playlist-player]': [player], '[data-playlist-controls]': [controls], '[data-playlist-select]': buttons, '[data-playlist-previous]': [previous], '[data-playlist-next]': [next] });
    root.matches = () => true;
    root.dataset = { playlistTracks: JSON.stringify([{ url: '/a.mp3', title: 'A' }, { url: '/b.mp3', title: 'B' }]), order: 'reverse', repeat: 'all', autoAdvance: 'true' };
    initialize(root);
    assert.equal(player.src, '/b.mp3'); assert.equal(player.plays, 0);
    next.dispatch('click'); assert.equal(player.src, '/a.mp3'); assert.equal(player.plays, 0);
    previous.dispatch('click'); assert.equal(player.src, '/b.mp3');
    player.dispatch('ended'); assert.equal(player.src, '/a.mp3'); assert.equal(player.plays, 1);
    root.dataset.repeat = 'one'; player.dispatch('ended'); assert.equal(player.src, '/a.mp3'); assert.equal(player.plays, 2);
    destroy(root); assert.equal(controls.hidden, true); assert.equal(player.handlers.get('ended').length, 0);
});


test('reading progress and playlist order stay bounded at document and list edges', () => {
    assert.equal(readingProgress(0, 2000, 500), 0);
    assert.equal(readingProgress(750, 2000, 500), 50);
    assert.equal(readingProgress(3000, 2000, 500), 100);
    assert.equal(readingProgress(0, 500, 500), 100);
    assert.deepEqual(playlistOrder(3, 'reverse'), [2, 1, 0]);
    assert.deepEqual([...playlistOrder(5, 'shuffle', () => .5)].sort(), [0, 1, 2, 3, 4]);
    assert.equal(nextTrackIndex(2, 3, 'none'), null);
    assert.equal(nextTrackIndex(2, 3, 'all'), 0);
    assert.equal(nextTrackIndex(0, 3, 'all', -1), 2);
});

test('reading progress detaches scroll, resize and observation work when removed', () => {
    const previous = { document: globalThis.document, window: globalThis.window, requestAnimationFrame: globalThis.requestAnimationFrame, cancelAnimationFrame: globalThis.cancelAnimationFrame, ResizeObserver: globalThis.ResizeObserver };
    const document = Object.assign(new Element(), { scrollingElement: { scrollTop: 750, scrollHeight: 2000, clientHeight: 500 }, documentElement: {} });
    const window = new Element(); let frame; let disconnected = false; let cancelled = false;
    globalThis.document = document; globalThis.window = window;
    globalThis.requestAnimationFrame = callback => { frame = callback; return 1; };
    globalThis.cancelAnimationFrame = () => { cancelled = true; };
    globalThis.ResizeObserver = class { observe() {} disconnect() { disconnected = true; } };
    try {
        const progress = new Element(); const output = new Element();
        const root = new Element({ 'data-cms-reading-progress': '' }, { progress: [progress], '[data-reading-value]': [output] }); root.matches = () => true;
        initialize(root); frame(); assert.equal(progress.value, 50); assert.equal(output.textContent, '50');
        document.dispatch('scroll'); destroy(root);
        assert.equal(document.handlers.get('scroll').length, 0); assert.equal(window.handlers.get('resize').length, 0); assert.equal(disconnected, true); assert.equal(cancelled, true);
    } finally { Object.assign(globalThis, previous); }
});


test('Lottie playback direction is explicit while its default stays forward', () => {
    assert.equal(lottieDirection('reverse'), -1);
    assert.equal(lottieDirection('forward'), 1);
    assert.equal(lottieDirection(undefined), 1);
});
