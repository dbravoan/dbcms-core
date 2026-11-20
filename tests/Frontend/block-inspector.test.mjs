import test from 'node:test';
import assert from 'node:assert/strict';
import { appearanceScope, splitDimension, expandBox, writeStyle, writeBoxSide, schemaContentFields, schemaDesignFields, validInspectorValue, DebouncedPreview, rootComposition, canMoveNodeInto, themeVariableOptions, simpleGradientSettings, simpleGradientCss, clearOwnerDesignOverrides } from '../../resources/js/block-inspector.js';
import { createBlockManager } from '../../resources/js/block-manager.js';

const definitions = [{ type: 'accordion', name: 'Accordion', schema: { layout: { type: 'select', group: 'design', options: { stack: 'Stack', grid: 'Grid' } }, gap: { type: 'number', group: 'design', clear_appearance_on_edit: ['gap', 'row_gap', 'column_gap'] }, grid_columns: { type: 'number', group: 'design' }, label: { type: 'rich_text' }, children: { type: 'blocks' } } }, { type: 'heading', name: 'Title', schema: { text: { type: 'rich_text' } } }];
const node = () => ({ id: 'root', type: 'accordion', children: [{ id: 'child', type: 'heading', text: 'Retained' }] });
const editor = roots => { const manager = createBlockManager({ es: roots }, 'es', definitions); manager.blocks = manager.sectionsByLocale.es; return manager; };

test('device styles and global states have distinct scopes without fabricated combinations', () => {
    assert.equal(appearanceScope('tablet', 'base'), 'tablet');
    assert.equal(appearanceScope('mobile', 'hover'), 'hover');
    const manager = editor([node()]); manager.selectNode([0]); manager.setInspectorDevice('mobile'); manager.setInspectorState('focus');
    manager.setAppearanceValue('color', '#abc');
    assert.deepEqual(manager.selectedNode().appearance, { focus: { color: '#abc' } });
    manager.setInspectorState('base'); manager.setAppearanceValue('width', '90%');
    assert.equal(manager.selectedNode().appearance.mobile.width, '90%');
});

test('dimension controls preserve custom expressions and explicit units without guessing defaults', () => {
    assert.deepEqual(splitDimension('1.25rem'), { amount: '1.25', unit: 'rem', custom: false });
    assert.deepEqual(splitDimension('var(--project-width)'), { amount: 'var(--project-width)', unit: '', custom: true });
    assert.deepEqual(splitDimension(''), { amount: '', unit: '', custom: false });
    assert.equal(validInspectorValue('1.', { control: 'length' }), false);
    assert.equal(validInspectorValue('1.2rem', { control: 'length' }), true);
    assert.equal(validInspectorValue('url(https://remote.test)', { control: 'color' }), false);
    assert.equal(validInspectorValue('#12345', { control: 'color' }), false);
    assert.equal(validInspectorValue('#123456', { control: 'color' }), true);
});

test('opening Design on legacy roots and columns does not normalize or mutate saved data', () => {
    const original = { ...node(), bg_color: '#123456', animation: 'fadeIn', columns: [{ id: 'column', blocks: [] }], appearance: { base: { width: '42ch' }, tablet: { color: '#abc' }, hover: { filter: 'brightness(1.1)' }, classes: ['project-owned'] }, future: { preserve: true } };
    const manager = editor([original]); const before = manager.getCleanSerializedSections();
    manager.openAppearanceModal(0);
    assert.equal(manager.inspectorTab, 'design'); assert.equal(manager.showDesignDialog, true);
    assert.equal(manager.appearanceValue('background_color'), '#123456');
    manager.openColumnAppearanceModal(0, 0); manager.presentationTarget();
    assert.equal(manager.inspectorColumn().column.style, undefined);
    assert.equal(manager.getCleanSerializedSections(), before);
});

test('editing one common property clears only its related legacy overlap and keeps unrelated states', () => {
    const original = { bg_color: '#123', bg_image_url: '/kept.jpg', animation: 'fadeIn', border_top: { enabled: true, width: '4px', color: '#abc' }, border_bottom: { enabled: true, width: '2px', style: 'dotted' }, appearance: { tablet: { color: '#456' }, hover: { opacity: .9 } } };
    writeStyle(original, 'base', 'background_color', '#fff');
    assert.equal(original.bg_color, undefined); assert.equal(original.bg_image_url, '/kept.jpg');
    writeStyle(original, 'base', 'border_width', '3px');
    assert.equal(original.border_top, undefined); assert.equal(original.border_bottom, undefined);
    assert.equal(original.appearance.base.border_top_style, 'solid'); assert.equal(original.appearance.base.border_top_color, '#abc');
    assert.equal(original.appearance.base.border_bottom_style, 'dotted'); assert.equal(original.appearance.base.border_bottom_color, 'var(--cms-accent)');
    assert.equal(original.appearance.base.border_top_width, undefined);
    writeStyle(original, 'base', 'background_color', '');
    assert.equal(original.appearance.base.background_color, undefined); assert.equal(original.bg_color, undefined);
    assert.deepEqual(original.appearance.tablet, { color: '#456' }); assert.deepEqual(original.appearance.hover, { opacity: .9 }); assert.equal(original.animation, 'fadeIn');
});

