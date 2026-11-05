<?php

declare(strict_types=1);

namespace DbCms\Core\Services\Cms;

use DbCms\Core\Contracts\Services\AdminThemeServiceInterface;
use DbCms\Core\Extensions\ComposerPackages;
use DbCms\Core\Themes\ThemeManifestReader;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;

class AdminThemeService implements AdminThemeServiceInterface
{
    protected ?string $activeTheme = null;

    /**
     * Theme whose view paths are currently registered (guards re-registration).
     */
    private ?string $registeredTheme = null;

    public function __construct(
        private readonly ThemeManifestReader $manifests,
    ) {}

    /**
     * Get the currently active backoffice theme identifier.
     */
    public function getActiveTheme(): string
    {
        return $this->activeTheme ?? strtolower((string) config('cms.admin_theme', 'dbcms'));
    }

    /**
     * Reset the memoised active theme so it is re-read from config.
     *
     * Used when the configured theme changes within a long-lived process.
     */
    public function resetActiveTheme(): void
    {
        $this->activeTheme = null;
    }

    /**
     * Set the currently active backoffice theme identifier and refresh view paths.
     */
    public function setActiveTheme(string $theme): void
    {
        $this->activeTheme = strtolower($theme);
        config(['cms.admin_theme' => $this->activeTheme]);
        $this->registerThemeViewPaths();
    }

    /**
     * List all discovered backoffice themes.
     *
     * @return array<string, array{name: string, identifier: string, path: string, version: string, description: string, author: string}>
     */
    public function getAvailableThemes(): array
    {
        $themes = [];
        foreach (app(ComposerPackages::class)->all('dbcms-admin-theme') as $identifier => $package) {
            $themes[$identifier] = $this->getThemeManifest($identifier);
        }

        return $themes;
    }

    /**
     * Read a backoffice theme manifest (theme.json) with sensible fallbacks.
     *
     * @return array{name: string, identifier: string, path: string, version: string, description: string, author: string, brand: array{name: string, tagline: string, logo: string|null, icon: string|null, edition: string|null, accent: string, mode: string}}
     */
    public function getThemeManifest(string $theme): array
    {
        $theme = strtolower($theme);
        $dir = $this->getThemePath($theme);

        $manifest = [
            'name' => ucfirst($theme),
            'identifier' => $theme,
            'path' => $dir,
            'version' => '1.0.0',
            'description' => '',
            'author' => '',
            'brand' => self::defaultBrand(ucfirst($theme)),
        ];

        $decoded = $this->manifests->read($dir);
        if ($decoded === []) {
            return $manifest;
        }

        $brand = is_array($decoded['brand'] ?? null) ? $decoded['brand'] : [];

        return [
            'name' => (string) ($decoded['name'] ?? $manifest['name']),
            'identifier' => (string) ($decoded['identifier'] ?? $theme),
            'path' => $dir,
            'version' => (string) (app(ComposerPackages::class)->all('dbcms-admin-theme')[$theme]['version']),
            'description' => (string) ($decoded['description'] ?? ''),
            'author' => (string) ($decoded['author'] ?? ''),
            'brand' => [
                'name' => (string) ($brand['name'] ?? $decoded['name'] ?? $manifest['brand']['name']),
                'tagline' => ! empty($brand['tagline']) ? trim((string) $brand['tagline']) : '',
                'logo' => ! empty($brand['logo']) ? (string) $brand['logo'] : null,
                'icon' => ! empty($brand['icon']) ? (string) $brand['icon'] : null,
                'edition' => ! empty($brand['edition']) ? trim((string) $brand['edition']) : null,
                'accent' => $this->normalizeAccent($brand['accent'] ?? null),
                'mode' => in_array($brand['mode'] ?? 'dark', ['light', 'dark'], true) ? (string) ($brand['mode'] ?? 'dark') : 'dark',
            ],
        ];
    }

    /**
     * Default `brand` block for themes that do not declare one.
     *
     * @return array{name: string, tagline: string, logo: string|null, icon: string|null, edition: string|null, accent: string, mode: string}
     */
    public static function defaultBrand(string $name): array
    {
        return [
            'name' => $name,
            'tagline' => '',
            'logo' => null,
            'icon' => null,
            'edition' => null,
            'accent' => '#38BDF8',
            'mode' => 'dark',
        ];
    }

