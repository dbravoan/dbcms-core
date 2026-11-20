<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Models\User;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
});

test('login only advertises enabled existing demo personas in each distributed brand', function (string $theme): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    config(['cms.quick_login' => false]);
    $this->get(route('admin.login'))->assertOk()->assertDontSee('data-demo-personas', false);

    config(['cms.quick_login' => true]);
    $this->get(route('admin.login'))->assertOk()->assertDontSee('data-demo-personas', false);

    User::factory()->create(['email' => 'editor@db-cms.io']);
    $response = $this->get(route('admin.login'))->assertOk()->assertSee('data-demo-personas', false);
    $response->assertSee(route('admin.quick-login', 'editor'));
    $response->assertDontSee(route('admin.quick-login', 'admin'));
    $response->assertDontSee('secret123');

    app()->detectEnvironment(static fn (): string => 'production');
    $this->get(route('admin.login'))->assertOk()->assertDontSee('data-demo-personas', false);
})->with(['dbcms', 'fixture-admin']);
