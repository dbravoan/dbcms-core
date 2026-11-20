import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createPresentationRuntime } from '../../resources/js/block-presentation.js';

class Events {
    listeners = new Map();
    addEventListener(name, callback) { const set = this.listeners.get(name) || new Set(); set.add(callback); this.listeners.set(name, set); }
    removeEventListener(name, callback) { this.listeners.get(name)?.delete(callback); }
    emit(name, event = {}) { for (const callback of this.listeners.get(name) || []) callback(event); }
}
class Element extends Events {
    constructor(dataset = {}) {
        super(); this.dataset = dataset; this.hidden = false; this.isConnected = true; this.owned = []; this.nested = []; this.values = new Map(); this.classes = new Set(); this.restarts = 0;
        this.style = { setProperty: (key, value) => this.values.set(key, value), removeProperty: key => this.values.delete(key) };
        this.classList = { add: key => { this.classes.add(key); if (key === 'cms-motion-running') this.restarts++; }, remove: key => this.classes.delete(key) };
    }
    querySelectorAll(query) { return query.includes('data-cms-presentation-motion') ? this.owned : this.nested; }
    querySelector() { return this.entrance; }
    matches() { return !this.tagName && ['scrollMagic', 'scrollEffect', 'scrollScrub', 'scrollVideo'].some(key => Object.hasOwn(this.dataset, key)); }
    closest(query) { return query === '[data-cms-consent]' ? this.gate : query === '[hidden]' ? this.hiddenOwner : null; }
    contains(node) { return this.nested.includes(node); }
    getBoundingClientRect() { return { top: 200, bottom: 600, height: 400 }; }
}
function environment({ reduced = false, preview = false } = {}) {
    const win = new Events(), doc = new Events(), mediaQuery = new Events();
    mediaQuery.matches = reduced;
    let intersect;
    const observed = [];
    Object.assign(win, { innerHeight: 1000, parent: {}, location: new URL('https://site.test/preview'), matchMedia: () => mediaQuery,
        IntersectionObserver: class { constructor(callback) { intersect = callback; } observe(node) { observed.push(node); } unobserve() {} disconnect() {} },
        requestAnimationFrame: callback => { callback(); return 1; }, cancelAnimationFrame() {},
    });
    doc.documentElement = { clientHeight: 1000, hasAttribute: name => preview && name === 'data-cms-preview' };
    doc.roots = []; doc.querySelectorAll = () => doc.roots.filter(node => node.matches()); doc.hidden = false;
    const video = () => Object.assign(new Element(), { tagName: 'VIDEO', duration: 100, plays: 0, pauses: 0, play() { this.plays++; return Promise.resolve(); }, pause() { this.pauses++; } });
    return { win, doc, mediaQuery, video, observed, enter: node => intersect([{ target: node, isIntersecting: true }]), leave: node => intersect([{ target: node, isIntersecting: false }]) };
}

test('plain presentation surfaces create no motion observations', () => {
    const env = environment(); env.doc.roots = [new Element({ cmsPresentation: 'block' })];
    const runtime = createPresentationRuntime(env.win, env.doc);
    assert.equal(env.observed.length, 0); runtime.destroy();
});

test('video waits for viewport and current authorization, pauses on exit and reduced motion', () => {
    const env = environment(); const root = new Element({ scrollVideo: 'playback' }); const video = env.video(); root.owned = [video]; env.doc.roots = [root];
    video.gate = { dataset: { cmsConsent: 'external' } }; let granted = false; env.win.dbcmsConsent = { allows: () => granted };
    const runtime = createPresentationRuntime(env.win, env.doc);
    assert.equal(video.plays, 0); env.enter(root); assert.equal(video.plays, 0);
    granted = true; env.win.emit('cookie-consent-updated'); assert.equal(video.plays, 1);
    env.leave(root); assert.ok(video.pauses > 0); const played = video.plays;
    env.mediaQuery.matches = true; env.mediaQuery.emit('change'); env.enter(root); assert.equal(video.plays, played);
    runtime.destroy();
});

