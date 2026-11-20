<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Database\Seeders\CmsFoundationSeeder;
use DbCms\Core\Models\Market;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    $this->seed(CmsFoundationSeeder::class);
});

test('/sitemap.xml returns a valid segmented sitemap index', function (): void {
    $response = $this->get('/sitemap.xml');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<sitemapindex', false)
        ->assertSee('sitemaps/pages.xml', false);
});

test('/sitemaps/{segment}.xml returns a valid child sitemap', function (): void {
    $response = $this->get('/sitemaps/pages.xml');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<urlset', false);
});

test('unknown and out-of-range sitemap segments remain unavailable', function (): void {
    $this->get('/sitemaps/unknown.xml')->assertNotFound();
    $this->get('/sitemaps/pages-999.xml')->assertNotFound();
});

test('/{market}/{locale}/sitemap.xml returns valid regional XML sitemaps', function (): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'eu']);
    foreach (['es' => 'pagina-espana', 'eu' => 'pagina-europa'] as $market => $slug) {
        $page = Page::create(['template' => 'default', 'layout' => 'default', 'is_published' => true]);
        $page->translations()->create(['locale' => 'es', 'title' => $slug, 'slug' => $slug]);
        $page->markets()->attach(Market::where('code', $market)->firstOrFail());
    }
    $this->get('/es/es/sitemap.xml')->assertStatus(200)
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<urlset', false)
        ->assertSee('/es/es/pagina-espana', false)
        ->assertDontSee('pagina-europa', false);
});

test('api endpoint returns valid Schema.org site graph', function (): void {
    $response = $this->getJson('/api/v1/geo/metadata');

    $response->assertStatus(200)
        ->assertJsonPath('@context', 'https://schema.org')
        ->assertJsonStructure([
            '@context',
            '@graph' => [
                '*' => ['@type', '@id'],
            ],
        ]);
});
