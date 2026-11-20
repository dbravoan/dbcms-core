<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Contracts\Services\IntegrationGuideServiceInterface;
use DbCms\Core\DTOs\Integrations\GuideStep;
use DbCms\Core\DTOs\Integrations\SetupGuideDescriptor;
use DbCms\Core\Integrations\IntegrationGuideRegistry;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config(['cms.installation_id' => 'guides-installation-a']);
    $this->admin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->admin->roles()->attach($role);
    CmsModule::firstOrCreate(['identifier' => 'inactive-guides'], ['name' => 'Inactive integration', 'version' => '1.0.0', 'provider' => 'DbCms\\DbCms\Modules\\InactiveGuides\\Provider', 'is_installed' => true, 'is_active' => false]);
    Route::get('/control/oauth/{provider}/callback', fn () => response('callback'))->name('test.guides.oauth.callback');
    Route::getRoutes()->refreshNameLookups();
    $this->guide = new SetupGuideDescriptor(
        id: 'provider', platform: 'Provider', title: 'Conectar el proveedor',
        prerequisites: ['Cuenta autorizada'],
        steps: [
            new GuideStep('prepare', 'Preparar la cuenta', 'Crear un proyecto en el proveedor.', 'Abrir la configuración del módulo.', 'La cuenta está preparada.', ['Documentación' => 'https://example.com/docs'], 'test.guides.oauth.callback', ['provider' => 'example']),
            new GuideStep('verify', 'Comprobar la conexión', 'Revisar permisos.', 'Comprobar desde la configuración.', 'Estado de la conexión visible.'),
        ],
        troubleshooting: ['Si faltan permisos, revisar la aplicación.'],
        officialSources: ['Documentación oficial' => 'https://example.com/docs'], reviewedAt: '2026-10-13', version: '1',
    );
    app(IntegrationGuideRegistry::class)->register('inactive-guides', [$this->guide]);
});

test('inactive module tutorials remain accessible without exposing any connection credentials', function (): void {
    $this->actingAs($this->admin)->get(route('admin.modules.guides.index', ['identifier' => 'inactive-guides']))
        ->assertOk()->assertSee('Conectar el proveedor');
    $this->get(route('admin.modules.guides.show', ['identifier' => 'inactive-guides', 'guide' => 'provider']))
        ->assertOk()->assertSee('Crear un proyecto en el proveedor.')
        ->assertSee(route('test.guides.oauth.callback', ['provider' => 'example']))
        ->assertSee('no acredita una conexión verificada');
});

test('guide routes require an authenticated administrative user', function (): void {
    $url = route('admin.modules.guides.show', ['identifier' => 'inactive-guides', 'guide' => 'provider']);
    $this->get($url)->assertRedirect(route('admin.login'));
    $this->actingAs(User::factory()->create())->get($url)->assertRedirect(route('admin.login'));
});

test('tutorial progress is isolated by user and installation and is informational only', function (): void {
    $service = app(IntegrationGuideServiceInterface::class);
    $service->updateProgress('inactive-guides', 'provider', (int) $this->admin->id, 'prepare', true);
    expect($service->show('inactive-guides', 'provider', (int) $this->admin->id)['completedSteps'])->toBe(['prepare']);
    expect($service->show('inactive-guides', 'provider', (int) User::factory()->create()->id)['completedSteps'])->toBe([]);
    config(['cms.installation_id' => 'guides-installation-b']);
    expect($service->show('inactive-guides', 'provider', (int) $this->admin->id)['completedSteps'])->toBe([]);
    config(['cms.installation_id' => 'guides-installation-a']);
    $service->updateProgress('inactive-guides', 'provider', (int) $this->admin->id, 'prepare', false);
    expect($service->show('inactive-guides', 'provider', (int) $this->admin->id)['completedSteps'])->toBe([]);
    expect(DB::table('integration_guide_progress')->count())->toBe(0);
});

test('guide progress accepts only known steps and never persists arbitrary fields', function (): void {
    $url = route('admin.modules.guides.progress', ['identifier' => 'inactive-guides', 'guide' => 'provider']);
    $this->actingAs($this->admin)->post($url, ['step_id' => 'unknown', 'completed' => true])->assertSessionHasErrors('step_id');
    $this->post($url, ['step_id' => 'prepare', 'completed' => true, 'access_token' => 'must-not-be-saved'])
        ->assertRedirect(route('admin.modules.guides.show', ['identifier' => 'inactive-guides', 'guide' => 'provider']));
    $row = (array) DB::table('integration_guide_progress')->first();
    expect($row)->not->toHaveKey('access_token')->and($row['installation_id'])->toBe('guides-installation-a');
});

test('unregistered tutorials return not found and never fetch remote documentation', function (): void {
    Http::preventStrayRequests();
    $this->actingAs($this->admin)->get(route('admin.modules.guides.show', ['identifier' => 'inactive-guides', 'guide' => 'missing']))->assertNotFound();
    $this->get(route('admin.modules.guides.index', ['identifier' => 'absent']))->assertNotFound();
    Http::assertNothingSent();
});

test('guide rendering escapes text and works in both canonical admin brands', function (): void {
    foreach (['dbcms', 'fixture-admin'] as $brand) {
        app(AdminThemeServiceInterface::class)->setActiveTheme($brand);
        $this->actingAs($this->admin)->get(route('admin.modules.guides.show', ['identifier' => 'inactive-guides', 'guide' => 'provider']))
            ->assertOk()->assertSee('Preparar la cuenta')->assertSee('admin-btn');
    }
});

test('guide navigation and clipboard controls have translated accessible labels in both admin brands', function (): void {
    foreach (['dbcms', 'fixture-admin'] as $brand) {
        app(AdminThemeServiceInterface::class)->setActiveTheme($brand);
        foreach (['es' => ['Copiar URL de retorno', 'Paso anterior', 'Paso siguiente'], 'en' => ['Copy return URL', 'Previous step', 'Next step']] as $locale => $labels) {
            $this->admin->forceFill(['locale' => $locale])->save();
            $response = $this->actingAs($this->admin)->get(route('admin.modules.guides.show', ['identifier' => 'inactive-guides', 'guide' => 'provider']));
            $response->assertOk()->assertSee('aria-live="polite"', false)->assertSee('href="#step-verify"', false)->assertSee('href="#step-prepare"', false);
            foreach ($labels as $label) {
                $response->assertSee($label);
            }
            $response->assertDontSee('integrations.copy_url');
        }
    }
});

test('settings routes are never presented as OAuth callback URLs', function (): void {
    $guide = new SetupGuideDescriptor('not-oauth', 'API key', 'Clave API', [], [new GuideStep('settings', 'Guardar clave', 'Crear clave.', 'Guardar configuración.', 'Configuración guardada.', callbackRoute: 'admin.modules.settings', callbackParameters: ['identifier' => 'inactive-guides'])], [], [], '2026-10-13');
    app(IntegrationGuideRegistry::class)->register('inactive-guides', [$guide]);
    $this->actingAs($this->admin)->get(route('admin.modules.guides.show', ['identifier' => 'inactive-guides', 'guide' => 'not-oauth']))
        ->assertOk()->assertDontSee('id="callback-settings"', false)->assertDontSee('Copiar URL de retorno');
});
