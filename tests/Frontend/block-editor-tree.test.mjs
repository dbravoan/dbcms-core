import test from 'node:test';
import assert from 'node:assert/strict';
import { childSlots, treeRows, cloneBlockTree, containsUnavailable, schemaDefaults, cleanEditorValue, EditorHistory, hasRetiredPayloadKey, resizeSectionColumns } from '../../resources/js/block-editor-tree.js';
import { previewNonce } from '../../resources/js/editor-runtime.js';
import { createBlockManager } from '../../resources/js/block-manager.js';

const definitions = [
    { type: 'accordion', name: 'Accordion', schema: { items: { type: 'repeater', fields: { title: { type: 'rich_text' }, children: { type: 'blocks' } } }, metadata: { type: 'object', fields: { panels: { type: 'repeater', fields: { children: { type: 'blocks' } } } } } } },
    { type: 'tabs', name: 'Tabs', schema: { items: { type: 'repeater', fields: { title: { type: 'rich_text' }, children: { type: 'blocks' } } } } },
    { type: 'heading', name: 'Heading', schema: { text: { type: 'rich_text' }, level: { type: 'select', options: { h2: 'H2' } } } },
];
const composition = () => ({ id: 'accordion', type: 'accordion', items: [{ title: 'Panel', children: [{ id: 'tabs', type: 'tabs', items: [{ title: 'Tab', children: [{ id: 'heading', type: 'heading', text: 'Text', product_id: 42 }] }] }] }], metadata: { panels: [{ children: [{ id: 'deep', type: 'heading', text: 'Deeper' }] }] } });

test('recursive structure follows schema blocks inside objects and repeaters', () => {
    const root = composition();
    assert.equal(childSlots(root, definitions[0]).length, 2);
    const rows = treeRows([root], definitions);
    assert.deepEqual(rows.map(row => row.node.id), ['accordion', 'tabs', 'heading', 'deep']);
    assert.deepEqual(rows[2].path, [0, 'items', 0, 'children', 0, 'items', 0, 'children', 0]);
    assert.deepEqual(rows[3].path, [0, 'metadata', 'panels', 0, 'children', 0]);
});

test('cloning refreshes every block and column identity while preserving data references', () => {
    const source = composition();
    source.columns = [{ id: 'column', blocks: [{ id: 'column-heading', type: 'heading', text: 'Column' }] }];
    const cloned = cloneBlockTree(source, definitions);
    const originalIds = treeRows([source], definitions).map(row => row.node.id);
    const clonedIds = treeRows([cloned], definitions).map(row => row.node.id);
    assert.ok(clonedIds.every(id => !originalIds.includes(id)));
    assert.equal(new Set(clonedIds).size, clonedIds.length);
    assert.notEqual(cloned.columns[0].id, 'column');
    assert.equal(cloned.items[0].children[0].items[0].children[0].product_id, 42);
    cloned.items[0].children[0].items[0].title = 'Edited';
    assert.equal(source.items[0].children[0].items[0].title, 'Tab');
});

test('unavailable nested blocks lock structural mutations while retaining editable neighbours', () => {
    const source = composition();
    source.items[0].children[0].items[0].children.push({ type: 'disabled', id: 'retained', private: [1, 2] });
    assert.equal(containsUnavailable(source, definitions), true);
    const editor = createBlockManager({ es: [source] }, 'es', definitions);
    editor.blocks = editor.sectionsByLocale.es;
    editor.cloneBlock(0);
    assert.equal(editor.blocks.length, 1);
    assert.equal(editor.blocks[0].items[0].children[0].items[0].children[1].id, 'retained');
});

test('translation copies preserve every structural identity and create independent payloads', () => {
    const source = composition();
    const editor = createBlockManager({ es: [source], en: [] }, 'en', definitions);
    editor.blocks = editor.sectionsByLocale.en;
    editor.copyBlocksFrom('es', 'en');
    assert.deepEqual(editor.sectionsByLocale.es, editor.sectionsByLocale.en);
    editor.sectionsByLocale.en[0].items[0].children[0].items[0].title = 'Translated';
    assert.equal(editor.sectionsByLocale.es[0].items[0].children[0].items[0].title, 'Tab');
});

