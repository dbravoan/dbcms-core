<?php

declare(strict_types=1);
use DbCms\Core\Services\Cms\FrontViewResolver;
use DbCms\Core\Services\Cms\ThemeManagerService;

uses()->group('source-catalogue');

test('it resolves an existing theme view', function (): void {
    config(['cms.theme' => 'fixture-theme']);

    $resolver = app(FrontViewResolver::class);

    expect($resolver->theme())->toBe('fixture-theme')
        ->and($resolver->resolve('blog.index', 'blog::index'))->toBe('theme-fixture-theme::blog.index');
});

test('it falls back to the module view when the theme does not override it', function (): void {
    config(['cms.theme' => 'fixture-theme']);

    expect(app(FrontViewResolver::class)->resolve('blog.missing', 'blog::index'))->toBe('blog::index');
});

test('it falls back to the default theme when the configured one is unknown', function (): void {
    config(['cms.theme' => 'does-not-exist']);

    $first = array_key_first(app(ThemeManagerService::class)->getAvailableThemes());
    expect(app(FrontViewResolver::class)->layout())->toBe('theme-'.$first.'::layouts.app');
});

test('it resolves module block views through the registered theme namespace', function (): void {
    config(['cms.theme' => 'fixture-theme']);

    expect(app(FrontViewResolver::class)->resolve('shop.blocks.products', 'shop::blocks.products'))
        ->toBe('theme-fixture-theme::shop.blocks.products');
});

test('it returns null for a block view that exists nowhere', function (): void {
    config(['cms.theme' => 'fixture-theme']);

    expect(app(FrontViewResolver::class)->block('blocks.definitely-missing'))->toBeNull();
});
