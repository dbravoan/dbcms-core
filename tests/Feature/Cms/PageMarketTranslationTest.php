<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Search\PageSearchSource;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Menu;
use DbCms\Core\Services\Capabilities\EntityMutationService;
use DbCms\Core\Services\Cms\MenuService;
use DbCms\Core\Services\Geo\LlmsTxtGeneratorService;
use DbCms\Core\Services\Seo\SitemapService;
use Illuminate\Support\Facades\View;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'eu']);
    View::addNamespace('test-pages', PACKAGE_ROOT.'/tests/Fixtures/views');
    config(['cms.page_views' => ['example' => 'test-pages::code-page'], 'cms.page_cache.enabled' => false]);
    $this->withoutVite();
});

function regionalPageFixture(string $template = 'default'): Page
{
    return Page::withoutEvents(function () use ($template): Page {
        $page = Page::create(['template' => $template, 'is_published' => true, 'published_at' => now()]);
        $page->translations()->create(['locale' => 'es', 'title' => 'Global title', 'slug' => 'global-page', 'meta_description' => 'Global metadata']);
        $section = $page->sections()->create(['type' => 'rich_text', 'is_active' => true, 'status' => 'published']);
        $section->translations()->create(['locale' => 'es', 'content_json' => ['content' => 'Global section text']]);
        foreach (['eu', 'es'] as $code) {
            $marketId = Market::where('code', $code)->value('id');
            $page->translations()->create(['locale' => 'es', 'market_id' => $marketId, 'title' => $code.' regional title', 'slug' => $code.'-regional-page', 'meta_title' => $code.' SEO title', 'meta_description' => $code.' regional metadata']);
            $section->translations()->create(['locale' => 'es', 'market_id' => $marketId, 'content_json' => ['content' => $code.' regional section text']]);
        }
        $untranslated = $page->sections()->create(['type' => 'rich_text', 'is_active' => true, 'status' => 'published']);
        $untranslated->translations()->create(['locale' => 'en', 'content_json' => ['content' => 'English only private to this locale']]);
        $regionalOnly = $page->sections()->create(['type' => 'rich_text', 'is_active' => true, 'status' => 'published']);
        $regionalOnly->translations()->create(['locale' => 'es', 'market_id' => Market::where('code', 'eu')->value('id'), 'content_json' => ['content' => 'EU exclusive section']]);

        return $page->refresh();
    });
}

test('section and Blade pages resolve only the effective requested market and locale', function (string $template): void {
    regionalPageFixture($template);
    foreach (['es' => 'eu', 'eu' => 'es'] as $code => $other) {
        $response = $this->get('/'.$code.'/es/'.$code.'-regional-page')->assertOk()
            ->assertViewHas('translation', fn ($translation): bool => $translation->title === $code.' regional title')
            ->assertViewHas('seoMeta', fn ($seo): bool => $seo->title === $code.' SEO title' && $seo->description === $code.' regional metadata')
            ->assertViewHas('alternateUrls', fn ($urls): bool => str_ends_with($urls['es']['es'], '/es/es/es-regional-page') && str_ends_with($urls['eu']['es'], '/eu/es/eu-regional-page') && ! isset($urls['es']['en']));
        if ($template === 'default') {
            $response->assertSee($code.' regional section text')->assertDontSee($other.' regional section text')->assertDontSee('Global section text')->assertDontSee('English only private');
            $code === 'eu' ? $response->assertSee('EU exclusive section') : $response->assertDontSee('EU exclusive section');
        } else {
            $response->assertSee($code.' regional title')->assertDontSee('regional section text');
        }
        $this->get('/'.$code.'/es/'.$other.'-regional-page')->assertNotFound();
        $this->get('/'.$code.'/es/global-page')->assertNotFound();
        $this->get('/'.$code.'/en/'.$code.'-regional-page')->assertNotFound();
    }
})->with(['default', 'blade:example']);

test('missing and blank regional values use only the same locale global content', function (): void {
    $page = regionalPageFixture();
    $page->translations()->whereNotNull('market_id')->delete();
    $page->translations()->create(['locale' => 'es', 'market_id' => Market::where('code', 'es')->value('id'), 'title' => ' ', 'slug' => '', 'meta_description' => '']);
    $page->sections()->first()->translations()->whereNotNull('market_id')->delete();
    $page->sections()->first()->translations()->create(['locale' => 'es', 'market_id' => Market::where('code', 'es')->value('id'), 'content_json' => []]);
    $response = $this->get('/es/es/global-page')->assertOk()->assertSee('Global section text')
        ->assertViewHas('translation', fn ($translation): bool => $translation->title === 'Global title' && $translation->meta_description === 'Global metadata');
    $response->assertDontSee('English only private');
    $this->get('/eu/es/global-page')->assertOk()->assertSee('Global section text');
    $this->get('/es/en/global-page')->assertNotFound();
    $page->translations()->whereNull('market_id')->update(['title' => '']);
    $this->get('/es/es/global-page')->assertNotFound();
});

