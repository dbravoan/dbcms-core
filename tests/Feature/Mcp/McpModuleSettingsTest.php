<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Mcp\McpServiceProvider;
use DbCms\Core\Models\User;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Cms\CoreFeatureSettings;
use Illuminate\Validation\ValidationException;
use Tests\Support\McpCoreComposition;
use Tests\Support\McpFixtures;

uses(McpCoreComposition::class);

beforeEach(function (): void {
    McpFixtures::identities();
});

test('MCP is loaded in a core-only composition without a module manifest', function (): void {
    expect(app()->getProvider(McpServiceProvider::class))->not->toBeNull();
    expect(app(ModuleManager::class)->discoverModules())->not->toHaveKey('mcp');
    expect(app(CoreFeatureSettings::class)->get('mcp', 'is_enabled'))->toBeTrue();
});

test('MCP settings display its guide and link to the central token issuer', function (string $brand): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($brand);
    $response = $this->actingAs(User::where('email', 'admin@db-cms.io')->firstOrFail(), 'web')
        ->get(route('admin.settings.features.edit', ['feature' => 'mcp']));
    $response->assertOk()->assertSee('Servidor MCP')->assertSee('Autenticación Exclusiva con Laravel Passport')
        ->assertSee('opencode.json')->assertSee('.cursor/mcp.json')
        ->assertSee(route('admin.tokens.create'), false)
        ->assertDontSee('generate-token')->assertDontSee('onclick=', false);
})->with(['dbcms', 'fixture-admin']);

test('the MCP endpoint can be disabled through core settings without module state', function (): void {
    app(CoreFeatureSettings::class)->set('mcp', 'is_enabled', false);
    $this->postJson('/mcp/cms', ['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 1])->assertNotFound();
});

test('MCP rate limits reject nonpositive or fractional settings before persistence', function (mixed $limit): void {
    expect(fn () => app(CoreFeatureSettings::class)->setMany('mcp', ['rate_limit_per_minute' => $limit]))
        ->toThrow(ValidationException::class);
    expect(app(CoreFeatureSettings::class)->get('mcp', 'rate_limit_per_minute'))->toBe(60);
})->with([0, -1, 1.5]);
