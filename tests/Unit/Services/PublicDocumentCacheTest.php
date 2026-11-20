<?php

declare(strict_types=1);

use DbCms\Core\Services\Cms\PageCacheService;
use DbCms\Core\Services\Seo\PublicDocumentCache;
use Illuminate\Support\Facades\Cache;

test('public documents reuse cached output until content invalidates the index', function (): void {
    $cache = app(PublicDocumentCache::class);
    $calls = 0;
    $generate = function () use (&$calls): string {
        return 'document-'.++$calls;
    };

    expect($cache->remember('test:document', $generate))->toBe('document-1')
        ->and($cache->remember('test:document', $generate))->toBe('document-1');
    app(PageCacheService::class)->invalidateIndexes();
    expect($cache->remember('test:document', $generate))->toBe('document-2');
});

test('public documents still render once when cache reads and writes fail', function (): void {
    Cache::shouldReceive('get')->andThrow(new RuntimeException('Cache unavailable'));
    Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('Cache unavailable'));
    $calls = 0;

    expect(app(PublicDocumentCache::class)->remember('test:outage', function () use (&$calls): string {
        $calls++;

        return 'available document';
    }))->toBe('available document')->and($calls)->toBe(1);
});

test('document generator errors propagate without repeating domain work', function (): void {
    $calls = 0;
    $failure = new DomainException('Generation failed');

    try {
        app(PublicDocumentCache::class)->remember('test:failed-generator', function () use (&$calls, $failure): string {
            $calls++;
            throw $failure;
        });
        $this->fail('The generator failure must propagate.');
    } catch (DomainException $caught) {
        expect($caught)->toBe($failure)->and($calls)->toBe(1);
    }
});
