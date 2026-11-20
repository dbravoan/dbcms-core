import test from 'node:test';
import assert from 'node:assert/strict';
import { createBlockManager } from '../../resources/js/block-manager.js';
import { identifyTree } from '../../resources/js/block-editor-tree.js';

const types = [
    { type: 'heading', name: 'Hero', defaultPayload: { title: 'Configured' }, schema: { title: { type: 'rich_text', label: 'Title' } } },
    { type: 'accordion', name: 'Container', schema: { children: { type: 'blocks' }, items: { type: 'repeater', fields: { children: { type: 'blocks' } } } } },
    { type: 'heading', name: 'Heading', schema: { text: { type: 'rich_text' } } },
];
const ID = /^[a-zA-Z0-9_-]{1,100}$/;

function withBrowserStubs(run) {
    const saved = { window: globalThis.window, sessionStorage: globalThis.sessionStorage };
    const store = new Map();
    globalThis.window = { addEventListener() {}, removeEventListener() {} };
    globalThis.sessionStorage = { getItem: key => store.get(key) ?? null, setItem: (key, value) => store.set(key, String(value)) };
    try { return run(store); } finally { globalThis.window = saved.window; globalThis.sessionStorage = saved.sessionStorage; }
}
const editorFor = (sections, options = {}) => { const editor = createBlockManager(sections, 'es', types, [], null, options); editor.blocks = editor.sectionsByLocale.es; return editor; };

test('insertion zones add a section at the start, between cards and at the end', () => {
    const editor = editorFor({ es: [{ id: 'a', type: 'heading' }, { id: 'b', type: 'heading' }] });
    editor.openAddModalAt(1); editor.addSectionWithBlock('heading', 'empty');
    editor.openAddModalAt(0); editor.addSectionWithBlock('heading', 'empty');
    editor.openAddModalAt(editor.blocks.length); editor.addSectionWithBlock('accordion', 'empty');
    assert.deepEqual(editor.blocks.map(block => block.type), ['heading', 'heading', 'heading', 'heading', 'accordion']);
    assert.deepEqual([editor.blocks[1].id, editor.blocks[3].id], ['a', 'b']);
    assert.ok(editor.blocks.every(block => !Object.hasOwn(block, 'is_collapsed')));
});

test('legacy content without nested ids is identified in memory like BlockTree::identify, without an undo entry', () => withBrowserStubs(() => {
    const legacy = { es: [{ type: 'accordion', children: [{ type: 'heading', text: 'x' }], items: [{ children: [{ type: 'heading' }] }] }, { type: 'retired_type', children: [{ type: 'heading' }] }], en: [{ type: 'heading' }] };
    const editor = createBlockManager(legacy, 'es', types);
    editor.init();
    const [root, unavailable] = editor.sectionsByLocale.es;
    for (const id of [root.id, root.children[0].id, root.items[0].children[0].id, editor.sectionsByLocale.en[0].id]) assert.match(id, /^node_/), assert.match(id, ID);
    assert.match(root.items[0].id, /^item_/);
    assert.equal(Object.hasOwn(unavailable, 'id'), false);
    assert.equal(Object.hasOwn(unavailable.children[0], 'id'), false);
    assert.equal(editor.canUndo(), false);
    assert.equal(identifyTree(editor.sectionsByLocale.es, types), 0);
}));

test('collapsing one card or all cards is UI state: no form change, no history, stored per entity in sessionStorage', () => withBrowserStubs(store => {
    const editor = createBlockManager({ es: [{ id: 'a', type: 'heading' }, { id: 'c', type: 'accordion', children: [{ id: 'h', type: 'heading' }] }] }, 'es', types, [], null, { entityType: 'page', entityId: 7 });
    editor.init();
    const serialized = editor.getCleanSerializedSections(); const undo = editor.canUndo(); const version = editor.historyVersion;
    editor.toggleCollapse(editor.blocks[0], [0]);
    assert.equal(editor.isCollapsed(editor.blocks[0], [0]), true);
    editor.toggleCollapseAll(true);
    assert.equal(editor.isCollapsed(editor.blocks[1], [1]), true);
    assert.equal(editor.getCleanSerializedSections(), serialized);
    assert.equal(editor.canUndo(), undo); assert.equal(editor.historyVersion, version);
    assert.deepEqual(JSON.parse(store.get('dbcms:block-collapse:page:7')), { es: { a: true, c: true } });
    editor.selectNode([1, 'children', 0]);
    assert.equal(editor.isCollapsed(editor.blocks[1], [1]), false, 'selecting a descendant opens its ancestors');
    assert.equal(editor.isCollapsed(editor.blocks[0], [0]), true);
    const reloaded = createBlockManager({ es: [{ id: 'a', type: 'heading' }] }, 'es', types, [], null, { entityType: 'page', entityId: 7 });
    reloaded.init();
    assert.equal(reloaded.isCollapsed(reloaded.blocks[0], [0]), true);
    editor.toggleCollapseAll(false);
    assert.equal(editor.getCleanSerializedSections(), serialized);
}));

