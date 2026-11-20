import test from 'node:test';
import assert from 'node:assert/strict';
import { createAdminDialogManager, createAdminDialog } from '../../resources/js/admin-dialog.js';

function environment() {
    const listeners = new Map();
    const document = { activeElement: null, body: { style: { overflow: 'auto' } } };
    const browser = {
        document,
        location: { origin: 'https://cms.example' },
        getComputedStyle: element => ({ zIndex: element.zIndex }),
        addEventListener(name, handler) { listeners.set(name, handler); },
        removeEventListener(name, handler) { if (listeners.get(name) === handler) listeners.delete(name); },
        CustomEvent: class { constructor(type) { this.type = type; } },
    };
    function element(parent = null, extras = {}) {
        return {
            parent, isConnected: true, disabled: false, tabIndex: 0,
            getClientRects() { return this.visible === false ? [] : [{}]; },
            closest() { return this.inert ? this : null; },
            contains(other) { for (let node = other; node; node = node.parent) if (node === this) return true; return false; },
            focus() { document.activeElement = this; },
            ...extras,
        };
    }
    function dialog(zIndex = '50') {
        const root = element(null, { zIndex });
        const panel = element(root, { tabIndex: -1, items: [] });
        panel.querySelectorAll = selector => selector === 'iframe[data-cms-preview-frame]'
            ? panel.items.filter(item => item.contentWindow) : panel.items;
        panel.querySelector = () => panel.items.find(item => item.initial) || null;
        root.querySelector = () => panel;
        const control = extras => { const item = element(panel, extras); panel.items.push(item); return item; };
        return { root, panel, control };
    }
    function key(key, shiftKey = false) {
        const event = { key, shiftKey, prevented: false, stopped: false, preventDefault() { this.prevented = true; }, stopImmediatePropagation() { this.stopped = true; } };
        listeners.get('keydown')?.(event);
        return event;
    }
    return { browser, document, listeners, element, dialog, key };
}

test('dialog focuses its initial control and wraps Tab around enabled visible controls', () => {
    const env = environment();
    const manager = createAdminDialogManager(env.browser);
    const view = env.dialog();
    const first = view.control();
    view.control({ disabled: true });
    view.control({ visible: false });
    view.control({ tabIndex: -1 });
    view.control({ inert: true });
    const last = view.control({ initial: true });
    const entry = manager.open(view.root, view.panel, () => {});
    manager.focus(entry);
    assert.equal(env.document.activeElement, last);
    assert.equal(env.key('Tab').prevented, true);
    assert.equal(env.document.activeElement, first);
    assert.equal(env.key('Tab', true).prevented, true);
    assert.equal(env.document.activeElement, last);
    const outside = env.element();
    outside.focus();
    env.listeners.get('focusin')({ target: outside });
    assert.equal(env.document.activeElement, last);
    manager.close(entry);
    assert.equal(env.document.body.style.overflow, 'auto');
    assert.equal(env.listeners.size, 0);
});

test('Escape closes only the upper dialog then restores focus into its lower owner', () => {
    const env = environment();
    const manager = createAdminDialogManager(env.browser);
    const trigger = env.element();
    trigger.focus();
    const lower = env.dialog('200');
    const childTrigger = lower.control();
    const calls = [];
    let lowerEntry, upperEntry;
    lowerEntry = manager.open(lower.root, lower.panel, () => { calls.push('lower'); manager.close(lowerEntry); });
    manager.focus(lowerEntry);
    const upper = env.dialog('220');
    const cancel = upper.control({ initial: true });
    upperEntry = manager.open(upper.root, upper.panel, () => { calls.push('upper'); manager.close(upperEntry); });
    manager.focus(upperEntry);
    assert.equal(env.document.activeElement, cancel);
    const escape = env.key('Escape');
    assert.equal(escape.prevented, true);
    assert.equal(escape.stopped, true);
    assert.deepEqual(calls, ['upper']);
    assert.equal(env.document.activeElement, childTrigger);
    assert.equal(env.document.body.style.overflow, 'hidden');
    env.key('Escape');
    assert.deepEqual(calls, ['upper', 'lower']);
    assert.equal(env.document.activeElement, trigger);
    assert.equal(env.document.body.style.overflow, 'auto');
});

