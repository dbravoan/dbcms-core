<?php

declare(strict_types=1);

namespace DbCms\Core\Modules;

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Contracts\Services\ModuleSettingsServiceInterface;
use DbCms\Core\Http\Middleware\EnsureAdminAuthenticated;
use DbCms\Core\Http\Middleware\SetBackofficeLocale;
use DbCms\Core\Models\CmsModule;
use DbCms\Core\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

abstract class BaseModuleServiceProvider extends ServiceProvider implements ModuleInterface
{
    /**
     * Unique module identifier.
     */
    protected string $identifier;

    /**
     * Module name.
     */
    protected string $name;

    /**
     * Whether module is pageable.
     */
    protected bool $isPageable = false;

    public function getName(): string
    {
        return $this->name;
    }

    public function isPageable(): bool
    {
        return $this->isPageable;
    }

    public function registerPageableRoutes(): void
    {
        // Override in concrete module service providers
    }

    /**
     * Register paths to be checked for views with module theme cascading.
     *
     * @param  string|array<int, string>  $path
     * @param  string  $namespace
     */
    protected function loadViewsFrom($path, $namespace): void
    {
        parent::loadViewsFrom($path, $namespace);

        if ($this->app->bound(AdminThemeServiceInterface::class)) {
            $basePath = is_array($path) ? ($path[0] ?? '') : $path;
            if ($basePath !== '') {
                $this->app->make(AdminThemeServiceInterface::class)
                    ->registerModuleThemePaths($namespace, $basePath);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAdminNavigation(): array
    {
        return [];
    }

    /** @return array{views: array<string, string>, data: array<string, mixed>}|null */
    public function getAdminDashboard(User $user): ?array
    {
        return null;
    }

    /**
     * Dynamically registers an isolated authentication provider and guards for this module.
     *
     * @param  string  $providerKey  Identifier for the provider (e.g. 'shop_customers')
     * @param  class-string  $modelClass  Authenticatable model class
     * @param  string  $webGuard  Web session guard name (e.g. 'customer')
     * @param  string|null  $apiGuard  Optional API Passport guard name (e.g. 'customer-api')
     */
    protected function registerModuleAuth(
        string $providerKey,
        string $modelClass,
        string $webGuard,
        ?string $apiGuard = null
    ): void {
        Config::set("auth.providers.{$providerKey}", [
            'driver' => 'eloquent',
            'model' => $modelClass,
        ]);

        Config::set("auth.guards.{$webGuard}", [
            'driver' => 'session',
            'provider' => $providerKey,
        ]);

        if ($apiGuard !== null) {
            Config::set("auth.guards.{$apiGuard}", [
                'driver' => 'passport',
                'provider' => $providerKey,
            ]);
        }
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $file = (new \ReflectionClass($this))->getFileName();
        if ($file !== false) {
            $views = dirname($file).'/Resources/views';
            if (is_dir($views.'/admin')) {
                View::addLocation($views);
            }
        }
        if ($this->isPageable()) {
            // Do not register routes for a disabled module: a 404 route would
            // still win the matcher and hide a page with the same slug.
            $configured = in_array($this->identifier, (array) Config::get('cms.modules', []), true);
            $enabled = $configured;
            try {
                $manager = $this->app->make(ModuleManager::class);
                $record = CmsModule::query()->where('identifier', $this->identifier)->first();
                if ($record !== null) {
                    $enabled = $manager->isModuleEnabled($this->identifier);
                }
            } catch (Throwable) {
                // Installer / pre-migration boot: keep the configured selection.
            }

            if ($enabled) {
                Route::middleware("module.enabled:{$this->identifier}")->group(fn () => $this->registerPageableRoutes());
            }
        }

        $this->registerAdminRoutes();
    }

    /**
     * Automatically registers backoffice admin routes if Routes/admin.php exists in module.
     */
    protected function registerAdminRoutes(): void
    {
        $reflector = new \ReflectionClass($this);
        $fileName = $reflector->getFileName();
        if (! $fileName) {
            return;
        }

        $adminRoutesPath = dirname($fileName).'/Routes/admin.php';

        if (file_exists($adminRoutesPath)) {
            $adminPath = (string) Config::get('cms.admin_path', 'admin');

            Route::prefix($adminPath)
                ->name('admin.')
                ->middleware([
                    'web',
                    EnsureAdminAuthenticated::class,
                    SetBackofficeLocale::class,
                    "module.enabled:{$this->identifier}",
                ])
                ->group($adminRoutesPath);
        }
    }

    /**
     * Returns the settings schema definition for this module.
     * By default, loads the 'settings' definition from module.json manifest.
     *
     * @return array<string, array{type: string, label: string, description?: string, placeholder?: string, required?: bool, secret?: bool, default?: mixed, options?: array<string, string>}>
     */
    public function getSettingsSchema(): array
    {
        $reflector = new \ReflectionClass($this);
        $fileName = $reflector->getFileName();
        if ($fileName) {
            $manifestPath = dirname($fileName).'/module.json';
            if (file_exists($manifestPath)) {
                $manifest = json_decode((string) file_get_contents($manifestPath), true);
                if (is_array($manifest) && isset($manifest['settings']) && is_array($manifest['settings'])) {
                    /** @var array<string, array{type: string, label: string, description?: string, placeholder?: string, required?: bool, secret?: bool, default?: mixed, options?: array<string, string>}> $settings */
                    $settings = $manifest['settings'];

                    return $settings;
                }
            }
        }

        return [];
    }

    /**
     * Helper to retrieve a setting value for this module.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        if ($this->app->bound(ModuleSettingsServiceInterface::class)) {
            return $this->app->make(ModuleSettingsServiceInterface::class)->get($this->identifier, $key, $default);
        }

        return config("modules.{$this->identifier}.{$key}", $default);
    }
}
