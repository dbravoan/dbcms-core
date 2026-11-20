<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

test('login page adapts branding according to the active backoffice theme', function (string $brand, string $expectedBrandText, string $expectedSubtext): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($brand);

    $response = $this->get(route('admin.login'));

    $response->assertStatus(200);
    $response->assertSee($expectedBrandText);
    $response->assertSee($expectedSubtext);
})->with([
    ['dbcms', 'dbCMS', 'Autenticación'],
    ['fixture-admin', 'Fixture Admin', 'Autenticación'],
]);

test('admin dashboard layout renders each brand and its optional badge', function (string $brand, string $expectedBrand, ?string $expectedBadge): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($brand);

    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertStatus(200);
    $response->assertSee($expectedBrand);
    if ($expectedBadge === null) {
        $response->assertDontSee('CORPORATE');
    } else {
        $response->assertSee($expectedBadge);
    }
})->with([
    ['dbcms', 'dbCMS', 'CORE'],
    ['fixture-admin', 'Fixture Admin', null],
]);

test('error 404 page renders matching theme flavor layout', function (string $brand, string $expectedTitle): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($brand);

    $response = $this->get('/a-completely-non-existent-cms-route-404');

    $response->assertStatus(404);
    $response->assertSee($expectedTitle);
})->with([
    ['dbcms', 'dbCMS'],
    ['fixture-admin', 'Fixture Admin'],
]);
