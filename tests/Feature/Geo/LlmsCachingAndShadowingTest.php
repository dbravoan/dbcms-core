<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\GeoServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Cache::flush();
});

test('/llms.txt is cached server-side across requests', function (): void {
    $mock = Mockery::mock(GeoServiceInterface::class);
    $mock->shouldReceive('generateLlmsTxt')->once()->andReturn('CACHED-LLMS-BODY');
    $this->app->instance(GeoServiceInterface::class, $mock);

    $this->get('/llms.txt')->assertOk()->assertSee('CACHED-LLMS-BODY');
    // Second request must be served from cache → the generator runs only once.
    $this->get('/llms.txt')->assertOk()->assertSee('CACHED-LLMS-BODY');
});

test('a page with slug "llms" cannot shadow the /llms.txt endpoint', function (): void {
    CmsFixtures::localization();

    $page = Page::create([
        'template' => 'default',
        'is_published' => true,
    ]);
    $page->translations()->create([
        'locale' => 'es',
        'title' => 'LLMS Shadow',
        'slug' => 'llms',
    ]);

    // The endpoint must keep serving the generated plain-text knowledge base,
    // not the HTML page that shares the slug.
    $this->get('/llms.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('Regional Knowledge Bases');
});
