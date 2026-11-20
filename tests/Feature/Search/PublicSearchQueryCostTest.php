<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Search\SearchEngine;
use DbCms\Core\Search\SearchIndexer;
use Illuminate\Support\Facades\DB;
use Tests\Support\CmsFixtures;

test('public search resolves its market once per result set rather than once per hit', function (): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'us']);
    app(SearchIndexer::class)->batch(function (): void {
        foreach (range(1, 10) as $number) {
            $page = Page::create(['is_published' => true, 'template' => 'default']);
            $page->translations()->create(['locale' => 'es', 'title' => 'Costefrontera '.$number, 'slug' => 'costefrontera-'.$number]);
        }
    });
    DB::enableQueryLog();
    try {
        $result = app(SearchEngine::class)->search('costefrontera', 'es', 'page', limit: 20, market: 'es');
        $lookups = collect(DB::getQueryLog())->filter(static fn (array $entry): bool => str_contains($entry['query'], 'from `markets`') && str_contains($entry['query'], 'where `code` = ?'));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    expect($result->hits)->toHaveCount(10);
    expect($lookups->count())->toBeLessThanOrEqual(3);
    foreach ($result->hits as $hit) {
        expect($hit->url)->toContain('/es/es/costefrontera-');
    }
});
