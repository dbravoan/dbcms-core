<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

test('a guest can switch the backoffice language from the login screen', function (): void {
    $this->from('/admin/login')
        ->post('/admin/locale', ['locale' => 'en'])
        ->assertRedirect('/admin/login');

    expect(session('backoffice_locale'))->toBe('en');

    // The login screen itself is now rendered in the chosen language.
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('Panel access');

    $this->post('/admin/locale', ['locale' => 'es'])->assertRedirect();

    expect(session('backoffice_locale'))->toBe('es');
});

test('the locale switcher is a working segmented control that marks the current locale', function (): void {
    $response = $this->get('/admin/login')->assertOk();

    $response->assertSee('action="'.route('admin.locale.update').'"', false)
        ->assertSee('name="locale"', false)
        ->assertSee('aria-pressed="true"', false)
        ->assertSee('value="en"', false);
});
