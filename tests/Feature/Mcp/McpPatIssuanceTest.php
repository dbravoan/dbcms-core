<?php

declare(strict_types=1);

use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\McpCoreComposition;
use Tests\Support\McpFixtures;

uses(RefreshDatabase::class, McpCoreComposition::class);

beforeEach(function (): void {
    McpFixtures::identities();
});

test('cms:issue-mcp-token issues a token that the mcp endpoint accepts', function (): void {
    $this->artisan('cms:issue-mcp-token', ['--name' => 'PAT Smoke'])->assertSuccessful();

    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $tokenResult = $admin->createToken('PAT Smoke', ['mcp:access', 'mcp:read', 'mcp:write']);

    $response = $this->withToken($tokenResult->accessToken)->postJson('/mcp/cms', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ]);

    $response->assertOk();
    expect($response->json('result.tools'))->toBeArray()->not->toBeEmpty();
});
