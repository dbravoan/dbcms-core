import test from 'node:test';
import assert from 'node:assert/strict';
import { createVisitorGeoSelector } from '../../resources/js/visitor-geo.js';

const markets = [
    { code: 'es', defaultLocale: 'es', languages: [{ code: 'es', url: 'https://cms.test/es' }, { code: 'ca', url: 'https://cms.test/ca' }] },
    { code: 'uk', defaultLocale: 'en', languages: [{ code: 'en', url: 'https://cms.test/uk/en' }] },
];
function visitor(overrides = {}) {
    const listeners = new Map();
    const visits = [];
    const stored = new Map();
    const browser = {
        document: { cookie: '' },
        location: { protocol: 'https:', assign: url => visits.push(url) },
        localStorage: { setItem: (key, value) => stored.set(key, value) },
        addEventListener: (name, listener) => {
            const current = listeners.get(name) ?? new Set();
            current.add(listener);
            listeners.set(name, current);
        },
        removeEventListener: (name, listener) => listeners.get(name)?.delete(listener),
    };
    const selector = createVisitorGeoSelector({ markets, autoShow: false, currentMarket: 'es', currentLocale: 'ca', recommendedMarket: 'uk', recommendedLocale: 'en', ...overrides }, browser);
    selector.init();
    return { selector, browser, visits, stored, listeners, open: () => listeners.get('open-geo-modal')?.forEach(listener => listener()) };
}

test('returning visitors can manually reopen the mounted selector after staying and reloading', () => {
    const first = visitor({ autoShow: true });
    assert.equal(first.selector.showModal, true);
    assert.equal(first.selector.selectedMarket, 'uk');
    first.selector.stayCurrent();
    assert.equal(first.selector.showModal, false);
    assert.match(first.browser.document.cookie, /cms_geo_preference=es_ca;.*max-age=31536000; SameSite=Lax; Secure/);
    const returning = visitor({ autoShow: false });
    assert.equal(returning.selector.showModal, false);
    returning.open();
    assert.equal(returning.selector.showModal, true);
    assert.equal(returning.selector.selectedMarket, 'es');
    assert.equal(returning.selector.selectedLocale, 'ca');
});

test('confirmation uses only the known server URL and rejects a language from another market', () => {
    const view = visitor();
    view.selector.selectedMarket = 'uk';
    view.selector.selectedLocale = 'ca';
    assert.equal(view.selector.confirmSelection(), false);
    assert.deepEqual(view.visits, []);
    assert.equal(view.browser.document.cookie, '');
    view.selector.selectMarket('uk');
    assert.equal(view.selector.selectedLocale, 'en');
    assert.equal(view.selector.confirmSelection(), true);
    assert.deepEqual(view.visits, ['https://cms.test/uk/en']);
    assert.equal(view.stored.get('cms_geo_preference'), 'uk_en');
    view.selector.selectedMarket = '//attacker.test';
    assert.equal(view.selector.confirmSelection(), false);
    assert.deepEqual(view.visits, ['https://cms.test/uk/en']);
});

test('single-market selection follows the canonical language-only URL without invented prefixes', () => {
    const view = visitor({ markets: [markets[0]] });
    assert.equal(view.selector.confirmSelection(), true);
    assert.deepEqual(view.visits, ['https://cms.test/ca']);
});

test('invalid recommendations fall back to an available market locale and storage denial still saves the cookie', () => {
    const view = visitor({ autoShow: true, recommendedMarket: 'missing', recommendedLocale: 'unsupported' });
    assert.equal(view.selector.selectedMarket, 'es');
    assert.equal(view.selector.selectedLocale, 'es');
    view.browser.localStorage.setItem = () => { throw new Error('Storage unavailable'); };
    assert.equal(view.selector.confirmSelection(), true);
    assert.match(view.browser.document.cookie, /cms_geo_preference=es_es/);
});

test('one component registers one manual listener and removes it when destroyed', () => {
    const view = visitor();
    const listener = view.selector.openListener;
    view.selector.init();
    assert.equal(view.selector.openListener, listener);
    view.selector.destroy();
    view.open();
    assert.equal(view.selector.showModal, false);
    view.selector.init();
    view.open();
    assert.equal(view.selector.showModal, true);
});