test('explicit border inheritance preserves untouched incomplete legacy defaults without reviving the cleared color', () => {
    const target = { border_top: { enabled: true, color: 'red', future: 'preserved' } };
    writeStyle(target, 'base', 'border_color', '');
    assert.deepEqual(target.appearance.base, { border_top_width: '1px', border_top_style: 'solid' });
    assert.deepEqual(target.border_top, { future: 'preserved' });
    const hover = { border_top: { enabled: true, color: 'red' } };
    writeStyle(hover, 'hover', 'border_color', 'blue');
    assert.deepEqual(hover.appearance.base, { border_top_width: '1px', border_top_style: 'solid', border_top_color: 'red' });
    assert.deepEqual(hover.appearance.hover, { border_color: 'blue' });
});

test('an independent spacing edit expands only the affected shorthand and preserves every other side', () => {
    const target = { padding: '10px 20px 30px 40px', bg_color: '#123', appearance: { mobile: { padding: '2px' } } };
    writeBoxSide(target, 'base', 'padding', 1, '7rem');
    assert.equal(target.padding, undefined);
    assert.deepEqual(target.appearance.base, { padding_top: '10px', padding_right: '7rem', padding_bottom: '30px', padding_left: '40px' });
    assert.deepEqual(target.appearance.mobile, { padding: '2px' });
    writeBoxSide(target, 'base', 'padding', 0, '', false);
    assert.equal(target.appearance.base.padding_top, undefined); assert.equal(target.appearance.base.padding_left, '40px');
    writeBoxSide(target, 'mobile', 'padding', 2, '8px', true);
    assert.deepEqual(target.appearance.mobile, { padding: '8px' }); assert.equal(target.bg_color, '#123');
    assert.deepEqual(expandBox('var(--space) 2rem'), ['var(--space)', '2rem', 'var(--space)', '2rem']);
});

test('owner design controls are projected into Design once and never copied into appearance', () => {
    assert.deepEqual(Object.keys(schemaDesignFields(definitions[0])), ['layout', 'gap', 'grid_columns']);
    assert.deepEqual(Object.keys(schemaContentFields(definitions[0])), ['label', 'children']);
    const manager = editor([node()]); manager.selectNode([0]); manager.selectedNode().gap = 18; manager.syncBlocksToLocale();
    assert.equal(manager.selectedNode().gap, 18); assert.equal(manager.selectedNode().appearance, undefined);
});

test('column edits target style only while the root preview keeps every neighbouring child', () => {
    const original = { ...node(), columns: [{ id: 'column', blocks: [{ id: 'inside', type: 'heading', text: 'Column' }] }] };
    const manager = editor([original]); manager.selectColumn([0, 'columns', 0]); manager.setAppearanceValue('width', '90%');
    assert.equal(original.appearance, undefined); assert.equal(original.columns[0].style.appearance.base.width, '90%');
    const input = manager.compositionPreviewInput(); // No endpoint configured means no network or preview payload.
    assert.equal(input, null);
    manager.editorOptions.previewUrl = '/private/preview';
    assert.equal(manager.compositionPreviewInput().payload.columns[0].blocks[0].text, 'Column');
    assert.equal(manager.compositionPreviewInput().selected_column_index, 0);
    assert.equal(rootComposition(manager.blocks, manager.structureRows(), 'inside', '[]'), original);
});

test('toolbar preview sends every root section while card preview keeps a single section payload', () => {
    const first = { id: 'a', type: 'heading', text: 'Uno' };
    const second = { id: 'b', type: 'heading', text: 'Dos' };
    const manager = editor([first, second]);
    manager.editorOptions.previewUrl = '/private/preview';
    manager.selectNode([0]);
    const selected = manager.compositionPreviewInput('selected');
    assert.equal(selected.type, 'heading');
    assert.equal(selected.payload.id, 'a');
    assert.equal(selected.sections, undefined);
    const all = manager.compositionPreviewInput('all');
    assert.equal(all.sections.length, 2);
    assert.equal(all.sections[0].payload.id, 'a');
    assert.equal(all.sections[1].payload.id, 'b');
    assert.equal(all.type, undefined);
});

test('viewport extent is root-only and is never written merely by selecting a node', () => {
    const manager = editor([node()]); manager.selectNode([0, 'children', 0]); manager.setExtent('viewport');
    assert.equal(manager.selectedNode().appearance, undefined);
    manager.selectNode([0]); manager.setExtent('viewport'); assert.equal(manager.selectedNode().appearance.extent, 'viewport');
    manager.setExtent(''); assert.equal(manager.selectedNode().appearance.extent, undefined);
});

