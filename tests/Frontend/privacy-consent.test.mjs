import assert from 'node:assert/strict';
import test from 'node:test';
import { createConsentClient } from '../../resources/js/privacy-consent.js';

const response = value => ({ ok: true, json: async () => value });

test('a cached page cannot grant consent before the current server decision arrives', async () => {
    let release;
    const decisions = [];
    const client = createConsentClient({ endpoint: '/privacy/consent', fetcher: () => new Promise(resolve => { release = resolve; }), dispatch: prefs => decisions.push(prefs) });
    assert.equal(client.allows('external'), false);
    assert.equal(decisions.length, 0);
    release(response({ revision: 'current', csrf: 'fresh', configured: false, preferences: { necessary: true, external: false } }));
    await client.ready;
    assert.equal(client.allows('external'), false);
});

test('only a successful current-revision CSRF protected response changes consent', async () => {
    const calls = [];
    const client = createConsentClient({ endpoint: '/privacy/consent', fetcher: async (url, options) => {
        calls.push({ url, options });
        return response({ revision: 'policy-v4', csrf: 'fresh-token', configured: options.method === 'POST', preferences: { external: options.method === 'POST' } });
    }, dispatch: () => {} });
    await client.ready;
    await client.save({ external: true });
    assert.equal(client.allows('external'), true);
    assert.equal(calls[0].options.cache, 'no-store');
    assert.equal(calls[1].options.headers['X-CSRF-TOKEN'], 'fresh-token');
    assert.deepEqual(JSON.parse(calls[1].options.body), { revision: 'policy-v4', preferences: { external: true } });
});

test('failed persistence does not optimistically grant optional requests', async () => {
    const client = createConsentClient({ endpoint: '/privacy/consent', fetcher: async (url, options) => options.method ? { ok: false } : response({ revision: 'v4', csrf: 'token', preferences: { external: false } }), dispatch: () => {} });
    await client.ready;
    await assert.rejects(client.save({ external: true }));
    assert.equal(client.allows('external'), false);
});

test('necessary-only installations do not ask for optional consent', async () => {
    const { createConsentPanel } = await import('../../resources/js/privacy-consent.js');
    const previous = globalThis.window;
    globalThis.window = { addEventListener() {}, dbcmsConsent: { ready: Promise.resolve({ configured: false, preferences: { necessary: true } }) } };
    try {
        const panel = createConsentPanel();
        await panel.init();
        assert.equal(panel.showBanner, false);
    } finally { globalThis.window = previous; }
});

test('a changed grant waits for reload, withdrawal revokes immediately, and refusal stays on the page', async () => {
    const changes = [], decisions = [];
    const client = createConsentClient({ endpoint: '/privacy/consent', fetcher: async (url, options) => response({ revision: 'v4', csrf: 'token', preferences: options.method ? JSON.parse(options.body).preferences : { necessary: true, external: false } }), dispatch: preferences => decisions.push({ ...preferences }), onChange: revoked => changes.push(revoked) });
    await client.ready;
    await client.save({ external: false });
    assert.deepEqual(changes, []);
    const beforeGrant = decisions.length;
    await client.save({ external: true });
    assert.deepEqual(changes, [false]);
    assert.equal(decisions.length, beforeGrant);
    await client.save({ external: false });
    assert.deepEqual(changes, [false, true]);
    assert.equal(decisions.at(-1).external, false);
});


test('editorial preview never requests session consent even if a project override includes its controls', async () => {
    const { installConsent } = await import('../../resources/js/privacy-consent.js');
    const previous = { document: globalThis.document, window: globalThis.window, fetch: globalThis.fetch };
    let requests = 0;
    globalThis.document = {
        documentElement: { hasAttribute: name => name === 'data-cms-preview' },
        querySelector: () => ({ dataset: { consentEndpoint: '/privacy/consent' } }),
    };
    globalThis.window = { addEventListener() {} };
    globalThis.fetch = async () => { requests++; return response({ preferences: { necessary: true } }); };
    try {
        installConsent();
        assert.equal(requests, 0);
        assert.equal(window.dbcmsConsent, undefined);
    } finally { Object.assign(globalThis, previous); }
});
