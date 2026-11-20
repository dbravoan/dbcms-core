<?php

declare(strict_types=1);

use DbCms\Core\Capabilities\CapabilityRegistry;
use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\SearchDocumentIndex;
use DbCms\Core\Search\Models\SearchTerm;
use DbCms\Core\Search\SearchIndexer;
use Tests\Support\CmsFixtures;

test('core search serves published pages without installing optional modules or recording queries', function (): void {
    CmsFixtures::localization(['es']);
    config(['cms.page_cache.enabled' => false]);
    $page = Page::create(['template' => 'default', 'is_published' => true]);
    $page->translations()->create(['locale' => 'es', 'title' => 'Contenido esencial', 'slug' => 'contenido-esencial']);
    app(SearchIndexer::class)->indexAll();

    expect(CmsModule::query()->count())->toBe(0)
        ->and(app(CapabilityRegistry::class)->metadata('cms_search')['module'])->toBeNull();
    $this->get('/buscar')->assertOk()->assertSee('noindex', false);
    $this->get('/buscar/contenido-esencial')->assertOk()->assertSee('Contenido esencial');
    expect(SearchTerm::query()->count())->toBe(0);
});

test('search reindex preserves the other indexed languages', function (): void {
    CmsFixtures::localization();
    $page = CmsFixtures::home(['es', 'en']);
    app(SearchIndexer::class)->indexAll();

    $this->artisan('search:reindex', ['--locale' => 'es'])->assertSuccessful();

    expect(SearchDocumentIndex::where('entity_id', $page->id)->orderBy('locale')->pluck('locale')->all())
        ->toBe(['en', 'es']);
});
