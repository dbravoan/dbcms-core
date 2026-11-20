import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const template = readFileSync(new URL('../../resources/views/admin/orchestration/panel.blade.php', import.meta.url), 'utf8');

function panel(initialExecutionId = null, orchestrationEnabled = true) {
    let script = template.match(/<script[^>]*>([\s\S]*?)<\/script>/)[1];
    while (script.includes('@json(')) {
        const start = script.indexOf('@json(');
        let depth = 1;
        let quote = null;
        let end = start + 6;
        for (; depth > 0 && end < script.length; end++) {
            const char = script[end];
            if (quote) {
                if (char === '\\') end++;
                else if (char === quote) quote = null;
            } else if (char === "'" || char === '"') quote = char;
            else if (char === '(') depth++;
            else if (char === ')') depth--;
        }
        assert.equal(depth, 0);
        const expression = script.slice(start + 6, end - 1);
        let value;
        if (expression.includes("isset($initialExecutionId) ? url")) value = initialExecutionId ? '/admin/executions' : '/admin/orchestration';
        else if (expression.includes("Route::has(")) value = orchestrationEnabled ? '/admin/orchestration' : null;
        else if (expression.includes("url(config('cms.admin_path').'/executions')")) value = '/admin/executions';
        else if (expression.includes('$initialExecutionId ?? null')) value = initialExecutionId;
        else if (expression === 'isset($initialExecutionId)') value = Boolean(initialExecutionId);
        else throw new Error(`Unhandled Blade expression: ${expression}`);
        script = script.slice(0, start) + JSON.stringify(value) + script.slice(end);
    }
    const requests = [];
    const context = {
        URLSearchParams, location: { search: '?execution=execution-1' },
        document: { querySelector: () => ({ content: 'csrf-token' }) },
        clearTimeout() {}, setTimeout() {}, crypto: { randomUUID: () => 'request-1' },
        fetch: async (url, options) => {
            requests.push({ url, ...options });
            return { ok: true, json: async () => ({ id: 'execution-1', status: 'approval_required', steps: [] }) };
        },
    };
    vm.runInNewContext(script, context);
    return { state: context.cmsExecution(), requests };
}

for (const [entrypoint, id, enabled] of [
    ['natural language panel', null, true],
    ['direct core review', 'execution-1', false],
]) {
    test(`${entrypoint} approves and polls through registered core routes`, async () => {
        const { state, requests } = panel(id, enabled);
        state.execution = { id: 'execution-1' };
        await state.act('approve', { step: 'publish', binding_hash: 'reviewed-content' });
        assert.equal(requests[0].url, '/admin/executions/execution-1/approve');
        assert.equal(requests[0].method, 'POST');
        assert.equal(JSON.parse(requests[0].body).binding_hash, 'reviewed-content');
        assert.equal(requests[1].url, '/admin/executions/execution-1/status');
        assert.equal(state.error, '');
    });
}

test('the natural language panel sends instructions and answers to its optional planner', async () => {
    const { state, requests } = panel();
    state.instruction = 'Prepare content';
    await state.start();
    assert.equal(requests[0].url, '/admin/orchestration');
    assert.equal(JSON.parse(requests[0].body).instruction, 'Prepare content');
    await state.act('respond', { answer: 'Spanish' });
    assert.equal(requests[2].url, '/admin/orchestration/execution-1/respond');
});

test('a direct clarification without a planner keeps its question without sending an invalid request', async () => {
    const { state, requests } = panel('execution-1', false);
    state.execution = { id: 'execution-1', status: 'needs_input', question: 'Which language?' };
    await state.act('respond', { answer: 'Spanish' });
    assert.equal(requests.length, 0);
    assert.equal(state.execution.question, 'Which language?');
    assert.match(state.error, /Completa los datos/);
});