    /**
     * Normalizes a manifest accent to a `#RGB`/`#RRGGBB` value with a safe fallback.
     */
    private function normalizeAccent(mixed $accent): string
    {
        if (is_string($accent) && preg_match('/^#?[0-9A-Fa-f]{3,8}$/', $accent) === 1) {
            return str_starts_with($accent, '#') ? $accent : '#'.$accent;
        }

        return '#38BDF8';
    }

    /**
     * Resolve the web URL of an asset shipped inside a backoffice theme.
     */
    public function themeAsset(string $theme, string $path): string
    {
        $path = ltrim($path, '/');

        if (Route::has('theme.asset')) {
            return route('theme.asset', ['theme' => $theme, 'path' => $path]);
        }

        return asset("themes/admin/{$theme}/{$path}");
    }

    /**
     * Get the absolute filesystem path for a specific admin theme.
     */
    public function getThemePath(string $theme): string
    {
        $package = app(ComposerPackages::class)->all('dbcms-admin-theme')[$theme] ?? null;
        if ($package === null) {
            throw new \DomainException('The selected admin theme is not installed.');
        }

        return $package['directory'];
    }

    /**
     * Determine whether an admin theme directory exists.
     */
    public function themeExists(string $theme): bool
    {
        return isset($this->getAvailableThemes()[strtolower($theme)]);
    }

    /**
     * Register cascading view locations and component paths for the active backoffice theme.
     *
     * Idempotent: re-registering the same theme is a no-op, and switching themes
     * correctly re-points the view finder (needed by long-lived workers and tests).
     */
    public function registerThemeViewPaths(): void
    {
        $activeTheme = $this->getActiveTheme();

        if ($this->registeredTheme === $activeTheme) {
            return;
        }

        $themePath = $this->getThemePath($activeTheme);

        if (File::isDirectory($themePath)) {
            /** @var FileViewFinder $finder */
            $finder = View::getFinder();

            // Strip any previously registered themes/admin paths to prevent stale theme leakage
            $currentPaths = array_values(array_filter(
                $finder->getPaths(),
                fn (string $path): bool => ! in_array(realpath($path), array_map(static fn (array $theme): string|false => realpath($theme['directory']), app(ComposerPackages::class)->all('dbcms-admin-theme')), true)
            ));

            $finder->setPaths(array_values(array_unique([resource_path('views/themes/admin/'.$activeTheme), resource_path('views'), $themePath, ...$currentPaths])));
            $finder->flush();

            $componentsPath = $themePath.'/admin/components';
            if (File::isDirectory($componentsPath)) {
                Blade::anonymousComponentPath($componentsPath, 'admin');
            }
        }

        $this->registeredTheme = $activeTheme;
    }

    /**
     * Register cascading theme override paths for a specific module's view namespace.
     */
    public function registerModuleThemePaths(string $moduleIdentifier, string $moduleBaseViewPath): void
    {
        $activeTheme = $this->getActiveTheme();
        $themePath = $this->getThemePath($activeTheme);

        // 1. Check if the module provides an internal theme variant (e.g. modules/{Module}/Resources/views/themes/{activeTheme})
        $moduleInternalThemePath = $moduleBaseViewPath."/themes/{$activeTheme}";
        if (File::isDirectory($moduleInternalThemePath)) {
            View::prependNamespace($moduleIdentifier, $moduleInternalThemePath);
        }

        // 2. Check if the active backoffice theme provides a global override for this module
        $themeModulePath = $themePath."/modules/{$moduleIdentifier}";
        if (File::isDirectory($themeModulePath)) {
            View::prependNamespace($moduleIdentifier, $themeModulePath);
        }
        foreach ([resource_path('views/vendor/'.$moduleIdentifier), resource_path('views/themes/admin/'.$activeTheme.'/modules/'.$moduleIdentifier)] as $override) {
            if (is_dir($override)) {
                View::prependNamespace($moduleIdentifier, $override);
            }
        }

    }
}
