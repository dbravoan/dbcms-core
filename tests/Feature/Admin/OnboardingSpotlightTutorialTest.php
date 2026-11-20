<?php

declare(strict_types=1);

use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

test('admin version labels preserve the installed Composer version without adding another prefix', function (string $brand, string $version): void {
    config(['cms.admin_theme' => $brand, 'cms.version' => $version]);
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $this->actingAs($admin, 'web')->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('SISTEMA OPERATIVO &bull; '.$version, false)
        ->assertDontSee('vv0.1.3');
})->with(['dbcms', 'fixture-admin'])->with(['0.1.3', 'v0.1.3']);

test('core backoffice onboarding targets rendered navigation in both brands', function (string $brand, bool $extras): void {
    config([
        'cms.admin_theme' => $brand,
        'cms.quick_login' => $extras,
        'cms.adminer_url' => $extras ? 'http://localhost:8099' : null,
    ]);
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($admin, 'web')
        ->get(route('admin.dashboard'));

    $response->assertStatus(200);

    $response->assertSee('start-onboarding-tour');
    $response->assertSee("target: '#nav-pages'", false);
    if ($extras) {
        $response->assertSee("target: '#nav-adminer'", false);
        $response->assertSee("target: '#nav-persona-switcher'", false);
    } else {
        $response->assertDontSee("target: '#nav-adminer'", false);
        $response->assertDontSee("target: '#nav-persona-switcher'", false);
    }

    $html = (string) $response->getContent();
    preg_match_all("/target:\\s*'(#nav-[^']+)'/", $html, $targets);
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    foreach ($targets[1] as $target) {
        expect($document->getElementById(substr($target, 1)))->not->toBeNull("Missing onboarding target: {$target}");
    }
})->with([
    ['dbcms', false],
    ['dbcms', true],
    ['fixture-admin', false],
    ['fixture-admin', true],
]);

test('both admin brands show errors returned by module operations', function (string $brand): void {
    config(['cms.admin_theme' => $brand]);
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $this->actingAs($admin, 'web')
        ->withSession(['error' => 'Falta una dependencia del módulo.'])
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Falta una dependencia del módulo.');
})->with(['dbcms', 'fixture-admin']);

test('dashboard market hint does not claim unconfigured countries', function (string $brand): void {
    config(['cms.admin_theme' => $brand]);
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $this->actingAs($admin, 'web')->get(route('admin.dashboard'))
        ->assertOk()->assertDontSee('ES / US / MX');
})->with(['dbcms', 'fixture-admin']);
