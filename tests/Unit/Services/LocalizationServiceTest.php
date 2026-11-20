<?php

declare(strict_types=1);

use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Services\Cms\LocalizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new LocalizationService;

    $this->spanish = Language::firstOrCreate(['code' => 'es'], [
        'name' => 'Spanish',
        'native_name' => 'Español',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->english = Language::firstOrCreate(['code' => 'en'], [
        'name' => 'English',
        'native_name' => 'English',
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->marketEs = Market::firstOrCreate(['code' => 'es'], [
        'name' => 'Spain',
        'currency' => 'EUR',
        'default_locale' => 'es',
        'is_default' => true,
        'is_active' => true,
    ]);

    $this->marketUs = Market::firstOrCreate(['code' => 'us'], [
        'code' => 'us',
        'name' => 'United States',
        'currency' => 'USD',
        'default_locale' => 'en',
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->marketEs->languages()->sync([$this->spanish->id => ['is_default' => true]]);
    $this->marketUs->languages()->sync([$this->english->id => ['is_default' => true]]);
});

test('it resolves market from request route parameter', function (): void {
    $request = Request::create('/us/en/blog');
    $request->setRouteResolver(function () {
        $route = new Route('GET', '{market}/{locale}/blog', []);
        $route->bind(request());
        $route->setParameter('market', 'us');
        $route->setParameter('locale', 'en');

        return $route;
    });

    $market = $this->service->resolveMarket($request);

    expect($market)->not->toBeNull()
        ->and($market->code)->toBe('us');
});

test('it resolves default market when no market parameter is supplied', function (): void {
    $request = Request::create('/');

    $market = $this->service->resolveMarket($request);

    expect($market->code)->toBe('es')
        ->and($market->is_default)->toBeTrue();
});

test('it resolves locale for a specific market', function (): void {
    $request = Request::create('/us/en/blog');
    $request->setRouteResolver(function () use ($request) {
        $route = new Route('GET', '{market}/{locale}/blog', []);
        $route->bind($request);
        $route->setParameter('locale', 'en');

        return $route;
    });

    $language = $this->service->resolveLocale($request, $this->marketUs);

    expect($language->code)->toBe('en');
});

test('it builds correct multi-directional hreflang cluster', function (): void {
    $slugs = [
        'es' => 'guia-arquitectura-agentes',
        'en' => 'guide-agentic-architecture',
    ];

    $hreflangs = $this->service->buildHreflangCluster('blog/{slug}', $slugs);

    expect($hreflangs)->toHaveKey('es-ES')
        ->and($hreflangs)->toHaveKey('en-US')
        ->and($hreflangs)->toHaveKey('x-default')
        ->and($hreflangs['es-ES'])->toContain('/blog/guia-arquitectura-agentes')
        ->and($hreflangs['en-US'])->toContain('/blog/guide-agentic-architecture');
});