test('debounced previews abort old work, ignore late results and do not restart for selection-only changes', () => {
    const timers = new Map(); let next = 0; const jobs = [];
    const preview = new DebouncedPreview({ setTimer: callback => { timers.set(++next, callback); return next; }, clearTimer: key => timers.delete(key) });
    const run = (controller, current) => jobs.push({ controller, current });
    preview.schedule('one', run); preview.schedule('two', run); assert.equal(timers.size, 1);
    [...timers.values()][0](); timers.clear(); assert.equal(jobs[0].current(), true);
    preview.schedule('two', run); assert.equal(timers.size, 0);
    preview.schedule('three', run); assert.equal(jobs[0].controller.signal.aborted, true); assert.equal(jobs[0].current(), false);
    preview.runNow('four', run); assert.equal(timers.size, 0); assert.equal(jobs[1].current(), true);
    preview.reset(); assert.equal(jobs[1].current(), false);
});

test('default preview timers retain the browser global receiver', () => {
    const beforeSet = globalThis.setTimeout; const beforeClear = globalThis.clearTimeout; const calls = [];
    globalThis.setTimeout = function () { assert.equal(this, globalThis); calls.push('set'); return 5; };
    globalThis.clearTimeout = function (id) { assert.equal(this, globalThis); assert.equal(id, 5); calls.push('clear'); };
    try { const preview = new DebouncedPreview(); preview.schedule('fixture', () => {}); preview.reset(); assert.deepEqual(calls, ['set', 'clear']); }
    finally { globalThis.setTimeout = beforeSet; globalThis.clearTimeout = beforeClear; }
});

test('model ancestry rejects drops into any descendant slot even when editor DOM trees are separate and IDs absent', () => {
    const root = { type: 'accordion', children: [{ type: 'accordion', children: [] }], columns: [{ blocks: [{ type: 'accordion', children: [] }] }] };
    assert.equal(canMoveNodeInto(root, root.children, definitions), false);
    assert.equal(canMoveNodeInto(root, root.children[0].children, definitions), false);
    assert.equal(canMoveNodeInto(root, root.columns[0].blocks[0].children, definitions), false);
    assert.equal(canMoveNodeInto(root.children[0], root.children, definitions), true);
    assert.equal(canMoveNodeInto(root, [], definitions), true);
});

test('invalid drafts are scoped to the current root and disappear after deletion replacement or history restoration', () => {
    const manager = editor([{ id: 'a', type: 'heading' }, { id: 'b', type: 'heading' }]); manager.selectNode([0]);
    manager.inspectorDraftValues['es:a:base:width'] = '1.'; manager.setInspectorValidity('es:a:base:width', false);
    assert.equal(manager.inspectorHasInvalid(), true);
    manager.selectNode([1]); assert.equal(manager.inspectorHasInvalid(), false);
    const beforeConfirm = globalThis.confirm; globalThis.confirm = () => true;
    try { manager.removeBlock(0); } finally { globalThis.confirm = beforeConfirm; }
    assert.deepEqual(manager.inspectorInvalid, {}); assert.deepEqual(manager.inspectorDraftValues, {});
    manager.setInspectorValidity('es:b:base:width', false); manager.undo(); assert.deepEqual(manager.inspectorInvalid, {});
    manager.selectNode([1]); manager.setInspectorValidity('es:b:base:width', false);
    manager.setBlocks([{ id: 'b', type: 'heading', text: 'Replacement' }]); assert.deepEqual(manager.inspectorInvalid, {});
});

test('a valid media picker result resolves the draft for that exact target and preserves other drafts', () => {
    const manager = editor([node()]); manager.selectNode([0]); manager.beginMediaRequest = request => { manager.pendingMediaRequest = request; };
    manager.inspectorDraftValues['es:root:base:bg_image_url'] = 'javascript:bad'; manager.setInspectorValidity('es:root:base:bg_image_url', false);
    manager.setInspectorValidity('es:child:base:width', false);
    manager.openInspectorMedia('bg_image_url', 'image'); manager.pendingMediaRequest.onSelect({ url: '/media/selected.jpg' });
    assert.equal(manager.selectedNode().bg_image_url, '/media/selected.jpg'); assert.equal(manager.inspectorInvalid['es:root:base:bg_image_url'], undefined);
    assert.equal(manager.inspectorDraftValues['es:root:base:bg_image_url'], undefined); assert.equal(manager.inspectorInvalid['es:child:base:width'], true);
});

