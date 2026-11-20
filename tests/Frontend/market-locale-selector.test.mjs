import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const template = readFileSync(new URL('../../resources/views/admin/markets/index.blade.php', import.meta.url), 'utf8');
const expression = template.match(/<x-admin\.language-select[^>]*name="default_locale"[^>]*x-init="([^"]+)"/s)?.[1];

function editor() {
    const watchers = new Map();
    const state = {
        editMarket: { default_locale: '' },
        showEditModal: false,
        selected: null,
        hydrate(value) { this.selected = value; },
        $watch(name, callback) { watchers.set(name, callback); },
    };
    assert.ok(expression, 'The edit selector must define its initialization contract.');
    Function('state', `with (state) { ${expression} }`)(state);
    return {
        state,
        open(locale) {
            state.editMarket = { default_locale: locale };
            state.showEditModal = true;
            watchers.get('showEditModal')?.(true);
        },
        close() {
            state.showEditModal = false;
            watchers.get('showEditModal')?.(false);
        },
    };
}

test('opening a market editor hydrates its stored default locale', () => {
    const view = editor();
    view.open('es');
    assert.equal(view.state.selected, 'es');
});

test('reopening the same market discards a previous unsaved selection', () => {
    const view = editor();
    view.open('es');
    view.state.selected = 'en';
    view.close();
    view.open('es');
    assert.equal(view.state.selected, 'es');
});

test('switching markets hydrates the next locale without changing saved data', () => {
    const view = editor();
    view.open('es');
    view.close();
    view.open('en');
    assert.equal(view.state.selected, 'en');
    assert.equal(view.state.editMarket.default_locale, 'en');
});
