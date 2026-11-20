<?php

declare(strict_types=1);

use DbCms\Core\Models\Market;
use DbCms\Core\Models\Menu;
use DbCms\Core\Services\Cms\MenuService;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en', 'fr']);
    $menu = Menu::create(['slug' => 'main', 'name' => 'Main', 'location' => 'header', 'is_active' => true]);
    $item = $menu->items()->create(['type' => 'custom', 'url' => '/', 'order' => 0, 'is_active' => true]);
    foreach (['es' => 'Inicio', 'en' => 'Home', 'fr' => 'Accueil'] as $locale => $title) {
        $item->translations()->create(['locale' => $locale, 'title' => $title]);
    }
});

test('menu service resolves header navigation for active market and locale', function (): void {
    $service = app(MenuService::class);
    $market = Market::where('code', 'es')->firstOrFail();

    $menu = $service->getMenu('header', $market, 'es');

    expect($menu)->toBeArray();
    expect($menu)->not->toBeEmpty();
    expect($menu[0])->toHaveKeys(['id', 'title', 'url', 'target_blank', 'children']);
    expect($menu[0]['title'])->toBe('Inicio');
});

test('menu service provides translated titles for english and french', function (): void {
    $service = app(MenuService::class);
    $market = Market::where('code', 'es')->firstOrFail();

    $menuEn = $service->getMenu('header', $market, 'en');
    $menuFr = $service->getMenu('header', $market, 'fr');

    expect($menuEn[0]['title'])->toBe('Home');
    expect($menuFr[0]['title'])->toBe('Accueil');
});

test('front navigation renders localized menus three levels deep', function (): void {
    $menu = Menu::where('slug', 'main')->firstOrFail();
    $blog = $menu->items()->create(['type' => 'custom', 'url' => '#', 'order' => 1, 'is_active' => true]);
    $blog->translations()->create(['locale' => 'es', 'title' => 'Blog']);
    $articles = $menu->items()->create(['parent_id' => $blog->id, 'type' => 'custom', 'url' => '/blog', 'order' => 0, 'is_active' => true]);
    $articles->translations()->create(['locale' => 'es', 'title' => 'Todos los artículos']);
    $guides = $menu->items()->create(['parent_id' => $blog->id, 'type' => 'custom', 'url' => '#', 'order' => 1, 'is_active' => true]);
    $guides->translations()->create(['locale' => 'es', 'title' => 'Guías']);
    $feed = $menu->items()->create(['parent_id' => $guides->id, 'type' => 'custom', 'url' => '/feed.xml', 'order' => 0, 'is_active' => true]);
    $feed->translations()->create(['locale' => 'es', 'title' => 'RSS del blog']);

    $market = Market::where('code', 'es')->firstOrFail();
    $items = app(MenuService::class)->getMenu('header', $market, 'es');
    $html = view('themes::components.nav-items', ['items' => $items])->render();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $dropdown = $document->querySelector('nav > details');
    $nested = $dropdown?->querySelector('.cms-nav-submenu > details');

    expect($document->querySelector('nav > a')?->textContent)->toBe('Inicio')
        ->and($dropdown?->querySelector('summary')?->textContent)->toBe('Blog')
        ->and($dropdown?->querySelector('a')?->textContent)->toBe('Todos los artículos')
        ->and($nested?->querySelector('summary')?->textContent)->toBe('Guías')
        ->and($nested?->querySelector('a')?->textContent)->toBe('RSS del blog')
        ->and($nested?->querySelector('a')?->getAttribute('href'))->toEndWith('/feed.xml');
});
