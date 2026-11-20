<?php

declare(strict_types=1);

use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'eu']);
});

test('/llms.txt endpoint returns text/plain index with domain knowledge', function (): void {
    $response = $this->get('/llms.txt');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('Regional Knowledge Bases')
        ->assertSee('/llms-full.txt');
});

test('/llms-full.txt endpoint returns complete semantic corpus', function (): void {
    $response = $this->get('/llms-full.txt');

    $response->assertStatus(200)
        ->assertSee('Full Semantic Corpus');
});

test('/{market}/{locale}/llms.txt returns localized plain text knowledge base for AI engines', function (): void {
    $response = $this->get('/es/es/llms.txt');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('/es/es')
        ->assertSee('/es/es/llms-full.txt')
        ->assertSee('/es/es/sitemap.xml');
});

test('/{market}/{locale}/llms-full.txt returns localized full semantic corpus for AI engines', function (): void {
    $response = $this->get('/es/es/llms-full.txt');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertSee('Full Semantic Corpus');
});

test('localized GEO endpoints reject an active language outside its market', function (): void {
    $market = Market::where('code', 'es')->firstOrFail();
    $english = Language::where('code', 'en')->firstOrFail();
    $market->languages()->detach($english->getKey());

    expect($english->is_active)->toBeTrue();
    $this->get('/es/en/llms.txt')->assertNotFound();
    $this->get('/es/en/llms-full.txt')->assertNotFound();
});
