<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\Market;
use Tests\Support\CmsFixtures;
use Tests\Support\ThemePackageFixture;

beforeEach(function (): void {
    $this->withoutVite();
    config(['cms.page_cache.enabled' => false]);
});

function withCorePageFallbackTheme(Closure $assertions): void
{
    $packages = new ThemePackageFixture;
    $identifier = 'journey-page-fallback-'.strtolower(str()->random(8));
    try {
        $packages->front($identifier);
        config(['cms.theme' => $identifier]);
        $assertions($identifier);
    } finally {
        $packages->restore();
    }
}

function corePageFallbackDocument(string $html): DOMDocument
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

    return $document;
}

test('core page fallback follows the active layout navigation without advertising missing modules', function (bool $multilingual): void {
    $locales = $multilingual ? ['es', 'en'] : ['es'];
    CmsFixtures::localization($locales, ['es']);
    CmsFixtures::home($locales, ['es']);
    withCorePageFallbackTheme(function () use ($locales): void {
        $market = Market::where('code', 'es')->sole();
        foreach ($locales as $locale) {
            $url = app(LocalizationServiceInterface::class)->url('/', $market, $locale);
            $response = $this->get($url)->assertOk()->assertViewIs('pages::show');
            $document = corePageFallbackDocument($response->getContent());
            foreach ((new DOMXPath($document))->query('//header//a[@href] | //footer//a[@href]') as $anchor) {
                $this->get($anchor->getAttribute('href'))->assertSuccessful();
            }
        }
    });
})->with([false, true]);

test('core page fallback renders its content and unified SEO through the selected theme layout', function (bool $multilingual): void {
    $locales = $multilingual ? ['es', 'en'] : ['es'];
    CmsFixtures::localization($locales, ['es']);
    CmsFixtures::home($locales, ['es']);
    $page = Page::create(['is_published' => true]);
    foreach ($locales as $locale) {
        $page->translations()->create(['locale' => $locale, 'title' => 'BODY FALLBACK '.$locale, 'slug' => 'fallback-'.$locale, 'meta_title' => 'SEO FALLBACK '.$locale, 'meta_description' => 'DESCRIPTION FALLBACK '.$locale]);
    }
    withCorePageFallbackTheme(function (string $theme) use ($locales): void {
        $market = Market::where('code', 'es')->sole();
        foreach ($locales as $locale) {
            $url = app(LocalizationServiceInterface::class)->url('/fallback-'.$locale, $market, $locale);
            $response = $this->get($url)->assertOk()->assertViewIs('pages::show');
            $document = corePageFallbackDocument($response->getContent());
            $xpath = new DOMXPath($document);
            expect($document->documentElement->getAttribute('class'))->toContain('front-'.$theme);
            expect($xpath->query('//main//h1')->item(0)?->textContent)->toBe('BODY FALLBACK '.$locale);
            expect($xpath->query('//title')->item(0)?->textContent)->toContain('SEO FALLBACK '.$locale);
            expect($xpath->query('//meta[@name="description"]')->item(0)?->getAttribute('content'))->toBe('DESCRIPTION FALLBACK '.$locale);
            expect($xpath->query('//link[@rel="canonical"]')->item(0)?->getAttribute('href'))->toBe($url);
            $schemas = [];
            foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
                $schemas[] = json_decode($script->textContent, true, flags: JSON_THROW_ON_ERROR);
            }
            expect(json_encode($schemas, JSON_THROW_ON_ERROR))->toContain('WebPage')->toContain('SEO FALLBACK '.$locale);
        }
    });
})->with([false, true]);
