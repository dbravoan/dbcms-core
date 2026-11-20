<?php

declare(strict_types=1);

use DbCms\Core\Models\Language;
use DbCms\Core\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en', 'it']);
    CmsFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('languages resolve an assigned flag or a sensible code fallback', function (): void {
    $assigned = Language::create([
        'code' => 'xx',
        'name' => 'Test',
        'native_name' => 'Test',
        'flag' => '🏳️',
        'is_active' => true,
    ]);
    expect($assigned->flag)->toBe('🏳️');

    // `fr` already ships in the language matrix: use a code without an
    // explicit flag to exercise the derivation from the ISO code.
    $fallback = Language::create([
        'code' => 'ja',
        'name' => 'Japanese',
        'native_name' => '日本語',
        'is_active' => true,
    ]);
    expect($fallback->flag)->toBe('🇯🇵');
});

test('an admin can assign a language flag from the backoffice', function (): void {
    $language = Language::where('code', 'it')->firstOrFail();

    $response = $this->actingAs($this->admin, 'web')
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->put(route('admin.languages.update', $language), [
            'name' => 'Catalan',
            'native_name' => 'Català',
            'flag' => '🏴',
            'direction' => 'ltr',
            'is_active' => 1,
        ]);

    $response->assertRedirect(route('admin.markets.index'));
    expect($language->fresh()->flag)->toBe('🏴');
});

test('locale tabs render the backoffice-assigned language flags', function (): void {
    $rendered = $this->blade(
        '<x-admin.locale-tabs :languages="\DbCms\Core\Models\Language::where(\'is_active\', true)->get()" />'
    );

    $rendered->assertSee('🇪🇸', false); // es
    $rendered->assertSee('🇺🇸', false); // en
});
