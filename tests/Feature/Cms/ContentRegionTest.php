<?php

declare(strict_types=1);

use DbCms\Core\Services\Cms\ThemeManagerService;
use DbCms\Core\View\Components\CmsContentRegion;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
use Tests\Support\CmsFixtures;
use Tests\Support\ThemePackageFixture;

/** Optional disposable browser artifacts, written only to a caller-supplied directory. */
function writeContentRegionFixture(string $name, string $html): void
{
    $directory = getenv('DBCMS_REGION_FIXTURE_DIR');
    if (! is_string($directory) || $directory === '') {
        return;
    }
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    expect($html)->toMatch('/rel="stylesheet"[^>]+href="[^"]*\/build\/assets\/[^"]+\.css"/');
    file_put_contents($directory.'/'.$name.'.html', $html);
}

beforeEach(function (): void {
    if (getenv('DBCMS_REGION_FIXTURE_DIR')) {
        Vite::clearResolvedInstance();
        $this->withVite();
    }
    config(['cms.page_cache.enabled' => false]);
    CmsFixtures::localization(['es'], ['es']);
});

test('Blade content regions expose the active theme tracks and preserve caller attributes', function (): void {
    config(['cms.theme' => 'fixture-alternate']);
    $html = Blade::render('<x-cms::content-region as="section" class="example-region" aria-label="Contenido"><div data-cms-extent="viewport">Ancho completo</div></x-cms::content-region>');
    expect($html)->toContain('<section')->toContain('cms-content-region example-region')
        ->toContain('data-cms-content-region')->toContain('data-cms-region-theme="fixture-alternate"')
        ->toContain('aria-label="Contenido"')->toContain('data-cms-extent="viewport"');
    expect(Blade::render('<x-cms::content-region theme="fixture-theme" as="main">Contenido</x-cms::content-region>'))->toContain('<main')->toContain('data-cms-region-theme="fixture-theme"');
    expect(fn () => new CmsContentRegion('fixture-theme', 'script'))->toThrow(InvalidArgumentException::class);
});

test('every public Page theme leaves effective section roots directly in its content region', function (): void {
    $page = CmsFixtures::home();
    $sections = [
        ['id' => 'region-viewport', 'type' => 'accordion', 'appearance' => ['extent' => 'viewport'], 'items' => [['title' => 'Viewport', 'children' => [['id' => 'wide-heading', 'type' => 'heading', 'text' => 'Región de ancho completo']]]]],
        ['id' => 'region-theme', 'type' => 'accordion', 'appearance' => ['extent' => 'theme'], 'items' => [['title' => 'Theme', 'children' => [['id' => 'nested-viewport', 'type' => 'accordion', 'appearance' => ['extent' => 'viewport'], 'items' => [['title' => 'Nested', 'children' => [['id' => 'nested-heading', 'type' => 'heading', 'text' => 'Contenido anidado']]]]]]]]],
        ['id' => 'region-columns', 'type' => 'text', 'columns_layout' => '2', 'appearance' => ['extent' => 'viewport'], 'columns' => [
            ['id' => 'left-column', 'blocks' => [['id' => 'left-text', 'type' => 'rich_text', 'content' => 'Columna primera']]],
            ['id' => 'right-column', 'blocks' => [['id' => 'right-text', 'type' => 'rich_text', 'content' => 'Columna segunda']]],
        ]],
    ];
    $ids = [];
    foreach ($sections as $order => $payload) {
        $section = $page->sections()->create(['type' => $payload['type'], 'order' => $order, 'is_active' => true, 'status' => 'published']);
        $section->translations()->create(['locale' => 'es', 'content_json' => $payload]);
        $ids[] = $section->getKey();
    }
    foreach (['fixture-theme', 'fixture-alternate'] as $theme) {
        config(['cms.theme' => $theme]);
        app(ThemeManagerService::class)->syncActiveTheme();
        $response = $this->get('/es/es')->assertOk();
        $html = (string) $response->getContent();
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        foreach ($ids as $index => $id) {
            $root = $xpath->query('//*[@data-cms-section="'.$id.'"]')->item(0);
            expect($root)->toBeInstanceOf(DOMElement::class);
            expect($root->parentNode->attributes->getNamedItem('data-cms-content-region'))->not->toBeNull();
            expect($root->getAttribute('data-cms-extent'))->toBe($index === 1 ? 'theme' : 'viewport');
        }
        expect($xpath->query('//main')->length)->toBe(1);
        writeContentRegionFixture($theme.'-page', $html);

        // Exercise an ordinary module-shaped view in the real theme layout.
        $module = Blade::render("@extends('theme-{$theme}::layouts.app') @section('content')<article id=\"module-content\"><h1>Contenido de módulo</h1><p>Su ancho de tema debe conservarse.</p></article>@endsection", ['currentLocale' => 'es', 'seoMeta' => null]);
        writeContentRegionFixture($theme.'-module', $module);
        expect($module)->toContain('id="module-content"');
    }
});

test('the core Page fallback uses the same region for an independent theme without its own Page view', function (): void {
    $this->withoutVite();
    $page = CmsFixtures::home();
    $section = $page->sections()->create(['type' => 'heading', 'order' => 0, 'is_active' => true, 'status' => 'published']);
    $section->translations()->create(['locale' => 'es', 'content_json' => ['id' => 'fallback-viewport', 'type' => 'heading', 'text' => 'Página de tema independiente', 'appearance' => ['extent' => 'viewport']]]);
    $packages = new ThemePackageFixture;
    try {
        $packages->front('region-independent');
        config(['cms.theme' => 'region-independent']);
        app(ThemeManagerService::class)->syncActiveTheme();
        $response = $this->get('/es/es')->assertOk()->assertSee('data-cms-region-theme="region-independent"', false);
        $dom = new DOMDocument;
        @$dom->loadHTML((string) $response->getContent());
        $root = (new DOMXPath($dom))->query('//*[@data-cms-section="'.$section->id.'"]')->item(0);
        expect($root->parentNode->attributes->getNamedItem('data-cms-content-region'))->not->toBeNull();
        expect($root->getAttribute('data-cms-extent'))->toBe('viewport');
    } finally {
        $packages->restore();
    }
});
