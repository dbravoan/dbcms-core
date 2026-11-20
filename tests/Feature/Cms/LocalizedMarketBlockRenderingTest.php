<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Content\Pages\Models\PageSection;
use DbCms\Core\Content\Pages\Models\PageTranslation;
use DbCms\Core\Content\Pages\Models\SectionTranslation;
use DbCms\Core\Models\Market;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en', 'ca']);
    $this->marketEs = Market::where('code', 'es')->firstOrFail();
});

test('frontoffice resolves authentic catalan content on localized market route', function (): void {
    $page = Page::create([
        'template' => 'default',
        'layout' => 'app',
        'is_published' => true,
        'published_at' => now(),
    ]);
    $page->markets()->sync([$this->marketEs->id]);

    PageTranslation::create([
        'page_id' => $page->id,
        'locale' => 'es',
        'title' => 'Plataforma CMS',
        'slug' => 'home',
    ]);

    PageTranslation::create([
        'page_id' => $page->id,
        'locale' => 'ca',
        'title' => 'Plataforma CMS Català',
        'slug' => 'home',
    ]);

    $hero = PageSection::create([
        'page_id' => $page->id,
        'type' => 'rich_text',
        'order' => 1,
        'is_active' => true,
    ]);

    SectionTranslation::create([
        'page_section_id' => $hero->id,
        'locale' => 'es',
        'content_json' => [
            'title' => 'Titular en Castellano',
            'content' => 'Subtítulo en Castellano',
        ],
    ]);

    SectionTranslation::create([
        'page_section_id' => $hero->id,
        'locale' => 'ca',
        'content_json' => [
            'title' => 'Titular en Català Autèntic',
            'content' => 'Subtítol en Català Autèntic',
        ],
    ]);

    // Test Catalan endpoint
    $responseCa = $this->get('/es/ca');
    $responseCa->assertStatus(200)
        ->assertSee('Titular en Català Autèntic')
        ->assertSee('Subtítol en Català Autèntic')
        ->assertDontSee('Titular en Castellano');

    // Test Spanish endpoint
    $responseEs = $this->get('/es/es');
    $responseEs->assertStatus(200)
        ->assertSee('Titular en Castellano')
        ->assertSee('Subtítulo en Castellano')
        ->assertDontSee('Titular en Català Autèntic');
});
