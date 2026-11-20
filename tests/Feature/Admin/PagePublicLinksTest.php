<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\User;
use Illuminate\Testing\TestResponse;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::identities();
    config(['cms.page_cache.enabled' => false]);
});

/** @return list<string> */
function adminPageLiveLinks(TestResponse $response, bool $editing): array
{
    $response->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $selector = $editing
        ? '//header//a[contains(., "Ver página en vivo")]/@href'
        : '//a[@title="Ver en sitio"]/@href';

    return array_map(static fn (DOMNode $node): string => $node->nodeValue, iterator_to_array($xpath->query($selector)));
}

function adminLinkPage(string $slug, string $locale = 'es', bool $published = true): Page
{
    $page = Page::create(['template' => 'default', 'layout' => 'default', 'is_published' => $published]);
    $page->translations()->create(['locale' => $locale, 'title' => 'Link verification '.$slug, 'slug' => $slug]);

    return $page;
}

test('page administration links follow the real localized public route', function (array $locales, array $markets, string $slug, string $brand): void {
    CmsFixtures::localization($locales, $markets);
    app(AdminThemeServiceInterface::class)->setActiveTheme($brand);
    $page = adminLinkPage($slug);
    $page->markets()->attach(Market::where('code', $markets[0])->firstOrFail());
    $this->actingAs(User::firstOrFail());

    foreach ([false, true] as $editing) {
        $response = $this->get(route($editing ? 'admin.pages.edit' : 'admin.pages.index', $editing ? $page : []));
        $links = adminPageLiveLinks($response, $editing);
        expect($links)->toHaveCount(1);
        $this->get($links[0])->assertOk()->assertViewHas('page', fn (Page $visible): bool => $visible->is($page));
    }
})->with([
    'single market and language' => [['es'], ['es'], 'link-verification'],
    'single market and multiple languages' => [['es', 'en'], ['es'], 'link-verification'],
    'multiple markets and languages' => [['es', 'en'], ['es', 'uk'], 'link-verification'],
    'localized home' => [['es', 'en'], ['es', 'uk'], 'home'],
])->with(['dbcms', 'fixture-admin']);

test('live links select a translated context inside the assigned market', function (string $brand): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'uk']);
    app(AdminThemeServiceInterface::class)->setActiveTheme($brand);
    // The first translation is unavailable; the only active translation is English.
    $page = adminLinkPage('contenu-inactif', 'fr');
    $page->translations()->create(['locale' => 'en', 'title' => 'English assigned page', 'slug' => 'english-assigned']);
    $page->markets()->attach(Market::where('code', 'uk')->firstOrFail());
    $this->actingAs(User::firstOrFail());
    foreach ([false, true] as $editing) {
        $links = adminPageLiveLinks($this->get(route($editing ? 'admin.pages.edit' : 'admin.pages.index', $editing ? $page : [])), $editing);
        expect($links)->toHaveCount(1)->and($links[0])->toEndWith('/uk/en/english-assigned');
        $this->get($links[0])->assertOk()->assertViewHas('page', fn (Page $visible): bool => $visible->is($page));
    }
})->with(['dbcms', 'fixture-admin']);

test('unpublished and publicly unavailable pages do not advertise a live link', function (string $brand): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'uk']);
    app(AdminThemeServiceInterface::class)->setActiveTheme($brand);
    $draft = adminLinkPage('draft-page', published: false);
    $future = adminLinkPage('scheduled-page');
    $future->update(['published_at' => now()->addDay()]);
    $untranslated = adminLinkPage('inactive-translation', 'fr');
    $inactiveMarket = adminLinkPage('inactive-market');
    $inactiveMarket->markets()->attach(Market::create(['code' => 'fr', 'name' => 'France', 'currency' => 'EUR', 'default_locale' => 'es', 'is_active' => false]));
    $unsupportedMarket = adminLinkPage('unsupported-language', 'en');
    $spanish = Market::where('code', 'es')->firstOrFail();
    $spanish->languages()->sync([Language::where('code', 'es')->firstOrFail()->id]);
    $unsupportedMarket->markets()->attach($spanish);
    $this->actingAs(User::firstOrFail());
    expect(adminPageLiveLinks($this->get(route('admin.pages.index')), false))->toBe([]);
    foreach ([$draft, $future, $untranslated, $inactiveMarket, $unsupportedMarket] as $page) {
        expect(adminPageLiveLinks($this->get(route('admin.pages.edit', $page)), true))->toBe([]);
    }
})->with(['dbcms', 'fixture-admin']);

test('globally available pages choose an active translated public context', function (string $brand): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'uk']);
    app(AdminThemeServiceInterface::class)->setActiveTheme($brand);
    $page = adminLinkPage('global-english', 'en');
    $this->actingAs(User::firstOrFail());
    foreach ([false, true] as $editing) {
        $links = adminPageLiveLinks($this->get(route($editing ? 'admin.pages.edit' : 'admin.pages.index', $editing ? $page : [])), $editing);
        expect($links)->toHaveCount(1)->and($links[0])->toEndWith('/es/en/global-english');
        $this->get($links[0])->assertOk()->assertViewHas('page', fn (Page $visible): bool => $visible->is($page));
    }
})->with(['dbcms', 'fixture-admin']);