test('history coalesces typing, keeps structural boundaries and drops redo after a new edit', () => {
    const history = new EditorHistory({ text: '' }, { limit: 4, coalesceMs: 500 });
    history.record({ text: 'a' }, 'typing', 100);
    history.record({ text: 'ab' }, 'typing', 200);
    assert.equal(history.entries.length, 2);
    history.boundary(); history.record({ text: 'abc' }, 'typing', 250);
    assert.deepEqual(history.undo(), { text: 'ab' });
    assert.deepEqual(history.redo(), { text: 'abc' });
    history.undo(); history.record({ text: 'new' }, 'typing', 1000);
    assert.equal(history.canRedo, false);
    for (let value = 0; value < 8; value++) { history.boundary(); history.record({ value }); }
    assert.equal(history.entries.length, 4);
});

test('manager undo and redo restore nested payload and appearance across locales', () => {
    const editor = createBlockManager({ es: [composition()] }, 'es', definitions);
    editor.blocks = editor.sectionsByLocale.es;
    editor.selectNode([0, 'items', 0, 'children', 0, 'items', 0, 'children', 0]);
    editor.setAppearanceValue('padding', '2rem');
    assert.equal(editor.selectedNode().appearance.base.padding, '2rem');
    editor.undo();
    assert.equal(editor.selectedNode().appearance, undefined);
    editor.redo();
    assert.equal(editor.selectedNode().appearance.base.padding, '2rem');
    editor.cloneBlock(0);
    assert.equal(editor.blocks.length, 2);
    editor.undo();
    assert.equal(editor.blocks.length, 1);
});

test('schema defaults cover composable structures and serialization strips transient fields recursively', () => {
    assert.deepEqual(schemaDefaults({ children: { type: 'blocks' }, settings: { type: 'object', fields: { enabled: { type: 'boolean' }, children: { type: 'blocks' } } } }), { children: [], settings: { enabled: false, children: [] } });
    assert.deepEqual(cleanEditorValue({ id: 1, is_collapsed: true, children: [{ id: 2, is_collapsed: true, data: { retained: true } }] }), { id: 1, children: [{ id: 2, data: { retained: true } }] });
});

test('selected block remains selected by identity when its sibling order changes', () => {
    const editor = createBlockManager({ es: [{ id: 'one', type: 'heading' }, { id: 'two', type: 'heading' }] }, 'es', definitions);
    editor.blocks = editor.sectionsByLocale.es;
    assert.equal(editor.selectedNode(), null);
    editor.selectNode([0]); editor.moveDown(0);
    assert.equal(editor.selectedNode().id, 'one');
});

test('market context resolves selected form IDs to stable public codes', () => {
    const previous = globalThis.cmsContext;
    globalThis.cmsContext = { marketIds: ['12'] };
    try {
        const editor = createBlockManager({}, 'es', [], [], null, { market: 'ES', markets: { 12: 'FR' } });
        assert.equal(editor.editorMarket(), 'FR');
        globalThis.cmsContext.marketIds = [];
        assert.equal(editor.editorMarket(), 'ES');
    } finally { globalThis.cmsContext = previous; }
});

test('Blade copy preserves HTML and quotes with a concrete payload assignment', async () => {
    const descriptor = Object.getOwnPropertyDescriptor(globalThis, 'navigator');
    let copied;
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { clipboard: { writeText: async value => { copied = value; } } } });
    try {
        const editor = createBlockManager({ es: [{ id: 'copy', type: 'heading', text: "<h2>O'Reilly @home</h2>" }] }, 'es', definitions);
        editor.blocks = editor.sectionsByLocale.es; editor.selectNode([0]);
        await editor.copySelected('blade');
        assert.match(copied, /\$cmsBlockPayload = \[/);
        assert.match(copied, /<h2>O\\'Reilly '/);
        assert.match(copied, /chr\(64\)/);
        assert.match(copied, /:payload="\$cmsBlockPayload"/);
        assert.equal(editor.copyStatus, 'Copiado');
    } finally { if (descriptor) Object.defineProperty(globalThis, 'navigator', descriptor); else delete globalThis.navigator; }
});

