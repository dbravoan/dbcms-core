<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('uninstalled system redirects root web visits to /install', function (): void {
    if (File::exists(storage_path('installed'))) {
        File::delete(storage_path('installed'));
    }

    $response = $this->get('/');

    $response->assertRedirect(route('install.step1'));
});

test('installer routes bypass EnsureCmsIsInstalled redirection when uninstalled', function (): void {
    if (File::exists(storage_path('installed'))) {
        File::delete(storage_path('installed'));
    }

    $response1 = $this->get(route('install.step1'));
    $response1->assertStatus(200);
    $response1->assertViewIs('install.step1_requirements');

    $response2 = $this->get(route('install.step2'));
    $response2->assertStatus(200);
    $response2->assertSee('Configuración de la Base de Datos');
});

test('installed system redirects installer visits to admin login', function (): void {
    File::put(storage_path('installed'), (string) json_encode([
        'installed_at' => now()->toIso8601String(),
        'admin_path' => 'admin',
    ]));

    $response = $this->get(route('install.step1'));

    $response->assertRedirect('/admin/login');
});

test('only an authorized operator session can reach installation steps', function (): void {
    $this->withSession(['install.operator' => null]);
    $this->get(route('install.step2'))->assertRedirect(route('install.access'));
    $this->post(route('install.run-migrations'))->assertForbidden();

    $this->post(route('install.access.store'), ['install_token' => self::INSTALL_TOKEN])
        ->assertRedirect(route('install.step1'));
    for ($request = 0; $request < 12; $request++) {
        $this->get(route('install.step2'))->assertOk();
    }
});

test('a configured installer code grants access once and rotating it invalidates the previous session', function (): void {
    $this->withSession(['install.operator' => null]);
    $this->post(route('install.access.store'), ['install_token' => self::INSTALL_TOKEN])
        ->assertRedirect(route('install.step1'))->assertSessionHas('install.operator');
    $this->withSession(['install.operator' => null]);
    $this->post(route('install.access.store'), ['install_token' => self::INSTALL_TOKEN])
        ->assertRedirect(route('install.access'))->assertSessionHasErrors('install_token');

    $this->withSession(['install.operator' => hash('sha256', self::INSTALL_TOKEN)]);
    config(['cms.install_token' => bin2hex(random_bytes(32))]);
    $this->get(route('install.step2'))->assertRedirect(route('install.access'));
});

test('installer code submissions have a dedicated rate limit', function (): void {
    $this->withSession(['install.operator' => null]);
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->post(route('install.access.store'), ['install_token' => 'invalid'])
            ->assertRedirect(route('install.access'));
    }

    $this->post(route('install.access.store'), ['install_token' => self::INSTALL_TOKEN])
        ->assertTooManyRequests();
});
