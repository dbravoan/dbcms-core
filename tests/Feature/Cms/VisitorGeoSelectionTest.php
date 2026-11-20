<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Cms\PageCacheService;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    $this->withoutVite();
    config(['cms.page_cache.enabled' => false]);
});

/** @return array<string, mixed> */
function visitorGeoData(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $component = (new DOMXPath($document))->query('//*[@data-geo-selector]')->item(0);
    expect($component)->toBeInstanceOf(DOMElement::class);

    return json_decode($component->getAttribute('data-geo-selector'), true, flags: JSON_THROW_ON_ERROR);
}

test('the selector publishes actual canonical destinations for every available market language', function (string $profile): void {
    $locales = $profile === 'single-language' ? ['es'] : ['es', 'en'];
    $markets = $profile === 'multi-market' ? ['es', 'uk'] : ['es'];
    CmsFixtures::localization($locales, $markets);
    CmsFixtures::home($locales, $markets);
    $home = app(LocalizationServiceInterface::class)->url('/', Market::where('code', 'es')->sole(), 'es');
    $data = visitorGeoData($this->get($home)->assertOk()->getContent());
    expect($data['autoShow'])->toBe($profile !== 'single-language');
    foreach ($data['markets'] as $choice) {
        $market = Market::where('code', $choice['code'])->sole();
        foreach ($choice['languages'] as $language) {
            expect($language['url'])->toBe(app(LocalizationServiceInterface::class)->url('/', $market, $language['code']));
            $this->get($language['url'])->assertOk();
        }
    }
})->with(['single-language', 'multi-language', 'multi-market']);

test('a returning visitor still receives the manual selector while cached auto prompting stays disabled', function (): void {
    CmsFixtures::localization(['es', 'en'], ['es']);
    CmsFixtures::home(['es', 'en'], ['es']);
    config(['cms.page_cache.enabled' => true]);
    app(PageCacheService::class)->invalidate();
    $first = $this->get('/es')->assertOk()->assertHeader('X-Page-Cache', 'MISS');
    expect(visitorGeoData($first->getContent())['autoShow'])->toBeTrue();
    $returning = $this->withUnencryptedCookie('cms_geo_preference', 'es_es')->get('/es')->assertOk()->assertHeader('X-Page-Cache', 'HIT');
    $data = visitorGeoData($returning->getContent());
    expect($data['autoShow'])->toBeFalse()->and($data['currentMarket'])->toBe('es')->and($data['currentLocale'])->toBe('es');
    $returning->assertSee('visitorGeoSelector', false);
});

test('selector choices exclude inactive markets and languages and preserve market membership', function (): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'uk', 'hidden']);
    CmsFixtures::home(['es', 'en'], ['es', 'uk']);
    Market::where('code', 'hidden')->sole()->update(['is_active' => false]);
    $uk = Market::where('code', 'uk')->sole();
    $uk->languages()->sync([Language::where('code', 'en')->sole()->id]);
    $data = visitorGeoData($this->get('/es/es')->assertOk()->getContent());
    expect(array_column($data['markets'], 'code'))->not->toContain('hidden');
    $ukChoice = collect($data['markets'])->firstWhere('code', 'uk');
    expect(array_column($ukChoice['languages'], 'code'))->toBe(['en']);
    foreach ($ukChoice['languages'] as $language) {
        $this->get($language['url'])->assertOk();
    }
    Language::where('code', 'en')->sole()->update(['is_active' => false]);
    $home = app(LocalizationServiceInterface::class)->url('/', Market::where('code', 'es')->sole(), 'es');
    $updated = visitorGeoData($this->get($home)->assertOk()->getContent());
    expect(array_column($updated['markets'], 'code'))->toBe(['es']);
    expect(array_column($updated['markets'][0]['languages'], 'code'))->toBe(['es']);
});

test('GEO preference never forwards an untrusted external destination', function (): void {
    CmsFixtures::localization(['es', 'en'], ['es']);
    CmsFixtures::home(['es', 'en'], ['es']);
    $response = $this->post('/geo/set-preference', ['market' => 'es', 'locale' => 'en', 'target_url' => 'https://untrusted.example/path']);
    $response->assertRedirect('/en')->assertPlainCookie('cms_geo_preference', 'es_en');
    $this->get($response->headers->get('Location'))->assertOk();
});

test('GEO preference rejects inactive and unassigned market language pairs', function (): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'uk']);
    $uk = Market::where('code', 'uk')->sole();
    $uk->languages()->sync([Language::where('code', 'en')->sole()->id]);
    $this->postJson('/geo/set-preference', ['market' => 'uk', 'locale' => 'es'])->assertUnprocessable()->assertJsonValidationErrors('locale')->assertCookieMissing('cms_geo_preference');
    $uk->update(['is_active' => false]);
    $this->postJson('/geo/set-preference', ['market' => 'uk', 'locale' => 'en'])->assertUnprocessable()->assertJsonValidationErrors('market');
    Language::where('code', 'en')->sole()->update(['is_active' => false]);
    $this->postJson('/geo/set-preference', ['market' => 'es', 'locale' => 'en'])->assertUnprocessable()->assertJsonValidationErrors('locale');
});