test('before and after insertion preserve section position with one undo boundary', () => {
    const manager = editor([{ id: 'a', type: 'heading', text: 'A' }, { id: 'b', type: 'heading', text: 'B' }]);
    manager.openAddModalAt(1); manager.addSectionWithBlock('heading', 'empty');
    assert.deepEqual(manager.blocks.map(node => node.id === 'a' || node.id === 'b' ? node.id : 'new'), ['a', 'new', 'b']);
    manager.undo(); assert.deepEqual(manager.blocks.map(node => node.id), ['a', 'b']);
    manager.addEmptySection(1);
    assert.equal(manager.blocks.length, 3);
    assert.deepEqual(manager.blocks[1].columns?.[0]?.blocks ?? [], []);
    manager.undo(); assert.deepEqual(manager.blocks.map(node => node.id), ['a', 'b']);
});

test('one shared theme palette serves controls without repeated field metadata', () => {
    const palette = { 'var(--cms-accent)': 'Acento', 'var(--cms-fg)': 'Texto' };
    assert.deepEqual(themeVariableOptions({ supports_theme_variables: true }, palette), Object.entries(palette));
    assert.deepEqual(themeVariableOptions({ theme_variables: { 'var(--project-accent)': 'Proyecto' } }, palette), [['var(--project-accent)', 'Proyecto']]);
    assert.deepEqual(themeVariableOptions({}, palette), []);
});

test('visual gradients round trip representable stops without rewriting custom layers on open', () => {
    const defaults = { type: 'linear', angle: 135, from: 'var(--cms-surface)', to: 'var(--cms-accent)' };
    const css = 'linear-gradient(45deg, #112233 0%, #aabbcc 100%)';
    const parsed = simpleGradientSettings(css, defaults);
    assert.equal(parsed.supported, true); assert.equal(simpleGradientCss(parsed.settings), css);
    const rgba = simpleGradientSettings('linear-gradient(90deg, rgba(0, 0, 0, 0.5) 0%, var(--cms-accent) 100%)');
    assert.equal(rgba.supported, true); assert.equal(rgba.settings.from, 'rgba(0, 0, 0, 0.5)');
    const custom = 'linear-gradient(90deg, red 0%, green 50%, blue 100%)';
    assert.deepEqual(simpleGradientSettings(custom, defaults), { supported: false, source: custom });
    assert.equal(simpleGradientSettings('radial-gradient(circle, red 0%, blue 100%)').supported, true);
    assert.equal(simpleGradientSettings('radial-gradient(ellipse at top, red, blue)').supported, false);
    assert.throws(() => simpleGradientCss({ type: 'linear', angle: 45, from: 'url(https://remote.test)', to: '#fff' }));
});

test('an owner layout edit clears only its base CSS overlap and retains responsive and unknown values', () => {
    const target = { gap: 20, appearance: { base: { gap: '48px', row_gap: '9px', color: '#abc', display: 'flex' }, mobile: { gap: '2px' }, hover: { color: '#def' } } };
    clearOwnerDesignOverrides(target, schemaDesignFields(definitions[0]).gap);
    assert.deepEqual(target.appearance.base, { color: '#abc', display: 'flex' });
    assert.deepEqual(target.appearance.mobile, { gap: '2px' }); assert.deepEqual(target.appearance.hover, { color: '#def' });
});

test('explicit background changes resolve only their replaced gradient drafts and base paint', () => {
    const manager = editor([{ ...node(), bg_type: 'gradient', bg_color: '#123', bg_image_url: '/retained.jpg', appearance: { base: { background_image: 'linear-gradient(45deg, red, blue)', background_color: '#abc', color: '#def' }, mobile: { background_image: 'linear-gradient(90deg, white, black)' } } }]); manager.selectNode([0]);
    for (const field of ['background_image_angle', 'background_image_from']) { manager.inspectorDraftValues[`es:root:base:${field}`] = 'incomplete'; manager.setInspectorValidity(`es:root:base:${field}`, false); }
    manager.setAppearanceValue('background_image', 'linear-gradient(45deg, red, blue)', { preserveGradientDrafts: true });
    assert.equal(manager.inspectorHasInvalid(), true);
    manager.setAppearanceValue('background_image', 'linear-gradient(90deg, red, blue)');
    assert.equal(manager.inspectorHasInvalid(), false);
    manager.setBackgroundType('color'); assert.equal(manager.selectedNode().appearance.base.background_image, undefined);
    assert.equal(manager.selectedNode().appearance.base.background_color, '#abc'); assert.equal(manager.selectedNode().bg_image_url, '/retained.jpg');
    manager.setBackgroundType('none'); assert.equal(manager.selectedNode().appearance.base.background_color, undefined); assert.equal(manager.selectedNode().bg_color, undefined);
    assert.equal(manager.selectedNode().appearance.base.color, '#def'); assert.equal(manager.selectedNode().appearance.mobile.background_image, 'linear-gradient(90deg, white, black)');
});
