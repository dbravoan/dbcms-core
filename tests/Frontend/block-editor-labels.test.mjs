import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { EDITOR_LABEL_FALLBACK, setEditorLabels, t } from '../../resources/js/editor-labels.js';
import { createBlockManager } from '../../resources/js/block-manager.js';

const root = new URL('../../', import.meta.url);
const langGroup = locale => {
    const source = readFileSync(new URL(`lang/${locale}/admin.php`, root), 'utf8');
    const block = /'block_editor_js' => \[\n([\s\S]*?)\n    \],/.exec(source)?.[1] ?? '';
    const unquote = value => value.slice(1, -1).replace(/\\(['\\])/g, '$1');
    return Object.fromEntries([...block.matchAll(/^\s*('(?:[^'\\]|\\.)*') => ('(?:[^'\\]|\\.)*'),$/gm)].map(([, key, value]) => [unquote(key), unquote(value)]));
};

test.afterEach(() => setEditorLabels(undefined));

test('editor labels exist in es and en with the same keys, and es matches the JS fallback', () => {
    const es = langGroup('es'); const en = langGroup('en'); const keys = Object.keys(EDITOR_LABEL_FALLBACK).sort();
    assert.ok(keys.length > 100);
    assert.deepEqual(Object.keys(es).sort(), keys);
    assert.deepEqual(Object.keys(en).sort(), keys);
    assert.deepEqual(es, { ...EDITOR_LABEL_FALLBACK });
    for (const key of keys) assert.notEqual(en[key], '', key);
    for (const key of keys) assert.deepEqual((en[key].match(/:[a-z_]+/g) || []).sort(), (es[key].match(/:[a-z_]+/g) || []).sort(), key);
});

test('the manager uses the labels passed by Blade and falls back to Spanish', () => {
    const english = createBlockManager({ es: [] }, 'es', [], [], null, { labels: langGroup('en') });
    assert.equal(english.dividerTypes.find(option => option.value === 'waves').label, 'Soft waves');
    assert.equal(english.animationOptions[0].label, 'No animation');
    assert.equal(t('column_n', { n: 2 }), 'Column 2');
    assert.equal(t('confirm_copy_locale', { locale: 'EN' }), "This language's sections will be replaced with those from EN. Continue?");
    const spanish = createBlockManager({ es: [] }, 'es', [], [], null, {});
    assert.equal(spanish.dividerTypes.find(option => option.value === 'waves').label, 'Olas suaves');
    assert.equal(t('saved_reference', { id: 7 }), 'Referencia guardada (#7)');
    assert.equal(t('missing_key'), 'missing_key');
});

test('editor scripts keep no hardcoded Spanish UI literals outside the label catalogue', () => {
    // The inspector still recognises one backend-provided description prefix; it is data, not a label.
    const allowed = new Set(["'Valor tipado o variable del tema'"]);
    for (const file of ['editor-runtime.js', 'block-inspector.js', 'block-manager.js', 'media-field.js']) {
        const source = readFileSync(new URL(`resources/js/${file}`, root), 'utf8');
        const literals = [...source.matchAll(/(['`])((?:(?!\1)[^\n\\]|\\.)*?)\1/g)].map(match => match[0])
            .filter(literal => !allowed.has(literal) && /[áéíóúñ¿]|^.[A-ZÁÉ][a-záéíóúñ]+ [a-záéíóúñ]+/.test(literal) && !/\$\{|px;|=>|\(/.test(literal));
        assert.deepEqual(literals, [], file);
    }
});
