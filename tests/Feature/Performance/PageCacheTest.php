<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Http\Middleware\CachePublicPage;
use DbCms\Core\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use DbCms\Core\Models\User;
use DbCms\Core\Services\Cms\PageCacheService;
use DbCms\Core\Services\Cms\PublicationBoundaryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::home();
    CmsFixtures::identities();
    config([
        'cms.theme' => 'fixture-theme',
        'cms.admin_theme' => 'dbcms',
        'cms.page_cache.enabled' => true,
    ]);
    app(PageCacheService::class)->invalidate();
});

it('serves the second anonymous hit from cache without a cache header miss', function (): void {
    $first = $this->get('/es/es/');
    $first->assertOk();
    $first->assertHeader(CachePublicPage::HIT_HEADER, 'MISS');

    $second = $this->get('/es/es/');
    $second->assertOk();
    $second->assertHeader(CachePublicPage::HIT_HEADER, 'HIT');
});

it('rehydrates conditional requests rather than reusing a stale nonce', function (): void {
    $this->get('/es/es/')->assertOk();
    $etag = $this->get('/es/es/')->headers->get('ETag');

    expect($etag)->not->toBeNull();

    $this->withHeaders(['If-None-Match' => (string) $etag])
        ->get('/es/es/')
        ->assertOk()->assertHeader('X-Page-Cache', 'HIT');
});

it('invalidates cached pages as soon as content changes', function (): void {
    $this->get('/es/es/')->assertOk();
    $this->get('/es/es/')->assertHeader(CachePublicPage::HIT_HEADER, 'HIT');

    // Editing content must drop the cache immediately.
    Page::query()->first()?->touch();

    $this->get('/es/es/')->assertHeader(CachePublicPage::HIT_HEADER, 'MISS');
    $this->get('/es/es/')->assertHeader(CachePublicPage::HIT_HEADER, 'HIT');
});

it('never caches for authenticated visitors (personalised admin bar)', function (): void {
    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $this->actingAs($admin, 'web')->get('/es/es/')->assertOk();
    $response = $this->actingAs($admin, 'web')->get('/es/es/');

    expect($response->headers->get(CachePublicPage::HIT_HEADER))->toBeNull();
});

it('bypasses the cache for preview and editor requests', function (): void {
    $this->get('/es/es/?preview=1')->assertOk();

    $response = $this->get('/es/es/?preview=1');
    expect($response->headers->get(CachePublicPage::HIT_HEADER))->toBeNull();
});

it('can be disabled entirely through configuration', function (): void {
    config(['cms.page_cache.enabled' => false]);

    $this->get('/es/es/')->assertOk();
    $response = $this->get('/es/es/');

    expect($response->headers->get(CachePublicPage::HIT_HEADER))->toBeNull();
});

it('prevents upstream caches storing hydrated visitor state', function (): void {
    $response = $this->get('/es/es/');
    $cacheControl = (string) $response->headers->get('Cache-Control');

    expect($cacheControl)->toContain('private')->toContain('no-store');
});

afterEach(function (): void {
    Cache::flush();
});

it('renders expiring captcha tokens live rather than putting them in the shared cache', function (): void {
    Route::get('/regression/page-cache/captcha/token', static fn () => response('<input name="_captcha_token" value="expiring-token">'))
        ->middleware(['web', ResolveMarketAndLocaleMiddleware::class, CachePublicPage::class]);
    $this->get('/regression/page-cache/captcha/token')->assertOk()->assertHeaderMissing(CachePublicPage::HIT_HEADER);
    $this->get('/regression/page-cache/captcha/token')->assertOk()->assertHeaderMissing(CachePublicPage::HIT_HEADER);
});

it('hydrates a fresh csrf token even when a public snapshot is shared', function (): void {
    $this->withSession(['_token' => 'visitor-token-first'])->get('/es/es/')->assertOk()->assertSee('visitor-token-first');
    $this->withSession(['_token' => 'visitor-token-second'])->get('/es/es/')
        ->assertOk()->assertHeader(CachePublicPage::HIT_HEADER, 'HIT')->assertSee('visitor-token-second')->assertDontSee('visitor-token-first');
});

it('does not cache a render that overlaps a dependency invalidation', function (): void {
    $rendered = 0;
    $cache = app(PageCacheService::class);
    Route::get('/regression/cache-during-update', static function () use (&$rendered, $cache) {
        $rendered++;
        if ($rendered === 1) {
            $cache->invalidateDependencies([$cache->typeDependencyKey('pages')]);
        }

        return response('<p>Render '.$rendered.'</p>');
    })->middleware(['web', ResolveMarketAndLocaleMiddleware::class, CachePublicPage::class]);

    $this->get('/regression/cache-during-update')->assertSee('Render 1')->assertHeaderMissing(CachePublicPage::HIT_HEADER);
    $this->get('/regression/cache-during-update')->assertSee('Render 2')->assertHeader(CachePublicPage::HIT_HEADER, 'MISS');
    $this->get('/regression/cache-during-update')->assertSee('Render 2')->assertHeader(CachePublicPage::HIT_HEADER, 'HIT');
});

it('reuses the publication boundary across requests and refreshes it at the deadline', function (): void {
    $this->freezeTime();
    Page::create(['is_published' => true, 'published_at' => now()->addSeconds(10)]);
    $boundary = app(PublicationBoundaryService::class);
    $first = $boundary->state();
    DB::enableQueryLog();
    try {
        expect($boundary->state())->toBe($first);
        expect(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'published_at')))->toBe([]);
        $this->travel(11)->seconds();
        expect($boundary->state()['fingerprint'])->not->toBe($first['fingerprint']);
    } finally {
        DB::disableQueryLog();
        $this->travelBack();
    }
});
