import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const component = new URL('../../resources/views/components/admin/integration-guide-clipboard.blade.php', import.meta.url);

function clipboard(value, writeText = async () => {}) {
    assert.ok(existsSync(component), 'The guide needs a nonce-protected clipboard component.');
    const script = readFileSync(component, 'utf8').match(/<script[^>]*>([\s\S]*?)<\/script>/)?.[1];
    assert.ok(script, 'The clipboard component must contain its executable helper.');
    const context = { URL, navigator: { clipboard: { writeText } }, window: { location: { origin: 'https://cms.example' } } };
    vm.runInNewContext(script, context);
    const control = context.window.integrationGuideClipboard({ copied: 'Copied', failed: 'Select and copy manually' });
    const input = { value, focused: false, selected: false, focus() { this.focused = true; }, select() { this.selected = true; } };
    control.$refs = { callback: input };
    return { control, input };
}

test('the guide copies the exact public OAuth URL only after the button is pressed', async () => {
    const writes = [];
    const value = 'https://cms.example/control/social-publishing/oauth/youtube/callback';
    const { control } = clipboard(value, async text => writes.push(text));
    assert.deepEqual(writes, []);
    await control.copy();
    assert.deepEqual(writes, [value]);
    assert.equal(control.feedback, 'Copied');
});

test('clipboard rejects foreign URLs, credentials, query values and non-OAuth routes', async () => {
    for (const value of [
        'https://foreign.example/control/oauth/youtube/callback',
        'javascript:alert(1)',
        'https://user:secret@cms.example/control/oauth/youtube/callback',
        'https://cms.example/control/oauth/youtube/callback?code=secret',
        'https://cms.example/control/oauth/youtube/callback#token',
        'https://cms.example/control/modules/ai-assistant/settings',
    ]) {
        const writes = [];
        const { control } = clipboard(value, async text => writes.push(text));
        await control.copy();
        assert.deepEqual(writes, []);
        assert.equal(control.feedback, 'Select and copy manually');
    }
});

test('clipboard denial leaves the exact readonly URL selected and announces manual copying', async () => {
    const { control, input } = clipboard('https://cms.example/control/oauth/youtube/callback', async () => { throw new Error('Denied'); });
    await control.copy();
    assert.equal(input.focused, true);
    assert.equal(input.selected, true);
    assert.equal(control.feedback, 'Select and copy manually');
});
