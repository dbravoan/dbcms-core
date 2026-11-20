import test from 'node:test';
import assert from 'node:assert/strict';
import { installBlockPreview } from '../../resources/js/block-preview.js';

const fixture = () => {
    const listeners = new Map();
    const messages = [];
    const nodes = ['first', 'quote"[id]', 'last'].map(id => ({
        dataset: { cmsNode: id }, attributes: new Set(), scrolled: false,
        setAttribute(name) { this.attributes.add(name); },
        removeAttribute(name) { this.attributes.delete(name); },
        hasAttribute(name) { return this.attributes.has(name); },
        scrollIntoView() { this.scrolled = true; },
        closest() { return this; },
    }));
    const columns = ['first', 'last'].map(owner => ({ ...nodes[0], dataset: { cmsColumnOwner: owner, cmsColumnIndex: '0' }, attributes: new Set(['data-cms-column-index']) }));
    const region = {
        querySelectorAll: selector => selector === '[data-cms-node]' ? nodes : columns,
        contains: node => nodes.includes(node) || columns.includes(node),
        addEventListener: (type, callback) => listeners.set(`region:${type}`, callback),
        removeEventListener: type => listeners.delete(`region:${type}`),
    };
    const frame = {
        parent: { postMessage: (data, origin) => messages.push({ data, origin }) },
        document: {
            baseURI: 'https://cms.example/admin/pages/create', body: { dataset: { previewRoot: 'first', previewNode: 'first' } },
            documentElement: { hasAttribute: name => name === 'data-cms-preview' },
            querySelector: () => region,
        },
        addEventListener: (type, callback) => listeners.set(type, callback),
        removeEventListener: type => listeners.delete(type),
    };
    return { frame, nodes, columns, listeners, messages };
};

test('private preview selects exact node identities without interpreting them as CSS selectors', () => {
    const { frame, nodes, listeners, messages } = fixture();
    const stop = installBlockPreview(frame);
    assert(nodes[0].hasAttribute('data-cms-preview-selected'));
    listeners.get('message')({ source: frame.parent, origin: 'https://cms.example', data: { source: 'cms-block-preview', type: 'selection', node_id: 'quote"[id]' } });
    assert(!nodes[0].hasAttribute('data-cms-preview-selected'));
    assert(nodes[1].hasAttribute('data-cms-preview-selected'));
    assert(nodes[1].scrolled);
    assert.deepEqual(messages, [{ data: { source: 'cms-block-preview', type: 'ready' }, origin: 'https://cms.example' }]);
    stop();
    assert.equal(listeners.size, 0);
});

test('selection commands require the actual parent, same origin and private-preview message namespace', () => {
    const { frame, nodes, listeners } = fixture();
    const stop = installBlockPreview(frame);
    const valid = { source: frame.parent, origin: 'https://cms.example', data: { source: 'cms-block-preview', type: 'selection', node_id: 'last' } };
    for (const invalid of [{ ...valid, source: {} }, { ...valid, origin: 'https://untrusted.example' }, { ...valid, data: { ...valid.data, source: 'other' } }, { ...valid, data: { ...valid.data, type: 'replay' } }]) listeners.get('message')(invalid);
    assert(nodes[0].hasAttribute('data-cms-preview-selected'));
    assert(!nodes[2].hasAttribute('data-cms-preview-selected'));
    stop();
});

test('click selection keeps widget interactions active and never sends payloads to the parent', () => {
    const { frame, nodes, listeners, messages } = fixture();
    const stop = installBlockPreview(frame);
    listeners.get('region:click')({ target: nodes[2], preventDefault: () => assert.fail('Interactive widgets must remain usable') });
    assert.deepEqual(messages.at(-1), { data: { source: 'cms-block-preview', type: 'select', node_id: 'last' }, origin: 'https://cms.example' });
    assert(nodes[2].hasAttribute('data-cms-preview-selected'));
    stop();
});

test('public documents and top-level previews install no selection bridge', () => {
    for (const topLevel of [true, false]) {
        const { frame, listeners, messages } = fixture();
        if (topLevel) frame.parent = frame;
        else frame.document.documentElement.hasAttribute = () => false;
        installBlockPreview(frame)();
        assert.equal(listeners.size, 0);
        assert.equal(messages.length, 0);
    }
});

test('column indices are scoped to their root and nested columns never select an unrelated root column', () => {
    const { frame, nodes, columns, listeners, messages } = fixture();
    const stop = installBlockPreview(frame);
    listeners.get('message')({ source: frame.parent, origin: 'https://cms.example', data: { source: 'cms-block-preview', type: 'selection', column_index: 0 } });
    assert(columns[0].hasAttribute('data-cms-preview-selected'));
    assert(!columns[1].hasAttribute('data-cms-preview-selected'));
    listeners.get('region:click')({ target: columns[1] });
    assert(nodes[2].hasAttribute('data-cms-preview-selected'));
    assert(!columns[0].hasAttribute('data-cms-preview-selected'));
    assert.equal(messages.at(-1).data.node_id, 'last');
    assert.equal(messages.at(-1).data.column_index, undefined);
    stop();
});

test('private frames relay Escape to their exact parent even without a composition region', () => {
    for (const hasRegion of [true, false]) {
        const { frame, listeners, messages } = fixture();
        if (!hasRegion) frame.document.querySelector = () => null;
        const stop = installBlockPreview(frame);
        messages.length = 0;
        const keydown = listeners.get('keydown');
        assert.equal(typeof keydown, 'function');
        keydown({ key: 'Tab' });
        keydown({ key: 'Escape', defaultPrevented: true });
        assert.equal(messages.length, 0);
        let prevented = false;
        keydown({ key: 'Escape', preventDefault() { prevented = true; } });
        assert.equal(prevented, true);
        assert.deepEqual(messages, [{ data: { source: 'cms-preview', type: 'escape' }, origin: 'https://cms.example' }]);
        listeners.get('pagehide')();
        assert.equal(listeners.size, 0);
        stop();
    }
});
