<?php

declare(strict_types=1);

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Services\Seo\JsonLdSchemaService;
use DbCms\Core\Services\Seo\SeoMetaService;

test('optional SEO images are omitted when their unconfigured fallback files are missing', function (): void {
    app(GeneralSettingsServiceInterface::class)->set('general.site_logo', '');
    app(GeneralSettingsServiceInterface::class)->set('general.og_image', '');
    config(['seo.defaults.og_image' => url('/images/og-default.png')]);
    expect(is_file(public_path('images/logo.png')))->toBeFalse();
    expect(is_file(public_path('images/og-default.png')))->toBeFalse();
    $graph = app(JsonLdSchemaService::class)->generateSiteGraph();
    expect($graph['@graph'][0])->not->toHaveKey('logo');
    $head = view('themes::components.seo-head', ['seoMeta' => app(SeoMetaService::class)->forWebPage('A public title', 'A real description', url('/'))])->render();
    expect($head)->not->toContain('property="og:image"')->not->toContain('name="twitter:image"')->toContain('name="twitter:card" content="summary"');
});

test('configured SEO images preserve their destination and relative media become absolute URLs', function (): void {
    app(GeneralSettingsServiceInterface::class)->set('general.site_logo', '/uploads/public-identity.webp');
    app(GeneralSettingsServiceInterface::class)->set('general.og_image', '/uploads/public-social.webp');
    $graph = app(JsonLdSchemaService::class)->generateSiteGraph();
    expect($graph['@graph'][0]['logo']['url'])->toBe(url('/uploads/public-identity.webp'));
    $head = view('themes::components.seo-head', ['seoMeta' => app(SeoMetaService::class)->forWebPage('Public title', 'Description', url('/'))])->render();
    expect($head)->toContain('property="og:image" content="'.url('/uploads/public-social.webp').'"');
    $external = 'https://cdn.example.test/identity.webp';
    $head = view('themes::components.seo-head', ['seoMeta' => app(SeoMetaService::class)->forWebPage('Public title', 'Description', url('/'), image: $external)])->render();
    expect($head)->toContain('property="og:image" content="'.$external.'"')->toContain('name="twitter:image" content="'.$external.'"')->toContain('name="twitter:card" content="summary_large_image"');
});

test('JsonLdSchemaService generates valid site and organization graph', function (): void {
    $service = new JsonLdSchemaService;
    $graph = $service->generateSiteGraph();

    expect($graph['@context'])->toBe('https://schema.org')
        ->and($graph['@graph'])->toBeArray()
        ->and(count($graph['@graph']))->toBeGreaterThanOrEqual(2);
});