test('parallax touches only owned layers and never nested image nodes', () => {
    const env = environment(); const root = new Element({ scrollScrub: 'parallax' }); const own = new Element(), childImage = new Element(); own.tagName = 'DIV'; childImage.tagName = 'IMG';
    root.owned = [own]; root.nested = [childImage]; env.doc.roots = [root];
    const runtime = createPresentationRuntime(env.win, env.doc); env.enter(root);
    assert.match(own.values.get('--cms-scroll-transform'), /translate3d/); assert.equal(childImage.values.size, 0);
    env.mediaQuery.matches = true; env.mediaQuery.emit('change'); assert.equal(own.values.size, 0); runtime.destroy();
});

test('content-only rotation uses its owned entrance frame and preserves child image styles', () => {
    const env = environment(); const root = new Element({ scrollScrub: 'rotate' }); root.entrance = new Element(); const child = new Element(); child.tagName = 'IMG'; root.nested = [child]; env.doc.roots = [root];
    const runtime = createPresentationRuntime(env.win, env.doc); env.enter(root);
    assert.match(root.entrance.values.get('--cms-scroll-transform'), /rotate/); assert.equal(child.values.size, 0);
    runtime.destroy(); assert.equal(root.entrance.values.size, 0);
});

test('explicit playback scrub never autoplay and does not seek while reduced or unauthorized', () => {
    const env = environment(); const root = new Element({ scrollScrub: 'playback' }); const video = env.video(); root.owned = [video]; env.doc.roots = [root];
    const runtime = createPresentationRuntime(env.win, env.doc); env.enter(root);
    assert.equal(video.plays, 0); assert.ok(video.currentTime > 0);
    const current = video.currentTime; env.mediaQuery.matches = true; env.mediaQuery.emit('change'); assert.equal(video.currentTime, current); runtime.destroy();
});

test('mount initialization is idempotent, cleanup removes lifecycle state, replay is same-origin parent preview only', () => {
    const env = environment({ preview: true }); const root = new Element({ scrollEffect: 'flipInX', scrollDelay: '0s', scrollDuration: '2s' }); env.doc.roots = [root];
    root.entrance = new Element();
    root.values.set('transform', 'rotate(12deg)'); root.values.set('opacity', '.4');
    env.doc.baseURI = 'https://site.test/page'; env.win.location = { origin: 'null', href: 'about:srcdoc' };
    const runtime = createPresentationRuntime(env.win, env.doc); env.enter(root); assert.equal(root.entrance.restarts, 1);
    runtime.initialize(root); env.win.emit('cms-content-mounted', { detail: { root } }); assert.equal(root.entrance.restarts, 1);
    const data = { source: 'cms-block-preview', type: 'replay' };
    env.win.emit('message', { source: {}, origin: 'https://site.test', data }); env.win.emit('message', { source: env.win.parent, origin: 'https://other.test', data }); assert.equal(root.entrance.restarts, 1);
    env.win.emit('message', { source: env.win.parent, origin: 'https://site.test', data }); assert.equal(root.entrance.restarts, 2);
    root.entrance.emit('animationend', { target: root.entrance });
    assert.equal(root.entrance.classes.has('cms-motion-running'), false);
    assert.equal(root.entrance.values.size, 0); assert.equal(root.values.get('opacity'), '.4'); assert.equal(root.values.get('transform'), 'rotate(12deg)');
    runtime.replay(); assert.equal(root.entrance.restarts, 3);
    runtime.cleanup(root); env.win.emit('message', { source: env.win.parent, origin: 'https://site.test', data }); assert.equal(root.entrance.restarts, 3);
    runtime.destroy(); assert.equal(env.win.listeners.get('message').size, 0);
});

test('bounce and flip have real local keyframes and legacy include emits no inline runtime', async () => {
    const css = await readFile(new URL('../../resources/css/block-presentation.css', import.meta.url), 'utf8');
    assert.match(css, /@keyframes cms-bounceIn/); assert.match(css, /@keyframes cms-flipInX/); assert.match(css, /prefers-reduced-motion/);
    const bridge = await readFile(new URL('../../resources/views/themes/components/scroll-magic.blade.php', import.meta.url), 'utf8');
    assert.doesNotMatch(bridge, /<script|<style/);
});
