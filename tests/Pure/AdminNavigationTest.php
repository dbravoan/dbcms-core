<?php

declare(strict_types=1);

namespace Tests\Pure;

use DbCms\Core\DTOs\Admin\AdminNavigationItem;
use DbCms\Core\Models\User;
use DbCms\Core\Modules\ModuleInterface;
use DbCms\Core\Modules\ModuleManager;
use DbCms\Core\Services\Admin\AdminNavigationService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Mockery;
use PHPUnit\Framework\TestCase;

final class AdminNavigationTest extends TestCase
{
    private Container $previousContainer;

    private ?Container $previousFacade;

    private Application $isolated;

    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->isolated = new Application(dirname(__DIR__, 3));
        $this->isolated->instance('config', new Repository);
        $this->isolated->instance('translator', new Translator(new ArrayLoader, 'en'));
        $this->router = new Router(new Dispatcher($this->isolated), $this->isolated);
        $this->isolated->instance('router', $this->router);
        foreach (['posts', 'categories'] as $entity) {
            $this->router->get('/blog/'.$entity, fn () => '')->name('admin.blog.'.$entity.'.index');
            $this->router->get('/blog/'.$entity.'/{id}/edit', fn () => '')->name('admin.blog.'.$entity.'.edit');
        }
        $this->router->get('/modules/{identifier}/settings', fn () => '')->name('admin.modules.settings');
        $this->router->getRoutes()->refreshNameLookups();
        $this->request('/blog/categories/7/edit');
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->isolated);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacade);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_groups_have_no_destination_and_follow_the_active_descendant_on_edit_routes(): void
    {
        $group = $this->navigation($this->group())[0];
        self::assertNull($group->route);
        self::assertNull($group->getUrl());
        self::assertTrue($group->active);
        self::assertFalse($group->children[0]->active);
        self::assertTrue($group->children[1]->active);
        self::assertSame('admin.blog.posts.create', $group->children[0]->createRoute);
        self::assertSame('12', $group->children[0]->badgeText);
        self::assertSame('http://localhost/blog/categories', $group->children[1]->getUrl());

        $translated = $this->group();
        $translated[0]['title'] = 'Otra traducción';
        $translated[0]['children'][0]['title'] = 'Artículos';
        self::assertSame($group->id, $this->navigation($translated)[0]->id);
        self::assertSame($group->children[0]->id, $this->navigation($translated)[0]->children[0]->id);
    }

    public function test_permissions_and_unresolved_routes_remove_children_and_collapse_single_destinations(): void
    {
        $items = $this->group();
        $items[0]['children'][1]['permission'] = 'restricted';
        $items[0]['children'][] = ['title' => 'Missing', 'route' => 'missing.index'];
        $single = $this->navigation($items)[0];
        self::assertSame('admin.blog.posts.index', $single->route);
        self::assertSame([], $single->children);
        $items[0]['children'][0]['permission'] = 'restricted';
        self::assertSame([], $this->navigation($items));
    }

    public function test_module_settings_parameters_select_only_the_current_destination(): void
    {
        $this->request('/modules/maintenance/settings');
        $items = [];
        foreach (['maintenance', 'mcp'] as $identifier) {
            $items[] = ['title' => $identifier, 'route' => 'admin.modules.settings', 'params' => ['identifier' => $identifier]];
        }
        $navigation = $this->navigation($items);
        self::assertTrue($navigation[0]->active);
        self::assertFalse($navigation[1]->active);
        self::assertNotSame($navigation[0]->id, $navigation[1]->id);
        self::assertSame('http://localhost/modules/mcp/settings', $navigation[1]->getUrl());
        self::assertSame([], $this->navigation([['title' => 'Missing argument', 'route' => 'admin.modules.settings']]));
    }

    public function test_disabled_modules_contribute_no_navigation(): void
    {
        self::assertSame([], $this->navigation([], false));
    }

    private function request(string $path): void
    {
        $request = Request::create('http://localhost'.$path);
        $route = $this->router->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $this->isolated->instance('request', $request);
        $this->isolated->instance('url', new UrlGenerator($this->router->getRoutes(), $request));
    }

    /** @return array<int, array<string, mixed>> */
    private function group(): array
    {
        return [[
            'id' => 'blog', 'title' => 'Blog',
            'children' => [
                ['title' => 'Posts', 'route' => 'admin.blog.posts.index', 'createRoute' => 'admin.blog.posts.create', 'badgeText' => '12'],
                ['title' => 'Categories', 'route' => 'admin.blog.categories.index'],
            ],
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, AdminNavigationItem>
     */
    private function navigation(array $items, bool $enabled = true): array
    {
        $module = Mockery::mock(ModuleInterface::class);
        $module->shouldReceive('getAdminNavigation')->andReturn($items);
        $manager = Mockery::mock(ModuleManager::class);
        $manager->shouldReceive('getActiveModules')->andReturn($enabled ? ['blog' => $module] : []);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isAdmin', 'hasRole')->andReturn(false);
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission): bool => $permission !== 'restricted');
        foreach ((new AdminNavigationService($manager))->getNavigationSections($user) as $section) {
            if ($section->title === 'admin.nav.sections.extensions') {
                return $section->items;
            }
        }

        return [];
    }
}
