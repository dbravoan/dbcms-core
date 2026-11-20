<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Models\Market;
use Tests\Support\CmsFixtures;

beforeEach(function (): void {
    CmsFixtures::localization(['es'], ['es']);
    CmsFixtures::identities();
});

test('a disabled module prefix does not shadow a page with the same slug', function (): void {
    $page = Page::query()->create(['template' => 'default', 'is_published' => true, 'published_at' => now()]);
    $page->translations()->create(['locale' => 'es', 'title' => 'Blog page', 'slug' => 'blog']);
    $page->markets()->sync([Market::query()->where('code', 'es')->sole()->getKey()]);

    $this->get('/es/blog')->assertOk()->assertSee('Blog page');
});
