<?php

declare(strict_types=1);

namespace Tests\Smoke;

use DomainException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Facade;
use Tests\Support\BootstrapCacheIsolation;
use Tests\TestCase;
use Throwable;

final class BootstrapCacheIsolationTest extends TestCase
{
    private ?BootstrapCacheIsolation $foreignCaches = null;

    /** @var array<string, string> */
    private array $foreignPaths = [];

    private ?string $privateDirectory = null;

    public function createApplication()
    {
        $this->foreignCaches = new BootstrapCacheIsolation;
        foreach (['APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE'] as $key) {
            $path = (string) getenv($key);
            $this->foreignPaths[$key] = $path;
            file_put_contents($path, '<?php throw new RuntimeException("Foreign cache was loaded");');
        }

        try {
            $app = parent::createApplication();
            $this->privateDirectory = dirname($app->getCachedConfigPath());

            return $app;
        } catch (Throwable $exception) {
            $this->foreignCaches->restore();
            throw $exception;
        }
    }

    public function test_boot_uses_declared_composition_and_cache_commands_only_touch_private_paths(): void
    {
        $this->assertSame([], config('cms.modules'));
        $this->assertFalse($this->app->configurationIsCached());
        $this->assertFalse($this->app->routesAreCached());
        $this->privateDirectory = dirname($this->app->getCachedConfigPath());

        foreach (['APP_CONFIG_CACHE' => $this->app->getCachedConfigPath(), 'APP_ROUTES_CACHE' => $this->app->getCachedRoutesPath(), 'APP_EVENTS_CACHE' => $this->app->getCachedEventsPath()] as $key => $path) {
            $this->assertNotSame($this->foreignPaths[$key], $path);
            file_put_contents($path, '<?php return [];');
        }
        foreach (['config:clear', 'route:clear', 'event:clear'] as $command) {
            $this->assertSame(0, $this->app->make(Kernel::class)->call($command));
        }
        foreach ($this->foreignPaths as $path) {
            $this->assertSame('<?php throw new RuntimeException("Foreign cache was loaded");', file_get_contents($path));
        }
    }

    public function test_failed_bootstrap_restores_the_running_tests_cache_environment(): void
    {
        $paths = [];
        foreach (array_keys($this->foreignPaths) as $key) {
            $paths[$key] = [getenv($key), $_ENV[$key], $_SERVER[$key]];
        }
        $probe = new class('unused') extends TestCase
        {
            public const TEST_MODULES = ['absent-cache-isolation-fixture'];
        };
        $failed = false;
        try {
            $probe->createApplication();
        } catch (DomainException) {
            $failed = true;
        } finally {
            Container::setInstance($this->app);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->app);
        }

        $this->assertTrue($failed, 'The deliberately missing module must fail bootstrap.');
        foreach ($paths as $key => $values) {
            $this->assertSame($values, [getenv($key), $_ENV[$key], $_SERVER[$key]]);
        }
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
            foreach ($this->foreignPaths as $key => $path) {
                $this->assertSame($path, getenv($key));
                $this->assertSame($path, $_ENV[$key]);
                $this->assertSame($path, $_SERVER[$key]);
            }
            if ($this->privateDirectory !== null) {
                $this->assertDirectoryDoesNotExist($this->privateDirectory);
            }
        } finally {
            $this->foreignCaches?->restore();
        }
    }
}