test('visual layer priority owns Escape even if a lower dialog opens later', () => {
    const env = environment();
    const manager = createAdminDialogManager(env.browser);
    const upper = env.dialog('220');
    const upperControl = upper.control();
    const calls = [];
    const upperEntry = manager.open(upper.root, upper.panel, () => calls.push('upper'));
    const lower = env.dialog('120');
    lower.control();
    const lowerEntry = manager.open(lower.root, lower.panel, () => calls.push('lower'));
    manager.focus(upperEntry);
    manager.focus(lowerEntry);
    assert.equal(env.document.activeElement, upperControl);
    manager.requestClose(lowerEntry);
    assert.deepEqual(calls, []);
    env.key('Escape');
    assert.deepEqual(calls, ['upper']);
});

test('nonclosable upper dialog consumes Escape and traps focus without closing its parent', () => {
    const env = environment();
    const manager = createAdminDialogManager(env.browser);
    const lower = env.dialog();
    const upper = env.dialog('220');
    const calls = [];
    manager.open(lower.root, lower.panel, () => calls.push('lower'));
    const entry = manager.open(upper.root, upper.panel, () => calls.push('upper'), false);
    manager.focus(entry);
    const escape = env.key('Escape');
    assert.equal(escape.prevented, true);
    assert.equal(escape.stopped, true);
    manager.requestClose(entry);
    assert.deepEqual(calls, []);
    env.key('Tab');
    assert.equal(env.document.activeElement, upper.panel);
});

test('destroying a parent under its child preserves the original return target', () => {
    const env = environment();
    const manager = createAdminDialogManager(env.browser);
    const trigger = env.element();
    trigger.focus();
    const lower = env.dialog();
    lower.control();
    const lowerEntry = manager.open(lower.root, lower.panel, () => {});
    manager.focus(lowerEntry);
    const upper = env.dialog('220');
    const childControl = upper.control();
    const upperEntry = manager.open(upper.root, upper.panel, () => {});
    manager.focus(upperEntry);
    manager.close(lowerEntry);
    assert.equal(env.document.activeElement, childControl);
    manager.close(upperEntry);
    assert.equal(env.document.activeElement, trigger);
});

test('Alpine adapter releases its stack entry on close and destruction', () => {
    const env = environment();
    const view = env.dialog();
    const first = view.control();
    const component = createAdminDialog({}, env.browser);
    component.$el = view.root;
    const ticks = [];
    component.$nextTick = callback => ticks.push(callback);
    const events = [];
    view.root.dispatchEvent = event => events.push(event.type);
    component.syncDialog(true);
    component.syncDialog(true);
    assert.equal(ticks.length, 1);
    ticks.shift()();
    assert.equal(env.document.activeElement, first);
    component.requestClose();
    assert.deepEqual(events, ['admin-dialog-request-close']);
    component.isMaximized = true;
    component.syncDialog(false);
    assert.equal(component.isMaximized, false);
    assert.equal(env.listeners.size, 0);
    component.syncDialog(true);
    component.destroy();
    ticks.shift()();
    assert.equal(env.listeners.size, 0);
});

test('Escape from a private frame closes only its visible, closable top dialog', () => {
    const env = environment();
    const manager = createAdminDialogManager(env.browser);
    const lower = env.dialog();
    const lowerFrame = lower.control({ contentWindow: {} });
    const calls = [];
    manager.open(lower.root, lower.panel, () => calls.push('lower'));
    const upper = env.dialog('220');
    const frame = upper.control({ contentWindow: {} });
    const hidden = upper.control({ contentWindow: {}, visible: false });
    let entry = manager.open(upper.root, upper.panel, () => { calls.push('upper'); manager.close(entry); });
    const valid = { source: frame.contentWindow, origin: 'null', data: { source: 'cms-preview', type: 'escape' } };
    const relay = event => env.listeners.get('message')?.(event);
    for (const invalid of [
        { ...valid, source: null }, { ...valid, source: {} },
        { ...valid, source: lowerFrame.contentWindow }, { ...valid, source: hidden.contentWindow },
        { ...valid, origin: 'https://untrusted.example' },
        { ...valid, data: { source: 'other', type: 'escape' } },
        { ...valid, data: { source: 'cms-preview', type: 'select' } },
    ]) relay(invalid);
    assert.deepEqual(calls, []);
    relay(valid);
    assert.deepEqual(calls, ['upper']);
    relay(valid);
    assert.deepEqual(calls, ['upper']);
    entry = manager.open(upper.root, upper.panel, () => calls.push('blocked'), false);
    relay({ ...valid, origin: env.browser.location.origin });
    assert.deepEqual(calls, ['upper']);
    manager.close(entry);
    relay({ ...valid, source: lowerFrame.contentWindow, origin: env.browser.location.origin });
    assert.deepEqual(calls, ['upper', 'lower']);
});
