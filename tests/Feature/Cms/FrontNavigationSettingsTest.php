<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en'], ['es']);
    CmsFixtures::home(['es', 'en'], ['es']);
    CmsFixtures::identities();
    config(['cms.theme' => 'fixture-theme', 'cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);
});

afterEach(function (): void {
    Cache::flush();
});

test('the backoffice updates the front navigation settings', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $this->actingAs($admin, 'web')
        ->put(route('admin.settings.update'), [
            'front_nav_admin_button' => '1',
            'front_nav_layout' => 'dropdown',
            'front_nav_toggle_enabled' => '1',
            'front_nav_toggle_icon' => 'grid',
            'front_nav_toggle_desktop' => '1',
        ])
        ->assertRedirect(route('admin.settings.index'));

    expect((bool) setting('front.nav_admin_button'))->toBeTrue()
        ->and((string) setting('front.nav_layout'))->toBe('dropdown')
        ->and((string) setting('front.nav_toggle_icon'))->toBe('grid')
        ->and((bool) setting('front.nav_toggle_desktop'))->toBeTrue();
});

test('unchecked navigation checkboxes are stored as false', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $this->actingAs($admin, 'web')
        ->put(route('admin.settings.update'), [])
        ->assertRedirect(route('admin.settings.index'));

    expect((bool) setting('front.nav_admin_button'))->toBeFalse()
        ->and((bool) setting('front.nav_toggle_enabled'))->toBeFalse()
        ->and((bool) setting('front.nav_toggle_desktop'))->toBeFalse();
});

test('the front navbar hides the backoffice button by default and wires the toggle', function (): void {
    $html = (string) $this->get('/es/es/')->assertOk()->getContent();

    expect($html)
        ->toContain('data-nav-toggle')
        ->not->toContain('>_admin');
});

test('the backoffice button appears on the front navbar when enabled', function (): void {
    app(GeneralSettingsServiceInterface::class)->set('front.nav_admin_button', true, 'front', 'boolean');

    $html = (string) $this->get('/es/es/')->assertOk()->getContent();

    expect($html)->toContain('>_admin');
});

test('the collapsible toggle can be disabled from the backoffice', function (): void {
    app(GeneralSettingsServiceInterface::class)->set('front.nav_toggle_enabled', false, 'front', 'boolean');

    $html = (string) $this->get('/es/es/')->assertOk()->getContent();

    expect($html)->not->toContain('data-nav-toggle');
});
