import test from 'node:test';
import assert from 'node:assert/strict';
import { createBlockManager } from '../../resources/js/block-manager.js';

const types = [{ type: 'heading', name: 'Hero', defaultPayload: { title: 'Configured' }, schema: { title: { type: 'rich_text', label: 'Title' } } }];
test('editor insertion uses registered definitions and cannot insert unavailable types', () => {
    const editor = createBlockManager({ es: [] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    editor.addSectionWithBlock('removed');
    assert.equal(editor.blocks.length, 0);
    editor.addSectionWithBlock('heading', 'demo');
    const inserted = editor.blocks[0].columns[0].blocks[0];
    assert.equal(inserted.title, 'Configured');
    assert.equal(editor.schemaFields(inserted)[0].key, 'title');
});
test('editor serialization preserves unavailable payloads', () => {
    const stored = { id: 1, type: 'unavailable', payload: { important: ['kept'] } };
    const editor = createBlockManager({ es: [stored] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    assert.deepEqual(JSON.parse(editor.getCleanSerializedSections()).es, [stored]);
    editor.cloneBlock(0);
    assert.equal(editor.blocks.length, 1);
});

test('layout changes cannot move unavailable instances into another column', () => {
    const first = { type: 'heading', title: 'First' };
    const disabled = { type: 'unavailable', data: { retained: ['private'] } };
    const editor = createBlockManager({ es: [{ id: 'section', columns_layout: '2', columns: [
        { id: 'left', blocks: [first] }, { id: 'right', blocks: [disabled] },
    ] }], en: [] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    const before = JSON.parse(editor.getCleanSerializedSections());
    assert.equal(editor.canSetColumnsLayout(editor.blocks[0], '1'), false);
    editor.setColumnsLayout(0, '1');
    assert.deepEqual(JSON.parse(editor.getCleanSerializedSections()), before);
    assert.equal(editor.canSetColumnsLayout(editor.blocks[0], '1-2'), true);
    editor.setColumnsLayout(0, '1-2');
    assert.deepEqual(editor.blocks[0].columns, before.es[0].columns);
    editor.blocks[0].columns[0].blocks[0].title = 'Edited neighbour';
    assert.equal(JSON.parse(editor.getCleanSerializedSections()).es[0].columns[1].blocks[0].data.retained[0], 'private');
});

test('layouts may expand or reduce when unavailable instances retain their column', () => {
    const unavailable = { id: 'disabled', type: 'unavailable', settings: { keep: ['original'] } };
    const first = { id: 'left', style: { bg_color: '#123' }, blocks: [unavailable] };
    const editor = createBlockManager({ es: [{ type: 'heading', columns_layout: '3', columns: [first,
        { id: 'middle', blocks: [{ type: 'heading', title: 'Second' }] },
        { id: 'right', blocks: [{ type: 'heading', title: 'Third' }] },
    ] }] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    editor.setColumnsLayout(0, '2');
    assert.equal(editor.blocks[0].columns.length, 2);
    assert.deepEqual(editor.blocks[0].columns[0], first);
    editor.setColumnsLayout(0, '4');
    assert.equal(editor.blocks[0].columns.length, 4);
    assert.deepEqual(editor.blocks[0].columns[0], first);
    editor.setColumnsLayout(0, '1');
    assert.equal(editor.blocks[0].columns[0].id, 'left');
    assert.deepEqual(editor.blocks[0].columns[0].blocks[0], unavailable);
});

test('layout movement checks unavailable descendants in nested containers', () => {
    const editor = createBlockManager({ es: [{ type: 'heading', columns_layout: '3', columns: [
        { id: 'left', blocks: [{ type: 'heading', title: 'First' }] },
        { id: 'middle', blocks: [{ type: 'heading', title: 'Second' }] },
        { id: 'right', blocks: [{ type: 'heading', columns: [{ id: 'inner', blocks: [{ type: 'unavailable', important: ['retained'] }] }] }] },
    ] }] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    const before = editor.getCleanSerializedSections();
    assert.equal(editor.canSetColumnsLayout(editor.blocks[0], '2'), false);
    editor.setColumnsLayout(0, '2');
    assert.equal(editor.getCleanSerializedSections(), before);
});

test('opening columns moves standalone content into the first column without duplicating it', () => {
    const editor = createBlockManager({ es: [{ id: 'section', type: 'heading', title: 'Keep me', image_url: '/cover.png' }] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    editor.setColumnsLayout(0, '2');
    const section = JSON.parse(editor.getCleanSerializedSections()).es[0];
    assert.equal(section.title, undefined);
    assert.equal(section.columns[0].blocks[0].title, 'Keep me');
    assert.equal(section.columns[0].blocks[0].image_url, '/cover.png');
    assert.deepEqual(section.columns[1].blocks, []);
});

test('reducing four columns to two preserves the visual order of removed columns', () => {
    const editor = createBlockManager({ es: [{ id: 'section', columns_layout: '4', columns: [1, 2, 3, 4].map(number => ({
        id: `column-${number}`, blocks: [{ type: 'heading', title: `Block ${number}` }],
    })) }] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    editor.setColumnsLayout(0, '2');
    const section = JSON.parse(editor.getCleanSerializedSections()).es[0];
    assert.deepEqual(section.columns.flatMap(column => column.blocks.map(block => block.title)), ['Block 1', 'Block 2', 'Block 3', 'Block 4']);
});

test('field visibility follows show_when rules', () => {
    const editor = createBlockManager();
    const field = { show_when: { field: 'mode', equals: 'advanced' } };
    assert.equal(editor.fieldVisible({ mode: 'advanced' }, field), true);
    assert.equal(editor.fieldVisible({ mode: 'simple' }, field), false);
    assert.equal(editor.fieldVisible({ post_id: '' }, { show_when: { field: 'post_id', falsy: true } }), true);
    assert.equal(editor.fieldVisible({ post_id: '9' }, { show_when: { field: 'post_id', falsy: true } }), false);
    assert.equal(editor.fieldVisible({ chart_type: 'donut' }, { show_when: { field: 'chart_type', in: ['bar', 'line'] } }), false);
});

test('media selection applies the URL and alt text and ignores stale or cancelled requests', () => {
    const editor = createBlockManager({ es: [] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    const target = { image_url: '', image_alt: '' };
    editor.pendingMediaRequest = {
        mode: 'url', targetObj: target, fieldName: 'image_url', altKey: 'image_alt', targetId: 'media-target',
    };
    editor.handleFileSelected({ target: 'other-target', file: { url: '/wrong.jpg' } });
    assert.equal(target.image_url, '');
    editor.handleFileSelected({ target: 'media-target', file: { url: '/image.jpg', alt_text: 'Descripción' } });
    assert.equal(target.image_url, '/image.jpg');
    assert.equal(target.image_alt, 'Descripción');
    assert.equal(editor.pendingMediaRequest, null);

    editor.pendingMediaRequest = { mode: 'url', targetObj: target, fieldName: 'image_url', targetId: 'cancel-target' };
    editor.handleFileManagerClosed({ target: 'cancel-target' });
    assert.equal(editor.pendingMediaRequest, null);
    target.image_url = '';
    editor.pendingMediaRequest = { mode: 'url', targetObj: target, fieldName: 'image_url', targetId: 'keep-target' };
    editor.handleFileManagerClosed({ target: 'different-target' });
    assert.notEqual(editor.pendingMediaRequest, null);
    editor.clearBlockMedia(target, 'image_url');
    assert.equal(target.image_url, '');
});

test('media schema fields initialize empty instead of inheriting demo paths', () => {
    const definition = {
        type: 'media_block',
        schema: { image_url: { type: 'media' }, poster_url: { type: 'media' } },
        defaultPayload: { image_url: '/demo.jpg', poster_url: '/poster.jpg' },
    };
    const editor = createBlockManager({ es: [] }, 'es', [definition]);
    editor.blocks = editor.sectionsByLocale.es;
    editor.addSectionWithBlock('media_block', 'empty');
    const block = editor.blocks.at(-1).columns[0].blocks[0];
    assert.equal(block.image_url, '');
    assert.equal(block.poster_url, '');
    editor.addSectionWithBlock('media_block', 'demo');
    const demo = editor.blocks.at(-1).columns[0].blocks[0];
    assert.equal(demo.image_url, '/demo.jpg');
    assert.equal(demo.poster_url, '/poster.jpg');
});

for (const placement of ['section', 'column']) {
    test(`empty ${placement} insertion initializes the schema without demo content`, () => {
        const definition = {
            type: 'custom_panel',
            schema: {
                heading: { type: 'rich_text' },
                body: { type: 'textarea' },
                link: { type: 'url' },
                entries: { type: 'repeater', fields: { label: { type: 'rich_text' } } },
                tags: { type: 'list' },
                metadata: { type: 'json' },
                enabled: { type: 'boolean' },
                limit: { type: 'number', default: 4 },
                format: { type: 'select', options: ['markdown', 'html'] },
                selection: { type: 'select', options: { '': 'All', chosen: 'Chosen' } },
                settings: { type: 'json', default: { colors: ['blue'] } },
            },
            defaultPayload: {
                heading: 'Demo heading', body: 'Demo body', link: '/demo',
                entries: [{ label: 'Demo entry' }], tags: ['Demo tag'],
                metadata: { example: 'Demo' }, enabled: true, limit: 6,
                format: 'html', selection: 'chosen', settings: { colors: ['red'] },
            },
        };
        const editor = createBlockManager({ es: [] }, 'es', [definition]);
        editor.blocks = editor.sectionsByLocale.es;
        if (placement === 'column') {
            editor.blocks.push({ id: 'accordion', columns: [{ id: 'column', blocks: [] }] });
            editor.columnTarget = { blockIndex: 0, colIndex: 0 };
        }
        const insert = (mode) => {
            if (placement === 'column') editor.columnTarget = { blockIndex: 0, colIndex: 0 };
            editor.insertType('custom_panel', mode);
            if (placement === 'column') {
                return editor.blocks[0].columns[0].blocks.at(-1);
            }

            return editor.blocks.at(-1).columns[0].blocks.at(-1);
        };
        const empty = insert('empty');
        const values = (block) => Object.fromEntries(Object.keys(definition.schema).map(key => [key, block[key]]));
        assert.deepEqual(values(empty), {
            heading: '', body: '', link: '', entries: [], tags: [], metadata: {},
            enabled: false, limit: 4, format: 'markdown', selection: '', settings: { colors: ['blue'] },
        });
        assert.equal('title' in empty, false);
        assert.equal('form_slug' in empty, false);
        const demo = insert('demo');
        assert.deepEqual(values(demo), definition.defaultPayload);
        empty.settings.colors.push('green');
        demo.entries[0].label = 'Edited';
        assert.deepEqual(definition.schema.settings.default, { colors: ['blue'] });
        assert.equal(definition.defaultPayload.entries[0].label, 'Demo entry');
        assert.deepEqual(insert('empty').settings, { colors: ['blue'] });
        assert.equal(editor.sectionsByLocale.es, editor.blocks);
    });
}

test('opening columns after ordinary insertion preserves arbitrary schema content', () => {
    const definition = { type: 'custom', schema: { heading: { type: 'rich_text' }, entries: { type: 'json' } }, defaultPayload: { heading: 'Keep custom title', entries: { entity_id: 9 } } };
    const editor = createBlockManager({ es: [] }, 'es', [definition]);
    editor.blocks = editor.sectionsByLocale.es;
    editor.addSectionWithBlock('custom', 'demo');
    editor.setColumnsLayout(0, '2');
    const section = JSON.parse(editor.getCleanSerializedSections()).es[0];
    assert.equal(section.columns[0].blocks[0].heading, 'Keep custom title');
    assert.deepEqual(section.columns[0].blocks[0].entries, { entity_id: 9 });
    assert.equal(section.heading, undefined);
});

test('the effective editor branch follows stored columns even for one column', () => {
    const editor = createBlockManager();
    assert.equal(editor.hasColumnContent({ columns_layout: '1', columns: [{ blocks: [{ type: 'heading', title: 'Actual' }] }] }), true);
    assert.equal(editor.hasColumnContent({ columns_layout: '1', columns: [] }), false);
});

test('unavailable nested instances cannot be removed, moved or edited through appearance', () => {
    const unavailable = { id: 'disabled', type: 'unavailable', settings: { keep: ['original'] } };
    const section = { type: 'heading', columns: [{ id: 'column', blocks: [{ type: 'heading', title: 'First' }, unavailable, { type: 'heading', title: 'Last' }] }] };
    const editor = createBlockManager({ es: [section] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    const before = JSON.parse(editor.getCleanSerializedSections());
    editor.removeColumnBlock(0, 0, 1);
    editor.moveColumnBlockUp(0, 0, 1);
    editor.moveColumnBlockDown(0, 0, 1);
    editor.openAppearanceModal(0, { colIndex: 0, subIndex: 1 });
    assert.equal(editor.showDesignDialog, false);
    assert.deepEqual(JSON.parse(editor.getCleanSerializedSections()), before);
});


test('a section containing an unavailable descendant cannot be cloned or removed', () => {
    const section = { id: 19, type: 'heading', columns_layout: '2', columns: [
        { id: 'left', blocks: [{ id: 'active', type: 'heading', title: 'Editable neighbour' }] },
        { id: 'right', blocks: [{ id: 'nested', type: 'heading', columns: [{ id: 'inner', blocks: [{ id: 'disabled', type: 'unavailable', retained: { important: [1, 2] } }] }] }] },
    ] };
    const editor = createBlockManager({ es: [section] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    const before = editor.getCleanSerializedSections();
    editor.cloneBlock(0);
    editor.removeBlock(0);
    assert.equal(editor.getCleanSerializedSections(), before);
    assert.equal(editor.blocks.length, 1);
    editor.blocks[0].columns[0].blocks[0].title = 'Saved neighbour';
    const after = JSON.parse(editor.getCleanSerializedSections()).es[0];
    assert.equal(after.columns[0].blocks[0].title, 'Saved neighbour');
    assert.deepEqual(after.columns[1].blocks[0].columns[0].blocks[0].retained, { important: [1, 2] });
});

test('published templates clone section trees with renewed ids at the insert index', () => {
    const template = {
        id: 7,
        title: 'Hero',
        slug: 'plantilla-hero',
        section_count: 1,
        preview_url: '/admin/block-components/7/preview',
        sectionsByLocale: {
            es: [{
                id: 'tpl-sec',
                type: 'rich_text',
                columns_layout: '1',
                columns: [{ id: 'tpl-col', blocks: [{ id: 'tpl-heading', type: 'heading', text: 'Clonado' }] }],
            }],
        },
    };
    const existing = { id: 'page-sec', type: 'text', columns_layout: '1', columns: [{ id: 'page-col', blocks: [] }] };
    const editor = createBlockManager({ es: [existing] }, 'es', types, [], null, { templates: [template] });
    editor.blocks = editor.sectionsByLocale.es;
    editor.insertIndex = 0;
    editor.insertTemplate(7);
    assert.equal(editor.blocks.length, 2);
    assert.equal(editor.blocks[0].columns[0].blocks[0].text, 'Clonado');
    assert.notEqual(editor.blocks[0].id, 'tpl-sec');
    assert.notEqual(editor.blocks[0].columns[0].blocks[0].id, 'tpl-heading');
    assert.equal(editor.blocks[1].id, 'page-sec');
});

test('template tab helpers filter templates and open preview without inserting', () => {
    const template = {
        id: 7,
        title: 'Hero',
        slug: 'plantilla-hero',
        preview_url: '/admin/block-components/7/preview',
        sectionsByLocale: { es: [{ id: 'tpl-sec', type: 'text', columns: [{ id: 'c', blocks: [] }] }] },
    };
    const editor = createBlockManager({ es: [] }, 'es', types, [], null, { templates: [template], entityType: 'page' });
    editor.blocks = [];
    assert.equal(editor.hasCompositionTemplates(), true);
    assert.equal(editor.filteredTemplates().length, 1);
    editor.searchQuery = 'faq';
    assert.equal(editor.filteredTemplates().length, 0);
    editor.searchQuery = '';
    editor.activeCategory = 'templates';
    assert.equal(editor.filteredTypes().length, 0);
    editor.openTemplatePreview(7);
    assert.equal(editor.showTemplatePreview, true);
    assert.equal(editor.previewingTemplate().id, 7);
    assert.ok(editor.templatePreviewSrc().includes('/admin/block-components/7/preview'));
    assert.ok(editor.templatePreviewSrc().includes('locale=es'));
    assert.equal(editor.blocks.length, 0);
    editor.closeTemplatePreview();
    assert.equal(editor.showTemplatePreview, false);
    editor.openTemplatePreview(7);
    editor.insertPreviewedTemplate();
    assert.equal(editor.showTemplatePreview, false);
    assert.equal(editor.blocks.length, 1);
});

test('column insertAt places the new block between existing siblings', () => {
    const editor = createBlockManager({ es: [{
        id: 'sec', type: 'text', columns_layout: '1',
        columns: [{ id: 'col', blocks: [
            { id: 'a', type: 'heading', text: 'Primero' },
            { id: 'c', type: 'heading', text: 'Tercero' },
        ] }],
    }] }, 'es', types);
    editor.blocks = editor.sectionsByLocale.es;
    editor.columnTarget = { blockIndex: 0, colIndex: 0, insertAt: 1 };
    editor.insertType('heading', 'empty');
    const blocks = editor.blocks[0].columns[0].blocks;
    assert.equal(blocks.length, 3);
    assert.equal(blocks[0].id, 'a');
    assert.equal(blocks[1].type, 'heading');
    assert.notEqual(blocks[1].id, 'a');
    assert.equal(blocks[2].id, 'c');
});
