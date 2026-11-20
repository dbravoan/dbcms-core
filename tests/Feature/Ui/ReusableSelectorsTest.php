<?php

declare(strict_types=1);

use DbCms\Core\Cms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
});

test('market select wraps the generic search select with the markets source', function (): void {
    $html = $this->blade('<x-admin.market-select name="market_id" value="es" />');

    $html->assertSee('markets', false);
    $html->assertSee('name="market_id"', false);
    $html->assertSee('searchSelect(', false);
});

test('language select wraps the generic search select with the languages source', function (): void {
    $html = $this->blade('<x-admin.language-select name="locale" value="es" />');

    $html->assertSee('languages', false);
    $html->assertSee('name="locale"', false);
    $html->assertSee('searchSelect(', false);
});

test('market select supports multiple values', function (): void {
    $html = $this->blade('<x-admin.market-select name="market_ids" multiple :values="[\'es\', \'mx\']" />');

    $html->assertSee('name="market_ids[]"', false);
});

test('market picker renders checkboxes and emits market change events', function (): void {
    $html = $this->blade('<x-admin.market-picker name="market_ids" />');

    $html->assertSee('name="market_ids[]"', false);
    $html->assertSee('market-picker-change', false);
    $html->assertSee('cms-markets-changed', false);
});

test('locale tabs render language buttons and emit the locale event', function (): void {
    $languages = collect([
        (object) ['code' => 'es', 'name' => 'Español'],
        (object) ['code' => 'en', 'name' => 'English'],
    ]);

    $html = $this->blade('<x-admin.locale-tabs :languages="$languages" active="en" />', compact('languages'));

    $html->assertSee('cms-locale-switched', false);
    $html->assertSee('Español', false);
    $html->assertSee('English', false);
});

test('module settings renderer maps the new market, language and entity select types', function (): void {
    // Los tipos de campo viven en el partial compartido por la lista global y
    // las pestañas de idioma/mercado.
    $source = file_get_contents(Cms::path('resources/views/admin/modules/partials/setting-field.blade.php'));

    expect($source)->toContain("'market_select'")
        ->toContain("'market_multiselect'")
        ->toContain("'language_select'")
        ->toContain("'language_multiselect'")
        ->toContain("'entity_select'")
        ->toContain("'entity_multiselect'");
});
