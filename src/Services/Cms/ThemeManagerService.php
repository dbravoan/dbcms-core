<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\GeneralSettingsServiceInterface;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Themes\ThemeManifestReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

class ThemeManagerService
{
    public const ACTIVE_THEME_SETTING = 'cms.active_theme';

    /**
     * Last theme whose view resolution was synced.
     */
    private ?string $syncedTheme = null;

    public function __construct(
        private readonly ThemeManifestReader $manifests,
        private readonly GeneralSettingsServiceInterface $settings,
        private readonly PageCacheService $pageCache,
    ) {}

    /**
     * Clears cached view resolution when the active theme changes.
     *
     * Settings persist panel activation independently of cached deployment config.
     * Refresh them at request boundaries for long-lived application workers too.
     */
    public function syncActiveTheme(): void
    {
        $this->settings->flushCache();
        $theme = $this->getActiveTheme();
        config(['cms.theme' => $theme]);

        if ($this->syncedTheme === $theme) {
            return;
        }

        $this->syncedTheme = $theme;

        try {
            $factory = View::getFacadeRoot();

            // Drop memoised resolved views so a theme switch inside a long-lived
            // process is picked up. Compiled Blade files are keyed by view path,
            // so they are NOT deleted on every request; an explicit switch
            // flushes them through flushCompiledViews().
            $factory->flushState();
            $factory->getFinder()->flush();
        } catch (\Throwable) {
            // View factory not ready (e.g. during early boot).
        }
    }

    /**
     * Drop the compiled Blade cache. Only needed when the active theme changes.
     */
    public function flushCompiledViews(): void
    {
        $directory = (string) config('view.compiled', storage_path('framework/views'));
        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR).'/*.php') ?: [] as $compiled) {
            @unlink($compiled);
        }
    }

    /**
     * Gets list of available themes in themes/ directory.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAvailableThemes(): array
    {
        $themes = [];
        foreach (app(ComposerPackages::class)->all('dbcms-theme') as $identifier => $package) {
            $themes[$identifier] = array_replace($this->manifests->read($package['directory']), $package);
        }

        return $themes;
    }

    /**
     * Returns currently active theme identifier.
     */
    public function getActiveTheme(): string
    {
        $default = (string) 'db-cyber';
        $configured = (string) config('cms.theme', $default);
        $stored = $this->settings->get(self::ACTIVE_THEME_SETTING, $configured);

        foreach ([$stored, $configured, $default] as $candidate) {
            if (is_string($candidate) && $this->isInstalledTheme($candidate)) {
                return $candidate;
            }
        }

        return array_key_first($this->getAvailableThemes()) ?? $default;
    }

    /**
     * Activate only installed themes, retaining deployment-owned configuration.
     */
    public function setActiveTheme(string $themeIdentifier): bool
    {
        if (! $this->isInstalledTheme($themeIdentifier) || $this->manifests->read($this->path($themeIdentifier)) === []) {
            throw ValidationException::withMessages(['theme' => 'El tema seleccionado no está instalado en este proyecto.']);
        }

        DB::transaction(fn () => $this->settings->set(self::ACTIVE_THEME_SETTING, $themeIdentifier, 'appearance'));
        config(['cms.theme' => $themeIdentifier]);
        $this->flushCompiledViews();
        $this->syncActiveTheme();
        $this->pageCache->invalidate();

        return true;
    }

    private function isInstalledTheme(string $identifier): bool
    {
        return isset($this->getAvailableThemes()[$identifier]);
    }

    public function path(string $identifier): string
    {
        $package = $this->getAvailableThemes()[$identifier] ?? null;
        if ($package === null) {
            throw new \DomainException('The selected public theme is not installed.');
        }

        return $package['directory'];
    }
}
