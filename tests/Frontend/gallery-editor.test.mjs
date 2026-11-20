import assert from 'node:assert/strict';
import test from 'node:test';
import { createGalleryEditor } from '../../resources/js/gallery-editor.js';

test('gallery selection preserves metadata and ignores unrelated file selections', () => {
    const previousWindow = globalThis.window;
    const previousEvent = globalThis.CustomEvent;
    const events = new EventTarget();
    globalThis.window = events;
    globalThis.CustomEvent = class extends Event {
        constructor(type, options) { super(type); this.detail = options.detail; }
    };
    try {
        const editor = createGalleryEditor();
        let target;
        events.addEventListener('open-filemanager', event => { target = event.detail.target; });
        editor.openFileManagerForNewItem();
        const file = { url: '/qa.webp', name: 'Old name', title: 'Saved title', alt_text: 'Saved alt', caption: 'Saved caption' };
        events.dispatchEvent(new CustomEvent('file-selected', { detail: { target: 'another-picker', file } }));
        assert.equal(editor.items.length, 0);
        events.dispatchEvent(new CustomEvent('file-selected', { detail: { target, file } }));
        assert.deepEqual(editor.items.map(({ id, ...item }) => item), [{ image_url: '/qa.webp', title: 'Saved title', alt_text: 'Saved alt', caption: 'Saved caption' }]);
        events.dispatchEvent(new CustomEvent('file-selected', { detail: { target, file } }));
        assert.equal(editor.items.length, 1);
    } finally {
        globalThis.window = previousWindow;
        globalThis.CustomEvent = previousEvent;
    }
});

test('opening a gallery locale leaves absent translations empty', () => {
    const editor = createGalleryEditor({ es: { title: 'Título español', description: 'Solo español' } }, [], ['es', 'en']);
    editor.init();
    editor.switchLocale('en');
    assert.equal(editor.activeLocale, 'en');
    assert.equal(editor.translations.en.title, '');
    assert.equal(editor.translations.en.description, '');
    assert.equal(editor.translations.es.title, 'Título español');
});
