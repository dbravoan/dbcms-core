<?php

declare(strict_types=1);

use DbCms\Core\Content\Pages\Models\Page;
use DbCms\Core\Contracts\Services\AdminNavigationServiceInterface;
use DbCms\Core\Contracts\Services\PublicContentDiscoveryInterface;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\Language;
use DbCms\Core\Models\Market;
use DbCms\Core\Models\Role;
use DbCms\Core\Models\User;
use DbCms\Core\Modules\ModuleManager;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->user->roles()->attach(Role::create(['slug' => 'admin', 'name' => 'Admin']));
    $language = Language::create(['code' => 'es', 'name' => 'Español', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    $this->market = Market::create(['code' => 'es', 'name' => 'España', 'currency' => 'EUR', 'default_locale' => 'es', 'is_default' => true, 'is_active' => true]);
    $this->market->languages()->attach($language, ['is_default' => true]);
    config(['cms.page_cache.enabled' => false]);
});

test('core pages and block packages remain reachable without optional module records', function (): void {
    expect(app(ModuleManager::class)->discoverModules())->not->toHaveKey('pages');
    expect(CmsModule::count())->toBe(0);
    $sections = app(AdminNavigationServiceInterface::class)->getNavigationSections($this->user);
    $routes = collect($sections)->flatMap(fn ($section) => $section->items)->pluck('route')->all();
    expect($routes)->toContain('admin.pages.index', 'admin.block-packages.index');
    $this->actingAs($this->user)->get(route('admin.dashboard'))->assertOk();
    $this->get('/admin/pages')->assertOk();
    $this->get('/admin/modules')->assertOk()->assertDontSee('/modules/pages/');
    expect(CmsModule::count())->toBe(0);
});

test('core public discovery includes only published local pages and no absent module segments', function (): void {
    foreach ([['public-page', true], ['private-page', false]] as [$slug, $published]) {
        $page = Page::withoutEvents(fn () => Page::create(['template' => 'default', 'is_published' => $published, 'published_at' => $published ? now() : null]));
        $page->translations()->create(['locale' => 'es', 'title' => $slug, 'slug' => $slug]);
        $page->markets()->attach($this->market);
    }
    expect(array_keys(app(PublicContentDiscoveryInterface::class)->schemas()))->toBe(['page']);
    $this->get('/sitemap.xml')->assertOk()->assertSee('/sitemaps/pages.xml')->assertDontSee('/sitemaps/posts.xml');
    $this->get('/sitemaps/posts.xml')->assertNotFound();
    $this->get('/es/es/sitemap.xml')->assertOk()->assertSee('public-page')->assertDontSee('private-page');
    $this->get('/es/es/llms-full.txt')->assertOk()->assertSee('public-page')->assertDontSee('private-page');
    $this->actingAs($this->user)->get('/admin/seo/audit')->assertOk()->assertSee('public-page');
});