test('design and motion dialogs select one target and never mount for columns on motion', () => {
    const editor = editorFor({ es: [{ id: 'a', type: 'heading' }, { id: 'b', type: 'heading' }, { id: 's', type: 'heading', columns_layout: '2', columns: [{ id: 'c1', blocks: [{ id: 'x', type: 'heading' }] }, { id: 'c2', blocks: [] }] }] });
    editor.openDesignDialog(0);
    assert.equal(editor.showDesignDialog, true); assert.equal(editor.selectedNode().id, 'a'); assert.equal(editor.inspectorTab, 'design');
    editor.openDesignDialog(1);
    assert.equal(editor.showDesignDialog, true); assert.equal(editor.selectedNode().id, 'b');
    editor.openDesignDialog(1);
    assert.equal(editor.showDesignDialog, false); assert.equal(editor.inspectorTab, 'content');
    editor.openDesignDialog(1);
    assert.equal(editor.showDesignDialog, true);
    editor.openMotionDialog(2, { colIndex: 0, subIndex: 0 });
    assert.equal(editor.showMotionDialog, true); assert.equal(editor.showDesignDialog, false); assert.equal(editor.selectedNode().id, 'x'); assert.equal(editor.canEditMotion(), true);
    editor.openMotionDialog(2, { colIndex: 0 });
    assert.equal(editor.showMotionDialog, true, 'columns cannot open motion');
    editor.toggleColumnDesign([2, 'columns', 0]);
    assert.equal(editor.showDesignDialog, true); assert.equal(editor.showMotionDialog, false); assert.equal(editor.inspectorColumn().column.id, 'c1');
    assert.equal(editor.canEditMotion(), false);
    editor.closeDesignDialog();
    assert.equal(editor.showDesignDialog, false); assert.equal(editor.inspectorTab, 'content');
});

test('undo and redo keep stable node ids', () => {
    const editor = editorFor({ es: [{ id: 'a', type: 'heading', title: 'One' }] });
    editor.openAddModalAt(1); editor.addSectionWithBlock('heading', 'empty');
    const ids = editor.blocks.map(block => block.id);
    editor.historyBoundary(); editor.blocks[0].title = 'Two'; editor.syncBlocksToLocale();
    editor.undo(); editor.undo();
    assert.deepEqual(editor.blocks.map(block => block.id), ['a']);
    editor.redo(); editor.redo();
    assert.deepEqual(editor.blocks.map(block => block.id), ids);
    assert.equal(editor.blocks[0].title, 'Two');
});

test('the preview is closed by default, sends no request while closed and a late response never reopens it', async () => {
    const saved = { document: globalThis.document, fetch: globalThis.fetch };
    let requests = 0; let release;
    globalThis.document = { querySelector: () => null };
    globalThis.fetch = () => { requests++; return new Promise(resolve => { release = () => resolve({ ok: true, json: async () => ({ html: '<p>late</p>' }) }); }); };
    try {
        const editor = editorFor({ es: [{ id: 'a', type: 'heading', title: 'One' }] }, { previewUrl: '/preview' });
        assert.equal(editor.showPreview, false);
        editor.selectNode([0]); editor.blocks[0].title = 'Edited'; editor.syncBlocksToLocale();
        await new Promise(resolve => setTimeout(resolve, 500));
        assert.equal(requests, 0);
        const pending = editor.previewSelected();
        assert.equal(editor.showPreview, true); assert.equal(requests, 1);
        editor.closePreview();
        release(); await pending;
        assert.equal(editor.showPreview, false); assert.equal(editor.previewHtml, '');
        editor.blocks[0].title = 'Again'; editor.syncBlocksToLocale();
        await new Promise(resolve => setTimeout(resolve, 500));
        assert.equal(requests, 1);
    } finally { globalThis.document = saved.document; globalThis.fetch = saved.fetch; }
});
