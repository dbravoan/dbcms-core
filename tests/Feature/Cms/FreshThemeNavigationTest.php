<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\LocalizationServiceInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Menu;
use Tests\Support\CmsFixtures;
use Tests\Support\ThemePackageFixture;

beforeEach(function (): void {
    $this->withoutVite();
    config(['cms.page_cache.enabled' => false]);
    // Browser output must represent a public production installation, with no
    // local developer tools link and no optional module composition.
    $this->app->instance('env', 'production');
});

/** @return list<string> */
function publicThemeNavigationLinks(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $links = [];
    foreach ((new DOMXPath($document))->query('//footer//a[@href] | //header//a[@href]') as $anchor) {
        $url = $anchor->getAttribute('href');
        if ($url !== '' && ! str_starts_with($url, '#')) {
            $links[] = $url;
        }
    }

    return array_values(array_unique($links));
}

test('fresh core themes advertise only working public navigation without invented business or legal pages', function (string $theme, string $profile): void {
    $locales = $profile === 'single-language' ? ['es'] : ['es', 'en', 'ca'];
    $markets = $profile === 'multi-market' ? ['es', 'uk'] : ['es'];
    CmsFixtures::localization($locales, $markets);
    CmsFixtures::home($locales, $markets);
    $generated = $theme === 'generated';
    $id = $generated ? 'journey-navigation-'.strtolower(str()->random(8)) : $theme;
    $packages = new ThemePackageFixture;
    try {
        if ($generated) {
            $packages->front($id);
        }
        config(['cms.theme' => $id]);
        $market = Market::where('code', $markets[0])->sole();
        foreach ($locales as $locale) {
            $home = app(LocalizationServiceInterface::class)->url('/', $market, $locale);
            $response = $this->get($home)->assertOk();
            $links = publicThemeNavigationLinks($response->getContent());
            expect($links)->toContain($home);
            foreach ($links as $link) {
                $this->get($link)->assertSuccessful();
            }
            $document = new DOMDocument;
            @$document->loadHTML($response->getContent());
            $cookieAction = (new DOMXPath($document))->query('//footer//button[@data-dispatch="open-cookie-settings"]')->item(0);
            expect($cookieAction)->toBeInstanceOf(DOMElement::class);
            if ($locale === 'ca') {
                expect($response->getContent())->toContain('Gestió de continguts per a diversos mercats i idiomes.');
            }
        }
    } finally {
        $packages->restore();
    }
})->with(['fixture-theme', 'fixture-alternate', 'generated'])->with(['single-language', 'multi-language', 'multi-market']);

test('configured footer menus take precedence over the safe root fallback', function (): void {
    CmsFixtures::localization(['es'], ['es']);
    CmsFixtures::home(['es'], ['es']);
    $page = Page::create(['is_published' => true]);
    $page->translations()->create(['locale' => 'es', 'title' => 'Published information', 'slug' => 'published-information']);
    foreach (['footer_quick', 'footer_legal'] as $location) {
        $menu = Menu::create(['name' => $location, 'slug' => $location, 'location' => $location, 'is_active' => true]);
        $item = $menu->items()->create(['type' => 'page', 'url' => 'published-information', 'is_active' => true, 'order' => 0]);
        $item->translations()->create(['locale' => 'es', 'title' => 'Configured '.$location]);
    }
    config(['cms.theme' => 'fixture-theme']);
    $html = $this->get('/')->assertOk()->assertSee('Configured footer_quick')->assertSee('Configured footer_legal')->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $footer = (new DOMXPath($document))->query('//footer')->item(0);
    expect($footer->textContent)->not->toContain('Inicio');
    foreach (publicThemeNavigationLinks($html) as $link) {
        $this->get($link)->assertSuccessful();
    }
});
