<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Mcp\Native\Servers\CmsMcpServer;
use DbCms\Core\Models\CapabilityAuditLog;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\Support\McpCoreComposition;
use Tests\Support\McpFixtures;

uses(RefreshDatabase::class, McpCoreComposition::class);

beforeEach(function (): void {
    McpFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

/**
 * The native transport (laravel/mcp) publishes the capability catalog and speaks
 * the standard protocol, so clients that require `initialize` can connect.
 */
test('the native server answers initialize with the standard handshake', function (): void {
    $response = $this->withToken($this->admin->createToken('native', ['mcp:access', 'mcp:read'])->accessToken)
        ->postJson('/mcp/cms', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 'test', 'version' => '1']],
        ]);

    $response->assertOk();
    expect($response->json('result.protocolVersion'))->toBe('2025-06-18')
        ->and($response->json('result.serverInfo.name'))->toBe((string) config('cms.name'))
        ->and($response->json('result.capabilities.tools'))->toBeArray();
});

test('the native server publishes the capability registry as native tools', function (): void {
    $token = $this->admin->createToken('native-list', ['mcp:access', 'mcp:read'])->accessToken;

    $response = $this->withToken($token)->postJson('/mcp/cms?limit=40', [
        'jsonrpc' => '2.0',
        'id' => 2,
        'method' => 'tools/list',
        'params' => [],
    ]);

    $response->assertOk();
    $tools = $response->json('result.tools');

    expect($tools)->toBeArray()
        ->and(array_column($tools, 'name'))->toContain('cms_capabilities')
        ->and(array_column($tools, 'name'))->not->toContain('cms_entity_create');
});

test('a capability runs through the native transport with the same security gates', function (): void {
    $token = $this->admin->createToken('native-call', ['mcp:access', 'mcp:read', 'mcp:write', 'mcp:destructive'])->accessToken;

    $call = fn (string $name, array $arguments = []) => $this->withToken($token)->postJson('/mcp/cms?limit=40', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => ['name' => $name, 'arguments' => $arguments],
    ]);

    // Read
    $list = $call('cms_entity_list', ['type' => 'page', 'per_page' => 2]);
    $list->assertOk();
    expect(json_decode((string) $list->json('result.content.0.text'), true))->toHaveKey('items');

    // Write
    $created = $call('cms_entity_create', [
        'type' => 'page',
        'attributes' => ['publish' => false],
        'translations' => ['es' => ['title' => 'Nativo MCP Test']],
    ]);
    $created->assertOk();
    $payload = json_decode((string) $created->json('result.content.0.text'), true);
    expect($payload['translations']['es']['slug'])->toBe('nativo-mcp-test');

    $refused = $call('cms_entity_delete', ['type' => 'page', 'id' => $payload['id']]);
    $pending = json_decode((string) $refused->json('result.content.0.text'), true);
    expect($pending['confirmation_required'])->toBeTrue();
    expect($pending['execution']['status'])->toBe('approval_required');
    expect(Page::find($payload['id']))->not->toBeNull();
    $stillPending = $call('cms_entity_delete', ['type' => 'page', 'id' => $payload['id'], 'confirm' => true]);
    expect(json_decode((string) $stillPending->json('result.content.0.text'), true)['confirmation_required'])->toBeTrue();
    expect(mcpTestHumanApproval($pending, $this->admin)['deleted'])->toBeTrue();

});

test('a token without the write scope is refused by the native transport', function (): void {
    $token = $this->admin->createToken('native-readonly', ['mcp:access', 'mcp:read'])->accessToken;

    $response = $this->withToken($token)->postJson('/mcp/cms?limit=40', [
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'tools/call',
        'params' => ['name' => 'cms_entity_create', 'arguments' => ['type' => 'page', 'translations' => ['es' => ['title' => 'No']]]],
    ]);

    expect($response->json('error'))->not->toBeNull();
    expect(Page::whereHas('translations', fn ($query) => $query->where('title', 'No'))->exists())->toBeFalse();
});

test('native calls are audited like the JSON-RPC transport', function (): void {
    $token = $this->admin->createToken('native-audit', ['mcp:access', 'mcp:read'])->accessToken;

    $this->withToken($token)->postJson('/mcp/cms?limit=40', [
        'jsonrpc' => '2.0',
        'id' => 5,
        'method' => 'tools/call',
        'params' => ['name' => 'cms_capabilities', 'arguments' => []],
    ])->assertOk();

    expect(CapabilityAuditLog::query()->where('tool', 'cms_capabilities')->where('status', 'ok')->exists())->toBeTrue();
});

test('the server can publish a bounded tool list for constrained clients', function (): void {
    mcpTestBindIdentity($this->admin);
    request()->merge(['limit' => 5]);

    // Built without the transport: the tool list is a pure projection of the
    // capability registry, so it can be inspected outside a request cycle.
    $server = new CmsMcpServer(new FakeTransporter);

    expect($server->capabilityTools())->toHaveCount(5);
});