test('new compositions use shared appearance without injecting legacy decoration', () => {
    const editor = createBlockManager({ es: [] }, 'es', definitions);
    editor.blocks = editor.sectionsByLocale.es;
    editor.addSectionWithBlock('accordion', 'empty');
    const section = editor.blocks[0];
    const block = section.columns[0].blocks[0];
    assert.equal(Object.hasOwn(section, 'bg_type'), false);
    assert.equal(Object.hasOwn(section, 'container_style'), false);
    assert.equal(Object.hasOwn(section, 'animation'), false);
    assert.equal(section.columns_layout, '1');
    assert.equal(block.type, 'accordion');
    assert.equal(Object.hasOwn(block, 'container_style'), false);
    editor.openAppearanceModal(0);
    assert.equal(editor.showDesignDialog, true);
    assert.equal(editor.selectedNode(), section);
    section.container_style = 'neon-glow';
    const preserved = editor.getCleanSerializedSections();
    assert.equal(editor.inspectorTab, 'design');
    editor.openAppearanceModal(0);
    assert.equal(editor.showDesignDialog, false);
    assert.equal(editor.inspectorTab, 'content');
    assert.equal(editor.getCleanSerializedSections(), preserved);
    assert.equal(section.container_style, 'neon-glow');
});

test('same-origin read-only preview reuses the parent CSP nonce for trusted asset and style tags', () => {
    const html = '<script type="module" nonce="post-nonce" src="/build/app.js"></script><style nonce=\'post-nonce\'>p{color:red}</style>';
    assert.equal(previewNonce(html, 'parent-nonce'), '<script type="module" nonce="parent-nonce" src="/build/app.js"></script><style nonce=\'parent-nonce\'>p{color:red}</style>');
    assert.equal(previewNonce(html, ''), html);
});

test('serialization puts the edited locale first without changing translated identities or content', () => {
    const original = { es: [{ id: 'same', type: 'heading', text: 'Español' }], en: [{ id: 'same', type: 'heading', text: 'English' }], fr: [{ id: 'same', type: 'heading', text: 'Français' }] };
    const editor = createBlockManager(original, 'en', definitions);
    editor.blocks = editor.sectionsByLocale.en;
    const serialized = JSON.parse(editor.getCleanSerializedSections());
    assert.deepEqual(Object.keys(serialized), ['en', 'es', 'fr']);
    assert.deepEqual(serialized.en, original.en);
    assert.deepEqual(serialized.es, original.es);
    assert.deepEqual(serialized.fr, original.fr);
    assert.deepEqual(Object.keys(editor.sectionsByLocale), ['es', 'en', 'fr']);
});

test('retired payload formats remain opaque and cannot be edited moved or cloned as a new schema', () => {
    const stored = { id: 'old-carousel', type: 'carousel', slides: [{ title: 'Legacy slide', image_url: '/retained.png' }] };
    const types = [...definitions, { type: 'carousel', name: 'Carousel', schema: { items: { type: 'repeater', fields: { children: { type: 'blocks' } } } }, retiredPayloadKeys: ['slides'] }];
    const editor = createBlockManager({ es: [stored] }, 'es', types); editor.blocks = editor.sectionsByLocale.es;
    assert.equal(editor.isBlockAvailable(stored), false);
    assert.equal(editor.isRetiredBlockPayload(stored), true);
    assert.match(editor.unavailableBlockMessage(stored), /formato retirado/);
    assert.equal(editor.structureRows()[0].available, false);
    assert.equal(editor.containsUnavailableBlock(stored), true);
    editor.cloneBlock(0);
    assert.equal(editor.blocks.length, 1);
    assert.deepEqual(JSON.parse(editor.getCleanSerializedSections()).es[0], stored);
});

test('retired nested keys make leaf payloads unavailable without aliasing', () => {
    assert.equal(hasRetiredPayloadKey({ type: 'alert', children: [] }, ['children']), true);
    assert.equal(hasRetiredPayloadKey({ type: 'alert', content: 'ok' }, ['children']), false);
    assert.equal(hasRetiredPayloadKey({ type: 'hotspots', items: [{ label: 'A', children: [] }] }, ['children']), true);
    assert.equal(hasRetiredPayloadKey({ type: 'hotspots', items: [{ label: 'A', text: 'ok' }] }, ['children']), false);
});
test('reducing a section keeps blocks in visual order across all removed columns', () => {
    const columns = ['one', 'two', 'three', 'four'].map(id => ({ id, blocks: [id] }));
    assert.deepEqual(resizeSectionColumns(columns, 2, String).map(column => column.blocks), [
        ['one'], ['two', 'three', 'four'],
    ]);
    assert.deepEqual(columns[1].blocks, ['two']);
});
