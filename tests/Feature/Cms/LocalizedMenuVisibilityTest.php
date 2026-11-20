<?php

declare(strict_types=1);

use DbCms\Core\Models\Menu;
use DbCms\Core\Services\Cms\MenuService;
use Tests\Support\CmsFixtures;

test('page menu entries without the requested locale stay absent at either nesting level', function (): void {
    CmsFixtures::localization();
    $menu = Menu::create(['slug' => 'localized', 'location' => 'header', 'name' => 'Localized navigation', 'is_active' => true]);
    $page = $menu->items()->create(['type' => 'page', 'url' => 'solo-es', 'is_active' => true, 'order' => 1]);
    $page->translations()->create(['locale' => 'es', 'title' => 'Solo español']);
    $parent = $menu->items()->create(['type' => 'custom_url', 'url' => '/', 'is_active' => true, 'order' => 2]);
    $parent->translations()->create(['locale' => 'en', 'title' => 'Home']);
    $child = $menu->items()->create(['parent_id' => $parent->id, 'type' => 'page', 'url' => 'otro-es', 'is_active' => true]);
    $child->translations()->create(['locale' => 'es', 'title' => 'Otra página']);

    $english = app(MenuService::class)->getMenu('header', locale: 'en');
    expect(array_column($english, 'title'))->toBe(['Home'])
        ->and($english[0]['children'])->toBe([]);
    $spanish = app(MenuService::class)->getMenu('header', locale: 'es');
    expect($spanish[0]['title'])->toBe('Solo español')
        ->and($spanish[1]['children'][0]['title'])->toBe('Otra página');
});
