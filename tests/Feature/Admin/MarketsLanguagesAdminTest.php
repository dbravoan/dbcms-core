<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en', 'it', 'pt'], ['es', 'us', 'mx']);
    CmsFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('the markets page renders the emoji picker for markets and languages', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get('/admin/markets');

    $response->assertOk()
        ->assertSee('name="flag"', false)
        ->assertSee('Seleccionar emoji', false)
        ->assertSee('Internacional', false);
});

test('a market flag is stored and falls back to the globe', function (): void {
    $this->actingAs($this->admin, 'web')->post('/admin/markets', [
        'code' => 'pt',
        'name' => 'Portugal',
        'flag' => '🥖',
        'currency' => 'EUR',
        'default_locale' => 'pt',
        'is_active' => '1',
    ])->assertRedirect(route('admin.markets.index'));

    $market = Market::where('code', 'pt')->firstOrFail();
    expect($market->flag)->toBe('🥖');

    $this->actingAs($this->admin, 'web')->post('/admin/markets', [
        'code' => 'global',
        'name' => 'Mercado Global',
        'currency' => 'EUR',
        'default_locale' => 'en',
        'is_active' => '1',
    ]);

    $global = Market::where('code', 'global')->firstOrFail();
    expect($global->flag)->toBe('🌐');
});

test('a market flag can be updated from the emoji picker', function (): void {
    $market = Market::where('code', 'us')->firstOrFail();

    $this->actingAs($this->admin, 'web')->put('/admin/markets/'.$market->id, [
        'name' => 'United States',
        'flag' => '🌐',
        'currency' => 'USD',
        'default_locale' => 'en',
        'is_active' => '1',
    ])->assertRedirect(route('admin.markets.index'));

    expect($market->fresh()->flag)->toBe('🌐');
});

test('markets and languages can be enabled and disabled, but not the defaults', function (): void {
    $market = Market::where('code', 'mx')->firstOrFail();
    $this->actingAs($this->admin, 'web')->post('/admin/markets/'.$market->id.'/toggle');
    expect($market->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin, 'web')->post('/admin/markets/'.$market->id.'/toggle');
    expect($market->fresh()->is_active)->toBeTrue();

    $language = Language::where('code', 'it')->firstOrFail();
    $this->actingAs($this->admin, 'web')->post(route('admin.languages.toggle', $language));
    expect($language->fresh()->is_active)->toBeFalse();

    // The default market/language cannot be disabled.
    $defaultMarket = Market::where('is_default', true)->firstOrFail();
    $defaultLanguage = Language::where('is_default', true)->firstOrFail();

    $this->actingAs($this->admin, 'web')->post('/admin/markets/'.$defaultMarket->id.'/toggle');
    $this->actingAs($this->admin, 'web')->post(route('admin.languages.toggle', $defaultLanguage));

    expect($defaultMarket->fresh()->is_active)->toBeTrue()
        ->and($defaultLanguage->fresh()->is_active)->toBeTrue();
});

test('an unused market can be removed', function (): void {
    $market = Market::create([
        'code' => 'zz',
        'name' => 'Test Market',
        'currency' => 'EUR',
        'default_locale' => 'en',
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'web')->delete('/admin/markets/'.$market->id)
        ->assertRedirect(route('admin.markets.index'));

    expect(Market::find($market->id))->toBeNull();
});

test('the default market and markets with content cannot be removed', function (): void {
    $default = Market::where('is_default', true)->firstOrFail();
    $this->actingAs($this->admin, 'web')->delete('/admin/markets/'.$default->id)
        ->assertSessionHas('error');
    expect(Market::find($default->id))->not->toBeNull();

    // A market referenced by content is protected (several tables cascade).
    $market = Market::create([
        'code' => 'zz',
        'name' => 'With content',
        'currency' => 'EUR',
        'default_locale' => 'en',
        'is_active' => true,
    ]);

    $page = Page::create(['template' => 'default', 'layout' => 'default', 'is_published' => false]);
    $page->markets()->attach($market->id);

    $this->actingAs($this->admin, 'web')->delete('/admin/markets/'.$market->id)
        ->assertSessionHas('error');

    expect(Market::find($market->id))->not->toBeNull();
});

test('a language can be removed but not the default one', function (): void {
    $language = Language::firstOrCreate(
        ['code' => 'ja'],
        ['name' => 'Japanese', 'native_name' => '日本語', 'direction' => 'ltr', 'is_active' => true],
    );

    $this->actingAs($this->admin, 'web')->delete(route('admin.languages.destroy', $language))
        ->assertRedirect(route('admin.markets.index'));

    expect(Language::find($language->id))->toBeNull();

    $default = Language::where('is_default', true)->firstOrFail();
    $this->actingAs($this->admin, 'web')->delete(route('admin.languages.destroy', $default))
        ->assertSessionHas('error');

    expect(Language::find($default->id))->not->toBeNull();
});

test('the flag emoji is derived from the code when none is assigned', function (): void {
    $language = Language::create([
        'code' => 'ja',
        'name' => 'Japanese',
        'native_name' => '日本語',
        'direction' => 'ltr',
        'is_active' => true,
    ]);

    expect($language->flag)->toBe('🇯🇵');

    // Language codes are not country codes: they resolve through the language map.
    $catalan = Language::firstOrCreate(
        ['code' => 'ca'],
        ['name' => 'Catalan', 'native_name' => 'Català', 'direction' => 'ltr', 'is_active' => true],
    );
    expect($catalan->flag)->toBe('🏴');

    $english = Language::firstOrCreate(
        ['code' => 'en'],
        ['name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_active' => true],
    );
    expect($english->flag)->toBe('🇺🇸');

    // A market, by contrast, derives its flag from the country code.
    $market = Market::create([
        'code' => 'pt',
        'name' => 'Portugal',
        'currency' => 'EUR',
        'default_locale' => 'pt',
        'is_active' => true,
    ]);
    expect($market->flag)->toBe('🇵🇹');
});
