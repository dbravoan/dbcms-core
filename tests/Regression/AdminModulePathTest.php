<?php

declare(strict_types=1);

namespace Tests\Regression;

use DbCms\Core\Cms;
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
use PHPUnit\Framework\TestCase;

/** Exercises the actual route files and module registration without booting a DB. */
final class AdminModulePathTest extends TestCase
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
        $this->isolated = new Application(PACKAGE_ROOT.'/.build/app');
        $this->isolated->instance('config', new Repository(['cms' => ['admin_path' => 'control']]));
        $this->isolated->instance('translator', new Translator(new ArrayLoader, 'en'));
        $this->router = new Router(new Dispatcher($this->isolated), $this->isolated);
        $this->isolated->instance('router', $this->router);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->isolated);
        require Cms::path('routes/web.php');
        $this->router->getRoutes()->refreshNameLookups();
        $this->isolated->instance('url', new UrlGenerator($this->router->getRoutes(), Request::create('http://localhost')));
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacade);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_named_routes_generate_custom_prefix_urls_for_async_and_navigation_actions(): void
    {
        foreach ([
            ['admin.notifications.read', ['id' => 3], '/control/notifications/3/read'],
            ['admin.modules.impact', ['identifier' => 'blog'], '/control/modules/blog/impact'],
            ['admin.media.update', ['id' => 4], '/control/media/4'],
            ['admin.media.destroy', ['filename' => 'photo.webp'], '/control/media/photo.webp'],
        ] as [$name, $args, $path]) {
            self::assertSame('http://localhost'.$path, route($name, $args));
        }
    }
}