test('discovery search sitemap corpus and Page menu targets use the same regional translation', function (): void {
    $page = regionalPageFixture();
    $menu = Menu::create(['name' => 'Fixture', 'slug' => 'regional', 'location' => 'regional', 'is_active' => true]);
    $item = $menu->items()->create(['type' => 'page', 'target_id' => $page->id, 'is_active' => true]);
    $item->translations()->create(['locale' => 'es', 'title' => 'Regional page']);
    $documents = collect(iterator_to_array(app(PageSearchSource::class)->documentsFor($page->id)));
    expect($documents)->toHaveCount(2);
    foreach (['es' => 'eu', 'eu' => 'es'] as $code => $other) {
        $market = Market::where('code', $code)->sole();
        $document = $documents->firstWhere('market', $code);
        expect($document->title)->toBe($code.' regional title')
            ->and($document->url)->toEndWith('/'.$code.'/es/'.$code.'-regional-page')
            ->and($document->body)->toContain($code.' regional section text')->not->toContain($other.' regional section text');
        $sitemap = app(SitemapService::class)->generateSitemapXml($market, 'es');
        expect($sitemap)->toContain('/'.$code.'/es/'.$code.'-regional-page')->not->toContain($other.'-regional-page')->not->toContain('global-page');
        $corpus = app(LlmsTxtGeneratorService::class)->generateLlmsFullTxt($market, 'es');
        expect($corpus)->toContain($code.' regional title')->toContain($code.' regional section text')->not->toContain($other.' regional title')->not->toContain($other.' regional section text')->not->toContain('English only private');
        $links = app(MenuService::class)->getMenu('regional', $market, 'es');
        expect($links[0]['url'])->toEndWith('/'.$code.'/es/'.$code.'-regional-page')
            ->and(app(MenuService::class)->getMenu('regional', $market, 'en'))->toBe([]);
    }
    $page->translations()->whereNull('market_id')->delete();
    $page->translations()->where('market_id', Market::where('code', 'es')->value('id'))->delete();
    $market = Market::where('code', 'es')->sole();
    expect(app(PublicContentDiscoveryInterface::class)->query('page', $market, 'es')->whereKey($page->id)->exists())->toBeFalse();
    $this->get('/es/es/eu-regional-page')->assertNotFound();
});

test('editing the global translation leaves the market translation intact', function (): void {
    $page = regionalPageFixture();
    $page->translations()->whereNull('market_id')->delete();
    $regional = $page->translations()->whereNotNull('market_id')->get()->pluck('title', 'market_id')->all();
    app(EntityMutationService::class)->update('page', $page->id, [
        'translations' => ['es' => ['title' => 'Nueva traducción global', 'slug' => 'nueva-global']],
    ]);

    expect($page->translations()->whereNull('market_id')->sole()->title)->toBe('Nueva traducción global')
        ->and($page->translations()->whereNotNull('market_id')->get()->pluck('title', 'market_id')->all())->toBe($regional);
});

test('explicit slugs receive a distinct URL at creation and on changes', function (): void {
    $writer = app(EntityMutationService::class);
    $first = $writer->create('page', ['translations' => ['es' => ['title' => 'Primera', 'slug' => 'oferta']]]);
    $second = $writer->create('page', ['translations' => ['es' => ['title' => 'Segunda', 'slug' => 'oferta']]]);
    $third = $writer->create('page', ['translations' => ['es' => ['title' => 'Tercera']]]);
    $writer->update('page', $third['id'], ['translations' => ['es' => ['slug' => 'oferta']]]);

    expect(Page::findOrFail($first['id'])->translations()->sole()->slug)->toBe('oferta')
        ->and(Page::findOrFail($second['id'])->translations()->sole()->slug)->toBe('oferta-2')
        ->and(Page::findOrFail($third['id'])->translations()->sole()->slug)->toBe('oferta-3');
});

test('a single market accepts a locale tag for pages and SEO routes', function (): void {
    Market::where('code', 'eu')->update(['is_active' => false]);
    CmsFixtures::localization(['es', 'zh-CN']);
    $page = CmsFixtures::home(['es', 'zh-CN']);
    $page->translations()->where('locale', 'zh-CN')->update(['title' => '中文版', 'slug' => 'localized']);

    $this->get('/zh-CN/localized')->assertOk()->assertSee('中文版');
    $this->get('/zh-CN/sitemap.xml')->assertOk();
    $this->get('/zh-CN/llms.txt')->assertOk();
});
